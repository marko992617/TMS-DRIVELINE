<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
use Dompdf\Dompdf;

require 'config.php';
require 'functions.php';

date_default_timezone_set('Europe/Belgrade');

// Retrieve parameters
$driver = $_REQUEST['driver'] ?? '';
$from   = $_REQUEST['from'] ?? '';
$to     = $_REQUEST['to'] ?? $from;

// Validate
if (!$driver) {
    die('Nije definisan vozač za slanje.');
}

// Fetch tours
$filters = ['driver'=>$driver,'from'=>$from,'to'=>$to];
$tours = getTours($filters);
if (empty($tours)) {
    die('Nema tura za poslati.');
}

// Sort by loading_time
usort($tours, function($a, $b){
    return strtotime($a['loading_time']) <=> strtotime($b['loading_time']);
});

// Prepare filename
$driverSafe = str_replace([' ', 'Š','Ć','Đ','š','ć','đ'], ['_','S','C','D','s','c','d'], $driver);
$vehicleSafe = str_replace(' ', '_', $tours[0]['vehicle']);
$filename = "{$driverSafe}_{$vehicleSafe}.pdf";

// Build HTML
$html = '<!DOCTYPE html><html lang="sr"><head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, sans-serif; margin:20px; color:#333; position:relative; }
.header { text-align:center; margin-bottom:15px; }
.header .company { font-size:16px; font-weight:bold; }
.header .address, .header .ids { font-size:12px; }
.title { text-align:center; font-size:18px; font-weight:bold; margin-bottom:20px; }
.info { margin-bottom:20px; }
.info div { margin:3px 0; }
.cards-container { display:flex; flex-wrap: wrap; gap:20px; justify-content: space-between; }
.card { border:1px solid #aaa; border-radius:4px; padding:12px; width:48%; box-sizing:border-box; }
.card .table { width:100%; border-collapse:collapse; }
.card .table td { padding:6px 8px; border-bottom:1px solid #ccc; }
.card .table tr:last-child td { border-bottom:none; }
.card .field { width:35%; font-weight:bold; }
.card .value { width:65%; }
.footer { position:absolute; bottom:20px; right:20px; font-size:10px; color:#666; }
</style></head><body>';

// Header & info
$html .= '<div class="header">
    <div class="company">VAŠ POTRČKO DOO BEOGRAD</div>
    <div class="address">Aleksandra Stamboliškog 6A</div>
    <div class="ids">PIB 107418055 | MB 20798513</div>
</div>';
$html .= '<div class="title">NALOG ZA UTOVAR</div>';
$html .= '<div class="info">
    <div><strong>Vozač:</strong> '. htmlspecialchars($driver) .'</div>
    <div><strong>Datum:</strong> '. htmlspecialchars($from) . ($to && $to !== $from ? ' - '. htmlspecialchars($to) : '') .'</div>
</div>';

// Cards container
$html .= '<div class="cards-container">';
foreach ($tours as $t) {
    $html .= '<div class="card"><table class="table">';
    $rows = [
        ['Šifra ture', $t['id']],
        ['ORS ID', $t['ors_id']],
        ['Datum', $t['date']],
        ['Vreme utovara', $t['loading_time']],
        ['Mesto utovara', $t['loading_loc']],
        ['Istovar', $t['unloading_loc']],
        ['Tip isporuke', $t['delivery_type']],
        ['Relacija', $t['relation'] ?? ''],
        ['Napomena', $t['note'] ?? '']
    ];
    foreach ($rows as $row) {
        $html .= '<tr><td class="field">'. htmlspecialchars($row[0]) .'</td><td class="value">'. htmlspecialchars($row[1]) .'</td></tr>';
    }
    $html .= '</table></div>';
}
$html .= '</div>'; // end cards-container

// Footer
$printDate = date('d.m.Y. H:i');
$html .= '<div class="footer">Datum štampe: '. $printDate .'</div>';

$html .= '</body></html>';

// Render PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4','portrait');
$dompdf->render();
$dompdf->stream($filename, ['Attachment'=>false]);
exit;
?>