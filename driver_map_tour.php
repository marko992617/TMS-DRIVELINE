
<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php';

$driver_id = $_SESSION['driver_id'] ?? 0;
$tour_id = isset($_GET['tour_id']) ? intval($_GET['tour_id']) : 0;
if ($tour_id <= 0 || $driver_id <= 0) {
    echo "Tura nije definisana ili vozač nije prijavljen.";
    exit;
}

$stmt = $pdo->prepare("SELECT unloading_loc, ors_id, DATE(loading_time) AS datum, TIME(loading_time) AS vreme_utovara FROM tours WHERE id = :tour_id AND driver_id = :driver_id");
$stmt->execute(['tour_id' => $tour_id, 'driver_id' => $driver_id]);

$tour = $stmt->fetch(PDO::FETCH_ASSOC);
$datum = $tour['datum'] ?? '';
$vreme_utovara = $tour['vreme_utovara'] ?? '';
$ors_id = $tour['ors_id'] ?? '';

if (!$tour || empty($tour['unloading_loc'])) {
    echo "Tura nije pronađena ili nije dodeljena ovom vozaču.";
    exit;
}

// Parsiranje šifara objekata
$codes = array_map('trim', preg_split('/[;,\n]+/', $tour['unloading_loc']));
$filtered = [];
foreach ($codes as $code) {
    $code = str_ireplace('TMO', '', $code);
    $code = trim($code);
    if (preg_match('/^\d{3}\s\d{3}$/', $code)) {
        $filtered[] = $code;
    }
}

if (count($filtered) === 0) {
    echo "Nema validnih objekata u turi.";
    exit;
}

// Dohvatanje podataka o objektima
$placeholders = implode(',', array_fill(0, count($filtered), '?'));
$query = $pdo->prepare("SELECT sifra, naziv, lat, lng, adresa FROM objekti WHERE sifra IN ($placeholders)");
$query->execute($filtered);
$results = $query->fetchAll(PDO::FETCH_ASSOC);

// Ređanje po redosledu iz unloading_loc
$indexed = [];
foreach ($results as $row) {
    $indexed[$row['sifra']] = $row;
}
$ordered_locations = [];
foreach ($filtered as $code) {
    if (isset($indexed[$code])) {
        $ordered_locations[] = $indexed[$code];
    }
}
?>

