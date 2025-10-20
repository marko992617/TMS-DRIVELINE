<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);
ini_set('memory_limit', '256M');

require 'vendor/autoload.php';
use Dompdf\Dompdf;
require 'config.php';

date_default_timezone_set('Europe/Belgrade');

// Fetch filters (same as report_finance.php)
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$vehicle_id = $_GET['vehicle_id'] ?? '';
$driver_id = $_GET['driver_id'] ?? '';
$client_id = $_GET['client_id'] ?? '';
$month = $_GET['month'] ?? '';

// Fetch fuel price from settings
$stmtSetting = $pdo->prepare("SELECT `value` FROM settings WHERE `name` = ? LIMIT 1");
$stmtSetting->execute(['fuel_price']);
$rowSetting = $stmtSetting->fetch(PDO::FETCH_ASSOC);
$fuelPrice = $rowSetting['value'] ?? 0;

// Build query (same as report_finance.php)
$params = [];
$sql = "SELECT t.id, t.loading_time,
               DATE(t.loading_time) AS date,
               TIME(t.loading_time) AS time,
               t.ors_id, t.delivery_type, t.turnover, t.km as tour_km,
               t.allowance,
               d.name AS driver_name,
               v.plate AS vehicle_plate,
               c.name AS client_name,
               v.fuel_consumption, v.depreciation_per_km,
               ds.waybill_number,
               ds.start_km,
               ds.end_km,
               (ds.end_km - ds.start_km) as driver_km
        FROM tours t
        LEFT JOIN drivers d ON t.driver_id=d.id
        LEFT JOIN vehicles v ON t.vehicle_id=v.id
        LEFT JOIN clients c ON t.client_id=c.id
        LEFT JOIN driver_submissions ds ON ds.tour_id=t.id
        WHERE 1=1";

if ($date_from) {
    $sql .= " AND DATE(t.loading_time) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $sql .= " AND DATE(t.loading_time) <= ?";
    $params[] = $date_to;
}
if ($vehicle_id) {
    $sql .= " AND t.vehicle_id = ?";
    $params[] = $vehicle_id;
}
if ($driver_id) {
    $sql .= " AND t.driver_id = ?";
    $params[] = $driver_id;
}
if ($client_id) {
    $sql .= " AND t.client_id = ?";
    $params[] = $client_id;
}
if ($month) {
    $sql .= " AND DATE_FORMAT(t.loading_time, '%Y-%m') = ?";
    $params[] = $month;
}

$sql .= " ORDER BY t.loading_time DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totalTourKm = 0;
$totalDriverKm = 0;
$totalTurnover = 0;
$totalFuel = 0;
$totalAmort = 0;
$totalAllowance = 0;
$totalContributions = 0;
$totalNet = 0;

