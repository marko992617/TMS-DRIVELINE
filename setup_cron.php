
<?php
// setup_cron.php - Postavi cron job za automatsko ažuriranje

// Kreiranje shell skripte za cron job
$cronScript = '#!/bin/bash
cd ' . __DIR__ . '
/usr/bin/php update_estimated_km.php >> estimated_km_log.txt 2>&1
';

file_put_contents('update_estimated_km.sh', $cronScript);
chmod('update_estimated_km.sh', 0755);

echo "Cron skripta kreirana: update_estimated_km.sh\n";
echo "Za postavljanje cron job-a pokrenite:\n";
echo "crontab -e\n";
echo "I dodajte liniju:\n";
echo "0 * * * * " . __DIR__ . "/update_estimated_km.sh\n";
echo "(ovo će pokretati skript na svakih sat vremena)\n";
?>
