    </main>
  </div>
</div>

<div class="modal" id="confirmModal">
  <div class="modal-back" data-modal-close></div>
  <div class="modal-card">
    <h3><?= icon('alert') ?> Please confirm</h3>
    <p id="confirmMsg">Are you sure?</p>
    <div class="modal-foot">
      <button class="btn btn-outline" data-modal-close>Cancel</button>
      <a href="#" class="btn btn-danger" id="confirmAction">Yes, continue</a>
    </div>
  </div>
</div>

<?php if (is_admin()): ?>
<!-- ---------- Result modal (shows admin action results as a popup) ---------- -->
<div class="modal" id="resultModal">
  <div class="modal-back" data-modal-close></div>
  <div class="modal-card" style="max-width:420px;text-align:center">
    <div id="resultIcon" style="width:56px;height:56px;border-radius:50%;display:grid;place-items:center;margin:6px auto 12px;font-size:26px">✓</div>
    <h3 id="resultTitle" style="justify-content:center;color:var(--text)">Done</h3>
    <div id="resultBody" style="text-align:left;color:var(--text-soft);font-size:.9rem;margin-bottom:18px"></div>
    <div class="modal-foot" style="justify-content:center">
      <button class="btn btn-primary" type="button" data-modal-close>OK</button>
    </div>
  </div>
</div>

<script>
(function () {
  /* Turn any flash alerts rendered on this page into a popup modal. */
  var alerts = document.querySelectorAll('.content .alert');
  if (alerts.length) {
    var MAP = {
      success: { sym: '✓', color: 'var(--success)', title: 'Success!' },
      warn:    { sym: '!',  color: 'var(--warn)',    title: 'Warning' },
      danger:  { sym: '✕', color: 'var(--danger)',  title: 'Not completed' },
      info:    { sym: 'i',  color: 'var(--info)',    title: 'Heads up' }
    };
    var first = MAP.info, items = [];
    alerts.forEach(function (a) {
      var m = a.className.match(/alert-(success|warn|danger|info)/);
      var cfg = MAP[(m && m[1]) || 'info'];
      if (items.length === 0) first = cfg;
      var txt = (a.querySelector('span') || a).textContent.trim();
      items.push('<div style="display:flex;gap:9px;margin-bottom:8px">' +
        '<strong style="color:' + cfg.color + '">' + cfg.sym + '</strong>' +
        '<span>' + txt + '</span></div>');
    });
    var icon = document.getElementById('resultIcon');
    icon.textContent = first.sym;
    icon.style.background = 'var(--success-soft)';
    if (first === MAP.warn)    icon.style.background = 'var(--warn-soft)';
    if (first === MAP.danger)  icon.style.background = 'var(--danger-soft)';
    if (first === MAP.info)    icon.style.background = 'var(--info-soft)';
    icon.style.color = first.color;
    document.getElementById('resultTitle').textContent = first.title;
    document.getElementById('resultBody').innerHTML = items.join('');
    setTimeout(function () { document.getElementById('resultModal').classList.add('open'); }, 250);
  }
})();
</script>

