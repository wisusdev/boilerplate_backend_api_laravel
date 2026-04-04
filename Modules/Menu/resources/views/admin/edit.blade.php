@extends('layouts.admin')

@section('title', __('Edit Menu') . ': ' . $menu->name)

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.css">
<style>
    .menu-item {
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        margin-bottom: 0.5rem;
    }
    .menu-item-header {
        padding: 0.75rem 1rem;
        cursor: move;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .menu-item-header:hover {
        background-color: #f8f9fa;
    }
    .menu-item-body {
        padding: 1rem;
        border-top: 1px solid #dee2e6;
        background-color: #f8f9fa;
        display: none;
    }
    .menu-item-body.show {
        display: block;
    }
    .menu-item-children {
        margin-left: 2rem;
        margin-top: 0.5rem;
    }
    .sortable-ghost {
        opacity: 0.4;
        background-color: #e3f2fd;
    }
    .sortable-chosen {
        background-color: #fff;
    }
    .menu-item-type {
        font-size: 0.75rem;
        color: #6c757d;
    }
    .add-item-panel {
        max-height: 300px;
        overflow-y: auto;
    }
</style>
@endpush

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="{{ route('admin.menus.index') }}">{{ __('Menus') }}</a></li>
                    <li class="breadcrumb-item active">{{ $menu->name }}</li>
                </ol>
            </nav>
            <h1 class="h3 mt-2">{{ __('Edit Menu') }}: {{ $menu->name }}</h1>
        </div>
        <button type="button" class="btn btn-success" id="save-menu" disabled>
            <i class="bi bi-check-lg me-1"></i> {{ __('Save Changes') }}
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row">
        <!-- Add Items Panel -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Add Menu Items') }}</h5>
                </div>
                <div class="card-body">
                    <div class="accordion" id="addItemsAccordion">
                        <!-- Custom Link -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#customLinkPanel">
                                    {{ __('Custom Link') }}
                                </button>
                            </h2>
                            <div id="customLinkPanel" class="accordion-collapse collapse show" data-bs-parent="#addItemsAccordion">
                                <div class="accordion-body">
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('URL') }}</label>
                                        <input type="text" class="form-control form-control-sm" id="custom-url" placeholder="https://">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">{{ __('Link Text') }}</label>
                                        <input type="text" class="form-control form-control-sm" id="custom-title" placeholder="{{ __('Menu Item') }}">
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="addCustomLink()">
                                        {{ __('Add to Menu') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Pages -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#pagesPanel">
                                    {{ __('Pages') }}
                                </button>
                            </h2>
                            <div id="pagesPanel" class="accordion-collapse collapse" data-bs-parent="#addItemsAccordion">
                                <div class="accordion-body add-item-panel" id="pages-list">
                                    <div class="text-center py-3">
                                        <div class="spinner-border spinner-border-sm" role="status"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Posts -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#postsPanel">
                                    {{ __('Posts') }}
                                </button>
                            </h2>
                            <div id="postsPanel" class="accordion-collapse collapse" data-bs-parent="#addItemsAccordion">
                                <div class="accordion-body add-item-panel" id="posts-list">
                                    <div class="text-center py-3">
                                        <div class="spinner-border spinner-border-sm" role="status"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Categories -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#categoriesPanel">
                                    {{ __('Categories') }}
                                </button>
                            </h2>
                            <div id="categoriesPanel" class="accordion-collapse collapse" data-bs-parent="#addItemsAccordion">
                                <div class="accordion-body add-item-panel" id="categories-list">
                                    <div class="text-center py-3">
                                        <div class="spinner-border spinner-border-sm" role="status"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Menu Settings -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Menu Settings') }}</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.menus.update', $menu) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('Menu Name') }}</label>
                            <input type="text" class="form-control" id="name" name="name" value="{{ $menu->name }}" required>
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label">{{ __('Display Location') }}</label>
                            <select class="form-select" id="location" name="location">
                                <option value="">{{ __('— Select location —') }}</option>
                                @foreach($locations as $key => $label)
                                    <option value="{{ $key }}" {{ $menu->location === $key ? 'selected' : '' }}>
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            {{ __('Update Settings') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Menu Structure -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">{{ __('Menu Structure') }}</h5>
                    <small class="text-muted">{{ __('Drag items to reorder') }}</small>
                </div>
                <div class="card-body">
                    @if(count($menuTree) > 0)
                        <div id="menu-items" class="menu-items-container">
                            @include('menu::admin.partials.menu-items', ['items' => $menuTree, 'level' => 0])
                        </div>
                    @else
                        <div class="text-center py-5" id="empty-menu-message">
                            <i class="bi bi-list-ul display-4 text-muted"></i>
                            <p class="mt-3 text-muted">{{ __('Add items from the left panel to build your menu.') }}</p>
                        </div>
                        <div id="menu-items" class="menu-items-container" style="min-height: 100px;"></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Edit Menu Item') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="edit-item-id">
                <div class="mb-3">
                    <label class="form-label">{{ __('Navigation Label') }}</label>
                    <input type="text" class="form-control" id="edit-item-title">
                </div>
                <div class="mb-3" id="edit-url-group">
                    <label class="form-label">{{ __('URL') }}</label>
                    <input type="text" class="form-control" id="edit-item-url">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('CSS Classes') }}</label>
                    <input type="text" class="form-control" id="edit-item-css" placeholder="{{ __('Optional') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('Icon') }}</label>
                    <input type="text" class="form-control" id="edit-item-icon" placeholder="bi-house">
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="edit-item-newtab">
                    <label class="form-check-label" for="edit-item-newtab">{{ __('Open in new tab') }}</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary" onclick="saveItemEdit()">{{ __('Save') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
