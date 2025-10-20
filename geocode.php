<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Rate limiting check (simple session-based)
session_start();
$now = time();
if (!isset($_SESSION['geocode_last_request'])) {
    $_SESSION['geocode_last_request'] = 0;
}

// Enforce 1 request per second
if ($now - $_SESSION['geocode_last_request'] < 1) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limited. Please wait.']);
    exit;
}

$_SESSION['geocode_last_request'] = $now;

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query) || strlen($query) < 3) {
    echo json_encode([]);
    exit;
}

// Build Nominatim URL
$nominatim_url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
    'q' => $query,
    'format' => 'jsonv2',
    'addressdetails' => 1,
    'limit' => 5,
    'countrycodes' => 'rs,ba,hr,si,hu,ro,bg,me,mk', // Balkans region
    'accept-language' => 'sr,en'
]);

// Setup cURL with proper User-Agent
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $nominatim_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_USERAGENT => 'DRIVELINE-TMS/1.0 (https://driveline-tms.com)',
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $http_code !== 200) {
    http_response_code(500);
    echo json_encode(['error' => 'Geocoding service unavailable']);
    exit;
}

$data = json_decode($response, true);
if (!is_array($data)) {
    echo json_encode([]);
    exit;
}

// Format results for autocomplete
$results = [];
foreach ($data as $item) {
    $results[] = [
        'display_name' => $item['display_name'] ?? '',
        'lat' => floatval($item['lat'] ?? 0),
        'lng' => floatval($item['lon'] ?? 0),
        'name' => $item['name'] ?? $item['display_name'] ?? '',
        'type' => $item['type'] ?? '',
        'importance' => floatval($item['importance'] ?? 0)
    ];
}

// Sort by importance (higher first)
usort($results, function($a, $b) {
    return $b['importance'] <=> $a['importance'];
});

echo json_encode($results);
?>