<!-- ---------- Reject-with-remarks modal (admin only, shared across admin pages) ---------- -->
<div class="modal" id="rejectModal">
  <div class="modal-back" data-modal-close></div>
  <div class="modal-card" style="max-width:460px">
    <div class="menu-head" style="margin:0 0 10px;padding-left:0;border-bottom:1px solid var(--border)">
      <strong style="display:flex;align-items:center;gap:8px;color:var(--danger)"><?= icon('alert', 'i i-sm') ?> Reject registration — <span id="rejectName">applicant</span></strong>
      <span>The reason you write here is what the applicant will see when they check their status on the login page.</span>
    </div>
    <form method="post" action="<?= e(base_url('admin/users.php')) ?>" id="rejectForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reject">
      <input type="hidden" name="id" id="rejectId" value="0">

      <div class="field" style="margin-bottom:12px">
        <label>Reason <span class="req">*</span></label>
        <select class="input" id="rejectReason" name="remarks_select" required>
          <option value="">— Select a reason —</option>
          <option value="The uploaded proof of residency is unreadable or incomplete. Please upload a clearer photo of your valid ID or barangay certificate.">Proof is unreadable / incomplete</option>
          <option value="The document you uploaded has expired or is no longer valid. Please upload a current valid ID or barangay certificate.">Expired / invalid document</option>
          <option value="The name on your proof of residency does not match the name you registered. Please upload an ID under your own name.">Name does not match the document</option>
          <option value="The submitted proof does not show residence within Barangay Libis. This system is exclusive to Libis residents.">Not a resident of Barangay Libis</option>
          <option value="other">Other (write your own reason)…</option>
        </select>
      </div>
      <div class="field" id="rejectCustomWrap" hidden style="margin-bottom:12px">
        <label>Custom reason <span class="req">*</span></label>
        <textarea class="input" id="rejectCustom" name="remarks_custom" rows="3" maxlength="255"
                  placeholder="Explain briefly why the registration was rejected…"></textarea>
        <div class="hint"><span id="rejectCount">0</span>/255 characters — required before submitting.</div>
      </div>

      <div class="modal-foot">
        <button class="btn btn-outline" type="button" data-modal-close>Cancel</button>
        <button class="btn btn-danger" type="submit"><?= icon('x', 'i i-sm') ?> Reject registration</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  /* Keep gear dropdowns visible inside .table-wrap (which clips overflow):
     while open, render the menu with position:fixed at the button. */
  function resetMenu(d) {
    var b = d.querySelector('.menu-body');
    if (b) { b.style.position = ''; b.style.top = ''; b.style.left = ''; b.style.right = ''; }
  }
  document.addEventListener('toggle', function (e) {
    var d = e.target;
    if (!d.matches || !d.matches('details.action-menu')) return;
    var body = d.querySelector('.menu-body');
    if (!body) return;
    if (d.open) {
      var r = d.querySelector('summary').getBoundingClientRect();
      body.style.position = 'fixed';
      body.style.top   = (r.bottom + 6) + 'px';
      body.style.right = Math.max(8, window.innerWidth - r.right) + 'px';
      body.style.left  = 'auto';
    } else {
      resetMenu(d);
    }
  }, true);
  window.addEventListener('scroll', function () {
    document.querySelectorAll('details.action-menu[open]').forEach(resetMenu);
  }, true);
  window.addEventListener('resize', function () {
    document.querySelectorAll('details.action-menu[open]').forEach(resetMenu);
  });

  /* Reject button inside a gear menu → open the remarks modal.
     Approve is a plain native form (works even without JavaScript). */
  document.addEventListener('click', function (e) {
    var rejectBtn = e.target.closest('[data-reject]');
    if (!rejectBtn) return;

    var menu = e.target.closest('details.menu');
    if (menu) menu.removeAttribute('open');
    e.preventDefault();

    var modal   = document.getElementById('rejectModal');
    var sel     = document.getElementById('rejectReason');
    var wrap    = document.getElementById('rejectCustomWrap');
    var custom  = document.getElementById('rejectCustom');
    var counter = document.getElementById('rejectCount');

    document.getElementById('rejectId').value = rejectBtn.getAttribute('data-reject');
    document.getElementById('rejectName').textContent = rejectBtn.getAttribute('data-name') || 'applicant';
    sel.value = '';
    custom.value = '';
    counter.textContent = '0';
    wrap.hidden = true;
    custom.removeAttribute('required');
    modal.classList.add('open');
    sel.focus();
  });

  var sel     = document.getElementById('rejectReason');
  var wrap    = document.getElementById('rejectCustomWrap');
  var custom  = document.getElementById('rejectCustom');
  var counter = document.getElementById('rejectCount');

  sel.addEventListener('change', function () {
    var isOther = sel.value === 'other';
    wrap.hidden = !isOther;
    if (isOther) { custom.setAttribute('required', 'required'); custom.focus(); }
    else         { custom.removeAttribute('required'); }
  });

  custom.addEventListener('input', function () {
    counter.textContent = String(custom.value.length);
  });

  /* Block submit until a real reason exists. */
  document.getElementById('rejectForm').addEventListener('submit', function (e) {
    var reason = sel.value === 'other' ? custom.value.trim() : sel.value;
    if (!reason) {
      e.preventDefault();
      alert('Please select or write the reason for rejection — the applicant will see it.');
    }
  });
})();
</script>
<?php endif; ?>

<script src="<?= base_url('assets/js/app.js') ?>?v=<?= (int)filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
</body>
</html>
