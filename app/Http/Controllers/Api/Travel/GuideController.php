<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Guías = usuarios con el rol 'guia'. Se reutiliza el sistema de usuarios/roles
 * (Spatie) en lugar de una entidad separada. Los gastos se atribuyen a un guía.
 */
class GuideController extends Controller
{
    /**
     * Lista los usuarios con rol 'guia'.
     */
    public function index(): JsonResponse
    {
        $guides = User::role('guia')->orderBy('first_name')->get();

        return response()->json([
            'data' => $guides->map(fn (User $u) => $this->payload($u))->all(),
        ]);
    }

    /**
     * Otorga el rol 'guia' a un usuario existente.
     * Body: { data: { attributes: { user_id } } }.
     */
    public function store(Request $request): JsonResponse
    {
        $attrs = $request->input('data.attributes', $request->all());

        $data = validator($attrs, [
            'user_id' => ['required', 'uuid', 'exists:users,id'],
        ])->validate();

        $this->assertCanGrantGuideRole($request, $data['user_id']);

        $user = User::findOrFail($data['user_id']);
        $user->assignRole('guia');

        return response()->json(['data' => $this->payload($user)], 201);
    }

    /**
     * Revoca el rol 'guia' de un usuario.
     */
    public function destroy(User $user): JsonResponse
    {
        $user->removeRole('guia');

        return response()->json(null, 204);
    }

    /**
     * Otorgar el rol 'guia' concede sus permisos (expenses:index,
     * expenses:store — ver RoleSeeder). Sin este chequeo, un rol personalizado
     * con solo 'guides:store' (posible vía RolesController) podía usarse para
     * auto-otorgarse esos permisos sin haberlos tenido nunca, saltándose el
     * mismo guardia que UserController::assertCanAssignRoles aplica a
     * cualquier otra asignación de rol.
     */
    private function assertCanGrantGuideRole(Request $request, string $targetUserId): void
    {
        $actor = $request->user();

        if ($actor?->hasRole('superadmin')) {
            return;
        }

        if ($actor && $actor->getKey() === $targetUserId) {
            abort(403, 'No puedes otorgarte el rol guia a ti mismo.');
        }

        $role = Role::where('name', 'guia')->with('permissions')->first();
        $held = $actor?->getAllPermissions()->pluck('name') ?? collect();
        $excess = $role?->permissions->pluck('name')->diff($held) ?? collect();

        if ($excess->isNotEmpty()) {
            abort(403, "No puedes otorgar el rol 'guia': incluye permisos que no posees.");
        }
    }

    private function payload(User $user): array
    {
        return [
            'type' => 'guides',
            'id' => (string) $user->id,
            'attributes' => [
                'name' => trim($user->first_name.' '.$user->last_name) ?: $user->username,
                'email' => $user->email,
            ],
        ];
    }
}
