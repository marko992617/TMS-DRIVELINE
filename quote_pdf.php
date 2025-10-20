
<?php
require 'vendor/autoload.php';
use Dompdf\Dompdf;
require 'config.php';

if (empty($_GET['id'])) {
    die('Nije definisana ponuda za export.');
}

$id = intval($_GET['id']);

// Dobij ponudu sa podacima o klijentu
$stmt = $pdo->prepare("
    SELECT q.*, c.name as client_name, c.address as client_address, c.email as client_email, 
           c.phone as client_phone, c.pib as client_pib, c.company_number as client_mb,
           vc.name as vehicle_category_name
    FROM quotes q 
    LEFT JOIN clients c ON q.client_id = c.id 
    LEFT JOIN vehicle_categories vc ON q.vehicle_category_id = vc.id
    WHERE q.id = ?
");
$stmt->execute([$id]);
$quote = $stmt->fetch();

if (!$quote) {
    die('Ponuda nije pronađena.');
}

$html = '<!DOCTYPE html><html lang="sr"><head><meta charset="UTF-8"><style>
body { font-family: DejaVu Sans, sans-serif; margin: 20px; color: #333; font-size: 12px; }
.header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 15px; }
.header .company { font-size: 18px; font-weight: bold; margin-bottom: 5px; }
.header .address, .header .ids { font-size: 11px; color: #555; }
.title { text-align: center; font-size: 20px; font-weight: bold; margin: 30px 0; text-transform: uppercase; }
.info-section { margin-bottom: 25px; }
.info-section h3 { font-size: 14px; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
.info-table { width: 100%; margin-bottom: 15px; }
.info-table td { padding: 5px; vertical-align: top; }
.info-table .label { width: 30%; font-weight: bold; background-color: #f8f9fa; }
.cost-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
.cost-table th, .cost-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
.cost-table th { background-color: #f8f9fa; font-weight: bold; }
.cost-table .amount { text-align: right; }
.total-row { background-color: #e9ecef; font-weight: bold; }
.footer { margin-top: 40px; font-size: 10px; color: #666; }
.terms { margin-top: 30px; font-size: 11px; }
.terms h4 { font-size: 12px; margin-bottom: 10px; }
</style></head><body>';

$html .= '<div class="header">
    <div class="company">DRIVELINE DOO BEOGRAD</div>
    <div class="address">Žikice Jovanovića Španca 25A, 11000 Beograd</div>
    <div class="ids">PIB: 114097051 | MB: 21970662</div>
</div>';

$html .= '<div class="title">PONUDA ZA TRANSPORT</div>';

$html .= '<div style="display: flex; justify-content: space-between; margin-bottom: 30px;">
    <div style="width: 48%;">
        <div class="info-section">
            <h3>PODACI O KLIJENTU</h3>
            <table class="info-table">
                <tr><td class="label">Naziv:</td><td>' . htmlspecialchars($quote['client_name']) . '</td></tr>
                <tr><td class="label">Adresa:</td><td>' . htmlspecialchars($quote['client_address']) . '</td></tr>
                <tr><td class="label">PIB:</td><td>' . htmlspecialchars($quote['client_pib']) . '</td></tr>
                <tr><td class="label">MB:</td><td>' . htmlspecialchars($quote['client_mb']) . '</td></tr>
                <tr><td class="label">Email:</td><td>' . htmlspecialchars($quote['client_email']) . '</td></tr>
                <tr><td class="label">Telefon:</td><td>' . htmlspecialchars($quote['client_phone']) . '</td></tr>
            </table>
        </div>
    </div>
    <div style="width: 48%;">
        <div class="info-section">
            <h3>PODACI O PONUDI</h3>
            <table class="info-table">
                <tr><td class="label">Broj ponude:</td><td>' . htmlspecialchars($quote['quote_number']) . '</td></tr>
                <tr><td class="label">Datum kreiranja:</td><td>' . date('d.m.Y', strtotime($quote['created_at'])) . '</td></tr>
                <tr><td class="label">Važnost ponude:</td><td>' . $quote['validity_days'] . ' dana</td></tr>
                <tr><td class="label">Rok plaćanja:</td><td>' . $quote['payment_terms'] . ' dana</td></tr>
            </table>
        </div>
    </div>
</div>';

$html .= '<div class="info-section">
    <h3>OPIS TRANSPORTA</h3>
    <table class="info-table">
        <tr><td class="label">Ruta:</td><td>' . htmlspecialchars($quote['route_summary']) . '</td></tr>
        <tr><td class="label">Ukupna kilometraža:</td><td>' . number_format($quote['total_km'], 0) . ' km</td></tr>
        <tr><td class="label">Kategorija vozila:</td><td>' . htmlspecialchars($quote['vehicle_category_name']) . '</td></tr>
    </table>
</div>';

if (!empty($quote['notes'])) {
    $html .= '<div class="info-section">
        <h3>NAPOMENE</h3>
        <p>' . nl2br(htmlspecialchars($quote['notes'])) . '</p>
    </div>';
}

$html .= '<table class="cost-table">
    <thead>
        <tr>
            <th>Opis usluge</th>
            <th style="width: 150px; text-align: right;">Cena (RSD)</th>
        </tr>
    </thead>
    <tbody>
        <tr class="total-row">
            <td><strong>Transport robe - ' . htmlspecialchars($quote['route_summary']) . '</strong></td>
            <td class="amount"><strong>' . number_format($quote['total_price'], 2) . '</strong></td>
        </tr>
    </tbody>
</table>';

$html .= '<div class="terms">
    <h4>USLOVI PONUDE</h4>
    <ul>
        <li>Ponuda važi ' . $quote['validity_days'] . ' dana od datuma izdavanja</li>
        <li>Rok plaćanja: ' . $quote['payment_terms'] . ' dana od datuma isporuke</li>
        <li>Cena je izražena u RSD i uključuje PDV</li>
        <li>Transport se vrši našim vozilima uz potpuno osiguranje</li>
        <li>Prihvatanje ponude potvrđujete potpisivanjem i vraćanjem jednog primerka</li>
    </ul>
</div>';

$html .= '<div class="footer">
    <p>Datum kreiranja ponude: ' . date('d.m.Y H:i') . '</p>
    <br><br>
    <p>________________________<br>Potpis odgovornog lica</p>
</div>';

$html .= '</body></html>';

// Kreiraj PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = 'ponuda_' . $quote['quote_number'] . '.pdf';
$dompdf->stream($filename, ['Attachment' => false]);
exit;
?>
