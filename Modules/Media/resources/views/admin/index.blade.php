@extends('layouts.admin')

@section('title', __('Media Library'))

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Media Library') }}</h1>
        <div>
            <button type="button" class="btn btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#createFolderModal">
                <i class="bi bi-folder-plus"></i> {{ __('New Folder') }}
            </button>
            <button type="button" class="btn btn-primary" id="uploadBtn">
                <i class="bi bi-cloud-upload"></i> {{ __('Upload Files') }}
            </button>
            <input type="file" id="fileInput" multiple style="display: none;">
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Sidebar with folders -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Folders') }}</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="{{ route('admin.media.index') }}" class="list-group-item list-group-item-action {{ !request('folder_id') ? 'active' : '' }}">
                        <i class="bi bi-house me-2"></i> {{ __('All Files') }}
                    </a>
                    @foreach($folders as $folder)
                        <a href="{{ route('admin.media.index', ['folder_id' => $folder['id']]) }}" class="list-group-item list-group-item-action {{ request('folder_id') == $folder['id'] ? 'active' : '' }}">
                            <i class="bi bi-folder me-2"></i> {{ $folder['name'] }}
                        </a>
                        @foreach($folder['children'] ?? [] as $child)
                            <a href="{{ route('admin.media.index', ['folder_id' => $child['id']]) }}" class="list-group-item list-group-item-action ps-5 {{ request('folder_id') == $child['id'] ? 'active' : '' }}">
                                <i class="bi bi-folder me-2"></i> {{ $child['name'] }}
                            </a>
                        @endforeach
                    @endforeach
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Filter') }}</h5>
                </div>
                <div class="card-body">
                    <form method="GET">
                        <input type="hidden" name="folder_id" value="{{ request('folder_id') }}">
                        <div class="mb-3">
                            <input type="text" name="search" class="form-control" placeholder="{{ __('Search...') }}" value="{{ request('search') }}">
                        </div>
                        <div class="mb-3">
                            <select name="type" class="form-select">
                                <option value="">{{ __('All Types') }}</option>
                                <option value="image" {{ request('type') === 'image' ? 'selected' : '' }}>{{ __('Images') }}</option>
                                <option value="video" {{ request('type') === 'video' ? 'selected' : '' }}>{{ __('Videos') }}</option>
                                <option value="audio" {{ request('type') === 'audio' ? 'selected' : '' }}>{{ __('Audio') }}</option>
                                <option value="document" {{ request('type') === 'document' ? 'selected' : '' }}>{{ __('Documents') }}</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">{{ __('Apply') }}</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Media grid -->
        <div class="col-md-9">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        @if($currentFolder)
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-0">
                                    <li class="breadcrumb-item"><a href="{{ route('admin.media.index') }}">{{ __('All Files') }}</a></li>
                                    <li class="breadcrumb-item active">{{ $currentFolder->name }}</li>
                                </ol>
                            </nav>
                        @else
                            <span>{{ __('All Files') }}</span>
                        @endif
                    </div>
                    <div id="bulkActions" style="display: none;">
                        <button type="button" class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn">
                            <i class="bi bi-trash"></i> {{ __('Delete Selected') }}
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="dropZone" class="border border-dashed rounded p-5 text-center mb-4" style="display: none;">
                        <i class="bi bi-cloud-upload display-4 text-muted"></i>
                        <p class="text-muted mt-2">{{ __('Drop files here to upload') }}</p>
                    </div>

                    <div class="row g-3" id="mediaGrid">
                        @forelse($media as $item)
                            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                                <div class="card media-item h-100" data-id="{{ $item->id }}">
                                    <div class="position-relative">
                                        <input type="checkbox" class="position-absolute m-2 media-checkbox" style="z-index: 10;">
                                        <button type="button"
                                            class="btn btn-danger btn-sm position-absolute bottom-0 end-0 m-1 delete-btn"
                                            style="z-index:10; padding: 2px 6px;"
                                            data-id="{{ $item->id }}"
                                            data-name="{{ $item->original_name }}"
                                            title="{{ __('Delete') }}">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        @if($item->type === 'image')
                                            <img src="{{ $item->getUrl() }}" alt="{{ $item->alt }}" class="card-img-top"
                                                style="height: 120px; object-fit: cover; cursor: pointer;"
                                                onclick="showMediaDetails({{ json_encode(['id' => $item->id, 'url' => $item->getUrl(), 'name' => $item->original_name, 'type' => $item->type, 'size' => $item->getFormattedSize(), 'mime' => $item->mime_type, 'dimensions' => $item->getDimensions(), 'alt' => $item->alt, 'caption' => $item->caption]) }})">
                                        @else
                                            <div class="card-img-top d-flex align-items-center justify-content-center bg-light"
                                                style="height: 120px; cursor: pointer;"
                                                onclick="showMediaDetails({{ json_encode(['id' => $item->id, 'url' => $item->getUrl(), 'name' => $item->original_name, 'type' => $item->type, 'size' => $item->getFormattedSize(), 'mime' => $item->mime_type, 'dimensions' => null, 'alt' => $item->alt, 'caption' => $item->caption]) }})">
                                                @switch($item->type)
                                                    @case('video')
                                                        <i class="bi bi-film display-4 text-muted"></i>
                                                        @break
                                                    @case('audio')
                                                        <i class="bi bi-music-note-beamed display-4 text-muted"></i>
                                                        @break
                                                    @case('document')
                                                        <i class="bi bi-file-earmark-pdf display-4 text-muted"></i>
                                                        @break
                                                    @default
                                                        <i class="bi bi-file-earmark display-4 text-muted"></i>
                                                @endswitch
                                            </div>
                                        @endif
                                    </div>
                                    <div class="card-body p-2">
                                        <small class="text-truncate d-block" title="{{ $item->original_name }}">{{ $item->original_name }}</small>
                                        <small class="text-muted">{{ $item->getHumanFileSize() }}</small>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-12 text-center py-5">
                                <i class="bi bi-folder2-open display-1 text-muted"></i>
                                <p class="text-muted mt-3">{{ __('No files found. Upload some files to get started.') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
                @if($media->hasPages())
                    <div class="card-footer">
                        {{ $media->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Create Folder Modal -->
<div class="modal fade" id="createFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Create Folder') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createFolderForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="folderName" class="form-label">{{ __('Folder Name') }}</label>
                        <input type="text" class="form-control" id="folderName" name="name" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Create') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Media Details Modal -->
<div class="modal fade" id="mediaDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mediaDetailsTitle">{{ __('Media Details') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="mediaDetailsContent">
                <div class="row g-3">
                    <div class="col-md-7 text-center" id="mediaPreviewArea"></div>
                    <div class="col-md-5">
                        <dl class="row small mb-0" id="mediaDetailsMeta"></dl>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-danger" id="modalDeleteBtn">
                    <i class="bi bi-trash me-1"></i>{{ __('Delete') }}
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Close') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const uploadUrl      = @json(route('admin.media.upload'));
const createFolderUrl = @json(route('admin.media.folders.store'));
const bulkDeleteUrl  = @json(route('admin.media.bulk-delete'));
const destroyBaseUrl = @json(rtrim(route('admin.media.destroy', ['medium' => '__ID__']), ''));
const csrfToken      = document.querySelector('meta[name="csrf-token"]').content;
const currentFolderId = @json(request('folder_id'));

// ── Upload ────────────────────────────────────────────────────────────────────
const uploadBtn = document.getElementById('uploadBtn');
const fileInput  = document.getElementById('fileInput');
const dropZone   = document.getElementById('dropZone');

uploadBtn.addEventListener('click', () => fileInput.click());
fileInput.addEventListener('change', (e) => uploadFiles(e.target.files));

document.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.style.display = 'block'; });
document.addEventListener('dragleave', (e) => { if (e.relatedTarget === null) dropZone.style.display = 'none'; });
dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.style.display = 'none'; uploadFiles(e.dataTransfer.files); });

async function uploadFiles(files) {
    for (const file of files) {
        const formData = new FormData();
        formData.append('file', file);
        if (currentFolderId) formData.append('folder_id', currentFolderId);

        try {
            const res = await fetch(uploadUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                credentials: 'include',
                body: formData,
            });
            if (!res.ok) { console.error('Upload failed', res.status, await res.text()); alert('Upload failed: ' + res.status); return; }
            const result = await res.json();
            if (!result.id) { alert(result.message || 'Upload failed'); }
        } catch (err) { console.error('Upload error:', err); alert('Upload failed'); }
    }
    location.reload();
}

// ── Create folder ─────────────────────────────────────────────────────────────
document.getElementById('createFolderForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = document.getElementById('folderName').value;
    try {
        const res = await fetch(createFolderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            credentials: 'include',
            body: JSON.stringify({ name, parent_id: currentFolderId }),
        });
        const result = await res.json();
        result.id ? location.reload() : alert(result.message || 'Failed to create folder');
    } catch (err) { console.error(err); alert('Failed to create folder'); }
});

