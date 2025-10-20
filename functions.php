<?php
require 'config.php';
require_once 'osrm_service.php';
global $pdo;

function addTour($data) {
    global $pdo;

    // Generate tracking number if not provided
    if (!isset($data[':tracking_number'])) {
        $data[':tracking_number'] = generateTrackingNumber();
    }

    // Set default client_id if not provided for backward compatibility
    if (!isset($data[':client_id'])) {
        $data[':client_id'] = null;
    }

    $sql = "INSERT INTO tours 
        (tracking_number, date, driver_id, vehicle_id, client_id, ors_id, delivery_type, km, estimated_km, fuel_cost, amortization, allowance, loading_time, loading_loc, unloading_loc, route, note, status)
        VALUES 
        (:tracking_number, :date, :driver, :vehicle, :client_id, :ors_id, :delivery_type, :km, :estimated_km, :fuel, :amort, :allowance, :load_time, :load_loc, :unload_loc, :route, :note, 'primljen_nalog')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    $tourId = $pdo->lastInsertId();

    // Add initial status to history
    $stmt = $pdo->prepare("INSERT INTO tour_status_history (tour_id, status, changed_by, notes) VALUES (?, 'primljen_nalog', 'System', 'Tura kreirana')");
    $stmt->execute([$tourId]);

    return $tourId;
}

function updateTour($data) {
    global $pdo;
    
    // Build SET clause dynamically - only update client_id if explicitly provided
    $setClause = "date=:date,
            driver_id=:driver,
            vehicle_id=:vehicle,
            ors_id=:ors_id,
            delivery_type=:delivery_type,
            km=:km,
            fuel_cost=:fuel,
            amortization=:amort,
            allowance=:allowance,
            loading_time=:load_time,
            loading_loc=:load_loc,
            unloading_loc=:unload_loc,
            route=:route,
            note=:note";
    
    // Only update client_id if explicitly provided
    if (array_key_exists(':client_id', $data)) {
        $setClause .= ", client_id=:client_id";
    }
    
    $sql = "UPDATE tours SET " . $setClause . " WHERE id=:id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);
}

function deleteTour($id) {
    global $pdo;
    $stmt = $pdo->prepare("DELETE FROM tours WHERE id = ?");
    $stmt->execute([$id]);
}

function getTours($filters = []) {
    global $pdo;
    $sql = "SELECT t.*, 
                   d.name AS driver, 
                   v.plate AS vehicle,
                   t.estimated_km,
                   t.tracking_number,
                   t.status,
                   t.status_updated_at
            FROM tours t
            LEFT JOIN drivers d ON t.driver_id = d.id
            LEFT JOIN vehicles v ON t.vehicle_id = v.id
            WHERE 1";
    $params = [];
    if (!empty($filters['driver'])) {
        $sql .= " AND t.driver_id = :driver";
        $params[':driver'] = $filters['driver'];
    }
    if (!empty($filters['vehicle'])) {
        $sql .= " AND t.vehicle_id = :vehicle";
        $params[':vehicle'] = $filters['vehicle'];
    }
    if (!empty($filters['from'])) {
        $sql .= " AND t.date >= :from";
        $params[':from'] = $filters['from'];
    }
    if (!empty($filters['to'])) {
        $sql .= " AND t.date <= :to";
        $params[':to'] = $filters['to'];
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getDrivers($activeOnly = false) {
    global $pdo;
    $sql = "SELECT * FROM drivers";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY name";
    return $pdo->query($sql)->fetchAll();
}

function getVehicles() {
    global $pdo;
    return $pdo->query("SELECT * FROM vehicles")->fetchAll();
}

function calculateAmortization($vehicleId, $km) {
    // TODO: implement amortization logic
    return 0;
}

function calculateAllowance($driverId) {
    // TODO: implement allowance logic
    return 0;
}

function getVehicleIdByPlate($plate) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id FROM vehicles WHERE plate = ?");
    $stmt->execute([$plate]);
    $v = $stmt->fetch();
    return $v ? $v['id'] : null;
}

function addDriver($name) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO drivers (name) VALUES (?)");
    $stmt->execute([$name]);
}

