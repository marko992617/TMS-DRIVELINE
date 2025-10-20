<?php
require 'functions.php';

$id = $_GET['id'];
$stmt = $pdo->prepare("SELECT * FROM tours WHERE id = ?");
$stmt->execute([$id]);
$tour = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loadingData = json_decode($_POST['loading_addresses_json'] ?? '[]', true);
    $unloadingData = json_decode($_POST['unloading_addresses_json'] ?? '[]', true);
    
    $loadingText = !empty($loadingData) ? json_encode($loadingData, JSON_UNESCAPED_UNICODE) : '';
    $unloadingText = !empty($unloadingData) ? json_encode($unloadingData, JSON_UNESCAPED_UNICODE) : '';
    
    $data = [
        ':date'           => $_POST['date'],
        ':driver'         => $_POST['driver'],
        ':vehicle'        => $_POST['vehicle'],
        ':client_id'      => $_POST['client'] ?: null,
        ':ors_id'         => $_POST['ors_id'] ?? $tour['ors_id'],
        ':delivery_type'  => $_POST['delivery_type'] ?? $tour['delivery_type'],
        ':km'             => !empty($_POST['km']) ? floatval($_POST['km']) : 0,
        ':fuel'           => !empty($_POST['fuel_cost']) ? floatval($_POST['fuel_cost']) : 0,
        ':amort'          => calculateAmortization($_POST['vehicle'], !empty($_POST['km']) ? floatval($_POST['km']) : 0),
        ':allowance'      => calculateAllowance($_POST['driver']),
        ':load_time'      => $_POST['date'] . ' ' . $_POST['loading_time'],
        ':load_loc'       => $loadingText,
        ':unload_loc'     => $unloadingText,
        ':route'          => $_POST['route'] ?? '',
        ':note'           => $_POST['note'] ?? '',
        ':id'             => $id
    ];
    updateTour($data);
    // Preserve the date when redirecting back
    $redirectDate = $_POST['date'];
    header('Location: tours.php?date=' . urlencode($redirectDate));
    exit;
}

