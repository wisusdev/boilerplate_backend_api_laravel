<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomInquiryResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        return [
            'user_id' => $this->resource->user_id,
            'preferred_destinations' => $this->resource->preferred_destinations,
            'travel_start_date' => $this->resource->travel_start_date,
            'travel_end_date' => $this->resource->travel_end_date,
            'budget_min' => $this->resource->budget_min,
            'budget_max' => $this->resource->budget_max,
            'travelers_count' => $this->resource->travelers_count,
            'currency_code' => $this->resource->currency_code,
            'message' => $this->resource->message,
            'status' => $this->resource->status,
            'created_at' => $this->resource->created_at,
            'updated_at' => $this->resource->updated_at,
        ];
    }
}