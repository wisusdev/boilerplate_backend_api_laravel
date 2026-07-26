<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'user',
            'admin',
            // Guía: miembro del equipo con acceso limitado (p. ej. registrar gastos
            // que se le atribuyen). Los gastos referencian a un usuario con este rol.
            'guia',
        ];

        // Idempotente: permite re-ejecutar el seeder y que el instalador lo invoque.
        foreach ($roles as $role) {
            $newRole = Role::firstOrCreate(['name' => $role, 'guard_name' => 'api']);
            if ($newRole->name === 'admin') {
                $newRole->givePermissionTo(Permission::all());
            }
        }
    }
}
