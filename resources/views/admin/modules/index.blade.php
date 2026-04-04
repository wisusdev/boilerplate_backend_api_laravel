@extends('layouts.admin')

@section('title', __('Modules'))

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Modules') }}</h1>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary fs-6">{{ count($modules) }} {{ __('modules') }}</span>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#installModuleModal">
                <i class="bi bi-cloud-upload me-1"></i>{{ __('Install Module') }}
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-3">
        @foreach($modules as $module)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 {{ $module['enabled'] ? '' : 'opacity-75' }}">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <div class="rounded-2 p-2 {{ $module['enabled'] ? 'bg-primary bg-opacity-10' : 'bg-secondary bg-opacity-10' }}">
                                    <i class="bi bi-puzzle fs-5 {{ $module['enabled'] ? 'text-primary' : 'text-secondary' }}"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">{{ $module['name'] }}</div>
                                    <small class="text-muted">v{{ $module['version'] }}</small>
                                </div>
                            </div>
                            @if($module['enabled'])
                                <span class="badge bg-success">{{ __('Enabled') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('Disabled') }}</span>
                            @endif
                        </div>

                        <p class="text-muted small mb-3" style="min-height: 2rem;">{{ $module['description'] }}</p>

                        <div class="d-flex align-items-center justify-content-between">
                            <small class="text-muted font-monospace">{{ $module['path'] }}</small>

                            @if($module['protected'])
                                <button class="btn btn-sm btn-outline-secondary" disabled title="{{ __('Protected module') }}">
                                    <i class="bi bi-lock me-1"></i>{{ __('Protected') }}
                                </button>
                            @else
                                <form action="{{ route('admin.modules.toggle', $module['name']) }}" method="POST"
                                      onsubmit="return confirm('{{ $module['enabled'] ? __('Disable :name?', ['name' => $module['name']]) : __('Enable :name?', ['name' => $module['name']]) }}')">
                                    @csrf
                                    @if($module['enabled'])
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-toggle-on me-1"></i>{{ __('Disable') }}
                                        </button>
                                    @else
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-toggle-off me-1"></i>{{ __('Enable') }}
                                        </button>
                                    @endif
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Install Module Modal -->
<div class="modal fade" id="installModuleModal" tabindex="-1" aria-labelledby="installModuleModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.modules.install') }}" method="POST" enctype="multipart/form-data" id="installModuleForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="installModuleModalLabel">
                        <i class="bi bi-cloud-upload me-2"></i>{{ __('Install Module') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        {{ __('Upload a ZIP file containing a valid Laravel module. The ZIP must contain a :file at the root of the module directory.', ['file' => '<code>module.json</code>']) }}
                    </p>
                    <div class="mb-3">
                        <label for="module_zip" class="form-label fw-semibold">{{ __('Module ZIP file') }} <span class="text-danger">*</span></label>
                        <input type="file"
                               class="form-control @error('module_zip') is-invalid @enderror"
                               id="module_zip"
                               name="module_zip"
                               accept=".zip"
                               required>
                        @error('module_zip')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">{{ __('Max size: 100 MB. Only .zip files accepted.') }}</div>
                    </div>
                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        {{ __('Only install modules from trusted sources. Malicious modules can compromise your site.') }}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="installModuleBtn">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="installModuleSpinner"></span>
                        <i class="bi bi-cloud-upload me-1" id="installModuleIcon"></i>{{ __('Install') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('installModuleForm').addEventListener('submit', function () {
    const btn = document.getElementById('installModuleBtn');
    const spinner = document.getElementById('installModuleSpinner');
    const icon = document.getElementById('installModuleIcon');
    btn.disabled = true;
    spinner.classList.remove('d-none');
    icon.classList.add('d-none');
});

// Re-open modal if there's a validation error on module_zip
@error('module_zip')
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('installModuleModal')).show();
});
@enderror
</script>
@endpush
@endsection
