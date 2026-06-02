<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT group_id, status FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user && $user['group_id'] && $user['status'] === 'active') {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['invitation_code'] ?? '');
    $code = preg_replace('/[^0-9]/', '', $code);

    if (strlen($code) !== 6) {
        $error = 'Invitation code must be exactly 6 digits.';
    } else {
        $stmt = $pdo->prepare("SELECT id, group_name FROM savings_groups WHERE invitation_code = ?");
        $stmt->execute([$code]);
        $group = $stmt->fetch();

        if (!$group) {
            $error = 'Invalid invitation code. Please check with your group administrator.';
        } else {
            $stmt = $pdo->prepare("UPDATE users SET group_id = ?, status = 'pending' WHERE id = ?");
            $stmt->execute([$group['id'], $user_id]);
            $_SESSION['group_id'] = $group['id'];
            $_SESSION['status'] = 'pending';

            $stmt = $pdo->prepare("SELECT group_id, status FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Join Group — RSGMS</title>
<link rel="stylesheet" href="assets/css/icons.css">
<link rel="stylesheet" href="assets/css/design-system.css">
<style>
.auth-page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.auth-card{background:var(--white-soft);border:1px solid oklch(from var(--clay) l c h / .08);border-radius:20px;width:min(100%,440px);padding:clamp(2rem,4vw,2.5rem);box-shadow:0 12px 40px oklch(0 0 0 / .04);text-align:center}
.auth-card h1{font-size:1.5rem;font-weight:500;color:var(--ink);margin-bottom:0.25rem;font-variation-settings:'SOFT' 80}
.auth-card .sub{color:var(--ink-soft);font-size:0.9rem;margin-bottom:1.5rem}
.auth-card .flash-message{margin-bottom:1rem;text-align:left}
.join-steps{display:flex;flex-direction:column;gap:8px;margin-bottom:1.5rem;text-align:left}
.join-step{display:flex;align-items:flex-start;gap:10px;font-size:0.88rem;color:var(--ink-soft)}
.join-step-num{width:24px;height:24px;border-radius:50%;background:var(--clay);color:var(--cream);display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:600;flex-shrink:0}
.auth-card .code-input{margin-bottom:1.5rem}
.auth-card .code-input input{width:100%;max-width:240px;padding:14px 16px;font-size:1.5rem;letter-spacing:8px;text-align:center;border:2px solid oklch(from var(--clay) l c h / .15);border-radius:12px;font-weight:600;font-family:'Fraunces',serif;transition:border-color .2s;background:var(--cream-light);color:var(--ink)}
.auth-card .code-input input:focus{outline:none;border-color:var(--clay);box-shadow:0 0 0 3px oklch(from var(--clay) l c h / .1);background:var(--cream)}
.auth-card .btn{width:100%;justify-content:center;padding:0.75rem}
.auth-footer{margin-top:1.5rem;font-size:0.85rem}
.auth-footer a{color:var(--clay);text-decoration:none}
.auth-footer a:hover{text-decoration:underline}
.pending-state{padding:10px 0}
.pending-state .pend-icon{font-size:2.5rem;color:var(--gold);margin-bottom:12px}
.pending-state h2{font-family:'Fraunces',serif;font-size:1.3rem;font-weight:500;color:var(--ink);margin-bottom:6px;font-variation-settings:'SOFT' 80}
.pending-state p{color:var(--ink-soft);font-size:0.9rem;margin-bottom:16px}
.info-box{background:var(--cream);border:1px solid oklch(from var(--gold) l c h / .2);border-radius:12px;padding:14px;font-size:0.85rem;color:var(--ink-soft);text-align:left}
.info-box strong{display:block;color:var(--ink);margin-bottom:4px}
</style>
</head>
<body class="auth-page">
<div class="auth-card">
    <?php if ($user && $user['group_id'] && $user['status'] === 'pending'): ?>
        <?php $stmt = $pdo->prepare("SELECT sg.group_name FROM savings_groups sg JOIN users u ON u.group_id = sg.id WHERE u.id = ?"); $stmt->execute([$user_id]); $joined_group = $stmt->fetch(); ?>
        <div class="pending-state">
            <div class="pend-icon"><i class="fa-solid fa-hourglass-half"></i></div>
            <h2>Request sent!</h2>
            <p>You've requested to join <strong><?php echo htmlspecialchars($joined_group['group_name'] ?? 'the group'); ?></strong>.</p>
            <div class="info-box"><strong>Pending approval</strong>Your membership request is awaiting admin approval. You'll get full access once an administrator reviews and approves your request.</div>
            <div style="margin-top:20px;"><a href="dashboard.php" class="btn btn-primary">Go to dashboard</a></div>
        </div>
    <?php else: ?>
        <h1>Join a savings group</h1>
        <p class="sub">Enter your invitation code</p>

        <div class="join-steps">
            <div class="join-step"><span class="join-step-num">1</span> Your group administrator gives you a 6-digit invitation code.</div>
            <div class="join-step"><span class="join-step-num">2</span> Enter it below to link your account to the group.</div>
            <div class="join-step"><span class="join-step-num">3</span> Wait for admin approval to start saving and borrowing.</div>
        </div>

        <?php if ($error): ?><div class="flash-message error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <form method="POST" action="">
            <div class="code-input">
                <input type="text" name="invitation_code" id="code" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="off" placeholder="000000" autofocus>
            </div>
            <button type="submit" class="btn btn-primary">Join group</button>
        </form>

        <div class="auth-footer">
            <a href="dashboard.php">Skip for now →</a>
        </div>
    <?php endif; ?>
</div>

<script>
document.getElementById('code')?.addEventListener('input',function(){this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)});
</script>
<script src="assets/js/loading.js"></script>
</body>
</html>
