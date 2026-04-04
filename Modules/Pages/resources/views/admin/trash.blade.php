@extends('layouts.admin')

@section('title', __('Trash'))

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Trashed Pages') }}</h1>
        <a href="{{ route('admin.pages.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> {{ __('Back to Pages') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>{{ __('Title') }}</th>
                        <th>{{ __('Author') }}</th>
                        <th>{{ __('Deleted At') }}</th>
                        <th width="200">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pages as $page)
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $page->title }}</span>
                            </td>
                            <td>{{ $page->author?->first_name }} {{ $page->author?->last_name }}</td>
                            <td>
                                <small class="text-muted">{{ $page->deleted_at->format('M j, Y g:i a') }}</small>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <form action="{{ route('admin.pages.restore', $page->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-success" title="{{ __('Restore') }}">
                                            <i class="bi bi-arrow-counterclockwise"></i> {{ __('Restore') }}
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.pages.force-delete', $page->id) }}" method="POST" class="d-inline" onsubmit="return confirm('{{ __('Permanently delete this page? This cannot be undone.') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger" title="{{ __('Delete Permanently') }}">
                                            <i class="bi bi-trash"></i> {{ __('Delete') }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4">
                                <p class="text-muted mb-0">{{ __('No trashed pages found.') }}</p>
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
