@extends('layouts.admin')

@section('title', __('Create Category'))

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Create Category') }}</h1>
        <a href="{{ route('admin.blog.categories.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}
        </a>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.blog.categories.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name') }}" required autofocus>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">{{ __('Slug') }}</label>
                            <input type="text" id="slug" name="slug"
                                   class="form-control @error('slug') is-invalid @enderror"
                                   value="{{ old('slug') }}"
                                   placeholder="{{ __('Auto-generated if empty') }}">
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="parent_id" class="form-label">{{ __('Parent Category') }}</label>
                            <select id="parent_id" name="parent_id"
                                    class="form-select @error('parent_id') is-invalid @enderror">
                                <option value="">{{ __('None') }}</option>
                                @foreach($parents as $parent)
                                    <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                        {{ $parent->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">{{ __('Description') }}</label>
                            <textarea id="description" name="description" rows="3"
                                      class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>{{ __('Save Category') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
