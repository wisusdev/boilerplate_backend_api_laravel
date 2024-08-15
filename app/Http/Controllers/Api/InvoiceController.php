<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResource
    {
        $invoices = Invoice::query()
			->sparseFieldset()
			->jsonPaginate();

		return InvoiceResource::collection($invoices);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(InvoiceRequest $request): JsonResource
    {
		$dataValidated = $request->validated();
		$attributes = $dataValidated['data']['attributes'];

		// Format the dates correctly
		$attributes['invoice_date'] = Carbon::parse($attributes['invoice_date'])->format('Y-m-d');
		$attributes['due_date'] = isset($attributes['due_date']) ? Carbon::parse($attributes['due_date'])->format('Y-m-d') : null;

		$invoice = Invoice::create($attributes);
		$invoice->items()->createMany($dataValidated['data']['items']);

		return InvoiceResource::make($invoice);
    }

    /**
     * Display the specified resource.
     */
    public function show(Invoice $invoice): JsonResource
    {
        return InvoiceResource::make($invoice);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(InvoiceRequest $request, Invoice $invoice): InvoiceResource
	{
		$dataValidated = $request->validated();
		$attributes = $dataValidated['data']['attributes'];

		// Format the dates correctly
		$attributes['invoice_date'] = Carbon::parse($attributes['invoice_date'])->format('Y-m-d');
		$attributes['due_date'] = isset($attributes['due_date']) ? Carbon::parse($attributes['due_date'])->format('Y-m-d') : null;

		$invoice->update($attributes);
		$invoice->items()->delete();
		$invoice->items()->createMany($dataValidated['data']['items']);

		return InvoiceResource::make($invoice);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
		return response()->noContent();
    }
}
