<?php
$host     = "mysql.railway.internal";  // your MYSQL_HOST
$port     = 3306;                                  // your MYSQL_PORT (check this!)
$dbname   = "railway";                              // your MYSQL_DATABASE
$username = "root";                                 // your MYSQL_USER
$password = "VcWYiEoGxyrZtqYLVygeUXNnPRpixkZo";                         // your MYSQL_PASSWORD

$conn = new mysqli($host, $username, $password, $dbname, $port);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>