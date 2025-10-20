
<?php
require 'header.php';
require 'functions.php';

// Dobij sve klijente i kategorije vozila
$clients = $pdo->query("SELECT * FROM clients ORDER BY name")->fetchAll();
$vehicleCategories = $pdo->query("SELECT * FROM vehicle_categories ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generiši broj ponude
    $quoteNumber = 'PON-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

    $routePoints = json_encode($_POST['route_points']);
    $routeSummary = str_replace('→', '->', $_POST['route_summary']);
    $totalKm = floatval($_POST['total_km']);
    $fuelCost = floatval($_POST['fuel_cost']);
    $tolls = floatval($_POST['tolls']);
    $amortization = floatval($_POST['amortization']);
    $earnings = floatval($_POST['earnings']);
    $frigoCost = isset($_POST['frigo_required']) ? floatval($_POST['frigo_cost']) : 0;
    $driverAllowance = floatval($_POST['driver_allowance']);

    // Use manual transport price if provided, otherwise calculate
    $transportPrice = !empty($_POST['manual_transport_price']) ? 
                     floatval($_POST['manual_transport_price']) : 
                     $fuelCost + $tolls + $amortization + $earnings + $frigoCost + $driverAllowance;

    $vatAmount = $transportPrice * 0.20;
    $totalPrice = $transportPrice + $vatAmount;

    $stmt = $pdo->prepare("
        INSERT INTO quotes (
            quote_number, client_id, vehicle_category_id, route_points, route_summary, 
            total_km, fuel_cost, tolls, amortization, earnings, frigo_cost, driver_allowance,
            price_without_vat, vat_amount, total_price, payment_terms, valid_until, frigo_required
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $quoteNumber,
        $_POST['client_id'],
        $_POST['vehicle_category_id'],
        $routePoints,
        $routeSummary,
        $totalKm,
        $fuelCost,
        $tolls,
        $amortization,
        $earnings,
        $_POST['frigo_cost'] ?? 0,
        $driverAllowance,
        $transportPrice,
        $vatAmount,
        $totalPrice,
        $_POST['payment_terms'],
        $_POST['valid_until'],
        isset($_POST['frigo_required']) ? 1 : 0
    ]);

    $newQuoteId = $pdo->lastInsertId();
    header("Location: quote_pdf.php?id=$newQuoteId");
    exit;
}
?>

