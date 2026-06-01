<?php
session_start();
$is_logged_in = isset($_SESSION['user_id']);
$current_page = 'index';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>RSGMS — Rural Savings Group Management</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght,SOFT@9..144,300..700,0..100&family=Sora:wght@300..600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/icons.css">

<style>
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
:root{
  --clay:oklch(0.48 0.09 35);
  --clay-dark:oklch(0.35 0.07 33);
  --clay-light:oklch(0.62 0.10 38);
  --cream:oklch(0.94 0.025 70);
  --cream-light:oklch(0.97 0.015 75);
  --ink:oklch(0.18 0.02 30);
  --ink-soft:oklch(0.35 0.025 35);
  --gold:oklch(0.70 0.10 75);
  --gold-light:oklch(0.80 0.08 80);
  --moss:oklch(0.40 0.06 140);
  --sand:oklch(0.85 0.03 70);
  --white-soft:oklch(0.99 0.005 75);
}
html{scroll-behavior:smooth}
body{
  font-family:'Sora',sans-serif;
  font-weight:300;
  color:var(--ink);
  background:var(--cream-light);
  line-height:1.6;
  -webkit-font-smoothing:antialiased;
}

h1,h2,h3,h4{font-family:'Fraunces',serif;font-weight:600;font-variation-settings:'SOFT' 85;line-height:1.1}

/* nav */
nav{
  position:fixed;top:0;left:0;right:0;z-index:100;
  padding:clamp(0.75rem,2vw,1.25rem) clamp(1rem,4vw,3rem);
  display:flex;justify-content:space-between;align-items:center;
  transition:background .4s cubic-bezier(.22,1,.36,1),padding .3s ease;
  background:transparent;
}
nav.scrolled{background:var(--cream);box-shadow:0 1px 0 oklch(0 0 0 / .06);padding-block:clamp(0.5rem,1.5vw,0.85rem)}
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
  font-weight:500 !important;transition:background .25s,transform .2s !important;
}
.nav-cta:hover{background:var(--clay-dark) !important;transform:translateY(-1px)}

/* hero */
.hero{
  min-height:100vh;display:flex;align-items:center;
  padding:6rem clamp(1rem,5vw,4rem) 4rem;
  position:relative;overflow:hidden;
  background:var(--cream-light);
}
.hero-grid{
  display:grid;grid-template-columns:1fr 1fr;gap:3rem;
  max-width:1280px;width:100%;margin:0 auto;align-items:center;
}
.hero-text{position:relative;z-index:2}
.hero-badge{
  display:inline-flex;align-items:center;gap:0.5rem;
  font-size:clamp(0.7rem,0.9vw,0.8rem);font-weight:500;
  color:var(--clay);text-transform:uppercase;letter-spacing:.12em;
  background:oklch(from var(--clay) l c h / .12);padding:0.4rem 1rem;border-radius:100px;
  margin-bottom:clamp(1rem,2vw,1.5rem);
}
.hero h1{
  font-size:clamp(2.4rem,6.5vw,5rem);
  font-weight:500;letter-spacing:-.03em;
  line-height:1.05;color:var(--ink);
  font-variation-settings:'SOFT' 60;
}
.hero h1 em{font-style:italic;font-variation-settings:'SOFT' 100;color:var(--clay)}
.hero p{
  font-size:clamp(0.95rem,1.2vw,1.15rem);
  color:var(--ink-soft);max-width:44ch;
  margin-top:clamp(1rem,2vw,1.75rem);line-height:1.7;
}
.hero-actions{
  display:flex;gap:1rem;flex-wrap:wrap;
  margin-top:clamp(1.5rem,3vw,2.5rem);
}
.btn{
  display:inline-flex;align-items:center;gap:0.6rem;
  padding:0.8rem 1.75rem;border-radius:10px;
  text-decoration:none;font-weight:500;font-size:clamp(0.85rem,1vw,0.95rem);
  transition:all .3s cubic-bezier(.22,1,.36,1);
  border:none;cursor:pointer;
}
.btn-primary{background:var(--clay);color:var(--cream)}
.btn-primary:hover{background:var(--clay-dark);transform:translateY(-2px);box-shadow:0 8px 24px oklch(from var(--clay) l c h / .3)}
.btn-secondary{background:oklch(from var(--clay) l c h / .1);color:var(--clay)}
.btn-secondary:hover{background:oklch(from var(--clay) l c h / .18);transform:translateY(-2px)}
.btn-ghost{color:var(--ink-soft);padding:0.8rem 1.25rem}
.btn-ghost:hover{color:var(--ink)}

