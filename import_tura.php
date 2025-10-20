<?php
date_default_timezone_set('Europe/Belgrade');
$selected_date = isset($_POST['datum']) ? $_POST['datum'] : date('Y-m-d', strtotime('+1 day'));
// import_tura.php – nova stranica za uvoz tura iz Excel fajla (popravljeno null za unloading_loc)

require __DIR__ . '/vendor/autoload.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'header.php';
require 'config.php';
require 'functions.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$error = '';
$success = '';
$headers = [];
$showMap = false;

// Load saved mapping
$savedMap = json_decode($pdo->query("SELECT value FROM settings WHERE name='import_map_tura'")->fetchColumn() ?: '[]', true);
$importDate = $_POST['import_date'] ?? '';
$uploaded = $_SESSION['import_file_tura'] ?? '';

// Handle file upload and header reading
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel']) && !isset($_POST['save_map']) && !isset($_POST['import_confirm'])) {
    try {
        if (empty($importDate)) {
            throw new Exception('Molim izaberite datum importa.');
        }
        if ($_FILES['excel']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Greška pri otpremanju fajla (code ' . $_FILES['excel']['error'] . ').');
        }
        $tmp = $_FILES['excel']['tmp_name'];
        $destDir = __DIR__ . '/uploads';
        if (!is_dir($destDir) && !mkdir($destDir, 0755, true)) {
            throw new Exception('Ne mogu kreirati uploads direktorijum.');
        }
        $dest = $destDir . '/imp_tura_' . uniqid() . '_' . basename($_FILES['excel']['name']);
        if (!move_uploaded_file($tmp, $dest)) {
            throw new Exception('Greška pri premještanju fajla.');
        }
        $_SESSION['import_file_tura'] = $dest;
        $_SESSION['import_date_tura'] = $importDate;
        $uploaded = $dest;
        // Read headers
        $spreadsheet = IOFactory::load($dest);
        $sheet = $spreadsheet->getActiveSheet();
        $highestCol = $sheet->getHighestColumn();
        $maxCol = Coordinate::columnIndexFromString($highestCol);
        for ($c = 1; $c <= $maxCol; $c++) {
            $colLetter = Coordinate::stringFromColumnIndex($c);
            $headers[] = (string)$sheet->getCell($colLetter . '1')->getValue();
        }
        $showMap = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle save mapping
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_map'])) {
    $map = $_POST['map'] ?? [];
    $pdo->prepare("REPLACE INTO settings(name,value) VALUES('import_map_tura',?)")
        ->execute([json_encode($map)]);
    header('Location: import_tura.php');
    exit;
}

// Handle import confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_confirm']) && $uploaded) {
    try {
        $map = $_POST['map'] ?? [];
        if (empty($map)) {
            throw new Exception('Niste mapirali kolone.');
        }
        $importDate = $_SESSION['import_date_tura'] ?? '';
        if (empty($importDate)) {
            throw new Exception('Datum importa nije dostupan.');
        }
        $spreadsheet = IOFactory::load($uploaded);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $count = 0;
        for ($r = 2; $r <= $highestRow; $r++) {
            $rowRaw = [];
            foreach ($map as $field => $colIndex) {
                $cellVal = $sheet->getCell(Coordinate::stringFromColumnIndex((int)$colIndex) . $r)->getValue();
                if ($field === 'loading_time') {
                    if (is_numeric($cellVal)) {
                        $seconds = (int)round($cellVal * 86400);
                        $rowRaw[$field] = gmdate('H:i:s', $seconds);
                    } else {
                        $rowRaw[$field] = $cellVal;
                    }
                } else {
                    $rowRaw[$field] = $cellVal;
                }
            }
            $vehicleId = !empty($rowRaw['licenseplate']) ? getVehicleIdByPlate($rowRaw['licenseplate']) : null;
            $data = [
                'date'          => $importDate,
                'driver'        => null,
                'vehicle'       => $vehicleId,
                'ors_id'        => $rowRaw['ors_id'] ?? null,
                'delivery_type' => $rowRaw['delivery_type'] ?? null,
                'km'            => 0,
                'fuel'          => 0,
                'amort'         => calculateAmortization($vehicleId, 0),
                'allowance'     => calculateAllowance(null),
                'load_time'     => $importDate . ' ' . ($rowRaw['loading_time'] ?? ''),
                'load_loc'      => $rowRaw['loading_loc'] ?? '',
                'unload_loc'    => $rowRaw['unloading_loc'] ?? '',
                'route'         => $rowRaw['route'] ?? null,
                'note'          => '',
            ];
            addTour($data);
            $count++;
        }
        @unlink($uploaded);
        unset($_SESSION['import_file_tura'], $_SESSION['import_date_tura']);
        $success = "Uvezeno $count tura.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container">
  <h3>Import tura</h3>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>  
  <?php endif; ?>

  <?php if (!$showMap): ?>
    <form method="post" enctype="multipart/form-data" class="row g-3 mb-4">
      <div class="col-md-4">
        <label>Excel fajl</label>
        <input type="file" name="excel" accept=".xlsx,.xls" class="form-control" required>
      </div>
      <div class="col-md-3">
        <label>Datum importa</label>
        <input type="date" name="import_date" class="form-control" value="<?= htmlspecialchars($importDate) ?>" required>
      </div>
      <div class="col-md-2 align-self-end">
        <button class="btn btn-primary">Dalje</button>
      </div>
    </form>
  <?php else: ?>
    <form method="post" class="mb-4">
      <button name="save_map" class="btn btn-secondary mb-3">Sačuvaj mapiranje</button>
      <h5>Mapiranje kolona</h5>
      <input type="hidden" name="import_confirm" value="1">
      <div class="row">
        <?php
        $fields = [
            'date' => 'Datum ture',
            'ors_id' => 'ORS ID',
            'delivery_type' => 'Tip isporuke',
            'loading_loc' => 'Mesto utovara',
            'loading_time' => 'Vreme utovara',
            'licenseplate' => 'Reg. broj',
            'unloading_loc' => 'Mesto istovara',
            'route' => 'Relacija'
        ];
        foreach ($fields as $key => $label): ?>
            <div class="col-md-4 mb-2">
                <label><?= $label ?></label>
                <select name="map[<?= $key ?>]" class="form-select" required>
                    <option value="">-- izaberite --</option>
                    <?php foreach ($headers as $idx => $h): ?>
                        <?php $sel = (isset($savedMap[$key]) && $savedMap[$key] == ($idx+1)) ? 'selected' : ''; ?>
                        <option value="<?= $idx+1 ?>" <?= $sel ?>><?= htmlspecialchars($h) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endforeach; ?>
      </div>
      <button class="btn btn-success">Importuj ture</button>
    </form>
  <?php endif; ?>
</div>

<?php require 'footer.php'; ?>