<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\LoginResource;
use App\Models\DeviceInfo;
use App\Models\User;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{

    public function login(LoginRequest $request): JsonResource
    {
        $user = User::whereEmail($request->input('data.email'))->first();

        if (!$user || !Hash::check($request->input('data.password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')]
            ]);
        }

        $limitAuthDevices = intval(config('auth.limit_auth_devices'));

        if($limitAuthDevices > 0) {
            $validTokens = $user->tokens()
                ->where('revoked', 0)
                ->where('expires_at', '>', Carbon::now())
                ->get();

            $tokenCount = $validTokens->count();

            if($tokenCount >= $limitAuthDevices) {
                throw ValidationException::withMessages([
                    'email' => ['limit_auth_devices']
                ]);
            }
        }

        $tokenResult = $user->createToken('Login');
        $token = $tokenResult->token;
        $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();

        DeviceInfo::create([
            'login_at' => now(),
            'browser' => $this->getBrowser($request->header('User-Agent')),
            'os' => $this->getOS($request->server('HTTP_USER_AGENT')),
            'ip' => $request->ip(),
            'country' => $this->getCountry($request->ip()),
            'user_id' => $user->id,
            'session_token' => $token->id,
        ]);

        $dataResponse = (object)[
            'user' => $user,
            'token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_at' => Carbon::parse($token->expires_at)->toDateTimeString(),
        ];

        return LoginResource::make($dataResponse);
    }

    private function getOS($userAgent): string
    {
        $operativeSystems = [
            'Windows NT 10.0' => 'Windows 10',
            'Windows NT 6.2' => 'Windows 8',
            'Windows NT 6.1' => 'Windows 7',
            'Windows NT 6.0' => 'Windows Vista',
            'Windows NT 5.1' => 'Windows XP',
            'Windows NT 5.0' => 'Windows 2000',
            'Mac' => 'Mac',
            'Linux' => 'Linux',
            'Debian' => 'Debian',
            'Ubuntu' => 'Ubuntu',
            'Chrome OS' => 'Chrome OS',
            'PostmanRuntime' => 'Postman',
            'Unknown' => 'Unknown OS'
        ];

        foreach ($operativeSystems as $os => $osName) {
            if (false !== $pos = strpos($userAgent, $os)){
                break;
            }
        }

        return $osName;
    }

    private function getBrowser($userAgent): string
    {

        $browsers = [
            'Brave' => 'Brave',
            'Chrome' => 'Chrome',
            'coc_coc_browser' => 'Coc Coc',
            'Edg' => 'Edge',
            'Edge' => 'Edge',
            'Firefox' => 'Firefox',
            'IEMobile' => 'Internet Explorer Mobile',
            'MSIEMobile' => 'Internet Explorer Mobile',
            'MSIE' => 'Internet Explorer',
            'Opera' => 'Opera',
            'Opera Mini' => 'Opera Mini',
            'OPR' => 'Opera',
            'PaleMoon' => 'Pale Moon',
            'PostmanRuntime' => 'Postman',
            'Safari' => 'Safari',
            'Trident' => 'Internet Explorer 11.0',
            'Trident/[.0-9]+' => 'Internet Explorer 11.0',
            'UCBrowser' => 'UC Browser',
            'Unknown' => 'Unknown Browser',
            'UBrowser' => 'UC Browser',
            'Vivaldi' => 'Vivaldi',
            'Virgilio' => 'Virgilio',
            'YaBrowser' => 'Yandex',
        ];

        foreach ($browsers as $browser => $browserName) {
            if (false !== $pos = strpos($userAgent, $browser)){
                break;
            }
        }

        $sub1 = substr($userAgent, $pos);   // from browser name to end
        $sub2 = explode(' ', $sub1);     // just browser name and version
        $sub3 = explode('/', $sub2[0]);  // split browser name and version
        $sub4 = explode('.', $sub3[1]);  // get sub, sub sub versions etc

        return $browserName;
    }

    private function getCountry($ip): string
    {
        $json = file_get_contents("http://ip-api.com/json/{$ip}?fields=country,city");
        $data = json_decode($json, true);

        $country = 'Unknown';

        if(array_key_exists('city', $data) && array_key_exists('country', $data)) {
            $country = $data['city'] . ', ' . $data['country'];
        }

        return $country;
    }
}
