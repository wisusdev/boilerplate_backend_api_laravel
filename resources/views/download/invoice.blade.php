<p>{{ __('mail.greeting', ['name' => $name]) }}</p>
<p>{{ __('mail.pending_payment_intro') }}</p>
<ul>
    @foreach ($items as $item)
        <li><strong>{{ $item['description'] }}</strong>: {{ $item['total_price'] }}</li>
    @endforeach
</ul>
<p>{{ __('mail.pending_payment_total', ['total' => $total]) }}</p>
<p>{{ __('mail.pending_payment_cta') }}</p>
<a href="{{ $url }}">{{ __('mail.pending_payment_link') }}</a>
<p>{{ __('mail.regards') }}<br>{{ config('app.name') }}</p>