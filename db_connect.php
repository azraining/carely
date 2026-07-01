<?php
$host     = "gateway01.ap-southeast-1.prod.alicloud.tidbcloud.com";
$port     = 4000;
$dbname   = "railway";
$username = "xDjAHHUBSjw5Y5g.root";
$password = "Ay3l3RfCgXvPRrPK";

$conn = mysqli_init();

// Enable SSL (required by TiDB Cloud)
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);

mysqli_real_connect(
    $conn,
    $host,
    $username,
    $password,
    $dbname,
    $port,
    NULL,
    MYSQLI_CLIENT_SSL
);

if (mysqli_connect_errno()) {
    die("Connection failed: " . mysqli_connect_error());
}
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ===== SET TIMEZONE TO MALAYSIA (UTC+8) =====
$conn->query("SET time_zone = '+08:00'");

// Also set PHP timezone
date_default_timezone_set('Asia/Kuala_Lumpur');
?>