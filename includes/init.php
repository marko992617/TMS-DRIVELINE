<?php
// includes/init.php - stub to load main init.php from root
$root_init = __DIR__ . '/..' . '/init.php';
if (file_exists($root_init)) {
    require_once $root_init;
} else {
    die('Error: Main init.php not found in root directory.');
}
