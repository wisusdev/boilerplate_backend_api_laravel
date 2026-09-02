<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Limpia cuatro permisos que nunca protegían nada: `roles:create`,
 * `roles:edit`, `users:create` y `users:edit` vienen del patrón de una app
 * web ("permiso para ver el formulario"), que en una API no significa nada —
 * el control real lo hacen `:store` y `:update`. Ningún controlador ni el
 * frontend los consultaba jamás; solo confundían al configurar un rol.
 *
 * `PermissionSeeder` ya no los declara, pero eso solo evita que reaparezcan
 * en una instalación nueva. Una instalación existente los tiene sembrados en
 * la tabla `permissions` desde antes, así que hay que borrarlos aquí. La FK
 * de `role_has_permissions` es `onDelete('cascade')`: al borrar el permiso se
 * limpia solo cualquier rol que lo tuviera asignado.
 */
return new class extends Migration
{
    private const VESTIGIALES = ['roles:create', 'roles:edit', 'users:create', 'users:edit'];

    public function up(): void
    {
        DB::table('permissions')
            ->whereIn('name', self::VESTIGIALES)
            ->where('guard_name', 'api')
            ->delete();
    }

    public function down(): void
    {
        // Re-sembrarlos no tiene sentido: no protegían nada. Si hiciera falta
        // deshacer esta migración, `PermissionSeeder` (en una versión previa)
        // es la referencia.
    }
};
