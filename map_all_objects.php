
<?php
require __DIR__ . '/db.php';
?>
<!doctype html>
<html lang="sr">
<head>
    <meta charset="utf-8">
    <title>Mapa svih objekata</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html, body { height: 100%; margin: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; }
        .layout { display: grid; grid-template-columns: 400px 1fr; height: 100vh; }
        .sidebar { background: #f8f9fa; border-right: 1px solid #e5e7eb; overflow: auto; }
        .map-container { height: 100%; }
        #map { height: 100%; }
        .object-card { 
            cursor: pointer; 
            transition: all 0.2s;
            border: 1px solid #e5e7eb;
            margin-bottom: 8px;
            border-radius: 8px;
            padding: 12px;
            background: white;
        }
        .object-card:hover, .object-card:active { 
            background: #f3f4f6; 
            border-color: #3b82f6;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .object-title { font-weight: 600; color: #1f2937; margin-bottom: 4px; }
        .object-code { color: #6b7280; font-size: 12px; margin-bottom: 4px; }
        .object-address { color: #4b5563; font-size: 14px; }
        .search-section { position: sticky; top: 0; background: #f8f9fa; z-index: 100; padding: 16px; border-bottom: 1px solid #e5e7eb; }
        .stats { background: white; padding: 12px; margin: 16px; border-radius: 8px; border: 1px solid #e5e7eb; }
        .objects-list { padding: 0 16px 16px; }
        .no-coords { opacity: 0.6; }
        .nav-links { padding: 12px 16px; border-bottom: 1px solid #e5e7eb; }
        .nav-links a { margin-right: 12px; text-decoration: none; color: #3b82f6; }
        .nav-links a:hover { text-decoration: underline; }
        .filter-badge { 
            background: #dbeafe; 
            color: #1e40af; 
            padding: 2px 8px; 
            border-radius: 12px; 
            font-size: 11px; 
            margin-left: 8px;
        }
        
        /* Mobile Toggle Button */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            font-size: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
            cursor: pointer;
        }
        
        /* Mobile Styles */
        @media (max-width: 768px) {
            .layout {
                grid-template-columns: 1fr;
                grid-template-rows: auto 1fr;
            }
            
            .sidebar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 90%;
                max-width: 350px;
                height: 100vh;
                z-index: 999;
                transform: translateX(0);
                transition: left 0.3s ease;
                border-right: 1px solid #e5e7eb;
                box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            }
            
            .sidebar.open {
                left: 0;
            }
            
            .mobile-toggle {
                display: block;
            }
            
            .map-container {
                height: 100vh;
                grid-row: 1 / -1;
            }
            
            /* Focus on search input when sidebar opens */
            .sidebar.open #searchInput {
                outline: 2px solid #3b82f6;
                outline-offset: -2px;
            }
            
            .nav-links h4 {
                font-size: 1.1rem;
            }
            
            .search-section {
                padding: 12px;
            }
            
            .stats {
                margin: 12px;
                padding: 10px;
            }
            
            .object-card {
                padding: 10px;
                margin-bottom: 6px;
            }
            
            .object-title {
                font-size: 14px;
            }
            
            .object-code, .object-address {
                font-size: 12px;
            }
            
            .objects-list {
                padding: 0 12px 12px;
            }
            
            /* Touch-friendly buttons */
            .btn {
                min-height: 44px;
            }
            
            .form-control, .form-select {
                min-height: 44px;
            }
            
            /* Overlay for mobile sidebar */
            .mobile-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 998;
            }
            
            .mobile-overlay.active {
                display: block;
            }
        }
        
        @media (max-width: 480px) {
            .mobile-toggle {
                width: 45px;
                height: 45px;
                font-size: 18px;
                top: 15px;
                left: 15px;
            }
            
            .sidebar {
                width: 95%;
            }
            
            .nav-links h4 {
                font-size: 1rem;
            }
            
            .search-section {
                padding: 10px;
            }
            
            .stats {
                margin: 10px;
                padding: 8px;
            }
            
            .stats .fs-5 {
                font-size: 1.1rem !important;
            }
            
            .object-card {
                padding: 8px;
            }
            
            .object-title {
                font-size: 13px;
            }
            
            .object-code, .object-address {
                font-size: 11px;
            }
        }
        
        /* Tablet Styles */
        @media (min-width: 769px) and (max-width: 1024px) {
            .layout {
                grid-template-columns: 350px 1fr;
            }
            
            .search-section {
                padding: 14px;
            }
            
            .stats {
                margin: 14px;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Toggle Button -->
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" onclick="closeSidebar()"></div>
    
    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div class="nav-links">
                <h4 class="mb-3"><i class="fas fa-map-marked-alt me-2"></i>Mapa objekata</h4>
            </div>

            <div class="search-section">
                <div class="input-group mb-3">
                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                    <input type="text" id="searchInput" class="form-control" placeholder="Pretraži po šifri, nazivu, gradu, adresi..." autocomplete="off" autocapitalize="none">
                    <button class="btn btn-outline-secondary" type="button" id="clearSearch">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="row g-2">
                    <div class="col-6">
                        <select id="cityFilter" class="form-select form-select-sm">
                            <option value="">Svi gradovi</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <select id="coordsFilter" class="form-select form-select-sm">
                            <option value="">Sve lokacije</option>
                            <option value="with">Sa koordinatama</option>
                            <option value="without">Bez koordinata</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="stats">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="fs-5 fw-bold text-primary" id="totalObjects">0</div>
                        <div class="small text-muted">Ukupno</div>
                    </div>
                    <div class="col-4">
                        <div class="fs-5 fw-bold text-success" id="withCoords">0</div>
                        <div class="small text-muted">Sa koordinatama</div>
                    </div>
                    <div class="col-4">
                        <div class="fs-5 fw-bold text-warning" id="withoutCoords">0</div>
                        <div class="small text-muted">Bez koordinata</div>
                    </div>
                </div>
            </div>

            <div class="objects-list">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Objekti <span id="filteredCount" class="filter-badge">0</span></h6>
                    <small class="text-muted">Klikni za fokus na mapi</small>
                </div>
                <div id="objectsList"></div>
            </div>
        </aside>

        <main class="map-container">
            <div id="map"></div>
        </main>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let map, markersLayer, allObjects = [], filteredObjects = [], markers = {};

        // Initialize map
        function initMap() {
            map = L.map('map').setView([44.8125, 20.4612], 8);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19, 
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);
            markersLayer = L.layerGroup().addTo(map);
        }

        // Load all objects
        async function loadObjects() {
            try {
                const response = await fetch('get_all_objects.php');
                const data = await response.json();
                
                if (data.ok) {
                    allObjects = data.objects;
                    filteredObjects = [...allObjects];
                    updateStats();
                    populateCityFilter();
                    renderObjects();
                    renderMarkers();
                } else {
                    console.error('Error loading objects:', data.error);
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }

        // Update statistics
        function updateStats() {
            const total = allObjects.length;
            const withCoords = allObjects.filter(obj => obj.lat && obj.lng).length;
            const withoutCoords = total - withCoords;

            document.getElementById('totalObjects').textContent = total;
            document.getElementById('withCoords').textContent = withCoords;
            document.getElementById('withoutCoords').textContent = withoutCoords;
            document.getElementById('filteredCount').textContent = filteredObjects.length;
        }

        // Populate city filter
        function populateCityFilter() {
            const cities = [...new Set(allObjects.map(obj => obj.grad).filter(Boolean))].sort();
            const cityFilter = document.getElementById('cityFilter');
            
            cities.forEach(city => {
                const option = document.createElement('option');
                option.value = city;
                option.textContent = city;
                cityFilter.appendChild(option);
            });
        }

        // Filter objects
        function filterObjects() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const cityFilter = document.getElementById('cityFilter').value;
            const coordsFilter = document.getElementById('coordsFilter').value;

            filteredObjects = allObjects.filter(obj => {
                // Search filter
                const matchesSearch = !searchTerm || 
                    (obj.sifra && obj.sifra.toLowerCase().includes(searchTerm)) ||
                    (obj.naziv && obj.naziv.toLowerCase().includes(searchTerm)) ||
                    (obj.adresa && obj.adresa.toLowerCase().includes(searchTerm)) ||
                    (obj.grad && obj.grad.toLowerCase().includes(searchTerm));

                // City filter
                const matchesCity = !cityFilter || obj.grad === cityFilter;

                // Coordinates filter
                const hasCoords = obj.lat && obj.lng;
                const matchesCoords = !coordsFilter || 
                    (coordsFilter === 'with' && hasCoords) ||
                    (coordsFilter === 'without' && !hasCoords);

                return matchesSearch && matchesCity && matchesCoords;
            });

            document.getElementById('filteredCount').textContent = filteredObjects.length;
            renderObjects();
            renderMarkers();
        }

        // Render object list
        function renderObjects() {
            const container = document.getElementById('objectsList');
            container.innerHTML = '';

            filteredObjects.forEach(obj => {
                const hasCoords = obj.lat && obj.lng;
                const card = document.createElement('div');
                card.className = `object-card ${!hasCoords ? 'no-coords' : ''}`;
                card.onclick = () => {
                    // Add visual feedback on click
                    card.style.background = '#e3f2fd';
                    setTimeout(() => {
                        card.style.background = hasCoords ? 'white' : '#f9f9f9';
                    }, 200);
                    
                    focusObject(obj);
                };

                card.innerHTML = `
                    <div class="object-title">
                        ${obj.sifra || 'N/A'}
                        ${!hasCoords ? '<i class="fas fa-map-pin text-warning ms-1" title="Bez koordinata"></i>' : ''}
                    </div>
                    <div class="object-code">${obj.naziv || 'Bez naziva'}</div>
                    <div class="object-address">${obj.adresa || ''}</div>
                    ${obj.grad ? `<div class="small text-muted">${obj.grad}</div>` : ''}
                `;

                container.appendChild(card);
            });
        }

        // Render markers on map
        function renderMarkers() {
            markersLayer.clearLayers();
            markers = {};

            const objectsWithCoords = filteredObjects.filter(obj => obj.lat && obj.lng);
            
            objectsWithCoords.forEach(obj => {
                const marker = L.marker([parseFloat(obj.lat), parseFloat(obj.lng)])
                    .bindPopup(createPopupContent(obj));
                
                markersLayer.addLayer(marker);
                markers[obj.id] = marker;
            });

            // Fit map to markers if any exist
            if (objectsWithCoords.length > 0) {
                const group = new L.featureGroup(Object.values(markers));
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }

        // Create popup content
        function createPopupContent(obj) {
            return `
                <div style="min-width: 200px;">
                    <h6 class="mb-2">${obj.sifra || 'N/A'}</h6>
                    <p class="mb-1"><strong>Naziv:</strong> ${obj.naziv || 'Bez naziva'}</p>
                    <p class="mb-1"><strong>Adresa:</strong> ${obj.adresa || 'N/A'}</p>
                    ${obj.grad ? `<p class="mb-1"><strong>Grad:</strong> ${obj.grad}</p>` : ''}
                    <hr class="my-2">
                    <small class="text-muted">
                        Koordinate: ${parseFloat(obj.lat).toFixed(6)}, ${parseFloat(obj.lng).toFixed(6)}
                    </small>
                </div>
            `;
        }

        // Focus on object
        function focusObject(obj) {
            if (obj.lat && obj.lng && markers[obj.id]) {
                map.setView([parseFloat(obj.lat), parseFloat(obj.lng)], 16);
                markers[obj.id].openPopup();
            } else {
                alert('Ovaj objekat nema koordinate na mapi.');
            }
        }

        // Event listeners
        document.getElementById('searchInput').addEventListener('input', filterObjects);
        document.getElementById('cityFilter').addEventListener('change', filterObjects);
        document.getElementById('coordsFilter').addEventListener('change', filterObjects);
        
        document.getElementById('clearSearch').addEventListener('click', () => {
            document.getElementById('searchInput').value = '';
            document.getElementById('cityFilter').value = '';
            document.getElementById('coordsFilter').value = '';
            filterObjects();
        });

        // Mobile sidebar functions
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.mobile-overlay');
            const toggleBtn = document.querySelector('.mobile-toggle');
            const searchInput = document.getElementById('searchInput');
            
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                sidebar.classList.add('open');
                overlay.classList.add('active');
                toggleBtn.innerHTML = '<i class="fas fa-times"></i>';
                
                // Auto-focus search input on mobile after sidebar animation
                setTimeout(() => {
                    if (window.innerWidth <= 768) {
                        searchInput.focus();
                    }
                }, 350);
            }
        }
        
        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.querySelector('.mobile-overlay');
            const toggleBtn = document.querySelector('.mobile-toggle');
            
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        }
        
        // Focus on object - improved for mobile
        function focusObject(obj) {
            if (obj.lat && obj.lng && markers[obj.id]) {
                // On mobile, first close sidebar then focus on map
                if (window.innerWidth <= 768) {
                    closeSidebar();
                    // Wait for sidebar animation to complete before focusing map
                    setTimeout(() => {
                        map.setView([parseFloat(obj.lat), parseFloat(obj.lng)], 17);
                        markers[obj.id].openPopup();
                        // Ensure map size is correct after sidebar closes
                        setTimeout(() => map.invalidateSize(), 100);
                    }, 350);
                } else {
                    // Desktop behavior
                    map.setView([parseFloat(obj.lat), parseFloat(obj.lng)], 16);
                    markers[obj.id].openPopup();
                }
            } else {
                alert('Ovaj objekat nema koordinate na mapi.');
                // Don't close sidebar if object has no coordinates
            }
        }
        
        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                // Reset sidebar state on desktop
                const sidebar = document.getElementById('sidebar');
                const overlay = document.querySelector('.mobile-overlay');
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
                document.querySelector('.mobile-toggle').innerHTML = '<i class="fas fa-bars"></i>';
            }
            
            // Invalidate map size on resize
            if (map) {
                setTimeout(() => map.invalidateSize(), 100);
            }
        });
        
        // Touch event handling for better mobile experience
        document.addEventListener('touchstart', function(e) {
            // Prevent zoom on double tap for buttons and cards
            if (e.target.closest('.object-card, .btn, .mobile-toggle')) {
                e.preventDefault();
            }
        }, { passive: false });

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            initMap();
            loadObjects();
        });
    </script>
</body>
</html>
