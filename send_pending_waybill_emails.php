#!/usr/bin/env php
<?php
// send_pending_waybill_emails.php - Cron job script to send pending waybill emails
// Run this script every hour: 0 * * * * /path/to/send_pending_waybill_emails.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/email_helpers.php';

echo "=== Provera Email Statusa Tovarnih Listova ===\n";
echo "Vreme: " . date('Y-m-d H:i:s') . "\n\n";

$pending = get_pending_email_submissions();

if (empty($pending)) {
    echo "Nema razduženja koja čekaju slanje email-a.\n";
    exit(0);
}

echo "Pronađeno " . count($pending) . " razduženja za slanje:\n\n";

$sent = 0;
$failed = 0;

foreach ($pending as $submission) {
    $waybill = $submission['waybill_number'] ?? 'N/A';
    $submittedAt = $submission['submitted_at'];
    
    echo "Slanje email-a za tovarni list: $waybill (ID: {$submission['id']}, razduženo: $submittedAt)\n";
    
    $result = send_waybill_email($submission['id']);
    
    if ($result['success']) {
        echo "  ✓ Uspešno: " . $result['message'] . "\n";
        $sent++;
    } else {
        echo "  ✗ Greška: " . $result['message'] . "\n";
        $failed++;
    }
    
    echo "\n";
}

echo "=== Završeno ===\n";
echo "Poslato: $sent\n";
echo "Greške: $failed\n";
echo "Ukupno: " . count($pending) . "\n";

exit($failed > 0 ? 1 : 0);
?>
