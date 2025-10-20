<?php
/**
 * OSRM Service - Route and Distance Calculation
 * Provides integration with Open Source Routing Machine (OSRM) API
 */

class OSRMService {
    private $baseUrl;
    private $timeout;
    private $userAgent;
    
    public function __construct($baseUrl = 'http://router.project-osrm.org', $timeout = 30) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->userAgent = 'TMS-OSRM-Client/1.0';
    }
    
    /**
     * Calculate route between coordinates
     * @param array $coordinates Array of [longitude, latitude] pairs
     * @param array $options Route calculation options
     * @return array Route data with distance, duration, and geometry
     */
    public function calculateRoute($coordinates, $options = []) {
        $profile = $options['profile'] ?? 'driving';
        $steps = $options['steps'] ?? false;
        $alternatives = $options['alternatives'] ?? false;
        $geometries = $options['geometries'] ?? 'polyline';
        
        // Format coordinates: lng,lat;lng,lat
        $coordString = $this->formatCoordinates($coordinates);
        
        $params = [
            'steps' => $steps ? 'true' : 'false',
            'alternatives' => $alternatives ? 'true' : 'false',
            'geometries' => $geometries,
            'overview' => 'full'
        ];
        
        $url = "{$this->baseUrl}/route/v1/{$profile}/{$coordString}?" . http_build_query($params);
        
        $response = $this->makeRequest($url);
        
        if (empty($response['routes'])) {
            throw new Exception('No route found between specified coordinates');
        }
        
        $route = $response['routes'][0];
        
        return [
            'distance_meters' => $route['distance'],
            'distance_km' => round($route['distance'] / 1000, 2),
            'duration_seconds' => $route['duration'],
            'duration_minutes' => round($route['duration'] / 60, 1),
            'geometry' => $route['geometry'],
            'legs' => $route['legs'] ?? []
        ];
    }
    
    /**
     * Calculate distance matrix between multiple points
     * @param array $coordinates Array of [longitude, latitude] pairs
     * @param array $options Matrix calculation options
     * @return array Distance and duration matrices
     */
    public function calculateMatrix($coordinates, $options = []) {
        $profile = $options['profile'] ?? 'driving';
        $annotations = $options['annotations'] ?? 'duration,distance';
        $sources = $options['sources'] ?? null;
        $destinations = $options['destinations'] ?? null;
        
        $coordString = $this->formatCoordinates($coordinates);
        
        $params = ['annotations' => $annotations];
        if ($sources !== null) {
            $params['sources'] = is_array($sources) ? implode(';', $sources) : $sources;
        }
        if ($destinations !== null) {
            $params['destinations'] = is_array($destinations) ? implode(';', $destinations) : $destinations;
        }
        
        $url = "{$this->baseUrl}/table/v1/{$profile}/{$coordString}?" . http_build_query($params);
        
        return $this->makeRequest($url);
    }
    
    /**
     * Find nearest road point to coordinates
     * @param float $longitude
     * @param float $latitude
     * @param array $options
     * @return array Nearest waypoint data
     */
    public function findNearest($longitude, $latitude, $options = []) {
        $profile = $options['profile'] ?? 'driving';
        $number = $options['number'] ?? 1;
        
        $params = ['number' => $number];
        $url = "{$this->baseUrl}/nearest/v1/{$profile}/{$longitude},{$latitude}?" . http_build_query($params);
        
        return $this->makeRequest($url);
    }
    
    /**
     * Calculate route with multiple stops and return to start
     * @param array $startCoord [longitude, latitude]
     * @param array $stopCoords Array of [longitude, latitude] pairs
     * @return array Total route data
     */
    public function calculateRoundTrip($startCoord, $stopCoords) {
        // Build coordinates array: start -> stops -> back to start
        $coordinates = [$startCoord];
        $coordinates = array_merge($coordinates, $stopCoords);
        $coordinates[] = $startCoord; // Return to start
        
        return $this->calculateRoute($coordinates, [
            'steps' => true,
            'geometries' => 'polyline'
        ]);
    }
    
    /**
     * Format coordinates for OSRM API
     * @param array $coordinates
     * @return string
     */
    private function formatCoordinates($coordinates) {
        $formatted = [];
        foreach ($coordinates as $coord) {
            if (!is_array($coord) || count($coord) < 2) {
                throw new Exception('Invalid coordinate format. Expected [longitude, latitude]');
            }
            $formatted[] = $coord[0] . ',' . $coord[1]; // lng,lat
        }
        return implode(';', $formatted);
    }
    
    /**
     * Make HTTP request to OSRM API
     * @param string $url
     * @return array
     */
    private function makeRequest($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . $this->userAgent
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_SSL_VERIFYPEER => false, // For development
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("OSRM API connection error: {$error}");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("OSRM API HTTP error: {$httpCode}");
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("OSRM API JSON decode error: " . json_last_error_msg());
        }
        
        if (!isset($data['code']) || $data['code'] !== 'Ok') {
            $message = $data['message'] ?? 'Unknown OSRM API error';
            throw new Exception("OSRM API error: {$message}");
        }
        
        return $data;
    }
}

