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
        $trans = array(
            'OPR' => 'Opera',
            'UBrowser' => 'UC Browser',
            'YaBrowser' => 'Yandex',
            'Edg' => 'Edge',
            'Edge' => 'Microsoft Edge',
            'MSIE' => 'Internet Explorer',
            // yes, I know Trident isn't IE but it's the string that identifies IE11
            'Trident' => 'Internet Explorer 11.0',
            'PostmanRuntime' => 'Postman'
        );

        $browsers = [
            'Brave',
            'Chrome',
            'coc_coc_browser',
            'Edg',
            'Edge',
            'Firefox',
            'IEMobile',
            'MSIEMobile',
            'MSIE',
            'Opera',
            'Opera Mini',
            'OPR',
            'PaleMoon',
            'PostmanRuntime',
            'Safari',
            'Trident',
            'Trident/[.0-9]+',
            'UCBrowser',
            'Unknown',
            'UBrowser',
            'Vivaldi',
            'Virgilio',
            'YaBrowser'
        ];

        foreach ($browsers as $browser) {
            if (false !== $pos = strpos($userAgent, $browser)){
                break;
            }
        }

        $sub1 = substr($userAgent, $pos);   // from browser name to end
        $sub2 = explode(' ', $sub1);     // just browser name and version
        $sub3 = explode('/', $sub2[0]);  // split browser name and version
        $sub4 = explode('.', $sub3[1]);  // get sub, sub sub versions etc

        $browserName = $browser;

        if (isset($trans[$browser])) {
            $browserName = $trans[$browser];
        }

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
