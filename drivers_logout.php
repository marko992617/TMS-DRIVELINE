<?php
// drivers_logout.php - logout for drivers
session_start();
session_unset();
session_destroy();
header('Location: drivers_login.php');
exit;
?>