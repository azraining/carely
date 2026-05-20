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


// ===== EMAIL ALERT =====
// ===== BREVO EMAIL ALERT =====

$emailData = [
    "sender" => [
        "name"  => "Carely",
        "email" => "azimenurazreen@gmail.com"
    ],
    "to" => [
        [
            "email" => $caregiver_email
        ]
    ],
    "subject" => "Medication Missed Alert",
    "htmlContent" => "
        <h3>⚠️ Medication Missed Alert</h3>
        <p><b>Patient:</b> $patient_name</p>
        <p><b>Medication:</b> $medicine</p>
        <p><b>Time:</b> $time</p>
        <p>The patient did not take their medication within the alert window.</p>
    "
];

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://api.brevo.com/v3/smtp/email");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);

curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "accept: application/json",
    "api-key: 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",
    "content-type: application/json"
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    $emailStatus = "Email Failed: " . curl_error($ch);
} else {
    $emailStatus = "Email Sent";
}

curl_close($ch);

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