<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
			'type' => 'invoice_items',
			'id' => (string) $this->resource->getRouteKey(),
			'attributes' => [
				'invoice_id' => $this->resource->invoice_id,
				'name' => $this->resource->name,
				'description' => $this->resource->description,
				'quantity' => $this->resource->quantity,
				'unit_price' => $this->resource->unit_price,
				'total_price' => $this->resource->total_price,
				'metadata' => $this->resource->metadata,
			],
		];

    }
}