/* hero decorative pattern */
.hero-graphic{
  position:relative;z-index:1;
  display:flex;align-items:center;justify-content:center;
  min-height:clamp(280px,35vw,480px);
}
.hero-pattern{
  width:clamp(260px,30vw,420px);height:clamp(260px,30vw,420px);
  position:relative;
  background:
    repeating-conic-gradient(
      from 45deg,
      var(--clay) 0deg 2deg,
      transparent 2deg 8deg,
      var(--clay-light) 8deg 10deg,
      transparent 10deg 16deg,
      var(--gold) 16deg 18deg,
      transparent 18deg 24deg
    );
  border-radius:38% 62% 42% 58% / 54% 36% 64% 46%;
  animation:morph 14s ease-in-out infinite;
  transform:rotate(12deg);
}
.hero-pattern::before{
  content:'';position:absolute;inset:-15%;
  background:
    repeating-linear-gradient(
      -45deg,
      transparent 0 12px,
      oklch(from var(--clay) l c h / .08) 12px 14px
    );
  border-radius:inherit;
  z-index:-1;
}
.hero-diamond{
  position:absolute;width:clamp(120px,14vw,200px);height:clamp(120px,14vw,200px);
  background:oklch(from var(--gold) l c h / .08);
  border:2px solid oklch(from var(--gold) l c h / .15);
  transform:rotate(45deg);border-radius:8px;
  bottom:-5%;right:-5%;
  animation:float 8s ease-in-out infinite;
}
.hero-diamond2{
  position:absolute;width:clamp(60px,7vw,100px);height:clamp(60px,7vw,100px);
  background:oklch(from var(--clay) l c h / .06);
  border:2px solid oklch(from var(--clay) l c h / .12);
  transform:rotate(45deg);border-radius:4px;
  top:5%;left:-8%;
  animation:float 10s ease-in-out infinite reverse;
}

/* hero overlay image (right side) */
.hero-overlay-image{
  position:absolute;
  left:50%;
  top:50%;
  transform:translate(-50%, -50%);
  width: clamp(260px, 40vw, 720px);
  max-width: 52%;
  z-index: 4;
  pointer-events: none;
  filter: drop-shadow(0 24px 40px rgba(15,23,42,0.12));
  object-fit: contain;
}

@keyframes morph{
  0%,100%{border-radius:38% 62% 42% 58% / 54% 36% 64% 46%}
  33%{border-radius:52% 48% 58% 42% / 42% 58% 42% 58%}
  66%{border-radius:60% 40% 32% 68% / 48% 52% 48% 52%}
}
@keyframes float{
  0%,100%{transform:rotate(45deg) translateY(0)}
  50%{transform:rotate(45deg) translateY(-12px)}
}

/* trust bar */
.trust-bar{
  display:flex;align-items:center;justify-content:center;gap:clamp(1.5rem,3vw,3rem);flex-wrap:wrap;
  padding:clamp(1.5rem,2.5vw,2.5rem) clamp(1rem,4vw,3rem);
  background:var(--cream);border-block:1px solid oklch(from var(--clay) l c h / .08);
}
.trust-item{display:flex;align-items:center;gap:0.75rem;font-size:clamp(0.8rem,0.9vw,0.9rem);color:var(--ink-soft)}
.trust-dot{width:6px;height:6px;border-radius:50%;background:var(--clay);opacity:.5}

/* sections general */
.section{
  padding:clamp(3rem,6vw,6rem) clamp(1rem,5vw,3rem);
  max-width:1280px;margin:0 auto;
}
.section-header{margin-bottom:clamp(2rem,3vw,3.5rem)}
.section-label{
  font-size:clamp(0.7rem,0.85vw,0.8rem);font-weight:500;text-transform:uppercase;letter-spacing:.14em;
  color:var(--clay);margin-bottom:0.75rem;
}
.section h2{
  font-size:clamp(1.8rem,3.5vw,3rem);
  font-weight:500;letter-spacing:-.025em;
  color:var(--ink);max-width:20ch;
  font-variation-settings:'SOFT' 70;
}
.section h2 em{color:var(--clay);font-style:italic;font-variation-settings:'SOFT' 100}
.section-sub{
  font-size:clamp(0.9rem,1.1vw,1.05rem);color:var(--ink-soft);
  max-width:48ch;margin-top:0.75rem;line-height:1.7;
}

