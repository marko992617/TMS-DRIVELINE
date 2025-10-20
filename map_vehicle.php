<?php
// map_vehicle.php — datum + registarski broj -> lista tura -> klik prikazuje istovare na mapi
require __DIR__ . '/db.php';
date_default_timezone_set('Europe/Belgrade');

function tableExists(PDO $pdo, $table) {
    try { $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table)); return (bool)$stmt->fetchColumn(); }
    catch (Exception $e) { return false; }
}

// Registarske oznake - samo vozila koja imaju ture za odabrani datum
$plates = [];
$today = date('Y-m-d');
$selectedDate = $_GET['date'] ?? $today;
$selectedClient = $_GET['client'] ?? '';

// Get clients for filter
$clients = [];
if (tableExists($pdo, 'clients')) {
    $q = $pdo->prepare("SELECT DISTINCT c.id, c.name FROM clients c INNER JOIN tours t ON c.id = t.client_id WHERE t.date = ? ORDER BY c.name");
    $q->execute([$selectedDate]);
    $clients = $q->fetchAll(PDO::FETCH_ASSOC);
}

if (tableExists($pdo, 'vehicles')) {
    $whereClause = "WHERE v.plate IS NOT NULL AND v.plate<>'' AND t.date = ?";
    $params = [$selectedDate];
    
    if ($selectedClient) {
        $whereClause .= " AND t.client_id = ?";
        $params[] = $selectedClient;
    }
    
    $q = $pdo->prepare("
        SELECT DISTINCT v.id, v.plate 
        FROM vehicles v
        INNER JOIN tours t ON v.id = t.vehicle_id
        {$whereClause}
        ORDER BY v.plate
    ");
    $q->execute($params);
    $plates = $q->fetchAll(PDO::FETCH_ASSOC);
} else {
    try {
        $whereClause = "WHERE vehicle_plate IS NOT NULL AND vehicle_plate<>'' AND date = ?";
        $params = [$selectedDate];
        
        if ($selectedClient) {
            $whereClause .= " AND client_id = ?";
            $params[] = $selectedClient;
        }
        
        $q = $pdo->prepare("
            SELECT DISTINCT vehicle_plate AS plate 
            FROM tours 
            {$whereClause}
            ORDER BY vehicle_plate
        ");
        $q->execute($params);
        $plates = array_map(function($r){ return ['id'=>null, 'plate'=>$r['plate']]; }, $q->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) { $plates = []; }
}
?>
<!doctype html>
<html lang="sr">
<head>
  <meta charset="utf-8">
  <title>Mapa istovara po vozilu i datumu</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { box-sizing: border-box; }
    html, body { 
      height: 100%; 
      margin: 0; 
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
      background: #f8fafc;
    }

    .layout { 
      display: grid; 
      grid-template-columns: 400px 1fr; 
      height: 100vh; 
      gap: 0;
    }

    @media (max-width: 1024px) {
      .layout { 
        grid-template-columns: 1fr; 
        grid-template-rows: auto 1fr;
      }
      .sidebar { 
        max-height: 50vh; 
        overflow-y: auto;
      }
    }

    .sidebar { 
      background: white; 
      border-right: 1px solid #e2e8f0; 
      padding: 24px; 
      overflow-y: auto;
      box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    }

    .map { 
      height: 100%; 
      position: relative;
    }

    h2 { 
      color: #1e293b; 
      font-size: 24px; 
      font-weight: 700; 
      margin: 0 0 24px 0;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .nav { 
      margin-bottom: 24px; 
      padding: 16px;
      background: #f1f5f9;
      border-radius: 12px;
    }

    .nav a { 
      color: #3b82f6; 
      text-decoration: none; 
      margin-right: 16px;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 6px;
      transition: color 0.2s;
    }

    .nav a:hover { 
      color: #1d4ed8; 
    }

    .card { 
      background: white; 
      border: 1px solid #e2e8f0; 
      border-radius: 16px; 
      padding: 20px; 
      margin-bottom: 20px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }

    .card-header { 
      font-weight: 600; 
      font-size: 18px;
      color: #1e293b; 
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .form-group { 
      margin-bottom: 20px; 
    }

    label { 
      display: block; 
      margin-bottom: 8px; 
      font-weight: 600;
      color: #374151;
      font-size: 14px;
    }

    input[type="date"], select, button { 
      width: 100%;
      padding: 12px 16px; 
      border: 2px solid #e2e8f0; 
      border-radius: 12px;
      font-size: 16px;
      font-family: inherit;
      transition: all 0.2s ease;
      background: white;
    }

    input[type="date"]:focus, select:focus { 
      outline: none;
      border-color: #3b82f6;
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Poboljšanje za datum input */
    input[type="date"] {
      position: relative;
      color: #1e293b;
      font-weight: 500;
    }

    input[type="date"]::-webkit-calendar-picker-indicator {
      background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="%233b82f6"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM7 10h5v5H7z"/></svg>') no-repeat center;
      cursor: pointer;
      padding: 4px;
    }

    .date-display { 
      font-size: 12px; 
      color: #64748b; 
      margin-top: 6px;
      font-style: italic;
    }

    button { 
      background: linear-gradient(135deg, #3b82f6, #1d4ed8);
      color: white; 
      border: none;
      cursor: pointer; 
      font-weight: 600;
      transition: all 0.2s ease;
    }

    button:hover { 
      background: linear-gradient(135deg, #1d4ed8, #1e40af);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
    }

    button:active {
      transform: translateY(0);
    }

    .tours-list, .locations-list { 
      list-style: none; 
      margin: 0; 
      padding: 0; 
    }

    .tours-list li, .locations-list li { 
      padding: 16px; 
      border: 2px solid #f1f5f9; 
      border-radius: 12px; 
      margin-bottom: 12px; 
      cursor: pointer; 
      background: #fafbfc;
      transition: all 0.2s ease;
    }

    .tours-list li:hover, .locations-list li:hover { 
      background: #f8fafc;
      border-color: #3b82f6;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .tour-header { 
      font-weight: 700;
      color: #1e293b;
      font-size: 16px;
      margin-bottom: 8px;
    }

    .driver-name { 
      color: #059669; 
      font-weight: 600;
      margin-bottom: 4px;
    }

    .time-info { 
      color: #6366f1; 
      font-size: 13px;
      margin-bottom: 8px;
    }

    .route-info {
      background: #f0f9ff;
      border: 1px solid #bae6fd;
      border-radius: 8px;
      padding: 12px;
      margin-top: 8px;
    }

    .route-metric {
      display: inline-block;
      margin-right: 16px;
      color: #0369a1;
      font-size: 12px;
      font-weight: 600;
    }

    .route-button {
      transition: all 0.2s ease;
    }

    .route-button:hover {
      background: #059669 !important;
      transform: translateY(-1px);
    }

    .tags { 
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
      margin-top: 8px;
    }

    .pill { 
      display: inline-block; 
      padding: 4px 10px; 
      border-radius: 20px; 
      background: #e0f2fe;
      color: #0891b2;
      font-size: 12px;
      font-weight: 500;
      border: 1px solid #7dd3fc;
    }

    .muted { 
      color: #64748b; 
      font-size: 14px;
      font-style: italic;
    }

    .numbered-marker { 
      background: transparent !important; 
      border: none !important; 
    }

    .location-item {
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .location-number {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 28px;
      height: 28px;
      background: #3498db;
      color: white;
      border-radius: 50%;
      font-size: 12px;
      font-weight: bold;
      flex-shrink: 0;
    }

    .location-info {
      flex: 1;
      min-width: 0;
    }

    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #64748b;
    }

    .empty-state i {
      font-size: 48px;
      margin-bottom: 16px;
      opacity: 0.5;
    }

    @media (max-width: 768px) {
      .layout {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr;
      }

      .sidebar {
        max-height: 60vh;
        padding: 16px;
      }

      h2 {
        font-size: 20px;
      }

      .card {
        padding: 16px;
      }
    }
  </style>
</head>
<body>
<div class="layout">
  <aside class="sidebar">
    <h2><i class="fas fa-map-marked-alt"></i>Mapa istovara</h2>

    <div class="nav">
      <a href="index.html"><i class="fas fa-home"></i>Početna</a>
    </div>

    <div class="card">
      <div class="card-header">
        <i class="fas fa-filter"></i>Filteri
      </div>

      <div class="form-group">
        <label for="dt"><i class="fas fa-calendar-alt"></i> Datum</label>
        <input type="date" id="dt" value="<?= htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8') ?>" onchange="updateVehicleList()">
        <div class="date-display">
          Izabrani datum: <?= date('d.m.Y', strtotime($selectedDate)) ?>
        </div>
      </div>

      <div class="form-group">
        <label for="client"><i class="fas fa-building"></i> Klijent</label>
        <select id="client" onchange="updateVehicleList()">
          <option value="">-- svi klijenti --</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= htmlspecialchars($c['id'], ENT_QUOTES, 'UTF-8') ?>" <?= $c['id'] == $selectedClient ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label for="veh"><i class="fas fa-truck"></i> Registarski broj vozila</label>
        <select id="veh">
          <option value="">-- izaberi vozilo --</option>
          <?php foreach ($plates as $v): ?>
            <option value="<?= htmlspecialchars($v['plate'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($v['plate'], ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button id="btnLoad">
        <i class="fas fa-search"></i> Prikaži dostupne ture
      </button>
    </div>

    <div class="card">
      <div class="card-header">
        <i class="fas fa-list"></i>Dostupne ture
      </div>
      <ul id="tours" class="tours-list"></ul>
      <div class="muted">Klikni na turu da prikažeš istovare na mapi.</div>
    </div>

    <div class="card">
      <div class="card-header">
        <i class="fas fa-map-pin"></i>Sa koordinatama
      </div>
      <ul id="list" class="locations-list"></ul>

      <div class="card-header" style="margin-top: 20px;">
        <i class="fas fa-exclamation-triangle"></i>Bez koordinata
      </div>
      <div class="muted" style="margin-bottom: 10px;">
        <a href="geocode_missing.php" target="_blank" style="color: #3b82f6;">Pokreni geokodiranje</a> za objekte bez lat/lng.
      </div>
      <ul id="missing" class="locations-list"></ul>
    </div>
  </aside>

  <main>
    <div id="map" class="map"></div>
  </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
function createNumberedMarker(number, color = '#e74c3c') {
  const html = `
    <div style="
      background: linear-gradient(135deg, ${color}, ${color}cc);
      color: white;
      font-weight: bold;
      border-radius: 50%;
      width: 36px;
      height: 36px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      border: 3px solid white;
      box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      line-height: 1;
    ">${number}</div>
  `;
  return L.divIcon({ 
    html, 
    className: 'numbered-marker', 
    iconSize: [36, 36], 
    iconAnchor: [18, 18],
    popupAnchor: [0, -18]
  });
}

let map = L.map('map').setView([44.8125, 20.4612], 11);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  maxZoom: 19, 
  attribution: '&copy; <a href="https://openstreetmap.org">OpenStreetMap</a>'
}).addTo(map);

let markersLayer = L.layerGroup().addTo(map);

function escapeHtml(s){
  return (s??'').toString().replace(/[&<>"']/g, m=>({ 
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' 
  }[m]));
}

function fmtPopup(m){
  const parts=[];
  parts.push('<div style="font-weight:600;color:#1e293b;margin-bottom:8px;">'+escapeHtml(m.title)+'</div>');
  if (m.addr) parts.push('<div style="color:#64748b;margin-bottom:4px;"><i class="fas fa-map-marker-alt" style="margin-right:6px;"></i>'+escapeHtml(m.addr)+'</div>');
  if (m.sifra) parts.push('<div style="color:#64748b;font-size:12px;"><strong>Šifra:</strong> '+escapeHtml(m.sifra)+'</div>');
  if (m.info) parts.push('<div style="color:#64748b;font-size:12px;margin-top:4px;">'+escapeHtml(m.info)+'</div>');
  return parts.join('');
}

async function updateVehicleList() {
  const dt = document.getElementById('dt').value;
  const client = document.getElementById('client').value;
  if (!dt) return;
  
  let url = '?date=' + encodeURIComponent(dt);
  if (client) {
    url += '&client=' + encodeURIComponent(client);
  }
  window.location.href = url;
}

async function loadTours() {
  const dt = document.getElementById('dt').value;
  const plate = document.getElementById('veh').value;
  const client = document.getElementById('client').value;
  if (!dt || !plate) { 
    alert('Molimo izaberite datum i registarski broj.'); 
    return; 
  }

  try {
    let url = 'get_vehicle_tours.php?date='+encodeURIComponent(dt)+'&plate='+encodeURIComponent(plate);
    if (client) {
      url += '&client_id=' + encodeURIComponent(client);
    }
    const res = await fetch(url);
    const data = await res.json();
    const list = document.getElementById('tours');
    list.innerHTML = '';

    if (!data.ok) { 
      alert(data.error||'Došlo je do greške'); 
      return; 
    }

    if (data.tours.length===0) {
      list.innerHTML = '<div class="empty-state"><i class="fas fa-inbox"></i><div>Nema tura za izabrane parametre.</div></div>';
      return;
    }

    data.tours.forEach(async (t) => {
      const li = document.createElement('li');
      li.innerHTML = `
        <div class="tour-header">Tura #${escapeHtml(t.id)}</div>
        ${t.driver_name ? '<div class="driver-name"><i class="fas fa-user"></i> Vozač: '+escapeHtml(t.driver_name)+'</div>' : ''}
        ${t.loading_time ? '<div class="time-info"><i class="fas fa-clock"></i> Vreme utovara: '+escapeHtml(t.loading_time)+'</div>' : ''}
        ${t.ors_id ? '<div class="muted">ORS: '+escapeHtml(t.ors_id)+'</div>' : ''}
        <div class="tags">
          ${t.delivery_type ? '<span class="pill">'+escapeHtml(t.delivery_type)+'</span>' : ''}
          ${t.count_points ? '<span class="pill">'+t.count_points+' istovara</span>' : ''}
        </div>
        <div class="route-info" id="route-${t.id}">
          <i class="fas fa-spinner fa-spin"></i> Računam projektnu kilometražu...
        </div>
        <div style="margin-top: 8px;">
          <button class="route-button" onclick="showRouteDetails(${t.id})" style="
            background: #10b981; color: white; border: none; padding: 6px 12px; 
            border-radius: 6px; font-size: 12px; cursor: pointer;">
            <i class="fas fa-route"></i> Obračun i putanja
          </button>
        </div>
      `;
      li.addEventListener('click', ()=> loadTourMarkers(t.id));
      list.appendChild(li);

      // Calculate route metrics
      calculateTourRoute(t.id);
    });
  } catch(e) {
    alert('Greška pri učitavanju tura: ' + e.message);
  }
}

async function loadTourMarkers(tourId){
  try {
    const res = await fetch('get_tour_markers.php?tour_id='+encodeURIComponent(tourId));
    const data = await res.json();

    if (!data.ok) { 
      alert(data.error||'Greška'); 
      return; 
    }
    
    // Prikaži rutu automatski kada se učitaju markeri
    if (routeCalculations[tourId]) {
      drawRouteOnMap(tourId);
    }

    const list = document.getElementById('list');
    const missing = document.getElementById('missing');
    list.innerHTML = '';
    missing.innerHTML = '';

    markersLayer.clearLayers();
    let bounds = [];

    data.markers.forEach((m, index) => {
      const markerNumber = index + 1;
      const markerIcon = createNumberedMarker(markerNumber, '#3498db');
      const marker = L.marker([m.lat, m.lng], { icon: markerIcon }).bindPopup(fmtPopup(m));
      marker.addTo(markersLayer);
      bounds.push([m.lat, m.lng]);

      const li = document.createElement('li');
      li.innerHTML = `
        <div class="location-item">
          <div class="location-number">${markerNumber}</div>
          <div class="location-info">
            <div style="font-weight:600;color:#1e293b;">${escapeHtml(m.title || m.sifra)}</div>
            ${m.addr ? '<div style="color:#64748b;font-size:13px;">'+escapeHtml(m.addr)+'</div>' : ''}
          </div>
        </div>
      `;
      list.appendChild(li);
    });

    data.missing.forEach(m=>{
      const li = document.createElement('li');
      li.innerHTML = `
        <div style="color:#ef4444;">
          <i class="fas fa-exclamation-circle" style="margin-right:8px;"></i>
          ${escapeHtml(m.title || m.sifra)}
          ${m.addr ? '<div style="font-size:12px;margin-top:4px;">'+escapeHtml(m.addr)+'</div>' : ''}
        </div>
      `;
      missing.appendChild(li);
    });

    if (bounds.length>0) {
      map.fitBounds(bounds, {padding:[30,30]});
    }
  } catch(e) {
    alert('Greška pri učitavanju markera: ' + e.message);
  }
}

document.getElementById('btnLoad').addEventListener('click', loadTours);

// Nova Pazova Delhaize magacin koordinate
const WAREHOUSE_COORDS = [44.971966938665076, 20.228534192549727]; // Nova Pazova, tačne koordinate Delhaize magacina

let routeCalculations = {}; // Globalna varijabla za čuvanje proračuna

// Funkcija za dohvatanje vremena utovara ture iz baze
async function getTourLoadingTime(tourId) {
  try {
    const res = await fetch('get_tour_loading_time.php?tour_id='+encodeURIComponent(tourId));
    const data = await res.json();
    return data.loading_time || '00:00';
  } catch (error) {
    console.error('Greška pri dohvatanju vremena utovara:', error);
    return '00:00';
  }
}

async function calculateTourRoute(tourId) {
  try {
    // Dobij markere za turu
    const res = await fetch('get_tour_markers.php?tour_id='+encodeURIComponent(tourId));
    const data = await res.json();

    if (!data.ok || data.markers.length === 0) {
      document.getElementById('route-'+tourId).innerHTML = '<span class="route-metric">Nema podataka za rutu</span>';
      return;
    }

    // Kreiraj rutu: Nova Pazova -> sva istovarna mesta u redosledu -> Nova Pazova
    const waypoints = [WAREHOUSE_COORDS];
    data.markers.forEach(m => waypoints.push([m.lat, m.lng]));
    waypoints.push(WAREHOUSE_COORDS);

    // OSRM API poziv za celu rutu
    const coordinates = waypoints.map(w => w[1] + ',' + w[0]).join(';');
    const osrmUrl = `https://router.project-osrm.org/route/v1/driving/${coordinates}?overview=full&steps=true&geometries=geojson`;

    const osrmRes = await fetch(osrmUrl);
    const osrmData = await osrmRes.json();

    if (osrmData.code !== 'Ok') {
      throw new Error('OSRM greška: ' + osrmData.message);
    }

    const route = osrmData.routes[0];
    const totalDistance = Math.round(route.distance / 1000); // u kilometrima
    const totalDrivingTime = Math.round(route.duration / 60); // u minutima

    // Logika vremena:
    // 1. Početak od vremena utovara iz baze + 2 sata za utovar
    // 2. Vožnja i istovar po objektima: 10 min po objektu
    // 3. Povratak u Novu Pazovu
    
    // Dohvati vreme utovara iz baze za ovu turu
    const tourStartTime = await getTourLoadingTime(tourId);
    const loadingTime = 120; // 2 sata utovara
    const unloadingTime = data.markers.length * 10; // 10 min po objektu
    const totalTime = loadingTime + totalDrivingTime + unloadingTime;

    // Izračunaj vremena za svaki segment
    const segments = [];
    if (route.legs && route.legs.length > 0) {
      let cumulativeTime = loadingTime; // Počinjemo posle utovara
      
      // Nova Pazova do prvog objekta
      segments.push({
        from: 'Nova Pazova (utovar)',
        to: data.markers[0]?.title || 'Prvi objekat',
        duration: Math.round(route.legs[0].duration / 60),
        distance: Math.round(route.legs[0].distance / 1000),
        arrivalTime: cumulativeTime + Math.round(route.legs[0].duration / 60)
      });
      
      cumulativeTime += Math.round(route.legs[0].duration / 60) + 10; // +10 min za istovar
      
      // Između objekata
      for (let i = 1; i < data.markers.length; i++) {
        const legDuration = Math.round(route.legs[i].duration / 60);
        segments.push({
          from: data.markers[i-1]?.title || `Objekat ${i}`,
          to: data.markers[i]?.title || `Objekat ${i+1}`,
          duration: legDuration,
          distance: Math.round(route.legs[i].distance / 1000),
          arrivalTime: cumulativeTime + legDuration
        });
        cumulativeTime += legDuration + 10; // +10 min za istovar
      }
      
      // Poslednji objekat do Nove Pazove
      if (route.legs.length > data.markers.length) {
        const lastLeg = route.legs[route.legs.length - 1];
        const returnDuration = Math.round(lastLeg.duration / 60);
        segments.push({
          from: data.markers[data.markers.length - 1]?.title || 'Poslednji objekat',
          to: 'Nova Pazova (povratak)',
          duration: returnDuration,
          distance: Math.round(lastLeg.distance / 1000),
          arrivalTime: cumulativeTime - 10 + returnDuration // -10 jer ne istovaramo u magacinu
        });
      }
    }

    // Sačuvaj proračun za prikaz
    routeCalculations[tourId] = {
      totalDistance,
      totalDrivingTime,
      totalTime,
      loadingTime,
      unloadingTime,
      segments,
      markers: data.markers,
      routeGeometry: route.geometry
    };

    // Proceni vreme poslednjeg istovara i dolaska u magacin (okvirne satnice)
    // Počinjemo od stvarnog vremena utovara iz baze
    const tourStartTimeObj = await getTourLoadingTime(tourId);
    const [startHours, startMinutes] = tourStartTimeObj.split(':').map(Number);
    const baseTimeMinutes = startHours * 60 + startMinutes;
    
    let lastUnloadTime = baseTimeMinutes + loadingTime; // Početak + utovar
    let arrivalAtWarehouse = baseTimeMinutes + loadingTime;
    
    // Prolazimo kroz segmente i računamo vremena
    segments.forEach((segment, index) => {
      arrivalAtWarehouse += segment.duration;
      if (index < segments.length - 1) {
        // Nije poslednji segment (povratak), dodaj vreme istovara
        lastUnloadTime = arrivalAtWarehouse + 10;
        arrivalAtWarehouse += 10; // Dodaj vreme istovara za sledeći segment
      }
    });

    document.getElementById('route-'+tourId).innerHTML = `
      <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
        <span class="route-metric"><i class="fas fa-road"></i> ${totalDistance} km</span>
        <span class="route-metric"><i class="fas fa-clock"></i> ${Math.floor(totalTime/60)}h ${totalTime%60}min</span>
      </div>
      <div style="font-size: 11px; color: #0369a1; margin-bottom: 4px;">
        Završetak poslednjeg istovara: ~${Math.floor(lastUnloadTime/60).toString().padStart(2,'0')}:${(lastUnloadTime%60).toString().padStart(2,'0')}h
      </div>
      <div style="font-size: 11px; color: #0369a1;">
        Dolazak u magacin: ~${Math.floor(arrivalAtWarehouse/60).toString().padStart(2,'0')}:${(arrivalAtWarehouse%60).toString().padStart(2,'0')}h
      </div>
    `;

  } catch (error) {
    console.error('Greška pri računanju rute:', error);
    document.getElementById('route-'+tourId).innerHTML = '<span class="route-metric" style="color:#ef4444;">Greška pri računanju rute</span>';
  }
}

async function showRouteDetails(tourId) {
  const calculation = routeCalculations[tourId];
  if (!calculation) {
    alert('Proračun rute nije još uvek završen. Pokušajte ponovo za nekoliko sekundi.');
    return;
  }

  // Dohvati stvarno vreme utovara iz baze
  const tourStartTime = await getTourLoadingTime(tourId);
  
  // Konvertuj vreme utovara u minute od ponoći
  const [hours, minutes] = tourStartTime.split(':').map(Number);
  const startTimeMinutes = hours * 60 + minutes;
  
  // Kreiranje detaljnog obračuna
  let detailsHTML = `
    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); max-width: 600px; max-height: 80vh; overflow-y: auto;">
      <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px;">
        <h3 style="color: #1e293b; margin: 0; font-size: 20px;">
          <i class="fas fa-route" style="color: #3b82f6; margin-right: 8px;"></i>
          Detaljni obračun ture #${tourId}
        </h3>
        <button onclick="closeRouteDetails()" style="background: #ef4444; color: white; border: none; border-radius: 6px; padding: 8px 12px; cursor: pointer;">
          <i class="fas fa-times"></i>
        </button>
      </div>
      
      <div style="margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 8px;">
        <h4 style="color: #1e293b; margin: 0 0 10px 0; font-size: 16px;">Ukupno:</h4>
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; text-align: center;">
          <div style="background: #dbeafe; padding: 10px; border-radius: 6px;">
            <div style="color: #1d4ed8; font-weight: bold; font-size: 18px;">${calculation.totalDistance} km</div>
            <div style="color: #64748b; font-size: 12px;">Projektna kilometraža</div>
          </div>
          <div style="background: #dcfce7; padding: 10px; border-radius: 6px;">
            <div style="color: #16a34a; font-weight: bold; font-size: 18px;">${Math.floor(calculation.totalDrivingTime/60)}:${(calculation.totalDrivingTime%60).toString().padStart(2,'0')}</div>
            <div style="color: #64748b; font-size: 12px;">Vreme vožnje</div>
          </div>
          <div style="background: #fef3c7; padding: 10px; border-radius: 6px;">
            <div style="color: #d97706; font-weight: bold; font-size: 18px;">${Math.floor(calculation.totalTime/60)}:${(calculation.totalTime%60).toString().padStart(2,'0')}</div>
            <div style="color: #64748b; font-size: 12px;">Ukupno vreme</div>
          </div>
        </div>
      </div>

      <div style="margin-bottom: 20px;">
        <h4 style="color: #1e293b; margin: 0 0 15px 0; font-size: 16px;">Okvirni vremenski raspored:</h4>
        <div style="background: #f1f5f9; padding: 15px; border-radius: 8px; border-left: 4px solid #3b82f6;">
          <div style="margin-bottom: 8px;">
            <strong>${tourStartTime} - ${Math.floor((startTimeMinutes + 120)/60).toString().padStart(2,'0')}:${((startTimeMinutes + 120)%60).toString().padStart(2,'0')}</strong> - Utovar u Novoj Pazovi (2 sata)
          </div>`;

  let currentTime = startTimeMinutes + 120; // Početno vreme + 2 sata utovara
  calculation.segments.forEach((segment, index) => {
    const endTime = currentTime + segment.duration;
    
    detailsHTML += `
          <div style="margin-bottom: 8px;">
            <strong>${Math.floor(currentTime/60).toString().padStart(2,'0')}:${(currentTime%60).toString().padStart(2,'0')} - ${Math.floor(endTime/60).toString().padStart(2,'0')}:${(endTime%60).toString().padStart(2,'0')}</strong> - 
            Vožnja: ${segment.from} → ${segment.to} 
            <span style="color: #64748b;">(${segment.distance}km, ${segment.duration}min)</span>
          </div>`;
    
    currentTime = endTime;
    
    // Dodaj vreme za istovar ako nije povratak u magacin
    if (index < calculation.segments.length - 1) {
      const unloadEnd = currentTime + 10;
      detailsHTML += `
          <div style="margin-bottom: 8px; color: #059669;">
            <strong>${Math.floor(currentTime/60).toString().padStart(2,'0')}:${(currentTime%60).toString().padStart(2,'0')} - ${Math.floor(unloadEnd/60).toString().padStart(2,'0')}:${(unloadEnd%60).toString().padStart(2,'0')}</strong> - 
            <strong>Završetak istovara u ${segment.to}</strong> (10min)
          </div>`;
      currentTime = unloadEnd;
    } else {
      detailsHTML += `
          <div style="margin-bottom: 8px; background: #dcfce7; padding: 8px; border-radius: 4px;">
            <strong>${Math.floor(currentTime/60).toString().padStart(2,'0')}:${(currentTime%60).toString().padStart(2,'0')}</strong> - 
            <strong style="color: #16a34a;">Dolazak u magacin (Nova Pazova)</strong>
          </div>`;
    }
  });

  detailsHTML += `
        </div>
      </div>

      <div style="margin-bottom: 20px;">
        <h4 style="color: #1e293b; margin: 0 0 15px 0; font-size: 16px;">Lista istovara:</h4>`;

  calculation.markers.forEach((marker, index) => {
    detailsHTML += `
        <div style="display: flex; align-items: center; padding: 10px; background: #f8fafc; margin-bottom: 8px; border-radius: 8px; border-left: 3px solid #3b82f6;">
          <div style="background: #3b82f6; color: white; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; font-weight: bold; margin-right: 12px; font-size: 14px;">
            ${index + 1}
          </div>
          <div>
            <div style="font-weight: 600; color: #1e293b; margin-bottom: 2px;">${marker.title}</div>
            ${marker.addr ? `<div style="color: #64748b; font-size: 13px;">${marker.addr}</div>` : ''}
            ${marker.sifra ? `<div style="color: #64748b; font-size: 12px;">Šifra: ${marker.sifra}</div>` : ''}
          </div>
        </div>`;
  });

  detailsHTML += `
      </div>
      
      <div style="text-align: center; padding-top: 15px; border-top: 1px solid #e2e8f0;">
        <button onclick="drawRouteOnMap(${tourId})" style="background: #10b981; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; margin-right: 10px;">
          <i class="fas fa-map"></i> Prikaži rutu na mapi
        </button>
        <button onclick="closeRouteDetails()" style="background: #64748b; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer;">
          Zatvori
        </button>
      </div>
    </div>`;

  // Kreiranje overlay-a
  const overlay = document.createElement('div');
  overlay.id = 'routeDetailsOverlay';
  overlay.style.cssText = `
    position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
    background: rgba(0,0,0,0.7); z-index: 10000; display: flex; 
    align-items: center; justify-content: center; padding: 20px;
  `;
  overlay.innerHTML = detailsHTML;
  
  document.body.appendChild(overlay);
  
  // Zatvaranje na klik van sadržaja
  overlay.addEventListener('click', function(e) {
    if (e.target === overlay) {
      closeRouteDetails();
    }
  });
}

function closeRouteDetails() {
  const overlay = document.getElementById('routeDetailsOverlay');
  if (overlay) {
    overlay.remove();
  }
}

let routeLayer = null;

function drawRouteOnMap(tourId) {
  const calculation = routeCalculations[tourId];
  if (!calculation || !calculation.routeGeometry) {
    alert('Nema podataka o geometriji rute.');
    return;
  }

  // Ukloni postojeću rutu ako postoji
  if (routeLayer) {
    map.removeLayer(routeLayer);
  }

  // Dodaj rutu na mapu
  routeLayer = L.geoJSON(calculation.routeGeometry, {
    style: {
      color: '#2563eb',
      weight: 4,
      opacity: 0.8
    }
  }).addTo(map);

  // Zatvori dialog
  closeRouteDetails();
  
  // Fokusiraj mapu na rutu
  if (routeLayer.getBounds) {
    map.fitBounds(routeLayer.getBounds(), {padding: [20, 20]});
  }
}

// Poboljšanje za mobilne uređaje
if (window.innerWidth <= 768) {
  map.on('click', function() {
    document.querySelector('.sidebar').style.maxHeight = '40vh';
  });
}
</script>
</body>
</html>