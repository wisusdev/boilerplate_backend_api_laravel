@extends('layouts.admin')

@section('title', __('Settings'))

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">{{ __('Settings') }}</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf @method('PUT')

        @php $app = json_decode($settings->get('app')?->value, true) ?? []; @endphp
        <div class="card mb-4">
            <div class="card-header fw-semibold">{{ __('General') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Site Name') }}</label>
                        <input type="text" name="app[name]" class="form-control" value="{{ old('app.name', $app['name'] ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Email') }}</label>
                        <input type="email" name="app[email]" class="form-control" value="{{ old('app.email', $app['email'] ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('API URL') }}</label>
                        <input type="url" name="app[url_api]" class="form-control" value="{{ old('app.url_api', $app['url_api'] ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Frontend URL') }}</label>
                        <input type="url" name="app[url_frontend]" class="form-control" value="{{ old('app.url_frontend', $app['url_frontend'] ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Phone') }}</label>
                        <input type="text" name="app[phone]" class="form-control" value="{{ old('app.phone', $app['phone'] ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('Timezone') }}</label>
                        <input type="text" name="app[timezone]" class="form-control" value="{{ old('app.timezone', $app['timezone'] ?? '') }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('Description') }}</label>
                        <textarea name="app[description]" class="form-control" rows="2">{{ old('app.description', $app['description'] ?? '') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">{{ __('Address') }}</label>
                        <input type="text" name="app[address]" class="form-control" value="{{ old('app.address', $app['address'] ?? '') }}">
                    </div>
                </div>
            </div>
        </div>

        @php $mail = json_decode($settings->get('mail')?->value, true) ?? []; @endphp
        <div class="card mb-4">
            <div class="card-header fw-semibold">{{ __('Mail Configuration') }}</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Driver') }}</label>
                        <input type="text" name="mail[driver]" class="form-control" value="{{ old('mail.driver', $mail['driver'] ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Host') }}</label>
                        <input type="text" name="mail[host]" class="form-control" value="{{ old('mail.host', $mail['host'] ?? '') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">{{ __('Port') }}</label>
                        <input type="text" name="mail[port]" class="form-control" value="{{ old('mail.port', $mail['port'] ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('From Address') }}</label>
                        <input type="email" name="mail[from_address]" class="form-control" value="{{ old('mail.from_address', $mail['from_address'] ?? '') }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">{{ __('From Name') }}</label>
                        <input type="text" name="mail[from_name]" class="form-control" value="{{ old('mail.from_name', $mail['from_name'] ?? '') }}">
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary px-4">
            <i class="bi bi-check-lg me-1"></i>{{ __('Save Settings') }}
        </button>
    </form>
</div>
@endsection
