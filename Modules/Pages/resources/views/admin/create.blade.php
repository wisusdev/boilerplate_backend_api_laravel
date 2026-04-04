@extends('layouts.admin')

@section('title', __('Create Page'))

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Create Page') }}</h1>
        <a href="{{ route('admin.pages.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> {{ __('Back') }}
        </a>
    </div>

    <form action="{{ route('admin.pages.store') }}" method="POST">
        @csrf

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" required autofocus>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">{{ __('Slug') }}</label>
                            <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug') }}">
                            <div class="form-text">{{ __('Leave empty to auto-generate from title.') }}</div>
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">{{ __('Content') }}</label>
                            <textarea class="form-control @error('content') is-invalid @enderror" id="content" name="content" rows="10">{{ old('content') }}</textarea>
                            <div class="form-text">{{ __('Basic content. Use the Visual Builder for advanced layouts.') }}</div>
                            @error('content')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ __('SEO Settings') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="meta_seo_title" class="form-label">{{ __('SEO Title') }}</label>
                            <input type="text" class="form-control" id="meta_seo_title" name="meta[seo_title]" value="{{ old('meta.seo_title') }}" maxlength="70">
                            <div class="form-text">{{ __('Recommended: 50-60 characters') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="meta_seo_description" class="form-label">{{ __('SEO Description') }}</label>
                            <textarea class="form-control" id="meta_seo_description" name="meta[seo_description]" rows="2" maxlength="160">{{ old('meta.seo_description') }}</textarea>
                            <div class="form-text">{{ __('Recommended: 150-160 characters') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="meta_seo_keywords" class="form-label">{{ __('SEO Keywords') }}</label>
                            <input type="text" class="form-control" id="meta_seo_keywords" name="meta[seo_keywords]" value="{{ old('meta.seo_keywords') }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ __('Publish') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">{{ __('Status') }}</label>
                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                                <option value="draft" {{ old('status', 'draft') === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                                <option value="published" {{ old('status') === 'published' ? 'selected' : '' }}>{{ __('Published') }}</option>
                                <option value="scheduled" {{ old('status') === 'scheduled' ? 'selected' : '' }}>{{ __('Scheduled') }}</option>
                                <option value="private" {{ old('status') === 'private' ? 'selected' : '' }}>{{ __('Private') }}</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3" id="publishedAtContainer" style="display: none;">
                            <label for="published_at" class="form-label">{{ __('Publish Date') }}</label>
                            <input type="datetime-local" class="form-control @error('published_at') is-invalid @enderror" id="published_at" name="published_at" value="{{ old('published_at') }}">
                            @error('published_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> {{ __('Create Page') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ __('Page Attributes') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="parent_id" class="form-label">{{ __('Parent Page') }}</label>
                            <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                                <option value="">{{ __('(no parent)') }}</option>
                                @foreach($parents as $parent)
                                    <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                        {{ $parent->title }}
                                    </option>
                                @endforeach
                            </select>
                            @error('parent_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="template" class="form-label">{{ __('Template') }}</label>
                            <select class="form-select @error('template') is-invalid @enderror" id="template" name="template">
                                @foreach($templates as $key => $label)
                                    <option value="{{ $key }}" {{ old('template', 'default') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('template')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="order" class="form-label">{{ __('Order') }}</label>
                            <input type="number" class="form-control @error('order') is-invalid @enderror" id="order" name="order" value="{{ old('order', 0) }}" min="0">
                            @error('order')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ __('Featured Image') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <input type="text" class="form-control @error('featured_image') is-invalid @enderror" id="featured_image" name="featured_image" value="{{ old('featured_image') }}" placeholder="{{ __('Image URL or path') }}">
                            @error('featured_image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="selectImageBtn">
                            <i class="bi bi-image"></i> {{ __('Select Image') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('status');
    const publishedAtContainer = document.getElementById('publishedAtContainer');

    function togglePublishedAt() {
        publishedAtContainer.style.display = statusSelect.value === 'scheduled' ? 'block' : 'none';
    }

    statusSelect.addEventListener('change', togglePublishedAt);
    togglePublishedAt();

    // Auto-generate slug from title
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');

    titleInput.addEventListener('blur', function() {
        if (!slugInput.value) {
            slugInput.value = titleInput.value
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');
        }
    });
});
</script>
@endpush
