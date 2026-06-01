<?php
session_start();
$current_page = 'features';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Features — RSGMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght,SOFT@9..144,300..700,0..100&family=Sora:wght@300..600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/icons.css">
<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
  --clay:oklch(0.48 0.09 35);
  --clay-dark:oklch(0.35 0.07 33);
  --cream:oklch(0.94 0.025 70);
  --cream-light:oklch(0.97 0.015 75);
  --ink:oklch(0.18 0.02 30);
  --ink-soft:oklch(0.35 0.025 35);
  --gold:oklch(0.70 0.10 75);
  --gold-light:oklch(0.80 0.08 80);
}
html{scroll-behavior:smooth}
body{
  font-family:'Sora',sans-serif;font-weight:300;
  color:var(--ink);background:var(--cream-light);
  line-height:1.6;-webkit-font-smoothing:antialiased;
}
h1,h2,h3{font-family:'Fraunces',serif;font-weight:600;font-variation-settings:'SOFT' 85;line-height:1.1}

nav{
  position:fixed;top:0;left:0;right:0;z-index:100;
  padding:clamp(0.75rem,2vw,1.25rem) clamp(1rem,4vw,3rem);
  display:flex;justify-content:space-between;align-items:center;
  background:var(--cream);box-shadow:0 1px 0 oklch(0 0 0 / .06);
}
.nav-logo{font-family:'Fraunces',serif;font-weight:600;font-size:clamp(1.1rem,2vw,1.4rem);color:var(--ink);text-decoration:none;letter-spacing:-.02em;font-variation-settings:'SOFT' 85}
.nav-links{display:flex;gap:clamp(0.5rem,1.5vw,1.5rem);align-items:center}
.nav-links a{
  text-decoration:none;color:var(--ink-soft);font-size:clamp(0.8rem,1vw,0.9rem);font-weight:400;
  padding:0.4rem 0.8rem;border-radius:6px;transition:color .2s,background .2s;
}
.nav-links a:hover{color:var(--clay);background:oklch(0 0 0 / .04)}
.nav-links a.active{color:var(--clay);font-weight:500}
.nav-cta{
  background:var(--clay) !important;color:var(--cream) !important;
  padding:0.5rem 1.25rem !important;border-radius:8px !important;
  font-weight:500 !important;
}
.nav-cta:hover{background:var(--clay-dark) !important}

.page{max-width:1280px;margin:0 auto;padding:6rem clamp(1rem,5vw,3rem) 3rem}
.page-header{margin-bottom:3rem}
.page-header h1{
  font-size:clamp(2rem,4vw,3.2rem);font-weight:500;letter-spacing:-.025em;
  font-variation-settings:'SOFT' 70;color:var(--ink);
}
.page-header .page-label{
  font-size:clamp(0.7rem,0.85vw,0.8rem);font-weight:500;text-transform:uppercase;letter-spacing:.14em;
  color:var(--clay);margin-bottom:0.5rem;
}
.page-header p{color:var(--ink-soft);font-size:clamp(0.95rem,1.1vw,1.1rem);margin-top:0.75rem;max-width:48ch}

.features-grid{
  display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));
  gap:clamp(1rem,2vw,1.5rem);
}
.feature-card{
  background:var(--cream);border-radius:16px;padding:2rem;
  transition:transform .3s cubic-bezier(.22,1,.36,1),box-shadow .3s;
}
.feature-card:hover{transform:translateY(-4px);box-shadow:0 12px 32px oklch(from var(--clay) l c h / .1)}
.feature-card .card-icon{
  font-size:1.5rem;color:var(--clay);margin-bottom:1rem;
  width:44px;height:44px;display:flex;align-items:center;justify-content:center;
  background:oklch(from var(--clay) l c h / .1);border-radius:12px;
}
.feature-card h3{
  font-size:1.15rem;font-weight:500;color:var(--ink);margin-bottom:0.5rem;
  font-variation-settings:'SOFT' 80;
}
.feature-card p{color:var(--ink-soft);font-size:0.9rem;line-height:1.7}

