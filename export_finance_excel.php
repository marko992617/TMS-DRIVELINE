<?php
require 'config.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Fetch filters
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';
$vehicle_id = $_GET['vehicle_id'] ?? '';
$driver_id = $_GET['driver_id'] ?? '';
$client_id = $_GET['client_id'] ?? '';
$month = $_GET['month'] ?? '';

// Fetch fuel price
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

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers
$headers = [
    'A1' => 'Datum',
    'B1' => 'Vreme',
    'C1' => 'Vozač',
    'D1' => 'Klijent',
    'E1' => 'Vozilo',
    'F1' => 'ORS ID',
    'G1' => 'Broj tovarnog lista',
    'H1' => 'Tip',
    'I1' => 'KM obračun',
    'J1' => 'KM vozač',
    'K1' => 'Prihod (RSD)',
    'L1' => 'Gorivo (RSD)',
    'M1' => 'Amortizacija (RSD)',
    'N1' => 'Dnevnica (RSD)',
    'O1' => 'Doprinosi (RSD)',
    'P1' => 'Neto (RSD)'
];

foreach ($headers as $cell => $value) {
    $sheet->setCellValue($cell, $value);
}

// Style headers
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '333333']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
];
$sheet->getStyle('A1:P1')->applyFromArray($headerStyle);

// Add data
$row = 2;
$totalTourKm = 0;
$totalDriverKm = 0;
$totalTurnover = 0;
$totalFuel = 0;
$totalAmort = 0;
$totalAllowance = 0;
$totalContributions = 0;
$totalNet = 0;

foreach ($tours as $t) {
    $tourKm = floatval($t['tour_km']);
    $driverKm = floatval($t['driver_km'] ?? $tourKm);
    $turnover = floatval($t['turnover']);
    $allowance = floatval($t['allowance']);
    $contributions = 0; // Driver contributions column doesn't exist in database

    $fuelCost = round($driverKm * (float)$t['fuel_consumption'] / 100 * $fuelPrice);
    $amortCost = round($driverKm * (float)$t['depreciation_per_km']);
    $net = $turnover - $fuelCost - $amortCost - $allowance - $contributions;

    $sheet->setCellValue("A{$row}", $t['date']);
    $sheet->setCellValue("B{$row}", $t['time']);
    $sheet->setCellValue("C{$row}", $t['driver_name']);
    $sheet->setCellValue("D{$row}", $t['client_name'] ?? '-');
    $sheet->setCellValue("E{$row}", $t['vehicle_plate']);
    $sheet->setCellValue("F{$row}", $t['ors_id']);
    $sheet->setCellValue("G{$row}", $t['waybill_number'] ?? '-');
    $sheet->setCellValue("H{$row}", $t['delivery_type']);
    $sheet->setCellValue("I{$row}", $tourKm);
    $sheet->setCellValue("J{$row}", $driverKm);
    $sheet->setCellValue("K{$row}", $turnover);
    $sheet->setCellValue("L{$row}", $fuelCost);
    $sheet->setCellValue("M{$row}", $amortCost);
    $sheet->setCellValue("N{$row}", $allowance);
    $sheet->setCellValue("O{$row}", $contributions);
    $sheet->setCellValue("P{$row}", $net);

    $totalTourKm += $tourKm;
    $totalDriverKm += $driverKm;
    $totalTurnover += $turnover;
    $totalAllowance += $allowance;
    $totalContributions += $contributions;
    $totalFuel += $fuelCost;
    $totalAmort += $amortCost;
    $totalNet += $net;

    $row++;
}

// Add totals row
$sheet->setCellValue("A{$row}", 'UKUPNO');
$sheet->mergeCells("A{$row}:H{$row}");
$sheet->setCellValue("I{$row}", $totalTourKm);
$sheet->setCellValue("J{$row}", $totalDriverKm);
$sheet->setCellValue("K{$row}", $totalTurnover);
$sheet->setCellValue("L{$row}", $totalFuel);
$sheet->setCellValue("M{$row}", $totalAmort);
$sheet->setCellValue("N{$row}", $totalAllowance);
$sheet->setCellValue("O{$row}", $totalContributions);
$sheet->setCellValue("P{$row}", $totalNet);

// Style totals row
$totalStyle = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F0F0F0']]
];
$sheet->getStyle("A{$row}:P{$row}")->applyFromArray($totalStyle);

// Auto-size columns
foreach(range('A','P') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Set number formats
$sheet->getStyle('I:J')->getNumberFormat()->setFormatCode('#,##0');
$sheet->getStyle('K:P')->getNumberFormat()->setFormatCode('#,##0.00');

$writer = new Xlsx($spreadsheet);
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="finansijski_izvestaj_' . date('Y-m-d') . '.xlsx"');
$writer->save("php://output");
?>