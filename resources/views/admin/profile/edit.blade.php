@extends('layouts.admin')

@section('title', __('Profile'))

@section('content')
<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">{{ __('Profile Settings') }}</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <!-- Profile Information -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Profile Information') }}</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.profile.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="name" class="form-label">{{ __('Name') }}</label>
                            <input type="text" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   id="name" 
                                   name="name" 
                                   value="{{ old('name', $user->name) }}" 
                                   required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">{{ __('Email') }}</label>
                            <input type="email" 
                                   class="form-control @error('email') is-invalid @enderror" 
                                   id="email" 
                                   name="email" 
                                   value="{{ old('email', $user->email) }}" 
                                   required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if(!$user->email_verified_at)
                                <div class="form-text text-warning">
                                    <i class="bi bi-exclamation-triangle"></i> {{ __('Your email address is unverified.') }}
                                </div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label">{{ __('Registered') }}</label>
                            <input type="text" 
                                   class="form-control-plaintext" 
                                   readonly 
                                   value="{{ $user->created_at->format('M d, Y') }}">
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-1"></i> {{ __('Save Changes') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Update Password -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ __('Update Password') }}</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.profile.password') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="current_password" class="form-label">{{ __('Current Password') }}</label>
                            <input type="password" 
                                   class="form-control @error('current_password') is-invalid @enderror" 
                                   id="current_password" 
                                   name="current_password" 
                                   required>
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('New Password') }}</label>
                            <input type="password" 
                                   class="form-control @error('password') is-invalid @enderror" 
                                   id="password" 
                                   name="password" 
                                   required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">{{ __('Confirm Password') }}</label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password_confirmation" 
                                   name="password_confirmation" 
                                   required>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-shield-lock me-1"></i> {{ __('Update Password') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Account -->
        <div class="col-12">
            <div class="card border-danger">
                <div class="card-header bg-danger bg-opacity-10">
                    <h5 class="card-title mb-0 text-danger">{{ __('Delete Account') }}</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
                    </p>

                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                        <i class="bi bi-trash me-1"></i> {{ __('Delete Account') }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Account Confirmation Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('admin.profile.destroy') }}">
                @csrf
                @method('DELETE')

                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAccountModalLabel">{{ __('Delete Account') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-danger">
                        <strong>{{ __('Are you sure you want to delete your account?') }}</strong>
                    </p>
                    <p class="text-muted mb-3">
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                    </p>

                    <div class="mb-3">
                        <label for="delete_password" class="form-label">{{ __('Password') }}</label>
                        <input type="password" 
                               class="form-control @error('password') is-invalid @enderror" 
                               id="delete_password" 
                               name="password" 
                               placeholder="{{ __('Password') }}"
                               required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('Delete Account') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
