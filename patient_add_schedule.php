<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "patient") {
    header("Location: index.php");
    exit();
}

$patient_id = intval($_SESSION['user_id']);

// ===== ADD =====
if (isset($_POST['add'])) {
    $medicine = trim($_POST['medicine_name']);
    $hour     = intval($_POST['hour']);
    $minute   = intval($_POST['minute']);

    $stmt = $conn->prepare("
        SELECT id FROM medication_schedule
        WHERE patient_id = ? AND medication_hour = ? AND medication_minute = ?
    ");
    $stmt->bind_param("iii", $patient_id, $hour, $minute);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        $error = "A medication is already scheduled at this time.";
    } else {
        $stmt = $conn->prepare("
            INSERT INTO medication_schedule (patient_id, medicine_name, medication_hour, medication_minute)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("isii", $patient_id, $medicine, $hour, $minute);
        $stmt->execute();
        $success = "Medication added successfully!";
    }
}

// ===== DELETE =====
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM medication_schedule WHERE id = ? AND patient_id = ?");
    $stmt->bind_param("ii", $id, $patient_id);
    $stmt->execute();
    $success = "Medication removed.";
}

// GET CURRENT SCHEDULE
$stmt = $conn->prepare("SELECT * FROM medication_schedule WHERE patient_id = ? ORDER BY medication_hour, medication_minute");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$schedules = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carely — My Medication</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body{ font-size:18px; line-height:1.6;}
        .page-title    { font-family:'Fraunces',serif; font-size:1.8rem; font-weight:300; color:var(--lilac-deeper); margin-bottom:4px; }
        .page-title em { font-style:italic; color:var(--lilac-dark); }
        .page-sub      { font-size:.85rem; color:var(--ink-muted); margin-bottom:28px; }
        .alert         { padding:12px 16px; border-radius:10px; font-size:.875rem; margin-bottom:20px; font-weight:500; display:flex; align-items:center; gap:10px; }
        .alert-success { background:var(--green-light); color:#2e6b3e; border-left:4px solid var(--green); }
        .alert-error   { background:var(--rose-light);  color:#8b2020; border-left:4px solid var(--rose); }

        /* Add form */
        .section-icon  { width:40px;height:40px;border-radius:10px;background:var(--lilac-light);display:flex;align-items:center;justify-content:center;font-size:1.2rem;margin-bottom:12px; }
        .container h3  { text-align:left; font-size:1rem; color:var(--lilac-deeper); margin-bottom:4px; }
        .container .sub{ font-size:.82rem; color:var(--ink-muted); margin-bottom:18px; }
        .form-row      { display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap; margin-top:20px; }
        .form-row .fg  { flex:1; min-width:120px; display:flex; flex-direction:column; gap:4px; }
        .form-row .fg label { font-size:.95rem; font-weight:600; color:var(--ink-muted); }
        .form-row .fg input,
        .form-row .fg select { font-size:0.95rem; padding:12px 14px; border-radius:10px; margin-top:0; }
        .btn-add       { margin-top:0; padding:14px 22px; font-size:1rem; border-radius:10px; white-space:nowrap; align-self:flex-end; }
        .required-star { color:var(--rose); margin-left:2px; }

        /* Schedule list */
        .section-title { font-family:'Fraunces',serif; font-size:1.05rem; font-weight:600; color:var(--lilac-deeper); margin-bottom:14px; display:flex; align-items:center; gap:8px; }
        .section-title::after { content:''; flex:1; height:1px; background:#e0d0e0; }
        .sched-row {
            background:#fff; border:1px solid var(--border); border-radius:14px;
            padding:18px 20px; margin-bottom:12px;
            display:flex; align-items:center; gap:16px; flex-wrap:wrap;
            box-shadow:var(--shadow-sm); transition:box-shadow .2s;
        }
        .sched-row:hover { box-shadow:var(--shadow-md); }
        .sched-time  { background:var(--lilac-light); color:var(--lilac-deep); border-radius:10px; padding:6px 14px; font-family:'Fraunces',serif; font-size:1.2rem; font-weight:600; flex-shrink:0; min-width:80px; text-align:center; }
        .sched-name  { flex:1; font-weight:500; color:var(--ink); font-size:1.05rem; }
        .btn-remove  {
            display:inline-flex; align-items:center; gap:5px;
            background:#fff; color:var(--rose); border:1px solid rgba(192,80,74,.3);
            border-radius:10px; padding:10px 16px; font-size:.95rem; font-weight:500;
            cursor:pointer; transition:background .15s; text-decoration:none; flex-shrink:0;
        }
        .btn-remove:hover { background:var(--rose-light); text-decoration:none; color:var(--rose); }
        .empty-note { text-align:center; color:var(--ink-muted); font-style:italic; font-size:1rem; padding:26px; background:#fff; border:1px solid var(--border); border-radius:var(--radius); }

        @media(max-width:600px){ body{ font-size:17px;} .sched-row{ flex-direction:column; align-items:flex-start;} .btn-remove{ width:100%; justify-content:center;} .form-row{flex-direction:column;} }
    </style>
</head>
<body>

<?php include "patient_navbar.php"; ?>

<div class="page">

    <h1 class="page-title fade-in">My <em>Medication</em></h1>
    <p class="page-sub fade-in delay-1">Add or remove medications from your daily schedule.</p>

    <?php if (isset($success)): ?>
        <div class="alert alert-success fade-in">✅ <?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-error fade-in">⚠️ <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- ADD FORM -->
    <div class="container fade-in delay-2">
        <div class="section-icon">➕</div>
        <h3>Add New Medication</h3>
        <p class="sub">Set a daily reminder time for a medication.</p>

        <form method="POST">
            <div class="form-row">
                <div class="fg" style="flex:2; min-width:180px;">
                    <label>Medicine Name <span class="required-star">*</span></label>
                    <input type="text" name="medicine_name" placeholder="e.g. Paracetamol 500mg" required>
                </div>
                <div class="fg" style="min-width:90px;">
                    <label>Hour</label>
                    <select name="hour">
                        <?php for ($i=0;$i<24;$i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo str_pad($i,2,'0',STR_PAD_LEFT); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="fg" style="min-width:90px;">
                    <label>Minute</label>
                    <select name="minute">
                        <?php for ($i=0;$i<60;$i+=5): ?>
                            <option value="<?php echo $i; ?>"><?php echo str_pad($i,2,'0',STR_PAD_LEFT); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <button name="add" class="btn btn-add">💊 Add</button>
            </div>
        </form>
    </div>

    <!-- CURRENT SCHEDULE -->
    <div class="section-title fade-in delay-3">📅 Current Schedule</div>

    <?php if ($schedules->num_rows === 0): ?>
        <div class="empty-note fade-in delay-3">No medications scheduled yet.

        Please add your medication using the form above.</div>
    <?php else: ?>
        <?php while ($s = $schedules->fetch_assoc()):
            $h = str_pad($s['medication_hour'],   2,'0',STR_PAD_LEFT);
            $m = str_pad($s['medication_minute'], 2,'0',STR_PAD_LEFT);
        ?>
        <div class="sched-row fade-in delay-3">
            <span class="sched-time"><?php echo "$h:$m"; ?></span>
            <span class="sched-name">💊 <?php echo htmlspecialchars($s['medicine_name']); ?></span>
            <a href="?delete=<?php echo $s['id']; ?>"
               class="btn-remove"
               onclick="return confirm('Remove <?php echo htmlspecialchars($s['medicine_name']); ?> from your schedule?')">
               ❌ Delete
            </a>
        </div>
        <?php endwhile; ?>
    <?php endif; ?>

</div>
</body>
</html>