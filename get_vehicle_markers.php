<?php
// get_vehicle_markers.php — prilagođeno za tours.date, tours.vehicle_id, tours.unloading_loc
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$date = isset($_GET['date']) ? $_GET['date'] : null;
$vehicle_id = isset($_GET['vehicle_id']) ? $_GET['vehicle_id'] : null;
if (!$date || $vehicle_id===null || $vehicle_id==='') {
    echo json_encode(['ok'=>false, 'error'=>'Parametri date i vehicle_id su obavezni.']); exit;
}

// Učitaj string mesta istovara za traženi datum i vozilo
$stmt = $pdo->prepare("SELECT unloading_loc FROM tours WHERE `date` = :d AND vehicle_id = :v");
$stmt->execute([':d'=>$date, ':v'=>$vehicle_id]);
$rows = $stmt->fetchAll();

if (!$rows) { echo json_encode(['ok'=>true, 'markers'=>[], 'missing'=>[]]); exit; }

// Parsiraj sve istovare iz svih tura tog dana/vozila
$raw_points = [];
foreach ($rows as $r) {
    $txt = (string)($r['unloading_loc'] ?? '');
    if ($txt==='') continue;
    $parts = preg_split('/[;,\n]+/', $txt);
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p!=='') $raw_points[] = $p;
    }
}
if (!$raw_points) { echo json_encode(['ok'=>true, 'markers'=>[], 'missing'=>[]]); exit; }

// Normalizacija šifre: skini "TMO", izvuci 6 cifara (3+3)
$codes = [];
$labelByDigits = [];
foreach ($raw_points as $p) {
    $orig = $p;
    $p = preg_replace('/^\s*TMO\s*/i', '', $p);
    $p = preg_replace('/\s+/', ' ', $p);
    if (preg_match('/(\d{3})\D*(\d{3})/', $p, $m)) {
        $digits = $m[1] . $m[2]; // npr 213450
        $codes[] = $digits;
        if (!isset($labelByDigits[$digits])) $labelByDigits[$digits] = trim($orig);
    }
}
$codes = array_values(array_unique($codes));
if (!$codes) { echo json_encode(['ok'=>true, 'markers'=>[], 'missing'=>[]]); exit; }

// Dohvati objekte prema kanonskoj šifri (remove '-', ' ', '/')
$placeholders = implode(',', array_fill(0, count($codes), '?'));
$sql2 = "SELECT id, sifra, naziv, adresa, grad, lat, lng,
                REPLACE(REPLACE(REPLACE(sifra,'-',''),' ',''), '/', '') AS canon
         FROM objekti
         WHERE REPLACE(REPLACE(REPLACE(sifra,'-',''),' ',''), '/', '') IN ($placeholders)";
$stmt2 = $pdo->prepare($sql2);
$stmt2->execute($codes);
$found = $stmt2->fetchAll();

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
        $addr = trim($o['adresa'] . ( $o['grad'] ? ', '.$o['grad'] : '' ));
        if ($o['lat']!==null && $o['lng']!==null) {
            $markers[] = [
                'title' => $title,
                'addr'  => $addr,
                'sifra' => $o['sifra'],
                'lat'   => (float)$o['lat'],
                'lng'   => (float)$o['lng'],
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