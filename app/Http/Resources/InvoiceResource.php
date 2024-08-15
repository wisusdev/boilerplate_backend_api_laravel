<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
			'type' => 'invoices',
			'id' => (string) $this->resource->getRouteKey(),
			'attributes' => [
				'user_id' => $this->resource->user_id,
				'created_by' => $this->resource->created_by,
				'invoice_number' => $this->resource->invoice_number,
				'invoice_date' => $this->resource->invoice_date,
				'due_date' => $this->resource->due_date,
				'total_amount' => $this->resource->total_amount,
				'status' => $this->resource->status,
			],
			'relationships' => [
				'user' => [
					'first_name' => $this->resource->user->first_name,
					'last_name' => $this->resource->user->last_name,
					'email' => $this->resource->user->email,
				],
				'items' => InvoiceItemResource::collection($this->resource->items),
			],
		];
    }
}
