<?php
// update_estimated_km.php - Automatsko ažuriranje procenjene kilometraže za sve ture
require_once 'config.php';

date_default_timezone_set('Europe/Belgrade');

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents('estimated_km_log.txt', "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

function calculateEstimatedKmForTour($tourId, $unloadingText) {
    global $pdo;

    if (empty($unloadingText)) {
        return 0;
    }

    // Parse unloading locations using same logic as map_vehicle.php
    $raw_points = [];
    $parts = preg_split('/[;,\n]+/', $unloadingText);
    foreach ($parts as $p) { 
        $p = trim($p); 
        if ($p !== '') $raw_points[] = $p; 
    }

    // Normalize codes: remove "TMO", extract 6 digits (3+3)
    $codes = [];
    foreach ($raw_points as $p) {
        $p = preg_replace('/^\s*TMO\s*/i', '', $p);
        $p = preg_replace('/\s+/', ' ', $p);
        if (preg_match('/(\d{3})\D*(\d{3})/', $p, $m)) {
            $digits = $m[1] . $m[2];
            $codes[] = $digits;
        }
    }
    $codes = array_values(array_unique($codes));
    
    // Debug: prikažemo parsovane kodove za turu $tourId
    if (count($codes) > 0) {
        logMessage("Tura $tourId: parsovani kodovi: " . implode(', ', $codes));
    } else {
        logMessage("Tura $tourId: nisu pronađeni validni kodovi u: $unloadingText");
        return 0;
    }

    if (empty($codes)) {
        return 0;
    }

    // Get coordinates for these object codes - koristi kanonski pristup kao u get_vehicle_markers.php
    $placeholders = str_repeat('?,', count($codes) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT sifra, lat, lng,
               REPLACE(REPLACE(REPLACE(sifra,'-',''),' ',''), '/', '') AS canon
        FROM objekti 
        WHERE REPLACE(REPLACE(REPLACE(sifra,'-',''),' ',''), '/', '') IN ($placeholders)
        AND lat IS NOT NULL AND lng IS NOT NULL
        ORDER BY sifra
    ");
    $stmt->execute($codes);
    $markers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: proveravamo da li su pronađeni objekti
    logMessage("Tura $tourId: pronađeno " . count($markers) . " objekata od " . count($codes) . " kodova");
    if (count($markers) > 0) {
        $foundCodes = array_column($markers, 'sifra');
        logMessage("Tura $tourId: pronađeni objekti: " . implode(', ', $foundCodes));
    }
    
    // Proveravamo i koje kodove nismo našli
    if (count($markers) < count($codes)) {
        $foundCodes = array_column($markers, 'sifra');
        $missingCodes = array_diff($codes, $foundCodes);
        logMessage("Tura $tourId: nedostaju objekti: " . implode(', ', $missingCodes));
    }

    if (empty($markers)) {
        return 0;
    }

    // Nova Pazova Delhaize magacin coordinates
    $warehouseCoords = [44.97333346991272, 20.227517754163006];

    // Create route: Nova Pazova -> all markers -> Nova Pazova
    $waypoints = [$warehouseCoords];
    foreach ($markers as $marker) {
        $waypoints[] = [$marker['lat'], $marker['lng']];
    }
    $waypoints[] = $warehouseCoords;

    // OSRM API call
    $coordinates = array_map(function($w) { return $w[1] . ',' . $w[0]; }, $waypoints);
    $osrmUrl = 'https://router.project-osrm.org/route/v1/driving/' . implode(';', $coordinates) . '?overview=false';

    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($osrmUrl, false, $context);
    if ($response === false) {
        logMessage("OSRM API greška za turu $tourId");
        return 0;
    }

    $data = json_decode($response, true);
    if (!isset($data['routes'][0]['distance'])) {
        logMessage("Nema rute podataka za turu $tourId");
        return 0;
    }

    return round($data['routes'][0]['distance'] / 1000); // Convert to kilometers
}

try {
    logMessage("Pokretanje ažuriranja procenjene kilometraže");

    // Proveravamo da li kolona estimated_km postoji
    try {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM tours LIKE 'estimated_km'");
        if ($checkColumn->rowCount() == 0) {
            logMessage("GREŠKA: Kolona 'estimated_km' ne postoji u tabeli 'tours'");
            logMessage("Potrebno je pokrenuti: ALTER TABLE tours ADD COLUMN estimated_km INT DEFAULT 0 AFTER km");
            exit;
        } else {
            logMessage("Kolona 'estimated_km' postoji u tabeli");
        }
    } catch (Exception $e) {
        logMessage("Greška pri proveri kolone: " . $e->getMessage());
        exit;
    }

    // Debug: prvo proveravamo ukupan broj tura
    $debugStmt = $pdo->prepare("SELECT COUNT(*) as total FROM tours");
    $debugStmt->execute();
    $totalTours = $debugStmt->fetchColumn();
    logMessage("Ukupno tura u bazi: " . $totalTours);

    // Debug: proveravamo ture sa unloading_loc
    $debugStmt2 = $pdo->prepare("SELECT COUNT(*) as with_unloading FROM tours WHERE unloading_loc IS NOT NULL AND unloading_loc != ''");
    $debugStmt2->execute();
    $toursWithUnloading = $debugStmt2->fetchColumn();
    logMessage("Tura sa unloading_loc: " . $toursWithUnloading);

    // Debug: proveravamo ture bez estimated_km
    $debugStmt3 = $pdo->prepare("SELECT COUNT(*) as without_estimated FROM tours WHERE (estimated_km = 0 OR estimated_km IS NULL)");
    $debugStmt3->execute();
    $toursWithoutEstimated = $debugStmt3->fetchColumn();
    logMessage("Tura bez estimated_km: " . $toursWithoutEstimated);

    // Dohvati sve ture od 01.08.2025 koje nemaju procenjenu kilometražu
    $stmt = $pdo->prepare("
        SELECT id, unloading_loc, estimated_km, loading_time as date,
               TIMESTAMPDIFF(HOUR, loading_time, NOW()) as hours_diff
        FROM tours 
        WHERE (estimated_km = 0 OR estimated_km IS NULL)
        AND unloading_loc IS NOT NULL 
        AND unloading_loc != ''
        AND DATE(loading_time) >= '2025-08-01'
        ORDER BY loading_time DESC
        LIMIT 200
    ");
    $stmt->execute();
    $tours = $stmt->fetchAll(PDO::FETCH_ASSOC);

    logMessage("Pronađeno je " . count($tours) . " tura za obradu");

    // Debug: prikaži prvih nekoliko tura
    foreach (array_slice($tours, 0, 3) as $debugTour) {
        logMessage("Debug tura ID {$debugTour['id']}: estimated_km={$debugTour['estimated_km']}, unloading_loc length=" . strlen($debugTour['unloading_loc']));
    }

    $updated = 0;
    foreach ($tours as $tour) {
        $estimatedKm = calculateEstimatedKmForTour($tour['id'], $tour['unloading_loc']);

        if ($estimatedKm > 0) {
            try {
                $updateStmt = $pdo->prepare("UPDATE tours SET estimated_km = ? WHERE id = ?");
                $result = $updateStmt->execute([$estimatedKm, $tour['id']]);
                
                if ($result) {
                    $updated++;
                    logMessage("Uspešno ažurirano za turu {$tour['id']}: {$estimatedKm} km");
                    
                    // Verifikuj da li je stvarno upisano u bazu
                    $verifyStmt = $pdo->prepare("SELECT estimated_km FROM tours WHERE id = ?");
                    $verifyStmt->execute([$tour['id']]);
                    $savedValue = $verifyStmt->fetchColumn();
                    logMessage("Verifikacija - tura {$tour['id']} ima estimated_km: {$savedValue}");
                } else {
                    logMessage("GREŠKA: UPDATE neuspešan za turu {$tour['id']}");
                }
            } catch (Exception $e) {
                logMessage("GREŠKA pri ažuriranju ture {$tour['id']}: " . $e->getMessage());
            }
        } else {
            logMessage("Nije moguće izračunati kilometražu za turu {$tour['id']} sa unloading_loc: " . $tour['unloading_loc']);
        }

        // Pauza između zahteva za OSRM API
        sleep(1);
    }

    logMessage("Ažuriranje završeno. Ukupno ažurirano: $updated tura");

} catch (Exception $e) {
    logMessage("Greška: " . $e->getMessage());
}
?>