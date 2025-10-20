
<?php
require __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query("
        SELECT id, sifra, naziv, adresa, grad, lat, lng 
        FROM objekti 
        ORDER BY 
            CASE WHEN lat IS NOT NULL AND lng IS NOT NULL THEN 0 ELSE 1 END,
            grad ASC, 
            naziv ASC, 
            sifra ASC
    ");
    
    $objects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convert lat/lng to numbers where they exist
    foreach ($objects as &$obj) {
        if ($obj['lat'] !== null && $obj['lng'] !== null) {
            $obj['lat'] = (float)$obj['lat'];
            $obj['lng'] = (float)$obj['lng'];
        }
    }
    
    echo json_encode([
        'ok' => true,
        'objects' => $objects,
        'count' => count($objects)
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'Greška pri učitavanju objekata: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