const menuId = '{{ $menu->id }}';
const csrfToken = '{{ csrf_token() }}';
let hasChanges = false;
let menuItems = @json($menuTree);

// Initialize sortable
document.addEventListener('DOMContentLoaded', function() {
    initSortable();
    loadLinkableItems();
});

function initSortable() {
    const container = document.getElementById('menu-items');
    if (container) {
        new Sortable(container, {
            animation: 150,
            handle: '.menu-item-header',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: function() {
                markChanged();
            }
        });
    }

    // Initialize sortable for child containers
    document.querySelectorAll('.menu-item-children').forEach(function(el) {
        new Sortable(el, {
            group: 'menu-items',
            animation: 150,
            handle: '.menu-item-header',
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                markChanged();
            }
        });
    });
}

function markChanged() {
    hasChanges = true;
    document.getElementById('save-menu').disabled = false;
}

const linkableBaseUrl = '{{ route('admin.menus.linkable', ['type' => '__TYPE__']) }}';

const pluralMap = { page: 'pages', post: 'posts', category: 'categories' };

function loadLinkableItems() {
    ['page', 'post', 'category'].forEach(function(type) {
        fetch(linkableBaseUrl.replace('__TYPE__', type))
            .then(response => response.json())
            .then(data => {
                const container = document.getElementById(pluralMap[type] + '-list');
                if (!container) return;
                if (data.data.length === 0) {
                    container.innerHTML = '<p class="text-muted small mb-0">{{ __("No items available.") }}</p>';
                    return;
                }

                let html = '';
                data.data.forEach(function(item) {
                    html += `
                        <div class="form-check mb-2">
                            <input class="form-check-input linkable-item" type="checkbox" 
                                   value="${item.id}" 
                                   data-label="${item.label}" 
                                   data-type="${type}"
                                   id="${type}-${item.id}">
                            <label class="form-check-label" for="${type}-${item.id}">${item.label}</label>
                        </div>
                    `;
                });
                html += `<button type="button" class="btn btn-primary btn-sm mt-2" onclick="addSelectedItems('${type}')">{{ __('Add Selected') }}</button>`;
                container.innerHTML = html;
            });
    });
}

function addCustomLink() {
    const url = document.getElementById('custom-url').value;
    const title = document.getElementById('custom-title').value;

    if (!url || !title) {
        alert('{{ __("Please enter both URL and Link Text.") }}');
        return;
    }

    addMenuItem({
        title: title,
        type: 'custom',
        url: url
    });

    document.getElementById('custom-url').value = '';
    document.getElementById('custom-title').value = '';
}

