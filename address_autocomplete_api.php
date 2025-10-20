<?php
header('Content-Type: application/json');

if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
    echo json_encode([]);
    exit;
}

$query = trim($_GET['q']);

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$params = [
    'q' => $query . ', Serbia',
    'format' => 'json',
    'limit' => 5,
    'addressdetails' => 1,
    'countrycodes' => 'rs'
];

$url = 'https://nominatim.openstreetmap.org/search?' . http_build_query($params);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'User-Agent: DRIVELINE-TMS/1.0'
    ],
    CURLOPT_FOLLOWLOCATION => true,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200 || !$response) {
    echo json_encode([]);
    exit;
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
    echo json_encode([]);
    exit;
}

$suggestions = [];
foreach ($data as $result) {
    $displayName = $result['display_name'];
    
    if (isset($result['address'])) {
        $addr = $result['address'];
        $parts = [];
        
        if (isset($addr['road'])) $parts[] = $addr['road'];
        if (isset($addr['house_number'])) $parts[] = $addr['house_number'];
        if (isset($addr['city'])) {
            $parts[] = $addr['city'];
        } elseif (isset($addr['town'])) {
            $parts[] = $addr['town'];
        } elseif (isset($addr['village'])) {
            $parts[] = $addr['village'];
        }
        
        $shortName = !empty($parts) ? implode(', ', $parts) : $displayName;
    } else {
        $shortName = $displayName;
    }
    
    $suggestions[] = [
        'display' => $shortName,
        'full' => $displayName,
        'lat' => floatval($result['lat']),
        'lon' => floatval($result['lon'])
    ];
}

echo json_encode($suggestions);
