<?php

namespace App\Http\Controllers\Api\Base;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountUpdateRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use App\Notifications\DeleteAccountConfirmationNotification;
use App\Notifications\PasswordChangeNotification;
use App\Notifications\VerifyDeleteAccountNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

        if ($request->has('data.attributes.avatar') && $request->input('data.attributes.avatar')) {

            if (isset($user->avatar) && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $avatarName = $this->processAvatarUpload($request->input('data.attributes.avatar'));
        }

        $user->update([
            'first_name' => $request->input('data.attributes.first_name'),
            'last_name' => $request->input('data.attributes.last_name'),
            'email' => $request->input('data.attributes.email'),
            'avatar' => $avatarName ?? $user->avatar,
            'language' => $request->input('data.attributes.language')
        ]);

        if ($user->email !== $request->input('data.attributes.email')) {
            $user->email_verified_at = null;
            $user->save(['timestamps' => false]);
            $user->sendEmailVerificationNotification();
        }

        return ProfileResource::make($user);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {

        $user = $request->user();

        if (!Hash::check($request->input('data.attributes.current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['validation.passwordIncorrect'],
            ]);
        }

        $user->update([
            'password' => $request->input('data.attributes.password')
        ]);

        $user->notify(new PasswordChangeNotification());

        return response()->json([
            'data' => [
                'type' => 'change-password',
                'attributes' => [
                    'status' => true,
                    'message' => 'message.passwordChangedSuccessfully',
                ],
            ]
        ]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = Str::random(60);

        DB::table('password_reset_tokens')->updateOrInsert(['email' => $user->email], [
            'token' => $token,
            'created_at' => now()->addHours(6)
        ]);

        $url = config('app.frontend_url') . '/account/delete-account-verify?token=' . $token;

        // Send email
        $user->notify(new VerifyDeleteAccountNotification($url, $user->first_name));

        return response()->json([
            'data' => [
                'type' => 'delete-account',
                'attributes' => [
                    'status' => true,
                    'message' => 'message.deleteAccountEmailSent',
                ],
            ]
        ]);
    }

    public function deleteAccountVerify(Request $request): JsonResponse
    {
        $token = $request->input('data.attributes.token');
        $account = DB::table('password_reset_tokens')->where('token', $token)->first();

        // verify
        if (!$account) {
            throw ValidationException::withMessages([
                'token' => ['validation.tokenInvalid'],
            ]);
        }

        // Validate expire token
        if ($account->created_at < now()) {
            throw ValidationException::withMessages([
                'token' => ['validation.tokenExpired'],
            ]);
        }

        $user = User::whereEmail($account->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'token' => ['validation.userNotFound'],
            ]);
        }

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        // Send email to a user
        $user->notify(new DeleteAccountConfirmationNotification());

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'message.accountDeletedSuccessfully']);
    }

    private function processAvatarUpload(string $base64Data): string
    {
        // Extraer el tipo MIME y los datos base64
        if (!preg_match('/^data:([a-zA-Z0-9][a-zA-Z0-9\/+]*);base64,(.+)$/', $base64Data, $matches)) {
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
        $fileName = config('app.destination_path') . '/' . Str::uuid() . '.webp';
        $fullPath = storage_path('app/public/' . $fileName);

        // Crear directorio si no existe
        $directory = dirname($fullPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        // Convertir y guardar como WebP
        if (!imagewebp($image, $fullPath, 80)) { // 80 es la calidad (0-100)
            imagedestroy($image);
            throw new \RuntimeException('Error al guardar la imagen como WebP');
        }

        // Limpiar memoria
        imagedestroy($image);

        return $fileName;
    }
}
