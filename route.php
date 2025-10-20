<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['waypoints']) || !is_array($input['waypoints']) || count($input['waypoints']) < 2) {
    http_response_code(400);
    echo json_encode(['error' => 'At least 2 waypoints required']);
    exit;
}

$waypoints = $input['waypoints'];
$roundtrip = isset($input['roundtrip']) && $input['roundtrip'] === true;

// Validate waypoints structure
foreach ($waypoints as $waypoint) {
    if (!isset($waypoint['lat'], $waypoint['lng']) || 
        !is_numeric($waypoint['lat']) || !is_numeric($waypoint['lng'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid waypoint coordinates']);
        exit;
    }
}

// Build coordinates for OSRM (lon,lat format)
$coordinates = [];
foreach ($waypoints as $waypoint) {
    $coordinates[] = $waypoint['lng'] . ',' . $waypoint['lat'];
}

// Add roundtrip if requested
if ($roundtrip && count($coordinates) > 0) {
    $coordinates[] = $coordinates[0]; // Return to start
}

// Build OSRM URL
$osrm_url = 'http://router.project-osrm.org/route/v1/driving/' . implode(';', $coordinates) . '?' . http_build_query([
    'overview' => 'full',
    'geometries' => 'geojson',
    'steps' => 'false',
    'annotations' => 'false'
]);

// Make OSRM request
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $osrm_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_USERAGENT => 'DRIVELINE-TMS/1.0',
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $http_code !== 200) {
    http_response_code(500);
    echo json_encode(['error' => 'Routing service unavailable']);
    exit;
}

$data = json_decode($response, true);

if (!isset($data['routes']) || empty($data['routes'])) {
    http_response_code(404);
    echo json_encode(['error' => 'No route found']);
    exit;
}

$route = $data['routes'][0];

// Extract route information
$result = [
    'distance_km' => round(($route['distance'] ?? 0) / 1000, 2),
    'duration_min' => round(($route['duration'] ?? 0) / 60, 1),
    'geometry' => $route['geometry'] ?? null,
    'waypoints_used' => count($waypoints),
    'roundtrip' => $roundtrip
];

// Add route summary (waypoint names)
$route_summary = [];
foreach ($waypoints as $waypoint) {
    if (!empty($waypoint['name'])) {
        $route_summary[] = $waypoint['name'];
    }
}

if ($roundtrip && !empty($route_summary)) {
    $route_summary[] = $route_summary[0]; // Add return to start
}

$result['route_summary'] = implode(' → ', $route_summary);

echo json_encode($result);
?>