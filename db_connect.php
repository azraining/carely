<?php
$host     = "ballast.proxy.rlwy.net";
$port     = 33205;
$dbname   = "railway";
$username = "root";
$password = "VcWYiEoGxyrZtqYLVygeUXNnPRpixkZo";

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ===== SET TIMEZONE TO MALAYSIA (UTC+8) =====
$conn->query("SET time_zone = '+08:00'");

// Also set PHP timezone
date_default_timezone_set('Asia/Kuala_Lumpur');
?>