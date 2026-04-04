@php
    use Modules\Menu\Models\Menu;
    $primaryMenu = Menu::findByLocation('primary');
@endphp
<header class="site-header">
    <nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="{{ url('/') }}">{{ config('app.name', 'CMS') }}</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto">
                    @if($primaryMenu && $primaryMenu->items->isNotEmpty())
                        @foreach($primaryMenu->getTree() as $item)
                            @if($item['children'])
                                <li class="nav-item dropdown">
                                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                        {{ $item['title'] }}
                                    </a>
                                    <ul class="dropdown-menu">
                                        @foreach($item['children'] as $child)
                                            <li>
                                                <a class="dropdown-item" href="{{ $child['url'] }}" @if($child['target']) target="{{ $child['target'] }}" @endif>
                                                    {{ $child['title'] }}
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </li>
                            @else
                                <li class="nav-item">
                                    <a class="nav-link" href="{{ $item['url'] }}" @if($item['target']) target="{{ $item['target'] }}" @endif>
                                        {{ $item['title'] }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    @endif
                </ul>
            </div>
        </div>
    </nav>
</header>