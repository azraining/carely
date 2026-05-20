<?php
include "db_connect.php";

// ===== NO REQUIRES AT TOP - PHPMailer loaded only when needed =====

if (!isset($_POST['medicine_name'], $_POST['status'], $_POST['device_code'])) {
    echo "Missing Data";
    exit();
}

$medicine    = $_POST['medicine_name'];
$status      = $_POST['status'];
$device_code = $_POST['device_code'];

// ===== GET PATIENT FROM DEVICE =====
$stmt = $conn->prepare("SELECT patient_id FROM device_pairing WHERE device_code = ?");
$stmt->bind_param("s", $device_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "Device not paired";
    exit();
}

$patient_id = $result->fetch_assoc()['patient_id'];

// ===== INSERT MEDICATION LOG (happens first, before anything else) =====
$stmt = $conn->prepare("
    INSERT INTO medication_logs (patient_id, medicine_name, status)
    VALUES (?, ?, ?)
");
$stmt->bind_param("iss", $patient_id, $medicine, $status);

if (!$stmt->execute()) {
    echo "DB Insert Failed: " . $stmt->error;
    exit();
}

echo "Logged OK\n";  // at this point data is safely in DB

// ===== STOP HERE IF NOT MISSED =====
if ($status !== "Missed") {
    exit();
}

// ===== GET PATIENT NAME =====
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$patientResult = $stmt->get_result();

if ($patientResult->num_rows == 0) {
    echo "Patient not found";
    exit();
}

$patient_name = $patientResult->fetch_assoc()['name'];

// ===== GET CAREGIVER =====
$stmt = $conn->prepare("
    SELECT u.email, u.telegram_chat_id
    FROM users u
    JOIN caregiver_patient cp ON u.id = cp.caregiver_id
    WHERE cp.patient_id = ?
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$caregiverResult = $stmt->get_result();

if ($caregiverResult->num_rows == 0) {
    echo "Missed Logged (no caregiver assigned)";
    exit();
}

$caregiver       = $caregiverResult->fetch_assoc();
$caregiver_email = $caregiver['email'];
$chatId          = $caregiver['telegram_chat_id'];
$time            = date("Y-m-d H:i:s");

// ===== PHPMAILER LOADED HERE - only after DB insert is safe =====
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';
require 'PHPMailer-master/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;

// ===== EMAIL ALERT =====
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->SMTPDebug = 2;
    $mail->Debugoutput = 'error_log';
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'azimenurazreen@gmail.com';  // <-- replace
    $mail->Password   = 'lfkpupqgrbnuydsx';      // <-- replace
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('azimenurazreen@gmail.com', 'Carely');
    $mail->addAddress($caregiver_email);

    $mail->isHTML(true);
    $mail->Subject = 'Medication Missed Alert';
    $mail->Body    = "
        <p>Patient: <b>$patient_name</b></p>
        <p>Medication: <b>$medicine</b></p>
        <p>Time: <b>$time</b></p>
        <p>The patient did not take their medication within the alert window.</p>
    ";

    $mail->send();
    $emailStatus = "Email Sent";

} catch (Exception $e) {
    $emailStatus = "Email Failed: " . $mail->ErrorInfo;
    echo $emailStatus;
}

// ===== TELEGRAM ALERT =====
$telegramStatus = "No Telegram ID";

if (!empty($chatId)) {
    $botToken = "8058219409:AAFr8rhUWmLL14VFfmTAt2UORiV7BLmcKXA";  // <-- replace
    $message  = urlencode(
        "MISSED MEDICATION ALERT\n" .
        "Patient: $patient_name\n" .
        "Medicine: $medicine\n" .
        "Time: $time"
    );

    $url = "https://api.telegram.org/bot$botToken/sendMessage";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'chat_id' => $chatId,
        'text'    => "⚠️ MISSED MEDICATION ALERT
Patient: $patient_name
Medicine: $medicine
Time: $time"
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);

    curl_close($ch);

    if ($response === false) {
        $telegramStatus = "Telegram Failed";
    } else {
        $telegramStatus = $response;
    }
}

echo "$emailStatus | $telegramStatus";
?>