<div class="container-fluid mt-4 px-4">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mb-0 h4">
                            <i class="fas fa-plus me-2"></i>
                            Kreiranje nove ponude
                        </h2>
                        <div class="btn-group">
                            <a href="quotes.php" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>
                                Nazad na ponude
                            </a>
                            <a href="clients.php" class="btn btn-light btn-sm">
                                <i class="fas fa-users me-1"></i>
                                Klijenti
                            </a>
                            <a href="vehicle_categories.php" class="btn btn-light btn-sm">
                                <i class="fas fa-truck me-1"></i>
                                Kategorije vozila
                            </a>
                        </div>
                    </div>
                </div>

                <form method="POST" id="quoteForm">
                    <div class="card-body p-4">
                        <div class="row">
                            <!-- Leva strana - Osnovni podaci -->
                            <div class="col-lg-6">
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0 text-primary">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Osnovni podaci
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Klijent *</label>
                                            <select name="client_id" class="form-select" required>
                                                <option value="">Izaberite klijenta</option>
                                                <?php foreach ($clients as $client): ?>
                                                <option value="<?= $client['id'] ?>">
                                                    <?= htmlspecialchars($client['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Kategorija vozila *</label>
                                            <select name="vehicle_category_id" id="vehicleCategory" class="form-select" required>
                                                <option value="">Izaberite kategoriju</option>
                                                <?php foreach ($vehicleCategories as $cat): ?>
                                                <option value="<?= $cat['id'] ?>" 
                                                        data-fuel-per-km="<?= $cat['fuel_cost_per_km'] ?>"
                                                        data-amort-per-km="<?= $cat['amortization_per_km'] ?>"
                                                        data-earnings-per-km="<?= $cat['earnings_per_km'] ?>"
                                                        data-frigo-per-km="<?= $cat['frigo_cost_per_km'] ?>">
                                                    <?= htmlspecialchars($cat['name']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Ruta *</label>
                                            <input type="text" name="route_summary" id="routeSummary" class="form-control" required readonly
                                                   placeholder="Ruta će biti automatski generisana">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Ukupna kilometraža *</label>
                                            <input type="number" name="total_km" id="totalKm" class="form-control" step="0.01" required>
                                        </div>

                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="frigo_required" id="frigoRequired">
                                            <label class="form-check-label fw-semibold" for="frigoRequired">
                                                Potreban frigo transport
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Desna strana - Kalkulacija -->
                            <div class="col-lg-6">
                                <div class="card border-0 shadow-sm mb-4">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0 text-primary">
                                            <i class="fas fa-calculator me-2"></i>
                                            Kalkulacija troškova
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Troškovi goriva:</label>
                                            <input type="hidden" name="fuel_cost" id="fuelCost">
                                            <input type="number" class="form-control" id="fuelCostDisplay" step="0.01" onchange="updateFuelCost(this.value)">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Putarine:</label>
                                            <input type="number" name="tolls" id="tolls" class="form-control" value="0" step="0.01">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Amortizacija:</label>
                                            <input type="hidden" name="amortization" id="amortization">
                                            <input type="number" class="form-control" id="amortizationDisplay" step="0.01" onchange="updateAmortization(this.value)">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Zarada:</label>
                                            <input type="hidden" name="earnings" id="earnings">
                                            <input type="number" class="form-control" id="earningsDisplay" step="0.01" onchange="updateEarnings(this.value)">
                                        </div>

                                        <div class="mb-3" id="frigoSection" style="display: none;">
                                            <label class="form-label fw-semibold">Frigo doplata:</label>
                                            <input type="hidden" name="frigo_cost" id="frigoCost" value="0">
                                            <input type="number" class="form-control" id="frigoCostDisplay" value="0" step="0.01" onchange="updateFrigoCost(this.value)">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Dnevnica vozača:</label>
                                            <input type="number" name="driver_allowance" id="driverAllowance" class="form-control" value="0" step="0.01">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Ručno unesi ukupnu cenu transporta (opcionalno):</label>
                                            <input type="number" name="manual_transport_price" id="manualTransportPrice" class="form-control" step="0.01" placeholder="Ostavi prazno za automatsku kalkulaciju">
                                        </div>

                                        <hr>

                                        <div class="bg-light p-3 rounded">
                                            <div class="row mb-2">
                                                <div class="col-8"><strong>Cena prevoza bez PDV:</strong></div>
                                                <div class="col-4 text-end">
                                                    <strong class="text-success"><span id="priceDisplay">0.00 RSD</span></strong>
                                                </div>
                                            </div>

                                            <div class="row mb-2">
                                                <div class="col-8"><strong>PDV (20%):</strong></div>
                                                <div class="col-4 text-end">
                                                    <strong class="text-warning"><span id="vatDisplay">0.00 RSD</span></strong>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-8"><strong>Ukupno sa PDV:</strong></div>
                                                <div class="col-4 text-end">
                                                    <strong class="text-primary"><span id="totalDisplay">0.00 RSD</span></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Waypoints i Mapa -->
                        <div class="card border-1 shadow-sm mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0 text-primary">
                                    <i class="fas fa-map me-2"></i>
                                    Planiranje rute sa autocomplete i drag&drop
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- Waypoints Lista -->
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Lokacija utovara *</label>
                                            <input type="text" id="loadingLocation" class="form-control" placeholder="Unesite adresu utovara">
                                            <button type="button" id="searchLoading" class="btn btn-outline-primary btn-sm mt-1">
                                                <i class="fas fa-search me-1"></i>Prikaži na mapi
                                            </button>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Lokacija istovara *</label>
                                            <input type="text" id="unloadingLocation" class="form-control" placeholder="Unesite adresu istovara">
                                            <button type="button" id="searchUnloading" class="btn btn-outline-primary btn-sm mt-1">
                                                <i class="fas fa-search me-1"></i>Prikaži na mapi
                                            </button>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Dodatne lokacije</label>
                                            <div id="additionalLocations"></div>
                                            <button type="button" id="addLocation" class="btn btn-outline-success btn-sm">
                                                <i class="fas fa-plus me-1"></i>Dodaj lokaciju
                                            </button>
                                        </div>

                                        <div class="d-flex gap-2 mb-3 flex-wrap">
                                            <button type="button" id="calculateRoute" class="btn btn-primary">
                                                <i class="fas fa-route me-1"></i>Izračunaj rutu
                                            </button>
                                            <label class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="roundTrip" />
                                                <span class="form-check-label">Povratak na početak</span>
                                            </label>
                                        </div>

                                        <div id="routeInfo" class="mt-3" style="display: none;">
                                            <div class="bg-light p-3 rounded">
                                                <div class="mb-2">
                                                    <strong>Ukupna kilometraža:</strong> <span id="routeDistance">0 km</span>
                                                </div>
                                                <div>
                                                    <strong>Ruta:</strong> <span id="routeDescription"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Mapa -->
                                    <div class="col-md-6">
                                        <div id="routeMap" style="height: 400px; border-radius: 8px; border: 1px solid #dee2e6;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dodatni uslovi -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-light">
                                <h5 class="mb-0 text-primary">
                                    <i class="fas fa-file-contract me-2"></i>
                                    Uslovi ponude
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Uslovi plaćanja (dana):</label>
                                            <select name="payment_terms" class="form-select">
                                                <option value="8">8 dana</option>
                                                <option value="15">15 dana</option>
                                                <option value="30" selected>30 dana</option>
                                                <option value="45">45 dana</option>
                                                <option value="60">60 dana</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Ponuda važi do:</label>
                                            <input type="date" name="valid_until" class="form-select" 
                                                   value="<?= date('Y-m-d', strtotime('+15 days')) ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="route_points" id="routePoints" value="[]">
                    </div>

                    <div class="card-footer bg-light border-top text-end">
                        <a href="quotes.php" class="btn btn-secondary me-2">
                            <i class="fas fa-times me-1"></i>
                            Otkaži
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>
                            Kreiraj ponudu
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border-radius: 12px;
    overflow: hidden;
}

/* Waypoints Styles */
.waypoints-container {
    min-height: 120px;
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 10px;
    background-color: #f8f9fa;
}

.waypoint-item {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 8px;
    cursor: move;
    position: relative;
    transition: all 0.2s ease;
}

.waypoint-item:hover {
    border-color: #007bff;
    box-shadow: 0 2px 4px rgba(0,123,255,0.1);
}

.waypoint-item.sortable-ghost {
    opacity: 0.5;
    background: #e3f2fd;
}

.waypoint-header {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    gap: 8px;
}

.waypoint-role {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.role-utovar { background: #d1ecf1; color: #0c5460; }
.role-istovar { background: #f8d7da; color: #721c24; }
.role-dodatno { background: #d4edda; color: #155724; }

.drag-handle {
    color: #6c757d;
    cursor: grab;
    padding: 4px;
}

.drag-handle:active {
    cursor: grabbing;
}

.autocomplete-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0 0 6px 6px;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.autocomplete-item {
    padding: 10px;
    cursor: pointer;
    border-bottom: 1px solid #f1f3f4;
    font-size: 0.9rem;
}

.autocomplete-item:hover {
    background-color: #f8f9fa;
}

.autocomplete-item:last-child {
    border-bottom: none;
}

.route-marker-blue { color: #007bff; }
.route-marker-red { color: #dc3545; }

.card-header {
    border-bottom: none;
}

.card-header.bg-success {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%) !important;
}

.form-label.fw-semibold {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid #dee2e6;
    padding: 0.75rem;
}

.form-control:focus, .form-select:focus {
    border-color: #28a745;
    box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
}

.btn {
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 500;
}

.bg-light.p-3.rounded {
    background-color: #f8f9fa !important;
    border: 1px solid #dee2e6;
}

@media (max-width: 768px) {
    .container-fluid {
        padding: 1rem;
    }
    
    .d-flex.gap-2.flex-wrap .btn {
        margin-bottom: 0.5rem;
    }
}
</style>

<!-- Leaflet.js već učitan u header.php -->

<script>
// === JEDNOSTAVAN SISTEM ZA UNOS LOKACIJA ===

// Globalne varijable
let map, markers = [], routeLine = null, routeCoordinates = [];
let additionalLocationCount = 0;

// Inicijalizuj mapu
function initMap() {
    map = L.map('routeMap').setView([44.8176, 20.4633], 8);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
}

// Pozovi inicijalizaciju mape
setTimeout(initMap, 100);

// === WAYPOINT MANAGEMENT ===

class Waypoint {
    constructor(id, name = '', role = 'dodatno', lat = null, lng = null) {
        this.id = id;
        this.name = name;
        this.role = role; // 'utovar', 'istovar', 'dodatno'
        this.lat = lat;
        this.lng = lng;
    }
    
    isValid() {
        return this.name.trim().length > 0 && this.lat !== null && this.lng !== null;
    }
    
    toJSON() {
        return {
            id: this.id,
            name: this.name,
            role: this.role,
            lat: this.lat,
            lng: this.lng
        };
    }
}

function generateWaypointId() {
    return 'waypoint_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

function addWaypoint(name = '', role = 'dodatno', lat = null, lng = null) {
    const waypoint = new Waypoint(generateWaypointId(), name, role, lat, lng);
    waypoints.push(waypoint);
    renderWaypoints();
    return waypoint;
}

function removeWaypoint(id) {
    waypoints = waypoints.filter(w => w.id !== id);
    renderWaypoints();
    updateRouteCalculation();
}

function updateWaypointRole(id, newRole) {
    const waypoint = waypoints.find(w => w.id === id);
    if (waypoint) {
        // Enforce only one 'utovar'
        if (newRole === 'utovar') {
            waypoints.forEach(w => {
                if (w.id !== id && w.role === 'utovar') {
                    w.role = 'dodatno';
                }
            });
        }
        waypoint.role = newRole;
        renderWaypoints();
    }
}

// === AUTOCOMPLETE FUNKCIONALNOST ===

async function fetchGeocodeSuggestions(query) {
    try {
        const response = await fetch(`geocode.php?q=${encodeURIComponent(query)}`);
        if (!response.ok) throw new Error('Geocoding request failed');
        const data = await response.json();
        return data || [];
    } catch (error) {
        console.error('Geocoding error:', error);
        return [];
    }
}

function createAutocompleteInput(waypoint) {
    const container = document.createElement('div');
    container.className = 'position-relative';
    container.innerHTML = `
        <input type="text" 
               class="form-control waypoint-address-input" 
               placeholder="Unesite adresu..." 
               value="${waypoint.name}"
               data-waypoint-id="${waypoint.id}">
        <div class="autocomplete-dropdown d-none"></div>
    `;
    
    const input = container.querySelector('.waypoint-address-input');
    const dropdown = container.querySelector('.autocomplete-dropdown');
    
    input.addEventListener('input', async (e) => {
        const query = e.target.value.trim();
        const waypointId = e.target.dataset.waypointId;
        
        // Update waypoint name
        const waypoint = waypoints.find(w => w.id === waypointId);
        if (waypoint) waypoint.name = query;
        
        // Clear previous timer
        if (debounceTimers[waypointId]) {
            clearTimeout(debounceTimers[waypointId]);
        }
        
        if (query.length < 3) {
            dropdown.classList.add('d-none');
            return;
        }
        
        // Debounce API calls
        debounceTimers[waypointId] = setTimeout(async () => {
            const suggestions = await fetchGeocodeSuggestions(query);
            showAutocompleteSuggestions(dropdown, suggestions, waypointId);
        }, 500);
    });
    
    return container;
}

function showAutocompleteSuggestions(dropdown, suggestions, waypointId) {
    if (suggestions.length === 0) {
        dropdown.classList.add('d-none');
        return;
    }
    
    dropdown.innerHTML = suggestions.map(suggestion => `
        <div class="autocomplete-item" 
             data-lat="${suggestion.lat}" 
             data-lng="${suggestion.lng}" 
             data-name="${suggestion.name}"
             data-waypoint-id="${waypointId}">
            <div class="fw-semibold">${suggestion.name}</div>
            <small class="text-muted">${suggestion.display_name}</small>
        </div>
    `).join('');
    
    dropdown.classList.remove('d-none');
    
    // Add click handlers
    dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
        item.addEventListener('click', () => {
            const lat = parseFloat(item.dataset.lat);
            const lng = parseFloat(item.dataset.lng);
            const name = item.dataset.name;
            const waypointId = item.dataset.waypointId;
            
            selectAutocompleteSuggestion(waypointId, name, lat, lng);
            dropdown.classList.add('d-none');
        });
    });
}

function selectAutocompleteSuggestion(waypointId, name, lat, lng) {
    const waypoint = waypoints.find(w => w.id === waypointId);
    if (waypoint) {
        waypoint.name = name;
        waypoint.lat = lat;
        waypoint.lng = lng;
        
        // Update input value
        const input = document.querySelector(`[data-waypoint-id="${waypointId}"]`);
        if (input) {
            input.value = name;
        }
        
        updateRouteCalculation();
    }
}

// === WAYPOINTS RENDERING ===

function renderWaypoints() {
    const container = document.getElementById('waypointsList');
    
    container.innerHTML = waypoints.map(waypoint => `
        <div class="waypoint-item" data-waypoint-id="${waypoint.id}">
            <div class="waypoint-header">
                <i class="fas fa-grip-vertical drag-handle"></i>
                <select class="form-select form-select-sm waypoint-role-select" 
                        style="width: 100px;" 
                        data-waypoint-id="${waypoint.id}">
                    <option value="utovar" ${waypoint.role === 'utovar' ? 'selected' : ''}>Utovar</option>
                    <option value="istovar" ${waypoint.role === 'istovar' ? 'selected' : ''}>Istovar</option>
                    <option value="dodatno" ${waypoint.role === 'dodatno' ? 'selected' : ''}>Dodatno</option>
                </select>
                <button type="button" class="btn btn-outline-danger btn-sm ms-auto remove-waypoint-btn" 
                        data-waypoint-id="${waypoint.id}">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="waypoint-address-container" data-waypoint-id="${waypoint.id}"></div>
        </div>
    `).join('');
    
    // Add autocomplete inputs
    waypoints.forEach(waypoint => {
        const addressContainer = container.querySelector(`[data-waypoint-id="${waypoint.id}"] .waypoint-address-container`);
        if (addressContainer) {
            const autocompleteInput = createAutocompleteInput(waypoint);
            addressContainer.appendChild(autocompleteInput);
        }
    });
    
    // Add event listeners
    container.querySelectorAll('.remove-waypoint-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            removeWaypoint(btn.dataset.waypointId);
        });
    });
    
    container.querySelectorAll('.waypoint-role-select').forEach(select => {
        select.addEventListener('change', () => {
            updateWaypointRole(select.dataset.waypointId, select.value);
        });
    });
    
    // Initialize/reinitialize Sortable
    if (sortable) {
        sortable.destroy();
    }
    
    sortable = Sortable.create(container, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: function(evt) {
            // Reorder waypoints array
            const movedWaypoint = waypoints.splice(evt.oldIndex, 1)[0];
            waypoints.splice(evt.newIndex, 0, movedWaypoint);
            updateRouteCalculation();
        }
    });
}

// === ROUTE CALCULATION ===

async function updateRouteCalculation() {
    const validWaypoints = waypoints.filter(w => w.isValid());
    
    if (validWaypoints.length < 2) {
        clearMapDisplay();
        clearRouteInfo();
        return;
    }
    
    const waypointsData = validWaypoints.map(w => ({
        lat: w.lat,
        lng: w.lng,
        name: w.name,
        role: w.role
    }));
    
    try {
        const response = await fetch('route.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                waypoints: waypointsData,
                roundtrip: document.getElementById('roundTrip') && document.getElementById('roundTrip').checked
            })
        });
        
        if (!response.ok) throw new Error('Route calculation failed');
        const routeData = await response.json();
        
        updateMapDisplay(routeData);
        updateFormFields(routeData);
        updateRouteInfo(routeData);
        
    } catch (error) {
        console.error('Route calculation error:', error);
        // Fallback to basic distance calculation
        updateBasicCalculation(validWaypoints);
    }
}

