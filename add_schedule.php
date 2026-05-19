<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "caregiver") {
    header("Location: index.php");
    exit();
}

$caregiver_id = intval($_SESSION['user_id']);

// ===== ADD =====
if (isset($_POST['add'])) {
    $patient_id = intval($_POST['patient_id']);
    $medicine   = trim($_POST['medicine_name']);
    $hour       = intval($_POST['hour']);
    $minute     = intval($_POST['minute']);

    $stmt = $conn->prepare("
        INSERT INTO medication_schedule (patient_id, medicine_name, medication_hour, medication_minute)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isii", $patient_id, $medicine, $hour, $minute);
    $stmt->execute();
    $success = "Schedule added successfully!";
}

// ===== DELETE =====
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM medication_schedule WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $success = "Schedule deleted.";
}

// ===== UPDATE =====
if (isset($_POST['update'])) {
    $id       = intval($_POST['id']);
    $medicine = trim($_POST['medicine_name']);
    $hour     = intval($_POST['hour']);
    $minute   = intval($_POST['minute']);

    $stmt = $conn->prepare("
        UPDATE medication_schedule
        SET medicine_name = ?, medication_hour = ?, medication_minute = ?
        WHERE id = ?
    ");
    $stmt->bind_param("siii", $medicine, $hour, $minute, $id);
    $stmt->execute();
    $success = "Schedule updated successfully!";
}

// ===== GET PATIENTS =====
$stmt = $conn->prepare("
    SELECT u.id, u.name FROM users u
    JOIN caregiver_patient cp ON u.id = cp.patient_id
    WHERE cp.caregiver_id = ?
    ORDER BY u.name
");
$stmt->bind_param("i", $caregiver_id);
$stmt->execute();
$patients = $stmt->get_result();

// ===== GET SCHEDULES (only for this caregiver's patients) =====
$stmt = $conn->prepare("
    SELECT ms.*, u.name AS patient_name
    FROM medication_schedule ms
    JOIN users u ON u.id = ms.patient_id
    JOIN caregiver_patient cp ON cp.patient_id = ms.patient_id
    WHERE cp.caregiver_id = ?
    ORDER BY u.name, ms.medication_hour, ms.medication_minute
");
$stmt->bind_param("i", $caregiver_id);
$stmt->execute();
$schedules = $stmt->get_result();

// Group schedules by patient name
$grouped = [];
while ($s = $schedules->fetch_assoc()) {
    $grouped[$s['patient_name']][] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carely — Manage Schedule</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .page-title   { font-family:'Fraunces',serif; font-size:1.8rem; font-weight:300; color:var(--lilac-deeper); margin-bottom:4px; }
        .page-title em{ font-style:italic; color:var(--lilac-dark); }
        .page-sub     { font-size:.85rem; color:var(--ink-muted); margin-bottom:28px; }

        .alert        { padding:12px 16px; border-radius:10px; font-size:.875rem; margin-bottom:20px; font-weight:500; display:flex; align-items:center; gap:10px; }
        .alert-success{ background:var(--green-light); color:#2e6b3e; border-left:4px solid var(--green); }

        /* ADD FORM */
        .section-icon { width:40px;height:40px;border-radius:10px;background:var(--lilac-light);display:flex;align-items:center;justify-content:center;font-size:1.2rem;margin-bottom:12px; }
        .container h3 { text-align:left; font-size:1rem; color:var(--lilac-deeper); margin-bottom:4px; }
        .container .sub { font-size:.82rem; color:var(--ink-muted); margin-bottom:18px; }

        .form-row     { display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; margin-top:18px; }
        .form-row .fg { flex:1; min-width:120px; }
        .form-row .fg label { display:block; font-size:.8rem; font-weight:500; color:var(--ink-muted); margin-bottom:5px; }
        .form-row .fg input,
        .form-row .fg select { margin-top:0; }
        .form-row .fg-name   { flex:2; min-width:180px; }
        .form-row .fg-time   { min-width:90px; }

        .btn-add { margin-top:0; padding:11px 20px; white-space:nowrap; }

        /* PATIENT GROUP */
        .patient-group        { margin-bottom:28px; }
        .patient-group-header {
            display:flex; align-items:center; gap:12px;
            margin-bottom:12px;
        }
        .patient-group-avatar {
            width:36px;height:36px;border-radius:50%;
            background:linear-gradient(135deg,var(--lilac),var(--lilac-deep));
            color:#fff;display:flex;align-items:center;justify-content:center;
            font-family:'Fraunces',serif;font-size:.95rem;font-weight:600;flex-shrink:0;
        }
        .patient-group-name { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--lilac-deeper); }

        /* SCHEDULE ROW */
        .schedule-row {
            background:#fff;
            border:1px solid var(--border);
            border-radius:10px;
            padding:14px 18px;
            margin-bottom:10px;
            display:flex;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
            box-shadow:var(--shadow-sm);
            transition:box-shadow .2s;
        }
        .schedule-row:hover { box-shadow:var(--shadow-md); }
        .schedule-row .fg   { flex:1; min-width:110px; }
        .schedule-row .fg label { display:block; font-size:.75rem; font-weight:500; color:var(--ink-muted); margin-bottom:4px; }
        .schedule-row .fg input,
        .schedule-row .fg select { margin-top:0; padding:8px 10px; font-size:.87rem; }
        .schedule-row .fg-name   { flex:2; min-width:160px; }
        .schedule-row .fg-time   { min-width:80px; }
        .schedule-row .row-actions { display:flex; gap:8px; flex-shrink:0; }

        .btn-update {
            background:linear-gradient(135deg,var(--lilac),var(--lilac-deep));
            color:#fff; border:none; border-radius:8px;
            padding:8px 14px; font-size:.8rem; font-weight:500;
            cursor:pointer; transition:opacity .2s, transform .15s;
            white-space:nowrap; margin-top:0;
        }
        .btn-update:hover { opacity:.88; transform:translateY(-1px); }

        .btn-delete {
            background:#fff; color:var(--rose);
            border:1px solid rgba(192,80,74,.35);
            border-radius:8px; padding:8px 14px;
            font-size:.8rem; font-weight:500;
            cursor:pointer; transition:background .2s, transform .15s;
            white-space:nowrap; text-decoration:none; margin-top:0;
            display:inline-flex; align-items:center; gap:4px;
        }
        .btn-delete:hover { background:var(--rose-light); color:var(--rose); transform:translateY(-1px); text-decoration:none; }

        .empty-group { font-size:.85rem; color:var(--ink-muted); font-style:italic; padding:8px 0; }

        @media(max-width:600px) {
            .form-row   { flex-direction:column; }
            .schedule-row { flex-direction:column; align-items:stretch; }
            .schedule-row .row-actions { justify-content:flex-end; }
        }
    </style>
</head>
<body>

<?php include "navbar.php"; ?>

<div class="page">

    <h1 class="page-title fade-in">Medication <em>Schedule</em></h1>
    <p class="page-sub fade-in delay-1">Add, update or remove scheduled doses for your patients.</p>

    <?php if (isset($success)): ?>
        <div class="alert alert-success fade-in">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <!-- ADD NEW SCHEDULE -->
    <div class="container fade-in delay-2">
        <div class="section-icon">➕</div>
        <h3>Add New Schedule</h3>
        <p class="sub">Set a daily medication reminder for one of your patients.</p>

        <form method="POST">
            <div class="form-row">

                <div class="fg">
                    <label>Patient</label>
                    <select name="patient_id" required>
                        <option value="">— Select patient —</option>
                        <?php
                        $patients->data_seek(0);
                        while ($p = $patients->fetch_assoc()):
                        ?>
                            <option value="<?php echo $p['id']; ?>"
                                <?php echo (isset($_POST['patient_id']) && $_POST['patient_id'] == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="fg fg-name">
                    <label>Medicine Name</label>
                    <input type="text" name="medicine_name" placeholder="e.g. Paracetamol 500mg" required
                           value="<?php echo isset($_POST['medicine_name']) ? htmlspecialchars($_POST['medicine_name']) : ''; ?>">
                </div>

                <div class="fg fg-time">
                    <label>Hour</label>
                    <select name="hour">
                        <?php for ($i = 0; $i < 24; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="fg fg-time">
                    <label>Minute</label>
                    <select name="minute">
                        <?php for ($i = 0; $i < 60; $i += 5): ?>
                            <option value="<?php echo $i; ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT);  ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div>
                    <label style="visibility:hidden">Add</label>
                    <button name="add" class="btn btn-add">💊 Add</button>
                </div>

            </div>
        </form>
    </div>

    <!-- EXISTING SCHEDULES -->
    <?php if (empty($grouped)): ?>
        <div class="container fade-in delay-3" style="text-align:center; color:var(--ink-muted); padding:40px;">
            <p style="font-size:2rem; margin-bottom:10px;">📋</p>
            <p>No schedules added yet. Use the form above to get started.</p>
        </div>

    <?php else: ?>

        <?php foreach ($grouped as $patientName => $rows):
            $initial = strtoupper(substr($patientName, 0, 1));
        ?>
        <div class="patient-group fade-in delay-3">

            <div class="patient-group-header">
                <div class="patient-group-avatar"><?php echo $initial; ?></div>
                <span class="patient-group-name"><?php echo htmlspecialchars($patientName); ?></span>
            </div>

            <?php foreach ($rows as $s):
                $h = str_pad($s['medication_hour'],   2, '0', STR_PAD_LEFT);
                $m = str_pad($s['medication_minute'], 2, '0', STR_PAD_LEFT);
            ?>
            <form method="POST">
                <input type="hidden" name="id" value="<?php echo $s['id']; ?>">

                <div class="schedule-row">

                    <div class="fg fg-name">
                        <label>Medicine</label>
                        <input type="text" name="medicine_name"
                               value="<?php echo htmlspecialchars($s['medicine_name']); ?>" required>
                    </div>

                    <div class="fg fg-time">
                        <label>Hour</label>
                        <select name="hour">
                            <?php for ($i = 0; $i < 24; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($i == $s['medication_hour']) ? 'selected' : ''; ?>>
                                    <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="fg fg-time">
                        <label>Minute</label>
                        <select name="minute">
                            <?php for ($i = 0; $i < 60; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($i == $s['medication_minute']) ? 'selected' : ''; ?>>
                                    <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="row-actions">
                        <button name="update" class="btn-update">✔ Update</button>
                        <a href="add_schedule.php?delete=<?php echo $s['id']; ?>"
                           class="btn-delete"
                           onclick="return confirm('Delete <?php echo htmlspecialchars($s['medicine_name']); ?> from schedule?')">
                           🗑 Delete
                        </a>
                    </div>

                </div>
            </form>
            <?php endforeach; ?>

        </div>
        <?php endforeach; ?>

    <?php endif; ?>

</div>
</body>
</html>