@extends('layouts.admin')

@section('title', __('Dashboard'))

@section('content')
<div class="container-fluid">
    <h1 class="h3 mb-4">{{ __('Dashboard') }}</h1>

    <div class="row g-4 mb-4">
        <!-- Pages -->
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 bg-primary bg-opacity-10">
                        <i class="bi bi-file-earmark-text fs-3 text-primary"></i>
                    </div>
                    <div>
                        <div class="text-muted small">{{ __('Pages') }}</div>
                        <div class="h4 mb-0">
                            {{ \Modules\Pages\Models\Page::count() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Posts -->
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 bg-success bg-opacity-10">
                        <i class="bi bi-journal-text fs-3 text-success"></i>
                    </div>
                    <div>
                        <div class="text-muted small">{{ __('Posts') }}</div>
                        <div class="h4 mb-0">
                            {{ \Modules\Blog\Models\Post::count() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Media -->
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 bg-info bg-opacity-10">
                        <i class="bi bi-images fs-3 text-info"></i>
                    </div>
                    <div>
                        <div class="text-muted small">{{ __('Media') }}</div>
                        <div class="h4 mb-0">
                            {{ \Modules\Media\Models\Media::count() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users -->
        <div class="col-sm-6 col-xl-3">
            <div class="card">
                <div class="card-body d-flex align-items-center gap-3">
                    <div class="rounded-3 p-3 bg-warning bg-opacity-10">
                        <i class="bi bi-people fs-3 text-warning"></i>
                    </div>
                    <div>
                        <div class="text-muted small">{{ __('Users') }}</div>
                        <div class="h4 mb-0">
                            {{ \App\Models\User::count() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Recent Posts -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">{{ __('Recent Posts') }}</h5>
                    <a href="{{ route('admin.blog.posts.index') }}" class="btn btn-sm btn-outline-primary">{{ __('View all') }}</a>
                </div>
                <div class="card-body p-0">
                    @php
                        $recentPosts = \Modules\Blog\Models\Post::latest()->limit(5)->get();
                    @endphp
                    @if($recentPosts->count() > 0)
                        <ul class="list-group list-group-flush">
                            @foreach($recentPosts as $post)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-medium">{{ $post->title }}</div>
                                        <small class="text-muted">{{ $post->created_at->diffForHumans() }}</small>
                                    </div>
                                    <span class="badge bg-{{ $post->status === 'published' ? 'success' : 'secondary' }}">
                                        {{ $post->status }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="p-4 text-center text-muted">{{ __('No posts yet.') }}</div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Recent Pages -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">{{ __('Recent Pages') }}</h5>
                    <a href="{{ route('admin.pages.index') }}" class="btn btn-sm btn-outline-primary">{{ __('View all') }}</a>
                </div>
                <div class="card-body p-0">
                    @php
                        $recentPages = \Modules\Pages\Models\Page::latest()->limit(5)->get();
                    @endphp
                    @if($recentPages->count() > 0)
                        <ul class="list-group list-group-flush">
                            @foreach($recentPages as $page)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-medium">{{ $page->title }}</div>
                                        <small class="text-muted">{{ $page->created_at->diffForHumans() }}</small>
                                    </div>
                                    <span class="badge bg-{{ $page->status === 'published' ? 'success' : 'secondary' }}">
                                        {{ $page->status }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <div class="p-4 text-center text-muted">{{ __('No pages yet.') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
