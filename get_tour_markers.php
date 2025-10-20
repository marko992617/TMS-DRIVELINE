<?php
// get_tour_markers.php — markeri (objekti) za konkretnu turu
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$tour_id = isset($_GET['tour_id']) ? (int)$_GET['tour_id'] : 0;
if ($tour_id <= 0) { echo json_encode(['ok'=>false, 'error'=>'tour_id je obavezan.']); exit; }

try {
    $stmt = $pdo->prepare("SELECT unloading_loc FROM tours WHERE id = :id");
    $stmt->execute([':id'=>$tour_id]);
    $txt = (string)$stmt->fetchColumn();

    if ($txt === '') { echo json_encode(['ok'=>true, 'markers'=>[], 'missing'=>[]]); exit; }

    // Parsiranje istovara
    $raw_points = [];
    $parts = preg_split('/[;,\n]+/', $txt);
    foreach ($parts as $p) { $p = trim($p); if ($p!=='') $raw_points[] = $p; }

    // Normalizacija šifre: ukloni "TMO", izvuci 6 cifara (3+3)
    $codes = [];
    $labelByDigits = [];
    foreach ($raw_points as $p) {
        $orig = $p;
        $p = preg_replace('/^\s*TMO\s*/i', '', $p);
        $p = preg_replace('/\s+/', ' ', $p);
        if (preg_match('/(\d{3})\D*(\d{3})/', $p, $m)) {
            $digits = $m[1] . $m[2];
            $codes[] = $digits;
            if (!isset($labelByDigits[$digits])) $labelByDigits[$digits] = trim($orig);
        }
    }
    $codes = array_values(array_unique($codes));
    if (!$codes) { echo json_encode(['ok'=>true, 'markers'=>[], 'missing'=>[]]); exit; }

    // Učitavanje objekata prema kanonskoj sifri bez znakova
    $placeholders = implode(',', array_fill(0, count($codes), '?'));
    $sql2 = "SELECT id, sifra, naziv, adresa, grad, lat, lng,
                    REPLACE(REPLACE(REPLACE(sifra,'-',''),' ',''), '/', '') AS canon
             FROM objekti
             WHERE REPLACE(REPLACE(REPLACE(sifra,'-',''),' ',''), '/', '') IN ($placeholders)";
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($codes);
    $found = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $objByCanon = [];
    foreach ($found as $f) {
        $canon = preg_replace('/[^0-9]/', '', $f['sifra']);
        $objByCanon[$canon] = $f;
    }

    $markers = []; $missing = [];
    foreach ($codes as $digits) {
        if (isset($objByCanon[$digits])) {
            $o = $objByCanon[$digits];
            $title = $o['naziv'] ?: $o['sifra'];
            $addr = trim(($o['adresa'] ?? '') . ( ($o['grad'] ?? '') ? ', '.$o['grad'] : '' ));
            if ($o['lat']!==null && $o['lng']!==null && $o['lat']!=='' && $o['lng']!=='') {
                $markers[] = [
                    'title' => $title,
                    'addr'  => $addr,
                    'sifra' => $o['sifra'],
                    'lat'   => (float)$o['lat'],
                    'lng'   => (float)$o['lng'],
                    'info'  => null,
                ];
            } else {
                $missing[] = ['title'=>$title, 'addr'=>$addr, 'sifra'=>$o['sifra']];
            }
        } else {
            $lbl = $labelByDigits[$digits] ?? $digits;
            $missing[] = ['title'=>$lbl, 'addr'=>'', 'sifra'=>$lbl];
        }
    }

    echo json_encode(['ok'=>true, 'markers'=>$markers, 'missing'=>$missing], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
