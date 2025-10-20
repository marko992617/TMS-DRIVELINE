<?php
require 'functions.php';

$drivers = getDrivers();
$vehicles = getVehicles();
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loadingAddresses = json_decode($_POST['loading_addresses_json'] ?? '[]', true);
    $unloadingAddresses = json_decode($_POST['unloading_addresses_json'] ?? '[]', true);
    
    $loadingText = implode("\n", array_column($loadingAddresses, 'address'));
    $unloadingText = implode("\n", array_column($unloadingAddresses, 'address'));
    
    $data = [
        ':date'          => $_POST['date'],
        ':driver'        => $_POST['driver'] ?: null,
        ':vehicle'       => $_POST['vehicle'] ?: null,
        ':client_id'     => $_POST['client'] ?: null,
        ':ors_id'        => $_POST['ors_id'] ?? null,
        ':delivery_type' => $_POST['delivery_type'] ?? null,
        ':km'            => !empty($_POST['km']) ? floatval($_POST['km']) : 0,
        ':estimated_km'  => !empty($_POST['estimated_km']) ? floatval($_POST['estimated_km']) : 0,
        ':fuel'          => !empty($_POST['fuel_cost']) ? floatval($_POST['fuel_cost']) : 0,
        ':amort'         => calculateAmortization($_POST['vehicle'], !empty($_POST['km']) ? floatval($_POST['km']) : 0),
        ':allowance'     => calculateAllowance($_POST['driver']),
        ':load_time'     => $_POST['date'] . ' ' . $_POST['loading_time'],
        ':load_loc'      => $loadingText,
        ':unload_loc'    => $unloadingText,
        ':route'         => $_POST['route'] ?? '',
        ':note'          => $_POST['note'] ?? '',
    ];
    addTour($data);
    header('Location: tours.php');
    exit;
}

