<?php
// export_waybill.php — robustan prihvat ID ture i generisanje PDF-a
session_start();
require 'config.php';
require 'vendor/autoload.php';
require_once 'pdf_helpers.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Auth check (prilagodi po potrebi)
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// 1) Pokupi ID iz više mogućih polja (GET/POST), kao int
function fetch_tour_id(): int {
    $candidates = ['id','tour_id','t','tid'];
    foreach ($candidates as $key) {
        if (isset($_GET[$key]))  return (int)$_GET[$key];
        if (isset($_POST[$key])) return (int)$_POST[$key];
    }
    return 0;
}
$tour_id = fetch_tour_id();

if ($tour_id <= 0) {
    http_response_code(400);
    echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">';
    echo '<div class="container p-4"><div class="alert alert-danger">Nedostaje ID ture. Otvorite PDF tako što ćete pozvati <code>export_waybill.php?id=ID_TURE</code></div>';
    echo '<a class="btn btn-secondary" href="admin_driver_reports.php">Nazad</a></div>';
    exit;
}

// 2) Učitaj turu
$stmt = $pdo->prepare("SELECT t.id, t.waybill_number, t.loading_time, d.name AS driver_name, v.plate AS vehicle
                       FROM tours t
                       LEFT JOIN drivers d ON t.driver_id = d.id
                       LEFT JOIN vehicles v ON t.vehicle_id = v.id
                       WHERE t.id = ?");
$stmt->execute([$tour_id]);
$tour = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tour) {
    http_response_code(404);
    echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">';
    echo '<div class="container p-4"><div class="alert alert-warning">Tura nije pronađena.</div>';
    echo '<a class="btn btn-secondary" href="admin_driver_reports.php">Nazad</a></div>';
    exit;
}

$waybill = $tour['waybill_number'] ?: ('tura_'.$tour['id']);
$print_date = $tour['loading_time'] ? date('d.m.Y', strtotime($tour['loading_time'])) : date('d.m.Y');

// 3) Skupi slike (tabele ili fallback folderi)
$images = [];
// driver_tour_files
try {
    $check = $pdo->query("SHOW TABLES LIKE 'driver_tour_files'")->fetchColumn();
    if ($check) {
        $q = $pdo->prepare("SELECT file_path FROM driver_tour_files WHERE tour_id = ? ORDER BY id ASC");
        $q->execute([$tour_id]);
        foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $images[] = $r['file_path'];
    }
} catch (\Throwable $e) {}
// driver_images
if (empty($images)) {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'driver_images'")->fetchColumn();
        if ($check) {
            $q = $pdo->prepare("SELECT path AS file_path FROM driver_images WHERE tour_id = ? ORDER BY id ASC");
            $q->execute([$tour_id]);
            foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) $images[] = $r['file_path'];
        }
    } catch (\Throwable $e) {}
}
// fallback folderi
function scan_dir_images($dir) {
    $out = [];
    if (is_dir($dir)) {
        foreach (scandir($dir) as $fn) {
            if ($fn==='.'||$fn==='..') continue;
            $p = $dir.'/'.$fn;
            $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) $out[] = $p;
        }
        sort($out, SORT_NATURAL);
    }
    return $out;
}
if (empty($images)) $images = scan_dir_images(__DIR__."/uploads/waybills/{$tour_id}");
if (empty($images)) $images = scan_dir_images(__DIR__."/uploads/tours/{$tour_id}");

if (empty($images)) {
    echo '<!doctype html><meta charset="utf-8"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">';
    echo '<div class="container p-4"><div class="alert alert-info">Nema slika za ovu turu.</div>';
    echo '<a class="btn btn-secondary" href="admin_driver_reports.php">Nazad</a></div>';
    exit;
}

// 4) Auto-rotate + HTML
$slides = [];
foreach ($images as $imgPath) {
    if (!file_exists($imgPath)) continue;
    $fixed = fix_image_orientation($imgPath);
    $data = @file_get_contents($fixed);
    if ($data===false) continue;
    $base64 = 'data:image/jpeg;base64,'.base64_encode($data);
    $slides[] = '
      <div class="page">
        <div class="header">
          <div><strong>Broj tovarnog:</strong> '.htmlspecialchars($waybill).'</div>
          <div><strong>Datum:</strong> '.htmlspecialchars($print_date).'</div>
          <div><strong>Vozač:</strong> '.htmlspecialchars($tour['driver_name'] ?? '-').' &nbsp; | &nbsp; <strong>Vozilo:</strong> '.htmlspecialchars($tour['vehicle'] ?? '-').'</div>
        </div>
        <div class="imgwrap"><img src="'.$base64.'" /></div>
      </div>';
    if (strpos($fixed, sys_get_temp_dir())===0 && file_exists($fixed)) @unlink($fixed);
}

$html = '<!doctype html><html><head><meta charset="UTF-8">
<style>
@page { size: A4; margin: 18mm 14mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
.header { margin-bottom: 8px; }
.imgwrap { text-align:center; }
.imgwrap img { max-width: 100%; height: auto; }
.page { page-break-after: always; }
.page:last-child { page-break-after: auto; }
</style>
</head><body>'.implode('', $slides).'</body></html>';

$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$fname = safe_filename($waybill).'.pdf';
$dompdf->stream($fname, ['Attachment' => false]);
