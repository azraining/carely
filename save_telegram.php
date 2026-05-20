<?php
include "db_connect.php";

// ===== DEBUG LOG =====
//file_put_contents("log.txt", file_get_contents("php://input") . PHP_EOL, FILE_APPEND);

$data = json_decode(file_get_contents("php://input"), true);

// ===== CHECK MESSAGE =====
if (!isset($data['message'])) {
    //file_put_contents("log.txt", "No message\n", FILE_APPEND);
    exit();
}

$chat_id = $data['message']['chat']['id'];
$text = $data['message']['text'] ?? '';


// file_put_contents("log.txt", "TEXT: $text\n", FILE_APPEND);

// ===== CHECK /start =====
if (strpos($text, "/start") === 0) {

    $parts = explode(" ", trim($text));

    file_put_contents("log.txt", "PARTS: " . print_r($parts, true), FILE_APPEND);

    if (count($parts) < 2) {
        //file_put_contents("log.txt", "NO CODE\n", FILE_APPEND);
        exit();
    }

    $code = trim($parts[1]);

    //file_put_contents("log.txt", "CODE: $code\n", FILE_APPEND);

    // ===== FIND USER =====
    $stmt = $conn->prepare("SELECT id FROM users WHERE telegram_code=?");
    $stmt->bind_param("s", $code);
    $stmt->execute();

    $result = $stmt->get_result();

    if (!$result) {
        //file_put_contents("log.txt", "SQL ERROR: " . $conn->error . "\n", FILE_APPEND);
        exit();
    }

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();
        $user_id = $user['id'];

        //file_put_contents("log.txt", "USER FOUND: $user_id\n", FILE_APPEND);

        // ===== SAVE CHAT ID =====
        $update = $conn->prepare("
            UPDATE users 
            SET telegram_chat_id=?
            WHERE id=?
        ");

        $update->bind_param("si", $chat_id, $user_id);

        $update->execute();

        if ($update->error) {
            //file_put_contents("log.txt", "UPDATE ERROR: " . $conn->error . "\n", FILE_APPEND);
        } else {
            //file_put_contents("log.txt", "CHAT ID SAVED\n", FILE_APPEND);
        }

        // ===== SEND CONFIRMATION =====
        $botToken = "8058219409:AAFr8rhUWmLL14VFfmTAt2UORiV7BLmcKXA";

        $msg = urlencode("✅ Connected to Smart Pill Box!");

        $url = "https://api.telegram.org/bot$botToken/sendMessage";

        $post = [
            'chat_id' => $chat_id,
            'text'    => "✅ Connected to Smart Pill Box!"
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            //file_put_contents("log.txt", "CURL ERROR: " . curl_error($ch) . PHP_EOL, FILE_APPEND);
        }

        curl_close($ch);

    } else {

        //file_put_contents("log.txt", "NO USER FOUND\n", FILE_APPEND);

        $botToken = "8058219409:AAFr8rhUWmLL14VFfmTAt2UORiV7BLmcKXA";

        $msg = urlencode("❌ Invalid code. Please try again.");

        file_get_contents("https://api.telegram.org/bot$botToken/sendMessage?chat_id=$chat_id&text=$msg");
    }

    http_response_code(200);
    echo "OK";
    exit();
}
?>