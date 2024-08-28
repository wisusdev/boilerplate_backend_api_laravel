<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\NewInvoice;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResource
    {
        $invoices = Invoice::query()
			->allowedFilters(['user_id', 'created_by', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'status'])
			->allowedSorts(['id', 'user_id', 'created_by', 'invoice_number', 'invoice_date', 'due_date', 'total_amount', 'status'])
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
		$invoice = $this->createOrUpdateInvoice($attributes);

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
		$invoice = $this->createOrUpdateInvoice($attributes, $invoice);

		return InvoiceResource::make($invoice);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Invoice $invoice): Response
	{
        $invoice->delete();
		return response()->noContent();
    }

	private function createOrUpdateInvoice(array $attributes, Invoice $invoice = null): Invoice
	{
		$attributes = $this->formatDates($attributes);

		if ($invoice) {
			$invoice->update($attributes);
			$invoice->items()->delete();
		} else {
			$invoice = Invoice::create($attributes);
		}

		$invoice->items()->createMany($attributes['items']);

		if(isset($attributes['send_email'])) {
			$user = User::find($attributes['user_id']);
			App::setLocale($user->language);
			$fullNames = $user->first_name . ' ' . $user->last_name;
			$user->notify(new NewInvoice($fullNames, $invoice->total_amount, $invoice->items->toArray(), $invoice->id));
		}

		return $invoice;
	}

	private function formatDates(array $attributes): array
	{
		$attributes['invoice_date'] = Carbon::parse($attributes['invoice_date'])->format('Y-m-d');
		$attributes['due_date'] = isset($attributes['due_date']) ? Carbon::parse($attributes['due_date'])->format('Y-m-d') : null;

		return $attributes;
	}
}
