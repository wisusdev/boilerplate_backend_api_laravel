<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
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

    private function payload(User $user): array
    {
        return [
            'type' => 'guides',
            'id'   => (string) $user->id,
            'attributes' => [
                'name'  => trim($user->first_name . ' ' . $user->last_name) ?: $user->username,
                'email' => $user->email,
            ],
        ];
    }
}