<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mapa Ture #<?= htmlspecialchars($tour_id) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        #map {
            height: 500px;
            width: 100%;
            border-radius: 0.375rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .delivery-stop {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            margin-bottom: 0.75rem;
            padding: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            cursor: pointer;
            transition: all 0.15s ease-in-out;
        }
        
        .delivery-stop:hover {
            background-color: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        }
        
        .delivery-stop h5 {
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }
        
        .delivery-stop .text-muted {
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        .tour-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 1.5rem;
            border-radius: 0.375rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.25rem 0.5rem rgba(0, 0, 0, 0.1);
        }
        
        .tour-header h2 {
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
        }
        
        .tour-meta {
            font-size: 0.95rem;
            opacity: 0.9;
        }
        
        .section-title {
            color: #495057;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #007bff;
        }
        
        .back-btn {
            background: linear-gradient(135deg, #6c757d, #5a6268);
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 0.375rem;
            color: white;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
        }
        
        .back-btn:hover {
            background: linear-gradient(135deg, #5a6268, #545b62);
            color: white;
            text-decoration: none;
            transform: translateY(-1px);
        }
        
        @media (max-width: 768px) {
            #map {
                height: 400px;
            }
            
            .tour-header {
                padding: 1rem;
            }
            
            .tour-header h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="driver_dashboard.php">TMS Vozač</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#driverNav" aria-controls="driverNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="driverNav">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="driver_dashboard.php">Ture</a></li>
                <li class="nav-item"><a class="nav-link" href="drivers_logout.php">Logout</a></li>
            </ul>
        </div>
    </div>
</nav>

<main class="container my-4 flex-grow-1">
    <!-- Tour Header -->
    <div class="tour-header">
        <div class="d-flex justify-content-between align-items-start flex-wrap">
            <div>
                <h2><i class="fas fa-route me-2"></i>Tura #<?= htmlspecialchars($tour_id) ?></h2>
                <div class="tour-meta">
                    <i class="fas fa-clock me-1"></i>Utovar: <?= htmlspecialchars($vreme_utovara) ?> | 
                    <i class="fas fa-calendar me-1"></i>Datum: <?= htmlspecialchars($datum) ?> | 
                    <i class="fas fa-barcode me-1"></i>ORS: <?= htmlspecialchars($ors_id) ?>
                </div>
            </div>
            <a href="driver_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i>Nazad
            </a>
        </div>
    </div>

    <!-- Plan isporuke sekcija -->
    <div class="row">
        <div class="col-12">
            <h3 class="section-title">
                <i class="fas fa-clipboard-list me-2"></i>Plan isporuke
            </h3>
            
            <?php foreach ($ordered_locations as $i => $loc): ?>
                <div class="delivery-stop" onclick="focusOnLocation(<?= $loc['lat'] ?>, <?= $loc['lng'] ?>, '<?= htmlspecialchars($loc['naziv']) ?>')">
                    <h5>
                        <span class="badge bg-primary me-2"><?= ($i + 1) ?></span>
                        <?= htmlspecialchars($loc['naziv']) ?>
                    </h5>
                    <div class="text-muted">
                        <i class="fas fa-barcode me-1"></i>Šifra: <?= htmlspecialchars($loc['sifra']) ?>
                    </div>
                    <div class="text-muted">
                        <i class="fas fa-map-marker-alt me-1"></i>Adresa: <?= htmlspecialchars($loc['adresa']) ?>
                    </div>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation(); openInGoogleMaps(<?= $loc['lat'] ?>, <?= $loc['lng'] ?>)">
                            <i class="fas fa-external-link-alt me-1"></i>Otvori u Google Maps
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Mapa sekcija -->
    <div class="row mt-4">
        <div class="col-12">
            <h3 class="section-title">
                <i class="fas fa-map me-2"></i>Mapa lokacija
            </h3>
            <div id="map"></div>
        </div>
    </div>
</main>

<footer class="bg-light text-center py-3 mt-auto">
    <small>&copy; <?=date('Y')?> TransportAPP - Kreirao i implementirao - Copyright Marko Mladenović</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map
    var map = L.map('map');
    var bounds = L.latLngBounds();

    // Add markers
    var markers = <?php echo json_encode($ordered_locations); ?>;
    var markerObjects = [];
    
    markers.forEach(function(loc, index) {
        var latlng = [parseFloat(loc.lat), parseFloat(loc.lng)];
        var marker = L.marker(latlng).addTo(map);
        
        var popupContent = "<div style='font-size: 14px;'>" +
            "<b>" + (index + 1) + ". " + loc.naziv + "</b><br>" +
            "<small class='text-muted'>Šifra: " + loc.sifra + "</small><br>" +
            loc.adresa +
            "</div>";
        
        marker.bindPopup(popupContent);
        markerObjects.push(marker);
        bounds.extend(latlng);
    });

    // Fit map to bounds
    if (markers.length > 0) {
        map.fitBounds(bounds, { padding: [20, 20] });
    } else {
        map.setView([44.8125, 20.4612], 11);
    }

    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    // Global functions
    window.focusOnLocation = function(lat, lng, name) {
        map.setView([lat, lng], 16);
        
        // Find and open popup for this location
        markerObjects.forEach(function(marker) {
            var markerPos = marker.getLatLng();
            if (Math.abs(markerPos.lat - lat) < 0.0001 && Math.abs(markerPos.lng - lng) < 0.0001) {
                marker.openPopup();
            }
        });
    };

    window.openInGoogleMaps = function(lat, lng) {
        var url = 'https://www.google.com/maps/search/?api=1&query=' + lat + ',' + lng;
        window.open(url, '_blank');
    };

    // Responsive map resize
    setTimeout(() => {
        map.invalidateSize();
        if (markers.length > 0) {
            map.fitBounds(bounds, { padding: [20, 20] });
        }
    }, 200);
});
</script>

</body>
</html>
