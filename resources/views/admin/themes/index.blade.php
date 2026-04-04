@extends('layouts.admin')

@section('title', __('Themes'))

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Themes') }}</h1>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary fs-6">{{ count($themes) }} {{ __('themes') }}</span>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#installThemeModal">
                <i class="bi bi-cloud-upload me-1"></i>{{ __('Install Theme') }}
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="row g-4">
        @foreach($themes as $name => $theme)
            @php $isActive = ($name === $activeTheme); @endphp
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 {{ $isActive ? 'border-primary border-2' : '' }}">
                    @if(!empty($theme['preview']))
                        <img src="{{ asset('themes/' . $name . '/' . $theme['preview']) }}"
                             class="card-img-top object-fit-cover"
                             style="height:180px;"
                             alt="{{ $theme['label'] ?? $name }}"
                             onerror="this.style.display='none'">
                    @else
                        <div class="card-img-top bg-body-secondary d-flex align-items-center justify-content-center" style="height:180px;">
                            <i class="bi bi-palette fs-1 text-secondary"></i>
                        </div>
                    @endif

                    <div class="card-body d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-1">
                            <h5 class="card-title mb-0">{{ $theme['label'] ?? ucfirst($name) }}</h5>
                            @if($isActive)
                                <span class="badge bg-primary ms-2">{{ __('Active') }}</span>
                            @endif
                        </div>

                        @if(!empty($theme['author']))
                            <small class="text-muted mb-2">{{ __('By') }} {{ $theme['author'] }}</small>
                        @endif

                        @if(!empty($theme['description']))
                            <p class="card-text text-muted small flex-grow-1">{{ $theme['description'] }}</p>
                        @else
                            <div class="flex-grow-1"></div>
                        @endif

                        <div class="d-flex align-items-center justify-content-between mt-3">
                            @if(!empty($theme['version']))
                                <small class="text-muted">v{{ $theme['version'] }}</small>
                            @else
                                <span></span>
                            @endif

                            @if($isActive)
                                <button class="btn btn-sm btn-primary" disabled>
                                    <i class="bi bi-check-circle me-1"></i>{{ __('Current Theme') }}
                                </button>
                            @else
                                <form action="{{ route('admin.themes.activate', $name) }}" method="POST"
                                      onsubmit="return confirm('{{ __('Activate :name theme?', ['name' => $theme['label'] ?? ucfirst($name)]) }}')">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-palette me-1"></i>{{ __('Activate') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Install Theme Modal -->
<div class="modal fade" id="installThemeModal" tabindex="-1" aria-labelledby="installThemeModalLabel" aria-modal="true" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('admin.themes.install') }}" method="POST" enctype="multipart/form-data" id="installThemeForm">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="installThemeModalLabel">
                        <i class="bi bi-cloud-upload me-2"></i>{{ __('Install Theme') }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        {!! __('Upload a ZIP file containing a valid theme. The ZIP must contain a :file with at least a <code>slug</code> and <code>name</code> field.', ['file' => '<code>theme.json</code>']) !!}
                    </p>
                    <div class="mb-3">
                        <label for="theme_zip" class="form-label fw-semibold">{{ __('Theme ZIP file') }} <span class="text-danger">*</span></label>
                        <input type="file"
                               class="form-control @error('theme_zip') is-invalid @enderror"
                               id="theme_zip"
                               name="theme_zip"
                               accept=".zip"
                               required>
                        @error('theme_zip')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">{{ __('Max size: 50 MB. Only .zip files accepted.') }}</div>
                    </div>
                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        {{ __('Only install themes from trusted sources. Malicious themes can compromise your site.') }}
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" id="installThemeBtn">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="installThemeSpinner"></span>
                        <i class="bi bi-cloud-upload me-1" id="installThemeIcon"></i>{{ __('Install') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('installThemeForm').addEventListener('submit', function () {
    const btn = document.getElementById('installThemeBtn');
    const spinner = document.getElementById('installThemeSpinner');
    const icon = document.getElementById('installThemeIcon');
    btn.disabled = true;
    spinner.classList.remove('d-none');
    icon.classList.add('d-none');
});

// Re-open modal if there's a validation error on theme_zip
@error('theme_zip')
document.addEventListener('DOMContentLoaded', function () {
    new bootstrap.Modal(document.getElementById('installThemeModal')).show();
});
@enderror
</script>
@endpush
@endsection
