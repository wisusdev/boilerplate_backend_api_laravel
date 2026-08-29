<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriberRequest;
use App\Http\Resources\SubscriberResource;
use App\Models\Setting;
use App\Models\Subscriber;
use App\Notifications\AdminAlertNotification;
use App\Notifications\SubscriberWelcomeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SubscriberController extends Controller
{
    /**
     * Listado para el admin (leads / suscriptores a ofertas).
     * Filtros: filter[status]=subscribed|unsubscribed, filter[search]=...
     */
    public function index(): AnonymousResourceCollection
    {
        $subscribers = Subscriber::query()
            ->allowedFilters(['status', 'search'])
            ->allowedSorts(['created_at', 'email', 'status'])
            ->when(! request()->filled('sort'), fn ($q) => $q->latest())
            ->sparseFieldset()
            ->jsonPaginate();

        return SubscriberResource::collection($subscribers);
    }

    /**
     * Alta pública de suscripción a ofertas (idempotente).
     * Si el correo ya existe y estaba dado de baja, lo reactiva.
     */
    public function store(SubscriberRequest $request): JsonResponse
    {
        // El módulo puede desactivarse desde ajustes (app.offers_subscription_enabled).
        abort_unless($this->subscriptionEnabled(), 403, 'La suscripción a ofertas está desactivada.');

        $attrs = $request->validated()['data']['attributes'];
        $email = Str::lower(trim($attrs['email']));

        $subscriber = Subscriber::firstOrNew(['email' => $email]);
        $isNew = ! $subscriber->exists;
        $wasUnsubscribed = ! $isNew && $subscriber->status === Subscriber::STATUS_UNSUBSCRIBED;

        // No sobrescribimos datos previos con vacíos; reactivamos si estaba de baja.
        $subscriber->name = $attrs['name'] ?? $subscriber->name;
        $subscriber->source = $attrs['source'] ?? $subscriber->source ?? 'web';
        $subscriber->locale = $attrs['locale'] ?? $subscriber->locale;
        $subscriber->status = Subscriber::STATUS_SUBSCRIBED;
        $subscriber->unsubscribed_at = null;
        $subscriber->save();

        // Correos solo en alta nueva o reactivación (evita spam en reenvíos).
        if ($isNew || $wasUnsubscribed) {
            $this->sendSubscriptionEmails($subscriber);
        }

        // Mismo código siempre: 201 vs 200 revelaba si el correo ya estaba dado
        // de alta y permitía enumerar suscriptores.
        return SubscriberResource::make($subscriber)
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Token de baja ligado al correo y derivado de APP_KEY. No necesita columna
     * en base de datos y no es adivinable.
     */
    public static function unsubscribeToken(string $email): string
    {
        return hash_hmac('sha256', Str::lower(trim($email)), (string) config('app.key'));
    }

    /**
     * Lee el flag del módulo desde los ajustes (por defecto habilitado).
     */
    private function subscriptionEnabled(): bool
    {
        $app = json_decode(optional(Setting::where('key', 'app')->first())->value ?? '{}', true) ?? [];

        return ($app['offers_subscription_enabled'] ?? true) !== false;
    }

    /**
     * Envía la bienvenida al suscriptor y el aviso a los admins.
     * Cualquier fallo de correo se registra pero NO afecta la suscripción.
     */
    private function sendSubscriptionEmails(Subscriber $subscriber): void
    {
        try {
            Notification::route('mail', $subscriber->email)
                ->notify(new SubscriberWelcomeNotification($subscriber->name, $subscriber->email));

            $adminEmails = config('services.notifications.admin_emails', []);
            foreach ($adminEmails as $email) {
                Notification::route('mail', $email)->notify(new AdminAlertNotification(
                    'Nuevo suscriptor a ofertas',
                    'Un nuevo lead se ha suscrito para recibir ofertas.',
                    [
                        'email' => $subscriber->email,
                        'name' => $subscriber->name ?? '—',
                        'source' => $subscriber->source ?? '—',
                    ]
                ));
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudieron enviar los correos de suscripción: '.$e->getMessage(), [
                'subscriber_id' => $subscriber->id,
            ]);
        }
    }

    /**
     * Baja pública por correo. Siempre responde 200 (no revela si existía).
     */
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'token' => ['required', 'string'],
        ]);

        $email = Str::lower(trim($validated['email']));

        // Sin token, cualquiera podía dar de baja el correo de otra persona.
        if (! hash_equals(self::unsubscribeToken($email), $validated['token'])) {
            abort(403);
        }

        Subscriber::where('email', $email)->update([
            'status' => Subscriber::STATUS_UNSUBSCRIBED,
            'unsubscribed_at' => now(),
        ]);

        return response()->json([
            'data' => [
                'type' => 'subscribe_result',
                'attributes' => ['unsubscribed' => true],
            ],
        ]);
    }

    /**
     * Admin: cambia el estado (suscrito / dado de baja).
     */
    public function update(Request $request, Subscriber $subscriber): SubscriberResource
    {
        $validated = $request->validate([
            'data.attributes.status' => ['required', 'string', 'in:subscribed,unsubscribed'],
        ]);

        $status = $validated['data']['attributes']['status'];
        $subscriber->update([
            'status' => $status,
            'unsubscribed_at' => $status === Subscriber::STATUS_UNSUBSCRIBED ? now() : null,
        ]);

        return SubscriberResource::make($subscriber->fresh());
    }

    public function destroy(Subscriber $subscriber): JsonResponse
    {
        $subscriber->delete();

        return response()->json(null, 204);
    }
}
