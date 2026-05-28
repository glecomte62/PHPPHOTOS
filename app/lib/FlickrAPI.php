<?php

class FlickrAPI
{
    private string $apiKey;
    private string $userId;
    private string $baseUrl = 'https://api.flickr.com/services/rest/';

    public function __construct(string $apiKey, string $userId)
    {
        $this->apiKey  = $apiKey;
        $this->userId  = $userId;
    }

    /**
     * Retourne la liste des galeries (photosets) de l'utilisateur.
     * Chaque élément : ['id', 'title', 'cover_url', 'photo_count']
     */
    public function getPhotosets(): array
    {
        $data = $this->request([
            'method'  => 'flickr.photosets.getList',
            'user_id' => $this->userId,
            'per_page' => 500,
            'primary_photo_extras' => 'url_l,url_m',
        ]);

        if (($data['stat'] ?? '') !== 'ok') {
            throw new RuntimeException('Flickr API error: ' . ($data['message'] ?? 'unknown'));
        }

        $photosets = [];
        foreach ($data['photosets']['photoset'] ?? [] as $ps) {
            $coverUrl = $ps['primary_photo_extras']['url_l']
                     ?? $ps['primary_photo_extras']['url_m']
                     ?? (isset($ps['farm'], $ps['server'], $ps['primary'], $ps['secret'])
                         ? $this->buildPhotoUrl($ps['farm'], $ps['server'], $ps['primary'], $ps['secret'], 'z')
                         : '');

            $photosets[] = [
                'id'          => $ps['id'],
                'title'       => $ps['title']['_content'] ?? '',
                'description' => $ps['description']['_content'] ?? '',
                'photo_count' => (int)($ps['photos'] ?? 0),
                'cover_url'   => $coverUrl,
            ];
        }

        return $photosets;
    }

    /**
     * Retourne les photos et le titre d'une galerie.
     * Retourne ['title' => string, 'photos' => array]
     * Chaque photo : ['id', 'title', 'description', 'url_thumb', 'url_large', 'date_taken', 'tags']
     */
    public function getPhotos(string $photosetId): array
    {
        $data = $this->request([
            'method'      => 'flickr.photosets.getPhotos',
            'photoset_id' => $photosetId,
            'user_id'     => $this->userId,
            'extras'      => 'url_l,url_m,url_sq,date_taken,description,tags',
            'per_page'    => 500,
        ]);

        if (($data['stat'] ?? '') !== 'ok') {
            throw new RuntimeException('Flickr API error: ' . ($data['message'] ?? 'unknown'));
        }

        $photos = [];
        foreach ($data['photoset']['photo'] ?? [] as $p) {
            $photos[] = [
                'id'          => $p['id'],
                'title'       => $p['title'] ?? '',
                'description' => $p['description']['_content'] ?? '',
                'url_thumb'   => $p['url_sq'] ?? $p['url_m'] ?? '',
                'url_large'   => $p['url_l']  ?? $p['url_m'] ?? '',
                'date_taken'  => $p['datetaken'] ?? '',
                'tags'        => array_filter(explode(' ', $p['tags'] ?? '')),
            ];
        }

        return [
            'title'  => $data['photoset']['title']['_content'] ?? '',
            'photos' => $photos,
        ];
    }

    private function buildPhotoUrl(string $farm, string $server, string $id, string $secret, string $size): string
    {
        return "https://farm{$farm}.staticflickr.com/{$server}/{$id}_{$secret}_{$size}.jpg";
    }

    private function request(array $params): array
    {
        $params['api_key'] = $this->apiKey;
        $params['format']  = 'json';
        $params['nojsoncallback'] = 1;

        $url = $this->baseUrl . '?' . http_build_query($params);

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('cURL initialization failed — extension may not be available');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response  = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('cURL error (' . $curlErrno . '): ' . ($curlError ?: 'unknown'));
        }

        $decoded = json_decode($response, true);
        if ($decoded === null) {
            throw new RuntimeException('Invalid JSON from Flickr API');
        }

        return $decoded;
    }
}