/* features */
.features{display:flex;flex-direction:column;gap:clamp(2rem,4vw,5rem)}
.feature-row{
  display:grid;grid-template-columns:1fr 1fr;gap:clamp(2rem,4vw,5rem);
  align-items:center;
}
.feature-row.reverse{direction:rtl}
.feature-row.reverse>*{direction:ltr}
.feature-num{
  font-family:'Fraunces',serif;
  font-size:clamp(3.5rem,6vw,6rem);font-weight:300;
  color:oklch(from var(--clay) l c h / .12);
  line-height:1;margin-bottom:-0.3em;
  font-variation-settings:'SOFT' 100;
}
.feature-row h3{
  font-size:clamp(1.3rem,2vw,1.8rem);
  font-weight:500;color:var(--ink);margin-bottom:0.75rem;
  font-variation-settings:'SOFT' 80;
}
.feature-row p{color:var(--ink-soft);line-height:1.7;font-size:clamp(0.9rem,1vw,1rem)}
.feature-visual{
  aspect-ratio:4/3;border-radius:18px;overflow:hidden;
  background:var(--cream);display:flex;align-items:center;justify-content:center;
  position:relative;
}
.feature-visual-inner{
  width:70%;height:70%;
  background:
    repeating-linear-gradient(
      45deg,
      var(--clay) 0 3px,
      transparent 3px 10px,
      var(--gold-light) 10px 13px,
      transparent 13px 20px
    );
  border-radius:24px;opacity:.3;
  transform:rotate(-6deg);
}
.feature-visual .shape-1{
  position:absolute;width:50%;height:30%;
  border:3px solid oklch(from var(--clay) l c h / .15);
  border-radius:12px;top:15%;right:10%;
  transform:rotate(8deg);
}
.feature-visual .shape-2{
  position:absolute;width:35%;height:45%;
  background:oklch(from var(--gold) l c h / .08);
  border-radius:60% 40% 50% 50%;bottom:10%;left:12%;
}

/* feature overlay image inside right visual (centered) */
.feature-overlay-image{
  position:absolute;
  left:50%;
  top:50%;
  transform:translate(-50%,-50%);
  width: clamp(360px, 55vw, 920px);
  max-width: 92%;
  height: auto;
  z-index: 6;
  pointer-events: none;
  object-fit: contain;
  filter: drop-shadow(0 24px 48px rgba(15,23,42,0.12));
}

/* Slightly smaller/nudged variant for visuals where we need to reveal more of the foreground */
.feature-overlay-image--small{
  width: clamp(260px, 36vw, 560px) !important;
  transform: translate(-50%,-50%) !important;
}

@media(max-width:860px){
  .feature-overlay-image{ display:none; }
}

/* stats */
.stats-banner{
  background:var(--clay);color:var(--cream);
  padding:clamp(2.5rem,4vw,4rem) clamp(1rem,5vw,3rem);
  margin-block:clamp(2rem,3vw,3rem);
}
.stats-grid{
  max-width:1280px;margin:0 auto;
  display:grid;grid-template-columns:repeat(4,1fr);gap:2rem;
}
.stat-item{text-align:center}
.stat-number{
  font-family:'Fraunces',serif;
  font-size:clamp(2.5rem,5vw,4.5rem);font-weight:400;
  line-height:1;margin-bottom:0.5rem;
  letter-spacing:-.03em;
}
.stat-label{font-size:clamp(0.8rem,0.9vw,0.95rem);opacity:.75;font-weight:400}
.stat-desc{font-size:clamp(0.7rem,0.75vw,0.8rem);opacity:.6;margin-top:0.25rem}

/* cta */
.cta-section{
  padding:clamp(3rem,6vw,6rem) clamp(1rem,5vw,3rem);
  text-align:center;
  max-width:720px;margin:0 auto;
}
.cta-section h2{
  font-size:clamp(1.8rem,3.5vw,3rem);
  font-weight:500;letter-spacing:-.025em;
  margin-bottom:1rem;
}
.cta-section p{color:var(--ink-soft);font-size:clamp(0.95rem,1.1vw,1.1rem);margin-bottom:2rem;line-height:1.7}
.cta-actions{display:flex;gap:1rem;justify-content:center;flex-wrap:wrap}

