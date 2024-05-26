<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $subscriptions = Subscription::query()
            ->sparseFieldset()
            ->jsonPaginate();

        return SubscriptionResource::collection($subscriptions);
    }

    public function show(Subscription $subscription): SubscriptionResource
    {
        return new SubscriptionResource($subscription);
    }

    public function store(SubscriptionRequest $request): SubscriptionResource
    {
        $data = $request->validated();

        $package = Package::find($data['data']['attributes']['package_id']);

        $start_date = now();
        $trial_days = $package->trial_days;

        $end_date = match ($package->interval) {
            'day' => $start_date->addDays($package->interval_count),
            'week' => $start_date->addWeeks($package->interval_count),
            'month' => $start_date->addMonths($package->interval_count),
            'year' => $start_date->addYears($package->interval_count),
            default => $start_date,
        };

        if($trial_days > 0) {
            $end_date = $start_date->addDays($package->trial_days);
        }

        $subscription = Subscription::create([
            'user_id' => $data['data']['attributes']['user_id'],
            'package_id' => $package->id,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'trial_ends_at' => $trial_days > 0 ? $end_date : null,
            'package_price' => $package->price,
            'package_details' => $package->description,
            'created_by' => $data['data']['attributes']['created_by'],
            'payment_method' => $data['data']['attributes']['payment_method'],
            'payment_transaction_id' => $data['data']['attributes']['payment_transaction_id'],
            'status' => $data['data']['attributes']['status'],
        ]);

        return SubscriptionResource::make($subscription);
    }

    public function update(Request $request, Subscription $subscription): SubscriptionResource
    {
        $data = $request->validate([
            'data' => ['required', 'array'],
            'data.type' => ['required', 'string', 'in:subscriptions'],
            'data.attributes.status' => ['in:approved,waiting,declined,cancel'],
        ]);

        $subscription->update([
            'status' => $data['data']['attributes']['status'],
        ]);

        return SubscriptionResource::make($subscription);
    }

    public function destroy(Subscription $subscription): void
    {
        $subscription->delete();
    }
}