$html = '<!DOCTYPE html><html lang="sr"><head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, sans-serif; margin:15px; color:#333; font-size: 10px; }
.header { text-align:center; margin-bottom:15px; }
.header .company { font-size:14px; font-weight:bold; }
.header .address, .header .ids { font-size:10px; }
.title { text-align:center; font-size:16px; font-weight:bold; margin:15px 0; }
.filters { font-size: 9px; margin-bottom: 10px; }
table { width:100%; border-collapse:collapse; font-size: 8px; }
th, td { border:1px solid #666; padding:3px; text-align:left; }
th { background-color:#f0f0f0; font-weight:bold; text-align:center; }
.text-end { text-align:right; }
.footer { margin-top:10px; font-size:8px; color:#666; }
.totals { background-color:#f0f0f0; font-weight:bold; }
</style></head><body>';

$html .= '<div class="header">
    <div class="company">VAŠ POTRČKO DOO BEOGRAD</div>
    <div class="address">Aleksandra Stamboliškog 6A</div>
    <div class="ids">PIB 107418055 | MB 20798513</div>
</div>';

$html .= '<div class="title">FINANSIJSKI IZVEŠTAJ</div>';

// Add filters info
$html .= '<div class="filters">Filteri: ';
if ($date_from) $html .= "Od: $date_from ";
if ($date_to) $html .= "Do: $date_to ";
if ($month) $html .= "Mesec: $month ";
$html .= '</div>';

$html .= '<table>
<thead>
<tr>
<th>Datum</th><th>Vreme</th><th>Vozač</th><th>Klijent</th><th>Vozilo</th><th>ORS ID</th>
<th>Tovarni list</th><th>Tip</th><th>KM obr.</th><th>KM vozač</th>
<th>Prihod</th><th>Gorivo</th><th>Amort.</th><th>Dnevnica</th><th>Doprinosi</th><th>Neto</th>
</tr>
</thead>
<tbody>';

foreach($tours as $t) {
    $tourKm = floatval($t['tour_km']); // Kilometraža iz obračuna (uvoz)
    $driverKm = floatval($t['driver_km'] ?? 0); // Kilometraža koju je vozač unео
    $turnover = floatval($t['turnover']);
    $allowance = floatval($t['allowance']);
    $contributions = 0; // Driver contributions column doesn't exist in database
    
    $fuelCost = round($driverKm * (float)$t['fuel_consumption'] / 100 * $fuelPrice);
    $amortCost = round($driverKm * (float)$t['depreciation_per_km']);
    $net = $turnover - $fuelCost - $amortCost - $allowance - $contributions;
    
    $totalTourKm += $tourKm;
    $totalDriverKm += $driverKm;
    $totalTurnover += $turnover;
    $totalAllowance += $allowance;
    $totalContributions += $contributions;
    $totalFuel += $fuelCost;
    $totalAmort += $amortCost;
    $totalNet += $net;
    
    $html .= '<tr>';
    $html .= '<td>'.htmlspecialchars($t['date']).'</td>';
    $html .= '<td>'.htmlspecialchars($t['time']).'</td>';
    $html .= '<td>'.htmlspecialchars($t['driver_name']).'</td>';
    $html .= '<td>'.htmlspecialchars($t['client_name'] ?? '-').'</td>';
    $html .= '<td>'.htmlspecialchars($t['vehicle_plate']).'</td>';
    $html .= '<td>'.htmlspecialchars($t['ors_id']).'</td>';
    $html .= '<td>'.htmlspecialchars($t['waybill_number'] ?? '-').'</td>';
    $html .= '<td>'.htmlspecialchars($t['delivery_type']).'</td>';
    $html .= '<td class="text-end">'.number_format($tourKm,0,',','.').'</td>';
    $html .= '<td class="text-end">'.number_format($driverKm,0,',','.').'</td>';
    $html .= '<td class="text-end">'.number_format($turnover,2,',','.').'</td>';
    $html .= '<td class="text-end">'.number_format($fuelCost,0,',','.').'</td>';
    $html .= '<td class="text-end">'.number_format($amortCost,0,',','.').'</td>';
    $html .= '<td class="text-end">'.number_format($allowance,2,',','.').'</td>';
    $html .= '<td class="text-end">'.number_format($contributions,2,',','.').'</td>';
    $html .= '<td class="text-end">'.number_format($net,2,',','.').'</td>';
    $html .= '</tr>';
}

$html .= '</tbody>
<tfoot>
<tr class="totals">
<td colspan="8"><strong>UKUPNO:</strong></td>
<td class="text-end"><strong>'.number_format($totalTourKm,0,',','.').'</strong></td>
<td class="text-end"><strong>'.number_format($totalDriverKm,0,',','.').'</strong></td>
<td class="text-end"><strong>'.number_format($totalTurnover,2,',','.').'</strong></td>
<td class="text-end"><strong>'.number_format($totalFuel,0,',','.').'</strong></td>
<td class="text-end"><strong>'.number_format($totalAmort,0,',','.').'</strong></td>
<td class="text-end"><strong>'.number_format($totalAllowance,2,',','.').'</strong></td>
<td class="text-end"><strong>'.number_format($totalContributions,2,',','.').'</strong></td>
<td class="text-end"><strong>'.number_format($totalNet,2,',','.').'</strong></td>
</tr>
</tfoot>
</table>';

$printDate = date('d.m.Y. H:i');
$html .= '<div class="footer">Datum štampe: ' . $printDate . '</div>';
$html .= '</body></html>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4','landscape');
$dompdf->render();

$filename = 'finansijski_izvestaj_' . date('Y-m-d') . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;
?>
