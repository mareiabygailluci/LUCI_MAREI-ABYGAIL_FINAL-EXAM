<?php
/**
 * LOST2FOUND — My Profile (update info + change password)
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$uid = (int)$_SESSION['user']['id'];
$db  = get_db();

$st = $db->prepare('SELECT * FROM users WHERE id = ?');
$st->execute([$uid]);
$me = $st->fetch();

/* ---- Update profile info ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'save_profile') {
    verify_csrf();
    $name    = post('full_name');
    $email   = strtolower(post('email'));
    $contact = post('contact_number');
    $address = post('address');
    $showName = post('show_name') === '0' ? 0 : 1;
    $errors  = [];

    if ($name === '')                    $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
    if ($contact === '')                 $errors[] = 'Contact number is required.';
    if ($address === '')                 $errors[] = 'Address / Purok is required.';

    if (!$errors) {
        $dup = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $dup->execute([$email, $uid]);
        if ($dup->fetch()) {
            $errors[] = 'That email address is already used by another account.';
        }
    }

    if (!$errors) {
        $db->prepare('UPDATE users SET full_name = ?, email = ?, contact_number = ?, address = ?, show_name = ? WHERE id = ?')
           ->execute([$name, $email, $contact, $address, $showName, $uid]);
        $_SESSION['user']['full_name'] = $name;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['show_name'] = $showName;
        flash('success', 'Profile updated.');
        redirect('resident/profile.php');
    }

    foreach ($errors as $err) flash('danger', $err);
    redirect('resident/profile.php');
}

/* ---- Change password ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'change_password') {
    verify_csrf();
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!password_verify($current, $me['password'])) {
        flash('danger', 'Your current password is incorrect.');
    } elseif ($err = validate_password($new)) {
        flash('danger', $err);
    } elseif ($new !== $confirm) {
        flash('danger', 'New password confirmation does not match.');
    } else {
        $db->prepare('UPDATE users SET password = ? WHERE id = ?')
           ->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
        flash('success', 'Password changed successfully.');
    }
    redirect('resident/profile.php');
}

$st->execute([$uid]);
$me = $st->fetch();

$title  = 'My Profile';
$active = 'profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>My Profile</h1>
    <p>Keep your contact details up to date so the barangay can reach you about matches and claims.</p>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><h3>Personal information</h3></div>
    <div class="card-body">
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_profile">
        <div class="field mb-2">
          <label for="full_name">Full name</label>
          <input class="input" id="full_name" name="full_name" value="<?= e($me['full_name']) ?>" required>
        </div>
        <div class="field mb-2">
          <label for="email">Email address</label>
          <input class="input" type="email" id="email" name="email" value="<?= e($me['email']) ?>" required>
        </div>
        <div class="field mb-2">
          <label for="contact_number">Contact number</label>
          <input class="input" id="contact_number" name="contact_number" value="<?= e($me['contact_number']) ?>" required>
        </div>
        <div class="field mb-2">
          <label for="address">Purok / Address</label>
          <select class="input" id="address" name="address" required>
            <?= purok_select($me['address']) ?>
          </select>
        </div>
        <div class="field mb-3">
          <label for="show_name">Display my reports as</label>
          <select class="input" id="show_name" name="show_name">
            <option value="1"<?= $me['show_name'] ? ' selected' : '' ?>>My real name (<?= e($me['full_name']) ?>)</option>
            <option value="0"<?= !$me['show_name'] ? ' selected' : '' ?>>Anonymous — <?= e($me['anonymous_name'] ?: ensure_anonymous_name($uid)) ?></option>
          </select>
          <div class="hint">Choose how other residents see you on your lost / found reports. The barangay admin always sees your real identity and contact number.</div>
        </div>
        <div class="flex mb-3">
          <span class="badge badge-plain <?= $me['role'] === 'admin' ? 'badge-match' : 'badge-info' ?>">
            <?= $me['role'] === 'admin' ? 'Barangay Admin' : 'Resident' ?>
          </span>
          <span class="badge badge-plain <?= $me['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
            <?= e(ucfirst($me['status'])) ?>
          </span>
          <span class="faint">Member since <?= fmt_date($me['created_at']) ?></span>
        </div>
        <button class="btn btn-primary" type="submit"><?= icon('check', 'i i-sm') ?> Save changes</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3>Change password</h3></div>
    <div class="card-body">
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="field mb-2">
          <label for="current_password">Current password</label>
          <input class="input" type="password" id="current_password" name="current_password" required>
        </div>
        <div class="field mb-2">
          <label for="new_password">New password</label>
          <input class="input" type="password" id="new_password" name="new_password" required>
          <div class="hint">At least 8 characters, with letters and numbers.</div>
        </div>
        <div class="field mb-3">
          <label for="confirm_password">Confirm new password</label>
          <input class="input" type="password" id="confirm_password" name="confirm_password" required>
        </div>
        <button class="btn btn-primary" type="submit"><?= icon('shield', 'i i-sm') ?> Update password</button>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
