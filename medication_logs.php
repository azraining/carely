<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "caregiver") {
    header("Location: index.php");
    exit();
}

$caregiver_id = intval($_SESSION['user_id']);

// ===== FILTER =====
$filterPatient = isset($_GET['patient_id']) ? intval($_GET['patient_id'])  : 0;
$filterStatus  = isset($_GET['status'])     ? trim($_GET['status'])        : '';
$filterSearch  = isset($_GET['search'])     ? trim($_GET['search'])        : '';

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
$patientList = [];
while ($r = $patients->fetch_assoc()) $patientList[] = $r;

// ===== BUILD LOG QUERY =====
$whereClauses = ["cp.caregiver_id = ?"];
$params       = [$caregiver_id];
$types        = "i";

if ($filterPatient > 0) {
    $whereClauses[] = "ml.patient_id = ?";
    $params[]       = $filterPatient;
    $types         .= "i";
}
if ($filterStatus !== '') {
    $whereClauses[] = "ml.status = ?";
    $params[]       = $filterStatus;
    $types         .= "s";
}
if ($filterSearch !== '') {
    $whereClauses[] = "ml.medicine_name LIKE ?";
    $params[]       = '%' . $filterSearch . '%';
    $types         .= "s";
}

$where = implode(" AND ", $whereClauses);