function addSelectedItems(type) {
    const checkboxes = document.querySelectorAll(`#${pluralMap[type]}-list .linkable-item:checked`);
    
    checkboxes.forEach(function(checkbox) {
        addMenuItem({
            title: checkbox.dataset.label,
            type: type,
            target_type: getModelClass(type),
            target_id: checkbox.value
        });
        checkbox.checked = false;
    });
}

function getModelClass(type) {
    const models = {
        'page': 'Modules\\Pages\\Models\\Page',
        'post': 'Modules\\Blog\\Models\\Post',
        'category': 'Modules\\Blog\\Models\\Category'
    };
    return models[type] || '';
}

function addMenuItem(data) {
    fetch(`{{ route('admin.menus.items.store', $menu) }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Add item to DOM
            appendMenuItemToDOM(result.data);
            hideEmptyMessage();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('{{ __("Failed to add menu item.") }}');
    });
}

function appendMenuItemToDOM(item) {
    const container = document.getElementById('menu-items');
    const itemHtml = createMenuItemHtml(item);
    container.insertAdjacentHTML('beforeend', itemHtml);
    initSortable();
}

function createMenuItemHtml(item) {
    return `
        <div class="menu-item" data-id="${item.id}">
            <div class="menu-item-header">
                <div>
                    <i class="bi bi-grip-vertical me-2 text-muted"></i>
                    <span class="menu-item-title">${item.title}</span>
                    <span class="menu-item-type ms-2">${item.type}</span>
                </div>
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-outline-secondary" onclick="editItem('${item.id}')">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="deleteItem('${item.id}')">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <div class="menu-item-children"></div>
        </div>
    `;
}

function hideEmptyMessage() {
    const msg = document.getElementById('empty-menu-message');
    if (msg) msg.style.display = 'none';
}

function editItem(itemId) {
    const itemEl = document.querySelector(`[data-id="${itemId}"]`);
    const title = itemEl.querySelector('.menu-item-title').textContent;

    document.getElementById('edit-item-id').value = itemId;
    document.getElementById('edit-item-title').value = title;
    
    const modal = new bootstrap.Modal(document.getElementById('editItemModal'));
    modal.show();
}

function saveItemEdit() {
    const itemId = document.getElementById('edit-item-id').value;
    const data = {
        title: document.getElementById('edit-item-title').value,
        url: document.getElementById('edit-item-url').value,
        css_class: document.getElementById('edit-item-css').value,
        icon: document.getElementById('edit-item-icon').value,
        open_in_new_tab: document.getElementById('edit-item-newtab').checked
    };

    fetch(`/admin/menu-items/${itemId}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Update DOM
            const itemEl = document.querySelector(`[data-id="${itemId}"]`);
            itemEl.querySelector('.menu-item-title').textContent = data.title;
            
            bootstrap.Modal.getInstance(document.getElementById('editItemModal')).hide();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('{{ __("Failed to update menu item.") }}');
    });
}

function deleteItem(itemId) {
    if (!confirm('{{ __("Are you sure you want to delete this menu item?") }}')) {
        return;
    }

    fetch(`/admin/menu-items/${itemId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            document.querySelector(`[data-id="${itemId}"]`).remove();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('{{ __("Failed to delete menu item.") }}');
    });
}

// Save menu order
document.getElementById('save-menu').addEventListener('click', function() {
    const items = [];
    document.querySelectorAll('#menu-items > .menu-item').forEach(function(el, index) {
        collectItems(el, null, index, items);
    });

    fetch(`{{ route('admin.menus.reorder', $menu) }}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ items: items })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            hasChanges = false;
            document.getElementById('save-menu').disabled = true;
            alert('{{ __("Menu saved successfully!") }}');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('{{ __("Failed to save menu.") }}');
    });
});

function collectItems(el, parentId, order, items) {
    items.push({
        id: el.dataset.id,
        parent_id: parentId,
        order: order
    });

    const children = el.querySelectorAll(':scope > .menu-item-children > .menu-item');
    children.forEach(function(child, index) {
        collectItems(child, el.dataset.id, index, items);
    });
}
</script>
@endpush
