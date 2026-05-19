<?php
session_start();
include "db_connect.php";

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] == 'patient' ? 'patients_dashboard.php' : 'caregiver_dashboard.php'));
    exit();
}

if (isset($_POST['register'])) {

    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $role     = $_POST['role'];

    // CHECK EXISTING EMAIL
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {

        $error = "An account with this email already exists.";

    } elseif (strlen($password) < 8) {

        $error = "Password must be at least 8 characters.";

    } elseif ($role !== 'caregiver') {

        $error = "Please select a valid role.";

    } else {

        // SAVE PASSWORD WITHOUT HASH
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $password, $role);

        if ($stmt->execute()) {
            $success = "Account created! You can now sign in.";
        } else {
            $error = "Registration failed.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carely — Caregiver Registration</title>

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
            max-width: 440px;
            overflow: hidden;
        }

        .auth-card-head {
            background: linear-gradient(135deg, var(--lilac-light), #ede4f0);
            padding: 28px 36px 22px;
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

        .form-group input,
        .form-group select {
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

        .hint {
            font-size: .74rem;
            color: var(--ink-muted);
            margin-top: 4px;
        }

        .role-notice {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            background: var(--lilac-light);
            border: 1px solid var(--lilac-mid, #e0d0e0);
            border-radius: 10px;
            padding: 14px 16px;
            margin-top: 4px;
        }

        .role-notice-icon {
            font-size: 1.6rem;
            flex-shrink: 0;
        }

        .role-notice-title {
            font-weight: 600;
            font-size: .9rem;
            color: var(--lilac-deep);
            margin-bottom: 3px;
        }

        .role-notice-desc {
            font-size: .8rem;
            color: var(--ink-muted);
            line-height: 1.5;
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

            <h2>Create a caregiver account</h2>

            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    ⚠️ <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($success); ?>
                    <a href="index.php">Sign in →</a>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="form-group">
                    <label>Full Name</label>

                    <input
                        type="text"
                        name="name"
                        placeholder="e.g. Ahmad bin Yusof"
                        required
                        value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                    >
                </div>

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
                        placeholder="Min. 8 characters"
                        required
                    >

                    <p class="hint">
                        Must be at least 8 characters.
                    </p>
                </div>

                <!-- CAREGIVER ONLY -->
                <input type="hidden" name="role" value="caregiver">

                <div class="form-group">

                    <label>Account Type</label>

                    <div class="role-notice">

                        <div class="role-notice-icon">
                            👩‍⚕️
                        </div>

                        <div>

                            <div class="role-notice-title">
                                Caregiver Account
                            </div>

                            <div class="role-notice-desc">
                                Patient accounts are created by caregivers after registration.<br>
                                If you are a patient, ask your caregiver to add you.
                            </div>

                        </div>

                    </div>

                </div>

                <button name="register" class="btn btn-auth">
                    ✨ Create Account
                </button>

            </form>

            <div class="auth-footer">
                Already have an account?
                <a href="index.php">Sign in</a>
            </div>

        </div>

    </div>

</div>

</body>
</html>