footer{
  background:var(--ink);color:oklch(0.7 0.02 70);
  padding:clamp(2rem,3vw,3rem) clamp(1rem,5vw,3rem);
  margin-top:clamp(3rem,5vw,5rem);
}
.footer-grid{
  max-width:1280px;margin:0 auto;
  display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:2rem;
}
.footer-brand{font-family:'Fraunces',serif;font-size:1.15rem;color:var(--cream);margin-bottom:0.5rem}
.footer-desc{font-size:0.85rem;opacity:.6;line-height:1.6;max-width:30ch}
.footer-col h4{font-family:'Fraunces',serif;font-size:0.95rem;color:var(--cream);margin-bottom:0.75rem}
.footer-col a{display:block;color:oklch(0.6 0.02 70);text-decoration:none;font-size:0.85rem;padding:0.2rem 0;transition:color .2s}
.footer-col a:hover{color:var(--gold-light)}
.footer-bottom{
  max-width:1280px;margin:2rem auto 0;padding-top:1.5rem;
  border-top:1px solid oklch(1 0 0 / .06);
  font-size:0.8rem;opacity:.5;text-align:center;
}
@media(max-width:860px){
  .features-grid{grid-template-columns:1fr}
  .footer-grid{grid-template-columns:1fr 1fr;gap:1.5rem}
  .nav-links a{font-size:0.75rem;padding:0.3rem 0.5rem}
}
@media(max-width:480px){
  .footer-grid{grid-template-columns:1fr;text-align:center}
  .footer-desc{margin-inline:auto}
}
</style>
</head>
<body>

<nav>
  <a href="index.php" class="nav-logo">RSGMS</a>
  <div class="nav-links">
    <a href="index.php">Home</a>
    <a href="about.php">About</a>
    <a href="features.php" class="active">Features</a>
    <a href="contact.php">Contact</a>
    <a href="login.php" class="nav-cta">Login</a>
  </div>
</nav>

<main class="page">
  <div class="page-header">
    <div class="page-label">Features</div>
    <h1>Built for the way<br>groups actually work</h1>
    <p>Every feature designed for low-bandwidth, high-trust environments — collective decision-making, transparent records, and zero friction.</p>
  </div>

  <div class="features-grid">
    <div class="feature-card"><div class="card-icon"><i class="fa-solid fa-sack-dollar"></i></div><h3>Savings Management</h3><p>Complete savings contribution recording and automated balance calculation for every group member with full transaction history.</p></div>
    <div class="feature-card"><div class="card-icon"><i class="fa-solid fa-chart-line"></i></div><h3>Loan Management</h3><p>Complete loan lifecycle management from application through approval, disbursement, repayment tracking, and automated interest calculation.</p></div>
    <div class="feature-card"><div class="card-icon"><i class="fa-solid fa-users"></i></div><h3>Member Management</h3><p>Register members, assign roles, and provide real-time access to individual savings and loan positions.</p></div>
    <div class="feature-card"><div class="card-icon"><i class="fa-solid fa-file-lines"></i></div><h3>Financial Reporting</h3><p>Generate group financial statements, member summaries, loan portfolio reports, and meeting preparation documents automatically.</p></div>
    <div class="feature-card"><div class="card-icon"><i class="fa-solid fa-bell"></i></div><h3>Automated Notifications</h3><p>Send repayment reminders, meeting alerts, and transaction confirmations to all group members.</p></div>
    <div class="feature-card"><div class="card-icon"><i class="fa-solid fa-shield-halved"></i></div><h3>Role-Based Access Control</h3><p>Secure access with different permission levels for administrators, loan officers, and members.</p></div>
    <div class="feature-card"><div class="card-icon"><i class="fa-solid fa-mobile-screen-button"></i></div><h3>Mobile Friendly</h3><p>Access the system from any internet-capable device including basic smartphones.</p></div>
    <div class="feature-card"><div class="card-icon"><i class="fa-solid fa-chart-column"></i></div><h3>Real-Time Dashboards</h3><p>Personalized dashboards showing savings balances, loan positions, and transaction history.</p></div>
    <div class="feature-card"><div class="card-icon"><i class="fa-solid fa-calendar-days"></i></div><h3>Meeting Management</h3><p>Track meeting attendance, minutes, and financial activities during group meetings.</p></div>
  </div>
</main>

<footer>
  <div class="footer-grid">
    <div>
      <div class="footer-brand">RSGMS</div>
      <p class="footer-desc">A Rural Savings Group Management System developed at Zambia University College of Technology.</p>
    </div>
    <div class="footer-col">
      <h4>Platform</h4>
      <a href="features.php">Features</a>
      <a href="about.php">About</a>
      <a href="register.php">Start a group</a>
    </div>
    <div class="footer-col">
      <h4>Support</h4>
      <a href="contact.php">Contact</a>
      <a href="login.php">Member login</a>
    </div>
    <div class="footer-col">
      <h4>Legal</h4>
      <a href="about.php">Privacy</a>
      <a href="about.php">Terms</a>
    </div>
  </div>
  <div class="footer-bottom">© 2026 RSGMS — Zambia University College of Technology</div>
</footer>

</body>
</html>
