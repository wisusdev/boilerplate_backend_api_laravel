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
            // 'roles:create' y 'roles:edit' NO existen a propósito: son el
            // patrón de una app web ("permiso para ver el formulario"), que en
            // una API no protege nada — el control real ya lo hacen 'store' y
            // 'update'. Tenerlos sin uso solo confundía a quien configurara un
            // rol. Ver migración 2026_09_01_000003.
            'roles:index',
            'roles:store',
            'roles:show',
            'roles:update',
            'roles:delete',

            // ── Base: permissions ──
            'permissions:index',
            'permissions:by-role',

            // ── Base: users ──
            'users:index',
            'users:store',
            'users:show',
            'users:update',
            'users:delete',

            // ── Travel: tours ──
            'tours:store',
            'tours:update',
            'tours:media',
            'tours:delete',

            // ── Travel: transporte ──
            'transport-vehicles:store',
            'transport-vehicles:update',
            'transport-vehicles:media',
            'transport-vehicles:delete',

            // ── Travel: categorías de tour ──
            'tour-categories:store',
            'tour-categories:update',
            'tour-categories:delete',

            // ── Travel: monedas ──
            'currencies:store',
            'currencies:update',

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
            'invoices:store',
            'invoices:delete',
            'invoices:show',
            'invoices:update',
            'invoices:generate-dte',
            // Anular un DTE sellado ante Hacienda: más delicado que emitirlo.
            'invoices:invalidate-dte',

            // ── Travel: ajustes ──
            'settings:update',

            // ── Travel: reportes ──
            'reports:view',

            // ── Travel: galería ──
            'gallery:store',
            'gallery:delete',
            'gallery:reorder',

            // ── Travel: mapa público de pines ──
            'map-pins:store',
            'map-pins:update',
            'map-pins:delete',

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
            // Dar por cobrado un pago manual (efectivo / transferencia ya recibidos).
            'payments:mark-paid',
            // Enlaces de pago del banco. Emitir y confirmar se separan a
            // propósito: emitir es logística, confirmar mueve dinero sobre la
            // palabra de una persona.
            'payments:issue-link',
            'payments:confirm-link',
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
