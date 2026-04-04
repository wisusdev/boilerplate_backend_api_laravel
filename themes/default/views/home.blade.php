@extends('layouts.app')

@section('title', config('app.name', 'CMS'))

@section('content')
<section class="py-5 bg-light">
    <div class="container text-center">
        <h1 class="display-5 fw-bold mb-3">{{ config('app.name', 'CMS') }}</h1>
        <p class="lead text-muted mb-4">A modern Laravel-powered content management system.</p>
        <a href="{{ url('/admin') }}" class="btn btn-primary btn-lg">
            <i class="bi bi-speedometer2 me-2"></i>{{ __('Go to Admin Panel') }}
        </a>
    </div>
</section>
@endsection
