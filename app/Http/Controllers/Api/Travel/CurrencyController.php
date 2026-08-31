<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\CurrencyRequest;
use App\Http\Resources\CurrencyResource;
use App\Models\Currency;

class CurrencyController extends Controller
{
    public function index()
    {
        return CurrencyResource::collection(Currency::query()->where('is_active', true)->orderBy('code')->jsonPaginate());
    }

    public function show(Currency $currency): CurrencyResource
    {
        if (! $currency->is_active) {
            abort(404);
        }

        return CurrencyResource::make($currency);
    }

    public function store(CurrencyRequest $request): CurrencyResource
    {
        $data = $request->validated()['data']['attributes'];

        $currency = Currency::updateOrCreate(
            ['code' => strtoupper($data['code'])],
            [
                'name' => $data['name'],
                'symbol' => $data['symbol'],
                'rate_to_usd' => $data['rate_to_usd'],
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]
        );

        if ($currency->is_default) {
            Currency::query()->where('id', '!=', $currency->id)->update(['is_default' => false]);
        }

        return CurrencyResource::make($currency);
    }

    /**
     * PATCH /api/currencies/{currency}
     *
     * El panel ya ofrecía este formulario, pero la ruta no existía y guardar
     * fallaba en silencio.
     */
    public function update(CurrencyRequest $request, Currency $currency): CurrencyResource
    {
        $attrs = $request->validated()['data']['attributes'];

        $currency->update($attrs);

        // Solo puede haber una moneda por defecto.
        if (! empty($attrs['is_default'])) {
            Currency::where('id', '!=', $currency->id)->update(['is_default' => false]);
        }

        return CurrencyResource::make($currency->fresh());
    }
}
