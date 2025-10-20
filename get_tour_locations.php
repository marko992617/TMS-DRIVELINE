
<?php
header('Content-Type: application/json; charset=utf-8');
require 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$toursData = $_SESSION['tours_data'] ?? [];

if (empty($toursData)) {
    echo json_encode(['ok' => false, 'error' => 'Nema podataka o turama.']);
    exit;
}

$locations = [];

// Get geocoding data for locations
foreach ($toursData as $index => $tour) {
    $loadingLoc = $tour['loading_loc'] ?? '';
    $unloadingLoc = $tour['unloading_loc'] ?? '';
    
    // For demo purposes, generate random coordinates around Belgrade
    // In production, you would use real geocoding
    $locations[] = [
        'index' => $index,
        'loading_loc' => $loadingLoc,
        'unloading_loc' => $unloadingLoc,
        'lat' => 44.8125 + (($index * 7) % 100 - 50) / 1000,
        'lng' => 20.4612 + (($index * 11) % 100 - 50) / 1000,
        'ors_id' => $tour['ors_id'] ?? '',
        'delivery_type' => $tour['delivery_type'] ?? ''
    ];
}

echo json_encode(['ok' => true, 'locations' => $locations]);
?>
