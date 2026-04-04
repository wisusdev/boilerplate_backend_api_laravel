@extends('layouts.admin')

@section('title', __('Edit Post') . ' - ' . $post->title)

@push('styles')
<!-- Quill.js -->
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js"></script>
@endpush

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Edit Post') }}</h1>
        <div>
            <a href="{{ route('admin.blog.posts.preview', $post) }}" class="btn btn-outline-secondary me-2" target="_blank">
                <i class="bi bi-eye"></i> {{ __('Preview') }}
            </a>
            <a href="{{ route('admin.blog.posts.index') }}" class="btn btn-outline-secondary">
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

    <form action="{{ route('admin.blog.posts.update', $post) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="title" class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $post->title) }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="slug" class="form-label">{{ __('Slug') }}</label>
                            <div class="input-group">
                                <span class="input-group-text">{{ url('/blog') }}/</span>
                                <input type="text" class="form-control @error('slug') is-invalid @enderror" id="slug" name="slug" value="{{ old('slug', $post->slug) }}">
                            </div>
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="excerpt" class="form-label">{{ __('Excerpt') }}</label>
                            <textarea class="form-control @error('excerpt') is-invalid @enderror" id="excerpt" name="excerpt" rows="3" maxlength="500">{{ old('excerpt', $post->excerpt) }}</textarea>
                            <div class="form-text">{{ __('Brief summary for listings. Max 500 characters.') }}</div>
                            @error('excerpt')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">{{ __('Content') }}</label>
                            <div id="content" style="min-height: 300px; background: white;"></div>
                            <input type="hidden" name="content" id="content_input" value="{{ old('content', $post->content) }}">
                            @error('content')
                                <div class="invalid-feedback d-block">{{ $message }}</div>
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
                            <input type="text" class="form-control" id="meta_seo_title" name="meta[seo_title]" value="{{ old('meta.seo_title', $post->meta['seo_title'] ?? '') }}" maxlength="70">
                            <div class="form-text">{{ __('Recommended: 50-60 characters') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="meta_seo_description" class="form-label">{{ __('SEO Description') }}</label>
                            <textarea class="form-control" id="meta_seo_description" name="meta[seo_description]" rows="2" maxlength="160">{{ old('meta.seo_description', $post->meta['seo_description'] ?? '') }}</textarea>
                            <div class="form-text">{{ __('Recommended: 150-160 characters') }}</div>
                        </div>

                        <div class="mb-3">
                            <label for="meta_seo_keywords" class="form-label">{{ __('SEO Keywords') }}</label>
                            <input type="text" class="form-control" id="meta_seo_keywords" name="meta[seo_keywords]" value="{{ old('meta.seo_keywords', $post->meta['seo_keywords'] ?? '') }}">
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
                                <option value="draft" {{ old('status', $post->status) === 'draft' ? 'selected' : '' }}>{{ __('Draft') }}</option>
                                <option value="published" {{ old('status', $post->status) === 'published' ? 'selected' : '' }}>{{ __('Published') }}</option>
                                <option value="scheduled" {{ old('status', $post->status) === 'scheduled' ? 'selected' : '' }}>{{ __('Scheduled') }}</option>
                                <option value="private" {{ old('status', $post->status) === 'private' ? 'selected' : '' }}>{{ __('Private') }}</option>
                            </select>
                            @error('status')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3" id="publishedAtContainer" style="display: {{ $post->status === 'scheduled' ? 'block' : 'none' }};">
                            <label for="published_at" class="form-label">{{ __('Publish Date') }}</label>
                            <input type="datetime-local" class="form-control @error('published_at') is-invalid @enderror" id="published_at" name="published_at" value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}">
                            @error('published_at')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="is_featured" name="is_featured" value="1" {{ old('is_featured', $post->is_featured) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_featured">{{ __('Featured Post') }}</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="allow_comments" name="allow_comments" value="1" {{ old('allow_comments', $post->allow_comments) ? 'checked' : '' }}>
                                <label class="form-check-label" for="allow_comments">{{ __('Allow Comments') }}</label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted">
                                {{ __('Created') }}: {{ $post->created_at->format('M j, Y g:i a') }}<br>
                                {{ __('Updated') }}: {{ $post->updated_at->format('M j, Y g:i a') }}<br>
                                {{ __('Views') }}: {{ number_format($post->views_count) }}
                            </small>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg"></i> {{ __('Update Post') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ __('Categories') }}</h5>
                    </div>
                    <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                        @php
                            $selectedCategories = old('categories', $post->categories->pluck('id')->toArray());
                        @endphp
                        @foreach($categories as $category)
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="category_{{ $category->id }}" name="categories[]" value="{{ $category->id }}" {{ in_array($category->id, $selectedCategories) ? 'checked' : '' }}>
                                <label class="form-check-label" for="category_{{ $category->id }}">{{ $category->name }}</label>
                            </div>
                        @endforeach
                        @if($categories->isEmpty())
                            <p class="text-muted mb-0">{{ __('No categories found.') }}</p>
                        @endif
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ __('Tags') }}</h5>
                    </div>
                    <div class="card-body">
                        <input type="text" class="form-control" id="tags_input" placeholder="{{ __('Add tags separated by commas') }}">
                        <div class="form-text">{{ __('Type and press Enter to add tags.') }}</div>
                        <div id="tags_container" class="mt-2"></div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">{{ __('Featured Image') }}</h5>
                    </div>
                    <div class="card-body">
                        @if($post->featured_image)
                            <img src="{{ $post->featured_image }}" alt="" class="img-fluid rounded mb-3">
                        @endif
                        <div class="mb-3">
                            <input type="text" class="form-control @error('featured_image') is-invalid @enderror" id="featured_image" name="featured_image" value="{{ old('featured_image', $post->featured_image) }}" placeholder="{{ __('Image URL or path') }}">
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

@include('media::components.media-selector-modal', ['modalId' => 'postFeaturedImageModal'])
@include('media::components.media-selector-modal', ['modalId' => 'postContentImageModal'])
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Quill.js
    const quill = new Quill('#content', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                [{ 'align': [] }],
                ['link', 'image', 'code-block'],
                ['clean']
            ]
        },
        placeholder: 'Write your content here...'
    });

    // Load existing content from hidden input
    const contentInput = document.getElementById('content_input');
    if (contentInput.value) {
        quill.root.innerHTML = contentInput.value;
    }

    // Custom image handler to use Media Library
    quill.getModule('toolbar').addHandler('image', function() {
        MediaSelector_postContentImageModal.show(function(media) {
            const range = quill.getSelection(true);
            quill.insertEmbed(range.index, 'image', media.url);
            quill.setSelection(range.index + 1);
        });
    });

    // Sync Quill content to hidden input before form submission
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        contentInput.value = quill.root.innerHTML;
    });

    // Status change handler
    const statusSelect = document.getElementById('status');
    const publishedAtContainer = document.getElementById('publishedAtContainer');

    function togglePublishedAt() {
        publishedAtContainer.style.display = statusSelect.value === 'scheduled' ? 'block' : 'none';
    }

    statusSelect.addEventListener('change', togglePublishedAt);

    // Tags handling
    const tagsInput = document.getElementById('tags_input');
    const tagsContainer = document.getElementById('tags_container');
    let tags = @json($post->tags->pluck('name')->toArray());

    function renderTags() {
        tagsContainer.innerHTML = tags.map((tag, index) => `
            <span class="badge bg-primary me-1 mb-1">
                ${tag}
                <button type="button" class="btn-close btn-close-white ms-1" style="font-size: 0.6em;" onclick="removeTag(${index})"></button>
            </span>
        `).join('');

        // Create hidden inputs for tags
        document.querySelectorAll('input[name="tags[]"]').forEach(el => el.remove());
        tags.forEach(tag => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'tags[]';
            input.value = tag;
            tagsContainer.appendChild(input);
        });
    }

    window.removeTag = function(index) {
        tags.splice(index, 1);
        renderTags();
    };

    tagsInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ',') {
            e.preventDefault();
            const value = this.value.trim().replace(/,/g, '');
            if (value && !tags.includes(value)) {
                tags.push(value);
                renderTags();
            }
            this.value = '';
        }
    });

    tagsInput.addEventListener('blur', function() {
        const value = this.value.trim();
        if (value && !tags.includes(value)) {
            tags.push(value);
            renderTags();
        }
        this.value = '';
    });

    // Render initial tags
    renderTags();

    // Featured Image selector
    document.getElementById('selectImageBtn').addEventListener('click', function() {
        MediaSelector_postFeaturedImageModal.show(function(media) {
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
