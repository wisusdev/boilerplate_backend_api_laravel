@extends('layouts.admin')

@section('title', __('Create Menu'))

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.menus.index') }}">{{ __('Menus') }}</a></li>
                    <li class="breadcrumb-item active">{{ __('Create') }}</li>
                </ol>
            </nav>
            <h1 class="h3 mt-2">{{ __('Create Menu') }}</h1>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-body">
                    <form action="{{ route('admin.menus.store') }}" method="POST">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('Menu Name') }} <span class="text-danger">*</span></label>
                            <input type="text" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name') }}"
                                   placeholder="{{ __('e.g., Main Navigation') }}"
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label">{{ __('Display Location') }}</label>
                            <select class="form-select @error('location') is-invalid @enderror" id="location" name="location">
                                <option value="">{{ __('— Select location —') }}</option>
                                @foreach($locations as $key => $label)
                                    <option value="{{ $key }}" {{ old('location') === $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">{{ __('Assign to a theme location for automatic display.') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">{{ __('Description') }}</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" 
                                      name="description" 
                                      rows="3"
                                      placeholder="{{ __('Optional description for this menu.') }}">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> {{ __('Create Menu') }}
                            </button>
                            <a href="{{ route('admin.menus.index') }}" class="btn btn-outline-secondary">
                                {{ __('Cancel') }}
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-info-circle me-1"></i> {{ __('How menus work') }}
                    </h6>
                    <p class="card-text small text-muted mb-2">
                        {{ __('Create a menu and add items to it. You can add:') }}
                    </p>
                    <ul class="small text-muted mb-0">
                        <li>{{ __('Custom links') }}</li>
                        <li>{{ __('Pages') }}</li>
                        <li>{{ __('Blog posts') }}</li>
                        <li>{{ __('Categories') }}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
