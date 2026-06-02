<?php
require_once 'includes/init.php';

$error = '';
$signup_success = $_SESSION['signup_success'] ?? '';
unset($_SESSION['signup_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $clean_username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);

    if (empty($clean_username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (strlen($password) > 128) {
        $error = 'Invalid username or password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$clean_username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['group_id'] = $user['group_id'] ?? 0;
            $_SESSION['status'] = $user['status'] ?? 'active';

            try {
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            } catch (PDOException $e) {}

            if (empty($user['group_id']) && $user['role'] === 'member') {
                header('Location: join_group.php');
                exit();
            }
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    }
    }
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — RSGMS</title>
<link rel="stylesheet" href="assets/css/icons.css">
<link rel="stylesheet" href="assets/css/design-system.css">
<style>
.auth-page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.auth-card{background:var(--white-soft);border:1px solid oklch(from var(--clay) l c h / .08);border-radius:20px;width:min(100%,420px);padding:clamp(2rem,4vw,2.5rem);box-shadow:0 12px 40px oklch(0 0 0 / .04)}
.auth-card h1{font-size:1.5rem;font-weight:500;text-align:center;color:var(--ink);margin-bottom:0.25rem;font-variation-settings:'SOFT' 80}
.auth-card .sub{text-align:center;color:var(--ink-soft);font-size:0.9rem;margin-bottom:1.5rem}
.auth-card .flash-message{margin-bottom:1rem}
.auth-card .form-group{margin-bottom:1rem}
.auth-card label{font-size:0.82rem}
.auth-card input{background:var(--cream-light)}
.auth-card .btn{width:100%;justify-content:center;margin-top:0.5rem;padding:0.75rem}
.auth-footer{text-align:center;margin-top:1.5rem;font-size:0.85rem;color:var(--ink-soft)}
.auth-footer a{color:var(--clay);text-decoration:none}
.auth-footer a:hover{text-decoration:underline}
.auth-footer .divider{margin:1rem 0;border:none;border-top:1px solid oklch(from var(--clay) l c h / .08)}
.auth-footer .demo-creds{font-size:0.8rem;color:var(--ink-soft);margin-top:0.5rem}
.auth-back{display:block;text-align:center;margin-top:1rem;font-size:0.85rem;color:var(--ink-soft);text-decoration:none}
.auth-back:hover{color:var(--clay)}
@media(max-width:480px){.auth-card{padding:1.5rem}}
</style>
</head>
<body class="auth-page">
<div class="auth-card">
    <h1>RSGMS</h1>
    <p class="sub">Sign in to your account</p>

    <?php if ($signup_success): ?>
    <div class="flash-message success"><?php echo htmlspecialchars($signup_success); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="flash-message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="loginForm" novalidate>
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" id="username" maxlength="50" required autofocus>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" id="password" maxlength="128" required>
        </div>
        <button type="submit" class="btn btn-primary">Sign in</button>
    </form>

    <div class="auth-footer">
        <p>Don't have an account? <a href="signup.php">Sign up</a></p>
        <p style="margin-top:4px;"><a href="register.php">Register a group</a></p>
        <hr class="divider">
        <p style="font-size:0.82rem;color:var(--ink-soft);opacity:.7">Demo: admin / admin123</p>
    </div>

    <a href="index.php" class="auth-back">← Back to home</a>
</div>

<script>
document.getElementById('loginForm')?.addEventListener('submit', function(e){
    const u=document.getElementById('username').value.trim(),p=document.getElementById('password').value;
    if(!u||!p){e.preventDefault();alert('Please fill in all fields.')}
});
</script>
<script src="assets/js/loading.js"></script>
</body>
</html>
