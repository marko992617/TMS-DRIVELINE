
<?php
// get_tour_loading_time.php — dohvata vreme utovara za konkretnu turu
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : 0;
if ($tour_id <= 0) { 
    echo json_encode(['ok'=>false, 'error'=>'tour_id je obavezan.']); 
    exit; 
}

try {
    $stmt = $pdo->prepare("SELECT loading_time FROM tours WHERE id = :id");
    $stmt->execute([':id'=>$tour_id]);
    $loading_time = $stmt->fetchColumn();
    
    if ($loading_time === false) {
        echo json_encode(['ok'=>false, 'error'=>'Tura nije pronađena.']);
        exit;
    }
    
    // Formatiranje vremena u HH:MM format
    $formatted_time = date('H:i', strtotime($loading_time));
    
    echo json_encode([
        'ok' => true, 
        'loading_time' => $formatted_time
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
?>
