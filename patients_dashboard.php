<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "patient") {
    header("Location: index.php");
    exit();
}

$patient_id = intval($_SESSION['user_id']);

// ===== GET SCHEDULE =====
$stmt = $conn->prepare("
    SELECT * FROM medication_schedule
    WHERE patient_id = ?
    ORDER BY medication_hour, medication_minute
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$schedule = $stmt->get_result();

// ===== NEXT MEDICATION =====
$nextMed     = null;
$currentTime = date("H:i");

$stmt = $conn->prepare("
    SELECT * FROM medication_schedule
    WHERE patient_id = ?
    ORDER BY medication_hour, medication_minute
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $time = str_pad($row['medication_hour'],   2,'0',STR_PAD_LEFT)
          . ':'
          . str_pad($row['medication_minute'], 2,'0',STR_PAD_LEFT);
    if ($time >= $currentTime) {
        $nextMed         = $row;
        $nextMed['time'] = $time;
        break;
    }
}

// ===== GET LOGS (grouped by week in PHP) =====
$stmt = $conn->prepare("
    SELECT * FROM medication_logs
    WHERE patient_id = ?
    ORDER BY taken_time DESC
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$logsResult = $stmt->get_result();

// Group logs by week label
$groupedLogs = [];
while ($row = $logsResult->fetch_assoc()) {
    $ts      = strtotime($row['taken_time']);
    $dayNum  = date('N', $ts); // 1=Mon, 7=Sun
    $monTs   = $ts - (($dayNum - 1) * 86400);
    $sunTs   = $monTs + (6 * 86400);
    $weekKey = date('d M', $monTs) . ' – ' . date('d M Y', $sunTs);
    $groupedLogs[$weekKey][] = $row;
}

// ===== TODAY STATS =====
$stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN status='Taken'  THEN 1 ELSE 0 END) as taken,
        SUM(CASE WHEN status='Missed' THEN 1 ELSE 0 END) as missed,
        COUNT(*) as total
    FROM medication_logs
    WHERE patient_id = ? AND DATE(taken_time) = CURDATE()
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$today = $stmt->get_result()->fetch_assoc();

$todayTaken  = intval($today['taken']);
$todayMissed = intval($today['missed']);
$todayTotal  = intval($today['total']);
$todayPct    = $todayTotal > 0 ? round(($todayTaken / $todayTotal) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carely — My Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <meta http-equiv="refresh" content="30">
    <style>

        /* ── PAGE HEADER ── */
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
            margin-bottom: 6px;
        }
        .welcome-text {
            font-size: .95rem;
            color: var(--ink-muted);
            margin-bottom: 28px;
        }

        /* ── LIVE BADGE ── */
        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: .72rem;
            color: var(--lilac-deep);
            background: var(--lilac-light);
            border: 1px solid #e0d0e0;
            border-radius: 20px;
            padding: 2px 9px;
            margin-left: 8px;
            vertical-align: middle;
        }
        .live-dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--lilac-dark);
            animation: pulse 1.8s infinite;
        }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.3} }

        /* ── TWO-COLUMN LAYOUT ── */
        .dash-grid {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 22px;
            align-items: start;
        }
        .dash-col-left  { display: flex; flex-direction: column; gap: 18px; }
        .dash-col-right { display: flex; flex-direction: column; gap: 18px; }

        /* ── SUMMARY CARDS ── */
        .summary-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .sum-card {
            background: #fff;
            border: 1px solid var(--border);
            border-top: 3px solid transparent;
            border-radius: var(--radius);
            padding: 16px 18px;
            box-shadow: var(--shadow-sm);
        }
        .sc-taken  { border-top-color: #5b9e6e; }
        .sc-missed { border-top-color: #E57373; }
        .sc-total  { border-top-color: var(--lilac); }
        .sc-rate   { border-top-color: var(--lilac-dark); }
        .sum-val {
            font-family: 'Fraunces', serif;
            font-size: 2rem;
            font-weight: 600;
            line-height: 1;
            margin-bottom: 4px;
        }
        .sc-taken  .sum-val { color: var(--green); }
        .sc-missed .sum-val { color: var(--rose); }
        .sc-total  .sum-val { color: var(--lilac-deep); }
        .sc-rate   .sum-val { color: var(--lilac-deeper); }
        .sum-lbl {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--ink-muted);
        }

        /* ── NEXT MED CARD ── */
        .next-med-card {
            background: #fff;
            border: 1px solid var(--border);
            border-top: 3px solid var(--lilac);
            border-radius: var(--radius);
            padding: 20px 22px;
            box-shadow: var(--shadow-sm);
        }
        .next-label {
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--ink-muted);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .next-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 6px;
        }
        .next-time {
            font-family: 'Fraunces', serif;
            font-size: 2rem;
            font-weight: 600;
            color: var(--lilac-deeper);
            line-height: 1;
        }
        .next-sub {
            font-size: .78rem;
            color: var(--ink-muted);
            margin-top: 6px;
        }
        .next-done {
            font-size: 1rem;
            font-weight: 600;
            color: var(--green);
            margin-top: 4px;
        }

        /* ── PROGRESS BAR ── */
        .progress-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 20px;
            box-shadow: var(--shadow-sm);
        }
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .progress-header span:first-child {
            font-size: .82rem;
            font-weight: 600;
            color: var(--ink);
        }
        .progress-header span:last-child {
            font-family: 'Fraunces', serif;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--lilac-deep);
        }
        .progress-track {
            background: var(--lilac-light);
            border-radius: 99px;
            height: 10px;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            border-radius: 99px;
            transition: width .5s ease;
        }
        .progress-fill.good    { background: linear-gradient(90deg,#5b9e6e,#3d7a52); }
        .progress-fill.warning { background: linear-gradient(90deg,#d4a017,#b87333); }
        .progress-fill.bad     { background: linear-gradient(90deg,#E57373,#c0504a); }
        .progress-caption {
            font-size: .78rem;
            color: var(--ink-muted);
            margin-top: 7px;
        }

        /* ── SECTION HEADER ── */
        .section-head {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        .section-title {
            font-family: 'Fraunces', serif;
            font-size: 1rem;
            font-weight: 600;
            color: var(--lilac-deeper);
        }
        .section-head::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--lilac-mid, #e0d0e0);
        }

        /* ── TABLE CARD ── */
        .table-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .9rem;
        }
        .data-table th {
            background: var(--lilac-light);
            color: var(--lilac-deep);
            font-size: .73rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            font-weight: 600;
            padding: 10px 16px;
            text-align: left;
        }
        .data-table td {
            padding: 11px 16px;
            border-bottom: 1px solid var(--border);
        }
        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: rgba(200,162,200,.05); }

        .time-badge {
            background: var(--lilac-light);
            color: var(--lilac-deep);
            border-radius: 8px;
            padding: 4px 12px;
            font-weight: 600;
            font-size: .9rem;
            display: inline-block;
            font-family: 'Fraunces', serif;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 600;
        }
        .status-pill.taken  { background: #e8f5e9; color: #2e6b3e; }
        .status-pill.missed { background: var(--rose-light); color: #8b2020; }
        .time-cell  { color: var(--ink-muted); font-size: .82rem; }
        .med-name   { font-weight: 500; }
        .empty-row  { text-align: center; padding: 28px; color: var(--ink-muted); font-style: italic; font-size: .88rem; }

        /* ── QUICK LINK ── */
        .quick-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--lilac-light);
            border: 1px solid var(--lilac-mid, #e0d0e0);
            border-radius: 10px;
            padding: 12px 16px;
            text-decoration: none;
            color: var(--lilac-deep);
            font-weight: 600;
            font-size: .88rem;
            transition: background .15s, transform .15s;
        }
        .quick-link:hover {
            background: var(--lilac-mid, #e0d0e0);
            transform: translateY(-1px);
            text-decoration: none;
            color: var(--lilac-deeper);
        }
        .quick-link span { font-size: 1.1rem; }

        /* ── WEEK GROUP (collapsible history) ── */
        .week-group {
            margin-bottom: 10px;
            border: 1px solid var(--lilac-mid, #e0d0e0);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }
        .week-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--lilac-light);
            padding: 11px 16px;
            cursor: pointer;
            user-select: none;
            transition: background .15s;
        }
        .week-header:hover { background: var(--lilac-mid, #e0d0e0); }
        .week-left  { display: flex; flex-direction: column; gap: 6px; }
        .week-label {
            font-family: 'Fraunces', serif;
            font-size: .9rem;
            font-weight: 600;
            color: var(--lilac-deeper);
        }
        .week-pills { display: flex; gap: 6px; flex-wrap: wrap; }
        .wpill {
            font-size: .72rem;
            font-weight: 600;
            padding: 2px 9px;
            border-radius: 12px;
        }
        .taken-pill  { background: #e8f5e9; color: #2e6b3e; }
        .missed-pill { background: var(--rose-light); color: #8b2020; }
        .rate-pill.good    { background: #e8f5e9; color: #2e6b3e; }
        .rate-pill.warning { background: #fff8e1; color: #7a5800; }
        .rate-pill.bad     { background: var(--rose-light); color: #8b2020; }
        .week-chevron {
            font-size: .75rem;
            color: var(--ink-muted);
            transition: transform .22s;
            flex-shrink: 0;
        }
        .week-chevron.open { transform: rotate(180deg); }
        .week-body { background: #fff; }
        .week-body .data-table td:first-child { padding-left: 18px; }

        /* ── RESPONSIVE ── */
        @media(max-width: 860px) {
            .dash-grid { grid-template-columns: 1fr; }
        }
        @media(max-width: 480px) {
            .summary-row { grid-template-columns: 1fr 1fr; }
            .data-table th,
            .data-table td { padding: 10px; font-size: .82rem; }
            .sum-val { font-size: 1.7rem; }
        }

    </style>
</head>
<body>

<?php include "patient_navbar.php"; ?>

<div class="page">

    <!-- PAGE HEADER -->
    <h1 class="page-title fade-in">
        My <em>Dashboard</em>
        <span class="live-badge"><span class="live-dot"></span> Live</span>
    </h1>
    <p class="welcome-text fade-in">
        Welcome back, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong> 👋
        &nbsp;·&nbsp; <?php echo date('l, d F Y'); ?>
    </p>
    <p class="page-sub fade-in delay-1">Auto-refreshes every 30 seconds</p>

    <!-- TWO-COLUMN GRID -->
    <div class="dash-grid">

        <!-- ══ LEFT COLUMN ══ -->
        <div class="dash-col-left">

            <!-- NEXT MEDICATION -->
            <div class="next-med-card fade-in delay-1">
                <div class="next-label">🕒 Next Medication</div>
                <?php if ($nextMed): ?>
                    <div class="next-name">💊 <?php echo htmlspecialchars($nextMed['medicine_name']); ?></div>
                    <div class="next-time"><?php echo $nextMed['time']; ?></div>
                    <div class="next-sub">Please have your medication ready.</div>
                <?php else: ?>
                    <div class="next-done">✅ All done for today!</div>
                    <div class="next-sub">No more medications scheduled for today.</div>
                <?php endif; ?>
            </div>

            <!-- SUMMARY STATS -->
            <div class="summary-row fade-in delay-2">
                <div class="sum-card sc-taken">
                    <div class="sum-val"><?php echo $todayTaken; ?></div>
                    <div class="sum-lbl">Taken Today</div>
                </div>
                <div class="sum-card sc-missed">
                    <div class="sum-val"><?php echo $todayMissed; ?></div>
                    <div class="sum-lbl">Missed Today</div>
                </div>
                <div class="sum-card sc-total">
                    <div class="sum-val"><?php echo $todayTotal; ?></div>
                    <div class="sum-lbl">Total Today</div>
                </div>
                <div class="sum-card sc-rate">
                    <div class="sum-val"><?php echo $todayPct; ?>%</div>
                    <div class="sum-lbl">Adherence Rate</div>
                </div>
            </div>

            <!-- DAILY PROGRESS -->
            <?php
            $barClass = $todayPct >= 80 ? 'good' : ($todayPct >= 50 ? 'warning' : 'bad');
            $barMsg   = $todayTotal == 0  ? 'No records yet today.'
                      : ($todayPct == 100 ? 'Perfect — all medications taken!'
                      : ($todayPct >= 80  ? 'Almost there, keep going!'
                      : ($todayPct >= 50  ? 'Halfway there — do not give up.'
                      :                     'Please remember to take your medication.')));
            ?>
            <div class="progress-card fade-in delay-2">
                <div class="progress-header">
                    <span>📈 Today's Progress</span>
                    <span><?php echo $todayPct; ?>%</span>
                </div>
                <div class="progress-track">
                    <div class="progress-fill <?php echo $barClass; ?>" style="width:<?php echo $todayPct; ?>%"></div>
                </div>
                <p class="progress-caption"><?php echo $barMsg; ?></p>
            </div>

            <!-- QUICK LINK -->
            <a href="patient_add_schedule.php" class="quick-link fade-in delay-3">
                <span>💊 Manage My Medications</span>
                <span>→</span>
            </a>

        </div><!-- /.dash-col-left -->

        <!-- ══ RIGHT COLUMN ══ -->
        <div class="dash-col-right">

            <!-- MEDICATION SCHEDULE -->
            <div class="fade-in delay-2">
                <div class="section-head">
                    <div class="section-title">📅 Medication Schedule</div>
                </div>
                <div class="table-card">
                    <table class="data-table">
                        <thead>
                            <tr><th>Medicine</th><th>Time</th></tr>
                        </thead>
                        <tbody>
                        <?php if ($schedule->num_rows === 0): ?>
                            <tr>
                                <td colspan="2" class="empty-row">
                                    No medications scheduled yet.
                                    <a href="patient_add_schedule.php" style="color:var(--lilac-dark);font-weight:600;">Add one →</a>
                                </td>
                            </tr>
                        <?php else:
                            $schedule->data_seek(0);
                            while ($row = $schedule->fetch_assoc()):
                                $h = str_pad($row['medication_hour'],   2,'0',STR_PAD_LEFT);
                                $m = str_pad($row['medication_minute'], 2,'0',STR_PAD_LEFT);
                        ?>
                            <tr>
                                <td class="med-name">💊 <?php echo htmlspecialchars($row['medicine_name']); ?></td>
                                <td><span class="time-badge"><?php echo "$h:$m"; ?></span></td>
                            </tr>
                        <?php endwhile; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MEDICATION HISTORY (collapsible by week) -->
            <div class="fade-in delay-3">
                <div class="section-head">
                    <div class="section-title">📋 Medication History</div>
                </div>

                <?php if (empty($groupedLogs)): ?>
                    <div class="table-card">
                        <p class="empty-row">No medication history yet.</p>
                    </div>

                <?php else:
                    $weekIndex = 0;
                    foreach ($groupedLogs as $weekLabel => $rows):
                        $wTaken  = 0; $wMissed = 0;
                        foreach ($rows as $r) $r['status'] == 'Taken' ? $wTaken++ : $wMissed++;
                        $wTotal  = $wTaken + $wMissed;
                        $wPct    = $wTotal > 0 ? round(($wTaken / $wTotal) * 100) : 0;
                        $wClass  = $wPct >= 80 ? 'good' : ($wPct >= 50 ? 'warning' : 'bad');
                        $uid     = 'week-' . $weekIndex;
                        $isFirst = $weekIndex === 0;
                ?>

                <div class="week-group">

                    <!-- WEEK HEADER (clickable) -->
                    <div class="week-header" onclick="toggleWeek('<?php echo $uid; ?>', this)">
                        <div class="week-left">
                            <span class="week-label">📅 <?php echo htmlspecialchars($weekLabel); ?></span>
                            <div class="week-pills">
                                <span class="wpill taken-pill">✔ <?php echo $wTaken; ?></span>
                                <span class="wpill missed-pill">✖ <?php echo $wMissed; ?></span>
                                <span class="wpill rate-pill <?php echo $wClass; ?>"><?php echo $wPct; ?>%</span>
                            </div>
                        </div>
                        <span class="week-chevron <?php echo $isFirst ? 'open' : ''; ?>" id="chev-<?php echo $uid; ?>">▼</span>
                    </div>

                    <!-- WEEK BODY -->
                    <div class="week-body" id="<?php echo $uid; ?>" <?php echo $isFirst ? '' : 'style="display:none"'; ?>>
                        <table class="data-table">
                            <thead>
                                <tr><th>Medicine</th><th>Status</th><th>Date &amp; Time</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $row):
                                $cls = strtolower($row['status']);
                                $sym = $row['status'] == 'Taken' ? '✔' : '✖';
                            ?>
                                <tr>
                                    <td class="med-name">💊 <?php echo htmlspecialchars($row['medicine_name']); ?></td>
                                    <td>
                                        <span class="status-pill <?php echo $cls; ?>">
                                            <?php echo "$sym {$row['status']}"; ?>
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

                <?php $weekIndex++; endforeach; endif; ?>
            </div>

        </div><!-- /.dash-col-right -->

    </div><!-- /.dash-grid -->

</div><!-- /.page -->
<script>
function toggleWeek(id, header) {
    var body = document.getElementById(id);
    var chev = document.getElementById('chev-' + id);
    var isOpen = body.style.display !== 'none';
    body.style.display = isOpen ? 'none' : '';
    chev.classList.toggle('open', !isOpen);
}
</script>
</body>
</html>