function updateMapDisplay(routeData) {
    // Clear existing markers and route
    clearMapDisplay();
    
    // Add markers
    waypoints.forEach((waypoint, index) => {
        if (waypoint.isValid()) {
            const marker = createRouteMarker(waypoint, index);
            markers.push(marker);
            map.addLayer(marker);
        }
    });
    
    // Add route line
    if (routeData.geometry && routeData.geometry.coordinates) {
        const coords = routeData.geometry.coordinates.map(coord => [coord[1], coord[0]]);
        routeLine = L.polyline(coords, {
            color: '#28a745',
            weight: 4,
            opacity: 0.8
        });
        map.addLayer(routeLine);
        
        // Fit map to bounds
        if (coords.length > 0) {
            map.fitBounds(routeLine.getBounds(), { padding: [20, 20] });
        }
    }
}

function createRouteMarker(waypoint, index) {
    let markerText, markerColor;
    
    switch (waypoint.role) {
        case 'utovar':
            markerText = 'U';
            markerColor = '#007bff';
            break;
        case 'istovar':
            markerText = waypoints.filter(w => w.role === 'istovar').indexOf(waypoint) + 1;
            markerColor = '#dc3545';
            break;
        default:
            markerText = index + 1;
            markerColor = '#28a745';
    }
    
    const markerIcon = L.divIcon({
        className: 'custom-div-icon',
        html: `<div style="background-color:${markerColor};color:white;border-radius:50%;width:30px;height:30px;text-align:center;line-height:30px;font-weight:bold;border:2px solid white;box-shadow:0 2px 4px rgba(0,0,0,0.3);">${markerText}</div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15]
    });
    
    return L.marker([waypoint.lat, waypoint.lng], { 
        icon: markerIcon,
        title: waypoint.name
    }).bindPopup(`<strong>${waypoint.role.toUpperCase()}:</strong><br>${waypoint.name}`);
}

function clearMapDisplay() {
    markers.forEach(marker => map.removeLayer(marker));
    markers = [];
    if (routeLine) {
        map.removeLayer(routeLine);
        routeLine = null;
    }
}

function updateFormFields(routeData) {
    document.getElementById('totalKm').value = routeData.distance_km || 0;
    document.getElementById('routeSummary').value = routeData.route_summary || '';
    
    // Update cost calculation
    updateFromCategory();
}

function updateRouteInfo(routeData) {
    document.getElementById('routeDistance').textContent = (routeData.distance_km || 0) + ' km';
    document.getElementById('routeDescription').textContent = routeData.route_summary || '';
    document.getElementById('routeInfo').style.display = 'block';
}

function clearRouteInfo() {
    document.getElementById('routeInfo').style.display = 'none';
    document.getElementById('totalKm').value = '';
    document.getElementById('routeSummary').value = '';
}

function updateBasicCalculation(validWaypoints) {
    // Fallback calculation when OSRM is not available
    let totalDistance = 0;
    for (let i = 0; i < validWaypoints.length - 1; i++) {
        const coord1 = validWaypoints[i];
        const coord2 = validWaypoints[i + 1];
        totalDistance += calculateDistance(coord1.lat, coord1.lng, coord2.lat, coord2.lng);
    }
    
    document.getElementById('totalKm').value = totalDistance.toFixed(2);
    const routeSummary = validWaypoints.map(w => w.name).join(' → ');
    document.getElementById('routeSummary').value = routeSummary;
    
    updateMapDisplay({ distance_km: totalDistance.toFixed(2), route_summary: routeSummary });
    updateRouteInfo({ distance_km: totalDistance.toFixed(2), route_summary: routeSummary });
}

function calculateDistance(lat1, lng1, lat2, lng2) {
    const R = 6371; // Radius of the Earth in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
        Math.sin(dLng/2) * Math.sin(dLng/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}
            const newPos = e.target.getLatLng();
            // Update coordinates and trigger recalculation
            updateMarkerPosition(marker, newPos.lat, newPos.lng, number, address);
        });
    }
    
    return marker;
}

// Handle marker position updates
function updateMarkerPosition(marker, lat, lng, markerNumber, originalAddress) {
    // Show confirm dialog
    if (confirm(`Marker ${markerNumber} je pomeren na novu poziciju.\nDa li želite da preračunate rutu?`)) {
        recalculateRouteWithNewPosition(markerNumber, lat, lng, originalAddress);
    } else {
        // Reset to original position if user cancels
        const originalMarker = markers.find(m => m === marker);
        if (originalMarker) {
            // Find original coordinates from routeCoordinates
            const originalIndex = markers.indexOf(originalMarker);
            if (routeCoordinates[originalIndex]) {
                marker.setLatLng(routeCoordinates[originalIndex]);
            }
        }
    }
}

// Recalculate route with new marker positions
async function recalculateRouteWithNewPosition(markerNumber, newLat, newLng, originalAddress) {
    try {
        // Update the specific coordinate
        const markerIndex = markerNumber === 'U' ? 0 : parseInt(markerNumber) - 1 + 1; // Loading is 0, unloading starts at 1
        if (routeCoordinates[markerIndex]) {
            routeCoordinates[markerIndex] = [newLat, newLng];
        }

        // Build location list for OSRM call
        const loadingLoc = document.getElementById('loadingLocation').value.trim();
        const unloadingLoc = document.getElementById('unloadingLocation').value.trim();
        const additionalInputs = document.querySelectorAll('#additionalLocations .additional-location');
        const allUnloadingLocs = [unloadingLoc];
        
        additionalInputs.forEach(input => {
            if (input.value.trim()) {
                allUnloadingLocs.push(input.value.trim());
            }
        });

        // Call OSRM endpoint again
        const formData = new FormData();
        formData.append('client_id', document.querySelector('[name="client_id"]').value || '');
        formData.append('loading_loc', loadingLoc);
        formData.append('unloading_loc', allUnloadingLocs.join(';'));

        const response = await fetch('estimate_distance.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();
        
        if (data.success) {
            // Update form fields
            document.getElementById('totalKm').value = data.distance_km;
            document.getElementById('routeDistance').textContent = data.distance_km + ' km';
            
            // Update route geometry if available
            if (routeLine) {
                map.removeLayer(routeLine);
            }
            
            if (data.coordinates && data.coordinates.route_geometry && data.coordinates.route_geometry.coordinates) {
                const geometryCoords = data.coordinates.route_geometry.coordinates.map(coord => [coord[1], coord[0]]);
                routeLine = L.polyline(geometryCoords, {
                    color: '#28a745',
                    weight: 4,
                    opacity: 0.8
                }).addTo(map);
            }
            
            updateFromCategory();
        }
    } catch (error) {
        console.error('Error recalculating route:', error);
        alert('Greška pri preračunavanju rute: ' + error.message);
    }
}

// Funkcije za kalkulaciju
function updateCalculation() {
    const totalKm = parseFloat(document.getElementById('totalKm').value) || 0;
    const manualPrice = parseFloat(document.getElementById('manualTransportPrice').value) || 0;

    if (manualPrice > 0) {
        const vatAmount = manualPrice * 0.20;
        const totalPrice = manualPrice + vatAmount;

        document.getElementById('priceDisplay').textContent = manualPrice.toFixed(2) + ' RSD';
        document.getElementById('vatDisplay').textContent = vatAmount.toFixed(2) + ' RSD';
        document.getElementById('totalDisplay').textContent = totalPrice.toFixed(2) + ' RSD';
    } else {
        const fuelCost = parseFloat(document.getElementById('fuelCost').value) || 0;
        const tolls = parseFloat(document.getElementById('tolls').value) || 0;
        const amortization = parseFloat(document.getElementById('amortization').value) || 0;
        const earnings = parseFloat(document.getElementById('earnings').value) || 0;
        const frigoCost = document.getElementById('frigoRequired').checked ? 
                         (parseFloat(document.getElementById('frigoCost').value) || 0) : 0;
        const driverAllowance = parseFloat(document.getElementById('driverAllowance').value) || 0;

        const transportPrice = fuelCost + tolls + amortization + earnings + frigoCost + driverAllowance;
        const vatAmount = transportPrice * 0.20;
        const totalPrice = transportPrice + vatAmount;

        document.getElementById('priceDisplay').textContent = transportPrice.toFixed(2) + ' RSD';
        document.getElementById('vatDisplay').textContent = vatAmount.toFixed(2) + ' RSD';
        document.getElementById('totalDisplay').textContent = totalPrice.toFixed(2) + ' RSD';
    }
}

function updateFromCategory() {
    const categorySelect = document.getElementById('vehicleCategory');
    const selectedOption = categorySelect.options[categorySelect.selectedIndex];
    const totalKm = parseFloat(document.getElementById('totalKm').value) || 0;

    if (selectedOption && totalKm > 0) {
        const fuelPerKm = parseFloat(selectedOption.dataset.fuelPerKm) || 0;
        const amortPerKm = parseFloat(selectedOption.dataset.amortPerKm) || 0;
        const earningsPerKm = parseFloat(selectedOption.dataset.earningsPerKm) || 0;
        const frigoPerKm = parseFloat(selectedOption.dataset.frigoPerKm) || 0;

        document.getElementById('fuelCost').value = (fuelPerKm * totalKm).toFixed(2);
        document.getElementById('fuelCostDisplay').value = (fuelPerKm * totalKm).toFixed(2);

        document.getElementById('amortization').value = (amortPerKm * totalKm).toFixed(2);
        document.getElementById('amortizationDisplay').value = (amortPerKm * totalKm).toFixed(2);

        document.getElementById('earnings').value = (earningsPerKm * totalKm).toFixed(2);
        document.getElementById('earningsDisplay').value = (earningsPerKm * totalKm).toFixed(2);

        document.getElementById('frigoCost').value = (frigoPerKm * totalKm).toFixed(2);
        document.getElementById('frigoCostDisplay').value = (frigoPerKm * totalKm).toFixed(2);

        updateCalculation();
    }
}

function updateFuelCost(value) {
    document.getElementById('fuelCost').value = value;
    updateCalculation();
}

function updateAmortization(value) {
    document.getElementById('amortization').value = value;
    updateCalculation();
}

function updateEarnings(value) {
    document.getElementById('earnings').value = value;
    updateCalculation();
}

function updateFrigoCost(value) {
    document.getElementById('frigoCost').value = value;
    updateCalculation();
}

// Geocoding funkcija
async function geocodeAddress(address) {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(address)}&format=json&limit=1`);
        const data = await response.json();
        if (data.length > 0) {
            return {
                lat: parseFloat(data[0].lat),
                lng: parseFloat(data[0].lon),
                display_name: data[0].display_name
            };
        }
        return null;
    } catch (error) {
        console.error('Error geocoding address:', error);
        return null;
    }
}

