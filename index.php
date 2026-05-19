<?php
session_start();
include "db_connect.php";

// Show flash message
$flash = '';

if (isset($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

// Already logged in
if (isset($_SESSION['user_id'])) {

    header("Location: " .
        ($_SESSION['role'] == 'patient'
            ? 'patients_dashboard.php'
            : 'caregiver_dashboard.php'));

    exit();
}

// LOGIN
if (isset($_POST['login'])) {

    $email    = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        // NORMAL PASSWORD CHECK (NO HASH)
        if ($password == $user['password']) {

            $_SESSION['user_id']   = $user['id'];
            $_SESSION['role']      = $user['role'];
            $_SESSION['user_name'] = $user['name'];

            header("Location: " .
                ($user['role'] == 'patient'
                    ? 'patients_dashboard.php'
                    : 'caregiver_dashboard.php'));

            exit();

        } else {

            $error = "Invalid email or password.";

        }

    } else {

        $error = "Invalid email or password.";

    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carely — Login</title>

    <link rel="stylesheet" href="style.css">

    <style>

        .auth-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .auth-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }

        .auth-card-head {
            background: linear-gradient(135deg, var(--lilac-light), #ede4f0);
            padding: 32px 36px 24px;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }

        .auth-brand {
            font-family: 'Fraunces', serif;
            font-size: 2rem;
            font-weight: 600;
            color: var(--lilac-deep);
            letter-spacing: -.3px;
            margin-bottom: 4px;
        }

        .auth-brand span {
            color: var(--lilac-dark);
        }

        .auth-slogan {
            font-size: .78rem;
            color: var(--ink-muted);
            font-style: italic;
            letter-spacing: .04em;
        }

        .auth-card-body {
            padding: 28px 36px 32px;
        }

        .auth-card-body h2 {
            font-family: 'Fraunces', serif;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--lilac-deeper);
            text-align: left;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: .82rem;
            font-weight: 500;
            color: var(--ink-muted);
            margin-bottom: 5px;
        }

        .form-group input {
            margin-top: 0;
            width: 100%;
        }

        .btn-auth {
            width: 100%;
            margin-top: 8px;
            padding: 12px;
            font-size: .95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .auth-footer {
            text-align: center;
            margin-top: 20px;
            font-size: .84rem;
            color: var(--ink-muted);
        }

        .auth-footer a {
            color: var(--lilac-deep);
            font-weight: 500;
        }

        .auth-footer a:hover {
            color: var(--lilac-dark);
        }

        .alert {
            padding: 11px 14px;
            border-radius: 8px;
            font-size: .85rem;
            margin-bottom: 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-error {
            background: var(--rose-light);
            color: #8b2020;
            border-left: 4px solid var(--rose);
        }

        .alert-success {
            background: var(--green-light);
            color: #2e6b3e;
            border-left: 4px solid var(--green);
        }

    </style>
</head>

<body>

<div class="auth-wrap">

    <div class="auth-card fade-in">

        <div class="auth-card-head">
            <div class="auth-brand">Care<span>ly</span></div>
            <div class="auth-slogan">Caring for Every Dose</div>
        </div>

        <div class="auth-card-body">

            <h2>Welcome back</h2>

            <?php if ($flash): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($flash); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="form-group">
                    <label>Email Address</label>

                    <input
                        type="email"
                        name="email"
                        placeholder="you@email.com"
                        required
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label>Password</label>

                    <input
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                    >
                </div>

                <button name="login" class="btn btn-auth">
                    🔐 Sign In
                </button>

            </form>

            <div class="auth-footer">
                Don't have an account?
                <a href="register.php">Create one</a>
            </div>

        </div>

    </div>

</div>

</body>
</html>