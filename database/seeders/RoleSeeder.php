<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Presets de permisos por rol.
     *
     * - 'superadmin' NO aparece aquí: pasa cualquier autorización vía Gate::before
     *   (AuthServiceProvider), no depende de filas de permiso.
     * - 'admin' recibe TODOS los permisos existentes (grant-all).
     * - 'user' no tiene permisos admin; opera solo sobre sus propios datos (ownership).
     *
     * @return array<string, array<int, string>>
     */
    public static function presets(): array
    {
        return [
            // Finanzas: gastos, facturas, reportes y visibilidad total de pagos.
            'finanzas' => [
                'finance:view',
                'reports:view',
                'expenses:index', 'expenses:store', 'expenses:update', 'expenses:delete', 'expenses:view-all',
                'expense-categories:index', 'expense-categories:store', 'expense-categories:update', 'expense-categories:delete',
                'invoices:index', 'invoices:show', 'invoices:store', 'invoices:update',
                'invoices:delete', 'invoices:generate-dte', 'invoices:invalidate-dte',
                'payments:view-all', 'payments:mark-paid',
                'payments:issue-link', 'payments:confirm-link',
            ],

            // Editor de contenido: catálogo público (tours, transporte, categorías,
            // monedas, testimonios, reseñas, galería).
            'editor' => [
                'tours:store', 'tours:update', 'tours:media', 'tours:delete',
                'transport-vehicles:store', 'transport-vehicles:update', 'transport-vehicles:media',
                'transport-vehicles:delete',
                'tour-categories:store', 'tour-categories:update', 'tour-categories:delete',
                'currencies:store', 'currencies:update',
                'reviews:store', 'reviews:update', 'reviews:delete',
                'product-reviews:moderate',
                'gallery:store', 'gallery:delete', 'gallery:reorder',
                'map-pins:store', 'map-pins:update', 'map-pins:delete',
            ],

            // Guía: registra y consulta únicamente SUS gastos (el ownership por
            // guide_id se aplica en ExpenseController).
            'guia' => [
                'expenses:index',
                'expenses:store',
            ],

            // Cliente final: sin permisos admin.
            'user' => [],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Idempotente: permite re-ejecutar el seeder y que el instalador lo invoque.

        // superadmin: rol god (bypass vía Gate::before). Solo asegura su existencia.
        Role::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'api']);

        // admin: todos los permisos existentes.
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);
        $admin->syncPermissions(Permission::all());

        // Roles con preset de permisos.
        foreach (self::presets() as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);

            // Solo sincroniza permisos que existan en BD (evita fallo si falta el seed base).
            $valid = Permission::whereIn('name', $permissions)->pluck('name')->all();
            $role->syncPermissions($valid);

            // Aviso en consola si algún permiso del preset no existe todavía.
            $missing = array_diff($permissions, $valid);
            if (! empty($missing)) {
                $this->command?->warn("RoleSeeder: permisos inexistentes para '{$roleName}': ".implode(', ', $missing));
            }
        }

        // Descarta cualquier caché de permisos de spatie tras el sync.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