// ── Selection & bulk delete ───────────────────────────────────────────────────
const bulkActions = document.getElementById('bulkActions');

document.querySelectorAll('.media-checkbox').forEach(cb => {
    cb.addEventListener('change', () => {
        const checked = document.querySelectorAll('.media-checkbox:checked');
        bulkActions.style.display = checked.length > 0 ? 'block' : 'none';
    });
});

document.getElementById('bulkDeleteBtn').addEventListener('click', async () => {
    const checked = document.querySelectorAll('.media-checkbox:checked');
    const ids = Array.from(checked).map(cb => cb.closest('.media-item').dataset.id);
    if (!confirm(`{{ __('Delete') }} ${ids.length} {{ __('files?') }}`)) return;
    await deleteByIds(ids);
});

async function deleteByIds(ids) {
    try {
        const res = await fetch(bulkDeleteUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            credentials: 'include',
            body: JSON.stringify({ ids }),
        });
        const result = await res.json();
        if (result.success) location.reload();
    } catch (err) { console.error(err); }
}

// ── Individual delete button (card) ──────────────────────────────────────────
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const id   = btn.dataset.id;
        const name = btn.dataset.name;
        if (confirm(`{{ __('Delete') }} "${name}"?`)) deleteByIds([id]);
    });
});

// ── Media details modal ───────────────────────────────────────────────────────
let currentDetailId = null;

