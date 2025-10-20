
<?php
require 'functions.php';

if (isset($_GET['id'])) {
    deleteTour($_GET['id']);
}

header('Location: tours.php');
exit;
?>
