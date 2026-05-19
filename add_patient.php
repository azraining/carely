<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "caregiver") {
    header("Location: index.php");
    exit();
}

$caregiver_id = intval($_SESSION['user_id']);

/* ===== ADD PATIENT + PAIR DEVICE ===== */
if (isset($_POST['add_patient'])) {

    $name        = trim($_POST['name']);
    $email       = trim($_POST['email']);
    $password    = $_POST['password'];
    $device_code = trim($_POST['device_code']);

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        $error = "A patient with this email already exists.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'patient')");
        $stmt->bind_param("sss", $name, $email, $password);
        $stmt->execute();
        $patient_id = $conn->insert_id;

        $stmt = $conn->prepare("INSERT INTO caregiver_patient (caregiver_id, patient_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $caregiver_id, $patient_id);
        $stmt->execute();

        if (!empty($device_code)) {
            $stmt = $conn->prepare("
                INSERT INTO device_pairing (device_code, patient_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE patient_id = ?
            ");
            $stmt->bind_param("sii", $device_code, $patient_id, $patient_id);
            $stmt->execute();
        }

        $success = "Patient added successfully" . (!empty($device_code) ? " and device paired!" : "!");
    }
}

/* ===== PAIR EXISTING PATIENT ===== */
if (isset($_POST['pair_existing'])) {

    $patient_id  = intval($_POST['patient_id']);
    $device_code = trim($_POST['device_code']);

    $stmt = $conn->prepare("
        INSERT INTO device_pairing (device_code, patient_id)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE patient_id = ?
    ");
    $stmt->bind_param("sii", $device_code, $patient_id, $patient_id);
    $stmt->execute();

    $success = "Device paired successfully!";
}

// Get patients for dropdown
$stmt = $conn->prepare("
    SELECT u.id, u.name FROM users u
    JOIN caregiver_patient cp ON u.id = cp.patient_id
    WHERE cp.caregiver_id = ?
    ORDER BY u.name
");
$stmt->bind_param("i", $caregiver_id);
$stmt->execute();
$patients = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carely — Add Patient</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .form-group { margin-top: 18px; }
        .form-group label {
            display: block;
            font-size: .82rem;
            font-weight: 500;
            color: var(--ink-muted);
            margin-bottom: 5px;
        }
        .form-group input,
        .form-group select {
            margin-top: 0;
        }
        .input-hint {
            font-size: .76rem;
            color: var(--ink-muted);
            margin-top: 5px;
        }
        .section-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            background: var(--lilac-light);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            margin-bottom: 12px;
        }
        .container h3 {
            text-align: left;
            font-size: 1rem;
            color: var(--lilac-deeper);
            margin-bottom: 4px;
        }
        .container .sub {
            font-size: .82rem;
            color: var(--ink-muted);
            margin-bottom: 18px;
        }
        .divider-or {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 8px 0 24px;
            color: var(--ink-muted);
            font-size: .82rem;
        }
        .divider-or::before,
        .divider-or::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }
        .btn-full {
            width: 100%;
            margin-top: 22px;
            padding: 12px;
            font-size: .9rem;
            justify-content: center;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .page-title {
            font-family: 'Fraunces', serif;
            font-size: 1.8rem;
            font-weight: 300;
            color: var(--lilac-deeper);
            margin-bottom: 4px;
        }
        .page-title em { font-style: italic; color: var(--lilac-dark); }
        .page-sub {
            font-size: .85rem;
            color: var(--ink-muted);
            margin-bottom: 28px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: .875rem;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success {
            background: var(--green-light);
            color: #2e6b3e;
            border-left: 4px solid var(--green);
        }
        .alert-error {
            background: var(--rose-light);
            color: #8b2020;
            border-left: 4px solid var(--rose);
        }
        .required-star { color: var(--rose); margin-left: 2px; }
    </style>
</head>
<body>


<?php include "navbar.php"; ?>


<div class="page">

    <h1 class="page-title fade-in">Add <em>Patient</em></h1>
    <p class="page-sub fade-in delay-1">Register a new patient or pair a Carely device to an existing one.</p>

    <?php if (isset($error)): ?>
        <div class="alert alert-error fade-in">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success fade-in">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- ADD NEW PATIENT -->
    <div class="container fade-in delay-2">
        <div class="section-icon">👤</div>
        <h3>Add New Patient</h3>
        <p class="sub">Create a new patient account and optionally link their device right away.</p>

        <form method="POST">

            <div class="form-group">
                <label>Full Name <span class="required-star">*</span></label>
                <input type="text" name="name" placeholder="e.g. Ahmad bin Yusof" required
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label>Email Address <span class="required-star">*</span></label>
                <input type="email" name="email" placeholder="patient@email.com" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <div class="form-group">
                <label>Password <span class="required-star">*</span></label>
                <input type="password" name="password" placeholder="Set a password for the patient" required>
            </div>

            <div class="form-group">
                <label>Device Code <span style="color:var(--ink-muted);font-weight:400;">(optional)</span></label>
                <input type="text" name="device_code" placeholder="e.g. BOX001"
                       value="<?php echo isset($_POST['device_code']) ? htmlspecialchars($_POST['device_code']) : ''; ?>">
                <p class="input-hint">Leave blank if you want to pair the device later.</p>
            </div>

            <button name="add_patient" class="btn btn-full">➕ Add Patient</button>

        </form>
    </div>

    <div class="divider-or fade-in delay-3">or</div>

    <!-- PAIR EXISTING PATIENT -->
    <div class="container fade-in delay-3">
        <div class="section-icon">🔗</div>
        <h3>Pair Device to Existing Patient</h3>
        <p class="sub">Link a Carely device to a patient you have already registered.</p>

        <form method="POST">

            <div class="form-group">
                <label>Select Patient <span class="required-star">*</span></label>
                <select name="patient_id" required>
                    <option value="">— Select a patient —</option>
                    <?php while ($p = $patients->fetch_assoc()): ?>
                        <option value="<?php echo $p['id']; ?>">
                            <?php echo htmlspecialchars($p['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Device Code <span class="required-star">*</span></label>
                <input type="text" name="device_code" placeholder="e.g. BOX001" required>
                <p class="input-hint">The code is printed on the bottom of the Carely.</p>
            </div>

            <button name="pair_existing" class="btn btn-full">🔗 Pair Device</button>

        </form>
    </div>

</div>
</body>
</html>