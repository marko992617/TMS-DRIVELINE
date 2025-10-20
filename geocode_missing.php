<?php
// geocode_missing.php
require __DIR__ . '/db.php';

$COUNTRY = 'Serbia';
$RATE_DELAY_MS = 1500;
$USER_AGENT = 'DrivelineTMS/1.0 (contact: you@example.com)';
$ERROR_LOG = __DIR__ . '/geocode_errors.log';

function geocode($q, $ua) {
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($q);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => [ 'User-Agent: ' . $ua ],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$resp) return null;
    $arr = json_decode($resp, true);
    if (!is_array($arr) || empty($arr)) return null;
    return ['lat' => (float)$arr[0]['lat'], 'lng' => (float)$arr[0]['lon']];
}

$q = $pdo->query("SELECT id, sifra, naziv, adresa, grad FROM objekti WHERE lat IS NULL OR lng IS NULL ORDER BY id ASC LIMIT 50");
$rows = $q->fetchAll();
$upd = $pdo->prepare("UPDATE objekti SET lat=:lat, lng=:lng WHERE id=:id");

$done = 0; $ok = 0; $fail = 0;
foreach ($rows as $r) {
    $full = trim($r['adresa'] . ', ' . $r['grad'] . ', ' . $COUNTRY);
    if ($full === ', , ' . $COUNTRY) {
        $fail++; $done++;
        file_put_contents($ERROR_LOG, "Prazna adresa za ID: {$r['id']}
", FILE_APPEND);
        continue;
    }
    $g = geocode($full, $USER_AGENT);
    if ($g) {
        $upd->execute([':lat' => $g['lat'], ':lng' => $g['lng'], ':id' => $r['id']]);
        $ok++;
    } else {
        $fail++;
        file_put_contents($ERROR_LOG, "Neuspešno: {$full} (ID: {$r['id']})
", FILE_APPEND);
    }
    $done++;
    usleep($RATE_DELAY_MS * 1000);
}

header('Refresh: 5; URL=geocode_missing.php'); // Auto-refresh za sledećih 50
header('Content-Type: text/plain; charset=utf-8');
echo "Obrađeno: $done
Uspešno ažurirano: $ok
Neuspešnih: $fail
";
echo "Osvežavanje stranice za sledećih 50 u 5 sekundi...";
?>
