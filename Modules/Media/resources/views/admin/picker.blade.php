@extends('layouts.admin')

@section('title', __('Media Picker'))

@section('content')
<div class="container-fluid">
    <div class="mb-3">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="{{ __('Search...') }}" value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="type" class="form-select form-select-sm">
                    <option value="">{{ __('All Types') }}</option>
                    <option value="image" {{ request('type') === 'image' ? 'selected' : '' }}>{{ __('Images') }}</option>
                    <option value="video" {{ request('type') === 'video' ? 'selected' : '' }}>{{ __('Videos') }}</option>
                    <option value="document" {{ request('type') === 'document' ? 'selected' : '' }}>{{ __('Documents') }}</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">{{ __('Filter') }}</button>
            </div>
        </form>
    </div>

    <div class="row g-2" id="pickerGrid">
        @forelse($media as $item)
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <div class="card h-100 picker-item" style="cursor: pointer;"
                     data-id="{{ $item->id }}"
                     data-url="{{ $item->getUrl() }}"
                     data-name="{{ $item->name }}"
                     data-type="{{ $item->type }}">
                    @if($item->type === 'image')
                        <img src="{{ $item->getUrl() }}" alt="{{ $item->alt }}"
                             class="card-img-top" style="height: 100px; object-fit: cover;">
                    @else
                        <div class="card-img-top d-flex align-items-center justify-content-center bg-light" style="height: 100px;">
                            <i class="bi bi-file-earmark display-5 text-muted"></i>
                        </div>
                    @endif
                    <div class="card-body p-1">
                        <small class="text-truncate d-block" title="{{ $item->name }}">{{ $item->name }}</small>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12 text-center py-4 text-muted">
                {{ __('No files found.') }}
            </div>
        @endforelse
    </div>

    @if($media->hasPages())
        <div class="mt-3">{{ $media->links() }}</div>
    @endif
</div>

@push('scripts')
<script>
document.querySelectorAll('.picker-item').forEach(item => {
    item.addEventListener('click', () => {
        const selected = {
            id: item.dataset.id,
            url: item.dataset.url,
            name: item.dataset.name,
            type: item.dataset.type,
        };
        if (window.opener) {
            window.opener.postMessage({ type: 'media-selected', media: selected }, '*');
            window.close();
        } else if (window.parent !== window) {
            window.parent.postMessage({ type: 'media-selected', media: selected }, '*');
        }
    });
});
</script>
@endpush
@endsection
