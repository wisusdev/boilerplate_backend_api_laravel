@extends('layouts.email')
@section('content')
    <p>{{ __('mail.greeting', ['name' => $name]) }}</p>
    <p>{{ __('mail.subscription_thank_you') }}</p>
    <p>{{ __('mail.subscription_details') }}</p>
    <ul>
        <li><strong>{{ __('mail.start_date') }}</strong> {{ $start_date }}</li>
        <li><strong>{{ __('mail.plan_name') }}</strong> {{ $plan_name }}</li>
        <li><strong>{{ __('mail.price') }}</strong> {{ $price }}</li>
    </ul>
    <p>{{ __('mail.support_contact') }}</p>
    <p>{{ __('mail.regards') }}<br>{{ config('app.name') }}</p>
@endsection