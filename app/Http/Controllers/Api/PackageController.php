<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PackageRequest;
use App\Http\Resources\PackageResource;
use App\Models\Package;

class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::query()
            ->sparseFieldset()
            ->jsonPaginate();

        return PackageResource::collection($packages);
    }

    public function store(PackageRequest $request): PackageResource
    {
        $data = $request->validated();
        $package = Package::create($data['data']['attributes']);

        return PackageResource::make($package);
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
