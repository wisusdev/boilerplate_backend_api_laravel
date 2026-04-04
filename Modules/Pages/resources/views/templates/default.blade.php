@extends('layouts.app')

@section('title', $page->getSeoTitle())

@section('meta')
    <meta name="description" content="{{ $page->getSeoDescription() }}">
    @if($page->getSeoKeywords())
        <meta name="keywords" content="{{ $page->getSeoKeywords() }}">
    @endif

    <!-- Open Graph -->
    <meta property="og:title" content="{{ $page->getSeoTitle() }}">
    <meta property="og:description" content="{{ $page->getSeoDescription() }}">
    @if($page->meta['og_image'] ?? $page->featured_image)
        <meta property="og:image" content="{{ $page->meta['og_image'] ?? $page->featured_image }}">
    @endif
    <meta property="og:url" content="{{ $page->getUrl() }}">
    <meta property="og:type" content="website">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $page->getSeoTitle() }}">
    <meta name="twitter:description" content="{{ $page->getSeoDescription() }}">
    @if($page->meta['og_image'] ?? $page->featured_image)
        <meta name="twitter:image" content="{{ $page->meta['og_image'] ?? $page->featured_image }}">
    @endif

    <!-- Canonical URL -->
    <link rel="canonical" href="{{ $page->getUrl() }}">
@endsection

@section('content')
    @if($page->content_html)
        {{-- Display GrapesJS builder content --}}
        <style>
            {!! $page->content_css['css'] ?? '' !!}
        </style>
        {!! $page->content_html !!}
    @else
        {{-- Display regular content --}}
        <div class="container py-5">
            @if($page->featured_image)
                <img src="{{ $page->featured_image }}" alt="{{ $page->title }}" class="img-fluid rounded mb-4">
            @endif

            <h1 class="mb-4">{{ $page->title }}</h1>

            @if($page->getBreadcrumbs())
                <nav aria-label="breadcrumb" class="mb-4">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/">{{ __('Home') }}</a></li>
                        @foreach($page->getBreadcrumbs() as $crumb)
                            @if($loop->last)
                                <li class="breadcrumb-item active" aria-current="page">{{ $crumb['title'] }}</li>
                            @else
                                <li class="breadcrumb-item"><a href="{{ $crumb['url'] }}">{{ $crumb['title'] }}</a></li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            @endif

            <div class="page-content">
                {!! $page->content !!}
            </div>
        </div>
    @endif
@endsection
