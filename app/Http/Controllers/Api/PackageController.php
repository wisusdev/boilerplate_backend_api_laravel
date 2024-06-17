<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;
use App\Services\PaypalService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackageController extends Controller
{
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

            $data = $request->validated();
            $package = Package::create($data['data']['attributes']);

            $paypalService = new PaypalService();
            $paypalService->createProduct(
                $package->id,
                $package->name,
                $package->description
            );

            DB::commit();

            return PackageResource::make($package);

        } catch (\Exception $e) {
            DB::rollBack();
            throw ValidationException::withMessages([
                'error' => ['errorAsOccurred']
            ]);
        }
    }

    public function show(Package $package): PackageResource
    {
        return PackageResource::make($package);
    }

    public function update(PackageRequest $request, Package $package): PackageResource
    {
        $data = $request->validated();
        $package->update($data['data']['attributes']);

        return PackageResource::make($package);
    }

    public function destroy(Package $package): void
    {
        $package->delete();
    }
}
