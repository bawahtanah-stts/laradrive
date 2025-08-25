<?php

namespace Arcphysx\Laradrive\Modules\Singleton;

use Arcphysx\Laradrive\Laradrive;
use Arcphysx\Laradrive\Modules\Contract\HttpClientModuleContract;
use Google\Client;
use Google_Service_Drive;
use Illuminate\Support\Facades\File;

class GoogleClient implements HttpClientModuleContract
{
    private static $INSTANCE = null;
    private static $GOOGLE_CLIENT = null;

    private function __construct()
    {
        $this->initGoogleClient();
        $this->validateAuthToken();
    }

    public static function _get()
    {
        if (self::$INSTANCE == null) {
            self::$INSTANCE = new GoogleClient();
        }
        return self::$INSTANCE;
    }

    private function initGoogleClient()
    {
        self::$GOOGLE_CLIENT = new Client();
        self::$GOOGLE_CLIENT->setApplicationName(env('APP_NAME', 'your-app-name'));

        // https://developers.google.com/drive/api/v3/about-auth
        self::$GOOGLE_CLIENT->setScopes(Google_Service_Drive::DRIVE);

        self::$GOOGLE_CLIENT->setAuthConfig(Laradrive::credentialPath());
        self::$GOOGLE_CLIENT->setAccessType('offline');
        self::$GOOGLE_CLIENT->setPrompt('select_account consent');

        $token = $this->getAuthToken();

        if ($token) {
            // set token ke client
            self::$GOOGLE_CLIENT->setAccessToken((array) $token);

            // kalau ada refresh_token, inject ke client
            if (isset($token->refresh_token)) {
                self::$GOOGLE_CLIENT->refreshToken($token->refresh_token);
            }

            // update file token
            $this->writeAuthToken();
        }
    }

    public function validateAuthToken()
    {
        if (self::$GOOGLE_CLIENT->isAccessTokenExpired()) {
            $refreshToken = self::$GOOGLE_CLIENT->getRefreshToken();

            if ($refreshToken) {
                $newToken = self::$GOOGLE_CLIENT->fetchAccessTokenWithRefreshToken($refreshToken);

                // pastikan refresh_token tidak hilang
                if (!isset($newToken['refresh_token'])) {
                    $newToken['refresh_token'] = $refreshToken;
                }

                $this->writeAuthToken((object) $newToken);
            } else {
                throw new \Exception("No refresh token available. Please re-authorize the app.");
            }
        }
    }

    public function getCredentialInfo()
    {
        $json = File::get(Laradrive::credentialPath());
        return json_decode($json, false); // object
    }

    public function getAuthToken()
    {
        if (!File::exists(Laradrive::authTokenPath())) {
            return null;
        }
        $json = File::get(Laradrive::authTokenPath());
        return json_decode($json, false); // object
    }

    public function writeAuthToken($token = null)
    {
        if ($token === null) {
            $token = (object) self::$GOOGLE_CLIENT->getAccessToken();

            // pastikan refresh_token ikut disimpan
            if ($refreshToken = self::$GOOGLE_CLIENT->getRefreshToken()) {
                $token->refresh_token = $refreshToken;
            }
        }

        File::replace(
            Laradrive::authTokenPath(),
            json_encode($token, JSON_PRETTY_PRINT)
        );
    }
}
