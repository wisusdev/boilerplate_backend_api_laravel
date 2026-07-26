<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseCategoryResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $c = $this->resource;

        return [
            'name' => $c->name,
            'slug' => $c->slug,
            'icon' => $c->icon,
            'is_active' => $c->is_active,
            'sort_order' => $c->sort_order,
            'created_at' => $c->created_at,
            'updated_at' => $c->updated_at,
        ];
    }
}
