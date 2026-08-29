<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Ciclo de vida del avatar y de las sesiones de un usuario.
 *
 * El avatar se borraba del disco al hacer un *soft delete*, con lo que la fila
 * seguía guardando una ruta a un fichero inexistente. Ahora el fichero solo
 * desaparece cuando el registro desaparece de verdad (`forceDelete`) o cuando se
 * sustituye por otro.
 */
class UserObserver
{
    /** Borra el avatar anterior cuando se sustituye por uno nuevo. */
    public function updated(User $user): void
    {
        if (! $user->wasChanged('avatar')) {
            return;
        }

        $this->deleteAvatarFile($user->getOriginal('avatar'));
    }

    /** Soft delete: se revocan las sesiones, pero los ficheros se conservan. */
    public function deleted(User $user): void
    {
        if ($user->isForceDeleting()) {
            return;
        }

        $user->tokens()->update(['revoked' => true]);
    }

    /** Borrado definitivo: ahora sí, fuera el avatar y los tokens OAuth. */
    public function forceDeleted(User $user): void
    {
        $this->deleteAvatarFile($user->avatar);

        $tokenIds = $user->tokens()->pluck('id');

        if ($tokenIds->isNotEmpty()) {
            DB::table('oauth_refresh_tokens')->whereIn('access_token_id', $tokenIds)->delete();
        }

        $user->tokens()->delete();
    }

    private function deleteAvatarFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
