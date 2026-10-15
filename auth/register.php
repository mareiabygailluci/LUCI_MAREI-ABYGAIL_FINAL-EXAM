<?php
/**
 * LOST2FOUND — Resident registration
 */
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : 'resident/dashboard.php');
}

$errors = [];
$old = ['first_name' => '', 'last_name' => '', 'email' => '', 'contact_number' => '', 'address' => ''];
$db      = get_db();
$isFirst = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
$showPending = false;
$pendingName = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($old as $k => $_) {
        $old[$k] = trim((string)($_POST[$k] ?? ''));
    }

    /* Normalize the name parts: collapse extra spaces, then Title Case
       each word (e.g. "dELA  cruz" → "Dela Cruz"). */
    $old['first_name'] = mb_convert_case(preg_replace('/\s+/', ' ', $old['first_name']), MB_CASE_TITLE, 'UTF-8');
    $old['last_name']  = mb_convert_case(preg_replace('/\s+/', ' ', $old['last_name']),  MB_CASE_TITLE, 'UTF-8');
    /* Canonical full name stored in the database: "First Last". */
    $old['full_name']  = trim($old['first_name'] . ' ' . $old['last_name']);

    $password     = (string)($_POST['password'] ?? '');
    $confirm      = (string)($_POST['confirm_password'] ?? '');
    $email        = strtolower($old['email']);

    /* Contact number: digits only, max 11 (e.g. 09171234567). */
    $old['contact_number'] = preg_replace('/\D/', '', $old['contact_number']);
    $old['contact_number'] = substr($old['contact_number'], 0, 11);

    if ($old['first_name'] === '' || $old['last_name'] === '') $errors[] = 'First name and last name are required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))     $errors[] = 'A valid email address is required.';
    if (!preg_match('/^09\d{9}$/', $old['contact_number'])) $errors[] = 'Contact number must be a valid 11-digit mobile number starting with 09 (e.g. 09171234567).';
    if ($old['address'] === '')                         $errors[] = 'Address / Purok is required.';
    if ($err = validate_password($password))            $errors[] = $err;
    if ($password !== $confirm)                         $errors[] = 'Password confirmation does not match.';

    $proofName = null;
    if (!$errors) {
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'That email address is already registered. If your registration was rejected, resubmit a clearer proof of residency instead of registering again.';
        } elseif (!$isFirst) {
            /* Residents must attach proof of residency (valid ID / barangay certificate). */
            try {
                $proofName = handle_image_upload('residency_proof', 'proof');
                if (!$proofName) {
                    $errors[] = 'Please upload a photo of your valid ID or barangay certificate as proof of residency.';
                }
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
    }

    if (!$errors) {
        /* The very first account becomes the Barangay Admin — trusted, no review needed.
           Everyone else starts as 'pending' until the admin verifies their proof of residency. */
        $role   = $isFirst ? 'admin' : 'resident';
        $status = $isFirst ? 'active' : 'pending';

        $db->prepare('INSERT INTO users (full_name, email, password, contact_number, address, role, status, residency_proof) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
           ->execute([$old['full_name'], $email, password_hash($password, PASSWORD_DEFAULT),
                      $old['contact_number'], $old['address'], $role, $status, $proofName]);
        $id = (int)$db->lastInsertId();
        ensure_anonymous_name($id);

        if ($isFirst) {
            notify($id, 'Welcome to Lost2Found! Report lost or found items and let the system find possible matches for you.', 'system');
            login_user(['id' => $id, 'full_name' => $old['full_name'], 'email' => $email, 'role' => $role]);
            flash('success', 'Welcome to Lost2Found, ' . $old['full_name'] . '! Your account is ready.');
            redirect('admin/dashboard.php');
        }

        notify($id, 'Welcome to Lost2Found! Your registration is being reviewed by the barangay admin. You can log in once your proof of residency is verified.', 'system');
        notify_admins('New resident registration pending approval: ' . $old['full_name'] . ' (' . $old['address'] . '). Please review the uploaded proof of residency.', 'system', 'admin/users.php');

        $showPending = true;
        $pendingName = $old['full_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create account · Lost2Found</title>
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
      <h1>Create your account.</h1>
      <p>Join the official Barangay Libis Lost &amp; Found system. Registrations are reviewed by the barangay office to keep the platform exclusive to residents.</p>
      <ul class="auth-points">
        <li><?= icon('check-circle', 'i i-sm') ?><span>Report items you lost or found</span></li>
        <li><?= icon('check-circle', 'i i-sm') ?><span>Get notified of possible matches</span></li>
        <li><?= icon('check-circle', 'i i-sm') ?><span>File and track claim requests</span></li>
      </ul>
    </div>
    <div class="auth-foot">Barangay Libis · Lost &amp; Found Management System</div>
  </div>

  <div class="auth-main">
    <div class="auth-card" style="max-width:460px">
      <?php if ($showPending): ?>
      <div class="card" style="text-align:center">
        <h1>Registration received</h1>
        <p class="sub">Salamat, <?= e($pendingName) ?>!</p>
        <div class="alert alert-info" style="text-align:left;margin-bottom:14px">
          <span>Your account is now <strong>pending verification</strong>. The barangay admin will review the proof of residency you submitted. You may log in once your account has been approved.</span>
        </div>
        <a class="btn btn-primary btn-block" href="<?= base_url('auth/login.php') ?>">Go to log in</a>
      </div>
      <?php else: ?>
      <div class="card">
        <h1>Create account</h1>
        <p class="sub">For residents of Barangay Libis.</p>

        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger" style="margin-bottom:14px"><span><?= e($err) ?></span></div>
        <?php endforeach; ?>

        <form method="post" novalidate enctype="multipart/form-data" class="reg-form">
          <?= csrf_field() ?>
          <div class="form-grid">
            <div class="field">
              <label for="first_name">First name <span class="req">*</span></label>
              <input class="input" id="first_name" name="first_name" value="<?= e($old['first_name']) ?>"
                     placeholder="Juan" required>
            </div>
            <div class="field">
              <label for="last_name">Last name <span class="req">*</span></label>
              <input class="input" id="last_name" name="last_name" value="<?= e($old['last_name']) ?>"
                     placeholder="Dela Cruz" required>
            </div>
            <div class="field full">
              <label for="email">Email address <span class="req">*</span></label>
              <input class="input" type="email" id="email" name="email" value="<?= e($old['email']) ?>"
                     placeholder="you@example.com" required>
            </div>
            <div class="field">
              <label for="contact_number">Contact number <span class="req">*</span></label>
              <input class="input" id="contact_number" name="contact_number" value="<?= e($old['contact_number']) ?>"
                     placeholder="09171234567" inputmode="numeric" maxlength="11" autocomplete="tel" required>
              <div class="hint">11 digits, starts with 09.</div>
            </div>
            <div class="field">
              <label for="address">Purok / Address <span class="req">*</span></label>
              <select class="input" id="address" name="address" required>
                <option value="" disabled<?= $old['address'] === '' ? ' selected' : '' ?>>Select your Purok</option>
                <?= purok_select($old['address']) ?>
              </select>
              <div class="hint">Barangay Libis puroks only.</div>
            </div>
            <?php if (!$isFirst): ?>
            <div class="field full">
              <label for="residency_proof">Proof of residency <span class="req">*</span></label>
              <input class="input" type="file" id="residency_proof" name="residency_proof" accept="image/jpeg,image/png,image/webp">
              <div class="hint">Photo of a valid ID or barangay certificate (JPG/PNG/WEBP, max 5 MB). Reviewed by the barangay admin before you can log in.</div>
            </div>
            <?php endif; ?>
            <div class="field">
              <label for="password">Password <span class="req">*</span></label>
              <div class="pwd-wrap">
                <input class="input" type="password" id="password" name="password" required>
                <button type="button" class="pwd-toggle" aria-label="Show password"><?= icon('eye', 'i i-sm') ?></button>
              </div>
              <div class="hint">8+ characters, letters &amp; numbers.</div>
            </div>
            <div class="field">
              <label for="confirm_password">Confirm password <span class="req">*</span></label>
              <div class="pwd-wrap">
                <input class="input" type="password" id="confirm_password" name="confirm_password" required>
                <button type="button" class="pwd-toggle" aria-label="Show password"><?= icon('eye', 'i i-sm') ?></button>
              </div>
              <div class="hint">Must match your password.</div>
            </div>
          </div>
          <button class="btn btn-primary btn-block" type="submit">Create account</button>
        </form>

        <p class="auth-alt">Already registered? <a href="<?= base_url('auth/login.php') ?>">Log in</a></p>
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>
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

  /* Contact number: digits only, hard cap at 11. */
  var contact = document.getElementById('contact_number');
  if (contact) {
    contact.addEventListener('input', function () {
      this.value = this.value.replace(/\D/g, '').slice(0, 11);
    });
  }
})();
</script>
</body>
</html>
