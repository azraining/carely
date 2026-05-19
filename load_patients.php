<?php
session_start();
include "db_connect.php";

$caregiver_id = $_SESSION['user_id'];

$result = $conn->query("
    SELECT users.* FROM users
    JOIN caregiver_patient 
    ON users.id = caregiver_patient.patient_id
    WHERE caregiver_patient.caregiver_id = '$caregiver_id'
");

while ($row = $result->fetch_assoc()) {

    echo "<div class='container'>";
    echo "<h3>{$row['name']}</h3>";

    $pid = $row['id'];

    $logs = $conn->query("
        SELECT * FROM medication_logs 
        WHERE patient_id='$pid'
        ORDER BY taken_time DESC LIMIT 5
    ");

    while ($log = $logs->fetch_assoc()) {
        echo "<p>{$log['medicine_name']} - {$log['status']}</p>";
    }

    echo "</div><br>";
}
?>