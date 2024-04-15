<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountUpdateRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\ProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function profile(Request $request): JsonResource
    {
        return ProfileResource::make($request->user());
    }

    public function updateProfile(AccountUpdateRequest $request): JsonResource
    {
        $user = $request->user();

        if ($request->has('data.attributes.avatar') && $request->input('data.attributes.avatar')) {
            $destinationPath = '/uploads/' . date('Y') . '/' . date('m') . '/' . date('d');

            if (Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $avatar = $request->input('data.attributes.avatar');
            $avatar = str_replace('data:image/png;base64,', '', $avatar);
            $avatar = str_replace(' ', '+', $avatar);
            $avatar = base64_decode($avatar);
            $avatarName = $destinationPath . '/avatar.webp';
            Storage::disk('public')->put($avatarName, $avatar);
        }

        $user->update([
            'first_name' => $request->input('data.attributes.first_name'),
            'last_name' => $request->input('data.attributes.last_name'),
            'email' => $request->input('data.attributes.email'),
            'avatar' => $avatarName ?? $user->avatar,
            'language' => $request->input('data.attributes.language')
        ]);

        if($user->email !== $request->input('data.attributes.email')) {
            $user->email_verified_at = null;
            $user->save(['timestamps' => false]);
            $user->sendEmailVerificationNotification();
        }

        return ProfileResource::make($user);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {

        $user = $request->user();

        if (!Hash::check($request->input('data.attributes.current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect'],
            ]);
        }

        $user->update([
            'password' => $request->input('data.attributes.password')
        ]);

        return response()->json(['message' => 'Password changed successfully']);
    }

    public function devicesAuthList(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()->tokens]);
    }

}
