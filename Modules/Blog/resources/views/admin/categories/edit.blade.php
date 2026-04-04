@extends('layouts.admin')

@section('title', __('Edit Category'))

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Edit Category') }}: <span class="text-muted fw-normal">{{ $category->name }}</span></h1>
        <a href="{{ route('admin.blog.categories.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>{{ __('Back') }}
        </a>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.blog.categories.update', $category) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('Name') }} <span class="text-danger">*</span></label>
                            <input type="text" id="name" name="name"
                                   class="form-control @error('name') is-invalid @enderror"
                                   value="{{ old('name', $category->name) }}" required autofocus>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">{{ __('Slug') }}</label>
                            <input type="text" id="slug" name="slug"
                                   class="form-control @error('slug') is-invalid @enderror"
                                   value="{{ old('slug', $category->slug) }}">
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
                                    <option value="{{ $parent->id }}"
                                        {{ old('parent_id', $category->parent_id) == $parent->id ? 'selected' : '' }}>
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
                                      class="form-control @error('description') is-invalid @enderror">{{ old('description', $category->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i>{{ __('Update Category') }}
                            </button>
                            <form action="{{ route('admin.blog.categories.destroy', $category) }}"
                                  method="POST" class="d-inline"
                                  onsubmit="return confirm('{{ __('Are you sure?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="bi bi-trash me-1"></i>{{ __('Delete') }}
                                </button>
                            </form>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
