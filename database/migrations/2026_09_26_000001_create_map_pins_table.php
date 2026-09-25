<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Mapa público de pines personalizados: lugares con información de los tours,
 * un video de Instagram que se reproduce en el propio mapa o un enlace.
 *
 * Permisos `map-pins:*` para quien ya gestiona el catálogo público (admin y
 * editor, los que tienen `gallery:store`).
 */
return new class extends Migration
{
    private const PERMISOS = ['map-pins:store', 'map-pins:update', 'map-pins:delete'];

    public function up(): void
    {
        Schema::create('map_pins', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            // Aspecto del pin: icono de Bootstrap Icons (de una lista cerrada) y color.
            $table->string('icon', 40)->default('geo-alt-fill');
            $table->string('color', 7)->default('#184ca0');
            $table->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();
            // Post o reel de Instagram: se guarda solo lo necesario para incrustarlo.
            $table->string('instagram_url', 255)->nullable();
            $table->string('instagram_type', 10)->nullable();   // p | reel | tv
            $table->string('instagram_code', 40)->nullable();
            // Cualquier otro enlace (YouTube, TikTok, una web…): se abre aparte.
            $table->string('link_url', 500)->nullable();
            $table->string('link_label', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $this->grantPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', self::PERMISOS)->where('guard_name', 'api')->delete();
        Schema::dropIfExists('map_pins');
    }

    /**
     * Una instalación existente ya sembró sus permisos: los nuevos se crean
     * aquí y se asignan a los roles que gestionan la galería.
     */
    private function grantPermissions(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }
        $galeria = DB::table('permissions')->where('name', 'gallery:store')->where('guard_name', 'api')->value('uuid');
        if (! $galeria) {
            return; // Instalación nueva: los siembra PermissionSeeder.
        }

        $roles = DB::table('role_has_permissions')->where('permission_id', $galeria)->pluck('role_id');
        foreach (self::PERMISOS as $nombre) {
            $uuid = DB::table('permissions')->where('name', $nombre)->where('guard_name', 'api')->value('uuid');
            if (! $uuid) {
                $uuid = (string) Str::uuid();
                DB::table('permissions')->insert([
                    'uuid' => $uuid, 'name' => $nombre, 'guard_name' => 'api',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($roles as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $uuid, 'role_id' => $roleId]);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
