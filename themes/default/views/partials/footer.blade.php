@php
    use Modules\Menu\Models\Menu;
    $footerMenu = Menu::findByLocation('footer');
@endphp
<footer class="site-footer mt-auto">
    <div class="container py-4">
        <div class="row g-4">
            <div class="col-md-4">
                <h6 class="text-white fw-semibold mb-2">{{ config('app.name', 'CMS') }}</h6>
                <p class="small mb-0">A modern content management system built with Laravel.</p>
            </div>
            <div class="col-md-4">
                <h6 class="text-white fw-semibold mb-2">Quick Links</h6>
                @if($footerMenu && $footerMenu->items->isNotEmpty())
                    @foreach($footerMenu->getTree() as $item)
                        <a href="{{ $item['url'] }}" class="d-block text-secondary small text-decoration-none" @if($item['target']) target="{{ $item['target'] }}" @endif>
                            {{ $item['title'] }}
                        </a>
                    @endforeach
                @else
                    <a href="{{ url('/') }}" class="d-block text-secondary small text-decoration-none">Home</a>
                    <a href="{{ url('/admin') }}" class="d-block text-secondary small text-decoration-none">Admin</a>
                @endif
            </div>
            <div class="col-md-4">
                <h6 class="text-white fw-semibold mb-2">Contact</h6>
                <p class="small mb-0">info@example.com</p>
            </div>
        </div>
        <hr class="border-secondary mt-4">
        <p class="text-center small mb-0">&copy; {{ date('Y') }} {{ config('app.name', 'CMS') }}. All rights reserved.</p>
    </div>
</footer>
