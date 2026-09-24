<?php
/**
 * LOST2FOUND — Resubmit proof of residency
 * For applicants whose registration was REJECTED. They verify with their
 * email + password, upload a clearer proof, and go back to 'pending' —
 * no need to register all over again.
 */
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : 'resident/dashboard.php');
}

$errors   = [];
$email    = trim((string)($_GET['email'] ?? ''));
$showDone = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = strtolower(trim(post('email')));
    $password = (string)($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errors[] = 'Please enter your email and password.';
    } else {
        $stmt = get_db()->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid email or password.';
        } elseif ($user['status'] !== 'rejected') {
            $errors[] = 'This account is not awaiting a resubmission. If your registration is still pending, please wait for the admin\'s review.';
        } else {
            /* Replace the old proof with the new upload. */
            try {
                $newProof = handle_image_upload('residency_proof', 'proof');
                if (!$newProof) {
                    $errors[] = 'Please attach a clearer photo of your valid ID or barangay certificate (JPG/PNG/WEBP, max 5 MB).';
                }
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
                $newProof = null;
            }

            if (!$errors && $newProof) {
                delete_uploaded_image($user['residency_proof']); // remove the rejected upload
                get_db()->prepare(
                    "UPDATE users SET status = 'pending', residency_proof = ?, review_remarks = NULL,
                            reviewed_by = NULL, reviewed_at = NULL WHERE id = ?"
                )->execute([$newProof, $user['id']]);

                notify_admins($user['full_name'] . ' resubmitted their proof of residency. Please review the new upload.', 'system', 'admin/users.php');
                $showDone = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Resubmit proof · Lost2Found</title>
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
      <h1>Resubmit your proof.</h1>
      <p>Your registration was not approved, but you can try again right away — verify your identity, upload a clearer proof of residency, and the barangay admin will review it again.</p>
    </div>
    <div class="auth-foot">Barangay Libis · Lost &amp; Found Management System</div>
  </div>

  <div class="auth-main">
    <div class="auth-card" style="max-width:460px">
      <?php if ($showDone): ?>
      <div class="card" style="text-align:center">
        <h1>Resubmission received</h1>
        <p class="sub">Salamat!</p>
        <div class="alert alert-info" style="text-align:left;margin-bottom:14px">
          <span>Your new proof of residency is now <strong>pending review</strong>. The barangay admin will check it again — you may log in once it is approved.</span>
        </div>
        <a class="btn btn-primary btn-block" href="<?= base_url('auth/login.php') ?>">Go to log in</a>
      </div>
      <?php else: ?>
      <div class="card">
        <h1>Resubmit proof of residency</h1>
        <p class="sub">For registrations that were not approved.</p>

        <?php foreach ($errors as $err): ?>
          <div class="alert alert-danger" style="margin-bottom:14px"><span><?= e($err) ?></span></div>
        <?php endforeach; ?>

        <form method="post" novalidate enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="field">
            <label for="email">Email address <span class="req">*</span></label>
            <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>"
                   placeholder="you@example.com" required autofocus>
          </div>
          <div class="field">
            <label for="password">Password <span class="req">*</span></label>
            <div class="pwd-wrap">
              <input class="input" type="password" id="password" name="password" required>
              <button type="button" class="pwd-toggle" aria-label="Show password"><?= icon('eye', 'i i-sm') ?></button>
            </div>
          </div>
          <div class="field" style="margin-bottom:18px">
            <label for="residency_proof">New proof of residency <span class="req">*</span></label>
            <input class="input" type="file" id="residency_proof" name="residency_proof" accept="image/jpeg,image/png,image/webp">
            <div class="hint">Clear photo of a valid ID or barangay certificate (JPG/PNG/WEBP, max 5 MB). This replaces the one that was rejected.</div>
          </div>
          <button class="btn btn-primary btn-block" type="submit">Submit for review</button>
        </form>

        <p class="auth-alt"><a href="<?= base_url('auth/login.php') ?>">&larr; Back to log in</a></p>
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
})();
</script>
</body>
</html>
