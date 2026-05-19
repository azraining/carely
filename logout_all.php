<?php
session_start();
include "db_connect.php";

if (isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);

    // Invalidate any remember-me / persistent tokens if you have them
    // (extend this if you add a tokens table later)
    $conn->prepare("UPDATE users SET telegram_code = NULL WHERE id = ?")
         ->bind_param("i", $user_id);
    // Note: we only clear telegram_code if you want to force re-link;
    // remove that line if you want telegram to stay connected.
}

session_unset();
session_destroy();

// Restart a clean session to flash a message on the login page
session_start();
$_SESSION['flash'] = "You have been signed out from all sessions.";
header("Location: index.php");
exit();