<?php

namespace App\Http\Controllers\Api\Travel;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomInquiryRequest;
use App\Http\Resources\CustomInquiryResource;
use App\Models\CustomInquiry;
use App\Services\CustomInquiryService;

class CustomInquiryController extends Controller
{
    public function __construct(private readonly CustomInquiryService $customInquiryService)
    {
    }

    public function index()
    {
        return CustomInquiryResource::collection(CustomInquiry::query()->latest()->jsonPaginate());
    }

    public function show(CustomInquiry $customInquiry): CustomInquiryResource
    {
        return CustomInquiryResource::make($customInquiry);
    }

    public function store(CustomInquiryRequest $request): CustomInquiryResource
    {
        $inquiry = $this->customInquiryService->create($request->validated()['data']['attributes'], $request->user());

        return CustomInquiryResource::make($inquiry);
    }
}