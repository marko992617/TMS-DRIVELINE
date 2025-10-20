
<?php
header('Content-Type: application/json; charset=utf-8');
require 'config.php';

$code = $_POST['object_code'] ?? $_GET['code'] ?? '';

if (empty($code)) {
    echo json_encode(['ok' => false, 'error' => 'Kod objekta je obavezan']);
    exit;
}

try {
    // Clean the code - remove TMO and normalize
    $cleanCode = preg_replace('/^\s*TMO\s*/i', '', $code);
    $cleanCode = trim($cleanCode);
    
    // Try to find object by exact match first
    $stmt = $pdo->prepare("SELECT sifra, naziv, adresa, grad, lat, lng FROM objekti WHERE sifra = ?");
    $stmt->execute([$cleanCode]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not found, try pattern matching for codes like "213 514"
    if (!$result && preg_match('/(\d{3})\s*(\d{3})/', $cleanCode, $matches)) {
        $pattern1 = $matches[1] . ' ' . $matches[2];  // "213 514"
        $pattern2 = $matches[1] . '-' . $matches[2];  // "213-514"
        $pattern3 = $matches[1] . $matches[2];        // "213514"
        
        $stmt = $pdo->prepare("SELECT sifra, naziv, adresa, grad, lat, lng FROM objekti 
                               WHERE sifra IN (?, ?, ?) 
                               OR sifra LIKE ? OR sifra LIKE ?
                               LIMIT 1");
        $stmt->execute([
            $pattern1, 
            $pattern2, 
            $pattern3,
            "%{$pattern1}%",
            "%{$pattern2}%"
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($result && !empty($result['lat']) && !empty($result['lng'])) {
        echo json_encode([
            'ok' => true,
            'sifra' => $result['sifra'],
            'naziv' => $result['naziv'],
            'adresa' => $result['adresa'],
            'grad' => $result['grad'],
            'lat' => floatval($result['lat']),
            'lng' => floatval($result['lng'])
        ]);
    } else {
        echo json_encode([
            'ok' => false, 
            'error' => 'Objekat nije pronađen ili nema koordinate',
            'code' => $cleanCode
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'ok' => false, 
        'error' => 'Greška pri pretraživanju: ' . $e->getMessage()
    ]);
}
?>