$drivers = getDrivers();
$vehicles = getVehicles();
$clients = $pdo->query("SELECT id, name FROM clients ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
require 'header.php';
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Izmeni Turu #<?= $tour['id'] ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
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
            max-width: 800px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
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

        .header-buttons {
            display: flex;
            gap: 8px;
        }

        .btn {
            padding: 12px 16px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .btn-primary {
            background: #10a37f;
            color: white;
        }

        .btn-primary:hover {
            background: #0d8968;
            transform: translateY(-1px);
            color: white;
            text-decoration: none;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #6b7280;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
            color: #374151;
            text-decoration: none;
        }

        /* Main Content */
        .main-content {
            padding: 30px 0;
        }

        .form-card {
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
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
        
        /* Ensure select dropdown displays properly */
        .form-select {
            appearance: menulist;
            -webkit-appearance: menulist;
            -moz-appearance: menulist;
            cursor: pointer;
            background-image: url('data:image/svg+xml;charset=UTF-8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6,9 12,15 18,9"></polyline></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: #10a37f;
            box-shadow: 0 0 0 3px rgba(16, 163, 127, 0.1);
        }

        .form-textarea {
            resize: none;
            min-height: 60px;
            overflow: hidden;
        }

        .form-textarea.auto-resize {
            min-height: 60px;
        }
        
        .address-list {
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
            margin-bottom: 8px;
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
        }
        
        .autocomplete-wrapper {
            position: relative;
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
            z-index: 1000;
            display: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .autocomplete-dropdown.show {
            display: block;
        }
        
        .autocomplete-item {
            padding: 10px 12px;
            cursor: pointer;
            font-size: 14px;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .autocomplete-item:hover {
            background: #f3f4f6;
        }
        
        .autocomplete-item:last-child {
            border-bottom: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 24px;
            color: #9ca3af;
            font-size: 14px;
        }
        
        #map {
            width: 100%;
            height: 400px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-top: 20px;
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

        .locations-section {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }

        .assignment-section {
            background: #f0f9ff;
            border-color: #bfdbfe;
        }

        .details-section {
            background: #fefce8;
            border-color: #fde047;
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
        
        .input-with-icon .form-textarea {
            padding-left: 40px;
        }
        
        /* Specific styling for textarea icons - align with first line of text */
        .input-with-icon .form-textarea + .input-icon,
        .input-with-icon:has(.form-textarea) .input-icon {
            top: 20px; /* Align with first line instead of center */
            transform: translateY(0);
            font-size: 14px; /* Match text size */
            line-height: 1;
        }
        
        /* KM estimation styling */
        .km-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        #estimate-km-btn {
            align-self: flex-start;
            font-size: 13px;
            padding: 8px 12px;
        }
        
        #estimation-result {
            font-size: 13px;
            padding: 8px 12px;
            border-radius: 6px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
        }
        
        #estimation-result.success {
            background: #d1edff;
            border-color: #bee5eb;
            color: #0c5460;
        }
        
        #estimation-result.error {
            background: #f8d7da;
            border-color: #f5c2c7;
            color: #842029;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
            }

            .header-buttons {
                flex-direction: column;
                gap: 8px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 0 16px;
            }
            
            .form-card {
                padding: 24px 20px;
            }
        }

        /* Map Modal Styles */
        .map-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
        }

        .map-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .map-container {
            background: white;
            border-radius: 12px;
            width: 90%;
            height: 80%;
            max-width: 1200px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .map-header {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .map-header h3 {
            margin: 0;
            color: #1f2937;
            font-size: 18px;
            font-weight: 600;
        }

        .close-map {
            background: #f3f4f6;
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            cursor: pointer;
            color: #6b7280;
            font-size: 14px;
            transition: all 0.2s;
        }

        .close-map:hover {
            background: #e5e7eb;
            color: #374151;
        }

        .map-content {
            flex: 1;
            position: relative;
        }

        #routeMap {
            width: 100%;
            height: 100%;
            border-radius: 0 0 12px 12px;
        }

        .map-controls {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .map-btn {
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 10px 15px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }

        .map-btn:hover {
            background: #f9fafb;
            border-color: #9ca3af;
        }

        .map-btn.primary {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }

        .map-btn.primary:hover {
            background: #2563eb;
        }

        .map-info {
            position: absolute;
            bottom: 10px;
            left: 10px;
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 300px;
        }

        .map-info h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            font-weight: 600;
            color: #1f2937;
        }

        .map-info-item {
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>

<div class="header">
    <div class="container">
        <div class="header-content">
            <h1>Izmeni Turu #<?= $tour['id'] ?></h1>
            <div class="header-buttons">
                <a href="tour_status.php?id=<?= $tour['id'] ?>" class="btn btn-secondary">
                    <i class="fas fa-tasks"></i>
                    Status
                </a>
                <a href="tours.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Nazad
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="main-content">
        <div class="form-card">
            <h2 class="form-title">
                <i class="fas fa-edit"></i>
                Osnovni podaci o turi
            </h2>

            <form method="POST" class="form-sections">
                <!-- Osnovne informacije -->
                <div class="section">
                    <h3 class="section-title">
                        <i class="fas fa-calendar"></i>
                        Datum i vreme
                    </h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label required">Datum ture</label>
                            <input type="date" name="date" class="form-input" value="<?= $tour['date'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Vreme utovara</label>
                            <input type="time" name="loading_time" class="form-input" 
                                   value="<?= date('H:i', strtotime($tour['loading_time'])) ?>" required>
                        </div>
                    </div>
                </div>

                <!-- Lokacije -->
                <div class="section locations-section">
                    <h3 class="section-title">
                        <i class="fas fa-map-marker-alt"></i>
                        Lokacije
                    </h3>
                    
                    <input type="hidden" name="loading_addresses_json" id="loadingAddressesJson">
                    <input type="hidden" name="unloading_addresses_json" id="unloadingAddressesJson">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div>
                            <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #059669;">
                                <i class="fas fa-upload"></i> Mesta utovara
                            </h4>
                            <input type="text" id="loadingNameInput" class="form-input" placeholder="Naziv objekta (npr: Delhaize Pazova)" style="margin-bottom: 8px;">
                            <div class="autocomplete-wrapper">
                                <input type="text" id="loadingInput" class="form-input" placeholder="Adresa" autocomplete="off" style="margin-bottom: 8px;">
                                <div id="loadingAutocomplete" class="autocomplete-dropdown"></div>
                            </div>
                            <button type="button" class="btn btn-secondary" onclick="addLoadingAddress()" style="width: 100%; margin-bottom: 12px;">
                                <i class="fas fa-plus"></i> Dodaj mesto utovara
                            </button>
                            <div id="loadingAddressList" class="address-list"></div>
                            <div id="loadingEmpty" class="empty-state">Nema dodanih mesta utovara</div>
                        </div>
                        
                        <div>
                            <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #d97706;">
                                <i class="fas fa-download"></i> Mesta istovara
                            </h4>
                            <input type="text" id="unloadingNameInput" class="form-input" placeholder="Naziv objekta (npr: Imlek)" style="margin-bottom: 8px;">
                            <div class="autocomplete-wrapper">
                                <input type="text" id="unloadingInput" class="form-input" placeholder="Adresa" autocomplete="off" style="margin-bottom: 8px;">
                                <div id="unloadingAutocomplete" class="autocomplete-dropdown"></div>
                            </div>
                            <button type="button" class="btn btn-secondary" onclick="addUnloadingAddress()" style="width: 100%; margin-bottom: 12px;">
                                <i class="fas fa-plus"></i> Dodaj mesto istovara
                            </button>
                            <div id="unloadingAddressList" class="address-list"></div>
                            <div id="unloadingEmpty" class="empty-state">Nema dodanih mesta istovara</div>
                        </div>
                    </div>
                    
                    <div id="map"></div>
                    <div style="margin-top: 12px; font-size: 13px; color: #6b7280;">
                        <div style="display: flex; gap: 20px;">
                            <div><span style="color: #059669;">●</span> Utovar (zeleno)</div>
                            <div><span style="color: #d97706;">●</span> Istovar (narandžasto)</div>
                        </div>
                        <div style="margin-top: 8px; font-size: 12px; color: #9ca3af;">
                            <i class="fas fa-info-circle"></i> Prevucite adrese da promenite redosled
                        </div>
                    </div>
                </div>

                <!-- Dodele -->
                <div class="section assignment-section">
                    <h3 class="section-title">
                        <i class="fas fa-users"></i>
                        Dodele
                    </h3>
                    <div class="form-grid">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label required">Vozač</label>
                                <select name="driver" class="form-select" required>
                                    <option value="">Izaberite vozača</option>
                                    <?php foreach ($drivers as $d): ?>
                                        <option value="<?= $d['id'] ?>" <?= $d['id'] == $tour['driver_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($d['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label required">Vozilo</label>
                                <select name="vehicle" class="form-select" required>
                                    <option value="">Izaberite vozilo</option>
                                    <?php foreach ($vehicles as $v): ?>
                                        <option value="<?= $v['id'] ?>" <?= $v['id'] == $tour['vehicle_id'] ? 'selected' : '' ?>>
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
                                    <option value="<?= $c['id'] ?>" <?= $c['id'] == $tour['client_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Dodatni detalji -->
                <div class="section details-section">
                    <h3 class="section-title">
                        <i class="fas fa-info-circle"></i>
                        Dodatni detalji
                    </h3>
                    <div class="form-grid">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Tip isporuke</label>
                                <input type="text" name="delivery_type" class="form-input" 
                                       value="<?= htmlspecialchars($tour['delivery_type'] ?? '') ?>" 
                                       placeholder="Unesite tip isporuke">
                            </div>
                            <div class="form-group">
                                <label class="form-label">ORS ID</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-barcode input-icon"></i>
                                    <input type="text" name="ors_id" class="form-input" 
                                           value="<?= htmlspecialchars($tour['ors_id'] ?? '') ?>" placeholder="ORS identifikator">
                                </div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Kilometraža</label>
                                <div class="km-group">
                                    <div class="input-with-icon">
                                        <i class="fas fa-road input-icon"></i>
                                        <input type="number" name="km" id="km-input" class="form-input" 
                                               value="<?= $tour['km'] ?? 0 ?>" min="0" step="0.01" placeholder="0">
                                    </div>
                                    <button type="button" id="estimate-km-btn" class="btn btn-outline-primary mt-2" 
                                            title="Proceni kilometražu na osnovu klijenta i lokacija">
                                        <i class="fas fa-calculator"></i> Proceni
                                    </button>
                                    <div id="estimation-result" class="mt-2" style="display: none;"></div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Troškovi goriva (RSD)</label>
                                <div class="input-with-icon">
                                    <i class="fas fa-gas-pump input-icon"></i>
                                    <input type="number" name="fuel_cost" class="form-input" 
                                           value="<?= $tour['fuel_cost'] ?? 0 ?>" min="0" step="0.01" placeholder="0">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Napomena</label>
                            <textarea name="note" class="form-textarea" rows="3" 
                                      placeholder="Dodatne napomene o turi..."><?= htmlspecialchars($tour['note'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Sačuvaj izmene
                    </button>
                    <a href="tour_status.php?id=<?= $tour['id'] ?>" class="btn btn-secondary">
                        <i class="fas fa-tasks"></i>
                        Upravljaj statusom
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Map Modal -->
<div id="mapModal" class="map-modal">
    <div class="map-container">
        <div class="map-header">
            <h3><i class="fas fa-map-marked-alt"></i> Pregled rute i lokacija</h3>
            <button class="close-map" onclick="closeMapModal()">
                <i class="fas fa-times"></i> Zatvori
            </button>
        </div>
        <div class="map-content">
            <div id="routeMap"></div>
            
            <div class="map-controls">
                <button class="map-btn primary" onclick="acceptRouteEstimation()">
                    <i class="fas fa-check"></i> Potvrdi procenu
                </button>
                <button class="map-btn" onclick="recalculateRoute()">
                    <i class="fas fa-sync"></i> Preračunaj
                </button>
            </div>
            
            <div class="map-info" id="mapInfo">
                <h4>Informacije o ruti</h4>
                <div class="map-info-item">Ukupno: <span id="totalDistance">-</span> km</div>
                <div class="map-info-item">Vreme: <span id="totalDuration">-</span> min</div>
                <div class="map-info-item">Tip: <span id="calculationType">-</span></div>
                <div class="map-info-item">Lokacija: <span id="unloadingCount">-</span></div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
// Parse existing addresses
<?php
$loadingData = json_decode($tour['loading_loc'] ?? '[]', true);
$unloadingData = json_decode($tour['unloading_loc'] ?? '[]', true);

if (!is_array($loadingData)) {
    $loadingData = $tour['loading_loc'] ? [['name' => 'Utovar', 'address' => $tour['loading_loc']]] : [];
}
if (!is_array($unloadingData)) {
    $unloadingData = $tour['unloading_loc'] ? [['name' => 'Istovar', 'address' => $tour['unloading_loc']]] : [];
}
?>
const loadingAddresses = <?= json_encode($loadingData, JSON_UNESCAPED_UNICODE) ?>;
const unloadingAddresses = <?= json_encode($unloadingData, JSON_UNESCAPED_UNICODE) ?>;

let autocompleteTimeout = null;
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
}

function removeAddress(type, index) {
    if (type === 'loading') {
        loadingAddresses.splice(index, 1);
        renderAddressList('loading');
    } else {
        unloadingAddresses.splice(index, 1);
        renderAddressList('unloading');
    }
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
        }
    });
    
    if (type === 'loading') {
        loadingSortable = sortable;
    } else {
        unloadingSortable = sortable;
    }
}

function setupAutocomplete(inputId, dropdownId) {
    const input = document.getElementById(inputId);
    const dropdown = document.getElementById(dropdownId);
    
    input.addEventListener('input', function() {
        const query = this.value.trim();
        
        if (autocompleteTimeout) {
            clearTimeout(autocompleteTimeout);
        }
        
        if (query.length < 2) {
            dropdown.classList.remove('show');
            return;
        }
        
        autocompleteTimeout = setTimeout(() => {
            fetch('address_autocomplete_api.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (data.suggestions && data.suggestions.length > 0) {
                        dropdown.innerHTML = data.suggestions.map(s => 
                            `<div class="autocomplete-item" onclick="selectAutocomplete('${inputId}', '${s.replace(/'/g, "\\'")}')">${s}</div>`
                        ).join('');
                        dropdown.classList.add('show');
                    } else {
                        dropdown.classList.remove('show');
                    }
                })
                .catch(() => dropdown.classList.remove('show'));
        }, 300);
    });
}

function selectAutocomplete(inputId, value) {
    document.getElementById(inputId).value = value;
    document.querySelectorAll('.autocomplete-dropdown').forEach(d => d.classList.remove('show'));
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.autocomplete-wrapper')) {
        document.querySelectorAll('.autocomplete-dropdown').forEach(d => d.classList.remove('show'));
    }
});

