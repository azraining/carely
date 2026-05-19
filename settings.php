<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "caregiver") {
    header("Location: index.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);
$tab     = isset($_GET['tab']) ? $_GET['tab'] : 'profile';
$success = '';
$error   = '';

// ===== FETCH USER =====
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

/* UPDATE SESSION */
$_SESSION['user_name'] = $user['name'];

// ===== FETCH PATIENT =====
$patient = null;

$stmt = $conn->prepare("
    SELECT users.*
    FROM users
    INNER JOIN caregiver_patient
    ON caregiver_patient.patient_id = users.id
    WHERE caregiver_patient.caregiver_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $patient = $result->fetch_assoc();
}

// ===== SAVE PROFILE =====
if (isset($_POST['save_profile'])) {

    $name  = trim($_POST['name']);
    $email = trim($_POST['email']);

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->bind_param("si", $email, $user_id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {

        $error = "That email is already used by another account.";

    } elseif (empty($name) || empty($email)) {

        $error = "Name and email cannot be empty.";

    } else {

        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $email, $user_id);
        $stmt->execute();

        $success = "Profile updated successfully.";

        // REFRESH USER
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        // UPDATE SESSION NAME
        $_SESSION['user_name'] = $user['name'];
    }

    $tab = 'profile';
}

// ===== CHANGE PASSWORD =====
if (isset($_POST['save_password'])) {

    $current = trim($_POST['current_password']);
    $new     = trim($_POST['new_password']);
    $confirm = trim($_POST['confirm_password']);

    // CHECK CURRENT PASSWORD
    if ($current != $user['password']) {

        $error = "Your current password is incorrect.";

    }
    // CHECK LENGTH
    elseif (strlen($new) < 8) {

        $error = "New password must be at least 8 characters.";

    }
    // CHECK CONFIRM PASSWORD
    elseif ($new != $confirm) {

        $error = "New passwords do not match.";

    }
    else {

        // SAVE NEW PASSWORD DIRECTLY (NO HASH)
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new, $user_id);
        $stmt->execute();

        $success = "Password changed successfully.";

        // REFRESH USER DATA
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    }

    $tab = 'password';
}

// ===== NOTIFICATION PREFS =====
if (isset($_POST['save_notifications'])) {
    $_SESSION['notify_email']    = isset($_POST['notify_email'])    ? 1 : 0;
    $_SESSION['notify_telegram'] = isset($_POST['notify_telegram']) ? 1 : 0;
    $success = "Notification preferences saved.";
    $tab = 'notifications';
}

// ===== SAVE PATIENT ACCOUNT =====
if (isset($_POST['save_patient_account'])) {

    $patient_name  = trim($_POST['patient_name']);
    $patient_email = trim($_POST['patient_email']);
    $patient_pass  = trim($_POST['patient_password']);

    $patient_id = intval($_POST['patient_id']);

    if (empty($patient_name) || empty($patient_email)) {

        $error = "Patient name and email cannot be empty.";

    } else {

        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ?
            AND id != ?
        ");

        $stmt->bind_param("si", $patient_email, $patient_id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {

            $error = "That email is already used.";

        } else {

            // UPDATE WITHOUT PASSWORD
            if (empty($patient_pass)) {

                $stmt = $conn->prepare("
                    UPDATE users
                    SET name = ?, email = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "ssi",
                    $patient_name,
                    $patient_email,
                    $patient_id
                );

            } else {

                // UPDATE WITH PASSWORD
                $stmt = $conn->prepare("
                    UPDATE users
                    SET name = ?, email = ?, password = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "sssi",
                    $patient_name,
                    $patient_email,
                    $patient_pass,
                    $patient_id
                );
            }

            $stmt->execute();

            $success = "Patient account updated successfully.";

            // REFRESH PATIENT
            $stmt = $conn->prepare("
                SELECT users.*
                FROM users
                INNER JOIN caregiver_patient
                ON caregiver_patient.patient_id = users.id
                WHERE caregiver_patient.caregiver_id = ?
                LIMIT 1
            ");

            $stmt->bind_param("i", $user_id);
            $stmt->execute();

            $patient = $stmt->get_result()->fetch_assoc();
        }
    }

    $tab = 'patient_account';
}

$initial = strtoupper(substr($user['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carely — Settings</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── PAGE HEADER ── */
        .page-title     { font-family:'Fraunces',serif; font-size:1.8rem; font-weight:300; color:var(--lilac-deeper); margin-bottom:4px; }
        .page-title em  { font-style:italic; color:var(--lilac-dark); }
        .page-sub       { font-size:.85rem; color:var(--ink-muted); margin-bottom:28px; }

        /* ── ALERT ── */
        .alert          { padding:12px 16px; border-radius:10px; font-size:.875rem; margin-bottom:20px; font-weight:500; display:flex; align-items:center; gap:10px; }
        .alert-success  { background:var(--green-light); color:#2e6b3e; border-left:4px solid var(--green); }
        .alert-error    { background:var(--rose-light);  color:#8b2020; border-left:4px solid var(--rose); }
        .alert-info     { background:var(--lilac-light); color:var(--lilac-deep); border-left:4px solid var(--lilac-dark); }

        /* ── SETTINGS LAYOUT ── */
        .settings-grid {
            display: grid;
            grid-template-columns: 240px 1fr;
            gap: 22px;
            align-items: start;
        }

        /* ── SIDEBAR ── */
        .settings-sidebar {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 82px;
        }
        .sidebar-head {
            padding: 22px 20px 18px;
            border-bottom: 1px solid var(--border);
            text-align: center;
            background: var(--lilac-light);
        }
        .sidebar-avatar {
            width: 60px; height: 60px; border-radius: 50%;
            background: linear-gradient(135deg, var(--lilac), var(--lilac-deep));
            color: #fff;
            display: flex; align-items: center; justify-content: center;
            font-family: 'Fraunces', serif; font-size: 1.5rem; font-weight: 600;
            margin: 0 auto 10px;
        }
        .sidebar-name  { font-weight: 600; font-size: .95rem; color: var(--ink); }
        .sidebar-email { font-size: .76rem; color: var(--ink-muted); margin-top: 3px; }
        .sidebar-role  {
            display: inline-block; margin-top: 8px;
            background: rgba(200,162,200,.25); color: var(--lilac-deep);
            border-radius: 20px; padding: 2px 12px;
            font-size: .72rem; font-weight: 600; letter-spacing: .04em;
        }
        .settings-nav   { list-style: none; padding: 8px 0; }
        .settings-nav a {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 20px; font-size: .875rem; font-weight: 500;
            color: var(--ink-muted); text-decoration: none;
            border-left: 3px solid transparent;
            transition: background .15s, color .15s;
        }
        .settings-nav a:hover  { background: var(--lilac-light); color: var(--lilac-deep); text-decoration: none; }
        .settings-nav a.active { background: var(--lilac-light); color: var(--lilac-deep); border-left-color: var(--lilac-dark); font-weight: 600; }

        /* ── PANEL ── */
        .settings-panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .panel-head {
            padding: 20px 26px 16px;
            border-bottom: 1px solid var(--border);
        }
        .panel-head h3 { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--lilac-deeper); text-align:left; margin-bottom:3px; }
        .panel-head p  { font-size:.82rem; color:var(--ink-muted); }
        .panel-body    { padding: 22px 26px; }
        .panel-footer  {
            padding: 14px 26px;
            border-top: 1px solid var(--border);
            background: var(--lilac-light);
            display: flex; justify-content: flex-end; gap: 10px;
        }

        /* ── FORM FIELDS ── */
        .form-group{
            margin-top:18px;
        }

        /* REMOVE TOP SPACE INSIDE ROW */
        .field-row .form-group{
            margin-top:0;
        }

        .form-group label{
            display:block;
            font-size:.82rem;
            font-weight:500;
            color:var(--ink-muted);
            margin-bottom:5px;
        }

        /* INPUT STYLE */
        .form-group input{
            margin-top:0;
            width:100%;
            height:48px;
            padding:0 14px;
            box-sizing:border-box;
        }

        /* PERFECT ALIGNMENT */
        .field-row{
            display:flex;
            gap:16px;
            width:100%;
        }

        .field-row .form-group{
            flex:1;
            min-width:0;
        }

        .required-star{
            color:var(--rose);
            margin-left:2px;
        }

        /* DISABLED INPUT */
        .field-disabled{
            background:var(--lilac-light) !important;
            color:var(--ink-muted) !important;
            cursor:not-allowed !important;
            width:100%;
            box-sizing:border-box;
        }

        /* ── BUTTONS ── */
        .btn-save {
            background: linear-gradient(135deg, var(--lilac), var(--lilac-deep));
            color: #fff; border: none; border-radius: 8px;
            padding: 9px 22px; font-size: .875rem; font-weight: 500;
            cursor: pointer; transition: opacity .2s, transform .15s; margin-top: 0;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-save:hover { opacity: .88; transform: translateY(-1px); }

        /* ── TOGGLE (notification checkboxes) ── */
        .notif-option {
            display: flex; align-items: flex-start; gap: 14px;
            padding: 16px; border: 1px solid var(--border);
            border-radius: 10px; margin-bottom: 12px;
            transition: background .15s, border-color .15s;
            cursor: pointer;
        }
        .notif-option:hover { background: var(--lilac-light); border-color: var(--lilac-mid); }
        .notif-option input[type=checkbox] {
            width: 18px; height: 18px; margin-top: 2px; flex-shrink: 0;
            accent-color: var(--lilac-deep); cursor: pointer;
        }
        .notif-label-title { font-weight: 600; font-size: .9rem; color: var(--ink); margin-bottom: 3px; }
        .notif-label-desc  { font-size: .82rem; color: var(--ink-muted); line-height: 1.4; }

        /* ── DANGER ZONE ── */
        .danger-zone {
            border: 1px solid rgba(192,80,74,.25);
            border-radius: 10px; padding: 18px 20px; margin-bottom: 14px;
        }
        .danger-zone:last-child { margin-bottom: 0; }
        .danger-zone h4 { font-size: .88rem; color: var(--rose); font-weight: 600; margin-bottom: 4px; }
        .danger-zone p  { font-size: .82rem; color: var(--ink-muted); margin-bottom: 12px; line-height: 1.5; }
        .btn-danger {
            display: inline-flex; align-items: center; gap: 6px;
            background: var(--rose-light); color: var(--rose);
            border: 1px solid rgba(192,80,74,.35); border-radius: 8px;
            padding: 8px 16px; font-size: .82rem; font-weight: 600;
            cursor: pointer; transition: background .15s; text-decoration: none;
        }
        .btn-danger:hover { background: #f5c5c3; text-decoration: none; color: var(--rose); }

        /* ── RESPONSIVE ── */
        @media(max-width:740px) {
            .settings-grid  { grid-template-columns: 1fr; }
            .settings-sidebar { position: static; }
            .field-row      { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<?php include "navbar.php"; ?>

<div class="page">

    <h1 class="page-title fade-in">Account <em>Settings</em></h1>
    <p class="page-sub fade-in delay-1">Manage your profile, password and notification preferences.</p>

    <?php if ($success): ?>
        <div class="alert alert-success fade-in">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error fade-in">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="settings-grid fade-in delay-2">

        <!-- SIDEBAR -->
        <aside class="settings-sidebar">
            <div class="sidebar-head">
                <div class="sidebar-avatar"><?php echo $initial; ?></div>
                <div class="sidebar-name"><?php echo htmlspecialchars($user['name']); ?></div>
                <div class="sidebar-email"><?php echo htmlspecialchars($user['email']); ?></div>
                <span class="sidebar-role">CAREGIVER</span>
            </div>
            <ul class="settings-nav">
                <li><a href="?tab=profile"       class="<?php echo $tab=='profile'?'active':''; ?>">👤 &nbsp;Profile</a></li>
                <li><a href="?tab=password"      class="<?php echo $tab=='password'?'active':''; ?>">🔒 &nbsp;Password</a></li>
                <li><a href="?tab=notifications" class="<?php echo $tab=='notifications'?'active':''; ?>">🔔 &nbsp;Notifications</a></li>
                <li><a href="?tab=patient_account" class="<?php echo $tab=='patient_account'?'active':''; ?>"> ⚙️ &nbsp;Patient Account</a></li>
                <li><a href="?tab=danger"        class="<?php echo $tab=='danger'?'active':''; ?>">⚠️ &nbsp;Account</a></li>
            </ul>
        </aside>

        <!-- PANEL -->
        <div>

        <?php if ($tab === 'profile'): ?>
        <div class="settings-panel">
            <div class="panel-head">
                <h3>👤 Profile Information</h3>
                <p>Update your display name and email address.</p>
            </div>
            <form method="POST">
                <div class="panel-body">
                    <div class="field-row">
                        <div class="form-group">
                            <label>Full Name <span class="required-star">*</span></label>
                            <input type="text" name="name" required
                                   value="<?php echo htmlspecialchars($user['name']); ?>">
                        </div>
                        <div class="form-group">
                            <label>Email Address <span class="required-star">*</span></label>
                            <input type="email" name="email" required
                                   value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <input type="text" value="Caregiver" class="field-disabled" disabled>
                    </div>
                    <?php if (!empty($user['created_at'])): ?>
                    <div class="form-group">
                        <label>Member Since</label>
                        <input type="text" value="<?php echo date('d F Y', strtotime($user['created_at'])); ?>" class="field-disabled" disabled>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="panel-footer">
                    <button type="submit" name="save_profile" class="btn-save">💾 Save Changes</button>
                </div>
            </form>
        </div>

        <?php elseif ($tab === 'password'): ?>
        <div class="settings-panel">
            <div class="panel-head">
                <h3>🔒 Change Password</h3>
                <p>Use a strong password of at least 8 characters.</p>
            </div>
            <form method="POST">
                <div class="panel-body">
                    <div class="form-group">
                        <label>Current Password <span class="required-star">*</span></label>
                        <input type="password" name="current_password" required placeholder="Enter your current password">
                    </div>
                    <div class="field-row">
                        <div class="form-group">
                            <label>New Password <span class="required-star">*</span></label>
                            <input type="password" name="new_password" required placeholder="Min. 8 characters">
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password <span class="required-star">*</span></label>
                            <input type="password" name="confirm_password" required placeholder="Repeat new password">
                        </div>
                    </div>
                    <div class="alert alert-info" style="margin-top:18px; margin-bottom:0;">
                        💡 You will remain logged in after changing your password.
                    </div>
                </div>
                <div class="panel-footer">
                    <button type="submit" name="save_password" class="btn-save">🔒 Update Password</button>
                </div>
            </form>
        </div>

        <?php elseif ($tab === 'notifications'): ?>
        <div class="settings-panel">
            <div class="panel-head">
                <h3>🔔 Notification Preferences</h3>
                <p>Choose how you want to be alerted when a patient misses a dose.</p>
            </div>
            <form method="POST">
                <div class="panel-body">

                    <label class="notif-option">
                        <input type="checkbox" name="notify_email"
                               <?php echo ($_SESSION['notify_email'] ?? 1) ? 'checked' : ''; ?>>
                        <div>
                            <div class="notif-label-title">📧 Email Alerts</div>
                            <div class="notif-label-desc">
                                Send an alert to <b><?php echo htmlspecialchars($user['email']); ?></b> when a patient misses their medication.
                            </div>
                        </div>
                    </label>

                    <label class="notif-option">
                        <input type="checkbox" name="notify_telegram"
                               <?php echo ($_SESSION['notify_telegram'] ?? 1) ? 'checked' : ''; ?>>
                        <div>
                            <div class="notif-label-title">✈️ Telegram Alerts</div>
                            <div class="notif-label-desc">
                                Send an instant message via the Carely Telegram bot.
                                <?php if (empty($user['telegram_chat_id'])): ?>
                                    <a href="caregiver_dashboard.php" style="color:var(--lilac-dark);">Connect Telegram →</a>
                                <?php else: ?>
                                    <span style="color:var(--green); font-weight:600;">● Connected</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </label>

                </div>
                <div class="panel-footer">
                    <button type="submit" name="save_notifications" class="btn-save">💾 Save Preferences</button>
                </div>
            </form>
        </div>

        <?php elseif ($tab === 'patient_account'): ?>
        <div class="settings-panel">
            <div class="panel-head">
                <h3>⚙️ Patient Account</h3>
                <p>Manage your patient's account information.</p>
            </div>

            <?php if ($patient): ?>

            <form method="POST">

                <div class="panel-body">

                    <input type="hidden"
                        name="patient_id"
                        value="<?php echo $patient['id']; ?>">

                    <div class="field-row">

                        <div class="form-group">
                            <label>
                                Patient Name
                                <span class="required-star">*</span>
                            </label>

                            <input type="text"
                                name="patient_name"
                                required
                                value="<?php echo htmlspecialchars($patient['name']); ?>">
                        </div>

                        <div class="form-group">
                            <label>
                                Patient Email
                                <span class="required-star">*</span>
                            </label>

                            <input type="email"
                                name="patient_email"
                                required
                                value="<?php echo htmlspecialchars($patient['email']); ?>">
                        </div>

                    </div>

                    <div class="form-group">
                        <label>New Patient Password</label>

                        <input type="password"
                            name="patient_password"
                            placeholder="Leave blank to keep current password">
                    </div>

                    <div class="alert alert-info"
                        style="margin-top:18px; margin-bottom:0;">

                        💡 Leave password blank if you do not want to change it.

                    </div>

                </div>

                <div class="panel-footer">
                    <button type="submit"
                            name="save_patient_account"
                            class="btn-save">

                        💾 Save Patient Changes

                    </button>
                </div>

            </form>

            <?php else: ?>

                <div class="panel-body">

                    <div class="alert alert-info">
                        ℹ️ No patient linked to your account yet.
                    </div>

                </div>

            <?php endif; ?>

        </div>

        <?php elseif ($tab === 'danger'): ?>
        <div class="settings-panel">
            <div class="panel-head">
                <h3>⚠️ Account Actions</h3>
                <p>These actions are irreversible. Please read carefully before proceeding.</p>
            </div>
            <div class="panel-body">

                <div class="danger-zone">
                    <h4>Sign Out Everywhere</h4>
                    <p>End all active sessions across all devices immediately.</p>
                    <a href="logout_all.php"
                       onclick="return confirm('Sign out from all devices?')"
                       class="btn-danger">🚪 Sign Out All Sessions</a>
                </div>

                <div class="danger-zone">
                    <h4>Delete Account</h4>
                    <p>Permanently delete your Carely account. All your patient links, schedules and medication logs will be removed. This cannot be undone.</p>
                    <a href="delete_account.php"
                       onclick="return confirm('Are you absolutely sure? This cannot be undone.')"
                       class="btn-danger">🗑 Delete My Account</a>
                </div>

            </div>
        </div>

        <?php endif; ?>

        </div>
    </div>

</div>
</body>
</html>