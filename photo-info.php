<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/FlickrAPI.php';

header('Content-Type: application/json');

$photoId = $_GET['id'] ?? '';
if (!$photoId || !preg_match('/^\d+$/', $photoId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid photo id']);
    exit;
}

$api = new FlickrAPI(FLICKR_API_KEY, FLICKR_USER_ID);

$exif     = [];
$location = [];

try { $exif     = $api->getExif($photoId);     } catch (RuntimeException) {}
try { $location = $api->getLocation($photoId); } catch (RuntimeException) {}

echo json_encode(['exif' => $exif, 'location' => $location]);
