<?php

namespace App\Http\Resources;

use App\Traits\JsonApiResource;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    use JsonApiResource;

    /**
     * Transform the resource into an array.
     */
    public function toJsonApi(): array
    {
        return [
            'country' => $this->resource->country,
            'country_code' => $this->resource->country_code,
            'currency' => $this->resource->currency,
            'code' => $this->resource->code,
            'symbol' => $this->resource->symbol,
            'thousand_separator' => $this->resource->thousand_separator,
            'decimal_separator' => $this->resource->decimal_separator,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),

            // Campos calculados
            'businesses_count' => $this->when(
                $this->resource->relationLoaded('businesses'),
                fn() => $this->resource->businesses->count()
            ),
            'purchase_businesses_count' => $this->when(
                $this->resource->relationLoaded('purchase_businesses'),
                fn() => $this->resource->purchase_businesses->count()
            ),
            'total_businesses_count' => $this->when(
                $this->resource->relationLoaded('businesses') && $this->resource->relationLoaded('purchase_businesses'),
                fn() => $this->resource->businesses->count() + $this->resource->purchase_businesses->count()
            ),
            'is_in_use' => $this->when(
                $this->resource->relationLoaded('businesses') || $this->resource->relationLoaded('purchase_businesses'),
                fn() => $this->resource->businesses()->exists() || $this->resource->purchase_businesses()->exists()
            ),

            // Formato de muestra
            'format_sample' => $this->formatCurrencySample(1234.56),
        ];
    }

    /**
     * Format a currency sample with the currency's separators and symbol.
     */
    private function formatCurrencySample(float $amount): string
    {
        $thousands = $this->resource->thousand_separator;
        $decimal = $this->resource->decimal_separator;
        $symbol = $this->resource->symbol;

        // Formatear número con separadores
        $formattedAmount = number_format($amount, 2, $decimal, $thousands);

        // Agregar símbolo según convención común
        // Para USD, EUR, GBP generalmente va antes, para otros después
        if (in_array($this->resource->code, ['USD', 'EUR', 'GBP', 'CAD', 'AUD'])) {
            return $symbol . $formattedAmount;
        }

        return $formattedAmount . ' ' . $symbol;
    }
}
