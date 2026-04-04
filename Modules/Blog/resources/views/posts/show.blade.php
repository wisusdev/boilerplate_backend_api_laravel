@extends('layouts.app')

@section('title', $post->getSeoTitle())

@push('meta')
    <meta name="description" content="{{ $post->getSeoDescription() }}">
    <meta property="og:title" content="{{ $post->getSeoTitle() }}">
    <meta property="og:description" content="{{ $post->getSeoDescription() }}">
    @if($post->featured_image)
        <meta property="og:image" content="{{ $post->featured_image }}">
    @endif
    <meta property="og:url" content="{{ $post->getUrl() }}">
    <meta property="og:type" content="article">
    <link rel="canonical" href="{{ $post->getUrl() }}">
@endpush

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            {{-- Preview banner --}}
            @if(!$post->isPublished())
                <div class="alert alert-warning d-flex align-items-center mb-4">
                    <i class="bi bi-eye me-2"></i>
                    <strong>{{ __('Preview mode') }}</strong>&nbsp;— {{ __('This post is not published yet.') }}
                    @auth
                        <a href="{{ route('admin.blog.posts.edit', $post) }}" class="ms-auto btn btn-sm btn-warning">
                            <i class="bi bi-pencil me-1"></i>{{ __('Edit') }}
                        </a>
                    @endauth
                </div>
            @endif

            {{-- Categories --}}
            @if($post->categories->isNotEmpty())
                <div class="mb-2">
                    @foreach($post->categories as $category)
                        <span class="badge bg-primary me-1">{{ $category->name }}</span>
                    @endforeach
                </div>
            @endif

            {{-- Title --}}
            <h1 class="fw-bold mb-3">{{ $post->title }}</h1>

            {{-- Meta --}}
            <div class="text-muted small mb-4 d-flex align-items-center gap-3 flex-wrap">
                @if($post->author)
                    <span><i class="bi bi-person me-1"></i>{{ $post->author->name }}</span>
                @endif
                @if($post->published_at)
                    <span><i class="bi bi-calendar3 me-1"></i>{{ $post->published_at->format('d M Y') }}</span>
                @endif
                <span><i class="bi bi-clock me-1"></i>{{ $post->getReadingTime() }} {{ __('min read') }}</span>
            </div>

            {{-- Featured image --}}
            @if($post->featured_image)
                <img src="{{ $post->featured_image }}" alt="{{ $post->title }}"
                     class="img-fluid rounded-3 mb-4 w-100" style="max-height:400px; object-fit:cover;">
            @endif

            {{-- Content --}}
            <div class="post-content lh-lg">
                {!! $post->content_html ?: nl2br(e($post->content)) !!}
            </div>

            {{-- Tags --}}
            @if($post->tags->isNotEmpty())
                <div class="mt-4 pt-4 border-top">
                    <strong>{{ __('Tags:') }}</strong>
                    @foreach($post->tags as $tag)
                        <span class="badge bg-secondary me-1">{{ $tag->name }}</span>
                    @endforeach
                </div>
            @endif

            {{-- Related posts --}}
            @if(!empty($relatedPosts) && $relatedPosts->isNotEmpty())
                <div class="mt-5">
                    <h3 class="h5 fw-bold mb-3">{{ __('Related Posts') }}</h3>
                    <div class="row g-3">
                        @foreach($relatedPosts as $related)
                            <div class="col-md-4">
                                <div class="card h-100">
                                    @if($related->featured_image)
                                        <img src="{{ $related->featured_image }}" class="card-img-top"
                                             style="height:140px; object-fit:cover;" alt="{{ $related->title }}">
                                    @endif
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <a href="{{ $related->getUrl() }}" class="text-decoration-none stretched-link">
                                                {{ $related->title }}
                                            </a>
                                        </h6>
                                        <small class="text-muted">{{ $related->published_at?->format('d M Y') }}</small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
@endsection
