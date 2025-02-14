<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountUpdateRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LogoutDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\ProfileResource;
use App\Models\DeviceInfo;
use App\Models\User;
use App\Notifications\DeleteAccountConfirmationNotification;
use App\Notifications\VerifyDeleteAccountNotification;
use App\Notifications\PasswordChangeNotification;
use App\Services\PaypalService;
use App\Services\StripeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

            if (isset($user->avatar) && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $avatar = $request->input('data.attributes.avatar');
            $avatar = str_replace('data:image/png;base64,', '', $avatar);
            $avatar = str_replace(' ', '+', $avatar);
            $avatar = base64_decode($avatar);
            $avatarName = config('app.destination_path') . '/avatar.webp';
            Storage::disk('public')->put($avatarName, $avatar);
        }

        $user->update([
            'first_name' => $request->input('data.attributes.first_name'),
            'last_name' => $request->input('data.attributes.last_name'),
            'email' => $request->input('data.attributes.email'),
            'avatar' => $avatarName ?? $user->avatar,
            'language' => $request->input('data.attributes.language')
        ]);

        if ($user->email !== $request->input('data.attributes.email')) {
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
                'current_password' => ['validation.passwordIncorrect'],
            ]);
        }

        $user->update([
            'password' => $request->input('data.attributes.password')
        ]);

        $user->notify(new PasswordChangeNotification());

        return response()->json([
            'data' => [
                'type' => 'change-password',
                'attributes' => [
                    'status' => true,
                    'message' => 'message.passwordChangedSuccessfully',
                ],
            ]
        ]);
    }

	public function deleteAccount(Request $request): JsonResponse
	{
		$user = $request->user();
		$token = Str::random(60);

		DB::table('password_reset_tokens')->updateOrInsert(['email' => $user->email], [
			'token' => $token,
			'created_at' => now()->addHours(6)
		]);

		$url = config('app.frontend_url').'/account/delete-account-verify?token='.$token;

		// Send email
		$user->notify(new VerifyDeleteAccountNotification($url, $user->first_name));

		return response()->json([
			'data' => [
				'type' => 'delete-account',
				'attributes' => [
					'status' => true,
					'message' => 'message.deleteAccountEmailSent',
				],
			]
		]);
	}

    public function deleteAccountVerify(Request $request): JsonResponse
    {
		$token = $request->input('data.attributes.token');
		$account = DB::table('password_reset_tokens')->where('token', $token)->first();

		// verify
		if (!$account) {
			throw ValidationException::withMessages([
				'token' => ['validation.tokenInvalid'],
			]);
		}

		// Validate expire token
		if ($account->created_at < now()) {
			throw ValidationException::withMessages([
				'token' => ['validation.tokenExpired'],
			]);
		}

		$user = User::whereEmail($account->email)->first();

		if (!$user) {
			throw ValidationException::withMessages([
				'token' => ['validation.userNotFound'],
			]);
		}

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

		DB::table('password_reset_tokens')->where('email', $user->email)->delete();

		// Send email to user
		$user->notify(new DeleteAccountConfirmationNotification());

        $user->devices()->delete();
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'message.accountDeletedSuccessfully']);
    }

    public function devicesAuthList(Request $request): JsonResource
    {
        $userId = $request->user()->id;

        $devices = DeviceInfo::where('user_id', $userId)
            ->sparseFieldset()
            ->jsonPaginate();

        return DeviceResource::collection($devices);
    }

    public function logoutDevice(LogoutDeviceRequest $request): JsonResponse
    {
        $deviceInfo = DeviceInfo::find($request->input('data.attributes.device_id'));

        if (!$deviceInfo || $deviceInfo->user_id != $request->user()->id) {
            return response()->json(['message' => 'message.deviceNotFound'], 404);
        }

        $token = $request->user()->tokens()->where('id', $deviceInfo->session_token)->first();
        $deviceInfo->delete();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'data' => [
                'type' => 'logout-device',
                'attributes' => [
                    'status' => true,
                    'message' => 'message.deviceLoggedOutSuccessfully',
                ],
            ]
        ]);
    }
}
