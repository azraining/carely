<?php
    $host = "mysql.railway.internal";
    $username = "root";      // default for XAMPP
    $password = "VcWYiEoGxyrZtqYLVygeUXNnPRpixkZo";          // default empty
    $database = "railway";

    // Create connection
    $conn = new mysqli($host, $username, $password, $database, 3307);

    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Optional: set charset
    $conn->set_charset("utf8");
?>