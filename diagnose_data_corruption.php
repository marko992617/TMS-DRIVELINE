
<?php
require 'header.php';
require 'config.php';

echo "<h2>Dijagnostika korupcije podataka - Ture vs Vozači vs Vozila</h2>";

// Analiziraj neusaglašenosti po datumima
echo "<h4>Analiza neusaglašenosti po datumima:</h4>";

$stmtByDate = $pdo->query("
    SELECT 
        DATE(t.loading_time) as datum,
        COUNT(*) as ukupno_tura,
        COUNT(CASE WHEN d.id IS NULL AND t.driver_id IS NOT NULL THEN 1 END) as lose_vozaci,
        COUNT(CASE WHEN v.id IS NULL AND t.vehicle_id IS NOT NULL THEN 1 END) as losa_vozila,
        COUNT(CASE WHEN t.delivery_type IS NULL OR t.delivery_type = '' THEN 1 END) as bez_tipa_isporuke,
        COUNT(CASE WHEN t.unloading_loc IS NULL OR t.unloading_loc = '' THEN 1 END) as bez_istovara
    FROM tours t
    LEFT JOIN drivers d ON t.driver_id = d.id
    LEFT JOIN vehicles v ON t.vehicle_id = v.id
    WHERE DATE(t.loading_time) >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
    GROUP BY DATE(t.loading_time)
    ORDER BY DATE(t.loading_time) DESC
");

$dateAnalysis = $stmtByDate->fetchAll();

echo "<div class='table-responsive'>";
echo "<table class='table table-striped'>";
echo "<tr><th>Datum</th><th>Ukupno tura</th><th>Loši vozači</th><th>Loša vozila</th><th>Bez tipa isporuke</th><th>Bez istovara</th><th>Status</th></tr>";

foreach ($dateAnalysis as $row) {
    $hasProblems = $row['lose_vozaci'] > 0 || $row['losa_vozila'] > 0 || $row['bez_tipa_isporuke'] > 0 || $row['bez_istovara'] > 0;
    $statusClass = $hasProblems ? 'table-danger' : 'table-success';
    $status = $hasProblems ? '⚠️ PROBLEMI' : '✅ OK';
    
    echo "<tr class='$statusClass'>";
    echo "<td><strong>{$row['datum']}</strong></td>";
    echo "<td>{$row['ukupno_tura']}</td>";
    echo "<td>" . ($row['lose_vozaci'] > 0 ? "<span class='text-danger'>{$row['lose_vozaci']}</span>" : "0") . "</td>";
    echo "<td>" . ($row['losa_vozila'] > 0 ? "<span class='text-danger'>{$row['losa_vozila']}</span>" : "0") . "</td>";
    echo "<td>" . ($row['bez_tipa_isporuke'] > 0 ? "<span class='text-warning'>{$row['bez_tipa_isporuke']}</span>" : "0") . "</td>";
    echo "<td>" . ($row['bez_istovara'] > 0 ? "<span class='text-warning'>{$row['bez_istovara']}</span>" : "0") . "</td>";
    echo "<td>$status</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// Detaljni pregled problema
echo "<h4>Detaljni pregled problema:</h4>";

// Ture sa nepostojećim vozačima
echo "<h5>Ture sa nepostojećim vozačima (ID-jevi koji ne postoje u tabeli drivers):</h5>";
$stmtBadDrivers = $pdo->query("
    SELECT t.id, t.driver_id, DATE(t.loading_time) as datum, t.loading_time, 
           t.loading_loc, t.delivery_type, t.unloading_loc
    FROM tours t 
    LEFT JOIN drivers d ON t.driver_id = d.id 
    WHERE t.driver_id IS NOT NULL AND d.id IS NULL
    ORDER BY t.loading_time DESC
    LIMIT 50
");
$badDriverTours = $stmtBadDrivers->fetchAll();

if (empty($badDriverTours)) {
    echo "<p class='text-success'>✓ Nema tura sa nepostojećim vozačima</p>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<p>Pronađeno " . count($badDriverTours) . " tura sa nepostojećim vozačima:</p>";
    echo "<div class='table-responsive'>";
    echo "<table class='table table-sm'>";
    echo "<tr><th>Tura ID</th><th>Nepoznat Driver ID</th><th>Datum</th><th>Vreme</th><th>Utovar</th><th>Tip isporuke</th><th>Istovar</th></tr>";
    foreach ($badDriverTours as $tour) {
        echo "<tr>";
        echo "<td><a href='edit_tour.php?id={$tour['id']}'>{$tour['id']}</a></td>";
        echo "<td><span class='badge bg-danger'>{$tour['driver_id']}</span></td>";
        echo "<td>{$tour['datum']}</td>";
        echo "<td>" . date('H:i', strtotime($tour['loading_time'])) . "</td>";
        echo "<td>" . htmlspecialchars(substr($tour['loading_loc'], 0, 30)) . "</td>";
        echo "<td>" . htmlspecialchars($tour['delivery_type']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($tour['unloading_loc'], 0, 40)) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    echo "</div>";
}

// Ture sa nepostojećim vozilima
echo "<h5>Ture sa nepostojećim vozilima (ID-jevi koji ne postoje u tabeli vehicles):</h5>";
$stmtBadVehicles = $pdo->query("
    SELECT t.id, t.vehicle_id, DATE(t.loading_time) as datum, t.loading_time,
           t.loading_loc, t.delivery_type, t.unloading_loc
    FROM tours t 
    LEFT JOIN vehicles v ON t.vehicle_id = v.id 
    WHERE t.vehicle_id IS NOT NULL AND v.id IS NULL
    ORDER BY t.loading_time DESC
    LIMIT 50
");
$badVehicleTours = $stmtBadVehicles->fetchAll();

if (empty($badVehicleTours)) {
    echo "<p class='text-success'>✓ Nema tura sa nepostojećim vozilima</p>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<p>Pronađeno " . count($badVehicleTours) . " tura sa nepostojećim vozilima:</p>";
    echo "<div class='table-responsive'>";
    echo "<table class='table table-sm'>";
    echo "<tr><th>Tura ID</th><th>Nepoznat Vehicle ID</th><th>Datum</th><th>Vreme</th><th>Utovar</th><th>Tip isporuke</th><th>Istovar</th></tr>";
    foreach ($badVehicleTours as $tour) {
        echo "<tr>";
        echo "<td><a href='edit_tour.php?id={$tour['id']}'>{$tour['id']}</a></td>";
        echo "<td><span class='badge bg-danger'>{$tour['vehicle_id']}</span></td>";
        echo "<td>{$tour['datum']}</td>";
        echo "<td>" . date('H:i', strtotime($tour['loading_time'])) . "</td>";
        echo "<td>" . htmlspecialchars(substr($tour['loading_loc'], 0, 30)) . "</td>";
        echo "<td>" . htmlspecialchars($tour['delivery_type']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($tour['unloading_loc'], 0, 40)) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
    echo "</div>";
}

// Analiza tipova isporuke
echo "<h5>Analiza tipova isporuke:</h5>";
$stmtDeliveryTypes = $pdo->query("
    SELECT delivery_type, COUNT(*) as broj_tura 
    FROM tours 
    WHERE DATE(loading_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY delivery_type 
    ORDER BY broj_tura DESC
");
$deliveryTypes = $stmtDeliveryTypes->fetchAll();

echo "<div class='row'>";
echo "<div class='col-md-6'>";
echo "<table class='table table-sm'>";
echo "<tr><th>Tip isporuke</th><th>Broj tura</th></tr>";
foreach ($deliveryTypes as $type) {
    $deliveryType = $type['delivery_type'] ?: '<em>Prazan/NULL</em>';
    $class = empty($type['delivery_type']) ? 'table-warning' : '';
    echo "<tr class='$class'><td>$deliveryType</td><td>{$type['broj_tura']}</td></tr>";
}
echo "</table>";
echo "</div>";
echo "</div>";

// Preporučene akcije
echo "<h4>Preporučene akcije za rešavanje:</h4>";
echo "<div class='alert alert-info'>";
echo "<ol>";
echo "<li><strong>Immediate Fix:</strong> Idite na <a href='bulk_update2.php' class='btn btn-sm btn-primary'>Bulk Update</a> da masovno ispravite loše ture</li>";
echo "<li><strong>Kreiranje nedostajućih vozača:</strong> <a href='add_driver.php' class='btn btn-sm btn-success'>Dodaj vozača</a></li>";
echo "<li><strong>Kreiranje nedostajućih vozila:</strong> <a href='add_vehicle.php' class='btn btn-sm btn-success'>Dodaj vozilo</a></li>";
echo "<li><strong>Backup baze:</strong> Napravite backup baze pre bilo kakvih izmena</li>";
echo "</ol>";
echo "</div>";

// Statistike po vozačima i vozilima
echo "<h4>Trenutno stanje baze podataka:</h4>";
echo "<div class='row'>";

// Vozači
echo "<div class='col-md-6'>";
echo "<div class='card'>";
echo "<div class='card-header'>Vozači</div>";
echo "<div class='card-body'>";
$driversStats = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM drivers) as ukupno_vozaca,
        (SELECT COUNT(DISTINCT driver_id) FROM tours WHERE driver_id IS NOT NULL) as aktivnih_vozaca,
        (SELECT COUNT(DISTINCT driver_id) FROM tours WHERE DATE(loading_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND driver_id IS NOT NULL) as aktivnih_30_dana
")->fetch();

echo "<p>Ukupno vozača u sistemu: <strong>{$driversStats['ukupno_vozaca']}</strong></p>";
echo "<p>Vozača koji imaju ture: <strong>{$driversStats['aktivnih_vozaca']}</strong></p>";
echo "<p>Aktivnih posledjih 30 dana: <strong>{$driversStats['aktivnih_30_dana']}</strong></p>";
echo "</div>";
echo "</div>";
echo "</div>";

// Vozila
echo "<div class='col-md-6'>";
echo "<div class='card'>";
echo "<div class='card-header'>Vozila</div>";
echo "<div class='card-body'>";
$vehiclesStats = $pdo->query("
    SELECT 
        (SELECT COUNT(*) FROM vehicles) as ukupno_vozila,
        (SELECT COUNT(DISTINCT vehicle_id) FROM tours WHERE vehicle_id IS NOT NULL) as aktivnih_vozila,
        (SELECT COUNT(DISTINCT vehicle_id) FROM tours WHERE DATE(loading_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND vehicle_id IS NOT NULL) as aktivnih_30_dana
")->fetch();

echo "<p>Ukupno vozila u sistemu: <strong>{$vehiclesStats['ukupno_vozila']}</strong></p>";
echo "<p>Vozila koja imaju ture: <strong>{$vehiclesStats['aktivnih_vozila']}</strong></p>";
echo "<p>Aktivnih posledjih 30 dana: <strong>{$vehiclesStats['aktivnih_30_dana']}</strong></p>";
echo "</div>";
echo "</div>";
echo "</div>";

echo "</div>";

require 'footer.php';
?>
