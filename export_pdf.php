<?php
// NO OUTPUT before this point!
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
use Dompdf\Dompdf;
require 'config.php';
require 'functions.php';

date_default_timezone_set('Europe/Belgrade');

if (empty($_GET['id'])) {
    die('Nije definisana tura za export.');
}
$id = intval($_GET['id']);
$tours = getTours(['id' => $id]);
$t = $tours[0] ?? null;
if (!$t) {
    die('Tura nije pronađena.');
}

$driverSafe = str_replace(' ', '_', $t['driver']);
$vehicleSafe = str_replace(' ', '_', $t['vehicle']);
$filename = "$driverSafe" . "_" . "$vehicleSafe.pdf";

$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
body { font-family: "DejaVu Sans", Arial, sans-serif; margin:20px; color:#333; font-size: 12px; }
.header { text-align:center; margin-bottom:15px; border-bottom: 2px solid #333; padding-bottom: 10px; }
.header .company { font-size:16px; font-weight:bold; margin-bottom: 5px; }
.header .address, .header .ids { font-size:11px; color: #555; }
.title { text-align:center; font-size:18px; font-weight:bold; margin:20px 0; text-transform: uppercase; }
.card { border:1px solid #333; padding:15px; margin-bottom:20px; }
.card .table { width:100%; border-collapse:collapse; margin-bottom:0; }
.card .table td { padding:8px; border-bottom:1px solid #ddd; vertical-align: top; }
.card .table tr:last-child td { border-bottom:none; }
.card .field { width:35%; font-weight:bold; background-color: #f5f5f5; }
.card .value { width:65%; }
.footer { position:absolute; bottom:20px; right:20px; font-size:9px; color:#666; }
</style></head><body>';

$html .= '<div class="header">
    <div class="company">VAŠ POTRČKO DOO BEOGRAD</div>
    <div class="address">Aleksandra Stamboliškog 6A</div>
    <div class="ids">PIB 107418055 | MB 20798513</div>
</div>';
$html .= '<div class="title">NALOG ZA UTOVAR</div>';
$html .= '<div class="card"><table class="table">';
$rows = [
    ['Šifra ture', $t['id']],
    ['ORS ID', $t['ors_id']],
    ['Vozač', $t['driver']],
    ['Vozilo', $t['vehicle']],
    ['Datum', $t['date']],
    ['Vreme utovara', $t['loading_time']],
    ['Mesto utovara', $t['loading_loc']],
    ['Istovar', $t['unloading_loc']],
    ['Tip isporuke', $t['delivery_type']],
    ['Relacija', $t['relation'] ?? ''],
    ['Napomena', $t['note'] ?? '']
];
foreach ($rows as $row) {
    $html .= '<tr><td class="field">'.htmlspecialchars($row[0]).'</td><td class="value">'.htmlspecialchars($row[1]).'</td></tr>';
}
$html .= '</table></div>';
$printDate = date('d.m.Y. H:i');
$html .= '<div class="footer">Datum štampe: ' . $printDate . '</div>';
$html .= '</body></html>';

// Clear any output buffer
while (ob_get_level()) {
    ob_end_clean();
}

// Create Dompdf with options
$options = new \Dompdf\Options();
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isFontSubsettingEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4','portrait');
$dompdf->render();

// Use dompdf's stream method which handles headers properly
$dompdf->stream($filename, array("Attachment" => false));
exit;
?>