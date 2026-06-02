var Loading = (function () {
  function showSpinner(container, msg) {
    if (typeof container === 'string') container = document.querySelector(container);
    if (!container) return;
    container.dataset.loadingOrigHTML = container.innerHTML;
    msg = msg || 'Loading\u2026';
    container.innerHTML =
      '<div class="loading-spinner-wrap"><div class="loading-spinner loading-spinner-lg"></div><span>' + escapeHtml(msg) + '</span></div>';
  }

  function hideSpinner(container) {
    if (typeof container === 'string') container = document.querySelector(container);
    if (!container || !container.dataset.loadingOrigHTML) return;
    container.innerHTML = container.dataset.loadingOrigHTML;
    delete container.dataset.loadingOrigHTML;
  }

  function setButtonLoading(btn) {
    if (typeof btn === 'string') btn = document.querySelector(btn);
    if (!btn) return;
    if (btn.dataset.loadingOrigText !== undefined) return;
    btn.dataset.loadingOrigText = btn.innerHTML;
    btn.disabled = true;
    btn.classList.add('btn-loading');
    btn.innerHTML = '<span class="loading-spinner"></span><span class="btn-text">' + btn.textContent.trim() + '</span>';
  }

  function unsetButtonLoading(btn) {
    if (typeof btn === 'string') btn = document.querySelector(btn);
    if (!btn || !btn.dataset.loadingOrigText) return;
    btn.disabled = false;
    btn.classList.remove('btn-loading');
    btn.innerHTML = btn.dataset.loadingOrigText;
    delete btn.dataset.loadingOrigText;
  }

  function showSkeleton(container, opts) {
    if (typeof container === 'string') container = document.querySelector(container);
    if (!container) return;
    opts = opts || {};
    var lines = opts.lines || 4;
    var title = opts.title !== false;
    container.dataset.loadingOrigHTML = container.innerHTML;
    var html = '';
    if (title) html += '<div class="skeleton skeleton-title"></div>';
    for (var i = 0; i < lines; i++) {
      html += '<div class="skeleton skeleton-line"></div>';
    }
    if (opts.card) html = '<div class="skeleton skeleton-card"></div>';
    container.innerHTML = html;
  }

  function hideSkeleton(container) {
    hideSpinner(container);
  }

  function errorState(container, opts) {
    if (typeof container === 'string') container = document.querySelector(container);
    if (!container) return;
    opts = opts || {};
    var msg = opts.message || 'Something went wrong.';
    var retryText = opts.retryText || 'Retry';
    var onRetry = opts.onRetry || null;
    var html =
      '<div class="error-state">' +
      '<div class="error-state-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>' +
      '<div class="error-state-title">' + escapeHtml(msg) + '</div>' +
      (opts.detail ? '<div class="error-state-text">' + escapeHtml(opts.detail) + '</div>' : '') +
      (onRetry ? '<button type="button" class="btn btn-secondary error-state-action retry-btn">' + escapeHtml(retryText) + '</button>' : '') +
      '</div>';
    container.innerHTML = html;
    if (onRetry) {
      container.querySelector('.retry-btn').addEventListener('click', onRetry);
    }
  }

  function escapeHtml(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
  }

  function initForms() {
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (form.tagName !== 'FORM') return;
      var btn = form.querySelector('[type="submit"]');
      if (btn && !btn.disabled) {
        // Small delay to let form validation run first
        setTimeout(function () { setButtonLoading(btn); }, 50);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Disable buttons on any form submit to prevent double-send
    document.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function () {
        var btn = form.querySelector('[type="submit"]');
        if (btn && !btn.disabled) {
          setTimeout(function () { setButtonLoading(btn); }, 50);
        }
      });
    });

    // Clipboard fallback
    document.querySelectorAll('[data-copy]').forEach(function (btn) {
      if (!navigator.clipboard) {
        btn.style.display = 'none';
      }
    });
  });

  return {
    showSpinner: showSpinner,
    hideSpinner: hideSpinner,
    setButtonLoading: setButtonLoading,
    unsetButtonLoading: unsetButtonLoading,
    showSkeleton: showSkeleton,
    hideSkeleton: hideSkeleton,
    errorState: errorState,
    init: initForms,
  };
})();
