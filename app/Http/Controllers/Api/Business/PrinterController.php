<?php

namespace App\Http\Controllers\Api\Business;

use App\Http\Controllers\Controller;
use App\Http\Requests\PrinterRequest;
use App\Http\Resources\PrinterResource;
use App\Models\Business;
use App\Models\Printer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;

class PrinterController extends Controller
{
    /**
     * @throws AuthorizationException
     */
    public function index(Business $business): JsonResource
    {
        $this->authorize('index', Printer::class);

        $printers = Printer::query()
            ->where('business_id', $business->id)
            ->allowedIncludes(['business'])
            ->allowedFilters(['name', 'connection_type', 'capability_profile'])
            ->allowedSorts(['id', 'name', 'connection_type', 'capability_profile'])
            ->sparseFieldset()
            ->jsonPaginate();

        return PrinterResource::collection($printers);
    }

    /**
     * @throws AuthorizationException
     */
    public function store(PrinterRequest $request): PrinterResource
    {
        $this->authorize('create', Printer::class);

        $data = $request->validated();
        $attributes = $data['data']['attributes'];

        $printer = Printer::create($attributes);

        return PrinterResource::make($printer);
    }

    /**
     * @throws AuthorizationException
     */
    public function show(Business $business, Printer $printer): PrinterResource
    {
        $this->authorize('show', $printer);
        
        $printer = Printer::where('id', $printer->id)
            ->allowedIncludes(['business'])
            ->sparseFieldset(['business_id'])
            ->firstOrFail();

        return PrinterResource::make($printer);
    }

    /**
     * @throws AuthorizationException
     */
    public function update(PrinterRequest $request, Business $business, Printer $printer): PrinterResource
    {
        $this->authorize('update', $printer);

        $data = $request->validated();
        $attributes = $data['data']['attributes'];
        $printer->update($attributes);

        return PrinterResource::make($printer->load(['business']));
    }

    /**
     * @throws AuthorizationException
     */
    public function destroy(Business $business, Printer $printer): Response
    {
        $this->authorize('delete', $printer);
        $printer->delete();
	    return response()->noContent();
    }
}
