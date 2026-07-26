<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    use JsonApiResource;

    public function toJsonApi(): array
    {
        $e = $this->resource;

        return [
            'tour_id' => $e->tour_id,
            'tour_title' => optional($e->tour)->title,
            'expense_category_id' => $e->expense_category_id,
            'category_name' => optional($e->category)->name,
            'category_icon' => optional($e->category)->icon,
            'guide_id' => $e->guide_id,
            'guide_name' => $e->guide ? trim($e->guide->first_name.' '.$e->guide->last_name) : null,
            'amount' => $e->amount,
            'comment' => $e->comment,
            'spent_at' => optional($e->spent_at)->toDateString(),
            'currency_code' => $e->currency_code,
            'receipt_url' => $e->receiptUrl(),
            'created_at' => $e->created_at,
            'updated_at' => $e->updated_at,
        ];
    }
}
