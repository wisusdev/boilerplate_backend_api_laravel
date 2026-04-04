@extends('layouts.admin')

@section('title', __('Menus'))

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Menus') }}</h1>
        <a href="{{ route('admin.menus.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> {{ __('New Menu') }}
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('All Menus') }}</h5>
                </div>
                <div class="card-body p-0">
                    @if($menus->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>{{ __('Name') }}</th>
                                        <th>{{ __('Location') }}</th>
                                        <th>{{ __('Items') }}</th>
                                        <th>{{ __('Created') }}</th>
                                        <th width="120">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($menus as $menu)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.menus.edit', $menu) }}" class="fw-medium text-decoration-none">
                                                    {{ $menu->name }}
                                                </a>
                                            </td>
                                            <td>
                                                @if($menu->location)
                                                    <span class="badge bg-primary">
                                                        {{ $locations[$menu->location] ?? $menu->location }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td>{{ $menu->items->count() }}</td>
                                            <td>{{ $menu->created_at->format('M d, Y') }}</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('admin.menus.edit', $menu) }}" 
                                                       class="btn btn-outline-primary" 
                                                       title="{{ __('Edit') }}">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" 
                                                            class="btn btn-outline-danger" 
                                                            title="{{ __('Delete') }}"
                                                            onclick="confirmDelete('{{ $menu->id }}', '{{ $menu->name }}')">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                                <form id="delete-form-{{ $menu->id }}" 
                                                      action="{{ route('admin.menus.destroy', $menu) }}" 
                                                      method="POST" 
                                                      class="d-none">
                                                    @csrf
                                                    @method('DELETE')
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-list-ul display-1 text-muted"></i>
                            <p class="mt-3 text-muted">{{ __('No menus created yet.') }}</p>
                            <a href="{{ route('admin.menus.create') }}" class="btn btn-primary">
                                {{ __('Create your first menu') }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Menu Locations') }}</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        {{ __('Assign menus to theme locations for automatic display.') }}
                    </p>
                    <ul class="list-group list-group-flush">
                        @foreach($locations as $key => $label)
                            @php
                                $assignedMenu = $menus->firstWhere('location', $key);
                            @endphp
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>{{ $label }}</span>
                                @if($assignedMenu)
                                    <a href="{{ route('admin.menus.edit', $assignedMenu) }}" 
                                       class="badge bg-success text-decoration-none">
                                        {{ $assignedMenu->name }}
                                    </a>
                                @else
                                    <span class="badge bg-secondary">{{ __('Not assigned') }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function confirmDelete(id, name) {
    if (confirm(`{{ __('Are you sure you want to delete the menu') }} "${name}"?`)) {
        document.getElementById('delete-form-' + id).submit();
    }
}
</script>
@endpush