document.querySelector('form').addEventListener('submit', function() {
    document.getElementById('loadingAddressesJson').value = JSON.stringify(loadingAddresses);
    document.getElementById('unloadingAddressesJson').value = JSON.stringify(unloadingAddresses);
});

setTimeout(() => {
    initMap();
    renderAddressList('loading');
    renderAddressList('unloading');
    setupAutocomplete('loadingInput', 'loadingAutocomplete');
    setupAutocomplete('unloadingInput', 'unloadingAutocomplete');
}, 100);
</script>
<script>
function autoResize(textarea) {
    textarea.style.height = 'auto';
    textarea.style.height = Math.max(60, textarea.scrollHeight) + 'px';
}

// Initialize auto-resize on page load
document.addEventListener('DOMContentLoaded', function() {
    const autoResizeTextareas = document.querySelectorAll('.auto-resize');
    
    autoResizeTextareas.forEach(function(textarea) {
        // Set initial height based on content
        autoResize(textarea);
        
        // Auto-resize on input
        textarea.addEventListener('input', function() {
            autoResize(this);
        });
        
        // Auto-resize on paste
        textarea.addEventListener('paste', function() {
            setTimeout(() => autoResize(this), 10);
        });
        
        // Auto-resize on focus
        textarea.addEventListener('focus', function() {
            autoResize(this);
        });
    });
    
    // Distance estimation functionality
    const estimateBtn = document.getElementById('estimate-km-btn');
    const kmInput = document.getElementById('km-input');
    const resultDiv = document.getElementById('estimation-result');
    
    if (estimateBtn) {
        estimateBtn.addEventListener('click', function() {
            const clientSelect = document.querySelector('select[name="client"]');
            const loadingLoc = document.querySelector('textarea[name="loading_loc"]').value.trim();
            const unloadingLoc = document.querySelector('textarea[name="unloading_loc"]').value.trim();
            
            if (!loadingLoc || !unloadingLoc) {
                showEstimationResult('Molimo unesite lokacije utovara i istovara.', 'error');
                return;
            }
            
            // Show loading state
            estimateBtn.disabled = true;
            estimateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Računam...';
            
            // Prepare form data
            const formData = new FormData();
            formData.append('client_id', clientSelect?.value || '');
            formData.append('loading_loc', loadingLoc);
            formData.append('unloading_loc', unloadingLoc);
            
            // Make AJAX request
            fetch('estimate_distance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    showEstimationResult('Greška: ' + data.error, 'error');
                } else {
                    const message = `Procenjena kilometraža: ${data.distance_km} km (${data.duration_minutes} min)\\nTip: ${data.calculation_type}`;
                    showEstimationResult(message, 'success');
                    
                    // Store route data for map
                    window.routeData = data;
                    
                    // Show map if coordinates available
                    if (data.coordinates && data.coordinates.loading && data.coordinates.unloading) {
                        showMapModal(data);
                    } else {
                        // Ask user directly if no map data
                        if (confirm(`Procenjena kilometraža je ${data.distance_km} km.\\nDa li želite da koristite ovu vrednost?`)) {
                            kmInput.value = data.distance_km;
                        }
                    }
                }
            })
            .catch(error => {
                showEstimationResult('Greška pri komunikaciji sa serverom: ' + error.message, 'error');
            })
            .finally(() => {
                // Reset button state
                estimateBtn.disabled = false;
                estimateBtn.innerHTML = '<i class="fas fa-calculator"></i> Proceni';
            });
        });
    }
    
    function showEstimationResult(message, type) {
        resultDiv.textContent = message;
        resultDiv.className = type;
        resultDiv.style.display = 'block';
        
        // Hide after 10 seconds
        setTimeout(() => {
            resultDiv.style.display = 'none';
        }, 10000);
    }
});

