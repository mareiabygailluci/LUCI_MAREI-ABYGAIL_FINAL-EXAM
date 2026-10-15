/* ============================================================
   LOST2FOUND — UI helpers (no external dependencies)
   ============================================================ */
(function () {
  'use strict';

  /* ---------- Sidebar (mobile) ---------- */
  document.addEventListener('click', function (e) {
    var toggle = e.target.closest('[data-toggle="nav"]');
    if (toggle) {
      document.body.classList.toggle('nav-open');
      return;
    }
    // Close the mobile nav when tapping outside of it
    if (document.body.classList.contains('nav-open') && !e.target.closest('.sidebar')) {
      document.body.classList.remove('nav-open');
    }
    // Close any open dropdown menu when clicking elsewhere
    document.querySelectorAll('details.menu[open]').forEach(function (m) {
      if (!m.contains(e.target)) m.removeAttribute('open');
    });
  });

  document.querySelectorAll('.nav a').forEach(function (a) {
    a.addEventListener('click', function () {
      document.body.classList.remove('nav-open');
    });
  });

  /* ---------- Modals ---------- */
  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-modal-open]');
    if (opener) {
      e.preventDefault();
      var target = document.querySelector(opener.getAttribute('data-modal-open'));
      if (target) target.classList.add('open');
      return;
    }
    if (e.target.closest('[data-modal-close]')) {
      var modal = e.target.closest('.modal');
      if (modal) modal.classList.remove('open');
    }
  });

  /* ---------- Confirm modal (links + forms with data-confirm) ---------- */
  var confirmModal = document.getElementById('confirmModal');
  if (confirmModal) {
    var confirmMsg = document.getElementById('confirmMsg');
    var confirmBtn = document.getElementById('confirmAction');

    document.addEventListener('click', function (e) {
      var el = e.target.closest('[data-confirm]');
      if (!el) return;
      e.preventDefault();

      if (confirmMsg) confirmMsg.textContent = el.getAttribute('data-confirm') || 'Are you sure?';

      /* Positive actions (data-confirm-tone="success") get a calm green confirm;
         destructive ones (reject/delete) stay red. */
      var tone = el.getAttribute('data-confirm-tone') === 'success';
      if (confirmBtn) confirmBtn.className = tone ? 'btn btn-success' : 'btn btn-danger';
      confirmModal.classList.toggle('tone-success', tone);

      var form = el.closest('form');
      if (form && confirmBtn) {
        confirmBtn.removeAttribute('href');
        confirmBtn.addEventListener('click', function handler(ev) {
          ev.preventDefault();
          confirmBtn.removeEventListener('click', handler);
          confirmModal.classList.remove('open');
          form.submit();
        });
      } else if (confirmBtn) {
        confirmBtn.setAttribute('href', el.getAttribute('href') || '#');
      }

      confirmModal.classList.add('open');
    });
  }

  /* ---------- Photo upload preview ---------- */
  document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      var box = document.getElementById(input.getAttribute('data-preview'));
      if (!box) return;
      var img = box.querySelector('img');
      if (input.files && input.files[0] && img) {
        img.src = URL.createObjectURL(input.files[0]);
        box.classList.add('show');
      } else {
        box.classList.remove('show');
      }
    });
  });

  /* ---------- Flash alerts: dismiss + gentle auto-hide ---------- */
  document.querySelectorAll('.alert').forEach(function (alert) {
    var close = alert.querySelector('.close');
    if (close) close.addEventListener('click', function () { alert.remove(); });
    setTimeout(function () {
      alert.style.transition = 'opacity .4s ease';
      alert.style.opacity = '0';
      setTimeout(function () { alert.remove(); }, 400);
    }, 6000);
  });

  /* ---------- Clickable cards ([data-href]) ---------- */
  document.addEventListener('click', function (e) {
    var card = e.target.closest('[data-href]');
    if (!card) return;
    if (e.target.closest('a, button, input, select, textarea, label, form')) return;
    window.location.href = card.getAttribute('data-href');
  });
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    var card = e.target.closest ? e.target.closest('[data-href]') : null;
    if (card && card === e.target) window.location.href = card.getAttribute('data-href');
  });
})();
