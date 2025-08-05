<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\User;

class SocialAuthController extends Controller
{
    public function verifySocialToken(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|in:google,facebook',
            'access_token' => 'required|string',
        ]);

        $provider = $request->provider;
        $accessToken = $request->access_token;

        try {
            // Verificar token con el proveedor
            $userData = $this->verifyTokenWithProvider($provider, $accessToken);

            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'email_verified_at' => now(),
                    'status' => true,
                ]
            );

            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'access_token' => $token,
                'user' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }
    }

    /**
     * @throws \Exception
     */
    private function verifyTokenWithProvider($provider, $token): array
    {
        switch ($provider) {
            case 'google':
                return $this->verifyGoogleToken($token);
            case 'facebook':
                return $this->verifyFacebookToken($token);
            default:
                throw new \Exception('Unsupported provider');
        }
    }

    /**
     * @throws \Exception
     */
    private function verifyGoogleToken($token): array
    {
        $response = Http::get('https://www.googleapis.com/oauth2/v2/userinfo', [
            'access_token' => $token
        ]);

        if (!$response->successful()) {
            throw new \Exception('Invalid Google token');
        }

        $data = $response->json();
        return [
            'email' => $data['email'],
            'name' => $data['name'],
            'provider_id' => $data['id']
        ];
    }

    /**
     * @throws \Exception
     */
    private function verifyFacebookToken($token): array
    {
        $response = Http::get('https://graph.facebook.com/me', [
            'access_token' => $token,
            'fields' => 'id,name,email'
        ]);
        if (!$response->successful()) {
            throw new \Exception('Invalid Facebook token');
        }

        $data = $response->json();
        return [
            'email' => $data['email'],
            'name' => $data['name'],
            'provider_id' => $data['id']
        ];

    }
}
