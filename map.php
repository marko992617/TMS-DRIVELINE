<?php
// map.php — mapa po jednoj turi
require __DIR__ . '/db.php';
$ture = $pdo->query("SELECT id, CONCAT(COALESCE(naziv,''),' ',COALESCE(DATE_FORMAT(datum,'%Y-%m-%d'),'')) AS label FROM ture ORDER BY datum DESC, id DESC")->fetchAll();
?>
<!doctype html>
<html lang="sr">
<head>
  <meta charset="utf-8">
  <title>Mapa ture</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
  <style>
    html, body { height:100%; margin:0; font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial; }
    .layout { display:grid; grid-template-columns: 320px 1fr; height:100%; }
    .sidebar { padding:12px; border-right:1px solid #e5e7eb; overflow:auto; }
    .map { height:100%; }
    .missing { background:#fff7ed; border:1px solid #fed7aa; padding:8px; border-radius:8px; margin-top:12px; }
    .list { margin:8px 0; padding:0; list-style:none; }
    .list li { padding:4px 0; border-bottom:1px dashed #e5e7eb; }
    .head { font-weight:600; margin:6px 0; }
    .muted{ color:#6b7280; font-size:12px; }
    .controls { display:flex; gap:8px; align-items:center; }
    select, button { padding:6px 8px; border-radius:6px; border:1px solid #d1d5db; }
    button { cursor:pointer; }
    .nav { margin:8px 0; }
    .nav a{ margin-right:8px; }
  </style>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <h2>Mapa ture</h2>
      <div class="nav">
        <a href="index.html">Početna</a>
        <a href="map_vehicle.php">Mapa po vozilu</a>
      </div>
      <div class="controls">
        <form id="frm" onsubmit="return false">
          <label for="tura_id" class="head">Tura:</label><br>
          <select id="tura_id" name="tura_id">
            <option value="">-- izaberi --</option>
            <?php foreach($ture as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['label'] ?: ('Tura #'.$t['id']), ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
          <button id="btnLoad">Prikaži</button>
        </form>
        <a href="import_objekti.php" title="Import objekata">Import</a>
      </div>

      <div id="summary" class="muted">Izaberi turu pa klikni "Prikaži".</div>

      <div id="listWrap">
        <div class="head">Objekti na turi</div>
        <ul id="list" class="list"></ul>
        <div class="missing">
          <div class="head">Bez koordinata</div>
          <div class="muted">Pokreni <a href="geocode_missing.php" target="_blank">geokodiranje</a> pa osveži mapu.</div>
          <ul id="missing" class="list"></ul>
        </div>
      </div>
    </aside>
    <main>
      <div id="map" class="map"></div>
    </main>
  </div>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
  <script>
    let map = L.map('map').setView([44.8125, 20.4612], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19, attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    let markersLayer = L.layerGroup().addTo(map);

    function fmtPopup(m) {
      const parts = [];
      parts.push('<strong>'+escapeHtml(m.title)+'</strong>');
      if (m.addr) parts.push('<div>'+escapeHtml(m.addr)+'</div>');
      if (m.sifra) parts.push('<div class="muted">Šifra: '+escapeHtml(m.sifra)+'</div>');
      return parts.join('');
    }
    function escapeHtml(s){return (s??'').toString().replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]));}

    document.getElementById('btnLoad').addEventListener('click', async ()=>{
      const tura_id = document.getElementById('tura_id').value;
      if (!tura_id) return;
      const res = await fetch('get_tura_markeri.php?tura_id='+encodeURIComponent(tura_id));
      const data = await res.json();
      if (!data.ok) { alert(data.error||'Greška'); return; }

      const list = document.getElementById('list'); list.innerHTML = '';
      const missing = document.getElementById('missing'); missing.innerHTML = '';
      markersLayer.clearLayers();
      let bounds = [];

      data.markers.forEach(m=>{
        const marker = L.marker([m.lat, m.lng]).bindPopup(fmtPopup(m));
        marker.addTo(markersLayer);
        bounds.push([m.lat, m.lng]);
        const li = document.createElement('li');
        li.textContent = (m.title || m.sifra) + (m.addr? (' — '+m.addr) : '');
        list.appendChild(li);
      });

      data.missing.forEach(m=>{
        const li = document.createElement('li');
        li.textContent = (m.title || m.sifra) + (m.addr? (' — '+m.addr) : '');
        missing.appendChild(li);
      });

      document.getElementById('summary').textContent =
        `Sa koordinatama: ${data.markers.length} | Bez koordinata: ${data.missing.length}`;

      if (bounds.length>0) {
        map.fitBounds(bounds, {padding:[30,30]});
      }
    });
  </script>
</body>
</html>