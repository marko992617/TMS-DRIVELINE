
<?php
date_default_timezone_set('Europe/Belgrade');
require __DIR__ . '/vendor/autoload.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Check if we have the required data
if (!isset($_SESSION['tours_data']) || empty($_SESSION['tours_data'])) {
    die('Nema podataka za export. Molimo prvo učitajte Excel fajl.');
}

$toursData = $_SESSION['tours_data'];
$modifications = [];

// Get modifications from POST or from session assignment data
if (isset($_POST['modifications'])) {
    $modifications = json_decode($_POST['modifications'], true) ?: [];
}

// Get drivers and vehicles for lookup
$drivers = [];
$vehicles = [];

try {
    $stmt = $pdo->query("SELECT id, name FROM drivers ORDER BY name");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $drivers[$row['id']] = $row['name'];
    }
    
    $stmt = $pdo->query("SELECT id, plate FROM vehicles ORDER BY plate");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vehicles[$row['id']] = $row['plate'];
    }
} catch (Exception $e) {
    // Continue without driver/vehicle data
}

// Create new spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Uvezene ture - modifikovane');

// Define headers based on the image provided
$headers = [
    'A1' => 'Datum utovara rute',
    'B1' => 'Datum istovara rute', 
    'C1' => 'ORS_ID',
    'D1' => 'Prevoznik',
    'E1' => 'Šifra rute',
    'F1' => 'Tip isporuke',
    'G1' => 'Mesto utovara',
    'H1' => 'Magacin povrata',
    'I1' => 'Vreme utova.',
    'J1' => 'Vreme kraja utova.',
    'K1' => 'Kategorija vozila',
    'L1' => 'licenseplate',
    'M1' => 'licenseplateTrailer',
    'N1' => 'id_shift',
    'O1' => 'Mesto istovara'
];

// Set headers with styling
foreach ($headers as $cell => $header) {
    $sheet->setCellValue($cell, $header);
    $sheet->getStyle($cell)->getFont()->setBold(true);
    $sheet->getStyle($cell)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->setStartColor(new Color('E8F4FD')); // Light blue header
    $sheet->getStyle($cell)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);
    $sheet->getStyle($cell)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER);
}

// Auto-size columns
foreach (range('A', 'O') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Add data
$row = 2;
foreach ($toursData as $index => $tour) {
    // Original data from Excel
    $originalData = $tour['_original'] ?? [];
    
    // Set basic data
    $sheet->setCellValue('A' . $row, $tour['date'] ?? '');
    $sheet->setCellValue('B' . $row, $originalData['Datum istovara rute'] ?? '');
    $sheet->setCellValue('C' . $row, $tour['ors_id'] ?? '');
    $sheet->setCellValue('D' . $row, $originalData['Prevoznik'] ?? '');
    $sheet->setCellValue('E' . $row, $originalData['Šifra rute'] ?? '');
    $sheet->setCellValue('F' . $row, $tour['delivery_type'] ?? '');
    $sheet->setCellValue('G' . $row, $tour['loading_loc'] ?? '');
    $sheet->setCellValue('H' . $row, $originalData['Magacin povrata'] ?? '');
    
    // Handle "Vreme utova." (Column I) - Can be modified
    $timeValue = $tour['loading_time'] ?? '';
    $timeModified = false;
    
    if (isset($modifications[$index])) {
        $mod = $modifications[$index];
        if (isset($mod['timeModified']) && $mod['timeModified'] && isset($mod['newTime'])) {
            $timeValue = $mod['newTime'];
            $timeModified = true;
        }
    }
    
    // If tour was assigned, get the assigned time
    if (isset($tour['assigned_loading_time'])) {
        $assignedTime = date('H:i', strtotime($tour['assigned_loading_time']));
        $originalTime = $tour['loading_time'] ?? '';
        if ($assignedTime !== $originalTime) {
            $timeValue = $assignedTime;
            $timeModified = true;
        }
    }
    
    $sheet->setCellValue('I' . $row, $timeValue);
    if ($timeModified) {
        $sheet->getStyle('I' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new Color('FFEBEE')); // Light red for modified
    }
    
    $sheet->setCellValue('J' . $row, $originalData['Vreme kraja utova.'] ?? '');
    $sheet->setCellValue('K' . $row, $originalData['Kategorija vozila'] ?? '');
    
    // Handle "licenseplate" (Column L) - Can be modified
    $plateValue = $tour['license_plate'] ?? '';
    $plateModified = false;
    
    if (isset($modifications[$index])) {
        $mod = $modifications[$index];
        if (isset($mod['plateModified']) && $mod['plateModified'] && isset($mod['newPlate'])) {
            $plateValue = $mod['newPlate'];
            $plateModified = true;
        }
    }
    
    // If tour was assigned, get the assigned vehicle plate
    if (isset($tour['assigned_vehicle_plate'])) {
        $assignedPlate = $tour['assigned_vehicle_plate'];
        $originalPlate = $tour['license_plate'] ?? '';
        if ($assignedPlate !== $originalPlate) {
            $plateValue = $assignedPlate;
            $plateModified = true;
        }
    }
    
    $sheet->setCellValue('L' . $row, $plateValue);
    if ($plateModified) {
        $sheet->getStyle('L' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new Color('FFEBEE')); // Light red for modified
    }
    
    $sheet->setCellValue('M' . $row, $originalData['licenseplateTrailer'] ?? '');
    $sheet->setCellValue('N' . $row, $originalData['id_shift'] ?? '');
    $sheet->setCellValue('O' . $row, $tour['unloading_loc'] ?? '');
    
    // Apply borders to all data cells
    $sheet->getStyle('A' . $row . ':O' . $row)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);
    
    // Alternate row colors for better readability
    if ($row % 2 == 0) {
        $sheet->getStyle('A' . $row . ':O' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->setStartColor(new Color('F8F9FA')); // Very light gray for even rows
    }
    
    $row++;
}

// Add legend/note
$noteRow = $row + 2;
$sheet->setCellValue('A' . $noteRow, 'Napomena:');
$sheet->getStyle('A' . $noteRow)->getFont()->setBold(true);

$noteRow++;
$sheet->setCellValue('A' . $noteRow, '• Crveno označene ćelije predstavljaju izmenjene vrednosti u odnosu na originalni Excel fajl');
$sheet->getStyle('A' . $noteRow)->getFont()->setSize(10);

$noteRow++;
$sheet->setCellValue('A' . $noteRow, '• Kolone koje mogu biti izmenjene: Vreme utova. (I) i licenseplate (L)');
$sheet->getStyle('A' . $noteRow)->getFont()->setSize(10);

// Merge cells for the notes
$sheet->mergeCells('A' . ($noteRow - 1) . ':O' . ($noteRow - 1));
$sheet->mergeCells('A' . $noteRow . ':O' . $noteRow);

// Create writer and output
$writer = new Xlsx($spreadsheet);

// Set headers for download
$filename = 'ture_modifikovane_' . date('Y-m-d_H-i-s') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

// Clear any previous output
if (ob_get_level()) {
    ob_end_clean();
}

$writer->save('php://output');
exit;
?>
