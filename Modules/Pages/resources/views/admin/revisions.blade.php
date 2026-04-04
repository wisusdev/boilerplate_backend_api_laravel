@extends('layouts.admin')

@section('title', __('Revisions') . ' - ' . $page->title)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Revisions') }}: {{ $page->title }}</h1>
        <a href="{{ route('admin.pages.edit', $page) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> {{ __('Back to Edit') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('Modified By') }}</th>
                        <th>{{ __('Changes') }}</th>
                        <th>{{ __('Date') }}</th>
                        <th width="150">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($revisions as $index => $revision)
                        <tr>
                            <td>{{ $revisions->total() - ($revisions->perPage() * ($revisions->currentPage() - 1)) - $index }}</td>
                            <td>
                                @if($revision->user)
                                    {{ $revision->user->first_name }} {{ $revision->user->last_name }}
                                @else
                                    <span class="text-muted">{{ __('Unknown') }}</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $data = $revision->data;
                                    $fields = [];
                                    if (isset($data['title'])) $fields[] = 'Title';
                                    if (isset($data['content'])) $fields[] = 'Content';
                                    if (isset($data['content_html'])) $fields[] = 'Builder Content';
                                    if (isset($data['meta'])) $fields[] = 'Meta';
                                    if (isset($data['status'])) $fields[] = 'Status';
                                @endphp
                                <small class="text-muted">{{ implode(', ', $fields) ?: __('Initial version') }}</small>
                            </td>
                            <td>
                                <small class="text-muted">{{ $revision->created_at->format('M j, Y g:i a') }}</small>
                                <br>
                                <small class="text-muted">{{ $revision->created_at->diffForHumans() }}</small>
                            </td>
                            <td>
                                <form action="{{ route('admin.pages.revisions.restore', [$page, $revision->id]) }}" method="POST" onsubmit="return confirm('{{ __('Restore this revision? Current content will be saved as a new revision.') }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-arrow-counterclockwise"></i> {{ __('Restore') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <p class="text-muted mb-0">{{ __('No revisions found.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($revisions->hasPages())
            <div class="card-footer">
                {{ $revisions->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
