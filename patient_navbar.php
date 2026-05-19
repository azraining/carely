<?php
if (!isset($_SESSION)) session_start();
$current = basename($_SERVER['PHP_SELF']);

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
$_navInitial = $_navName ? strtoupper(substr($_navName, 0, 1)) : '?';
?>
<style>
.topbar {
    background: #ffffff;
    border-bottom: 1px solid var(--border);
    padding: 10px 28px;
    min-height: 72px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    position: sticky;
    top: 0;
    z-index: 200;
    box-shadow: 0 2px 12px rgba(90,74,106,.08);
}
.topbar-brand-wrap {
    display: flex; flex-direction: column;
    justify-content: center; gap: 1px;
    text-decoration: none; flex-shrink: 0;
}
.topbar-brand {
    font-family: 'Fraunces', serif;
    font-size: 1.6rem; font-weight: 600;
    color: var(--lilac-deep); letter-spacing: -.2px;
    line-height: 1.1; text-decoration: none;
}
.topbar-brand span { color: var(--lilac-dark); }
.topbar-slogan {
    font-size: .8rem; color: var(--ink-muted);
    letter-spacing: .04em; font-style: italic; line-height: 1;
}
.topbar-nav {
    display: flex; align-items: center; gap: 2px;
    flex: 1; justify-content: center;
}
.topbar-nav a {
    padding: 10px 16px; border-radius: 10px;
    text-decoration: none; color: var(--ink-muted);
    font-weight: 500; font-size: 1rem;
    position: relative; transition: color .18s, background .18s; white-space: nowrap;
}
.topbar-nav a:hover { color: var(--lilac-deep); background: var(--lilac-light); text-decoration: none; }
.topbar-nav a.active { color: white; font-weight: 600; background: var(--lilac-deep); }
.topbar-nav a.active::after {
    content: ''; position: absolute;
    bottom: -1px; left: 12px; right: 12px;
    height: 2px; background: var(--lilac-dark); border-radius: 2px;
}
.topbar-nav a.nav-logout { color: var(--rose); margin-left: 4px; }
.topbar-nav a.nav-logout:hover { background: var(--rose-light); color: #a33; text-decoration: none; }
.topbar-right { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.topbar-greeting { font-size: 1rem; color: var(--ink-muted); white-space: nowrap; }
.topbar-greeting b { font-size: 1.05rem; color: var(--ink); font-weight: 600; }
.topbar-avatar {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, var(--lilac), var(--lilac-deep));
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-family: 'Fraunces', serif; font-size: 1.1rem; font-weight: 600;
    text-decoration: none; flex-shrink: 0; transition: opacity .2s;
}
.topbar-avatar:hover { opacity: .85; text-decoration: none; }
@media(max-width:900px) { .topbar-greeting { display: none; } .topbar { padding: 0 16px; } }
@media(max-width:680px) {
    .topbar { height:auto; flex-wrap:wrap; padding:12px 14px; gap:8px; }
    .topbar-nav { order:3; width:100%; flex-wrap:wrap; justify-content:flex-start; gap:2px; }
    .topbar-nav a { font-size:.95rem; padding:8px 10px; }
}
</style>

<header class="topbar">
    <a href="patients_dashboard.php" class="topbar-brand-wrap">
        <span class="topbar-brand">Care<span>ly</span></span>
        <span class="topbar-slogan">Caring for Every Dose</span>
    </a>

    <nav class="topbar-nav">
        <a href="patients_dashboard.php"   class="<?php echo $current=='patients_dashboard.php'?'active':''; ?>">Dashboard</a>
        <a href="patient_add_schedule.php" class="<?php echo $current=='patient_add_schedule.php'?'active':''; ?>">My Medication</a>
        <a href="logout.php" class="nav-logout">🚪 Logout</a>
    </nav>

    <div class="topbar-right">
        <span class="topbar-greeting">Welcome, <b><?php echo htmlspecialchars($_navName); ?></b></span>
        <div class="topbar-avatar"><?php echo $_navInitial; ?></div>
    </div>
</header>