function showMediaDetails(item) {
    currentDetailId = item.id;

    document.getElementById('mediaDetailsTitle').textContent = item.name;

    const preview = document.getElementById('mediaPreviewArea');
    if (item.type === 'image') {
        preview.innerHTML = `<img src="${item.url}" class="img-fluid rounded shadow-sm" style="max-height:350px;" alt="${item.name}">`;
    } else if (item.type === 'video') {
        preview.innerHTML = `<video src="${item.url}" controls class="w-100 rounded"></video>`;
    } else if (item.type === 'audio') {
        preview.innerHTML = `<audio src="${item.url}" controls class="w-100"></audio>`;
    } else {
        preview.innerHTML = `<a href="${item.url}" target="_blank" class="btn btn-outline-primary"><i class="bi bi-download me-2"></i>{{ __('Download') }}</a>`;
    }

    const rows = [
        ['{{ __('File') }}', item.name],
        ['{{ __('Type') }}', item.mime],
        ['{{ __('Size') }}', item.size],
    ];
    if (item.dimensions) rows.push(['{{ __('Dimensions') }}', item.dimensions]);
    if (item.alt)        rows.push(['Alt', item.alt]);
    if (item.caption)    rows.push(['{{ __('Caption') }}', item.caption]);
    rows.push(['URL', `<a href="${item.url}" target="_blank" class="text-break small">${item.url}</a>`]);

    document.getElementById('mediaDetailsMeta').innerHTML = rows.map(([k, v]) =>
        `<dt class="col-5 text-muted">${k}</dt><dd class="col-7">${v}</dd>`
    ).join('');

    new bootstrap.Modal(document.getElementById('mediaDetailsModal')).show();
}

document.getElementById('modalDeleteBtn').addEventListener('click', () => {
    if (!currentDetailId) return;
    if (!confirm('{{ __('Delete this file permanently?') }}')) return;
    bootstrap.Modal.getInstance(document.getElementById('mediaDetailsModal'))?.hide();
    deleteByIds([currentDetailId]);
});
</script>
@endpush
