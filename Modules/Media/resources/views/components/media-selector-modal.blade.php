<!-- Media Selector Modal -->
<div class="modal fade" id="{{ $modalId ?? 'mediaSelectorModal' }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-images"></i> {{ __('Select Media') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="height: 500px; overflow-y: auto;">
                <div class="row g-3" id="{{ $modalId ?? 'mediaSelectorModal' }}_mediaGrid">
                    <div class="col-12 text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">{{ __('Loading...') }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" id="{{ $modalId ?? 'mediaSelectorModal' }}_selectBtn" disabled>
                    {{ __('Insert Selected') }}
                </button>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    const modalId = '{{ $modalId ?? 'mediaSelectorModal' }}';
    const modal = document.getElementById(modalId);
    const mediaGrid = document.getElementById(modalId + '_mediaGrid');
    const selectBtn = document.getElementById(modalId + '_selectBtn');
    let selectedMedia = null;
    let onSelectCallback = null;

    // Event: modal opened
    modal.addEventListener('show.bs.modal', function() {
        loadMedia();
    });

    // Load media from API
    function loadMedia() {
        fetch('{{ route('admin.media.index') }}', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            renderMedia(data.media || data);
        })
        .catch(error => {
            console.error('Error loading media:', error);
            mediaGrid.innerHTML = '<div class="col-12 text-center py-5 text-danger">{{ __('Error loading media') }}</div>';
        });
    }

    // Render media grid
    function renderMedia(media) {
        if (!media || media.length === 0) {
            mediaGrid.innerHTML = '<div class="col-12 text-center py-5 text-muted">{{ __('No media found') }}</div>';
            return;
        }

        mediaGrid.innerHTML = media.map(item => {
            const url = item.url || item.path || '';
            const isImage = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(url);
            
            return `
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card media-item" data-url="${url}" data-id="${item.id || ''}" style="cursor: pointer;">
                        <div class="card-body p-2 text-center" style="height: 150px; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                            ${isImage 
                                ? `<img src="${url}" class="img-fluid" style="max-height: 140px; object-fit: cover;">`
                                : `<i class="bi bi-file-earmark fs-1 text-secondary"></i>`
                            }
                        </div>
                        <div class="card-footer p-2">
                            <small class="text-truncate d-block">${item.filename || item.original_name || 'Untitled'}</small>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        // Attach click handlers
        document.querySelectorAll('.media-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.media-item').forEach(el => el.classList.remove('border-primary', 'border-3'));
                this.classList.add('border-primary', 'border-3');
                selectedMedia = {
                    url: this.dataset.url,
                    id: this.dataset.id
                };
                selectBtn.disabled = false;
            });
        });
    }

    // Select button handler
    selectBtn.addEventListener('click', function() {
        if (selectedMedia && onSelectCallback) {
            onSelectCallback(selectedMedia);
        }
        bootstrap.Modal.getInstance(modal).hide();
        selectedMedia = null;
        selectBtn.disabled = true;
    });

    // Expose public API
    window['MediaSelector_' + modalId] = {
        show: function(callback) {
            onSelectCallback = callback;
            new bootstrap.Modal(modal).show();
        }
    };
})();
</script>
@endpush
