
<?php
require 'header.php';
require 'functions.php';

// Check if quote ID is provided
if (empty($_GET['id'])) {
    header('Location: quotes.php');
    exit;
}

$quoteId = intval($_GET['id']);

// Get existing quote data
$stmt = $pdo->prepare("
    SELECT q.*, c.name as client_name, vc.name as vehicle_category_name 
    FROM quotes q 
    LEFT JOIN clients c ON q.client_id = c.id 
    LEFT JOIN vehicle_categories vc ON q.vehicle_category_id = vc.id
    WHERE q.id = ?
");
$stmt->execute([$quoteId]);
$quote = $stmt->fetch();

if (!$quote) {
    header('Location: quotes.php');
    exit;
}

// Get clients and vehicle categories for dropdowns
$clients = $pdo->query("SELECT * FROM clients ORDER BY name")->fetchAll();
$vehicleCategories = $pdo->query("SELECT * FROM vehicle_categories ORDER BY name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare("
        UPDATE quotes SET 
            client_id = ?, 
            vehicle_category_id = ?, 
            route_points = ?, 
            route_summary = ?, 
            total_km = ?, 
            fuel_cost = ?, 
            tolls = ?, 
            amortization = ?, 
            earnings = ?, 
            frigo_cost = ?, 
            driver_allowance = ?,
            price_without_vat = ?, 
            vat_amount = ?, 
            total_price = ?, 
            payment_terms = ?, 
            valid_until = ?, 
            frigo_required = ?
        WHERE id = ?
    ");

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

    $stmt->execute([
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
        isset($_POST['frigo_required']) ? 1 : 0,
        $quoteId
    ]);

    header("Location: quote_pdf.php?id=$quoteId");
    exit;
}

// Decode route points for display
$routePointsData = json_decode($quote['route_points'], true) ?: [];
?>

<div class="container-fluid mt-4 px-4">
    <div class="row justify-content-center">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="mb-0 h4">
                            <i class="fas fa-edit me-2"></i>
                            Izmena ponude <?= htmlspecialchars($quote['quote_number']) ?>
                        </h2>
                        <div class="btn-group">
                            <a href="quotes.php" class="btn btn-light btn-sm">
                                <i class="fas fa-arrow-left me-1"></i>
                                Nazad na ponude
                            </a>
                            <a href="quote_pdf.php?id=<?= $quoteId ?>" class="btn btn-success btn-sm" target="_blank">
                                <i class="fas fa-file-pdf me-1"></i>
                                Prikaži PDF
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form method="POST" id="quoteForm">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card border-0 shadow-sm mb-3">
                                    <div class="card-header bg-light border-bottom">
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
                                                <option value="<?= $client['id'] ?>" <?= $client['id'] == $quote['client_id'] ? 'selected' : '' ?>>
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
                                                        <?= $cat['id'] == $quote['vehicle_category_id'] ? 'selected' : '' ?>
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
                                            <label class="form-label fw-semibold">Rok plaćanja (dana)</label>
                                            <input type="number" name="payment_terms" class="form-control" value="<?= $quote['payment_terms'] ?>">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Datum važenja ponude *</label>
                                            <input type="date" name="valid_until" class="form-control" value="<?= $quote['valid_until'] ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input type="checkbox" name="frigo_required" id="frigoRequired" class="form-check-input" <?= $quote['frigo_required'] ? 'checked' : '' ?>>
                                                <label class="form-check-label fw-semibold" for="frigoRequired">
                                                    Roba sa temperaturnim režimom (frigo)
                                                </label>
                                            </div>
                                        </div>

                                        <div class="mb-0">
                                            <label class="form-label fw-semibold">Dnevnica vozača (bruto)</label>
                                            <input type="number" name="driver_allowance" id="driverAllowance" class="form-control" value="<?= $quote['driver_allowance'] ?>" step="0.01">
                                        </div>
                                    </div>
                                </div>

                                <!-- Cost Calculation Card -->
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light border-bottom">
                                        <h5 class="mb-0 text-primary">
                                            <i class="fas fa-calculator me-2"></i>
                                            Kalkulacija troškova
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Ukupna kilometraža:</label>
                                            <input type="hidden" name="total_km" id="totalKm" value="<?= $quote['total_km'] ?>">
                                            <input type="number" class="form-control" value="<?= $quote['total_km'] ?>" step="0.01" onchange="updateKm(this.value)">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Troškovi goriva:</label>
                                            <input type="hidden" name="fuel_cost" id="fuelCost" value="<?= $quote['fuel_cost'] ?>">
                                            <input type="number" class="form-control" value="<?= $quote['fuel_cost'] ?>" step="0.01" onchange="updateFuelCost(this.value)">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Putarine:</label>
                                            <input type="number" name="tolls" id="tolls" class="form-control" value="<?= $quote['tolls'] ?>" step="0.01">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Amortizacija:</label>
                                            <input type="hidden" name="amortization" id="amortization" value="<?= $quote['amortization'] ?>">
                                            <input type="number" class="form-control" value="<?= $quote['amortization'] ?>" step="0.01" onchange="updateAmortization(this.value)">
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Zarada:</label>
                                            <input type="hidden" name="earnings" id="earnings" value="<?= $quote['earnings'] ?>">
                                            <input type="number" class="form-control" value="<?= $quote['earnings'] ?>" step="0.01" onchange="updateEarnings(this.value)">
                                        </div>

                                        <div class="mb-3" id="frigoSection" style="<?= $quote['frigo_required'] ? 'display: block;' : 'display: none;' ?>">
                                            <label class="form-label fw-semibold">Doplata za frigo:</label>
                                            <input type="hidden" name="frigo_cost" id="frigoCost" value="<?= $quote['frigo_cost'] ?>">
                                            <input type="number" class="form-control" value="<?= $quote['frigo_cost'] ?>" step="0.01" onchange="updateFrigoCost(this.value)">
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
                                                    <strong class="text-success"><span id="priceDisplay"><?= number_format($quote['price_without_vat'], 2) ?> RSD</span></strong>
                                                </div>
                                            </div>

                                            <div class="row mb-2">
                                                <div class="col-8"><strong>PDV (20%):</strong></div>
                                                <div class="col-4 text-end">
                                                    <strong class="text-warning"><span id="vatDisplay"><?= number_format($quote['vat_amount'], 2) ?> RSD</span></strong>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-8"><strong>Ukupno sa PDV:</strong></div>
                                                <div class="col-4 text-end">
                                                    <strong class="text-primary"><span id="totalDisplay"><?= number_format($quote['total_price'], 2) ?> RSD</span></strong>
                                                </div>
                                            </div>
                                        </div>

                                        <input type="hidden" name="route_summary" id="routeSummary" value="<?= htmlspecialchars($quote['route_summary']) ?>">
                                        <input type="hidden" name="route_points" id="routePoints" value="<?= htmlspecialchars($quote['route_points']) ?>">

                                        <button type="submit" class="btn btn-success w-100 mt-3">
                                            <i class="fas fa-save me-2"></i>Sačuvaj izmene
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-8">
                                <div class="card border-0 shadow-sm">
                                    <div class="card-header bg-light border-bottom">
                                        <h5 class="mb-0 text-primary">
                                            <i class="fas fa-route me-2"></i>
                                            Pregled rute
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold">Trenutna ruta:</label>
                                            <div class="p-3 bg-light rounded">
                                                <p class="mb-0 text-primary"><?= htmlspecialchars($quote['route_summary']) ?></p>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-info border-0" role="alert">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <div>
                                                    <strong>Napomena:</strong> Za promenu rute, molimo vas da kreirate novu ponudu sa novom rutom.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border-radius: 12px;
    overflow: hidden;
}

