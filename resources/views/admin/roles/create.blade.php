@extends('layouts.admin')

@section('title', __('Create Role'))

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Create Role') }}</h1>
        <a href="{{ route('admin.roles.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}</a>
    </div>
    <div class="row"><div class="col-lg-8">
        <div class="card"><div class="card-body">
            <form method="POST" action="{{ route('admin.roles.store') }}">
                @csrf
                <div class="mb-4">
                    <label class="form-label">{{ __('Role Name') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required autofocus>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">{{ __('Permissions') }}</label>
                    @foreach($permissions as $resource => $perms)
                        <div class="card mb-2">
                            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                <span class="fw-semibold text-capitalize">{{ $resource }}</span>
                                <button type="button" class="btn btn-sm btn-outline-secondary toggle-all" data-group="{{ $resource }}">{{ __('Select All') }}</button>
                            </div>
                            <div class="card-body py-2">
                                <div class="row g-2">
                                    @foreach($perms as $permission)
                                        <div class="col-6 col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input perm-{{ $resource }}" type="checkbox"
                                                       name="permissions[]" value="{{ $permission->name }}"
                                                       id="perm_{{ $permission->id }}"
                                                       {{ in_array($permission->name, old('permissions', [])) ? 'checked' : '' }}>
                                                <label class="form-check-label small" for="perm_{{ $permission->id }}">{{ $permission->name }}</label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>{{ __('Create Role') }}</button>
            </form>
        </div></div>
    </div></div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.toggle-all').forEach(btn => {
    btn.addEventListener('click', () => {
        const group = btn.dataset.group;
        const checkboxes = document.querySelectorAll('.perm-' + group);
        const allChecked = Array.from(checkboxes).every(c => c.checked);
        checkboxes.forEach(c => c.checked = !allChecked);
        btn.textContent = allChecked ? '{{ __("Select All") }}' : '{{ __("Deselect All") }}';
    });
});
</script>
@endpush
