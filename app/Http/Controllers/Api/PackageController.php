<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use App\Services\PaypalService;
use App\Services\StripeService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackageController extends Controller
{
	public function __construct()
	{
		$this->middleware('can:packages:index')->only('index');
		$this->middleware('can:packages:store')->only('store');
		$this->middleware('can:packages:show')->only('show');
		$this->middleware('can:packages:update')->only('update');
		$this->middleware('can:packages:delete')->only('destroy');
	}

	public function index(): JsonResource
	{
		$packages = Package::query()
			->sparseFieldset()
			->jsonPaginate();

		return PackageResource::collection($packages);
	}

	/**
	 * @throws GuzzleException
	 * @throws ValidationException
	 */
	public function store(PackageRequest $request): PackageResource
	{
		try {
			DB::beginTransaction();

			$attributes = $request->validated()['data']['attributes'];

			$package = Package::create($attributes);

			$paypalService = new PaypalService();
			$paypalProduct = $paypalService->createProduct($package->id, $package->name, $package->description);

			if ($paypalProduct->http_code !== 201) {
				throw ValidationException::withMessages([
					'error' => ['errorAsOccurred']
				]);
			}

			$paypalPlan = $paypalService->createPlan($package->id, $package->name, $package->description, $package->interval_count, $package->interval, $package->price);

			$stripeService = new StripeService();
			$stripeProduct = $stripeService->createProduct($package->name, $package->description);
			if ($stripeProduct->http_code !== 200) {
				throw ValidationException::withMessages([
					'error' => ['errorAsOccurred']
				]);
			}

			$stripePrice = $stripeService->createPrice($stripeProduct->id, $package->price, 'usd', $package->interval, $package->interval_count);

			$package->update([
				'metadata' => [
					'paypal' => [
						'paypal_product_id' => $paypalProduct->id,
						'paypal_plan_id' => $paypalPlan->id,
					],
					'stripe' => [
						'stripe_product_id' => $stripeProduct->id,
						'stripe_price_id' => $stripePrice->id,
					]
				]
			]);

			if ($paypalPlan->http_code !== 201) {
				throw ValidationException::withMessages([
					'error' => ['errorAsOccurred']
				]);
			}

			DB::commit();

			return PackageResource::make($package);
		} catch (\Exception $e) {
			DB::rollBack();
			throw ValidationException::withMessages([
				'error' => ['errorAsOccurred' => $e->getMessage()]
			]);
		}

	}

	public function show(Package $package): PackageResource
	{
		return PackageResource::make($package);
	}

	public function update(PackageRequest $request, Package $package): PackageResource
	{
		try {
			DB::beginTransaction();

			$data = $request->validated();
			$package->update($data['data']['attributes']);

			$paypalService = new PaypalService();
			$paypalService->updateProduct($package->id, $package->name, $package->description);

			$stripeService = new StripeService();
			$stripeService->updateProduct($package->name, $package->description, json_decode($package->metadata)->stripe->stripe_product_id);

			DB::commit();

			return PackageResource::make($package);

		} catch (\Exception $e) {
			DB::rollBack();
			throw ValidationException::withMessages([
				'error' => ['errorAsOccurred']
			]);
		}
	}

	public function destroy(Package $package): void
	{
		$package->delete();
	}

	// Public methods
	public function publicIndex(): JsonResource
	{
		$packages = Package::query()
			->where('active', true)
			->sparseFieldset()
			->jsonPaginate();

		return PackageResource::collection($packages);
	}

	public function publicShow(Package $package): PackageResource
	{
		return PackageResource::make($package);
	}
}
