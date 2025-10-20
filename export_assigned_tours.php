
<?php
require __DIR__ . '/vendor/autoload.php';
require 'config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Pokušaj da učitaš iz sesije prvo
$toursData = $_SESSION['tours_data'] ?? [];

// Ako nema podataka u sesiji, učitaj iz baze
if (empty($toursData)) {
    $stmt = $pdo->prepare("SELECT data FROM export_cache WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $toursData = json_decode($result['data'], true);
    }
}

if (empty($toursData)) {
    die('Nema podataka za export. Molim prvo učitajte i dodelite ture.');
}

// Get drivers and vehicles data
$drivers = [];
$vehicles = [];

$stmt = $pdo->query("SELECT id, name FROM drivers");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $drivers[$row['id']] = $row['name'];
}

$stmt = $pdo->query("SELECT id, plate FROM vehicles");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $vehicles[$row['id']] = $row['plate'];
}

// Create new spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers
$headers = array_keys($toursData[0]);
$headers[] = 'Dodeljenj_Vozac';
$headers[] = 'Dodeljena_Registracija';
$headers[] = 'Vreme_Utovara';
$headers[] = 'Status_Izmene';

$col = 1;
foreach ($headers as $header) {
    $sheet->setCellValueByColumnAndRow($col, 1, $header);
    $col++;
}

// Add data rows
$row = 2;
foreach ($toursData as $index => $tour) {
    $col = 1;
    
    // Original data
    foreach ($tour as $key => $value) {
        // Skip internal tracking fields
        if (in_array($key, ['assigned_driver_id', 'assigned_vehicle_id', 'assigned_loading_time', 'was_assigned'])) {
            continue;
        }
        $sheet->setCellValueByColumnAndRow($col, $row, $value);
        $col++;
    }
    
    // Check if tour was assigned from session data
    $wasAssigned = isset($tour['was_assigned']) && $tour['was_assigned'];
    $assignedDriver = '';
    $assignedVehicle = '';
    $assignedTime = '';
    $originalTime = $tour['loading_time'] ?? '';
    $originalVehicle = $tour['vehicle_plate'] ?? '';
    
    // Get assignment data from session
    if ($wasAssigned) {
        $driverId = $tour['assigned_driver_id'] ?? null;
        $vehicleId = $tour['assigned_vehicle_id'] ?? null;
        $assignedTime = $tour['assigned_loading_time'] ?? '';
        
        if ($driverId && isset($drivers[$driverId])) {
            $assignedDriver = $drivers[$driverId];
        }
        
        if ($vehicleId && isset($vehicles[$vehicleId])) {
            $assignedVehicle = $vehicles[$vehicleId];
        }
    }
    
    // Check for modifications
    $timeModified = false;
    $vehicleModified = false;
    
    if ($assignedTime && $originalTime) {
        // Compare times - extract time part from datetime
        $originalTimeOnly = '';
        if (strpos($originalTime, ':') !== false) {
            $originalTimeOnly = $originalTime;
        }
        $assignedTimeOnly = '';
        if ($assignedTime) {
            $assignedDateTime = new DateTime($assignedTime);
            $assignedTimeOnly = $assignedDateTime->format('H:i:s');
        }
        $timeModified = ($originalTimeOnly !== $assignedTimeOnly);
    }
    
    $vehicleModified = ($originalVehicle && $assignedVehicle && $originalVehicle !== $assignedVehicle);
    
    // Add assigned driver
    $sheet->setCellValueByColumnAndRow($col, $row, $assignedDriver);
    if ($wasAssigned && $assignedDriver !== '') {
        $sheet->getStyleByColumnAndRow($col, $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('90EE90'); // Light green for assigned
    }
    $col++;
    
    // Add assigned vehicle
    $sheet->setCellValueByColumnAndRow($col, $row, $assignedVehicle);
    if ($vehicleModified) {
        $sheet->getStyleByColumnAndRow($col, $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF0000'); // Red for modified
    } elseif ($wasAssigned && $assignedVehicle !== '') {
        $sheet->getStyleByColumnAndRow($col, $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('90EE90'); // Light green for assigned
    }
    $col++;
    
    // Add assigned time
    $sheet->setCellValueByColumnAndRow($col, $row, $assignedTime);
    if ($timeModified) {
        $sheet->getStyleByColumnAndRow($col, $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF0000'); // Red for modified
    } elseif ($wasAssigned && $assignedTime !== '') {
        $sheet->getStyleByColumnAndRow($col, $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('90EE90'); // Light green for assigned
    }
    $col++;
    
    // Status column
    $status = $wasAssigned ? 'DODELJENO' : 'NEDODELJENO';
    $sheet->setCellValueByColumnAndRow($col, $row, $status);
    if ($wasAssigned) {
        $sheet->getStyleByColumnAndRow($col, $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('90EE90'); // Light green background
    }
    
    $row++;
}

// Auto-size columns
foreach (range('A', $sheet->getHighestColumn()) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Prepare filename
$filename = 'dodeljene_ture_' . date('Y-m-d_H-i-s') . '.xlsx';

// Output file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
