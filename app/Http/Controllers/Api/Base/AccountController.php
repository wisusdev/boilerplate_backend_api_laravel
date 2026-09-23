<?php

namespace App\Http\Controllers\Api\Base;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountUpdateRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use App\Notifications\DeleteAccountConfirmationNotification;
use App\Notifications\EmailChangeNotification;
use App\Notifications\PasswordChangeNotification;
use App\Notifications\VerifyDeleteAccountNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function profile(Request $request): JsonResource
    {
        return ProfileResource::make($request->user());
    }

    public function updateProfile(AccountUpdateRequest $request): JsonResource
    {
        $user = $request->user();
        // Se captura ANTES del update: comparándolo después la condición era
        // siempre falsa, el correo cambiaba y la cuenta seguía "verificada".
        $originalEmail = $user->email;
        $emailChanging = $request->input('data.attributes.email') !== $originalEmail;

        // El correo es el destino de la recuperación de cuenta: cambiarlo con
        // solo un bearer token (sin volver a pedir la contraseña) permitía que
        // un token robado se convirtiera en secuestro permanente de la cuenta
        // vía "cambiar correo" + "olvidé mi contraseña". AccountUpdateRequest
        // ya exige current_password cuando el correo cambia; aquí se verifica.
        if ($emailChanging && ! Hash::check((string) $request->input('data.attributes.current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['validation.passwordIncorrect'],
            ]);
        }

        if ($request->has('data.attributes.avatar') && $request->input('data.attributes.avatar')) {
            // El avatar anterior lo retira UserObserver::updated() al detectar el
            // cambio de columna, de modo que el fichero y la fila no se separen.
            $avatarName = $this->processAvatarUpload($request->input('data.attributes.avatar'));
        }

        $user->update([
            'first_name' => $request->input('data.attributes.first_name'),
            'last_name' => $request->input('data.attributes.last_name'),
            'email' => $request->input('data.attributes.email'),
            'avatar' => $avatarName ?? $user->avatar,
            'language' => $request->input('data.attributes.language'),
            'phone' => $request->input('data.attributes.phone'),
            'phone_secondary' => $request->input('data.attributes.phone_secondary'),
        ]);

        if ($originalEmail !== $user->email) {
            $user->email_verified_at = null;
            $user->save(['timestamps' => false]);
            $user->sendEmailVerificationNotification();

            // Igual que un cambio de contraseña: cierra el resto de sesiones
            // (se conserva la actual) y avisa a la dirección ANTERIOR, no solo
            // a la nueva, para que el titular real note un cambio que no hizo.
            $currentTokenId = $user->token()?->id;
            $user->tokens()
                ->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))
                ->update(['revoked' => true]);

            Notification::route('mail', $originalEmail)->notify(new EmailChangeNotification($user->email));
        }

        return ProfileResource::make($user);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {

        $user = $request->user();

        if (! Hash::check($request->input('data.attributes.current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['validation.passwordIncorrect'],
            ]);
        }

        $user->update([
            'password' => $request->input('data.attributes.password'),
        ]);

        // Cambiar la contraseña cierra el resto de sesiones; se conserva la actual
        // para que el usuario no quede desconectado del dispositivo que usa.
        $currentTokenId = $user->token()?->id;
        $user->tokens()
            ->when($currentTokenId, fn ($q) => $q->where('id', '!=', $currentTokenId))
            ->update(['revoked' => true]);

        $user->notify(new PasswordChangeNotification);

        return response()->json([
            'data' => [
                'type' => 'change-password',
                'attributes' => [
                    'status' => true,
                    'message' => 'message.passwordChangedSuccessfully',
                ],
            ],
        ]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = Str::random(60);

        // Tabla propia: compartirla con password_reset_tokens hacía que un token
        // de restablecimiento sirviera para borrar la cuenta.
        DB::table('account_deletion_tokens')->updateOrInsert(['email' => $user->email], [
            'token' => hash('sha256', $token),
            'created_at' => now(),
        ]);

        $url = config('app.frontend_url').'/account/delete-account-verify?token='.$token;

        // Send email
        $user->notify(new VerifyDeleteAccountNotification($url, $user->first_name));

        return response()->json([
            'data' => [
                'type' => 'delete-account',
                'attributes' => [
                    'status' => true,
                    'message' => 'message.deleteAccountEmailSent',
                ],
            ],
        ]);
    }

    public function deleteAccountVerify(Request $request): JsonResponse
    {
        $token = (string) $request->input('data.attributes.token');
        $account = DB::table('account_deletion_tokens')
            ->where('token', hash('sha256', $token))
            ->first();

        // verify
        if (! $account) {
            throw ValidationException::withMessages([
                'token' => ['validation.tokenInvalid'],
            ]);
        }

        // Validate expire token
        if (now()->greaterThan(now()->parse($account->created_at)->addHours(6))) {
            DB::table('account_deletion_tokens')->where('email', $account->email)->delete();

            throw ValidationException::withMessages([
                'token' => ['validation.tokenExpired'],
            ]);
        }

        $user = User::whereEmail($account->email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => ['validation.userNotFound'],
            ]);
        }

        // El enlace solo puede consumirlo el titular de la cuenta autenticado.
        if ($request->user()->getKey() !== $user->getKey()) {
            abort(403);
        }

        DB::table('account_deletion_tokens')->where('email', $user->email)->delete();

        // Send email to a user
        $user->notify(new DeleteAccountConfirmationNotification);

        // UserObserver revoca las sesiones. El avatar se conserva mientras la
        // fila exista (soft delete); solo se borra en un forceDelete.
        $user->delete();

        return response()->json(['message' => 'message.accountDeletedSuccessfully']);
    }

    private function processAvatarUpload(string $base64Data): string
    {
        // Extraer el tipo MIME y los datos base64
        if (! preg_match('/^data:([a-zA-Z0-9][a-zA-Z0-9\/+]*);base64,(.+)$/', $base64Data, $matches)) {
            throw new \InvalidArgumentException('Formato base64 inválido');
        }

        $mimeType = $matches[1];
        $imageData = base64_decode($matches[2]);

        if ($imageData === false) {
            throw new \InvalidArgumentException('Datos base64 inválidos');
        }

        // Crear imagen desde string
        $image = imagecreatefromstring($imageData);

        if ($image === false) {
            throw new \InvalidArgumentException('No se pudo crear la imagen desde los datos base64');
        }

        // Generar nombre único para el archivo
        $fileName = config('app.destination_path').'/'.Str::uuid().'.webp';
        $fullPath = storage_path('app/public/'.$fileName);

        // Crear directorio si no existe
        $directory = dirname($fullPath);
        if (! file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        // Convertir y guardar como WebP
        if (! imagewebp($image, $fullPath, 80)) { // 80 es la calidad (0-100)
            imagedestroy($image);
            throw new \RuntimeException('Error al guardar la imagen como WebP');
        }

        // Limpiar memoria
        imagedestroy($image);

        return $fileName;
    }
}
