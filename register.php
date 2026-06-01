<?php
session_start();
require_once 'config/database.php';
require_once 'config/validation.php';

$message = '';
$error = '';
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = Validation::sanitizeArray($_POST);
    $group_name        = $formData['group_name'] ?? '';
    $description       = $formData['description'] ?? '';
    $interest_rate     = floatval($formData['interest_rate'] ?? 10);
    $penalty_rate      = floatval($formData['penalty_rate'] ?? 5);
    $meeting_day       = $formData['meeting_day'] ?? '';
    $contribution_amount = floatval($formData['contribution_amount'] ?? 0);
    $admin_username    = $formData['admin_username'] ?? '';
    $admin_password    = $_POST['admin_password'] ?? '';
    $admin_full_name   = $formData['admin_full_name'] ?? '';
    $admin_email       = $formData['admin_email'] ?? '';
    $admin_phone       = $formData['admin_phone'] ?? '';

    $valid = true;
    if (!Validation::required($_POST, ['group_name', 'admin_username', 'admin_password', 'admin_full_name'])) $valid = false;
    if (!Validation::fullName($admin_full_name)) $valid = false;
    if (!Validation::username($admin_username)) $valid = false;
    if (!Validation::uniqueUsername($pdo, $admin_username)) $valid = false;
    if (!Validation::email($admin_email)) $valid = false;
    if ($admin_email !== '' && !Validation::uniqueEmail($pdo, $admin_email)) $valid = false;
    if (!Validation::phone($admin_phone)) $valid = false;
    if (!Validation::password($admin_password, 8)) $valid = false;
    if (!Validation::positiveNumber($interest_rate, 'Interest rate')) $valid = false;
    if (!Validation::positiveNumber($penalty_rate, 'Penalty rate')) $valid = false;
    if (!Validation::positiveNumber($contribution_amount, 'Contribution amount')) $valid = false;

    if ($valid) {
        $prefix = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $group_name), 0, 4));
        $prefix = $prefix ?: 'GRP';
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM savings_groups WHERE group_code LIKE ?");
        $stmt->execute([$prefix . '%']);
        $seq = $stmt->fetch()['cnt'] + 1;
        $group_code = $prefix . str_pad($seq, 3, '0', STR_PAD_LEFT);

        do {
            $invitation_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM savings_groups WHERE invitation_code = ?");
            $stmt->execute([$invitation_code]);
        } while ($stmt->fetch()['cnt'] > 0);

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO savings_groups (group_name, group_code, invitation_code, description, interest_rate, penalty_rate, meeting_day, contribution_amount, cycle_start_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$group_name, $group_code, $invitation_code, $description, $interest_rate, $penalty_rate, $meeting_day, $contribution_amount, date('Y-m-d')]);
            $group_id = $pdo->lastInsertId();

            $hashed = password_hash($admin_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, group_id) VALUES (?, ?, ?, ?, ?, 'group_admin', ?)");
            $stmt->execute([$admin_username, $hashed, $admin_full_name, $admin_email, $admin_phone, $group_id]);

            $pdo->commit();
            $message = "Group registered successfully! Your group code is <strong>{$group_code}</strong> and invitation code is <strong>{$invitation_code}</strong>. Share the invitation code with members so they can join.";
            $_POST = [];
            $formData = [];
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Registration failed: " . $e->getMessage();
        }
    } else {
        $error = Validation::firstError();
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register Group — RSGMS</title>
<link rel="stylesheet" href="assets/css/icons.css">
<link rel="stylesheet" href="assets/css/design-system.css">
<style>
.auth-page{min-height:100vh;padding:clamp(1.5rem,3vw,2.5rem) 20px}
.auth-page h1{font-size:1.5rem;font-weight:500;text-align:center;color:var(--ink);font-variation-settings:'SOFT' 80}
.auth-page>p.sub{text-align:center;color:var(--ink-soft);font-size:0.9rem;margin-bottom:1.5rem}
.register-form{background:var(--white-soft);border:1px solid oklch(from var(--clay) l c h / .08);border-radius:20px;max-width:680px;margin:0 auto;padding:clamp(1.5rem,3vw,2.5rem);box-shadow:0 12px 40px oklch(0 0 0 / .04)}
.register-form .flash-message{margin-bottom:1rem}
.register-form h2{font-family:'Fraunces',serif;font-size:1.1rem;font-weight:500;color:var(--clay);margin:1.5rem 0 1rem;padding-bottom:6px;border-bottom:1px solid oklch(from var(--clay) l c h / .08);font-variation-settings:'SOFT' 80}
.register-form .form-group{margin-bottom:1rem}
.register-form label{font-size:0.82rem}
.register-form input,.register-form select,.register-form textarea{background:var(--cream-light)}
.register-form .form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.register-form .field-hint{font-size:0.72rem;color:var(--ink-soft);margin-top:2px;opacity:.7}
.register-form .btn{width:100%;justify-content:center;margin-top:1rem;padding:0.75rem}
.auth-footer{text-align:center;margin-top:1.5rem;font-size:0.85rem}
.auth-footer a{color:var(--clay);text-decoration:none}
.auth-footer a:hover{text-decoration:underline}
.auth-back{display:block;text-align:center;margin-top:1rem;font-size:0.85rem;color:var(--ink-soft);text-decoration:none}
.auth-back:hover{color:var(--clay)}
@media(max-width:600px){.register-form .form-row{grid-template-columns:1fr;gap:0}}
</style>
</head>
<body class="auth-page">
    <h1>RSGMS</h1>
    <p class="sub">Register your savings group</p>

    <div class="register-form">
        <?php if ($message): ?>
        <div class="flash-message success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="flash-message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <h2>Group information</h2>
            <div class="form-group">
                <label>Group name *</label>
                <input type="text" name="group_name" required value="<?php echo htmlspecialchars($formData['group_name'] ?? $_POST['group_name'] ?? ''); ?>">
                <div class="field-hint">A unique group code will be auto-generated.</div>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"><?php echo htmlspecialchars($formData['description'] ?? $_POST['description'] ?? ''); ?></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Interest rate (%) *</label>
                    <input type="number" name="interest_rate" step="0.01" value="<?php echo htmlspecialchars($formData['interest_rate'] ?? $_POST['interest_rate'] ?? '10'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Penalty rate (%) *</label>
                    <input type="number" name="penalty_rate" step="0.01" value="<?php echo htmlspecialchars($formData['penalty_rate'] ?? $_POST['penalty_rate'] ?? '5'); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Meeting day</label>
                    <select name="meeting_day"><?php foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $d): ?><option value="<?php echo $d; ?>" <?php echo (($formData['meeting_day'] ?? $_POST['meeting_day'] ?? '') === $d) ? 'selected' : ''; ?>><?php echo $d; ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-group">
                    <label>Regular contribution (K)</label>
                    <input type="number" name="contribution_amount" step="0.01" value="<?php echo htmlspecialchars($formData['contribution_amount'] ?? $_POST['contribution_amount'] ?? '0'); ?>">
                </div>
            </div>

            <h2>Administrator account</h2>
            <div class="form-group">
                <label>Full name *</label>
                <input type="text" name="admin_full_name" required value="<?php echo htmlspecialchars($formData['admin_full_name'] ?? $_POST['admin_full_name'] ?? ''); ?>">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Username *</label>
                    <input type="text" name="admin_username" required value="<?php echo htmlspecialchars($formData['admin_username'] ?? $_POST['admin_username'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="admin_password" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="admin_email" value="<?php echo htmlspecialchars($formData['admin_email'] ?? $_POST['admin_email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="admin_phone" value="<?php echo htmlspecialchars($formData['admin_phone'] ?? $_POST['admin_phone'] ?? ''); ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Register group</button>
        </form>

        <a href="index.php" class="auth-back">← Back to home</a>
    </div>
</body>
</html>