.card-header {
    border-bottom: none;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
}

.bg-light.rounded-circle {
    border: 1px solid #e9ecef;
}

@media (max-width: 768px) {
    .container-fluid {
        padding: 1rem;
    }
    
    .card-header .btn-group {
        flex-wrap: wrap;
        gap: 0.25rem;
    }
}
</style>

<script>
// Update functions for manual input changes
function updateKm(value) {
    document.getElementById('totalKm').value = value;
    updateCalculation();
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

function updateCalculation() {
    const fuelCost = parseFloat(document.getElementById('fuelCost').value) || 0;
    const tolls = parseFloat(document.getElementById('tolls').value) || 0;
    const amortization = parseFloat(document.getElementById('amortization').value) || 0;
    const earnings = parseFloat(document.getElementById('earnings').value) || 0;
    const frigoCost = parseFloat(document.getElementById('frigoCost').value) || 0;
    const driverAllowance = parseFloat(document.getElementById('driverAllowance').value) || 0;
    const manualPrice = parseFloat(document.getElementById('manualTransportPrice').value) || 0;
    
    const calculatedPrice = fuelCost + amortization + earnings + frigoCost + tolls + driverAllowance;
    const finalPrice = manualPrice > 0 ? manualPrice : calculatedPrice;
    
    const vat = finalPrice * 0.20;
    const total = finalPrice + vat;
    
    document.getElementById('priceDisplay').textContent = finalPrice.toFixed(2) + ' RSD';
    document.getElementById('vatDisplay').textContent = vat.toFixed(2) + ' RSD';
    document.getElementById('totalDisplay').textContent = total.toFixed(2) + ' RSD';
}

// Event listeners
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
    }
    updateCalculation();
});
</script>

<?php require 'footer.php'; ?>
