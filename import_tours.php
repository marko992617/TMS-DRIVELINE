<?php
require __DIR__ . '/vendor/autoload.php';
require 'config.php'; // include $pdo
use PhpOffice\PhpSpreadsheet\IOFactory;

// Initialize messaging
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['excel_file'])) {
        if ($_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
            try {
                // Load spreadsheet
                $tmpName = $_FILES['excel_file']['tmp_name'];
                $spreadsheet = IOFactory::load($tmpName);
                $sheet = $spreadsheet->getActiveSheet();
                $highestRow = $sheet->getHighestRow();

                // Read header row
                $columns = [];
                $cellIterator = $sheet->getRowIterator(1,1)->current()->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(false);
                foreach ($cellIterator as $cell) {
                    $columns[] = trim($cell->getValue());
                }

                // If mapping provided, import
                if (isset($_POST['mapping']) && is_array($_POST['mapping'])) {
                    $mapping = $_POST['mapping'];
                    $dataRows = [];
                    for ($r = 2; $r <= $highestRow; $r++) {
                        $rowData = [];
                        foreach ($columns as $ci => $colName) {
                            $rowData[$colName] = $sheet->getCellByColumnAndRow($ci + 1, $r)->getValue();
                        }
                        $dataRows[] = $rowData;
                    }
                    // Prepare insert
                    $stmt = $pdo->prepare(
                        "INSERT INTO tours
                        (`date`,`loading_time`,`pickup_location`,`unload_location`,`ors_id`,`relation`,`driver_id`,`vehicle_id`,`delivery_type`,`note`)
                        VALUES
                        (:date,:loading_time,:pickup_location,:unload_location,:ors_id,:relation,:driver_id,:vehicle_id,:delivery_type,:note)"
                    );
                    $count = 0;
                    foreach ($dataRows as $row) {
                        $params = [];
                        foreach ($mapping as $excelCol => $dbField) {
                            if (empty($dbField)) continue;
                            $params[$dbField] = $row[$excelCol];
                        }
                        // split datetime if needed
                        if (!empty($params['loading_time']) && strpos($params['loading_time'], ' ') !== false) {
                            list($params['date'], $params['loading_time']) = explode(' ', $params['loading_time'], 2);
                        }
                        $stmt->execute([
                            ':date' => $params['date'] ?? null,
                            ':loading_time' => $params['loading_time'] ?? null,
                            ':pickup_location' => $params['pickup_location'] ?? null,
                            ':unload_location' => $params['unload_location'] ?? null,
                            ':ors_id' => $params['ors_id'] ?? null,
                            ':relation' => $params['relation'] ?? null,
                            ':driver_id' => $params['driver_id'] ?? null,
                            ':vehicle_id' => $params['vehicle_id'] ?? null,
                            ':delivery_type' => $params['delivery_type'] ?? null,
                            ':note' => $params['note'] ?? null,
                        ]);
                        $count++;
                    }
                    $message = "Uspešno importovano $count tura.";
                    $messageType = 'success';
                } else {
                    $message = 'Nije poslato mapiranje kolona.';
                    $messageType = 'danger';
                    $columns = isset($columns) ? $columns : [];
                }
            } catch (Exception $e) {
                $message = 'Greška pri importu: ' . $e->getMessage();
                $messageType = 'danger';
                $columns = isset($columns) ? $columns : [];
            }
        } else {
            $message = 'Greška pri otpremi fajla: ' . $_FILES['excel_file']['error'];
            $messageType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><title>Import Tours</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<h1>Import Tours</h1>
<?php if (!empty($message)): ?>
    <div class="alert alert-<?= $messageType ?>" role="alert"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<form method="post" enctype="multipart/form-data" class="mb-4">
    <div class="mb-3">
        <label>Excel file</label>
        <input type="file" name="excel_file" accept=".xlsx,.xls" required class="form-control" />
    </div>
    <button type="submit" class="btn btn-primary">Upload & Preview</button>
</form>

<?php if (!empty($columns) && empty($_POST['mapping'])): ?>
<form method="post">
    <?php foreach ($columns as $col): ?>
    <div class="mb-2 row">
        <label class="col-sm-4 col-form-label"><?= htmlspecialchars($col) ?></label>
        <div class="col-sm-8">
            <select name="mapping[<?= htmlspecialchars($col) ?>]" class="form-select">
                <option value="">-- skip --</option>
                <option value="date">Datum ture</option>
                <option value="loading_time">Vreme utovara</option>
                <option value="pickup_location">Mesto utovara</option>
                <option value="unload_location">Istovar</option>
                <option value="ors_id">ORS ID</option>
                <option value="relation">Relacija</option>
                <option value="driver_id">Vozač</option>
                <option value="vehicle_id">Vozilo</option>
                <option value="delivery_type">Tip isporuke</option>
                <option value="note">Napomena</option>
            </select>
        </div>
    </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-success">Import Data</button>
</form>
<?php endif; ?>

</body>
</html>
