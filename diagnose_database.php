
<?php
require 'header.php';
require 'config.php';

echo "<h2>Dijagnostika baze podataka</h2>";

// Proveri ture sa nepostojećim vozačima
echo "<h4>Ture sa nepostojećim vozačima:</h4>";
$stmtBadDrivers = $pdo->query("
    SELECT t.id, t.driver_id, t.loading_time, t.loading_loc 
    FROM tours t 
    LEFT JOIN drivers d ON t.driver_id = d.id 
    WHERE t.driver_id IS NOT NULL AND d.id IS NULL
    ORDER BY t.loading_time DESC
    LIMIT 20
");
$badDriverTours = $stmtBadDrivers->fetchAll();

if (empty($badDriverTours)) {
    echo "<p class='text-success'>✓ Sve ture imaju validne vozače</p>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<p>Pronađeno " . count($badDriverTours) . " tura sa nepostojećim vozačima:</p>";
    echo "<table class='table table-sm'>";
    echo "<tr><th>Tura ID</th><th>Driver ID</th><th>Vreme</th><th>Lokacija</th></tr>";
    foreach ($badDriverTours as $tour) {
        echo "<tr><td>{$tour['id']}</td><td>{$tour['driver_id']}</td><td>{$tour['loading_time']}</td><td>{$tour['loading_loc']}</td></tr>";
    }
    echo "</table>";
    echo "</div>";
}

// Proveri ture sa nepostojećim vozilima
echo "<h4>Ture sa nepostojećim vozilima:</h4>";
$stmtBadVehicles = $pdo->query("
    SELECT t.id, t.vehicle_id, t.loading_time, t.loading_loc 
    FROM tours t 
    LEFT JOIN vehicles v ON t.vehicle_id = v.id 
    WHERE t.vehicle_id IS NOT NULL AND v.id IS NULL
    ORDER BY t.loading_time DESC
    LIMIT 20
");
$badVehicleTours = $stmtBadVehicles->fetchAll();

if (empty($badVehicleTours)) {
    echo "<p class='text-success'>✓ Sve ture imaju validna vozila</p>";
} else {
    echo "<div class='alert alert-danger'>";
    echo "<p>Pronađeno " . count($badVehicleTours) . " tura sa nepostojećim vozilima:</p>";
    echo "<table class='table table-sm'>";
    echo "<tr><th>Tura ID</th><th>Vehicle ID</th><th>Vreme</th><th>Lokacija</th></tr>";
    foreach ($badVehicleTours as $tour) {
        echo "<tr><td>{$tour['id']}</td><td>{$tour['vehicle_id']}</td><td>{$tour['loading_time']}</td><td>{$tour['loading_loc']}</td></tr>";
    }
    echo "</table>";
    echo "</div>";
}

// Prikaži sve vozače
echo "<h4>Svi vozači u sistemu:</h4>";
$driversStmt = $pdo->query("SELECT id, name FROM drivers ORDER BY id");
$drivers = $driversStmt->fetchAll();
echo "<table class='table table-sm'>";
echo "<tr><th>ID</th><th>Ime</th></tr>";
foreach ($drivers as $driver) {
    echo "<tr><td>{$driver['id']}</td><td>{$driver['name']}</td></tr>";
}
echo "</table>";

// Prikaži sva vozila
echo "<h4>Sva vozila u sistemu:</h4>";
$vehiclesStmt = $pdo->query("SELECT id, plate FROM vehicles ORDER BY id");
$vehicles = $vehiclesStmt->fetchAll();
echo "<table class='table table-sm'>";
echo "<tr><th>ID</th><th>Registracija</th></tr>";
foreach ($vehicles as $vehicle) {
    echo "<tr><td>{$vehicle['id']}</td><td>{$vehicle['plate']}</td></tr>";
}
echo "</table>";

// Statistike
echo "<h4>Opšte statistike:</h4>";
$stats = [
    'Ukupno tura' => $pdo->query("SELECT COUNT(*) FROM tours")->fetchColumn(),
    'Ture sa vozačem' => $pdo->query("SELECT COUNT(*) FROM tours WHERE driver_id IS NOT NULL")->fetchColumn(),
    'Ture sa vozilom' => $pdo->query("SELECT COUNT(*) FROM tours WHERE vehicle_id IS NOT NULL")->fetchColumn(),
    'Ukupno vozača' => $pdo->query("SELECT COUNT(*) FROM drivers")->fetchColumn(),
    'Ukupno vozila' => $pdo->query("SELECT COUNT(*) FROM vehicles")->fetchColumn(),
];

echo "<table class='table table-sm'>";
foreach ($stats as $label => $value) {
    echo "<tr><td>$label</td><td><strong>$value</strong></td></tr>";
}
echo "</table>";

// Predlog rešenja
if (!empty($badDriverTours) || !empty($badVehicleTours)) {
    echo "<div class='alert alert-warning'>";
    echo "<h5>Predlog rešenja:</h5>";
    echo "<p>1. Idite na <a href='bulk_update2.php'>bulk_update2.php</a> da biste masovno ažurirali ture</p>";
    echo "<p>2. Ili ručno izmeni problematične ture preko <a href='tours.php'>tours.php</a></p>";
    echo "<p>3. Kreirajte nedostajuće vozače/vozila ako je potrebno</p>";
    echo "</div>";
}

require 'footer.php';
?>
