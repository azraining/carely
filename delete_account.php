<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "caregiver") {
    header("Location: index.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

// 1. Delete medication logs for this caregiver's patients
$stmt = $conn->prepare("
    DELETE ml FROM medication_logs ml
    JOIN caregiver_patient cp ON ml.patient_id = cp.patient_id
    WHERE cp.caregiver_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

// 2. Delete medication schedules
$stmt = $conn->prepare("
    DELETE ms FROM medication_schedule ms
    JOIN caregiver_patient cp ON ms.patient_id = cp.patient_id
    WHERE cp.caregiver_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

// 3. Delete device pairings
$stmt = $conn->prepare("
    DELETE dp FROM device_pairing dp
    JOIN caregiver_patient cp ON dp.patient_id = cp.patient_id
    WHERE cp.caregiver_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();

// 4. Delete caregiver-patient links
$stmt = $conn->prepare("DELETE FROM caregiver_patient WHERE caregiver_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

// 5. Delete the user account
$stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();

// Destroy session and redirect
session_unset();
session_destroy();

session_start();
$_SESSION['flash'] = "Your account has been permanently deleted.";
header("Location: index.php");
exit();