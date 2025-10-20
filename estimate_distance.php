<?php
/**
 * AJAX Endpoint for Distance Estimation
 * Works independently without database connection
 */

// Set content type to JSON
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $clientId = $_POST['client_id'] ?? null;
    $loadingLoc = $_POST['loading_loc'] ?? '';
    $unloadingLoc = $_POST['unloading_loc'] ?? '';
    
    // Validate required fields
    if (empty($loadingLoc) || empty($unloadingLoc)) {
        echo json_encode([
            'success' => false,
            'error' => 'Loading and unloading locations are required'
        ]);
        exit;
    }
    
    // Calculate distance using fallback method (no database required)
    $result = calculateDistanceWithoutDB($clientId, $loadingLoc, $unloadingLoc);
    
    echo json_encode([
        'success' => true,
        'distance_km' => $result['distance_km'],
        'duration_minutes' => $result['duration_minutes'],
        'calculation_type' => $result['calculation_type'],
        'unloading_count' => $result['unloading_count'] ?? 0,
        'coordinates' => $result['coordinates'] ?? null,
        'error' => $result['error'] ?? null,
        'note' => 'Direct calculation (database-independent)'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}

/**
 * Calculate distance without requiring database connection
 */
function calculateDistanceWithoutDB($clientId, $loadingLoc, $unloadingLoc) {
    // Determine calculation type based on loading location text
    $calculationType = determineCalculationType($loadingLoc, $unloadingLoc);
    
    try {
        // Geocode loading location
        $loadingCoords = geocodeAddress($loadingLoc);
        if (!$loadingCoords) {
            return [
                'distance_km' => 0,
                'duration_minutes' => 0,
                'calculation_type' => $calculationType,
                'error' => 'Could not geocode loading address: ' . $loadingLoc
            ];
        }
        
        // Parse and geocode multiple unloading locations
        $unloadingAddresses = parseUnloadingLocations($unloadingLoc);
        $unloadingCoords = [];
        $failedAddresses = [];
        
        foreach ($unloadingAddresses as $address) {
            $coords = geocodeAddress($address);
            if ($coords) {
                $unloadingCoords[] = $coords;
            } else {
                $failedAddresses[] = $address;
            }
        }
        
        if (empty($unloadingCoords)) {
            return [
                'distance_km' => 0,
                'duration_minutes' => 0,
                'calculation_type' => $calculationType,
                'error' => 'Could not geocode any unloading addresses'
            ];
        }
        
        // Calculate optimized route
        $route = calculateOptimizedRoute($loadingCoords, $unloadingCoords, $calculationType);
        
        $result = [
            'distance_km' => $route['distance_km'],
            'duration_minutes' => $route['duration_minutes'],
            'calculation_type' => $calculationType,
            'unloading_count' => count($unloadingCoords),
            'coordinates' => [
                'loading' => $loadingCoords,
                'unloading' => $unloadingCoords,
                'route_geometry' => $route['geometry'] ?? null
            ]
        ];
        
        if (!empty($failedAddresses)) {
            $result['warning'] = 'Some addresses could not be geocoded: ' . implode(', ', $failedAddresses);
        }
        
        return $result;
        
    } catch (Exception $e) {
        // Ultimate fallback - estimated distance based on content
        $estimatedDistance = estimateDistanceFromText($unloadingLoc);
        
        return [
            'distance_km' => $estimatedDistance,
            'duration_minutes' => round($estimatedDistance * 2.5), // ~2.5 min per km
            'calculation_type' => 'text_based_estimate',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Determine calculation type based on location text
 */
function determineCalculationType($loadingLoc, $unloadingLoc) {
    $loadingLower = strtolower($loadingLoc);
    $unloadingLower = strtolower($unloadingLoc);
    
    // Check for Milšped indicators FIRST (highest priority)
    if (strpos($loadingLower, 'milšped') !== false || 
        strpos($loadingLower, 'milsped') !== false ||
        strpos($unloadingLower, 'milšped') !== false || 
        strpos($unloadingLower, 'milsped') !== false) {
        return 'milsped_doo';
    }
    
    // Check for IMLEK/Batajnički (Delta Transporti indicators)
    if (strpos($loadingLower, 'imlek') !== false || 
        strpos($loadingLower, 'batajnički') !== false ||
        strpos($loadingLower, 'batajnica') !== false) {
        return 'delta_transporti';
    }
    
    // Check if any address contains Nova Pazova (Delta Transporti base)
    if (strpos($loadingLower, 'nova pazova') !== false || 
        strpos($unloadingLower, 'nova pazova') !== false) {
        return 'delta_transporti';
    }
    
    // For all other cases, use standard calculation (no special routing)
    return 'standard';
}

/**
 * Geocode address using Nominatim
 */
function geocodeAddress($address) {
    // Clean address for better geocoding
    $cleanAddress = cleanAddressForGeocoding($address);
    
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $cleanAddress,
        'format' => 'json',
        'limit' => 1,
        'countrycodes' => 'rs',
        'addressdetails' => 1
    ]);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'TMS-Distance-Calculator/1.0',
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }
    
    $data = json_decode($response, true);
    if (empty($data)) {
        return null;
    }
    
    return [
        'lat' => (float)$data[0]['lat'],
        'lng' => (float)$data[0]['lon']
    ];
}

/**
 * Clean address text for better geocoding
 */
function cleanAddressForGeocoding($address) {
    $original = $address;
    
    // Remove IMLEK prefix and similar company indicators
    $address = preg_replace('/^\s*(IMLEK\s*-\s*)/i', '', $address);
    
    // Remove DIS codes and object names in format "DIS 357 (naziv objekta) - "
    $address = preg_replace('/DIS\s+\d+\s*\([^)]+\)\s*-\s*/i', '', $address);
    
    // Remove object codes like "TMO 123 456" at the beginning
    $address = preg_replace('/^\s*TMO\s+\d+\s+\d+\s*-?\s*/i', '', $address);
    
    // Clean up extra spaces and dashes
    $address = preg_replace('/\s*-\s*/', ' ', $address);
    $address = preg_replace('/\s+/', ' ', $address);
    
    // Add Belgrade context for street addresses without city
    if (!preg_match('/\b(beograd|novi sad|niš|kragujevac|subotica|pančevo|čačak|kruševac|novi pazar|smederevo|leskovac|vranje)\b/i', $address)) {
        // If it looks like a street address (contains numbers), add Belgrade
        if (preg_match('/\d+/', $address)) {
            $address .= ', Beograd';
        }
    }
    
    // Add Serbia context if not present
    if (stripos($address, 'serbia') === false && stripos($address, 'srbija') === false) {
        $address .= ', Serbia';
    }
    
    return trim($address);
}

/**
 * Parse multiple unloading locations
 */
function parseUnloadingLocations($unloadingText) {
    // Split by commas first
    $locations = explode(',', $unloadingText);
    
    $cleanedLocations = [];
    foreach ($locations as $location) {
        $location = trim($location);
        if (!empty($location)) {
            $cleanedLocations[] = $location;
        }
    }
    
    return $cleanedLocations;
}

/**
 * Calculate optimized route using OSRM
 */
function calculateOptimizedRoute($loadingCoords, $unloadingCoords, $calculationType) {
    if ($calculationType === 'delta_transporti') {
        // Start from Nova Pazova, visit all unloading locations, return to Nova Pazova
        return calculateDeltaTransportiRoute($unloadingCoords);
    } else if ($calculationType === 'milsped_doo') {
        // Round trip from loading location with optimized stops
        return calculateMilspedRoute($loadingCoords, $unloadingCoords);
    } else {
        // Standard route - just loading to first unloading
        return calculateStandardRoute($loadingCoords, $unloadingCoords[0]);
    }
}

/**
 * Calculate Delta Transporti route (from Nova Pazova)
 */
function calculateDeltaTransportiRoute($unloadingCoords) {
    $novaPazova = ['lat' => 45.224722, 'lng' => 20.033333];
    
    // Build waypoints: Nova Pazova -> all unloading locations -> Nova Pazova
    $waypoints = [$novaPazova];
    $waypoints = array_merge($waypoints, $unloadingCoords);
    $waypoints[] = $novaPazova;
    
    return calculateRouteWithWaypoints($waypoints);
}

/**
 * Calculate Milšped route (round trip with optimization)
 */
function calculateMilspedRoute($loadingCoords, $unloadingCoords) {
    // For multiple stops, use OSRM trip service for optimization
    if (count($unloadingCoords) > 1) {
        return calculateOptimizedTrip($loadingCoords, $unloadingCoords);
    } else {
        // Simple round trip for single destination
        $waypoints = [$loadingCoords, $unloadingCoords[0], $loadingCoords];
        return calculateRouteWithWaypoints($waypoints);
    }
}

/**
 * Calculate standard route (one-way)
 */
function calculateStandardRoute($loadingCoords, $unloadingCoords) {
    $waypoints = [$loadingCoords, $unloadingCoords];
    return calculateRouteWithWaypoints($waypoints);
}

/**
 * Calculate route with given waypoints
 */
function calculateRouteWithWaypoints($waypoints) {
    $coordinates = [];
    foreach ($waypoints as $waypoint) {
        $coordinates[] = $waypoint['lng'] . ',' . $waypoint['lat'];
    }
    
    $url = 'https://router.project-osrm.org/route/v1/driving/' . implode(';', $coordinates) . '?overview=full&geometries=geojson';
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 15,
            'user_agent' => 'TMS-Route-Calculator/1.0',
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        throw new Exception('OSRM API unavailable');
    }
    
    $data = json_decode($response, true);
    if (!isset($data['routes'][0])) {
        throw new Exception('No route found');
    }
    
    $route = $data['routes'][0];
    
    return [
        'distance_km' => round($route['distance'] / 1000, 2),
        'duration_minutes' => round($route['duration'] / 60),
        'geometry' => $route['geometry']
    ];
}

