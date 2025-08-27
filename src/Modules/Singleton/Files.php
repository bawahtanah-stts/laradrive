<?php

namespace Arcphysx\Laradrive\Modules\Singleton;

use Arcphysx\Laradrive\Laradrive;
use Arcphysx\Laradrive\Modules\Contract\HttpClientModuleContract;
use Arcphysx\Laradrive\Modules\Wrapper\ResponseWrapper;
use GuzzleHttp\Psr7\Utils; // Menggunakan GuzzleHttp\Psr7\Utils

class Files implements HttpClientModuleContract
{
    private static ?Files $INSTANCE = null;

    private function __construct()
    {
        //
    }

    public static function _get(): Files
    {
        if (self::$INSTANCE === null) {
            self::$INSTANCE = new self();
        }
        return self::$INSTANCE;
    }

    public function list($folderId = null, $query = null)
    {
        $queries = [];

        if ($folderId) {
            $queries[] = "'$folderId' in parents";
        }

        if ($query) {
            $queries[] = $query;
        }

        $q = implode(' and ', $queries);

        $response = Laradrive::httpClient()->get("files", [
            'query' => [
                'q' => $q,
                'fields' => 'nextPageToken, files(id, name, mimeType, parents)'
            ]
        ]);

        return ResponseWrapper::parse($response);
    }

    public function get($fileId)
    {
        $response = Laradrive::httpClient()->get("files/$fileId", [
            'query' => [
                'fields' => '*'
            ]
        ]);

        return ResponseWrapper::parse($response);
    }

    public function delete($fileId)
    {
        $response = Laradrive::httpClient()->delete("files/$fileId");

        return ResponseWrapper::parse($response);
    }

    public function upload($filename, $mimeType, $file, $parentId, $uploadType = "multipart")
    {
        $metadata = json_encode([
            'name' => $filename,
            'mimeType' => $mimeType,
            'parents' => [$parentId],
        ]);

        $response = Laradrive::httpClient()->post("https://www.googleapis.com/upload/drive/v3/files", [
            'query' => [
                'uploadType' => $uploadType
            ],
            'multipart' => [
                [
                    'name' => 'metadata',
                    'contents' => $metadata,
                    'headers' => [
                        'Content-Type' => 'application/json; charset=UTF-8'
                    ]
                ],
                [
                    'name' => 'file',
                    'contents' => $file,
                    'headers' => [
                        'Content-Type' => $mimeType
                    ]
                ]
            ]
        ]);

        return ResponseWrapper::parse($response);
    }

    public function create($jsonBody)
    {
        $response = Laradrive::httpClient()->post("files", [
            'json' => $jsonBody,
        ]);

        return ResponseWrapper::parse($response);
    }

    public function copy($fileId, $destinationId, $name = null)
    {
        $body = [
            'parents' => [$destinationId]
        ];

        if ($name !== null) {
            $body["name"] = $name;
        }

        $response = Laradrive::httpClient()->post("files/$fileId/copy", [
            'json' => $body
        ]);

        return ResponseWrapper::parse($response);
    }

    public function rename($fileId, $name)
    {
        $body = [
            'name' => $name,
        ];

        $response = Laradrive::httpClient()->patch("files/$fileId", [
            'json' => $body
        ]);

        return ResponseWrapper::parse($response);
    }
}
