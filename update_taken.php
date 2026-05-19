<?php
include "db_connect.php";

if (!isset($_POST['device_code'], $_POST['medicine_name'])) {
    echo "Missing Data";
    exit();
}

$device_code = $_POST['device_code'];
$medicine    = $_POST['medicine_name'];

// ===== GET PATIENT =====
$stmt = $conn->prepare("SELECT patient_id FROM device_pairing WHERE device_code = ?");
$stmt->bind_param("s", $device_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "Device not paired";
    exit();
}

$patient_id = $result->fetch_assoc()['patient_id'];

// ===== PREVENT DUPLICATE (within 5 minutes) =====
$stmt = $conn->prepare("
    SELECT id FROM medication_logs
    WHERE patient_id = ?
      AND medicine_name = ?
      AND status = 'Taken'
      AND TIMESTAMPDIFF(MINUTE, taken_time, NOW()) < 5
");
$stmt->bind_param("is", $patient_id, $medicine);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    echo "Duplicate ignored";
    exit();
}

// ===== INSERT =====
$stmt = $conn->prepare("
    INSERT INTO medication_logs (patient_id, medicine_name, status, taken_time)
    VALUES (?, ?, 'Taken', NOW())
");
$stmt->bind_param("is", $patient_id, $medicine);

if (!$stmt->execute()) {
    echo "DB Insert Failed: " . $stmt->error;
    exit();
}

echo "Taken Logged Successfully";
?>