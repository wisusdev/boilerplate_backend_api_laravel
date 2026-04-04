@extends('layouts.admin')

@section('title', __('Edit Page') . ' - ' . $page->title)

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Edit Page') }}</h1>
        <div>
            <a href="{{ route('admin.pages.builder', $page) }}" class="btn btn-info me-2">
                <i class="bi bi-grid-3x3-gap"></i> {{ __('Visual Builder') }}
            </a>
            <a href="{{ route('admin.pages.preview', $page) }}" class="btn btn-outline-secondary me-2" target="_blank">
                <i class="bi bi-eye"></i> {{ __('Preview') }}
            </a>
            <a href="{{ route('admin.pages.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> {{ __('Back') }}
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('admin.pages.update', $page) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $page->title) }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">{{ __('Slug') }}</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ url('/') }}/</span>
                                <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug', $page->slug) }}">
                            </div>
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        
                        @if(!$page->content_html)
                            <div class="mb-3">
                                <label for="content" class="form-label">{{ __('Content') }}</label>
                                <textarea class="form-control @error('content') is-invalid @enderror" id="content" name="content" rows="10">{{ old('content', $page->content) }}</textarea>
                                <div class="form-text">{{ __('Basic content. Use the Visual Builder for advanced layouts.') }}</div>
                                @error('content')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        @endif

                        @if($page->content_html)
                            <div class="mb-3">
                                <label class="form-label">{{ __('Visual Builder Content Preview') }}</label>
                                <div class="border rounded p-3 bg-light" style="max-height: 200px; overflow-y: auto;">
                                    <small class="text-muted">{{ __('This page has visual builder content. Use the Visual Builder to edit.') }}</small>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ __('SEO Settings') }}</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="meta_seo_title" class="form-label">{{ __('SEO Title') }}</label>
                            <input type="text" class="form-control" id="meta_seo_title" name="meta[seo_title]" value="{{ old('meta.seo_title', $page->meta['seo_title'] ?? '') }}" maxlength="70">
                            <div class="form-text">{{ __('Recommended: 50-60 characters') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="meta_seo_description" class="form-label">{{ __('SEO Description') }}</label>
                            <textarea class="form-control" id="meta_seo_description" name="meta[seo_description]" rows="2" maxlength="160">{{ old('meta.seo_description', $page->meta['seo_description'] ?? '') }}</textarea>
                            <div class="form-text">{{ __('Recommended: 150-160 characters') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="meta_seo_keywords" class="form-label">{{ __('SEO Keywords') }}</label>
                            <input type="text" class="form-control" id="meta_seo_keywords" name="meta[seo_keywords]" value="{{ old('meta.seo_keywords', $page->meta['seo_keywords'] ?? '') }}">
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
                                <option value="draft" {{ old('status', $page->status) === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                                <option value="published" {{ old('status', $page->status) === 'published' ? 'selected' : '' }}>{{ __('Published') }}</option>
                                <option value="scheduled" {{ old('status', $page->status) === 'scheduled' ? 'selected' : '' }}>{{ __('Scheduled') }}</option>
                                <option value="private" {{ old('status', $page->status) === 'private' ? 'selected' : '' }}>{{ __('Private') }}</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3" id="publishedAtContainer" style="display: {{ $page->status === 'scheduled' ? 'block' : 'none' }};">
                            <label for="published_at" class="form-label">{{ __('Publish Date') }}</label>
                            <input type="datetime-local" class="form-control @error('published_at') is-invalid @enderror" id="published_at" name="published_at" value="{{ old('published_at', $page->published_at?->format('Y-m-d\TH:i')) }}">
                            @error('published_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <small class="text-muted">
                                {{ __('Created') }}: {{ $page->created_at->format('M j, Y g:i a') }}<br>
                                {{ __('Updated') }}: {{ $page->updated_at->format('M j, Y g:i a') }}
                            </small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> {{ __('Update Page') }}
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
                                    <option value="{{ $parent->id }}" {{ old('parent_id', $page->parent_id) == $parent->id ? 'selected' : '' }}>
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
                                    <option value="{{ $key }}" {{ old('template', $page->template ?? 'default') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('template')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="order" class="form-label">{{ __('Order') }}</label>
                            <input type="number" class="form-control @error('order') is-invalid @enderror" id="order" name="order" value="{{ old('order', $page->order) }}" min="0">
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
                        @if($page->featured_image)
                            <img src="{{ $page->featured_image }}" alt="" class="img-fluid rounded mb-3">
                        @endif
                        <div class="mb-3">
                            <input type="text" class="form-control @error('featured_image') is-invalid @enderror" id="featured_image" name="featured_image" value="{{ old('featured_image', $page->featured_image) }}" placeholder="{{ __('Image URL or path') }}">
                            @error('featured_image')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="selectImageBtn">
                            <i class="bi bi-image"></i> {{ __('Select Image') }}
                        </button>
                    </div>
                </div>

                @if($page->revisions->isNotEmpty())
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">{{ __('Revisions') }}</h5>
                            <a href="{{ route('admin.pages.revisions', $page) }}" class="btn btn-sm btn-outline-secondary">{{ __('View All') }}</a>
                        </div>
                        <div class="list-group list-group-flush">
                            @foreach($page->revisions->take(5) as $revision)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">{{ $revision->created_at->format('M j, Y g:i a') }}</small>
                                        <br>
                                        <small>{{ $revision->user?->first_name ?? __('Unknown') }}</small>
                                    </div>
                                    <form action="{{ route('admin.pages.revisions.restore', [$page, $revision]) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('{{ __('Restore this revision?') }}')">
                                            {{ __('Restore') }}
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </form>
</div>

@include('media::components.media-selector-modal', ['modalId' => 'pageFeaturedImageModal'])
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

    // Featured Image selector
    document.getElementById('selectImageBtn').addEventListener('click', function() {
        MediaSelector_pageFeaturedImageModal.show(function(media) {
            const featuredImageInput = document.getElementById('featured_image');
            featuredImageInput.value = media.url;
            
            // Update preview if image exists
            const imgPreview = featuredImageInput.parentElement.parentElement.querySelector('img');
            if (imgPreview) {
                imgPreview.src = media.url;
            } else {
                // Create preview if it doesn't exist
                const newImg = document.createElement('img');
                newImg.src = media.url;
                newImg.alt = '';
                newImg.className = 'img-fluid rounded mb-3';
                featuredImageInput.parentElement.parentElement.insertBefore(newImg, featuredImageInput.parentElement);
            }
        });
    });
});
</script>
@endpush