// Tracking system functions
function generateTrackingNumber() {
    global $pdo;
    $year = date('Y');

    // Get next sequential number for this year
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(tracking_number, 6) AS UNSIGNED)) as max_num 
                          FROM tours WHERE tracking_number LIKE ?");
    $stmt->execute(["T{$year}%"]);
    $result = $stmt->fetch();

    $nextNum = ($result['max_num'] ?? 0) + 1;
    return "T{$year}" . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
}

function updateTourStatus($tourId, $status, $changedBy = null, $notes = null, $problemDescription = null) {
    global $pdo;

    try {
        $pdo->beginTransaction();

        // Update tour status
        $stmt = $pdo->prepare("UPDATE tours SET status = ?, status_updated_at = NOW(), problem_description = ? WHERE id = ?");
        $stmt->execute([$status, $problemDescription, $tourId]);

        // Add to status history
        $stmt = $pdo->prepare("INSERT INTO tour_status_history (tour_id, status, changed_by, notes) VALUES (?, ?, ?, ?)");
        $stmt->execute([$tourId, $status, $changedBy, $notes]);

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function getTourStatusHistory($tourId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM tour_status_history WHERE tour_id = ? ORDER BY changed_at DESC");
    $stmt->execute([$tourId]);
    return $stmt->fetchAll();
}

function getStatusLabel($status) {
    $statusLabels = [
        'primljen_nalog' => 'Primljen nalog',
        'spreman_za_utovar' => 'Spreman za utovar',
        'na_utovaru' => 'Na utovaru',
        'u_putu_ka_istovaru' => 'U putu ka istovaru',
        'na_istovaru' => 'Na istovaru',
        'zavrseno' => 'Završeno',
        'problem' => 'Problem'
    ];
    return $statusLabels[$status] ?? $status;
}

function getStatusColor($status) {
    switch($status) {
        case 'primljen_nalog': return 'secondary';
        case 'spreman_za_utovar': return 'primary';
        case 'na_utovaru': return 'info';
        case 'u_putu_ka_istovaru': return 'warning';
        case 'na_istovaru': return 'warning';
        case 'zavrseno': return 'success';
        case 'problem': return 'danger';
        default: return 'secondary';
    }
}

function getStatusHexColor($status) {
    $colors = [
        'primljen_nalog' => '#3b82f6',
        'spreman_za_utovar' => '#0ea5e9', 
        'na_utovaru' => '#f59e0b',
        'u_putu_ka_istovaru' => '#f97316',
        'na_istovaru' => '#eab308',
        'zavrseno' => '#22c55e',
        'problem' => '#ef4444',
        // Support for short status names as well
        'primljen' => '#3b82f6',
        'spreman' => '#0ea5e9',
        'utovar' => '#f59e0b',
        'put' => '#f97316', 
        'istovar' => '#eab308',
        'zavrseno' => '#22c55e',
        'problem' => '#ef4444'
    ];
    return $colors[$status] ?? '#6b7280';
}

function getStatusBadgeClass($status) {
    switch($status) {
        case 'primljen_nalog': return 'primljen';
        case 'spreman_za_utovar': return 'spreman';
        case 'na_utovaru': return 'utovar';
        case 'u_putu_ka_istovaru': return 'put';
        case 'na_istovaru': return 'istovar';
        case 'zavrseno': return 'zavrseno';
        case 'problem': return 'problem';
        default: return 'primljen';
    }
}

function calculateEstimatedKmForTour($tourId, $unloadingText) {
    global $pdo;

    if (empty($unloadingText)) {
        return 0;
    }

    // Parse unloading locations using same logic as map_vehicle.php
    $raw_points = [];
    $parts = preg_split('/[;,\n]+/', $unloadingText);
    foreach ($parts as $p) { 
        $p = trim($p); 
        if ($p !== '') $raw_points[] = $p; 
    }

    // Normalize codes: remove "TMO", extract 6 digits (3+3)
    $codes = [];
    foreach ($raw_points as $p) {
        $p = preg_replace('/^\s*TMO\s*/i', '', $p);
        $p = preg_replace('/\s+/', ' ', $p);
        if (preg_match('/(\d{3})\D*(\d{3})/', $p, $m)) {
            $digits = $m[1] . $m[2];
            $codes[] = $digits;
        }
    }
    $codes = array_values(array_unique($codes));

    if (empty($codes)) {
        return 0;
    }

    // Get object coordinates from database
    $stmt = $pdo->prepare("SELECT sifra, lat, lng FROM objekti WHERE sifra IN (" . implode(',', array_fill(0, count($codes), '?')) . ")");
    $stmt->execute($codes);
    $objects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $markers = [];
    foreach ($objects as $obj) {
        $markers[] = [
            'lat' => (float)$obj['lat'],
            'lng' => (float)$obj['lng']
        ];
    }

    if (empty($markers)) {
        return 0;
    }

    // Nova Pazova warehouse coordinates
    $warehouseCoords = [44.971966938665076, 20.228534192549727];

    // Create route: Nova Pazova -> all markers -> Nova Pazova
    $waypoints = [$warehouseCoords];
    foreach ($markers as $marker) {
        $waypoints[] = [$marker['lat'], $marker['lng']];
    }
    $waypoints[] = $warehouseCoords;

    // OSRM API call
    $coordinates = array_map(function($w) { return $w[1] . ',' . $w[0]; }, $waypoints);
    $osrmUrl = 'https://router.project-osrm.org/route/v1/driving/' . implode(';', $coordinates) . '?overview=false';

    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($osrmUrl, false, $context);
    if ($response === false) {
        return 0;
    }

    $data = json_decode($response, true);
    if (!isset($data['routes'][0]['distance'])) {
        return 0;
    }

    return round($data['routes'][0]['distance'] / 1000); // Convert to kilometers
}

// Purchase Order functions
function generatePurchaseOrderNumber() {
    global $pdo;
    
    // Generate unique 10-digit number
    do {
        $number = str_pad(mt_rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
        
        // Check if number already exists
        $stmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE order_number = ?");
        $stmt->execute([$number]);
        $exists = $stmt->fetch();
    } while ($exists);
    
    return $number;
}

function createPurchaseOrder($orderNumber = null, $isManual = false, $createdBy = 'System') {
    global $pdo;
    
    if (!$orderNumber) {
        $orderNumber = generatePurchaseOrderNumber();
    }
    
    $stmt = $pdo->prepare("INSERT INTO purchase_orders (order_number, is_manual, created_by) VALUES (?, ?, ?)");
    $stmt->execute([$orderNumber, $isManual, $createdBy]);
    
    return $pdo->lastInsertId();
}

function addToursTosPurchaseOrder($purchaseOrderId, $tourIds) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        foreach ($tourIds as $tourId) {
            // Insert into junction table
            $stmt = $pdo->prepare("INSERT IGNORE INTO purchase_order_tours (purchase_order_id, tour_id) VALUES (?, ?)");
            $stmt->execute([$purchaseOrderId, $tourId]);
            
            // Update tour with purchase_order_id
            $stmt = $pdo->prepare("UPDATE tours SET purchase_order_id = ? WHERE id = ?");
            $stmt->execute([$purchaseOrderId, $tourId]);
        }
        
        // Update total tours count
        $stmt = $pdo->prepare("UPDATE purchase_orders SET total_tours = (SELECT COUNT(*) FROM purchase_order_tours WHERE purchase_order_id = ?) WHERE id = ?");
        $stmt->execute([$purchaseOrderId, $purchaseOrderId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function removeTourFromPurchaseOrder($purchaseOrderId, $tourId) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Remove from junction table
        $stmt = $pdo->prepare("DELETE FROM purchase_order_tours WHERE purchase_order_id = ? AND tour_id = ?");
        $stmt->execute([$purchaseOrderId, $tourId]);
        
        // Remove purchase_order_id from tour
        $stmt = $pdo->prepare("UPDATE tours SET purchase_order_id = NULL WHERE id = ?");
        $stmt->execute([$tourId]);
        
        // Update total tours count
        $stmt = $pdo->prepare("UPDATE purchase_orders SET total_tours = (SELECT COUNT(*) FROM purchase_order_tours WHERE purchase_order_id = ?) WHERE id = ?");
        $stmt->execute([$purchaseOrderId, $purchaseOrderId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

function getPurchaseOrders() {
    global $pdo;
    
    $stmt = $pdo->query("SELECT * FROM purchase_orders ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function getPurchaseOrderDetails($purchaseOrderId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT po.*, 
               GROUP_CONCAT(t.id) as tour_ids,
               GROUP_CONCAT(CONCAT(t.id, ':', t.loading_loc, ' → ', t.unloading_loc) SEPARATOR '|') as tour_details
        FROM purchase_orders po
        LEFT JOIN purchase_order_tours pot ON po.id = pot.purchase_order_id  
        LEFT JOIN tours t ON pot.tour_id = t.id
        WHERE po.id = ?
        GROUP BY po.id
    ");
    $stmt->execute([$purchaseOrderId]);
    return $stmt->fetch();
}

function getToursForPurchaseOrder($filters = []) {
    global $pdo;
    
    $sql = "SELECT t.*, 
                   d.name AS driver, 
                   v.plate AS vehicle,
                   c.name AS client_name,
                   t.waybill_number,
                   po.order_number as purchase_order_number
            FROM tours t
            LEFT JOIN drivers d ON t.driver_id = d.id
            LEFT JOIN vehicles v ON t.vehicle_id = v.id
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN purchase_orders po ON t.purchase_order_id = po.id
            WHERE 1=1";
    
    $params = [];
    
    // Date range filter
    if (!empty($filters['from_date'])) {
        $sql .= " AND DATE(t.date) >= ?";
        $params[] = $filters['from_date'];
    }
    
    if (!empty($filters['to_date'])) {
        $sql .= " AND DATE(t.date) <= ?";
        $params[] = $filters['to_date'];
    }
    
    // Date period filter (1-15 or 16-31)
    if (!empty($filters['date_period'])) {
        if ($filters['date_period'] == 'first_half') {
            $sql .= " AND DAY(t.date) BETWEEN 1 AND 15";
        } elseif ($filters['date_period'] == 'second_half') {
            $sql .= " AND DAY(t.date) BETWEEN 16 AND 31";
        }
    }
    
    // Filter by purchase order status
    if (isset($filters['has_purchase_order'])) {
        if ($filters['has_purchase_order'] === false) {
            $sql .= " AND t.purchase_order_id IS NULL";
        } else {
            $sql .= " AND t.purchase_order_id IS NOT NULL";
        }
    }
    
    // Filter by client
    if (!empty($filters['client_id'])) {
        $sql .= " AND t.client_id = ?";
        $params[] = $filters['client_id'];
    }
    
    $sql .= " ORDER BY t.date DESC, t.id DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function deletePurchaseOrder($purchaseOrderId) {
    global $pdo;
    
    // Validate input
    $purchaseOrderId = (int)$purchaseOrderId;
    if ($purchaseOrderId <= 0) {
        return false;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Check if purchase order exists
        $stmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE id = ?");
        $stmt->execute([$purchaseOrderId]);
        if (!$stmt->fetch()) {
            $pdo->rollBack();
            return false;
        }
        
        // Remove purchase_order_id from tours (detach tours)
        $stmt = $pdo->prepare("UPDATE tours SET purchase_order_id = NULL WHERE purchase_order_id = ?");
        $stmt->execute([$purchaseOrderId]);
        
        // Delete from junction table
        $stmt = $pdo->prepare("DELETE FROM purchase_order_tours WHERE purchase_order_id = ?");
        $stmt->execute([$purchaseOrderId]);
        
        // Delete purchase order
        $stmt = $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?");
        $stmt->execute([$purchaseOrderId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Delete purchase order error: " . $e->getMessage());
        return false;
    }
}

function updatePurchaseOrderNumber($purchaseOrderId, $newOrderNumber) {
    global $pdo;
    
    // Validate inputs
    $purchaseOrderId = (int)$purchaseOrderId;
    if ($purchaseOrderId <= 0 || !preg_match('/^\d{10}$/', $newOrderNumber)) {
        return false;
    }
    
    try {
        // Check if purchase order exists
        $stmt = $pdo->prepare("SELECT id FROM purchase_orders WHERE id = ?");
        $stmt->execute([$purchaseOrderId]);
        if (!$stmt->fetch()) {
            return false;
        }
        
        $stmt = $pdo->prepare("UPDATE purchase_orders SET order_number = ? WHERE id = ?");
        $stmt->execute([$newOrderNumber, $purchaseOrderId]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Update purchase order error: " . $e->getMessage());
        return false;
    }
}

/**
 * Estimate distance for a tour using client-specific logic
 */
function estimateTourDistance($clientId, $loadingLoc, $unloadingLoc) {
    global $pdo;
    
    try {
        $calculator = new ClientRouteCalculator($pdo);
        
        // Create temporary tour data for calculation
        $tourData = [
            'client_id' => $clientId,
            'loading_loc' => $loadingLoc,
            'unloading_loc' => $unloadingLoc
        ];
        
        // Get client configuration - check if columns exist first
        $clientConfig = null;
        if ($clientId) {
            try {
                // Check if route_calculation_type column exists
                $stmt = $pdo->query("SHOW COLUMNS FROM clients LIKE 'route_calculation_type'");
                $columnExists = $stmt->fetchColumn();
                
                if ($columnExists) {
                    $stmt = $pdo->prepare("SELECT route_calculation_type, base_latitude, base_longitude, base_location_name FROM clients WHERE id = ?");
                    $stmt->execute([$clientId]);
                    $clientConfig = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    // Fallback: just get basic client info and use standard calculation
                    $stmt = $pdo->prepare("SELECT id, name FROM clients WHERE id = ?");
                    $stmt->execute([$clientId]);
                    $client = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($client) {
                        // Determine calculation type based on client name
                        $clientName = strtolower($client['name']);
                        if (strpos($clientName, 'delta') !== false || strpos($clientName, 'transporti') !== false) {
                            $clientConfig = ['route_calculation_type' => 'delta_transporti'];
                        } elseif (strpos($clientName, 'milšped') !== false || strpos($clientName, 'milsped') !== false) {
                            $clientConfig = ['route_calculation_type' => 'milsped_doo'];
                        } else {
                            $clientConfig = ['route_calculation_type' => 'standard'];
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("Client config error: " . $e->getMessage());
                $clientConfig = ['route_calculation_type' => 'standard'];
            }
        }
        
        $calculationType = $clientConfig['route_calculation_type'] ?? 'standard';
        
        switch ($calculationType) {
            case 'delta_transporti':
                return $calculator->calculateDeltaTransportiRoute(array_merge($tourData, $clientConfig));
                
            case 'milsped_doo':
                return $calculator->calculateMilspedRoute(array_merge($tourData, $clientConfig));
                
            default:
                return $calculator->calculateStandardRoute($tourData);
        }
        
    } catch (Exception $e) {
        error_log("Distance estimation error: " . $e->getMessage());
        return [
            'distance_km' => 0,
            'duration_minutes' => 0,
            'calculation_type' => 'error',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Automatically calculate tour distance and update the tour
 */
function autoCalculateTourDistance($tourId) {
    global $pdo;
    
    try {
        $calculator = new ClientRouteCalculator($pdo);
        $result = $calculator->calculateTourDistance($tourId);
        
        // Update tour with calculated distance
        if ($result['distance_km'] > 0) {
            $stmt = $pdo->prepare("UPDATE tours SET km = ?, estimated_km = ? WHERE id = ?");
            $stmt->execute([
                $result['distance_km'],
                $result['distance_km'],
                $tourId
            ]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Auto calculate distance error: " . $e->getMessage());
        return [
            'distance_km' => 0,
            'calculation_type' => 'error',
            'error' => $e->getMessage()
        ];
    }
}

/**
 * AJAX endpoint for real-time distance estimation
 */
function ajaxDistanceEstimate() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $clientId = $_POST['client_id'] ?? null;
    $loadingLoc = $_POST['loading_loc'] ?? '';
    $unloadingLoc = $_POST['unloading_loc'] ?? '';
    
    if (empty($loadingLoc) || empty($unloadingLoc)) {
        echo json_encode([
            'error' => 'Loading and unloading locations are required',
            'distance_km' => 0
        ]);
        return;
    }
    
    $result = estimateTourDistance($clientId, $loadingLoc, $unloadingLoc);
    
    header('Content-Type: application/json');
    echo json_encode($result);
}

// Handle AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'estimate_distance') {
    ajaxDistanceEstimate();
    exit;
}
?>