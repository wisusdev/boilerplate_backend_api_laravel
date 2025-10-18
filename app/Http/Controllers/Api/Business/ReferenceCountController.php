<?php

namespace App\Http\Controllers\Api\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReferenceCountRequest;
use App\Http\Resources\ReferenceCountResource;
use App\Models\ReferenceCount;
use App\Models\Business;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class ReferenceCountController extends Controller
{
    public function index(Request $request, Business $business): JsonResource
    {
        $this->authorize('index', ReferenceCount::class);

        $referenceCounts = ReferenceCount::query()
            ->where('business_id', $business->id)
            ->allowedFilters(['ref_type', 'business_id'])
            ->allowedSorts(['id', 'ref_type', 'ref_count', 'created_at'])
            ->sparseFieldset()
            ->jsonPaginate();

        return ReferenceCountResource::collection($referenceCounts);
    }


    public function store(ReferenceCountRequest $request, Business $business): ReferenceCountResource
    {
        $this->authorize('create', ReferenceCount::class);

        $data = $request->validated();
        $referenceCountData = $data['data']['attributes'];
        $referenceCountData['business_id'] = $business->id;

        $referenceCount = DB::transaction(function () use ($referenceCountData) {
            return ReferenceCount::create($referenceCountData);
        });

        return ReferenceCountResource::make($referenceCount->load('business'));
    }

    /**
     * Display the specified reference count.
     *
     * @throws AuthorizationException
     */
    public function show(Business $business, ReferenceCount $referenceCount): JsonResource
    {
        $this->authorize('show', $referenceCount);

        return ReferenceCountResource::make($referenceCount->load('business'));
    }

	/**
	 * Update the specified reference count in storage.
	 *
	 * @throws AuthorizationException
	 * @throws \Throwable
	 */
    public function update(ReferenceCountRequest $request, Business $business, ReferenceCount $referenceCount): JsonResource
    {
        $this->authorize('update', $referenceCount);

        $data = $request->validated();
        $referenceCountData = $data['data']['attributes'];

        DB::transaction(function () use ($referenceCount, $referenceCountData) {
            $referenceCount->update($referenceCountData);
        });

        return ReferenceCountResource::make($referenceCount->load('business'));
    }

	/**
	 * Remove the specified reference count from storage.
	 *
	 * @throws AuthorizationException
	 * @throws \Throwable
	 */
    public function destroy(Business $business, ReferenceCount $referenceCount): JsonResponse
    {
        $this->authorize('delete', $referenceCount);

        DB::transaction(function () use ($referenceCount) {
            $referenceCount->delete();
        });

        return response()->json(null, 204);
    }
}
