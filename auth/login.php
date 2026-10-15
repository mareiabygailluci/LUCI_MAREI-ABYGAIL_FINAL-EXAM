<?php
/**
 * LOST2FOUND — Login
 */
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : 'resident/dashboard.php');
}

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = strtolower(post('email'));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter your email and password.';
    } elseif ($msg = login_attempts_remaining($email)) {
        $errors[] = $msg;
    } else {
        $stmt = get_db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            login_attempts_failed($email);
            $errors[] = 'Invalid email or password.';
        } elseif ($user['status'] === 'pending') {
            $errors[] = 'Your account is still pending verification. The barangay admin is reviewing your proof of residency — please check back soon.';
        } elseif ($user['status'] === 'rejected') {
            $errors[] = 'Your registration was not approved' . ($user['review_remarks'] ? ': ' . $user['review_remarks'] : ' because the proof of residency could not be verified.') . ' You may resubmit a clearer proof of residency below.';
            $rejectedEmail = $email;
        } elseif ($user['status'] !== 'active') {
            $errors[] = 'Your account has been deactivated. Please contact the barangay office.';
        } else {
            login_attempts_reset($email);
            login_user($user);

            /* If the admin just reviewed this account, surface the verdict
               (e.g. "Your registration has been approved!") right away. */
            $st = get_db()->prepare(
                "SELECT message FROM notifications
                  WHERE user_id = ? AND type = 'status' AND is_read = 0
                  ORDER BY id DESC LIMIT 1"
            );
            $st->execute([$user['id']]);
            if ($verdict = $st->fetch()) {
                flash('success', $verdict['message']);
            }

            redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'resident/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Log in · Lost2Found</title>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<div class="auth">
  <div class="auth-side">
    <div>
      <div class="flex">
        <div class="brand-mark">L2F</div>
        <div class="brand-name">LOST<span>2</span>FOUND</div>
      </div>
      <h1>Welcome back.</h1>
      <p>Sign in to report lost or found items, track your reports, and receive possible match alerts.</p>
      <ul class="auth-points">
        <li><?= icon('check-circle', 'i i-sm') ?><span>Report lost or found items in seconds</span></li>
        <li><?= icon('check-circle', 'i i-sm') ?><span>Automatic matching between lost &amp; found reports</span></li>
        <li><?= icon('check-circle', 'i i-sm') ?><span>Exclusive for verified Brgy. Libis residents — registrations are reviewed by the barangay office</span></li>
      </ul>
    </div>
    <div class="auth-foot">Barangay Libis · Lost &amp; Found Management System</div>
  </div>

  <div class="auth-main">
    <div class="auth-card">
      <div class="card">
        <h1>Log in</h1>
        <p class="sub">Use your account to continue.</p>

        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger" style="margin-bottom:14px"><span><?= e($err) ?></span></div>
        <?php endforeach; ?>

        <?php if (!empty($rejectedEmail)): ?>
          <a class="btn btn-warn btn-block" style="margin-bottom:14px"
             href="<?= e(base_url('auth/resubmit.php?email=' . rawurlencode($rejectedEmail))) ?>">
            <?= icon('image', 'i i-sm') ?> Resubmit a clearer proof of residency
          </a>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="field">
            <label for="email">Email address</label>
            <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>"
                   placeholder="you@example.com" required autofocus autocomplete="off"
                   data-check-url="<?= e(base_url('auth/check_email.php')) ?>"
                   data-resubmit-url="<?= e(base_url('auth/resubmit.php')) ?>">
            <div id="email-status" class="email-status" hidden></div>
          </div>
          <div class="field">
            <label for="password">Password</label>
            <div class="pwd-wrap">
              <input class="input" type="password" id="password" name="password"
                     placeholder="Your password" required>
              <button type="button" class="pwd-toggle" aria-label="Show password"><?= icon('eye', 'i i-sm') ?></button>
            </div>
          </div>
          <button class="btn btn-primary btn-block" type="submit">Log in</button>
        </form>

        <p class="auth-alt">No account yet? <a href="<?= base_url('auth/register.php') ?>">Create one</a></p>
      </div>
      <p class="auth-alt"><a href="<?= base_url('index.php') ?>">&larr; Back to home</a></p>
    </div>
  </div>

</div>

<footer class="public-footer">
  <b>LOST2FOUND</b> · Barangay Libis Lost &amp; Found Management System<br>
  &copy; <?= date('Y') ?> Barangay Libis. All rights reserved.
</footer>
<script>
(function () {
  var EYE     = <?= json_encode(icon('eye', 'i i-sm')) ?>;
  var EYE_OFF = <?= json_encode(icon('eye-off', 'i i-sm')) ?>;
  document.querySelectorAll('.pwd-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var input = btn.parentElement.querySelector('input');
      var show  = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.innerHTML = show ? EYE_OFF : EYE;
      btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  });

  /* Live email status: as the user types, show whether the account is
     approved, still pending, rejected, or not yet registered. */
  var emailInput = document.getElementById('email');
  var statusBox  = document.getElementById('email-status');
  var statusTimer = null, requestSeq = 0;

  var CHIP = {
    active:   { cls: 'st-ok',   text: 'Approved \u2014 you can log in.' },
    pending:  { cls: 'st-pend', text: 'Still pending \u2014 the barangay admin is reviewing your proof of residency.' },
    rejected: { cls: 'st-bad',  text: 'Not approved \u2014 please visit the barangay office or register again.' },
    inactive: { cls: 'st-bad',  text: 'Account deactivated \u2014 please contact the barangay office.' },
    none:     { cls: 'st-none', text: 'No account yet with this email \u2014 you can create one below.' }
  };

  function hideStatus() {
    statusBox.hidden = true;
    statusBox.className = 'email-status';
    statusBox.textContent = '';
  }

  function showStatus(key, remarks, suggest) {
    var c = CHIP[key] || CHIP.none;
    statusBox.hidden = false;
    statusBox.className = 'email-status ' + c.cls;
    if (key === 'rejected') {
      statusBox.textContent = '';
      statusBox.appendChild(document.createTextNode('Not approved — ' + (remarks || 'the proof of residency could not be verified') + ' '));
      var link = document.createElement('a');
      link.href = emailInput.getAttribute('data-resubmit-url') + '?email=' + encodeURIComponent(emailInput.value.trim().toLowerCase());
      link.textContent = 'Resubmit a clearer proof →';
      statusBox.appendChild(link);
      return;
    }
    if (key === 'none' && suggest) {
      /* Typo detected — offer the registered email with one click. */
      statusBox.textContent = '';
      statusBox.appendChild(document.createTextNode('No account with this email. Did you mean '));
      var link = document.createElement('a');
      link.href = '#';
      link.textContent = suggest;
      link.addEventListener('click', function (e) {
        e.preventDefault();
        emailInput.value = suggest;
        emailInput.dispatchEvent(new Event('input'));
        emailInput.focus();
      });
      statusBox.appendChild(link);
      statusBox.appendChild(document.createTextNode('?'));
      return;
    }
    statusBox.textContent = c.text;
  }

  if (emailInput && statusBox) {
    emailInput.addEventListener('input', function () {
      clearTimeout(statusTimer);
      var val = emailInput.value.trim();
      if (val.length < 6 || val.indexOf('@') === -1) { hideStatus(); return; }
      statusTimer = setTimeout(function () {
        var seq = ++requestSeq;
        var url = emailInput.getAttribute('data-check-url') +
                  '?email=' + encodeURIComponent(val.toLowerCase());
        fetch(url, { headers: { 'X-Requested-With': 'fetch' } })
          .then(function (r) { return r.ok ? r.json() : { status: 'none' }; })
          .then(function (data) {
            if (seq !== requestSeq) return;           // a newer request superseded this one
            showStatus(
              data && data.status ? data.status : 'none',
              data && data.remarks ? data.remarks : null,
              data && data.suggest ? data.suggest : null
            );
          })
          .catch(function () { if (seq === requestSeq) hideStatus(); });
      }, 450);
    });
  }
})();
</script>
</body>
</html>
