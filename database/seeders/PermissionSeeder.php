<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Catálogo central de permisos del sistema.
     *
     * Convención: 'modulo:accion'. Este array es la única fuente de verdad;
     * RoleSeeder compone los roles a partir de estos nombres.
     *
     * @return array<int, string>
     */
    public static function permissions(): array
    {
        return [
            // ── Base: roles ──
            'roles:index',
            'roles:create',
            'roles:store',
            'roles:show',
            'roles:edit',
            'roles:update',
            'roles:delete',

            // ── Base: permissions ──
            'permissions:index',
            'permissions:by-role',

            // ── Base: users ──
            'users:index',
            'users:create',
            'users:store',
            'users:show',
            'users:edit',
            'users:update',
            'users:delete',

            // ── Travel: tours ──
            'tours:store',
            'tours:update',
            'tours:media',

            // ── Travel: transporte ──
            'transport-vehicles:store',
            'transport-vehicles:update',
            'transport-vehicles:media',

            // ── Travel: categorías de tour ──
            'tour-categories:store',
            'tour-categories:update',
            'tour-categories:delete',

            // ── Travel: monedas ──
            'currencies:store',

            // ── Travel: testimonios (reviews) ──
            'reviews:store',
            'reviews:update',
            'reviews:delete',

            // ── Travel: reseñas de producto (moderación) ──
            'product-reviews:moderate',

            // ── Travel: cupones ──
            'coupons:index',
            'coupons:store',
            'coupons:update',
            'coupons:delete',

            // ── Travel: finanzas ──
            'finance:view',

            // ── Travel: categorías de gasto ──
            'expense-categories:index',
            'expense-categories:store',
            'expense-categories:update',
            'expense-categories:delete',

            // ── Travel: gastos ──
            'expenses:index',
            'expenses:store',
            'expenses:update',
            'expenses:delete',
            // Ver TODOS los gastos (no solo los propios). Sin este permiso, un
            // usuario con 'expenses:index' (p. ej. rol 'guia') solo ve/crea los suyos.
            'expenses:view-all',

            // ── Travel: facturas / DTE ──
            'invoices:index',
            'invoices:show',
            'invoices:update',
            'invoices:generate-dte',

            // ── Travel: ajustes ──
            'settings:update',

            // ── Travel: reportes ──
            'reports:view',

            // ── Travel: galería ──
            'gallery:store',
            'gallery:delete',
            'gallery:reorder',

            // ── Travel: suscriptores (leads) ──
            'subscribers:index',
            'subscribers:update',
            'subscribers:delete',

            // ── Travel: consultas personalizadas ──
            'custom-inquiries:index',
            'custom-inquiries:show',

            // ── Travel: guías ──
            'guides:index',
            'guides:store',
            'guides:delete',

            // ── Travel: reservas / pagos (alcance admin: ver TODO, no solo lo propio) ──
            'bookings:view-all',
            'payments:view-all',
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Idempotente: permite re-ejecutar el seeder y que el instalador lo invoque.
        foreach (self::permissions() as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }
    }
}
