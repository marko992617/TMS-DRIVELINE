<?php
// email_helpers.php - Helper functions for sending emails with waybill PDFs

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/pdf_helpers.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Učitaj .env fajl ako postoji
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Preskoči komentare
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
            $_ENV[trim($key)] = trim($value);
        }
    }
}

/**
 * Send waybill PDF to client via email
 * 
 * @param int $submission_id ID of the driver_submission
 * @return array ['success' => bool, 'message' => string]
 */
function send_waybill_email($submission_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT ds.*, 
                   t.client_id,
                   c.name AS client_name,
                   c.email AS client_email,
                   d.name AS driver_name,
                   v.plate AS vehicle_plate,
                   t.ors_id,
                   t.delivery_type,
                   t.loading_time,
                   t.loading_loc,
                   t.unloading_loc
            FROM driver_submissions ds
            JOIN tours t ON ds.tour_id = t.id
            LEFT JOIN clients c ON t.client_id = c.id
            LEFT JOIN drivers d ON ds.driver_id = d.id
            LEFT JOIN vehicles v ON t.vehicle_id = v.id
            WHERE ds.id = ?
        ");
        $stmt->execute([$submission_id]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$submission) {
            return ['success' => false, 'message' => 'Razduženje nije pronađeno'];
        }
        
        $waybill = trim($submission['waybill_number'] ?? '');
        if (empty($waybill)) {
            return ['success' => false, 'message' => 'Nema broja tovarnog lista'];
        }
        
        $pdfPath = $submission['image_path'];
        if (!$pdfPath || !file_exists(__DIR__ . '/' . $pdfPath)) {
            $tour_data = [
                'driver_name' => $submission['driver_name'],
                'vehicle_plate' => $submission['vehicle_plate']
            ];
            $pdfPath = create_waybill_pdf($submission, $tour_data);
            
            if (!$pdfPath) {
                return ['success' => false, 'message' => 'Greška pri generisanju PDF-a'];
            }
            
            $updateStmt = $pdo->prepare("UPDATE driver_submissions SET image_path = ? WHERE id = ?");
            $updateStmt->execute([$pdfPath, $submission_id]);
        }
        
        $pdfFullPath = __DIR__ . '/' . $pdfPath;
        
        if (!file_exists($pdfFullPath)) {
            return ['success' => false, 'message' => 'PDF fajl ne postoji: ' . $pdfPath];
        }
        
        $testMode = (getenv('EMAIL_PRODUCTION_MODE') !== 'true');
        
        if ($testMode) {
            $recipientEmail = getenv('EMAIL_TEST_RECIPIENT') ?: 'obracun.dlz@dts.rs';
        } else {
            $recipientEmail = $submission['client_email'] ?? null;
            if (empty($recipientEmail)) {
                return ['success' => false, 'message' => 'Klijent nema email adresu'];
            }
        }
        
        $mail = new PHPMailer(true);
        
        $mail->isSMTP();
        $mail->Host = getenv('EMAIL_HOST') ?: 'mail.driveline.rs';
        $mail->SMTPAuth = true;
        $mail->Username = getenv('EMAIL_USERNAME') ?: 'razduzenja@driveline.rs';
        $mail->Password = getenv('EMAIL_PASSWORD') ?: '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = (int)(getenv('EMAIL_PORT') ?: 465);
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom(
            getenv('EMAIL_FROM') ?: 'razduzenja@driveline.rs',
            'DRIVELINE DOO'
        );
        
        $mail->addAddress($recipientEmail);
        
        $ccEmail = getenv('EMAIL_CC');
        if ($ccEmail) {
            $mail->addCC($ccEmail);
        }
        
        $mail->isHTML(true);
        $mail->Subject = 'Razduženje tovarnog lista - ' . htmlspecialchars($waybill);
        
        $htmlBody = '
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #0066cc; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; margin-top: 20px; }
                .info-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                .info-table td { padding: 8px; border-bottom: 1px solid #ddd; }
                .info-table td:first-child { font-weight: bold; width: 40%; }
                .footer { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Razduženje Tovarnog Lista</h1>
                </div>
                <div class="content">
                    <p>Poštovani,</p>
                    <p>U prilogu se nalazi razduženje tovarnog lista.</p>
                    
                    <table class="info-table">
                        <tr>
                            <td>Broj tovarnog lista:</td>
                            <td>' . htmlspecialchars($waybill) . '</td>
                        </tr>
                        <tr>
                            <td>Vozač:</td>
                            <td>' . htmlspecialchars($submission['driver_name'] ?? '-') . '</td>
                        </tr>
                        <tr>
                            <td>Vozilo:</td>
                            <td>' . htmlspecialchars($submission['vehicle_plate'] ?? '-') . '</td>
                        </tr>
                        <tr>
                            <td>Datum razduženja:</td>
                            <td>' . date('d.m.Y H:i', strtotime($submission['submitted_at'])) . '</td>
                        </tr>
                    </table>
                </div>
                <div class="footer">
                    <p>Ovo je automatska poruka. Molimo ne odgovarajte na ovaj email.</p>
                    <p><strong>Driveline</strong><br>razduzenja@driveline.rs</p>
                </div>
            </div>
        </body>
        </html>';
        
        $mail->Body = $htmlBody;
        $mail->AltBody = "Razduženje tovarnog lista br. $waybill\n\n" .
                         "Vozač: " . ($submission['driver_name'] ?? '-') . "\n" .
                         "Vozilo: " . ($submission['vehicle_plate'] ?? '-') . "\n" .
                         "Datum: " . date('d.m.Y H:i', strtotime($submission['submitted_at'])) . "\n\n" .
                         "PDF prilog je u prilogu.";
        
        $mail->addAttachment($pdfFullPath, $waybill . '.pdf');
        
        $mail->send();
        
        $updateStmt = $pdo->prepare("
            UPDATE driver_submissions 
            SET email_sent_at = NOW(), 
                email_sent_status = 'sent' 
            WHERE id = ?
        ");
        $updateStmt->execute([$submission_id]);
        
        return ['success' => true, 'message' => 'Email uspešno poslat na ' . $recipientEmail];
        
    } catch (Exception $e) {
        error_log("Email send error for submission $submission_id: " . $e->getMessage());
        
        try {
            $updateStmt = $pdo->prepare("
                UPDATE driver_submissions 
                SET email_sent_status = 'failed' 
                WHERE id = ?
            ");
            $updateStmt->execute([$submission_id]);
        } catch (Exception $dbError) {
        }
        
        return ['success' => false, 'message' => 'Greška pri slanju email-a: ' . $e->getMessage()];
    }
}

/**
 * Get submissions that should have email sent (1 hour after submission, not yet sent)
 * Only for submissions from 2025-10-20 onwards
 * 
 * @return array List of submission IDs
 */
function get_pending_email_submissions() {
    global $pdo;
    
    try {
        // Automatsko slanje počinje od 20.10.2025
        $autoSendStartDate = '2025-10-20 00:00:00';
        
        $stmt = $pdo->prepare("
            SELECT id, waybill_number, submitted_at
            FROM driver_submissions
            WHERE (email_sent_status IS NULL OR email_sent_status = 'failed')
            AND submitted_at >= ?
            AND submitted_at <= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY submitted_at ASC
        ");
        $stmt->execute([$autoSendStartDate]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching pending email submissions: " . $e->getMessage());
        return [];
    }
}
?>
