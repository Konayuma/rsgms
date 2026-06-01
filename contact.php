<?php
session_start();
$current_page = 'contact';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contact — RSGMS</title>
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

.contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:clamp(2rem,3vw,3rem);align-items:start}
.contact-info{display:flex;flex-direction:column;gap:1.5rem}
.contact-item{display:flex;gap:1rem;align-items:flex-start}
.contact-item .ci-icon{
  width:44px;height:44px;display:flex;align-items:center;justify-content:center;
  background:oklch(from var(--clay) l c h / .1);border-radius:12px;
  color:var(--clay);font-size:1.1rem;flex-shrink:0;
}
.contact-item h3{font-size:1rem;font-weight:500;color:var(--ink);margin-bottom:0.25rem;font-variation-settings:'SOFT' 80}
.contact-item p{color:var(--ink-soft);font-size:0.9rem;line-height:1.6}

.contact-form{
  background:var(--cream);border-radius:16px;padding:2rem;
}
.contact-form h3{font-size:1.2rem;font-weight:500;margin-bottom:1.25rem;font-variation-settings:'SOFT' 80}
.form-group{margin-bottom:1rem}
.form-group label{display:block;font-size:0.85rem;font-weight:500;color:var(--ink);margin-bottom:0.35rem}
.form-group input,.form-group textarea{
  width:100%;padding:0.75rem 1rem;
  font-family:'Sora',sans-serif;font-size:0.9rem;
  border:1px solid oklch(from var(--clay) l c h / .15);
  border-radius:10px;background:var(--cream-light);
  transition:border-color .2s,box-shadow .2s;
  color:var(--ink);
}
.form-group input:focus,.form-group textarea:focus{
  outline:none;border-color:var(--clay);
  box-shadow:0 0 0 3px oklch(from var(--clay) l c h / .1);
}
.form-group textarea{resize:vertical;min-height:120px}
.btn{
  display:inline-flex;align-items:center;gap:0.6rem;
  padding:0.8rem 1.75rem;border-radius:10px;
  text-decoration:none;font-weight:500;font-size:0.9rem;
  transition:all .3s cubic-bezier(.22,1,.36,1);
  border:none;cursor:pointer;font-family:'Sora',sans-serif;
}
.btn-primary{background:var(--clay);color:var(--cream)}
.btn-primary:hover{background:var(--clay-dark);box-shadow:0 8px 24px oklch(from var(--clay) l c h / .3)}

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
  .contact-grid{grid-template-columns:1fr}
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
    <a href="features.php">Features</a>
    <a href="contact.php" class="active">Contact</a>
    <a href="login.php" class="nav-cta">Login</a>
  </div>
</nav>

<main class="page">
  <div class="page-header">
    <div class="page-label">Contact</div>
    <h1>Get in touch</h1>
  </div>

  <div class="contact-grid">
    <div class="contact-info">
      <div class="contact-item">
        <div class="ci-icon"><i class="fa-solid fa-location-dot"></i></div>
        <div><h3>Address</h3><p>Zambia University College of Technology<br>Ndola, Zambia</p></div>
      </div>
      <div class="contact-item">
        <div class="ci-icon"><i class="fa-solid fa-phone"></i></div>
        <div><h3>Phone</h3><p>+260 XXX XXX XXX</p></div>
      </div>
      <div class="contact-item">
        <div class="ci-icon"><i class="fa-solid fa-envelope"></i></div>
        <div><h3>Email</h3><p>info@rsgms.zm<br>support@rsgms.zm</p></div>
      </div>
      <div class="contact-item">
        <div class="ci-icon"><i class="fa-solid fa-clock"></i></div>
        <div><h3>Office Hours</h3><p>Monday — Friday: 8:00 AM – 5:00 PM<br>Saturday: 9:00 AM – 1:00 PM</p></div>
      </div>
    </div>

    <div class="contact-form">
      <h3>Send a message</h3>
      <form method="POST">
        <div class="form-group"><label>Your name</label><input type="text" required></div>
        <div class="form-group"><label>Email address</label><input type="email" required></div>
        <div class="form-group"><label>Subject</label><input type="text" required></div>
        <div class="form-group"><label>Message</label><textarea rows="5" required></textarea></div>
        <button type="submit" class="btn btn-primary">Send message</button>
      </form>
    </div>
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
