<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use App\Notifications\ForgotPassword;
use App\Notifications\PasswordChangeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ForgotController extends Controller
{
    /**
     * Minutos de validez del enlace de restablecimiento.
     */
    private function ttlMinutes(): int
    {
        return (int) config('auth.passwords.users.expire', 60);
    }

    /**
     * Envía el enlace de restablecimiento.
     *
     * La respuesta es SIEMPRE la misma exista o no la cuenta: antes, un correo
     * desconocido devolvía un error distinto y permitía enumerar usuarios.
     */
    public function forgot(ForgotRequest $request): JsonResponse
    {
        $email = $request->input('data.attributes.email');
        $user = User::whereEmail($email)->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(['email' => $email], [
                // Solo se guarda el hash: con acceso de lectura a la BD ya no se
                // pueden tomar cuentas usando los tokens almacenados.
                'token' => hash('sha256', $token),
                'created_at' => now(),
            ]);

            $url = config('app.frontend_url').'/auth/reset-password?token='.$token;

            $user->notify(new ForgotPassword($url, $user->first_name));
        }

        return response()->json([
            'data' => [
                'type' => 'users',
                'attributes' => [
                    'status' => true,
                    'message' => 'message.resetPasswordEmailSent',
                ],
            ],
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $token = (string) $request->input('data.attributes.token');

        $passwordReset = DB::table('password_reset_tokens')
            ->where('token', hash('sha256', $token))
            ->first();

        if (! $passwordReset) {
            throw ValidationException::withMessages([
                'token' => ['validation.tokenInvalid'],
            ]);
        }

        // `created_at` es el momento de emisión; el enlace caduca a los N minutos.
        if (now()->greaterThan(now()->parse($passwordReset->created_at)->addMinutes($this->ttlMinutes()))) {
            DB::table('password_reset_tokens')->where('email', $passwordReset->email)->delete();

            throw ValidationException::withMessages([
                'token' => ['validation.tokenExpired'],
            ]);
        }

        $user = User::whereEmail($passwordReset->email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => ['validation.userNotFound'],
            ]);
        }

        // Asigna la nueva contraseña (el cast 'hashed' del modelo la encripta).
        $user->password = $request->input('data.attributes.password');
        $user->save();

        // Un restablecimiento invalida las sesiones abiertas: si la cuenta estaba
        // comprometida, cambiar la contraseña debe expulsar al intruso.
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        // Notifica al usuario que su contraseña fue restablecida.
        $user->notify(new PasswordChangeNotification);

        return response()->json([
            'data' => [
                'type' => 'users',
                'attributes' => [
                    'status' => true,
                    'message' => 'message.passwordResetSuccess',
                ],
            ],
        ]);
    }
}
