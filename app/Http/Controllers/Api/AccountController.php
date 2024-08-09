<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccountUpdateRequest;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LogoutDeviceRequest;
use App\Http\Resources\DeviceResource;
use App\Http\Resources\ProfileResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\DeviceInfo;
use App\Models\Subscription;
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

            if (isset($user->avatar) && Storage::disk('public')->exists($user->avatar)) {
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

    public function deleteAccount(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if ($user->id != $id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->devices()->delete();
        $user->tokens()->revoke();
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

	public function subscriptions(Request $request): JsonResource
	{
		$user = $request->user();

		$subscriptions = $user->subscriptions()
			->sparseFieldset()
			->jsonPaginate();

		return SubscriptionResource::collection($subscriptions);
	}

	public function cancelSubscription(Request $request, Subscription $subscription): JsonResource
	{
		DB::beginTransaction();

		if($subscription['payment_method'] == 'paypal') {
			$responseCancelSubscription = (new PaypalService())->cancelSubscription($subscription['payment_transaction_id'], $request['data']['attributes']['reason']);
			if ($responseCancelSubscription->http_code === 204){
				$subscription->update([
					'status' => 'cancel',
				]);

				DB::commit();

			}
		}

		if($subscription['payment_method'] == 'stripe') {
			$responseCancelSubscription = (new StripeService())->cancelSubscription($subscription['payment_transaction_id'], $request['data']['attributes']['reason']);
			if ($responseCancelSubscription->http_code === 200){
				$subscription->update([
					'status' => 'cancel',
				]);

				DB::commit();
			}
		}

		return SubscriptionResource::make($subscription);
	}

	public function invoiceSubscription(Subscription $subscription): Response
	{
		$data = [
			'userFullName' => $subscription->user->first_name . ' ' . $subscription->user->last_name,
			'package_name' => $subscription->package->name,
			'interval_count' => $subscription->package->interval_count,
			'interval' => $subscription->package->interval,
			'package_price' => $subscription->package_price,
		];

		$pdf = Pdf::loadView('invoices.subscriptions', ['data' => $data]);
		$invoiceName = 'invoice-' . date('Y-m-d-h-m-s') . '.pdf';
		return $pdf->download($invoiceName);
	}

}
