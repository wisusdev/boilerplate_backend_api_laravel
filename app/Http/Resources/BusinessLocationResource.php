<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessLocationResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        return [
            'business_id' => $this->resource->business_id,
            'invoice_scheme_id' => $this->resource->invoice_scheme_id,
            'invoice_layout_id' => $this->resource->invoice_layout_id,
            'location_id' => $this->resource->location_id,
            'receipt_printer_type' => $this->resource->receipt_printer_type,
            'sale_invoice_layout_id' => $this->resource->sale_invoice_layout_id,
            'selling_price_group_id' => $this->resource->selling_price_group_id,
            'printer_id' => $this->resource->printer_id,
            'name' => $this->resource->name,
            'landmark' => $this->resource->landmark,
            'country' => $this->resource->country,
            'state' => $this->resource->state,
            'city' => $this->resource->city,
            'zip_code' => $this->resource->zip_code,
            'sale_invoice_scheme_id' => $this->resource->sale_invoice_scheme_id,
            'default_payment_accounts' => $this->resource->default_payment_accounts,
            'print_receipt_on_invoice' => $this->resource->print_receipt_on_invoice,
            'mobile' => $this->resource->mobile,
            'alternate_number' => $this->resource->alternate_number,
            'email' => $this->resource->email,
            'website' => $this->resource->website,
            'featured_products' => $this->resource->featured_products,
            'is_active' => $this->resource->is_active,
            'custom_field1' => $this->resource->custom_field1,
            'custom_field2' => $this->resource->custom_field2,
            'custom_field3' => $this->resource->custom_field3,
            'custom_field4' => $this->resource->custom_field4,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
            'deleted_at' => $this->resource->deleted_at,
        ];
    }

	public function getRelationshipLinks(): array
	{
		return [
			[
				'name' => 'business',
				'route' => 'businesses.show',
				'params' => $this->resource->business
			]
		];
	}

    public function getIncludes(): array
    {
        return [
            BusinessResource::make($this->resource->business)
        ];
    }

	protected function getRouteParameters(): array
	{
		return [
			'business' => $this->resource->business,
			'business_location' => $this->resource
		];
	}
}