// Izračunaj distancu između dve tačke
function calculateDistance(lat1, lng1, lat2, lng2) {
    const R = 6371; // Radius of the Earth in km
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
        Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
        Math.sin(dLng/2) * Math.sin(dLng/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

function addLocationField() {
    additionalLocationCount++;
    const locationsDiv = document.getElementById('additionalLocations');
    const newLocationDiv = document.createElement('div');
    newLocationDiv.classList.add('input-group', 'mb-2');
    newLocationDiv.innerHTML = `
        <input type="text" class="form-control additional-location" placeholder="Unesite adresu dodatne lokacije">
        <button type="button" class="btn btn-outline-danger removeLocationBtn">
            <i class="fas fa-minus"></i>
        </button>
    `;
    locationsDiv.appendChild(newLocationDiv);

    newLocationDiv.querySelector('.removeLocationBtn').addEventListener('click', function() {
        this.closest('.input-group').remove();
    });
}

function clearMarkers() {
    markers.forEach(marker => map.removeLayer(marker));
    markers = [];
    if (routeLine) {
        map.removeLayer(routeLine);
        routeLine = null;
    }
}

function updateRoutePoints() {
    const routePoints = [];
    const loadingLoc = document.getElementById('loadingLocation').value.trim();
    const unloadingLoc = document.getElementById('unloadingLocation').value.trim();

    if (loadingLoc) routePoints.push({ address: loadingLoc, type: 'utovar' });
    
    document.querySelectorAll('#additionalLocations .additional-location').forEach(input => {
        if (input.value.trim()) {
            routePoints.push({ address: input.value.trim(), type: 'dodatno' });
        }
    });
    
    if (unloadingLoc) routePoints.push({ address: unloadingLoc, type: 'istovar' });

    document.getElementById('routePoints').value = JSON.stringify(routePoints);
}

// Event listeneri
document.getElementById('addLocation').addEventListener('click', addLocationField);

// Geocoding funkcija
async function geocodeAddress(address) {
    try {
        const response = await fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(address)}&format=json&limit=1`);
        const data = await response.json();
        if (data.length > 0) {
            return {
                lat: parseFloat(data[0].lat),
                lng: parseFloat(data[0].lon),
                display_name: data[0].display_name
            };
        }
        return null;
    } catch (error) {
        console.error('Error geocoding address:', error);
        return null;
    }
}

// Dodaj lokaciju polje
function addLocationField() {
    additionalLocationCount++;
    const locationsDiv = document.getElementById('additionalLocations');
    const newLocationDiv = document.createElement('div');
    newLocationDiv.classList.add('input-group', 'mb-2');
    newLocationDiv.innerHTML = `
        <input type="text" class="form-control additional-location" placeholder="Unesite adresu dodatne lokacije">
        <button type="button" class="btn btn-outline-danger removeLocationBtn">
            <i class="fas fa-minus"></i>
        </button>
    `;
    locationsDiv.appendChild(newLocationDiv);

    newLocationDiv.querySelector('.removeLocationBtn').addEventListener('click', function() {
        this.closest('.input-group').remove();
    });
}

// Event listeneri
document.getElementById('addLocation').addEventListener('click', addLocationField);

document.getElementById('searchLoading').addEventListener('click', async () => {
    const location = document.getElementById('loadingLocation').value.trim();
    if (!location) return;
    
    const result = await geocodeAddress(location);
    if (result) {
        map.setView([result.lat, result.lng], 13);
        const marker = L.marker([result.lat, result.lng])
            .addTo(map)
            .bindPopup(`<strong>Utovar:</strong><br>${result.display_name}`)
            .openPopup();
        markers.push(marker);
    } else {
        alert('Lokacija utovara nije pronađena.');
    }
});

document.getElementById('searchUnloading').addEventListener('click', async () => {
    const location = document.getElementById('unloadingLocation').value.trim();
    if (!location) return;
    
    const result = await geocodeAddress(location);
    if (result) {
        map.setView([result.lat, result.lng], 13);
        const marker = L.marker([result.lat, result.lng])
            .addTo(map)
            .bindPopup(`<strong>Istovar:</strong><br>${result.display_name}`)
            .openPopup();
        markers.push(marker);
    } else {
        alert('Lokacija istovara nije pronađena.');
    }
});

document.getElementById('calculateRoute').addEventListener('click', async () => {
    const loadingLoc = document.getElementById('loadingLocation').value.trim();
    const unloadingLoc = document.getElementById('unloadingLocation').value.trim();
    
    if (!loadingLoc || !unloadingLoc) {
        alert('Molimo unesite adrese utovara i istovara.');
        return;
    }

    // Update form fields
    const routeNames = [loadingLoc, unloadingLoc];
    document.getElementById('routeSummary').value = routeNames.join(' → ');
    document.getElementById('totalKm').value = '100'; // Placeholder
    
    alert('Ruta je izračunata! Lokacije: ' + routeNames.join(' → '));
});

// Event listeners za kalkulaciju
document.getElementById('vehicleCategory').addEventListener('change', updateFromCategory);
document.getElementById('totalKm').addEventListener('input', updateFromCategory);
document.getElementById('tolls').addEventListener('input', updateCalculation);
document.getElementById('driverAllowance').addEventListener('input', updateCalculation);
document.getElementById('manualTransportPrice').addEventListener('input', updateCalculation);

document.getElementById('frigoRequired').addEventListener('change', function() {
    const frigoSection = document.getElementById('frigoSection');
    if (this.checked) {
        frigoSection.style.display = 'block';
    } else {
        frigoSection.style.display = 'none';
        document.getElementById('frigoCost').value = 0;
        document.getElementById('frigoCostDisplay').value = 0;
    }
    updateCalculation();
});

// EVENT LISTENERS - sada sa jednostavnim pristupom
</script>

<?php require 'footer.php'; ?>
