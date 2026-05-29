<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';

header('Content-Type: application/json; charset=utf-8');

$cacheFile = sys_get_temp_dir() . '/phpphotos_map_' . md5(FLICKR_USER_ID) . '.json';

// Cache valide (< 1h) → retour direct
if (file_exists($cacheFile) && (filemtime($cacheFile) + 3600) > time()) {
    readfile($cacheFile);
    exit;
}

$api    = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);
$photos = [];

try {
    $page  = 1;
    $pages = 1;

    do {
        $result  = $api->getGeoPhotos($page);
        $photos  = array_merge($photos, $result['photos']);
        $pages   = $result['pages'];
        $page++;
    } while ($page <= $pages);

} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}

$json = json_encode($photos, JSON_UNESCAPED_UNICODE);
file_put_contents($cacheFile, $json);
echo $json;
