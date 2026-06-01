var Toast = (function () {
  var container = null;
  var TOAST_DURATION = 5000;
  var CELEBRATION_DURATION = 7000;
  var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function ensureContainer() {
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      container.setAttribute('aria-live', 'polite');
      document.body.appendChild(container);
    }
    return container;
  }

  function show(message, type, opts) {
    opts = opts || {};
    type = type || 'info';
    var isCelebration = opts.celebrate || false;
    var duration = isCelebration ? CELEBRATION_DURATION : TOAST_DURATION;
    var c = ensureContainer();

    var el = document.createElement('div');
    el.className = 'toast toast--' + type + (isCelebration ? ' toast--celebrate' : '');
    el.innerHTML =
      '<span class="toast-icon">' + iconHTML(type, isCelebration) + '</span>' +
      '<div class="toast-body"><p>' + escapeHtml(message) + '</p></div>' +
      '<button class="toast-close" aria-label="Dismiss">&times;</button>' +
      '<div class="toast-progress"></div>';

    c.appendChild(el);

    var closeBtn = el.querySelector('.toast-close');
    var progress = el.querySelector('.toast-progress');

    function dismiss() {
      if (el.classList.contains('is-hiding')) return;
      el.classList.add('is-hiding');
      el.classList.remove('is-visible');
      setTimeout(function () {
        if (el.parentNode) el.parentNode.removeChild(el);
        if (isCelebration) confetti.clear();
      }, 400);
    }

    closeBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      dismiss();
    });

    el.addEventListener('click', function (e) {
      if (e.target === el || e.target.closest('.toast-body')) dismiss();
    });

    progress.style.animationDuration = duration + 'ms';

    if (!prefersReducedMotion) {
      requestAnimationFrame(function () { el.classList.add('is-visible'); });
    } else {
      el.classList.add('is-visible');
      el.style.transform = 'none';
      el.style.opacity = '1';
    }

    if (isCelebration && !prefersReducedMotion) {
      setTimeout(function () { confetti.fire(); }, 200);
    }

    setTimeout(dismiss, duration);

    return el;
  }

  function iconHTML(type, isCelebration) {
    if (isCelebration) {
      return '<svg class="toast-checkmark" viewBox="0 0 52 52" width="24" height="24"><circle class="checkmark-circle" cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="4"/><path class="checkmark-check" d="M14 27l7 7 16-16" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    if (type === 'success') {
      return '<svg class="toast-checkmark" viewBox="0 0 52 52" width="20" height="20"><circle class="checkmark-circle" cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="4"/><path class="checkmark-check" d="M14 27l7 7 16-16" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    var ICONS = {
      error:   '<svg class="toast-xmark" viewBox="0 0 52 52" width="20" height="20"><circle cx="26" cy="26" r="24" fill="none" stroke="currentColor" stroke-width="4"/><path d="M18 18l16 16M34 18l-16 16" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>',
      warning: '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2L2 22h20L12 2zM12 10v4M12 18h0"/></svg>',
      info:    '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 12l0-4M12 16l0-0.01"/></svg>',
    };
    return ICONS[type] || ICONS.info;
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  document.addEventListener('DOMContentLoaded', function () {
    var flashEl = document.getElementById('flash-data');
    if (flashEl) {
      try {
        var messages = JSON.parse(flashEl.getAttribute('data-flash') || '[]');
        messages.forEach(function (msg) {
          show(msg.message, msg.type, { celebrate: msg.celebrate || false });
        });
      } catch (e) {}
    }
  });

  return { show: show };
})();

var confetti = (function () {
  var canvas, ctx, particles, animId;
  var COLORS = ['#10b981', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4'];

  function createCanvas() {
    if (canvas) return;
    canvas = document.createElement('canvas');
    canvas.className = 'confetti-canvas';
    canvas.setAttribute('aria-hidden', 'true');
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    document.body.appendChild(canvas);
    ctx = canvas.getContext('2d');
    window.addEventListener('resize', function () {
      canvas.width = window.innerWidth;
      canvas.height = window.innerHeight;
    });
  }

  function fire() {
    createCanvas();
    particles = [];
    for (var i = 0; i < 80; i++) {
      particles.push({
        x: canvas.width * 0.2 + Math.random() * canvas.width * 0.6,
        y: -10 - Math.random() * 200,
        w: 6 + Math.random() * 6,
        h: 4 + Math.random() * 4,
        vx: (Math.random() - 0.5) * 4,
        vy: 2 + Math.random() * 4,
        color: COLORS[Math.floor(Math.random() * COLORS.length)],
        rotation: Math.random() * 360,
        rotSpeed: (Math.random() - 0.5) * 8,
        opacity: 1,
      });
    }
    animate();
  }

  function animate() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    var alive = false;
    for (var i = 0; i < particles.length; i++) {
      var p = particles[i];
      if (p.opacity <= 0) continue;
      alive = true;
      p.x += p.vx;
      p.y += p.vy;
      p.vy += 0.06;
      p.rotation += p.rotSpeed;
      p.opacity -= 0.003;
      ctx.save();
      ctx.translate(p.x, p.y);
      ctx.rotate(p.rotation * Math.PI / 180);
      ctx.globalAlpha = Math.max(0, p.opacity);
      ctx.fillStyle = p.color;
      ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
      ctx.restore();
    }
    if (alive) {
      animId = requestAnimationFrame(animate);
    } else {
      clear();
    }
  }

  function clear() {
    if (animId) { cancelAnimationFrame(animId); animId = null; }
    if (canvas) {
      ctx.clearRect(0, 0, canvas.width, canvas.height);
      canvas.style.display = 'none';
    }
    particles = [];
  }

  return { fire: fire, clear: clear };
})();
