<?php
session_start();
include "db_connect.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != "caregiver") {
    header("Location: index.php");
    exit();
}

$caregiver_id = intval($_SESSION['user_id']);

// ===== FILTER =====
$type   = isset($_GET['type'])   ? $_GET['type']           : 'weekly';
$offset = isset($_GET['offset']) ? intval($_GET['offset'])  : 0;
$search = isset($_GET['search']) ? trim($_GET['search'])    : '';

if ($type !== 'monthly') $type = 'weekly';

// ===== DATE RANGE =====
if ($type == 'monthly') {
    $startDate = date('Y-m-01', strtotime("-$offset months"));
    $endDate   = date('Y-m-t',  strtotime("-$offset months"));
    $label     = date('F Y',    strtotime("-$offset months"));
} else {
    $monday    = date('Y-m-d', strtotime("monday this week -$offset weeks"));
    $sunday    = date('Y-m-d', strtotime("sunday this week -$offset weeks"));
    $startDate = $monday;
    $endDate   = $sunday;
    $label     = date('d M Y', strtotime($monday)) . ' – ' . date('d M Y', strtotime($sunday));
}

// ===== GET PATIENTS =====
$stmt = $conn->prepare("
    SELECT u.* FROM users u
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
    <title>Carely — Reports</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ── PAGE HEADER ── */
        .page-title     { font-family:'Fraunces',serif; font-size:1.8rem; font-weight:300; color:var(--lilac-deeper); margin-bottom:4px; }
        .page-title em  { font-style:italic; color:var(--lilac-dark); }
        .page-sub       { font-size:.85rem; color:var(--ink-muted); margin-bottom:28px; }

        /* ── CONTROLS BAR ── */
        .controls-bar {
            background:#fff;
            border:1px solid var(--border);
            border-radius:var(--radius);
            padding:16px 20px;
            margin-bottom:20px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            flex-wrap:wrap;
            gap:14px;
            box-shadow:var(--shadow-sm);
        }
        .type-row { display:flex; gap:8px; }
        .type-btn {
            padding:7px 18px;
            border-radius:20px;
            border:2px solid var(--lilac-mid);
            cursor:pointer;
            background:#fff;
            color:var(--lilac-deep);
            font-weight:600;
            font-size:.83rem;
            text-decoration:none;
            transition:background .18s, color .18s, border-color .18s;
        }
        .type-btn:hover { background:var(--lilac-light); text-decoration:none; color:var(--lilac-deeper); }
        .type-btn.active { background:linear-gradient(135deg,var(--lilac),var(--lilac-deep)); color:#fff; border-color:transparent; }

        .nav-row        { display:flex; align-items:center; gap:10px; }
        .period-label   {
            font-family:'Fraunces',serif;
            font-size:.95rem;
            font-weight:600;
            color:var(--lilac-deeper);
            min-width:200px;
            text-align:center;
            background:var(--lilac-light);
            border:1px solid var(--lilac-mid);
            border-radius:8px;
            padding:6px 14px;
        }
        .nav-btn {
            display:inline-flex; align-items:center; gap:4px;
            padding:6px 13px; border-radius:8px;
            border:1px solid var(--border);
            background:#fff; color:var(--ink-muted);
            font-size:.82rem; font-weight:500;
            text-decoration:none;
            transition:background .15s, color .15s;
        }
        .nav-btn:hover  { background:var(--lilac-light); color:var(--lilac-deep); text-decoration:none; }
        .nav-btn.dimmed { opacity:.4; pointer-events:none; }

        /* ── SEARCH ── */
        .search-bar {
            background:#fff;
            border:1px solid var(--border);
            border-radius:var(--radius);
            padding:14px 18px;
            margin-bottom:24px;
            display:flex;
            gap:10px;
            align-items:center;
            box-shadow:var(--shadow-sm);
        }
        .search-bar input {
            flex:1; margin-top:0;
            padding:9px 13px;
            border:1px solid var(--border);
            border-radius:8px;
            font-size:.88rem;
        }
        .search-bar input:focus { border-color:var(--lilac-dark); box-shadow:0 0 0 3px rgba(200,162,200,.2); }
        .btn-search {
            background:linear-gradient(135deg,var(--lilac),var(--lilac-deep));
            color:#fff; border:none; border-radius:8px;
            padding:9px 18px; font-size:.85rem; font-weight:500;
            cursor:pointer; white-space:nowrap;
            transition:opacity .2s; margin-top:0;
        }
        .btn-search:hover { opacity:.88; }
        .btn-clear {
            background:#fff; color:var(--rose);
            border:1px solid rgba(192,80,74,.3);
            border-radius:8px; padding:9px 14px;
            font-size:.82rem; font-weight:500;
            text-decoration:none; white-space:nowrap;
            transition:background .15s;
        }
        .btn-clear:hover { background:var(--rose-light); text-decoration:none; color:var(--rose); }
        .search-active-tag {
            font-size:.82rem; color:var(--lilac-deep);
            background:var(--lilac-light); border:1px solid var(--lilac-mid);
            border-radius:20px; padding:3px 12px;
        }

        /* ── PATIENT REPORT CARD ── */
        .report-card {
            background:#fff;
            border:1px solid var(--border);
            border-radius:var(--radius);
            overflow:hidden;
            margin-bottom:24px;
            box-shadow:var(--shadow-sm);
            transition:box-shadow .2s;
        }
        .report-card:hover { box-shadow:var(--shadow-md); }

        .report-card-head {
            padding:18px 24px;
            border-bottom:1px solid var(--border);
            display:flex;
            align-items:center;
            justify-content:space-between;
            flex-wrap:wrap;
            gap:12px;
        }
        .rc-patient {
            display:flex; align-items:center; gap:12px;
        }
        .rc-avatar {
            width:40px;height:40px;border-radius:50%;
            background:linear-gradient(135deg,var(--lilac),var(--lilac-deep));
            color:#fff;display:flex;align-items:center;justify-content:center;
            font-family:'Fraunces',serif;font-size:1rem;font-weight:600;flex-shrink:0;
        }
        .rc-name   { font-family:'Fraunces',serif; font-size:1rem; font-weight:600; color:var(--ink); }
        .rc-period { font-size:.78rem; color:var(--ink-muted); margin-top:2px; }

        .adherence-badge {
            display:inline-flex; align-items:center; gap:6px;
            padding:6px 14px; border-radius:20px;
            font-family:'Fraunces',serif;
            font-size:1rem; font-weight:600;
        }
        .adherence-badge.good    { background:#e8f5e9; color:#2e6b3e; }
        .adherence-badge.warning { background:#fff8e1; color:#7a5800; }
        .adherence-badge.bad     { background:var(--rose-light); color:#8b2020; }

        .report-card-body { padding:20px 24px; }

        /* Stats row inside card */
        .rc-stats {
            display:flex; gap:20px; flex-wrap:wrap;
            margin-bottom:20px;
            padding-bottom:16px;
            border-bottom:1px solid var(--border);
        }
        .rc-stat { text-align:center; }
        .rc-stat-val {
            font-family:'Fraunces',serif; font-size:1.5rem;
            font-weight:600; line-height:1; margin-bottom:2px;
        }
        .rc-stat-lbl { font-size:.72rem; color:var(--ink-muted); text-transform:uppercase; letter-spacing:.05em; }
        .rc-stat.s-taken   .rc-stat-val { color:var(--green); }
        .rc-stat.s-missed  .rc-stat-val { color:var(--rose); }
        .rc-stat.s-total   .rc-stat-val { color:var(--lilac-deep); }
        .rc-stat.s-pct     .rc-stat-val { color:var(--lilac-deeper); }

        /* Two-col layout: chart + table */
        .rc-cols { display:grid; grid-template-columns:220px 1fr; gap:24px; align-items:start; margin-bottom:20px; }
        @media(max-width:640px){ .rc-cols { grid-template-columns:1fr; } }

        /* Medicine breakdown table */
        .med-table { width:100%; border-collapse:collapse; font-size:.85rem; }
        .med-table th, .med-table td { padding:9px 12px; text-align:left; }
        .med-table th {
            background:var(--lilac-light); color:var(--lilac-deep);
            font-size:.75rem; text-transform:uppercase; letter-spacing:.05em; font-weight:600;
        }
        .med-table tr { border-bottom:1px solid var(--border); }
        .med-table tr:last-child { border-bottom:none; }
        .med-table tr:hover td { background:rgba(200,162,200,.05); }
        .med-table .c-taken  { color:var(--green); font-weight:600; }
        .med-table .c-missed { color:var(--rose);  font-weight:600; }
        .pill-badge {
            display:inline-block; padding:2px 9px; border-radius:12px;
            font-size:.75rem; font-weight:600;
        }
        .pill-badge.good    { background:#e8f5e9; color:#2e6b3e; }
        .pill-badge.warning { background:#fff8e1; color:#7a5800; }
        .pill-badge.bad     { background:var(--rose-light); color:#8b2020; }

        /* Calendar */
        .cal-section h4 {
            font-size:.78rem; text-transform:uppercase; letter-spacing:.06em;
            color:var(--ink-muted); font-weight:600; margin-bottom:10px;
        }
        .calendar { display:flex; flex-wrap:wrap; gap:5px; }
        .day {
            width:38px;height:44px; border-radius:7px;
            display:flex;flex-direction:column;
            align-items:center;justify-content:center;
            font-size:.72rem; font-weight:600;
        }
        .day span.lbl { font-size:.55rem; opacity:.75; font-weight:400; margin-top:1px; }
        .taken  { background:var(--green); color:#fff; }
        .missed { background:var(--rose);  color:#fff; }
        .none   { background:var(--lilac-light); color:var(--ink-muted); }

        .no-data {
            text-align:center; padding:32px 20px;
            color:var(--ink-muted); font-size:.88rem; font-style:italic;
        }
    </style>
</head>
<body>

<?php include "navbar.php"; ?>

<div class="page">

    <h1 class="page-title fade-in">Medication <em>Reports</em></h1>
    <p class="page-sub fade-in delay-1">Track adherence across your patients by week or month.</p>

    <!-- CONTROLS BAR -->
    <div class="controls-bar fade-in delay-2">

        <div class="type-row">
            <a href="?type=weekly&offset=0&search=<?php echo urlencode($search); ?>"
               class="type-btn <?php echo $type=='weekly'?'active':''; ?>">📅 Weekly</a>
            <a href="?type=monthly&offset=0&search=<?php echo urlencode($search); ?>"
               class="type-btn <?php echo $type=='monthly'?'active':''; ?>">🗓 Monthly</a>
        </div>

        <div class="nav-row">
            <a href="?type=<?php echo $type; ?>&offset=<?php echo $offset+1; ?>&search=<?php echo urlencode($search); ?>"
               class="nav-btn">⬅ Previous</a>

            <span class="period-label">📅 <?php echo htmlspecialchars($label); ?></span>

            <a href="?type=<?php echo $type; ?>&offset=<?php echo max(0,$offset-1); ?>&search=<?php echo urlencode($search); ?>"
               class="nav-btn <?php echo $offset==0?'dimmed':''; ?>">Next ➡</a>
        </div>

    </div>

    <!-- SEARCH BAR -->
    <form method="GET" action="">
        <input type="hidden" name="type"   value="<?php echo $type; ?>">
        <input type="hidden" name="offset" value="<?php echo $offset; ?>">
        <div class="search-bar fade-in delay-2">
            <input type="text" name="search"
                   placeholder="Search by medicine name..."
                   value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn-search">🔍 Search</button>
            <?php if ($search): ?>
                <a href="?type=<?php echo $type; ?>&offset=<?php echo $offset; ?>"
                   class="btn-clear">✖ Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($search): ?>
        <p class="fade-in" style="margin-bottom:16px;">
            <span class="search-active-tag">🔍 Showing results for: <b><?php echo htmlspecialchars($search); ?></b></span>
        </p>
    <?php endif; ?>

    <!-- PATIENT REPORTS -->
    <?php
    $chartIndex = 0;

    while ($p = $patients->fetch_assoc()):
        $pid     = intval($p['id']);
        $initial = strtoupper(substr($p['name'], 0, 1));

        // Build log query
        if ($search !== '') {
            $stmt = $conn->prepare("
                SELECT medicine_name, status, taken_time
                FROM medication_logs
                WHERE patient_id = ? AND medicine_name LIKE ?
                  AND DATE(taken_time) BETWEEN ? AND ?
                ORDER BY taken_time DESC
            ");
            $like = '%' . $search . '%';
            $stmt->bind_param("isss", $pid, $like, $startDate, $endDate);
        } else {
            $stmt = $conn->prepare("
                SELECT medicine_name, status, taken_time
                FROM medication_logs
                WHERE patient_id = ?
                  AND DATE(taken_time) BETWEEN ? AND ?
                ORDER BY taken_time DESC
            ");
            $stmt->bind_param("iss", $pid, $startDate, $endDate);
        }
        $stmt->execute();
        $logs = $stmt->get_result();

        $taken        = 0;
        $missed       = 0;
        $medBreakdown = [];
        $dayStatus    = [];

        while ($log = $logs->fetch_assoc()) {
            $s   = $log['status'];
            $med = $log['medicine_name'];
            $day = date('Y-m-d', strtotime($log['taken_time']));

            if ($s == 'Taken') {
                $taken++;
                $dayStatus[$day] = $dayStatus[$day] ?? 'taken';
            } else {
                $missed++;
                if (!isset($dayStatus[$day]) || $dayStatus[$day] !== 'taken')
                    $dayStatus[$day] = 'missed';
            }

            if (!isset($medBreakdown[$med]))
                $medBreakdown[$med] = ['taken'=>0,'missed'=>0];
            $medBreakdown[$med][$s=='Taken'?'taken':'missed']++;
        }

        $total      = $taken + $missed;
        $percentage = ($total > 0) ? round(($taken / $total) * 100, 1) : 0;

        if      ($percentage >= 80) { $sc = 'good';    $st = '✅ Good';    }
        elseif  ($percentage >= 50) { $sc = 'warning'; $st = '⚠️ Warning'; }
        else                        { $sc = 'bad';     $st = '❌ Poor';    }
    ?>

    <div class="report-card fade-in delay-3">

        <!-- CARD HEADER -->
        <div class="report-card-head">
            <div class="rc-patient">
                <div class="rc-avatar"><?php echo $initial; ?></div>
                <div>
                    <div class="rc-name"><?php echo htmlspecialchars($p['name']); ?></div>
                    <div class="rc-period"><?php echo htmlspecialchars($label); ?></div>
                </div>
            </div>
            <span class="adherence-badge <?php echo $sc; ?>">
                <?php echo $percentage; ?>% &nbsp;<?php echo $st; ?>
            </span>
        </div>

        <!-- CARD BODY -->
        <div class="report-card-body">

            <?php if ($total == 0): ?>
                <div class="no-data">
                    📭 No medication logs found for this period<?php echo $search ? " matching \"".htmlspecialchars($search)."\"" : ''; ?>.
                </div>

            <?php else: ?>

            <!-- STAT ROW -->
            <div class="rc-stats">
                <div class="rc-stat s-taken">
                    <div class="rc-stat-val"><?php echo $taken; ?></div>
                    <div class="rc-stat-lbl">Taken</div>
                </div>
                <div class="rc-stat s-missed">
                    <div class="rc-stat-val"><?php echo $missed; ?></div>
                    <div class="rc-stat-lbl">Missed</div>
                </div>
                <div class="rc-stat s-total">
                    <div class="rc-stat-val"><?php echo $total; ?></div>
                    <div class="rc-stat-lbl">Total</div>
                </div>
                <div class="rc-stat s-pct">
                    <div class="rc-stat-val"><?php echo $percentage; ?>%</div>
                    <div class="rc-stat-lbl">Adherence</div>
                </div>
            </div>

            <!-- CHART + TABLE -->
            <div class="rc-cols">
                <div>
                    <canvas id="chart<?php echo $chartIndex; ?>"></canvas>
                </div>
                <div>
                    <?php if (!empty($medBreakdown)): ?>
                    <table class="med-table">
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Taken</th>
                                <th>Missed</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($medBreakdown as $medName => $counts):
                            $mt = $counts['taken'];
                            $mm = $counts['missed'];
                            $mp = ($mt+$mm > 0) ? round(($mt/($mt+$mm))*100, 1) : 0;
                            $mc = $mp>=80 ? 'good' : ($mp>=50 ? 'warning' : 'bad');
                        ?>
                            <tr>
                                <td>💊 <?php echo htmlspecialchars($medName); ?></td>
                                <td class="c-taken"><?php echo $mt; ?></td>
                                <td class="c-missed"><?php echo $mm; ?></td>
                                <td><span class="pill-badge <?php echo $mc; ?>"><?php echo $mp; ?>%</span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CALENDAR -->
            <div class="cal-section">
                <h4>Daily Overview</h4>
                <div class="calendar">
                <?php
                    $calStart  = new DateTime($startDate);
                    $calEnd    = new DateTime($endDate);
                    $calEnd->modify('+1 day');
                    $calRange  = new DatePeriod($calStart, new DateInterval('P1D'), $calEnd);

                    foreach ($calRange as $d):
                        $dStr = $d->format('Y-m-d');
                        $dDay = $d->format('D');
                        $dNum = $d->format('d');
                        if (isset($dayStatus[$dStr])) {
                            $cls = $dayStatus[$dStr];
                            $sym = $cls == 'taken' ? '✔' : '✖';
                        } else {
                            $cls = 'none'; $sym = '–';
                        }
                ?>
                    <div class="day <?php echo $cls; ?>">
                        <?php echo $sym; ?>
                        <span class="lbl"><?php echo $dDay; ?><br><?php echo $dNum; ?></span>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>

            <?php endif; ?>
        </div><!-- .report-card-body -->
    </div><!-- .report-card -->

    <script>
    (function(){
        var ctx = document.getElementById('chart<?php echo $chartIndex; ?>').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Taken','Missed'],
                datasets: [{
                    data: [<?php echo $taken; ?>, <?php echo $missed; ?>],
                    backgroundColor: ['#5b9e6e','#c0504a'],
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font:{ size:12 }, padding:14 }
                    }
                }
            }
        });
    })();
    </script>

    <?php
        $chartIndex++;
    endwhile;
    ?>

</div>
</body>
</html>