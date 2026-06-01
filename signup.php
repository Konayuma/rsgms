<?php
require_once 'includes/init.php';

$error = '';
$formData = [];

$rate_key = 'signup_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$signup_attempts = $_SESSION[$rate_key] ?? ['count' => 0, 'first' => 0];
if ($signup_attempts['count'] > 0 && (time() - $signup_attempts['first']) > 3600) {
    $signup_attempts = ['count' => 0, 'first' => 0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($signup_attempts['count'] >= 3) {
        $error = 'Too many signup attempts. Please try again later.';
    } else {
        $formData = Validation::sanitizeArray($_POST);
        $full_name = $formData['full_name'] ?? '';
        $username  = $formData['username'] ?? '';
        $email     = $formData['email'] ?? '';
        $phone     = $formData['phone'] ?? '';
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm_password'] ?? '';

        $valid = true;
        if (!Validation::required($formData, ['full_name', 'username', 'password', 'confirm_password'])) $valid = false;
        if (!Validation::fullName($full_name)) $valid = false;
        if (!Validation::username($username)) $valid = false;
        if (!Validation::uniqueUsername($pdo, $username)) $valid = false;
        if (!Validation::email($email)) $valid = false;
        if ($email !== '' && !Validation::uniqueEmail($pdo, $email)) $valid = false;
        if (!Validation::phone($phone)) $valid = false;
        if (!Validation::password($password, 8)) $valid = false;
        if (!Validation::match($password, $confirm, 'Passwords do not')) $valid = false;

        if ($valid) {
            try {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role) VALUES (?, ?, ?, ?, ?, 'member')");
                $stmt->execute([$username, $hashed, $full_name, $email, $phone]);

                $signup_attempts = ['count' => 0, 'first' => 0];
                $_SESSION[$rate_key] = $signup_attempts;
                session_regenerate_id(true);

                $_SESSION['signup_success'] = "Account created! Log in and join a savings group using an invitation code.";
                header('Location: login.php');
                exit();
            } catch (PDOException $e) {
                $error = "Registration failed. Please try again.";
            }
        } else {
            $signup_attempts['count']++;
            if ($signup_attempts['count'] === 1) $signup_attempts['first'] = time();
            $error = Validation::firstError();
        }
    }
    $_SESSION[$rate_key] = $signup_attempts;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign Up — RSGMS</title>
<link rel="stylesheet" href="assets/css/icons.css">
<link rel="stylesheet" href="assets/css/design-system.css">
<style>
.auth-page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.auth-card{background:var(--white-soft);border:1px solid oklch(from var(--clay) l c h / .08);border-radius:20px;width:min(100%,480px);padding:clamp(2rem,4vw,2.5rem);box-shadow:0 12px 40px oklch(0 0 0 / .04)}
.auth-card h1{font-size:1.5rem;font-weight:500;text-align:center;color:var(--ink);margin-bottom:0.25rem;font-variation-settings:'SOFT' 80}
.auth-card .sub{text-align:center;color:var(--ink-soft);font-size:0.9rem;margin-bottom:1.5rem}
.auth-card .flash-message{margin-bottom:1rem}
.auth-card .form-group{margin-bottom:1rem}
.auth-card .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.auth-card label{font-size:0.82rem}
.auth-card input{background:var(--cream-light)}
.auth-card .field-hint{font-size:0.72rem;color:var(--ink-soft);margin-top:2px;opacity:.7}
.auth-card .btn{width:100%;justify-content:center;margin-top:0.5rem;padding:0.75rem}
.auth-footer{text-align:center;margin-top:1.5rem;font-size:0.85rem;color:var(--ink-soft)}
.auth-footer a{color:var(--clay);text-decoration:none}
.auth-footer a:hover{text-decoration:underline}
.pw-bar{height:4px;border-radius:2px;margin-top:4px;background:oklch(from var(--clay) l c h / .08);width:0;transition:width .3s,background .3s}
@media(max-width:480px){.auth-card{padding:1.5rem}.auth-card .form-row{grid-template-columns:1fr;gap:0}}
</style>
</head>
<body class="auth-page">
<div class="auth-card">
    <h1>Join RSGMS</h1>
    <p class="sub">Create your member account</p>

    <?php if ($error): ?>
    <div class="flash-message error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="" id="signupForm" novalidate>
        <p style="margin-bottom:16px;color:var(--ink-soft);font-size:0.85rem;opacity:.7">You'll receive an invitation code from your group administrator to join a savings group after signing up.</p>

        <div class="form-group">
            <label>Full name *</label>
            <input type="text" name="full_name" id="full_name" maxlength="100" value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>" required>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" id="username" maxlength="50" value="<?php echo htmlspecialchars($formData['username'] ?? ''); ?>" required>
                <div class="field-hint">Letters, numbers, underscores only</div>
            </div>
            <div class="form-group">
                <label>Phone</label>
                <input type="tel" name="phone" id="phone" maxlength="20" placeholder="0977123456" value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>">
            </div>
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" id="email" maxlength="100" value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>">
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" id="password" maxlength="128" required>
                <div class="pw-bar" id="pwBar"></div>
                <div class="field-hint">Min 8 characters, 1 uppercase, 1 digit</div>
            </div>
            <div class="form-group">
                <label>Confirm password *</label>
                <input type="password" name="confirm_password" id="confirm_password" maxlength="128" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Create account</button>
    </form>

    <div class="auth-footer">
        Already have one? <a href="login.php">Log in</a><br>
        Need to register a group? <a href="register.php">Register group</a>
    </div>
</div>

<script>
document.getElementById('password')?.addEventListener('input',function(){
    const v=this.value;const bar=document.getElementById('pwBar');
    let s=0;if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[a-z]/.test(v))s++;
    const pct=Math.min(s*25,100);bar.style.width=pct+'%';
    bar.style.background=s<=1?'var(--danger)':s<=2?'var(--gold)':s<=3?'var(--clay-light)':'var(--success)';
});
</script>
</body>
</html>