// Map functionality
let map = null;
let loadingMarkers = [];
let unloadingMarkers = [];
let routeLayer = null;

function showMapModal(data) {
    const modal = document.getElementById('mapModal');
    modal.classList.add('active');
    
    // Initialize map if needed
    if (!map) {
        map = L.map('routeMap').setView([44.7866, 20.4489], 10); // Belgrade center
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
    }
    
    // Clear existing layers
    clearMapLayers();
    
    // Add loading markers
    if (data.coordinates.loading) {
        const marker = L.marker([data.coordinates.loading.lat, data.coordinates.loading.lng], {
            icon: createNumberedIcon('U', '#2563eb'),
            draggable: true
        }).addTo(map);
        
        marker.bindPopup('<b>Utovar</b><br>Povuci da promeniš lokaciju');
        marker.on('dragend', function(e) {
            updateLocationCoordinates('loading', e.target.getLatLng());
        });
        
        loadingMarkers.push(marker);
    }
    
    // Add unloading markers
    if (data.coordinates.unloading) {
        data.coordinates.unloading.forEach((coord, index) => {
            const marker = L.marker([coord.lat, coord.lng], {
                icon: createNumberedIcon(index + 1, '#dc2626'),
                draggable: true
            }).addTo(map);
            
            marker.bindPopup(`<b>Istovar ${index + 1}</b><br>Povuci da promeniš lokaciju`);
            marker.on('dragend', function(e) {
                updateLocationCoordinates('unloading', e.target.getLatLng(), index);
            });
            
            unloadingMarkers.push(marker);
        });
    }
    
    // Add route if available
    if (data.coordinates.route_geometry) {
        try {
            routeLayer = L.geoJSON(data.coordinates.route_geometry, {
                style: {
                    color: '#3b82f6',
                    weight: 4,
                    opacity: 0.7
                }
            }).addTo(map);
        } catch (e) {
            console.log('Route geometry error:', e);
        }
    }
    
    // Update info panel
    document.getElementById('totalDistance').textContent = data.distance_km;
    document.getElementById('totalDuration').textContent = data.duration_minutes;
    document.getElementById('calculationType').textContent = data.calculation_type;
    document.getElementById('unloadingCount').textContent = data.unloading_count + ' lokacija';
    
    // Fit bounds to markers
    const allMarkers = [...loadingMarkers, ...unloadingMarkers];
    if (allMarkers.length > 0) {
        const group = new L.featureGroup(allMarkers);
        map.fitBounds(group.getBounds().pad(0.1));
    }
}

