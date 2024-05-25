<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionRequest;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
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
        $subscription = Subscription::create($data['data']['attributes']);

        return SubscriptionResource::make($subscription);
    }

    public function update(SubscriptionRequest $request, Subscription $subscription): SubscriptionResource
    {
        $data = $request->validated();
        $subscription->update($data['data']['attributes']);

        return SubscriptionResource::make($subscription);
    }

    public function destroy(Subscription $subscription): void
    {
        $subscription->delete();
    }
}
