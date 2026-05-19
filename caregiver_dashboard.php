<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "caregiver") {
    header("Location: index.php");
    exit();
}

$caregiver_id = intval($_SESSION['user_id']);

// ===== TELEGRAM STATUS =====
$connected = false;
$code = "";

$check = $conn->query("SHOW COLUMNS FROM users LIKE 'telegram_chat_id'");
if ($check && $check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT telegram_chat_id, telegram_code FROM users WHERE id = ?");
    $stmt->bind_param("i", $caregiver_id);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();

    $connected = !empty($userData['telegram_chat_id']);

    if (empty($userData['telegram_code'])) {
        $code = rand(10000, 99999);
        $stmt2 = $conn->prepare("UPDATE users SET telegram_code = ? WHERE id = ?");
        $stmt2->bind_param("si", $code, $caregiver_id);
        $stmt2->execute();
    } else {
        $code = $userData['telegram_code'];
    }
}

// ===== GET CAREGIVER NAME =====
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $caregiver_id);
$stmt->execute();
$caregiverData = $stmt->get_result()->fetch_assoc();
$caregiverName = $caregiverData['name'] ?? 'Caregiver';

// ===== GET PATIENTS =====
$stmt = $conn->prepare("
    SELECT u.* FROM users u
    JOIN caregiver_patient cp ON u.id = cp.patient_id
    WHERE cp.caregiver_id = ?
");
$stmt->bind_param("i", $caregiver_id);
$stmt->execute();
$patients = $stmt->get_result();
$patientCount = $patients->num_rows;

// ===== COUNT TODAY'S LOGS =====
$stmt = $conn->prepare("
    SELECT COUNT(*) as total,
           SUM(CASE WHEN ml.status='Taken' THEN 1 ELSE 0 END) as taken,
           SUM(CASE WHEN ml.status='Missed' THEN 1 ELSE 0 END) as missed
    FROM medication_logs ml
    JOIN caregiver_patient cp ON ml.patient_id = cp.patient_id
    WHERE cp.caregiver_id = ?
    AND DATE(ml.taken_time) = CURDATE()
");
$stmt->bind_param("i", $caregiver_id);
$stmt->execute();
$todayStats = $stmt->get_result()->fetch_assoc();

$todayTaken  = intval($todayStats['taken']);
$todayMissed = intval($todayStats['missed']);
$todayTotal  = intval($todayStats['total']);

$patients->data_seek(0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carely — Caregiver Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,300;0,600;1,300&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <meta http-equiv="refresh" content="30">
</head>
<body>

<?php include "navbar.php"; ?>

<div class="page" style="max-width:1100px;">

    <!-- HEADING -->
    <h1 class="page-heading fade-in">
        Caregiver <em>Dashboard</em>
        <span class="live-badge"><span class="live-dot"></span> Live</span>
    </h1>
    <p class="page-subhead fade-in delay-1">
        <?php echo date('l, d F Y'); ?> &nbsp;·&nbsp; Auto-refreshes every 30 seconds
    </p>

    <!-- STATS -->
    <div class="stats-row fade-in delay-2">
        <div class="stat-card stat-patients">
            <div class="stat-icon">👥</div>
            <div class="stat-value"><?php echo $patientCount; ?></div>
            <div class="stat-label">Patients</div>
        </div>
        <div class="stat-card stat-taken">
            <div class="stat-icon">✅</div>
            <div class="stat-value"><?php echo $todayTaken; ?></div>
            <div class="stat-label">Taken Today</div>
        </div>
        <div class="stat-card stat-missed">
            <div class="stat-icon">⚠️</div>
            <div class="stat-value"><?php echo $todayMissed; ?></div>
            <div class="stat-label">Missed Today</div>
        </div>
        <div class="stat-card stat-total">
            <div class="stat-icon">💊</div>
            <div class="stat-value"><?php echo $todayTotal; ?></div>
            <div class="stat-label">Total Today</div>
        </div>
    </div>

    

    <!-- TELEGRAM -->
    <div class="section-title fade-in delay-3">🔔 Notifications</div>
    <div class="telegram-card fade-in delay-3">
        <div class="telegram-info">
            <h4>Telegram Alerts</h4>
            <p>Get instant push notifications whenever a patient misses their medication.</p>

            <?php if (!$connected): ?>
            <div class="telegram-setup">
                <div class="setup-step">
                    <span class="step-num">1</span>
                    <div class="step-body">
                        Open the bot on Telegram<br>
                        <a href="https://t.me/MediReminderrr_bot" target="_blank" class="btn-tg">
                            ✈️ Open Telegram Bot
                        </a>
                    </div>
                </div>
                <div class="setup-step">
                    <span class="step-num">2</span>
                    <div class="step-body">
                        Send this command to the bot:<br>
                        <span class="code-chip">/start <?php echo htmlspecialchars($code); ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="telegram-status">
            <span class="status-dot <?php echo $connected ? 'connected' : 'disconnected'; ?>"></span>
            <span class="status-text <?php echo $connected ? 'connected' : 'disconnected'; ?>">
                <?php echo $connected ? 'Connected' : 'Not Connected'; ?>
            </span>
        </div>
    </div>

    <!-- PATIENTS -->
    <div class="section-title fade-in delay-4">👥 My Patients &amp; Schedules</div>

    <?php if ($patientCount === 0): ?>
    <div class="empty-state fade-in">
        <div class="empty-icon">🏥</div>
        <p>No patients assigned yet. Contact your administrator to add patients.</p>
    </div>

    <?php else: ?>
    <div class="patients-grid">

    <?php
    $cardDelay = 4;
    while ($row = $patients->fetch_assoc()):
        $pid     = intval($row['id']);
        $initial = strtoupper(substr($row['name'], 0, 1));
        $cardDelay++;

        $stmt = $conn->prepare("
            SELECT * FROM medication_schedule
            WHERE patient_id = ?
            ORDER BY medication_hour, medication_minute
        ");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $schedules = $stmt->get_result();
    ?>

    <div class="patient-card fade-in" style="animation-delay:<?php echo ($cardDelay * 0.05); ?>s">

        <div class="patient-card-header">
            <div class="patient-avatar"><?php echo $initial; ?></div>
            <div class="patient-meta">
                <div class="patient-name"><?php echo htmlspecialchars($row['name']); ?></div>
                <div class="patient-email"><?php echo htmlspecialchars($row['email']); ?></div>
            </div>
        </div>

        <div class="patient-card-body">
            <?php if ($schedules->num_rows > 0): ?>
            <ul class="schedule-list">
            <?php while ($s = $schedules->fetch_assoc()):
                $hour   = str_pad($s['medication_hour'],   2, '0', STR_PAD_LEFT);
                $minute = str_pad($s['medication_minute'], 2, '0', STR_PAD_LEFT);
            ?>
                <li class="schedule-item">
                    <span class="schedule-time"><?php echo "$hour:$minute"; ?></span>
                    <span class="schedule-name">💊 <?php echo htmlspecialchars($s['medicine_name']); ?></span>
                </li>
            <?php endwhile; ?>
            </ul>
            <?php else: ?>
                <p class="no-schedule">No schedule added yet.</p>
            <?php endif; ?>
        </div>

        <div class="card-actions">
            <a href="add_schedule.php?patient_id=<?php echo $pid; ?>" class="btn-action btn-primary">
                ➕ Add Schedule
            </a>
            <a href="medication_logs.php?patient_id=<?php echo $pid; ?>" class="btn-action btn-secondary">
                📋 View Logs
            </a>
            <a href="report_dashboard.php?patient_id=<?php echo $pid; ?>" class="btn-action btn-secondary">
                📊 Report
            </a>
        </div>

    </div>

    <?php endwhile; ?>
    </div>
    <?php endif; ?>

</div>
</body>
</html>