$stmt = $conn->prepare("
    SELECT ml.*, u.name AS patient_name
    FROM medication_logs ml
    JOIN users u ON u.id = ml.patient_id
    JOIN caregiver_patient cp ON cp.patient_id = ml.patient_id
    WHERE $where
    ORDER BY ml.taken_time DESC
");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$allLogs = $stmt->get_result();

// ===== GROUP BY PATIENT → WEEK =====
// Structure: grouped[$patient_name][$weekLabel][] = $row
$grouped    = [];
$totalTaken = 0;
$totalMissed = 0;

while ($row = $allLogs->fetch_assoc()) {
    $pName = $row['patient_name'];
    $ts    = strtotime($row['taken_time']);

    // Week label: Mon dd Mmm – Sun dd Mmm YYYY
    $weekMon  = date('Y-m-d', strtotime('monday this week', $ts));
    // If today IS monday strtotime gives next monday, handle edge case
    if (date('N', $ts) == 1) $weekMon = date('Y-m-d', $ts);
    else $weekMon = date('Y-m-d', strtotime('last monday', $ts));

    $weekSun  = date('Y-m-d', strtotime($weekMon . ' +6 days'));
    $weekKey  = date('d M', strtotime($weekMon)) . ' – ' . date('d M Y', strtotime($weekSun));

    $grouped[$pName][$weekKey][] = $row;

    if ($row['status'] == 'Taken') $totalTaken++;
    else                           $totalMissed++;
}

$totalLogs = $totalTaken + $totalMissed;
$overallPct = $totalLogs > 0 ? round(($totalTaken / $totalLogs) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carely — Medication Logs</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .page-title    { font-family:'Fraunces',serif; font-size:1.8rem; font-weight:300; color:var(--lilac-deeper); margin-bottom:4px; }
        .page-title em { font-style:italic; color:var(--lilac-dark); }
        .page-sub      { font-size:.85rem; color:var(--ink-muted); margin-bottom:24px; }

        /* ── SUMMARY STATS ── */
        .summary-row {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
            gap:12px;
            margin-bottom:24px;
        }
        .sum-card {
            background:#fff; border:1px solid var(--border);
            border-radius:var(--radius); padding:16px 18px;
            box-shadow:var(--shadow-sm); position:relative; overflow:hidden;
        }
        .sum-card::before {
            content:''; position:absolute; top:0;left:0;right:0; height:3px;
        }
        .sum-card.sc-total::before  { background:linear-gradient(90deg,var(--lilac),var(--lilac-deep)); }
        .sum-card.sc-taken::before  { background:linear-gradient(90deg,#5b9e6e,#3d7a52); }
        .sum-card.sc-missed::before { background:linear-gradient(90deg,#E57373,#c0504a); }
        .sum-card.sc-rate::before   { background:linear-gradient(90deg,var(--lilac-dark),var(--lilac-deeper)); }
        .sum-val {
            font-family:'Fraunces',serif; font-size:2rem;
            font-weight:600; line-height:1; margin-bottom:3px;
        }
        .sum-card.sc-taken  .sum-val { color:var(--green); }
        .sum-card.sc-missed .sum-val { color:var(--rose); }
        .sum-card.sc-total  .sum-val { color:var(--lilac-deep); }
        .sum-card.sc-rate   .sum-val { color:var(--lilac-deeper); }
        .sum-lbl { font-size:.75rem; color:var(--ink-muted); text-transform:uppercase; letter-spacing:.05em; }

        /* ── FILTER BAR ── */
        .filter-bar {
            background:#fff; border:1px solid var(--border);
            border-radius:var(--radius); padding:14px 18px;
            margin-bottom:22px; box-shadow:var(--shadow-sm);
            display:flex; flex-wrap:wrap; gap:10px; align-items:flex-end;
        }
        .filter-bar .fg { display:flex; flex-direction:column; gap:4px; flex:1; min-width:140px; }
        .filter-bar label { font-size:.76rem; font-weight:500; color:var(--ink-muted); }
        .filter-bar input,
        .filter-bar select { margin-top:0; padding:8px 11px; font-size:.85rem; }
        .filter-bar .btn-filter {
            background:linear-gradient(135deg,var(--lilac),var(--lilac-deep));
            color:#fff; border:none; border-radius:8px;
            padding:9px 18px; font-size:.85rem; font-weight:500;
            cursor:pointer; white-space:nowrap; margin-top:0;
            align-self:flex-end;
        }
        .filter-bar .btn-filter:hover { opacity:.88; }
        .btn-clear-filter {
            align-self:flex-end; padding:9px 14px; border-radius:8px;
            border:1px solid rgba(192,80,74,.3); background:#fff;
            color:var(--rose); font-size:.82rem; font-weight:500;
            text-decoration:none; white-space:nowrap;
            transition:background .15s;
        }
        .btn-clear-filter:hover { background:var(--rose-light); text-decoration:none; color:var(--rose); }

        /* ── PATIENT SECTION ── */
        .patient-section { margin-bottom:32px; }
        .patient-section-head {
            display:flex; align-items:center; gap:12px; margin-bottom:14px;
        }
        .ps-avatar {
            width:40px;height:40px;border-radius:50%;
            background:linear-gradient(135deg,var(--lilac),var(--lilac-deep));
            color:#fff;display:flex;align-items:center;justify-content:center;
            font-family:'Fraunces',serif;font-size:1rem;font-weight:600;
        }
        .ps-name  { font-family:'Fraunces',serif; font-size:1.05rem; font-weight:600; color:var(--lilac-deeper); }
        .ps-count { font-size:.78rem; color:var(--ink-muted); margin-top:1px; }

        /* ── WEEK GROUP ── */
        .week-group { margin-bottom:18px; }
        .week-header {
            display:flex; align-items:center; justify-content:space-between;
            background:var(--lilac-light); border:1px solid var(--lilac-mid);
            border-radius:10px 10px 0 0; padding:9px 16px;
            cursor:pointer; user-select:none;
        }
        .week-header:hover { background:var(--lilac-mid); }
        .week-label {
            font-family:'Fraunces',serif; font-size:.9rem;
            font-weight:600; color:var(--lilac-deeper);
            display:flex; align-items:center; gap:8px;
        }
        .week-meta { display:flex; align-items:center; gap:10px; }
        .wk-taken  { font-size:.78rem; font-weight:600; color:var(--green); }
        .wk-missed { font-size:.78rem; font-weight:600; color:var(--rose); }
        .wk-pct    {
            font-size:.75rem; font-weight:600; padding:2px 9px;
            border-radius:12px;
        }
        .wk-pct.good    { background:#e8f5e9; color:#2e6b3e; }
        .wk-pct.warning { background:#fff8e1; color:#7a5800; }
        .wk-pct.bad     { background:var(--rose-light); color:#8b2020; }
        .week-chevron { font-size:.75rem; color:var(--ink-muted); transition:transform .2s; }
        .week-chevron.open { transform:rotate(180deg); }

        /* ── LOG TABLE ── */
        .week-body {
            border:1px solid var(--lilac-mid); border-top:none;
            border-radius:0 0 10px 10px; overflow:hidden;
        }
        .log-table { width:100%; border-collapse:collapse; font-size:.87rem; }
        .log-table th {
            background:var(--lilac-light); color:var(--lilac-deep);
            font-size:.75rem; text-transform:uppercase;
            letter-spacing:.05em; font-weight:600;
            padding:9px 14px; text-align:left;
        }
        .log-table td { padding:10px 14px; border-bottom:1px solid var(--border); }
        .log-table tr:last-child td { border-bottom:none; }
        .log-table tr:hover td { background:rgba(200,162,200,.05); }

        .status-pill {
            display:inline-flex; align-items:center; gap:4px;
            padding:3px 10px; border-radius:12px;
            font-size:.78rem; font-weight:600;
        }
        .status-pill.taken  { background:#e8f5e9; color:#2e6b3e; }
        .status-pill.missed { background:var(--rose-light); color:#8b2020; }

        .time-cell { color:var(--ink-muted); font-size:.83rem; }
        .med-cell  { font-weight:500; color:var(--ink); }
        .med-icon  { margin-right:4px; }

        /* ── EMPTY ── */
        .empty-state {
            text-align:center; padding:48px 20px;
            background:#fff; border:1px solid var(--border);
            border-radius:var(--radius); color:var(--ink-muted);
            box-shadow:var(--shadow-sm);
        }
        .empty-state .ei { font-size:2.5rem; margin-bottom:10px; }
        .empty-state p { font-size:.9rem; }

        @media(max-width:600px) {
            .summary-row { grid-template-columns:repeat(2,1fr); }
            .filter-bar  { flex-direction:column; }
        }
    </style>
</head>
<body>

<?php include "navbar.php"; ?>

<div class="page">

    <h1 class="page-title fade-in">Medication <em>Logs</em></h1>
    <p class="page-sub fade-in delay-1">Full history of doses taken and missed, grouped by week.</p>

    <!-- SUMMARY STATS -->
    <div class="summary-row fade-in delay-2">
        <div class="sum-card sc-total">
            <div class="sum-val"><?php echo $totalLogs; ?></div>
            <div class="sum-lbl">Total Logs</div>
        </div>
        <div class="sum-card sc-taken">
            <div class="sum-val"><?php echo $totalTaken; ?></div>
            <div class="sum-lbl">Taken</div>
        </div>
        <div class="sum-card sc-missed">
            <div class="sum-val"><?php echo $totalMissed; ?></div>
            <div class="sum-lbl">Missed</div>
        </div>
        <div class="sum-card sc-rate">
            <div class="sum-val"><?php echo $overallPct; ?>%</div>
            <div class="sum-lbl">Adherence Rate</div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" action="">
        <div class="filter-bar fade-in delay-2">

            <div class="fg">
                <label>Patient</label>
                <select name="patient_id">
                    <option value="0">All Patients</option>
                    <?php foreach ($patientList as $pl): ?>
                        <option value="<?php echo $pl['id']; ?>"
                            <?php echo $filterPatient == $pl['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($pl['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="fg">
                <label>Status</label>
                <select name="status">
                    <option value="">All</option>
                    <option value="Taken"  <?php echo $filterStatus=='Taken'  ?'selected':''; ?>>Taken</option>
                    <option value="Missed" <?php echo $filterStatus=='Missed' ?'selected':''; ?>>Missed</option>
                </select>
            </div>

            <div class="fg">
                <label>Medicine Name</label>
                <input type="text" name="search"
                       placeholder="e.g. Panadol"
                       value="<?php echo htmlspecialchars($filterSearch); ?>">
            </div>

            <button type="submit" class="btn-filter">🔍 Filter</button>

            <?php if ($filterPatient || $filterStatus || $filterSearch): ?>
                <a href="medication_logs.php" class="btn-clear-filter">✖ Clear</a>
            <?php endif; ?>

        </div>
    </form>

    <!-- LOGS -->
    <?php if (empty($grouped)): ?>
        <div class="empty-state fade-in">
            <div class="ei">📭</div>
            <p>No medication logs found<?php echo ($filterSearch||$filterStatus||$filterPatient) ? ' for the selected filters.' : ' yet.'; ?></p>
        </div>

    <?php else: ?>

    <?php foreach ($grouped as $patientName => $weeks):
        $ptTotal  = 0; $ptTaken = 0;
        foreach ($weeks as $wRows) foreach ($wRows as $r) {
            $ptTotal++;
            if ($r['status']=='Taken') $ptTaken++;
        }
        $ptPct = $ptTotal > 0 ? round(($ptTaken/$ptTotal)*100,1) : 0;
        $initial = strtoupper(substr($patientName,0,1));
    ?>

    <div class="patient-section fade-in delay-3">

        <!-- Patient header -->
        <div class="patient-section-head">
            <div class="ps-avatar"><?php echo $initial; ?></div>
            <div>
                <div class="ps-name"><?php echo htmlspecialchars($patientName); ?></div>
                <div class="ps-count"><?php echo $ptTaken; ?> taken · <?php echo $ptTotal-$ptTaken; ?> missed · <?php echo $ptPct; ?>% adherence</div>
            </div>
        </div>

        <!-- Week groups -->
        <?php foreach ($weeks as $weekLabel => $rows):
            $wTaken  = 0; $wMissed = 0;
            foreach ($rows as $r) $r['status']=='Taken' ? $wTaken++ : $wMissed++;
            $wTotal = $wTaken + $wMissed;
            $wPct   = $wTotal > 0 ? round(($wTaken/$wTotal)*100,1) : 0;
            $wClass = $wPct>=80 ? 'good' : ($wPct>=50 ? 'warning' : 'bad');
            $uid    = 'week-' . md5($patientName.$weekLabel);
        ?>

        <div class="week-group">
            <div class="week-header" onclick="toggleWeek('<?php echo $uid; ?>', this)">
                <span class="week-label">📅 <?php echo htmlspecialchars($weekLabel); ?></span>
                <div class="week-meta">
                    <span class="wk-taken">✔ <?php echo $wTaken; ?></span>
                    <span class="wk-missed">✖ <?php echo $wMissed; ?></span>
                    <span class="wk-pct <?php echo $wClass; ?>"><?php echo $wPct; ?>%</span>
                    <span class="week-chevron open" id="chev-<?php echo $uid; ?>">▼</span>
                </div>
            </div>

            <div class="week-body" id="<?php echo $uid; ?>">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Status</th>
                            <th>Date &amp; Time</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="med-cell">
                                <span class="med-icon">💊</span><?php echo htmlspecialchars($row['medicine_name']); ?>
                            </td>
                            <td>
                                <span class="status-pill <?php echo strtolower($row['status']); ?>">
                                    <?php echo $row['status']=='Taken' ? '✔' : '✖'; ?>
                                    <?php echo $row['status']; ?>
                                </span>
                            </td>
                            <td class="time-cell">
                                <?php echo date('D, d M Y · H:i', strtotime($row['taken_time'])); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endforeach; ?>
    </div>

    <?php endforeach; ?>
    <?php endif; ?>

</div>

<script>
function toggleWeek(id, header) {
    var body  = document.getElementById(id);
    var chev  = document.getElementById('chev-' + id);
    var open  = body.style.display !== 'none';
    body.style.display = open ? 'none' : '';
    chev.classList.toggle('open', !open);
}
</script>

</body>
</html>