<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

class VerifyEmailController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api')->only('resend');
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $user = User::where('id', $request->route('id'))->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['User not found']
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email verified']);
    }

    /**
     * @throws ValidationException
     */
    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => ['Email already verified']
            ]);
        }

        $user = $request->user();

        $name = $user->first_name;

        $url = URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification())
            ],
            false
        );

		$url = config('app.frontend_url') . str_replace('/api/v1', '', $url);
    
        Mail::send('mail.email_verify', ['url' => $url, 'name' => $name], function ($message) use ($user) {
            $message->to($user->email);
            $message->subject('Verify your email address');
        });

        return response()->json(['message' => 'Email verification link sent']);
    }
}
