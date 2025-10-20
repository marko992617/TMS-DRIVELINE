<?php
// send_tours.php - PDF generation for single or all drivers, skipping drivers without tours

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';
require 'config.php';
require 'functions.php';

use Dompdf\Dompdf;

date_default_timezone_set('Europe/Belgrade');

// Fetch all drivers
$allDrivers = $pdo->query("SELECT id, name FROM drivers ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// If date is set and 'all drivers' selected
if (isset($_GET['date']) && isset($_GET['driver']) && $_GET['driver'] === '') {
    $selectedDate = $_GET['date'];
    // Find drivers with tours on that date
    $sql = "SELECT DISTINCT driver_id FROM tours WHERE DATE(loading_time) = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$selectedDate]);
    $driversWithTours = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Generisanje tura</title></head><body>';
    echo '<script>';
    foreach ($allDrivers as $drv) {
        if (in_array($drv['id'], $driversWithTours)) {
            $id = $drv['id'];
            echo "window.open('send_tours.php?date=" . urlencode($selectedDate) . "&driver={$id}', '_blank');";
        }
    }
    echo 'window.close();';
    echo '</script>';
    echo '</body></html>';
    exit;
}

// If specific driver requested
if (isset($_GET['date'], $_GET['driver']) && $_GET['driver'] !== '') {
    $selectedDate = $_GET['date'];
    $driverId     = intval($_GET['driver']);

    $sql = "
        SELECT t.*, d.name AS driver_name, v.plate AS vehicle_plate
        FROM tours t
        LEFT JOIN drivers d ON t.driver_id = d.id
        LEFT JOIN vehicles v ON t.vehicle_id = v.id
        WHERE DATE(t.loading_time) = ? AND t.driver_id = ?
        ORDER BY t.loading_time ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$selectedDate, $driverId]);
    $toursList = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($toursList)) {
        die('Nema tura za vozača na ovaj datum.');
    }

    $dompdf = new Dompdf();
    $html   = '<html><head><meta charset="UTF-8"><style>'
            . 'body{font-family:"DejaVu Sans",sans-serif;font-size:12px;margin:0;padding:0;}'
            . '.header{text-align:center;margin-bottom:10px;}'
            . '.header h1{margin:0;font-size:18px;}'
            . '.header h2{margin:2px 0;font-size:14px;}'
            . '.nalog-title{text-align:center;font-size:16px;margin-bottom:15px;}'
            . '.tour-table{width:100%;table-layout:fixed;border:1px solid #000;border-collapse:collapse;margin-bottom:20px;}'
            . '.tour-table th{width:30%;border:1px solid #000;padding:5px;text-align:left;vertical-align:top;}'
            . '.tour-table td{width:70%;border:1px solid #000;padding:5px;text-align:left;vertical-align:top;}'
            . '.page-break{page-break-after:always;}'
            . '</style></head><body>';
    $html .= '<div class="header">'
           . '<h1>VAŠ POTRČKO DOO BEOGRAD</h1>'
           . '<h2>Aleksandra Stamboliskog 6A | PIB 107418055 | MB 20798513</h2>'
           . '</div>';
    $html .= '<div class="nalog-title">NALOG ZA UTOVAR</div>';

    $chunks = array_chunk($toursList, 2);
    foreach ($chunks as $i => $chunk) {
        foreach ($chunk as $t) {
            $html .= '<table class="tour-table">'
                   . '<tr><th>Šifra ture</th><td>' . htmlspecialchars($t['id'] ?? '', ENT_QUOTES) . '</td></tr>'
                   . '<tr><th>ORS ID</th><td>' . htmlspecialchars($t['ors_id'] ?? '', ENT_QUOTES) . '</td></tr>'
                   . '<tr><th>Vozač</th><td>' . htmlspecialchars($t['driver_name'] ?? '', ENT_QUOTES) . '</td></tr>'
                   . '<tr><th>Vozilo</th><td>' . htmlspecialchars($t['vehicle_plate'] ?? '', ENT_QUOTES) . '</td></tr>'
                   . '<tr><th>Datum</th><td>' . htmlspecialchars($selectedDate, ENT_QUOTES) . '</td></tr>'
                   . '<tr><th>Vreme utovara</th><td>' . htmlspecialchars($t['loading_time'] ?? '', ENT_QUOTES) . '</td></tr>'
                   . '<tr><th>Mesto utovara</th><td>' . htmlspecialchars($t['loading_loc'] ?? '', ENT_QUOTES) . '</td></tr>'
                   . '<tr><th>Istovar</th><td>' . htmlspecialchars($t['unloading_loc'] ?? '', ENT_QUOTES) . '</td></tr>'
                   . '<tr><th>Tip isporuke</th><td>' . htmlspecialchars($t['delivery_type'] ?? '', ENT_QUOTES) . '</td></tr>'
                   . '<tr><th>Relacija</th><td>' . htmlspecialchars($t['route'] ?? '', ENT_QUOTES) . '</td></tr>'
                   . '<tr><th>Napomena</th><td>' . htmlspecialchars($t['note'] ?? '', ENT_QUOTES) . '</td></tr>'
                   . '</table>';
        }
        if ($i < count($chunks) - 1) {
            $html .= '<div class="page-break"></div>';
        }
    }
    $html .= '</body></html>';

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4','portrait');
    $dompdf->render();
    if(ob_get_length()) ob_end_clean();

    $drv = preg_replace('/[^A-Za-z0-9_\-]/','',str_replace(' ','_', $toursList[0]['driver_name']));
    $veh = preg_replace('/[^A-Za-z0-9_\-]/','',str_replace(' ','_', $toursList[0]['vehicle_plate']));
    $file = "{$selectedDate}_{$drv}_{$veh}.pdf";
    $dompdf->stream($file,['Attachment'=>false]);
    exit;
}

// Display form
require 'header.php';
?>
<div class="container" style="max-width: 800px; margin: 0 auto; padding: 20px;">
  <h3>Slanje tura</h3>
  <form method="get" action="send_tours.php" class="row g-3 mb-4">
    <div class="col-md-4">
      <label for="date" class="form-label">Datum</label>
      <input type="date" id="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-md-4">
      <label for="driver" class="form-label">Vozač</label>
      <select id="driver" name="driver" class="form-select">
        <option value="">Svi vozači</option>
        <?php foreach($allDrivers as $d): ?>
          <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name'],ENT_QUOTES) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4 align-self-end">
      <button type="submit" class="btn btn-primary">Generiši</button>
    </div>
  </form>
</div>
<?php require 'footer.php'; exit;?>