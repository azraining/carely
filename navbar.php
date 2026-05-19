<?php
if (!isset($_SESSION)) session_start();
$current = basename($_SERVER['PHP_SELF']);

// Get user name for greeting + avatar
$_navName = '';
if (isset($_SESSION['user_id'])) {
    // Use cached session value to avoid a DB hit on every page
    $_navName = $_SESSION['user_name'] ?? '';
    if (empty($_navName) && isset($GLOBALS['conn'])) {
        $conn = $GLOBALS['conn'];
        $s = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $uid = intval($_SESSION['user_id']);
        $s->bind_param("i", $uid);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $_navName = $r['name'] ?? '';
        $_SESSION['user_name'] = $_navName;
    }
}
$_navInitial = $_navName ? strtoupper(substr($_navName, 0, 1)) : '?';
?>

<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,300;0,600;1,300&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
<style>
/* ── TOPBAR ── */
.topbar {
    background: #ffffff;
    border-bottom: 1px solid var(--border);
    padding: 0 32px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    position: sticky;
    top: 0;
    z-index: 200;
    box-shadow: 0 2px 12px rgba(90,74,106,.08);
}

/* Brand */
.topbar-brand-wrap {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 1px;
    text-decoration: none;
    flex-shrink: 0;
}
.topbar-brand {
    font-family: 'Fraunces', serif;
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--lilac-deep);
    letter-spacing: -.2px;
    line-height: 1.1;
    text-decoration: none;
}
.topbar-brand span { color: var(--lilac-dark); }
.topbar-slogan {
    font-size: .62rem;
    color: var(--ink-muted);
    letter-spacing: .04em;
    font-style: italic;
    line-height: 1;
}

/* Nav links — centre */
.topbar-nav {
    display: flex;
    align-items: center;
    gap: 2px;
    flex: 1;
    justify-content: center;
}
.topbar-nav a {
    padding: 6px 12px;
    border-radius: 8px;
    text-decoration: none;
    color: var(--ink-muted);
    font-weight: 500;
    font-size: .875rem;
    position: relative;
    transition: color .18s, background .18s;
    white-space: nowrap;
}
.topbar-nav a:hover {
    color: var(--lilac-deep);
    background: var(--lilac-light);
    text-decoration: none;
}
.topbar-nav a.active {
    color: var(--lilac-deep);
    font-weight: 600;
    background: var(--lilac-light);
}
.topbar-nav a.active::after {
    content: '';
    position: absolute;
    bottom: -1px; left: 12px; right: 12px;
    height: 2px;
    background: var(--lilac-dark);
    border-radius: 2px;
}
.topbar-nav a.nav-logout {
    color: var(--rose);
    margin-left: 4px;
}
.topbar-nav a.nav-logout:hover {
    background: var(--rose-light);
    color: #a33;
    text-decoration: none;
}

/* Right: greeting + avatar */
.topbar-right {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-shrink: 0;
}
.topbar-greeting {
    font-size: .82rem;
    color: var(--ink-muted);
    white-space: nowrap;
}
.topbar-greeting b { color: var(--ink); font-weight: 600; }
.topbar-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--lilac), var(--lilac-deep));
    color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-family: 'Fraunces', serif;
    font-size: .88rem; font-weight: 600;
    text-decoration: none;
    flex-shrink: 0;
    transition: opacity .2s;
}
.topbar-avatar:hover { opacity: .85; text-decoration: none; }

/* Responsive */
@media(max-width:900px) {
    .topbar-greeting { display: none; }
    .topbar { padding: 0 16px; }
}
@media(max-width:680px) {
    .topbar {
        height: auto;
        flex-wrap: wrap;
        padding: 10px 16px;
        gap: 8px;
    }
    .topbar-nav {
        order: 3;
        width: 100%;
        flex-wrap: wrap;
        justify-content: flex-start;
        gap: 2px;
    }
    .topbar-nav a { font-size: .8rem; padding: 5px 8px; }
}
</style>

<header class="topbar">

    <!-- BRAND -->
    <a href="caregiver_dashboard.php" class="topbar-brand-wrap">
        <span class="topbar-brand">Care<span>ly</span></span>
        <span class="topbar-slogan">Caring for Every Dose</span>
    </a>

    <!-- NAV LINKS -->
    <nav class="topbar-nav">
        <a href="caregiver_dashboard.php" class="<?php echo $current=='caregiver_dashboard.php'?'active':''; ?>">Dashboard</a>
        <a href="add_patient.php"         class="<?php echo $current=='add_patient.php'?'active':''; ?>">Add Patient</a>
        <a href="add_schedule.php"        class="<?php echo $current=='add_schedule.php'?'active':''; ?>">Schedule</a>
        <a href="medication_logs.php"     class="<?php echo $current=='medication_logs.php'?'active':''; ?>">Logs</a>
        <a href="report_dashboard.php"    class="<?php echo $current=='report_dashboard.php'?'active':''; ?>">Reports</a>
        <a href="settings.php"            class="<?php echo $current=='settings.php'?'active':''; ?>">Settings</a>
        <a href="logout.php"              class="nav-logout">Logout</a>
    </nav>

    <!-- GREETING + AVATAR -->
    <div class="topbar-right">
        <span class="topbar-greeting">Welcome, <b><?php echo htmlspecialchars($_navName); ?></b></span>
        <a href="settings.php" class="topbar-avatar" title="Account Settings">
            <?php echo $_navInitial; ?>
        </a>
    </div>

</header>