require 'header.php';
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dodaj Novu Turu</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #ffffff;
            color: #212121;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 24px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #212121;
            margin: 0;
        }

        .back-btn {
            background: #f3f4f6;
            color: #6b7280;
            border: 1px solid #d1d5db;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .back-btn:hover {
            background: #e5e7eb;
            color: #374151;
            text-decoration: none;
        }

        .main-content {
            padding: 30px 0;
        }

        .form-layout {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 24px;
        }

        .form-main {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .form-sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .sidebar-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .sidebar-title {
            font-size: 16px;
            font-weight: 600;
            color: #212121;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-title {
            font-size: 20px;
            font-weight: 600;
            color: #212121;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            gap: 24px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-size: 14px;
            font-weight: 500;
            color: #374151;
        }

        .form-label.required::after {
            content: " *";
            color: #ef4444;
        }

        .form-input, .form-select, .form-textarea {
            padding: 12px 16px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            transition: all 0.2s;
            font-family: inherit;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #10a37f;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 60px;
        }

        .form-sections {
            display: grid;
            gap: 32px;
        }

        .section {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 24px;
            background: #f8f9fa;
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #212121;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .assignment-section {
            background: #f0f9ff;
            border-color: #bfdbfe;
        }

        .details-section {
            background: #fefce8;
            border-color: #fde047;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #10a37f;
            color: white;
        }

        .btn-primary:hover {
            background: #0d8968;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
            width: 100%;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .btn-danger:hover {
            background: #fecaca;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
        }

        .input-with-icon {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            pointer-events: none;
        }

        .input-with-icon .form-input {
            padding-left: 40px;
        }

        .address-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 12px;
            max-height: 300px;
            overflow-y: auto;
        }

        .address-item {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 10px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            cursor: move;
            transition: all 0.2s;
        }
        
        .address-item:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .address-item.sortable-ghost {
            opacity: 0.4;
            background: #e0e7ff;
        }
        
        .address-item.sortable-drag {
            opacity: 1;
            cursor: grabbing;
        }
        
        .address-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            background: #3b82f6;
            color: white;
            border-radius: 50%;
            font-weight: 600;
            font-size: 14px;
            margin-right: 8px;
            flex-shrink: 0;
        }
        
        .address-number.loading {
            background: #059669;
        }
        
        .address-number.unloading {
            background: #d97706;
        }
        
        .drag-handle {
            cursor: grab;
            color: #9ca3af;
            margin-right: 8px;
            font-size: 16px;
        }
        
        .drag-handle:active {
            cursor: grabbing;
        }

        .address-item-text {
            flex: 1;
            font-size: 13px;
            color: #374151;
            word-break: break-word;
        }

        .route-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
        }

        .route-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #dbeafe;
        }

        .route-info-row:last-child {
            border-bottom: none;
        }

        .route-info-label {
            font-size: 13px;
            color: #6b7280;
        }

        .route-info-value {
            font-size: 16px;
            font-weight: 600;
            color: #1e40af;
        }

        .route-calculating {
            text-align: center;
            padding: 20px;
            color: #6b7280;
        }

        .empty-state {
            text-align: center;
            padding: 24px 16px;
            color: #9ca3af;
            font-size: 13px;
        }

        .checkbox-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 0;
        }

        .autocomplete-wrapper {
            position: relative;
            width: 100%;
        }

        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #d1d5db;
            border-top: none;
            border-radius: 0 0 8px 8px;
            max-height: 200px;
            overflow-y: auto;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none;
        }

        .autocomplete-dropdown.show {
            display: block;
        }

        .autocomplete-item {
            padding: 10px 12px;
            cursor: pointer;
            font-size: 13px;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.15s;
        }

        .autocomplete-item:last-child {
            border-bottom: none;
        }

        .autocomplete-item:hover {
            background: #f9fafb;
        }

        .autocomplete-item strong {
            color: #10a37f;
            font-weight: 500;
        }

        .autocomplete-loading {
            padding: 10px 12px;
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
        }

        .autocomplete-empty {
            padding: 10px 12px;
            text-align: center;
            color: #9ca3af;
            font-size: 13px;
        }

        #map {
            width: 100%;
            height: 400px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-top: 20px;
        }

        .map-container {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-top: 20px;
        }

        .map-title {
            font-size: 16px;
            font-weight: 600;
            color: #212121;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-option input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .checkbox-option label {
            cursor: pointer;
            font-size: 14px;
            color: #374151;
        }

        @media (max-width: 1200px) {
            .form-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 16px;
            }
            
            .form-main {
                padding: 24px 20px;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <div class="container">
        <div class="header-content">
            <h1>Dodaj Novu Turu</h1>
            <a href="tours.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Nazad na listu
            </a>
        </div>
    </div>
</div>

<div class="container">
    <div class="main-content">
        <form method="POST" class="form-layout" id="tourForm">
            <div class="form-main">
                <h2 class="form-title">
                    <i class="fas fa-plus-circle"></i>
                    Kreiranje nove ture
                </h2>

                <div class="form-sections">
                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-calendar"></i>
                            Datum i vreme
                        </h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label required">Datum ture</label>
                                <input type="date" name="date" class="form-input" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Vreme utovara</label>
                                <input type="time" name="loading_time" class="form-input" required>
                            </div>
                        </div>
                    </div>

                    <div class="section assignment-section">
                        <h3 class="section-title">
                            <i class="fas fa-users"></i>
                            Dodele
                        </h3>
                        <div class="form-grid">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Vozač</label>
                                    <select name="driver" class="form-select">
                                        <option value="">— Nije dodeljeno —</option>
                                        <?php foreach ($drivers as $d): ?>
                                            <option value="<?= $d['id'] ?>">
                                                <?= htmlspecialchars($d['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Vozilo</label>
                                    <select name="vehicle" class="form-select">
                                        <option value="">— Nije dodeljeno —</option>
                                        <?php foreach ($vehicles as $v): ?>
                                            <option value="<?= $v['id'] ?>">
                                                <?= htmlspecialchars($v['plate']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Klijent</label>
                                <select name="client" class="form-select">
                                    <option value="">— Nije dodeljeno —</option>
                                    <?php foreach ($clients as $c): ?>
                                        <option value="<?= $c['id'] ?>">
                                            <?= htmlspecialchars($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Dodatni detalji
                        </h3>
                        <div class="form-grid">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Relacija</label>
                                    <input type="text" name="route" class="form-input" placeholder="Opcionalno">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">ORS ID</label>
                                    <input type="text" name="ors_id" class="form-input" placeholder="Opcionalno">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tip isporuke</label>
                                <input type="text" name="delivery_type" class="form-input" placeholder="Redovna, vanredna...">
                            </div>
                        </div>
                    </div>

                    <div class="section details-section">
                        <h3 class="section-title">
                            <i class="fas fa-calculator"></i>
                            Kilometraža i troškovi
                        </h3>
                        <div class="form-grid">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Kilometraža</label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-road input-icon"></i>
                                        <input type="number" name="km" id="kmInput" class="form-input" 
                                               min="0" step="0.01" placeholder="Automatski izračunato" readonly>
                                        <input type="hidden" name="estimated_km" id="estimatedKmInput">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Troškovi goriva (RSD)</label>
                                    <div class="input-with-icon">
                                        <i class="fas fa-gas-pump input-icon"></i>
                                        <input type="number" name="fuel_cost" class="form-input" 
                                               min="0" step="0.01" placeholder="0">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Napomena</label>
                                <textarea name="note" class="form-textarea" rows="3" 
                                          placeholder="Dodatne napomene o turi..."></textarea>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="loading_addresses_json" id="loadingAddressesJson">
                    <input type="hidden" name="unloading_addresses_json" id="unloadingAddressesJson">

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Kreiraj turu
                        </button>
                    </div>
                </div>
            </div>

            <div class="form-sidebar">
                <div class="sidebar-card" style="background: #f0fdf4; border-color: #bbf7d0;">
                    <h3 class="sidebar-title">
                        <i class="fas fa-upload" style="color: #059669;"></i>
                        Mesta utovara
                    </h3>
                    
                    <div class="form-group">
                        <input 
                            type="text" 
                            id="loadingNameInput" 
                            class="form-input" 
                            placeholder="Naziv objekta (npr: Delhaize Pazova)"
                            style="margin-bottom: 8px;">
                        <div class="autocomplete-wrapper">
                            <input 
                                type="text" 
                                id="loadingInput" 
                                class="form-input" 
                                placeholder="Adresa (npr: Prva radna 4, Nova Pazova)"
                                autocomplete="off"
                                style="margin-bottom: 8px;">
                            <div id="loadingAutocomplete" class="autocomplete-dropdown"></div>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addLoadingAddress()">
                            <i class="fas fa-plus"></i>
                            Dodaj mesto utovara
                        </button>
                    </div>

                    <div id="loadingAddressList" class="address-list"></div>
                    
                    <div id="loadingEmpty" class="empty-state">
                        Nema dodanih mesta za utovar
                    </div>

                    <div class="checkbox-option" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                        <input type="checkbox" id="returnToLoading" onchange="calculateRoute()">
                        <label for="returnToLoading">Povrat na utovar</label>
                    </div>
                </div>

                <div class="sidebar-card" style="background: #fff7ed; border-color: #fed7aa;">
                    <h3 class="sidebar-title">
                        <i class="fas fa-download" style="color: #d97706;"></i>
                        Mesta istovara
                    </h3>
                    
                    <div class="form-group">
                        <input 
                            type="text" 
                            id="unloadingNameInput" 
                            class="form-input" 
                            placeholder="Naziv objekta (npr: Imlek)"
                            style="margin-bottom: 8px;">
                        <div class="autocomplete-wrapper">
                            <input 
                                type="text" 
                                id="unloadingInput" 
                                class="form-input" 
                                placeholder="Adresa (npr: Batajnički drum 5, Beograd)"
                                autocomplete="off"
                                style="margin-bottom: 8px;">
                            <div id="unloadingAutocomplete" class="autocomplete-dropdown"></div>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addUnloadingAddress()">
                            <i class="fas fa-plus"></i>
                            Dodaj mesto istovara
                        </button>
                    </div>

                    <div id="unloadingAddressList" class="address-list"></div>
                    
                    <div id="unloadingEmpty" class="empty-state">
                        Nema dodanih mesta za istovar
                    </div>

                    <div class="checkbox-option" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                        <input type="checkbox" id="returnToUnloading" onchange="calculateRoute()">
                        <label for="returnToUnloading">Povrat na istovar</label>
                    </div>
                </div>

                <div class="sidebar-card" style="background: #eff6ff; border-color: #bfdbfe;">
                    <h3 class="sidebar-title">
                        <i class="fas fa-route" style="color: #1e40af;"></i>
                        Proračun rute
                    </h3>
                    
                    <div id="routeInfo" class="route-info" style="display: none;">
                        <div class="route-info-row">
                            <span class="route-info-label">Ukupna kilometraža:</span>
                            <span class="route-info-value" id="totalKm">0 km</span>
                        </div>
                        <div class="route-info-row">
                            <span class="route-info-label">Procenjeno vreme:</span>
                            <span class="route-info-value" id="totalTime">0 min</span>
                        </div>
                        <div class="route-info-row">
                            <span class="route-info-label">Broj tačaka:</span>
                            <span class="route-info-value" id="waypointCount">0</span>
                        </div>
                    </div>

                    <div id="routeEmpty" class="empty-state">
                        Dodajte adrese za proračun rute
                    </div>
                </div>
            </div>
        </form>

        <div class="map-container">
            <h3 class="map-title">
                <i class="fas fa-map-marked-alt"></i>
                Prikaz rute na mapi
            </h3>
            <div id="map"></div>
            <div style="margin-top: 12px; font-size: 13px; color: #6b7280;">
                <div style="display: flex; gap: 20px;">
                    <div><span style="color: #059669;">●</span> Utovar (zeleno)</div>
                    <div><span style="color: #d97706;">●</span> Istovar (narandžasto)</div>
                </div>
                <div style="margin-top: 8px; font-size: 12px; color: #9ca3af;">
                    <i class="fas fa-info-circle"></i> Prevucite adrese da promenite redosled isporuke
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const loadingAddresses = [];
const unloadingAddresses = [];

let autocompleteTimeout = null;
let currentAutocompleteField = null;
let map = null;
let markers = [];
let loadingSortable = null;
let unloadingSortable = null;

function initMap() {
    map = L.map('map').setView([44.8, 20.5], 7);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18
    }).addTo(map);
}

function addLoadingAddress() {
    const nameInput = document.getElementById('loadingNameInput');
    const addressInput = document.getElementById('loadingInput');
    const name = nameInput.value.trim();
    const address = addressInput.value.trim();
    
    if (!address) {
        alert('Molim unesite adresu!');
        return;
    }
    
    loadingAddresses.push({ name: name || 'Utovar', address });
    nameInput.value = '';
    addressInput.value = '';
    renderAddressList('loading');
    calculateRoute();
}

function addUnloadingAddress() {
    const nameInput = document.getElementById('unloadingNameInput');
    const addressInput = document.getElementById('unloadingInput');
    const name = nameInput.value.trim();
    const address = addressInput.value.trim();
    
    if (!address) {
        alert('Molim unesite adresu!');
        return;
    }
    
    unloadingAddresses.push({ name: name || 'Istovar', address });
    nameInput.value = '';
    addressInput.value = '';
    renderAddressList('unloading');
    calculateRoute();
}

function removeAddress(type, index) {
    if (type === 'loading') {
        loadingAddresses.splice(index, 1);
        renderAddressList('loading');
    } else {
        unloadingAddresses.splice(index, 1);
        renderAddressList('unloading');
    }
    calculateRoute();
}

function renderAddressList(type) {
    const addresses = type === 'loading' ? loadingAddresses : unloadingAddresses;
    const listEl = document.getElementById(type + 'AddressList');
    const emptyEl = document.getElementById(type + 'Empty');
    
    if (addresses.length === 0) {
        listEl.innerHTML = '';
        emptyEl.style.display = 'block';
        return;
    }
    
    emptyEl.style.display = 'none';
    const cssClass = type === 'loading' ? 'loading' : 'unloading';
    
    listEl.innerHTML = addresses.map((addr, index) => `
        <div class="address-item" data-index="${index}">
            <div style="display: flex; align-items: center; flex: 1;">
                <i class="fas fa-grip-vertical drag-handle"></i>
                <span class="address-number ${cssClass}">${index + 1}</span>
                <div class="address-item-text">
                    <strong>${addr.name}</strong><br>
                    <small style="color: #6b7280;">${addr.address}</small>
                </div>
            </div>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeAddress('${type}', ${index})">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `).join('');
    
    initSortable(type);
}

function initSortable(type) {
    const listEl = document.getElementById(type + 'AddressList');
    if (!listEl) return;
    
    if (type === 'loading' && loadingSortable) {
        loadingSortable.destroy();
    }
    if (type === 'unloading' && unloadingSortable) {
        unloadingSortable.destroy();
    }
    
    const sortable = new Sortable(listEl, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        onEnd: function(evt) {
            const addresses = type === 'loading' ? loadingAddresses : unloadingAddresses;
            const item = addresses.splice(evt.oldIndex, 1)[0];
            addresses.splice(evt.newIndex, 0, item);
            renderAddressList(type);
            calculateRoute();
        }
    });
    
    if (type === 'loading') {
        loadingSortable = sortable;
    } else {
        unloadingSortable = sortable;
    }
}

function createNumberedIcon(number, color) {
    return L.divIcon({
        html: `<div style="
            background-color: ${color};
            color: white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
            border: 3px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.3);
        ">${number}</div>`,
        className: 'numbered-marker',
        iconSize: [32, 32],
        iconAnchor: [16, 16],
        popupAnchor: [0, -16]
    });
}

function updateMap(geocodedData) {
    markers.forEach(m => map.removeLayer(m));
    markers = [];
    
    if (geocodedData.loading) {
        geocodedData.loading.forEach((loc, i) => {
            const marker = L.marker([loc.lat, loc.lon], {
                icon: createNumberedIcon(i + 1, '#059669')
            })
                .addTo(map)
                .bindPopup(`<strong>#${i + 1} - ${loadingAddresses[i].name}</strong><br>${loadingAddresses[i].address}<br><small style="color: #059669;">Utovar</small>`);
            markers.push(marker);
        });
    }
    
    if (geocodedData.unloading) {
        const startNum = geocodedData.loading ? geocodedData.loading.length : 0;
        geocodedData.unloading.forEach((loc, i) => {
            const marker = L.marker([loc.lat, loc.lon], {
                icon: createNumberedIcon(startNum + i + 1, '#d97706')
            })
                .addTo(map)
                .bindPopup(`<strong>#${startNum + i + 1} - ${unloadingAddresses[i].name}</strong><br>${unloadingAddresses[i].address}<br><small style="color: #d97706;">Istovar</small>`);
            markers.push(marker);
        });
    }
    
    if (markers.length > 0) {
        const group = L.featureGroup(markers);
        map.fitBounds(group.getBounds().pad(0.1));
    }
}

function calculateRoute() {
    const routeInfo = document.getElementById('routeInfo');
    const routeEmpty = document.getElementById('routeEmpty');

    if (loadingAddresses.length === 0 && unloadingAddresses.length === 0) {
        routeInfo.style.display = 'none';
        routeEmpty.style.display = 'block';
        document.getElementById('kmInput').value = '';
        return;
    }

    if (loadingAddresses.length === 0) {
        routeInfo.innerHTML = '<div style="color: #dc2626; padding: 16px; text-align: center; font-size: 13px;">Molim dodajte bar jedno mesto utovara</div>';
        return;
    }

    routeEmpty.style.display = 'none';
    routeInfo.style.display = 'block';
    routeInfo.innerHTML = '<div class="route-calculating"><i class="fas fa-spinner fa-spin"></i> Računam rutu...</div>';

    const returnToLoading = document.getElementById('returnToLoading').checked;
    const returnToUnloading = document.getElementById('returnToUnloading').checked;

    fetch('calculate_route_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            loading_addresses: loadingAddresses.map(a => a.address),
            unloading_addresses: unloadingAddresses.map(a => a.address),
            return_to_loading: returnToLoading,
            return_to_unloading: returnToUnloading
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            routeInfo.innerHTML = `<div style="color: #dc2626; padding: 16px; text-align: center; font-size: 13px;">${data.error}</div>`;
            return;
        }

        document.getElementById('kmInput').value = data.distance_km;
        document.getElementById('estimatedKmInput').value = data.distance_km;

        routeInfo.innerHTML = `
            <div class="route-info-row">
                <span class="route-info-label">Ukupna kilometraža:</span>
                <span class="route-info-value">${data.distance_km} km</span>
            </div>
            <div class="route-info-row">
                <span class="route-info-label">Procenjeno vreme:</span>
                <span class="route-info-value">${Math.round(data.duration_minutes)} min</span>
            </div>
            <div class="route-info-row">
                <span class="route-info-label">Broj tačaka:</span>
                <span class="route-info-value">${data.waypoints_count}</span>
            </div>
        `;
        
        if (data.geocoded_locations) {
            updateMap(data.geocoded_locations);
        }
    })
    .catch(error => {
        console.error('Route calculation error:', error);
        routeInfo.innerHTML = '<div style="color: #dc2626; padding: 16px; text-align: center; font-size: 13px;">Greška pri računanju rute</div>';
    });
}

