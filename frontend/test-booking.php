<?php
$_SERVER["REQUEST_METHOD"] = "POST";
$_GET["action"] = "create-booking";
echo "Starting booking test...\n";
include "/var/www/html/api/api/production-api.php";
echo "Booking test completed.\n";
?>
