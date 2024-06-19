<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'subscriptions',
            'id' => (string)$this->resource->getRouteKey(),
            'attributes' => [
                'start_date' => $this->resource->start_date,
                'end_date' => $this->resource->end_date,
                'trial_ends_at' => $this->resource->trial_ends_at,
                'package_price' => $this->resource->package_price,
                'package_details' => $this->resource->package_details,
                'payment_method' => $this->resource->payment_method,
                'payment_transaction_id' => $this->resource->payment_transaction_id,
                'status' => $this->resource->status,
            ],
            'relationships' => [
                'user' => [
                    'type' => 'users',
                    'id' => (string)$this->resource->user_id,
                    'attributes' => [
                        'username' => $this->resource->user->username,
                        'first_name' => $this->resource->user->first_name,
                        'last_name' => $this->resource->user->last_name,
                        'email' => $this->resource->user->email,
                    ],
                ],
                'createBy' => [
                    'type' => 'users',
                    'id' => (string)$this->resource->created_by,
                    'attributes' => [
                        'username' => $this->resource->createdBy->username,
                        'first_name' => $this->resource->createdBy->first_name,
                        'last_name' => $this->resource->createdBy->last_name,
                        'email' => $this->resource->createdBy->email,
                    ],
                ],
                'package' => PackageResource::make($this->resource->package),
            ],
        ];
    }
}