document.getElementById('tourForm').addEventListener('submit', function() {
    document.getElementById('loadingAddressesJson').value = JSON.stringify(loadingAddresses);
    document.getElementById('unloadingAddressesJson').value = JSON.stringify(unloadingAddresses);
});

document.getElementById('loadingInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addLoadingAddress();
    }
});

document.getElementById('unloadingInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        addUnloadingAddress();
    }
});

document.getElementById('loadingNameInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('loadingInput').focus();
    }
});

document.getElementById('unloadingNameInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('unloadingInput').focus();
    }
});

function setupAutocomplete(inputId, dropdownId, type) {
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    
    input.addEventListener('input', function() {
        const query = this.value.trim();
        
        clearTimeout(autocompleteTimeout);
        
        if (query.length < 2) {
            dropdown.classList.remove('show');
            return;
        }
        
        dropdown.innerHTML = '<div class="autocomplete-loading"><i class="fas fa-spinner fa-spin"></i> Pretražujem...</div>';
        dropdown.classList.add('show');
        currentAutocompleteField = type;
        
        autocompleteTimeout = setTimeout(() => {
            fetch('address_autocomplete_api.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(suggestions => {
                    if (currentAutocompleteField !== type) return;
                    
                    if (suggestions.length === 0) {
                        dropdown.innerHTML = '<div class="autocomplete-empty">Nije pronađena adresa. Pokušajte sa nazivom grada ili opštine.</div>';
                        return;
                    }
                    
                    dropdown.innerHTML = suggestions.map(s => 
                        `<div class="autocomplete-item" data-address="${s.display}">${s.display}</div>`
                    ).join('');
                    
                    dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                        item.addEventListener('click', function() {
                            input.value = this.getAttribute('data-address');
                            dropdown.classList.remove('show');
                            
                            if (type === 'loading') {
                                addLoadingAddress();
                            } else {
                                addUnloadingAddress();
                            }
                        });
                    });
                })
                .catch(error => {
                    console.error('Autocomplete error:', error);
                    dropdown.innerHTML = '<div class="autocomplete-empty">Greška pri pretrazi</div>';
                });
        }, 300);
    });
    
    input.addEventListener('blur', function() {
        setTimeout(() => {
            dropdown.classList.remove('show');
        }, 200);
    });
    
    input.addEventListener('focus', function() {
        if (this.value.trim().length >= 2 && dropdown.children.length > 0) {
            dropdown.classList.add('show');
            currentAutocompleteField = type;
        }
    });
}

setupAutocomplete('loadingInput', 'loadingAutocomplete', 'loading');
setupAutocomplete('unloadingInput', 'unloadingAutocomplete', 'unloading');

document.addEventListener('click', function(e) {
    if (!e.target.closest('.autocomplete-wrapper')) {
        document.querySelectorAll('.autocomplete-dropdown').forEach(d => d.classList.remove('show'));
    }
});

setTimeout(() => {
    initMap();
}, 100);
</script>

</body>
</html>

<?php require 'footer.php'; ?>