function closeMapModal() {
    const modal = document.getElementById('mapModal');
    modal.classList.remove('active');
}

function clearMapLayers() {
    loadingMarkers.forEach(marker => map.removeLayer(marker));
    unloadingMarkers.forEach(marker => map.removeLayer(marker));
    if (routeLayer) {
        map.removeLayer(routeLayer);
        routeLayer = null;
    }
    loadingMarkers = [];
    unloadingMarkers = [];
}

function createNumberedIcon(number, color) {
    return L.divIcon({
        className: 'custom-div-icon',
        html: `<div style="background-color: ${color}; color: white; border-radius: 50%; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);">${number}</div>`,
        iconSize: [30, 30],
        iconAnchor: [15, 15]
    });
}

function updateLocationCoordinates(type, latlng, index = null) {
    // Store updated coordinates for recalculation
    if (!window.updatedCoordinates) {
        window.updatedCoordinates = {
            loading: window.routeData.coordinates.loading,
            unloading: [...window.routeData.coordinates.unloading]
        };
    }
    
    if (type === 'loading') {
        window.updatedCoordinates.loading = {lat: latlng.lat, lng: latlng.lng};
    } else if (type === 'unloading' && index !== null) {
        window.updatedCoordinates.unloading[index] = {lat: latlng.lat, lng: latlng.lng};
    }
}

function recalculateRoute() {
    if (!window.updatedCoordinates) return;
    
    // Show recalculating state
    const recalcBtn = document.querySelector('.map-btn:not(.primary)');
    const originalText = recalcBtn.innerHTML;
    recalcBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preračunavam...';
    recalcBtn.disabled = true;
    
    // TODO: Make AJAX call to recalculate with new coordinates
    // For now, just restore button
    setTimeout(() => {
        recalcBtn.innerHTML = originalText;
        recalcBtn.disabled = false;
    }, 1000);
}

function acceptRouteEstimation() {
    const kmInput = document.getElementById('km-input');
    if (window.routeData) {
        kmInput.value = window.routeData.distance_km;
    }
    closeMapModal();
}

// Close modal when clicking outside
document.getElementById('mapModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeMapModal();
    }
});
</script>

</body>
</html>