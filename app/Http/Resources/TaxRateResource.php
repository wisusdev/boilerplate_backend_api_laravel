<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "TaxRateResource",
    title: "Tax Rate Resource",
    description: "Recurso de tasa de impuesto",
    properties: [
        new OA\Property(property: "type", type: "string", example: "tax-rates"),
        new OA\Property(property: "id", type: "string", example: "1"),
        new OA\Property(
            property: "attributes",
            properties: [
                new OA\Property(property: "name", type: "string", example: "IVA 16%"),
                new OA\Property(property: "amount", type: "number", format: "float", example: 16.0),
                new OA\Property(property: "is_tax_group", type: "boolean", example: false),
                new OA\Property(property: "for_tax_group", type: "boolean", example: false),
                new OA\Property(property: "created_at", type: "string", format: "date-time", example: "2025-01-25T10:30:00.000000Z"),
                new OA\Property(property: "updated_at", type: "string", format: "date-time", example: "2025-01-25T10:30:00.000000Z"),
                new OA\Property(property: "deleted_at", type: "string", format: "date-time", nullable: true, example: null)
            ],
            type: "object"
        ),
        new OA\Property(
            property: "relationships",
            properties: [
                new OA\Property(
                    property: "business",
                    properties: [
                        new OA\Property(
                            property: "data",
                            properties: [
                                new OA\Property(property: "type", type: "string", example: "businesses"),
                                new OA\Property(property: "id", type: "string", example: "1")
                            ],
                            type: "object"
                        )
                    ],
                    type: "object"
                ),
                new OA\Property(
                    property: "creator",
                    properties: [
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "type", type: "string", example: "users"),
                                new OA\Property(property: "id", type: "string", example: "1")
                            ]
                        )
                    ],
                    type: "object"
                )
            ],
            type: "object"
        )
    ],
    type: "object"
)]
class TaxRateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => 'tax-rates',
            'id' => (string) $this->id,
            'attributes' => [
                'name' => $this->name,
                'amount' => (float) $this->amount,
                'is_tax_group' => (bool) $this->is_tax_group,
                'for_tax_group' => (bool) $this->for_tax_group,
                'created_at' => $this->created_at?->toISOString(),
                'updated_at' => $this->updated_at?->toISOString(),
                'deleted_at' => $this->deleted_at?->toISOString(),
            ],
            'relationships' => [
                'business' => [
                    'data' => [
                        'type' => 'businesses',
                        'id' => (string) $this->business_id,
                    ]
                ],
                'creator' => [
                    'data' => [
                        'type' => 'users',
                        'id' => (string) $this->created_by,
                    ]
                ]
            ]
        ];
    }
}
