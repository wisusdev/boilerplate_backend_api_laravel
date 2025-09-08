<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceSchemeResource extends JsonResource
{
    use JsonApiResource;

    /**
     * Transform the resource into an array.
     */
    public function toJsonApi(): array
    {
        return [
            'business_id' => $this->resource->business_id,
            'name' => $this->resource->name,
            'scheme_type' => $this->resource->scheme_type,
            'number_type' => $this->resource->number_type,
            'prefix' => $this->resource->prefix,
            'start_number' => $this->resource->start_number,
            'invoice_count' => $this->resource->invoice_count,
            'total_digits' => $this->resource->total_digits,
            'is_default' => $this->resource->is_default,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }

    public function getResourceLinks(): array
    {
        return [
            'self' => route('invoice-schemes.show',[
                'business' => $this->business_id,
                'invoice_scheme' => $this->resource->getRouteKey(),
            ]),
        ];
    }

    public function getRelationshipLinks(): array
    {
        $links = [];

        if ($this->resource->business_id) {
            $links['business'] = route('businesses.show', $this->resource->business_id);
        }

        return $links;
    }

    public function getIncludes(): array
    {
        return [
            BusinessResource::make($this->resource->business)
        ];
    }
}
