<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "patient") {
    header("Location: index.php");
    exit();
}

$patient_id = intval($_SESSION['user_id']);

// Summary
$stmt = $conn->prepare("
SELECT
COUNT(*) AS total,
SUM(status='Taken') AS taken,
SUM(status='Missed') AS missed
FROM medication_logs
WHERE patient_id=?
");
$stmt->bind_param("i",$patient_id);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

$total  = $summary['total'] ?? 0;
$taken  = $summary['taken'] ?? 0;
$missed = $summary['missed'] ?? 0;

// History
$stmt = $conn->prepare("
SELECT *
FROM medication_logs
WHERE patient_id=?
ORDER BY taken_time DESC
");
$stmt->bind_param("i",$patient_id);
$stmt->execute();
$logs = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Carely — Medication History</title>

<link rel="stylesheet" href="style.css">

<style>

body{
    font-size:18px;
}

.page-title{
    font-family:'Fraunces',serif;
    font-size:1.8rem;
    color:var(--lilac-deeper);
}

.page-title em{
    font-style:italic;
}

.page-sub{
    color:var(--ink-muted);
    margin-bottom:30px;
}

.summary-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
    margin-bottom:35px;
}

.summary-card{

    background:#fff;
    border:1px solid var(--border);
    border-radius:15px;
    padding:25px;
    box-shadow:var(--shadow-sm);
}

.summary-title{

    color:var(--ink-muted);
    font-size:.9rem;
}

.summary-value{

    font-size:2rem;
    font-weight:bold;
    margin-top:10px;
    color:var(--lilac-deeper);
}

.section-title{

    font-family:'Fraunces',serif;
    font-size:1.1rem;
    margin-bottom:15px;
    color:var(--lilac-deeper);
}

.history-table{

    width:100%;
    border-collapse:collapse;
    background:#fff;
    border-radius:15px;
    overflow:hidden;
    box-shadow:var(--shadow-sm);
}

.history-table th{

    background:var(--lilac-light);
    padding:15px;
    text-align:left;
}

.history-table td{

    padding:15px;
    border-bottom:1px solid #eee;
}

.history-table tr:hover{

    background:#faf7fc;
}

.status{

    padding:6px 15px;
    border-radius:20px;
    color:white;
    font-weight:600;
}

.taken{

    background:#4CAF50;
}

.missed{

    background:#e74c3c;
}

.empty-note{

    background:white;
    padding:40px;
    text-align:center;
    border-radius:15px;
    border:1px solid var(--border);
    color:gray;
}

</style>

</head>

<body>

<?php include "patient_navbar.php"; ?>

<div class="page">

<h1 class="page-title fade-in">
Medication <em>History</em>
</h1>

<p class="page-sub fade-in delay-1">
View your previous medication intake records.
</p>

<div class="summary-grid fade-in delay-2">

<div class="summary-card">
<div class="summary-title">Total Records</div>
<div class="summary-value"><?php echo $total; ?></div>
</div>

<div class="summary-card">
<div class="summary-title">Taken</div>
<div class="summary-value" style="color:#4CAF50;">
<?php echo $taken; ?>
</div>
</div>

<div class="summary-card">
<div class="summary-title">Missed</div>
<div class="summary-value" style="color:#e74c3c;">
<?php echo $missed; ?>
</div>
</div>

</div>

<div class="section-title fade-in delay-3">
📋 Medication History
</div>

<?php if($logs->num_rows==0): ?>

<div class="empty-note fade-in delay-3">
No medication history available.
</div>

<?php else: ?>

<table class="history-table fade-in delay-3">

<thead>

<tr>

<th>Date & Time</th>
<th>Medicine</th>
<th>Status</th>

</tr>

</thead>

<tbody>

<?php while($row=$logs->fetch_assoc()): ?>

<tr>

<td>

<?php
echo date("d M Y h:i A",strtotime($row['taken_time']));
?>

</td>

<td>

💊
<?php
echo htmlspecialchars($row['medicine_name']);
?>

</td>

<td>

<?php if($row['status']=="Taken"){ ?>

<span class="status taken">

✅ Taken

</span>

<?php } else { ?>

<span class="status missed">

❌ Missed

</span>

<?php } ?>

</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php endif; ?>

</div>

</body>
</html>