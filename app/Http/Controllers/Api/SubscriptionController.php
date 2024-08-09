<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\NewSubscription;
use App\Services\PaypalService;
use App\Services\StripeService;
use App\Services\WompiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
	public function index(): JsonResource
	{
		$subscriptions = Subscription::query()
			->sparseFieldset()
			->jsonPaginate();

		return SubscriptionResource::collection($subscriptions);
	}

	public function show(Subscription $subscription): SubscriptionResource
	{
		return SubscriptionResource::make($subscription);
	}

	/**
	 * @throws ValidationException
	 */
	public function store(SubscriptionRequest $request): SubscriptionResource|JsonResponse
	{
		$data = $request->validated();

		$userId = $data['data']['attributes']['user_id'];
		$package = Package::find($data['data']['attributes']['package_id']);

		if ($this->checkUserSubscription($userId, $package->id)) {
			throw ValidationException::withMessages([
				'error' => ['validation.subscriptionAlreadyExists']
			]);
		}

		try {
			DB::beginTransaction();

			$user = User::find($userId);
			App::setLocale($user->language);

			$metadata = json_decode($package->metadata);

			$start_date = now();
			$trial_days = $package->trial_days;

			$end_date = match ($package->interval) {
				'day' => $start_date->addDays($package->interval_count),
				'week' => $start_date->addWeeks($package->interval_count),
				'month' => $start_date->addMonths($package->interval_count),
				'year' => $start_date->addYears($package->interval_count),
				default => $start_date,
			};

			if ($trial_days > 0) {
				$end_date = $start_date->addDays($package->trial_days);
			}

			$paymentMethod = $data['data']['attributes']['payment_method'];

			$subscription = Subscription::create([
				'user_id' => $userId,
				'package_id' => $package->id,
				'start_date' => $start_date,
				'end_date' => $end_date,
				'trial_ends_at' => $trial_days > 0 ? $end_date : null,
				'package_price' => $package->price,
				'package_details' => $package->description,
				'created_by' => $data['data']['attributes']['created_by'],
				'payment_method' => $paymentMethod,
			]);

			$userName = $user->first_name . ' ' . $user->last_name;

			if ($paymentMethod === 'paypal'){
				$paypalService = new PaypalService();
				$paypalSubscription = $paypalService->createSubscription($subscription->id, $metadata->paypal->paypal_plan_id, $userName, $user->email);

				if ($paypalSubscription->http_code !== 201) {
					throw ValidationException::withMessages([
						'error' => ['errorAsOccurred']
					]);
				}

				$subscriptionLinks = collect($paypalSubscription->links);
				$approve = $subscriptionLinks->where('rel', 'approve')->first();

				DB::commit();

				$subscription->update([
					'payment_transaction_id' => $paypalSubscription->id,
				]);

				return response()->json([
					'approve_url' => $approve->href,
				]);
			}

			if($paymentMethod === 'stripe'){
				$stripe_payment_method = $data['data']['attributes']['payment_method_id'];

				$stripeService = new StripeService();
				$stripeCustomer = $stripeService->createCustomer($userName, $user->email, $stripe_payment_method);

				if ($stripeCustomer->http_code !== 200) {
					throw ValidationException::withMessages([
						'error' => ['errorAsOccurred']
					]);
				}

				$stripeSubscription = $stripeService->createSubscription($stripeCustomer->id, $stripe_payment_method, $metadata->stripe->stripe_price_id);

				DB::commit();
				$subscription->update([
					'payment_transaction_id' => $stripeSubscription->id,
				]);

				$stripePaymentIntent = $stripeSubscription->latest_invoice;

				if($stripePaymentIntent->payment_intent->status === 'succeeded'){
					$subscription->update([
						'status' => 'approved',
					]);

					$user->notify(new NewSubscription($userName, $start_date, $package->name, $package->price));

					return response()->json([
						'requires_action' => false,
						'status' => $stripePaymentIntent->payment_intent->status,
					]);
				}

				if($stripePaymentIntent->payment_intent->status === 'requires_action'){
					return response()->json([
						'requires_action' => true,
						'payment_intent_client_secret' => $stripePaymentIntent->payment_intent->client_secret,
					]);
				}

			}

			if($paymentMethod === 'wompi') {
				$dataWompi = $data['data']['attributes'];
				$wompiService = new WompiService();
				$wompiResponse = $wompiService->createPaymentWithCard($dataWompi, $package->price);

				if ($wompiResponse->http_code !== 200) {
					throw ValidationException::withMessages([
						'error' => ['errorAsOccurred']
					]);
				}

				DB::commit();

				$subscription->update([
					'payment_transaction_id' => $wompiResponse->idTransaccion,
					'status' => 'approved',
				]);

				$user->notify(new NewSubscription($userName, $start_date, $package->name, $package->price));

				return response()->json([
					'status' => 'approved',
					'isReal' => $wompiResponse->esReal
				]);
			}

			return SubscriptionResource::make($subscription);

		} catch (\Exception $e) {
			DB::rollBack();
			throw ValidationException::withMessages([
				'error' => ['errorAsOccurred']
			]);
		}
	}

	public function update(Request $request, Subscription $subscription): SubscriptionResource
	{
		$data = $request->validate([
			'data' => ['required', 'array'],
			'data.type' => ['required', 'string', 'in:subscriptions'],
			'data.attributes.status' => ['in:approved,waiting,declined,cancel'],
		]);

		$subscription->update([
			'status' => $data['data']['attributes']['status'],
		]);

		return SubscriptionResource::make($subscription);
	}

	public function destroy(Subscription $subscription): void
	{
		$subscription->delete();
	}

	/**
	 * @throws ValidationException
	 */
	public function publicStore(SubscriptionRequest $request): JsonResponse
	{
		return $this->store($request);
	}

	/**
	 * @throws ValidationException
	 */
	public function validateSubscription(Subscription $subscription): JsonResponse
	{
		if($subscription['status'] == 'approved' || $subscription['status'] == 'declined'){
			return response()->json([
				'status' => $subscription['status'],
			]);
		}

		if($subscription['payment_method'] == 'paypal') {
			$this->paypalActiveSubscription($subscription);
		}

		return response()->json([
			'status' => $subscription['status'],
		]);

	}

	private function paypalActiveSubscription(Subscription $subscription): void
	{
		$paypalService = new PaypalService();
		$paypalSubscriptionDetail = $paypalService->subscriptionDetails($subscription['payment_transaction_id']);

		if($paypalSubscriptionDetail->http_code !== 200) {
			throw ValidationException::withMessages([
				'error' => ['errorAsOccurred']
			]);
		}

		$paypalSubscriptionDetailStatus = $paypalSubscriptionDetail->status;

		if ($paypalSubscriptionDetailStatus === 'ACTIVE') {
			$user = $subscription->user;
			App::setLocale($user->language);
			$user->notify(new NewSubscription($user->first_name . ' ' . $user->last_name, $subscription->start_date, $subscription->package->name, $subscription->package->price));

			$subscription->update([
				'status' => 'approved',
			]);
		} else {
			$subscription->update([
				'status' => 'declined',
			]);
		}
	}

	private function checkUserSubscription(string $userId, string $packageId)
	{
		return Subscription::where('user_id', $userId)
			->where('package_id', $packageId)
			->whereNotIn('status', ['cancel', 'declined', 'waiting'])
			->exists();
	}

	public function getWompiRegions(): JsonResponse
	{
		$wompiService = new WompiService();
		$regions = $wompiService->getRegion();

		return response()->json($regions);
	}
}
