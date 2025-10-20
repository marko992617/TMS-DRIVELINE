<?php
// get_vehicle_tours.php — vrati liste tura za (date, plate)
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$date = isset($_GET['date']) ? $_GET['date'] : null;
$plate = isset($_GET['plate']) ? $_GET['plate'] : null;
if (!$date || !$plate) { echo json_encode(['ok'=>false, 'error'=>'Parametri date i plate su obavezni.']); exit; }

try {
    // Proveri da li postoje tabele/kolone
    $hasVehicles = false;
    try { $r = $pdo->query("SHOW TABLES LIKE 'vehicles'"); $hasVehicles = (bool)$r->fetchColumn(); } catch (Throwable $e) {}
    if ($hasVehicles) {
        $sql = "SELECT t.id, t.unloading_loc, t.ors_id, t.delivery_type, t.loading_time, d.name as driver_name
                FROM tours t
                JOIN vehicles v ON v.id = t.vehicle_id
                LEFT JOIN drivers d ON d.id = t.driver_id
                WHERE t.`date` = :d AND v.plate = :p
                ORDER BY t.loading_time, t.id";
        $st = $pdo->prepare($sql);
        $st->execute([':d'=>$date, ':p'=>$plate]);
    } else {
        // Fallback: tours.vehicle_plate
        $sql = "SELECT id, unloading_loc, ors_id, delivery_type, loading_time, 
                       (SELECT name FROM drivers WHERE id = driver_id) as driver_name
                FROM tours
                WHERE `date` = :d AND vehicle_plate = :p
                ORDER BY loading_time, id";
        $st = $pdo->prepare($sql);
        $st->execute([':d'=>$date, ':p'=>$plate]);
    }

    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $out = [];
    foreach ($rows as $r) {
        $cnt = 0;
        if (!empty($r['unloading_loc'])) {
            $parts = preg_split('/[;,\n]+/', $r['unloading_loc']);
            foreach ($parts as $p) { if (trim($p) !== '') $cnt++; }
        }
        $loadingTime = null;
        if (!empty($r['loading_time'])) {
            $loadingTime = date('H:i', strtotime($r['loading_time']));
        }
        
        $out[] = [
            'id' => (int)$r['id'],
            'ors_id' => $r['ors_id'] ?? null,
            'delivery_type' => $r['delivery_type'] ?? null,
            'driver_name' => $r['driver_name'] ?? null,
            'loading_time' => $loadingTime,
            'count_points' => $cnt
        ];
    }
    echo json_encode(['ok'=>true, 'tours'=>$out], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