/* footer */
footer{
  background:var(--ink);color:oklch(0.7 0.02 70);
  padding:clamp(2rem,3vw,3rem) clamp(1rem,5vw,3rem);
  margin-top:clamp(2rem,3vw,3rem);
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

/* scroll-reveal */
.reveal{opacity:0;transform:translateY(28px);transition:opacity .7s cubic-bezier(.22,1,.36,1),transform .7s cubic-bezier(.22,1,.36,1)}
.reveal.visible{opacity:1;transform:translateY(0)}
.reveal-delay-1{transition-delay:.1s}
.reveal-delay-2{transition-delay:.2s}
.reveal-delay-3{transition-delay:.35s}

/* responsive */
@media(max-width:860px){
  .hero-grid{grid-template-columns:1fr;text-align:center}
  .hero p{margin-inline:auto}
  .hero-actions{justify-content:center}
  .hero-graphic{display:none}
  .feature-row{grid-template-columns:1fr}
  .feature-row.reverse{direction:ltr}
  .feature-row.reverse>*{direction:ltr}
  .feature-visual{aspect-ratio:3/2;order:-1}
  .stats-grid{grid-template-columns:repeat(2,1fr);gap:1.5rem}
  .footer-grid{grid-template-columns:1fr 1fr;gap:1.5rem}
  .trust-bar{justify-content:center;gap:1rem}
  nav{background:var(--cream);box-shadow:0 1px 0 oklch(0 0 0 / .06)}
}
@media(max-width:480px){
  .hero{padding-top:5rem}
  .stats-grid{grid-template-columns:1fr 1fr;gap:1rem}
  .footer-grid{grid-template-columns:1fr;text-align:center}
  .footer-desc{margin-inline:auto}
  .nav-links a{font-size:0.75rem;padding:0.3rem 0.5rem}
}
</style>
</head>
<body>

<nav id="navbar">
  <a href="/" class="nav-logo">RSGMS</a>
  <div class="nav-links">
    <a href="index.php" class="active">Home</a>
    <a href="about.php">About</a>
    <a href="features.php">Features</a>
    <a href="contact.php">Contact</a>
    <a href="login.php" class="nav-cta">Login</a>
  </div>
</nav>

<section class="hero">
  <div class="hero-grid">
    <div class="hero-text reveal">
      <div class="hero-badge">Financial inclusion for rural Zambia</div>
      <h1>Rural savings.<br><em>Digitized.</em></h1>
      <p>RSGMS brings real-time financial transparency, automated loan management, and streamlined reporting to community savings groups — no bank account required.</p>
      <div class="hero-actions">
        <a href="register.php" class="btn btn-primary">Start your group</a>
        <a href="login.php" class="btn btn-secondary">Sign in</a>
        <a href="features.php" class="btn btn-ghost">How it works →</a>
      </div>
    </div>
    <div class="hero-graphic reveal reveal-delay-2">
      <div class="hero-pattern"></div>
      <img src="assets/Frame%201%20(2).png" alt="Decorative" class="hero-overlay-image" aria-hidden="true">
      <div class="hero-diamond"></div>
      <div class="hero-diamond2"></div>
    </div>
  </div>
</section>

<div class="trust-bar">
  <span class="trust-item"><span class="trust-dot"></span> 500+ savings groups</span>
  <span class="trust-item"><span class="trust-dot"></span> 100% free to start</span>
  <span class="trust-item"><span class="trust-dot"></span> Works on any phone</span>
  <span class="trust-item"><span class="trust-dot"></span> Zambia-built</span>
</div>

<section class="section">
  <div class="section-header reveal">
    <div class="section-label">Platform</div>
    <h2>Everything a savings group needs.<br>Nothing it <em>doesn't.</em></h2>
    <p class="section-sub">Purpose-built for the way community groups actually operate — collective savings, rotating loans, transparent records, and zero bureaucracy.</p>
  </div>

  <div class="features">
    <div class="feature-row reveal">
      <div>
        <div class="feature-num">01</div>
        <h3>Savings tracking,<br>down to the kwacha</h3>
        <p>Every contribution recorded in real time. Members can check their balance from any phone. No more lost notebooks or disputed totals.</p>
      </div>
      <div class="feature-visual">
        <div class="feature-visual-inner"></div>
        <img src="assets/Frame%202.png" alt="" class="feature-overlay-image" aria-hidden="true">
        <div class="shape-1"></div>
        <div class="shape-2"></div>
      </div>
    </div>

    <div class="feature-row reverse reveal">
      <div>
        <div class="feature-num">02</div>
        <h3>Loans that <em>calculate themselves</em></h3>
        <p>Interest, repayment schedules, penalties — handled automatically. Members see exactly what they owe and when it's due. No guesswork, no arguments.</p>
      </div>
      <div class="feature-visual">
        <div class="feature-visual-inner" style="background:repeating-linear-gradient(-45deg,var(--gold) 0 3px,transparent 3px 10px,var(--clay-light) 10px 13px,transparent 13px 20px)"></div>
        <img src="assets/Frame%203.png" alt="" class="feature-overlay-image" aria-hidden="true">
        <div class="shape-1" style="border-color:oklch(from var(--gold) l c h / .2);width:60%;height:20%;top:20%;right:5%"></div>
        <div class="shape-2" style="background:oklch(from var(--clay) l c h / .1);width:40%;height:35%;bottom:15%;left:8%"></div>
      </div>
    </div>

    <div class="feature-row reveal">
      <div>
        <div class="feature-num">03</div>
        <h3>Reports in seconds,<br>not spreadsheets</h3>
        <p>Generate financial statements, member summaries, and loan portfolio reports at the tap of a button. Ready for audits and meeting day.</p>
      </div>
      <div class="feature-visual">
        <div class="feature-visual-inner" style="background:repeating-linear-gradient(45deg,var(--moss) 0 3px,transparent 3px 10px,var(--cream) 10px 13px,transparent 13px 20px)"></div>
        <img src="assets/Frame%204.png" alt="" class="feature-overlay-image feature-overlay-image--small" aria-hidden="true">
        <div class="shape-1" style="border-color:oklch(from var(--moss) l c h / .2);width:40%;height:40%;top:10%;left:8%"></div>
        <div class="shape-2" style="background:oklch(from var(--gold) l c h / .1);width:50%;height:25%;bottom:15%;right:5%;border-radius:8px;transform:rotate(-4deg)"></div>
      </div>
    </div>
  </div>
</section>

<div class="stats-banner">
  <div class="stats-grid">
    <div class="stat-item reveal"><div class="stat-number">85%</div><div class="stat-label">Fewer record errors</div><div class="stat-desc">Digital vs manual tracking</div></div>
    <div class="stat-item reveal reveal-delay-1"><div class="stat-number">70%</div><div class="stat-label">Less disputes</div><div class="stat-desc">Transparent for everyone</div></div>
    <div class="stat-item reveal reveal-delay-2"><div class="stat-number">60%</div><div class="stat-label">Time saved</div><div class="stat-desc">Administrative work</div></div>
    <div class="stat-item reveal reveal-delay-3"><div class="stat-number">50%</div><div class="stat-label">More engagement</div><div class="stat-desc">Members stay active</div></div>
  </div>
</div>

<section class="cta-section reveal">
  <h2>Ready to bring your group <em>online?</em></h2>
  <p>No setup costs, no training required. If your members have basic phones, you're ready. A group admin can register in under 5 minutes.</p>
  <div class="cta-actions">
    <a href="register.php" class="btn btn-primary">Register your group</a>
    <a href="signup.php" class="btn btn-secondary">Join as a member</a>
  </div>
</section>

<footer>
  <div class="footer-grid">
    <div>
      <div class="footer-brand">RSGMS</div>
      <p class="footer-desc">A Rural Savings Group Management System developed at Zambia University College of Technology, bringing digital financial inclusion to community savings groups across Zambia.</p>
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

<script>
// nav scroll effect
const nav = document.getElementById('navbar');
let ticking = false;
document.addEventListener('scroll', () => {
  if (!ticking) {
    requestAnimationFrame(() => {
      nav.classList.toggle('scrolled', window.scrollY > 60);
      ticking = false;
    });
    ticking = true;
  }
});

// scroll reveal
const obs = new IntersectionObserver((entries) => {
  entries.forEach(e => {
    if (e.isIntersecting) { e.target.classList.add('visible'); obs.unobserve(e.target); }
  });
}, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

document.querySelectorAll('.reveal').forEach(el => obs.observe(el));
</script>

</body>
</html>
