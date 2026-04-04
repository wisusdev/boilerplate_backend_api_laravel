@foreach($items as $item)
    <div class="menu-item" data-id="{{ $item['id'] }}">
        <div class="menu-item-header">
            <div>
                <i class="bi bi-grip-vertical me-2 text-muted"></i>
                <span class="menu-item-title">{{ $item['title'] }}</span>
                <span class="menu-item-type ms-2">{{ $item['type'] ?? 'custom' }}</span>
            </div>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-secondary" onclick="editItem('{{ $item['id'] }}')">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-outline-danger" onclick="deleteItem('{{ $item['id'] }}')">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        <div class="menu-item-children">
            @if(!empty($item['children']))
                @include('menu::admin.partials.menu-items', ['items' => $item['children'], 'level' => $level + 1])
            @endif
        </div>
    </div>
@endforeach