/**
 * Calculate optimized trip using OSRM trip service
 */
function calculateOptimizedTrip($loadingCoords, $unloadingCoords) {
    // Build all coordinates for trip optimization
    $allCoords = [$loadingCoords];
    $allCoords = array_merge($allCoords, $unloadingCoords);
    
    $coordinates = [];
    foreach ($allCoords as $coord) {
        $coordinates[] = $coord['lng'] . ',' . $coord['lat'];
    }
    
    // Use OSRM trip service for optimization
    $url = 'https://router.project-osrm.org/trip/v1/driving/' . implode(';', $coordinates) . 
           '?source=first&destination=first&roundtrip=true&overview=full&geometries=geojson';
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 20,
            'user_agent' => 'TMS-Trip-Optimizer/1.0',
            'ignore_errors' => true
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        // Fallback to simple route
        $waypoints = [$loadingCoords];
        $waypoints = array_merge($waypoints, $unloadingCoords);
        $waypoints[] = $loadingCoords;
        return calculateRouteWithWaypoints($waypoints);
    }
    
    $data = json_decode($response, true);
    if (!isset($data['trips'][0])) {
        // Fallback to simple route
        $waypoints = [$loadingCoords];
        $waypoints = array_merge($waypoints, $unloadingCoords);
        $waypoints[] = $loadingCoords;
        return calculateRouteWithWaypoints($waypoints);
    }
    
    $trip = $data['trips'][0];
    
    return [
        'distance_km' => round($trip['distance'] / 1000, 2),
        'duration_minutes' => round($trip['duration'] / 60),
        'geometry' => $trip['geometry']
    ];
}

/**
 * Estimate distance based on text content (fallback)
 */
function estimateDistanceFromText($unloadingText) {
    // Count number of locations mentioned
    $locationCount = 1;
    
    // Split by common delimiters
    $parts = preg_split('/[,;\n]+/', $unloadingText);
    $locationCount = count(array_filter(array_map('trim', $parts)));
    
    // Base distance estimate
    $baseDistance = 25; // Average city delivery distance
    
    // Add distance for multiple locations
    if ($locationCount > 1) {
        $baseDistance += ($locationCount - 1) * 8; // 8km per additional stop
    }
    
    // Adjust based on text complexity (longer descriptions might indicate farther locations)
    $textComplexity = strlen($unloadingText) / 50; // Normalize by expected length
    $baseDistance += min(15, $textComplexity * 5); // Max 15km adjustment
    
    return max(5, min(120, round($baseDistance))); // Between 5-120km
}
?>