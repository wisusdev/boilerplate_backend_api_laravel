@extends('layouts.admin')

@section('title', __('Pages'))

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Pages') }}</h1>
        <div>
            <a href="{{ route('admin.pages.trash') }}" class="btn btn-outline-secondary me-2">
                <i class="bi bi-trash"></i> {{ __('Trash') }}
            </a>
            <a href="{{ route('admin.pages.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> {{ __('Add New Page') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="{{ __('Search...') }}" value="{{ request('search') }}">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">{{ __('All Status') }}</option>
                        <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>{{ __('Published') }}</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                        <option value="scheduled" {{ request('status') === 'scheduled' ? 'selected' : '' }}>{{ __('Scheduled') }}</option>
                        <option value="private" {{ request('status') === 'private' ? 'selected' : '' }}>{{ __('Private') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary">{{ __('Filter') }}</button>
                </div>
            </form>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Author') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th width="200">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pages as $page)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    @if($page->featured_image)
                                        <img src="{{ $page->featured_image }}" alt="" class="me-2" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                    @endif
                                    <div>
                                        <a href="{{ route('admin.pages.edit', $page) }}" class="fw-semibold text-decoration-none">
                                            {{ $page->title }}
                                        </a>
                                        @if($page->parent)
                                            <small class="text-muted d-block">— {{ $page->parent->title }}</small>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>{{ $page->author?->first_name }} {{ $page->author?->last_name }}</td>
                            <td>
                                @switch($page->status)
                                    @case('published')
                                        <span class="badge bg-success">{{ __('Published') }}</span>
                                        @break
                                    @case('draft')
                                        <span class="badge bg-secondary">{{ __('Draft') }}</span>
                                        @break
                                    @case('scheduled')
                                        <span class="badge bg-info">{{ __('Scheduled') }}</span>
                                        @break
                                    @case('private')
                                        <span class="badge bg-warning">{{ __('Private') }}</span>
                                        @break
                                @endswitch
                            </td>
                            <td>
                                <small class="text-muted">
                                    @if($page->published_at)
                                        {{ $page->published_at->format('M j, Y') }}
                                    @else
                                        {{ $page->created_at->format('M j, Y') }}
                                    @endif
                                </small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-outline-primary" title="{{ __('Edit') }}">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="{{ route('admin.pages.builder', $page) }}" class="btn btn-outline-info" title="{{ __('Visual Builder') }}">
                                        <i class="bi bi-grid-3x3-gap"></i>
                                    </a>
                                    <a href="{{ route('admin.pages.preview', $page) }}" class="btn btn-outline-secondary" target="_blank" title="{{ __('Preview') }}">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <form action="{{ route('admin.pages.duplicate', $page) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-secondary" title="{{ __('Duplicate') }}">
                                            <i class="bi bi-copy"></i>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.pages.destroy', $page) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="{{ __('Delete') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <p class="text-muted mb-0">{{ __('No pages found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($pages->hasPages())
            <div class="card-footer">
                {{ $pages->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