/**
 * Geocoding Service - Convert addresses to coordinates
 * Uses Nominatim (OpenStreetMap) geocoding service
 */
class GeocodingService {
    private $baseUrl;
    private $timeout;
    private $userAgent;
    private $rateLimitDelay;
    
    public function __construct($baseUrl = 'https://nominatim.openstreetmap.org', $timeout = 30, $rateLimitDelay = 1) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->timeout = $timeout;
        $this->userAgent = 'TMS-Geocoding-Client/1.0';
        $this->rateLimitDelay = $rateLimitDelay; // Seconds between requests
    }
    
    /**
     * Geocode address to coordinates
     * @param string $address
     * @param string $countryCode Optional country code (e.g., 'rs' for Serbia)
     * @return array [longitude, latitude] or null if not found
     */
    public function geocodeAddress($address, $countryCode = 'rs') {
        if (empty(trim($address))) {
            return null;
        }
        
        $params = [
            'q' => trim($address),
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 1,
            'countrycodes' => $countryCode
        ];
        
        $url = $this->baseUrl . '/search?' . http_build_query($params);
        
        // Rate limiting - respect Nominatim usage policy
        if ($this->rateLimitDelay > 0) {
            sleep($this->rateLimitDelay);
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . $this->userAgent
            ],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("Geocoding API connection error: {$error}");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Geocoding API HTTP error: {$httpCode}");
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Geocoding API JSON decode error: " . json_last_error_msg());
        }
        
        if (empty($data)) {
            return null; // Address not found
        }
        
        $result = $data[0];
        return [
            floatval($result['lon']), // longitude
            floatval($result['lat'])  // latitude
        ];
    }
    
    /**
     * Batch geocode multiple addresses
     * @param array $addresses
     * @param string $countryCode
     * @return array Array of geocoded results
     */
    public function batchGeocode($addresses, $countryCode = 'rs') {
        $results = [];
        
        foreach ($addresses as $index => $address) {
            try {
                $coordinates = $this->geocodeAddress($address, $countryCode);
                $results[$index] = [
                    'address' => $address,
                    'coordinates' => $coordinates,
                    'success' => $coordinates !== null
                ];
            } catch (Exception $e) {
                $results[$index] = [
                    'address' => $address,
                    'coordinates' => null,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}

/**
 * Client-specific Route Calculator
 * Handles different calculation logic for different clients
 */
class ClientRouteCalculator {
    private $osrm;
    private $geocoding;
    private $pdo;
    
    public function __construct($pdo, $osrmBaseUrl = null, $geocodingBaseUrl = null) {
        $this->pdo = $pdo;
        $this->osrm = new OSRMService($osrmBaseUrl);
        $this->geocoding = new GeocodingService($geocodingBaseUrl);
    }
    
    /**
     * Calculate distance for a tour based on client configuration
     * @param int $tourId
     * @return array Distance calculation result
     */
    public function calculateTourDistance($tourId) {
        // Get tour data first
        $stmt = $this->pdo->prepare("SELECT * FROM tours WHERE id = ?");
        $stmt->execute([$tourId]);
        $tour = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tour) {
            throw new Exception("Tour not found: {$tourId}");
        }
        
        // Get client data with fallback if route_calculation_type doesn't exist
        $calculationType = 'standard';
        if ($tour['client_id']) {
            try {
                // Check if route_calculation_type column exists
                $stmt = $this->pdo->query("SHOW COLUMNS FROM clients LIKE 'route_calculation_type'");
                $columnExists = $stmt->fetchColumn();
                
                if ($columnExists) {
                    $stmt = $this->pdo->prepare("
                        SELECT route_calculation_type, base_latitude, base_longitude, base_location_name, route_config_json
                        FROM clients WHERE id = ?
                    ");
                    $stmt->execute([$tour['client_id']]);
                    $clientConfig = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($clientConfig) {
                        $tour = array_merge($tour, $clientConfig);
                        $calculationType = $clientConfig['route_calculation_type'] ?? 'standard';
                    }
                } else {
                    // Fallback: determine by client name
                    $stmt = $this->pdo->prepare("SELECT name FROM clients WHERE id = ?");
                    $stmt->execute([$tour['client_id']]);
                    $client = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($client) {
                        $clientName = strtolower($client['name']);
                        if (strpos($clientName, 'delta') !== false || strpos($clientName, 'transporti') !== false) {
                            $calculationType = 'delta_transporti';
                        } elseif (strpos($clientName, 'milšped') !== false || strpos($clientName, 'milsped') !== false) {
                            $calculationType = 'milsped_doo';
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Client config error in calculateTourDistance: " . $e->getMessage());
                $calculationType = 'standard';
            }
        }
        
        switch ($calculationType) {
            case 'delta_transporti':
                return $this->calculateDeltaTransportiRoute($tour);
                
            case 'milsped_doo':
                return $this->calculateMilspedRoute($tour);
                
            default:
                return $this->calculateStandardRoute($tour);
        }
    }
    
    /**
     * Delta Transporti calculation logic
     * Start: Nova Pazova -> Stores based on unloading location
     */
    private function calculateDeltaTransportiRoute($tour) {
        // Base coordinates for Nova Pazova
        $startCoords = [20.033333, 45.224722]; // [lng, lat]
        
        // Parse unloading locations to find stores
        $unloadingText = $tour['unloading_loc'] ?? '';
        $storeAddresses = $this->parseStoreAddresses($unloadingText);
        
        if (empty($storeAddresses)) {
            // No specific stores, use standard calculation
            return $this->calculateStandardRoute($tour);
        }
        
        // Geocode store addresses
        $storeCoords = [];
        foreach ($storeAddresses as $address) {
            $coords = $this->geocoding->geocodeAddress($address);
            if ($coords) {
                $storeCoords[] = $coords;
            }
        }
        
        if (empty($storeCoords)) {
            // Fallback to standard calculation
            return $this->calculateStandardRoute($tour);
        }
        
        // Calculate route: Nova Pazova -> stores -> back to Nova Pazova
        $routeData = $this->osrm->calculateRoundTrip($startCoords, $storeCoords);
        
        return [
            'distance_km' => $routeData['distance_km'],
            'duration_minutes' => $routeData['duration_minutes'],
            'calculation_type' => 'delta_transporti',
            'start_location' => 'Nova Pazova',
            'store_count' => count($storeCoords),
            'route_geometry' => $routeData['geometry']
        ];
    }
    
    /**
     * Milšped DOO calculation logic
     * Loading location -> Object-Address unloading -> back to loading
     */
    private function calculateMilspedRoute($tour) {
        // Geocode loading location
        $loadingCoords = $this->geocoding->geocodeAddress($tour['loading_loc'] ?? '');
        if (!$loadingCoords) {
            throw new Exception('Cannot geocode loading location for Milšped route');
        }
        
        // Parse unloading locations (Object - Address format)
        $unloadingText = $tour['unloading_loc'] ?? '';
        $unloadingAddresses = $this->parseObjectAddresses($unloadingText);
        
        if (empty($unloadingAddresses)) {
            // Fallback to direct route
            $unloadingCoords = $this->geocoding->geocodeAddress($unloadingText);
            if ($unloadingCoords) {
                $unloadingAddresses = [$unloadingText];
            }
        }
        
        // Geocode unloading addresses
        $unloadingCoords = [];
        foreach ($unloadingAddresses as $address) {
            $coords = $this->geocoding->geocodeAddress($address);
            if ($coords) {
                $unloadingCoords[] = $coords;
            }
        }
        
        if (empty($unloadingCoords)) {
            throw new Exception('Cannot geocode unloading locations for Milšped route');
        }
        
        // Calculate route: loading -> unloading points -> back to loading
        $routeData = $this->osrm->calculateRoundTrip($loadingCoords, $unloadingCoords);
        
        return [
            'distance_km' => $routeData['distance_km'],
            'duration_minutes' => $routeData['duration_minutes'],
            'calculation_type' => 'milsped_doo',
            'start_location' => $tour['loading_loc'],
            'unloading_count' => count($unloadingCoords),
            'route_geometry' => $routeData['geometry']
        ];
    }
    
    /**
     * Standard route calculation
     * Direct route from loading to unloading location
     */
    private function calculateStandardRoute($tour) {
        $loadingCoords = $this->geocoding->geocodeAddress($tour['loading_loc'] ?? '');
        $unloadingCoords = $this->geocoding->geocodeAddress($tour['unloading_loc'] ?? '');
        
        if (!$loadingCoords || !$unloadingCoords) {
            return [
                'distance_km' => 0,
                'duration_minutes' => 0,
                'calculation_type' => 'standard',
                'error' => 'Cannot geocode addresses'
            ];
        }
        
        $routeData = $this->osrm->calculateRoute([$loadingCoords, $unloadingCoords]);
        
        return [
            'distance_km' => $routeData['distance_km'],
            'duration_minutes' => $routeData['duration_minutes'],
            'calculation_type' => 'standard',
            'route_geometry' => $routeData['geometry']
        ];
    }
    
    /**
     * Parse store addresses from text (for Delta Transporti)
     */
    private function parseStoreAddresses($text) {
        // Extract store addresses from various formats
        $addresses = [];
        
        // Split by common delimiters
        $lines = preg_split('/[\r\n,;]+/', $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                // Look for patterns like "TMO 213 843" or address-like strings
                if (preg_match('/TMO\s+\d+\s+\d+/', $line) || strlen($line) > 10) {
                    $addresses[] = $line;
                }
            }
        }
        
        return $addresses;
    }
    
    /**
     * Parse Object-Address format (for Milšped DOO)
     */
    private function parseObjectAddresses($text) {
        $addresses = [];
        
        // Split by lines and look for "Object - Address" format
        $lines = preg_split('/[\r\n]+/', $text);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                // Extract address part after " - " or use whole line
                if (strpos($line, ' - ') !== false) {
                    $parts = explode(' - ', $line, 2);
                    $addresses[] = trim($parts[1]);
                } else {
                    $addresses[] = $line;
                }
            }
        }
        
        return $addresses;
    }
}
?>