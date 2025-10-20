
<?php
// pdf_helpers.php — utili za PDF i slike (auto-rotate EXIF)

require_once __DIR__ . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Auto-rotira sliku na osnovu EXIF orijentacije i vraća putanju do privremene ispravljene slike.
 */
function fix_image_orientation(string $srcPath): string {
    $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png'])) {
        return $srcPath; // preskoči ostale formate
    }
    if (!function_exists('exif_read_data')) {
        return $srcPath; // nema exif modula
    }
    
    $orientation = 0;
    if (in_array($ext, ['jpg','jpeg'])) {
        try {
            $exif = @exif_read_data($srcPath);
            if ($exif && !empty($exif['Orientation'])) {
                $orientation = (int)$exif['Orientation'];
            }
        } catch (\Throwable $e) {}
    }
    if ($orientation === 0 || $orientation === 1) {
        return $srcPath; // već je OK
    }
    
    if ($ext === 'png') {
        $img = @imagecreatefrompng($srcPath);
    } else {
        $img = @imagecreatefromjpeg($srcPath);
    }
    if (!$img) return $srcPath;

    switch ($orientation) {
        case 3:
            $rot = imagerotate($img, 180, 0);
            break;
        case 6:
            $rot = imagerotate($img, -90, 0);
            break;
        case 8:
            $rot = imagerotate($img, 90, 0);
            break;
        default:
            $rot = $img;
    }

    $tmpDir = sys_get_temp_dir();
    $tmpFile = tempnam($tmpDir, 'wbfix_');
    @unlink($tmpFile);
    $tmpFile .= '.jpg';
    imagejpeg($rot, $tmpFile, 85);
    if ($rot !== $img) imagedestroy($rot);
    imagedestroy($img);

    return $tmpFile;
}

/**
 * Kreiranje PDF-a za razduženu turu sa slikama
 */
function create_waybill_pdf($submission, $tour_data = null) {
    $waybill = trim($submission['waybill_number'] ?? '');
    if (empty($waybill)) {
        return false;
    }

    $images = json_decode($submission['images_json'] ?? '[]', true);
    if (!is_array($images) || count($images) === 0) {
        return false;
    }

    // Kreiraj direktorijum za PDF-ove
    $pdfDir = __DIR__ . '/uploads/pdfs/';
    if (!is_dir($pdfDir)) {
        mkdir($pdfDir, 0777, true);
    }

    $pdfFileName = $waybill . '.pdf';
    $pdfPath = $pdfDir . $pdfFileName;
    $webPath = 'uploads/pdfs/' . $pdfFileName;

    // Kreiraj HTML za PDF
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">
    <style>
    @page { size: A4; margin: 20mm 10mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 0; }
    .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
    .header h1 { margin: 0; font-size: 18px; color: #333; }
    .info-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 10px; }
    .info-table td { padding: 6px; border: 1px solid #ddd; }
    .info-table .label { font-weight: bold; background-color: #f5f5f5; width: 30%; }
    .image-container { text-align: center; margin: 5px -10mm; page-break-inside: avoid; }
    .image-container img { width: calc(100% + 20mm); max-height: 750px; object-fit: contain; }
    .page-break { page-break-before: always; }
    </style>
    </head><body>';

    // Header
    $html .= '<div class="header">
        <h1>TOVARNI LIST - ' . htmlspecialchars($waybill) . '</h1>
    </div>';

    // Informacije o turi
    $html .= '<table class="info-table">
        <tr><td class="label">Broj tovarnog lista:</td><td>' . htmlspecialchars($waybill) . '</td></tr>
        <tr><td class="label">Datum:</td><td>' . date('d.m.Y', strtotime($submission['submitted_at'])) . '</td></tr>';
    
    if ($tour_data) {
        $html .= '<tr><td class="label">Vozač:</td><td>' . htmlspecialchars($tour_data['driver_name'] ?? '') . '</td></tr>
            <tr><td class="label">Vozilo:</td><td>' . htmlspecialchars($tour_data['vehicle_plate'] ?? '') . '</td></tr>';
    }
    
    if (!empty($submission['note'])) {
        $html .= '<tr><td class="label">Napomena:</td><td>' . htmlspecialchars($submission['note']) . '</td></tr>';
    }
    
    $html .= '</table>';

    // Dodaj slike
    $imageCount = 0;
    foreach ($images as $imagePath) {
        if (!file_exists($imagePath)) continue;
        
        $imageCount++;
        if ($imageCount > 1) {
            $html .= '<div class="page-break"></div>';
        }
        
        $fixedImage = fix_image_orientation($imagePath);
        $imageData = @file_get_contents($fixedImage);
        
        if ($imageData !== false) {
            $base64 = 'data:image/jpeg;base64,' . base64_encode($imageData);
            $html .= '<div class="image-container">
                <img src="' . $base64 . '" alt="Slika ' . $imageCount . '">
            </div>';
        }
        
        // Obriši privremenu sliku ako je kreirana
        if ($fixedImage !== $imagePath && strpos($fixedImage, sys_get_temp_dir()) === 0) {
            @unlink($fixedImage);
        }
    }

    $html .= '</body></html>';

    // Generiši PDF
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Sačuvaj PDF
    file_put_contents($pdfPath, $dompdf->output());
    
    return $webPath;
}

/**
 * Sanitizacija naziva fajla
 */
function safe_filename(string $name): string {
    $name = preg_replace('/[^A-Za-z0-9\-\_\.]+/u', '_', $name);
    $name = trim($name, '_');
    if ($name === '') $name = 'document';
    return $name;
}
?>
