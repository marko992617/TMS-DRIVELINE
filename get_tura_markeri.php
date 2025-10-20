<?php
// get_tura_markeri.php
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

$tura_id = isset($_GET['tura_id']) ? (int)$_GET['tura_id'] : 0;
if ($tura_id<=0) {
    echo json_encode(['ok'=>false, 'error'=>'Parametar tura_id je obavezan.']); exit;
}

$sql = "SELECT ts.redosled, o.sifra, o.naziv, o.adresa, o.grad, o.lat, o.lng
        FROM ture_stavke ts
        JOIN objekti o ON o.sifra = ts.sifra_objekta
        WHERE ts.tura_id = :tid
        ORDER BY COALESCE(ts.redosled, 999999), o.naziv, o.sifra";
$stmt = $pdo->prepare($sql);
$stmt->execute([':tid'=>$tura_id]);
$rows = $stmt->fetchAll();

$markers = []; $missing = [];
foreach ($rows as $r) {
    $label = trim(($r['naziv']??'')!=='' ? $r['naziv'] : $r['sifra']);
    $addr = trim($r['adresa'] . ( ($r['grad']??'') ? ', '.$r['grad'] : '' ));
    if ($r['lat'] !== null && $r['lng'] !== null) {
        $markers[] = [
            'title' => $label,
            'addr' => $addr,
            'lat' => (float)$r['lat'],
            'lng' => (float)$r['lng'],
            'sifra' => $r['sifra'],
        ];
    } else {
        $missing[] = [
            'title' => $label,
            'addr' => $addr,
            'sifra'=> $r['sifra'],
        ];
    }
}
echo json_encode(['ok'=>true, 'markers'=>$markers, 'missing'=>$missing], JSON_UNESCAPED_UNICODE);