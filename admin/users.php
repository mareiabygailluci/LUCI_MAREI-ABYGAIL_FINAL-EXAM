<?php
/**
 * LOST2FOUND — User management (CRUD)
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$uid = (int)$_SESSION['user']['id'];
$db  = get_db();
$errors = [];

/* ---------- POST: create / update / delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');

    if ($action === 'delete') {
        $id = (int)post('id');
        if ($id === $uid) {
            flash('warn', 'You cannot delete your own account.');
        } else {
            try {
                $db->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
                flash('success', 'User deleted along with all their reports.');
            } catch (PDOException $e) {
                flash('danger', 'Could not delete this user (database restriction). Consider deactivating instead.');
            }
        }
        redirect('admin/users.php');
    }

    /* Approve / reject registrations — dispatched BEFORE the create/update
       validation below, otherwise it never runs and users get a confusing
       "Name, contact number, and address are required" error instead. */
    if ($action === 'approve' || $action === 'reject') {
        $id      = (int)post('id');
        /* The modal posts the preset dropdown and the custom textarea separately;
           whichever the admin actually used wins. (Fixed: the fields previously
           had no name attributes, so the reason never reached the server.) */
        $custom  = trim((string)($_POST['remarks_custom'] ?? ''));
        $preset  = trim((string)($_POST['remarks_select'] ?? ''));
        $remarks = $custom !== '' ? $custom : ($preset !== '' && $preset !== 'other' ? $preset : '');
        if (mb_strlen($remarks) > 255) $remarks = mb_substr($remarks, 0, 255);

        $st = $db->prepare('SELECT full_name, status, role FROM users WHERE id = ?');
        $st->execute([$id]);
        if ($row = $st->fetch()) {
            if ($row['role'] === 'admin') {
                flash('warn', 'Admin accounts do not require approval.');
            } elseif (!in_array($row['status'], ['pending', 'rejected'], true)) {
                flash('warn', 'Only pending registrations can be ' . ($action === 'approve' ? 'approved' : 'rejected') . '.');
            } elseif ($action === 'approve') {
                $db->prepare("UPDATE users SET status = 'active', review_remarks = NULL, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                   ->execute([$uid, $id]);
                notify($id, 'Your registration has been approved! Your proof of residency was verified — you may now log in and start reporting lost or found items.', 'status', 'auth/login.php');
                flash('success', $row['full_name'] . ' was APPROVED and can now log in.');
            } elseif ($remarks === '') {
                flash('danger', 'Please write the reason for rejection (required) so the applicant knows why.');
            } else {
                $db->prepare("UPDATE users SET status = 'rejected', review_remarks = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?")
                   ->execute([$remarks, $uid, $id]);
                notify($id, 'Your registration was not approved. Reason: ' . $remarks . ' — Please visit the barangay office or register again with a clearer photo of your valid ID or barangay certificate.', 'status');
                flash('warn', $row['full_name'] . ' was REJECTED. The applicant will see the reason when they type their email on the login page.');
            }
        }
        redirect('admin/users.php');
    }

    $name    = post('full_name');
    $email   = strtolower(post('email'));
    $contact = post('contact_number');
    $address = post('address');
    $role    = post('role') === 'admin' ? 'admin' : 'resident';
    $status  = in_array(post('status'), ['pending', 'active', 'inactive', 'rejected'], true) ? post('status') : 'active';
    $password = (string)($_POST['password'] ?? '');

    if ($name === '' || $contact === '' || $address === '') $errors[] = 'Name, contact number, and address are required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))         $errors[] = 'A valid email address is required.';

    if (!$errors && $action === 'create' && ($err = validate_password($password))) $errors[] = $err;
    if (!$errors && $action === 'update' && $password !== '' && ($err = validate_password($password))) $errors[] = $err;

    if (!$errors) {
        $dup = $db->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
        $dup->execute([$email, $action === 'update' ? (int)post('id') : 0]);
        if ($dup->fetch()) $errors[] = 'That email address is already registered.';
    }

    if ($errors) {
        flash('danger', implode(' ', array_map('e', $errors)));
        redirect('admin/users.php' . ($action === 'update' ? '?edit=' . (int)post('id') : '?new=1'));
    }

    if ($action === 'create') {
        $db->prepare('INSERT INTO users (full_name, email, password, contact_number, address, role, status) VALUES (?, ?, ?, ?, ?, ?, ?)')
           ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $contact, $address, $role, $status]);
        ensure_anonymous_name((int)$db->lastInsertId());
        flash('success', "Account for {$name} created.");
    } else {
        $id = (int)post('id');
        if ($password !== '') {
            $db->prepare('UPDATE users SET full_name = ?, email = ?, password = ?, contact_number = ?, address = ?, role = ?, status = ? WHERE id = ?')
               ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $contact, $address, $role, $status, $id]);
        } else {
            $db->prepare('UPDATE users SET full_name = ?, email = ?, contact_number = ?, address = ?, role = ?, status = ? WHERE id = ?')
               ->execute([$name, $email, $contact, $address, $role, $status, $id]);
        }
        flash('success', 'User updated.');
    }
    redirect('admin/users.php');
}

/* ---------- GET: toggle active/inactive ---------- */
if (($_GET['action'] ?? '') === 'toggle') {
    verify_csrf();
    $id = (int)($_GET['id'] ?? 0);
    if ($id === $uid) {
        flash('warn', 'You cannot deactivate your own account.');
    } else {
        $st = $db->prepare('SELECT full_name, status FROM users WHERE id = ?');
        $st->execute([$id]);
        if ($row = $st->fetch()) {
            if (!in_array($row['status'], ['active', 'inactive'], true)) {
                flash('warn', 'Pending or rejected registrations must be approved or rejected — use the Approve/Reject buttons instead.');
            } else {
                $new = $row['status'] === 'active' ? 'inactive' : 'active';
                $db->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $id]);
                notify($id, $new === 'active'
                    ? 'Your account has been activated. Welcome back!'
                    : 'Your account has been deactivated by the barangay admin.', 'status');
                flash('success', "User " . ($new === 'active' ? 'activated' : 'deactivated') . ".");
            }
        }
    }
    redirect('admin/users.php');
}

/* ---------- Form target (create / edit) ---------- */
$editUser = null;
if (isset($_GET['edit'])) {
    $st = $db->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $editUser = $st->fetch() ?: null;
}
$showForm = isset($_GET['new']) || $editUser !== null;

/* ---------- List with filters ---------- */
$where = []; $params = [];
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $where[]  = '(full_name LIKE ? OR email LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$fRole = $_GET['role'] ?? '';
if (in_array($fRole, ['resident', 'admin'], true)) { $where[] = 'role = ?'; $params[] = $fRole; }
$fStatus = $_GET['status'] ?? '';

/* Rejected registrations stay out of the default list — they only appear
   when the admin explicitly picks Status → Rejected. */
if ($fStatus !== '' && in_array($fStatus, ['pending', 'active', 'inactive', 'rejected'], true)) {
    $where[] = 'status = ?';
    $params[] = $fStatus;
} else {
    $where[] = "status != 'rejected'";
}

$st = $db->prepare('SELECT * FROM users' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC');
$st->execute($params);
$users = $st->fetchAll();

$title  = 'Users';
$active = 'users';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>User Management</h1>
    <p>Create, edit, activate/deactivate, or remove resident and admin accounts.
       Deactivation is recommended over deletion to preserve records.</p>
  </div>
  <a class="btn btn-teal" href="<?= base_url('admin/users.php?new=1') ?>"><?= icon('plus', 'i i-sm') ?> New User</a>
</div>

<?php if ($showForm): ?>
  <div class="card mb-3" style="max-width:900px">
    <div class="card-head">
      <h3><?= $editUser ? 'Edit user — ' . e($editUser['full_name']) : 'Create new user' ?></h3>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/users.php') ?>">Close</a>
    </div>
    <div class="card-body">
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editUser ? 'update' : 'create' ?>">
        <?php if ($editUser): ?><input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>"><?php endif; ?>

        <div class="form-grid">
          <div class="field">
            <label>Full name <span class="req">*</span></label>
            <input class="input" name="full_name" value="<?= e($editUser['full_name'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label>Email <span class="req">*</span></label>
            <input class="input" type="email" name="email" value="<?= e($editUser['email'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label>Contact number <span class="req">*</span></label>
            <input class="input" name="contact_number" value="<?= e($editUser['contact_number'] ?? '') ?>" required>
          </div>
          <div class="field">
            <label>Purok / Address <span class="req">*</span></label>
            <select class="input" name="address" required>
              <?= purok_select($editUser['address'] ?? '') ?>
            </select>
          </div>
          <div class="field">
            <label>Role</label>
            <select class="input" name="role">
              <option value="resident"<?= ($editUser['role'] ?? '') === 'resident' ? ' selected' : '' ?>>Resident</option>
              <option value="admin"<?= ($editUser['role'] ?? '') === 'admin' ? ' selected' : '' ?>>Admin</option>
            </select>
          </div>
          <div class="field">
            <label>Status</label>
            <select class="input" name="status">
              <?php $cur = $editUser['status'] ?? 'active'; ?>
              <option value="pending"<?= $cur === 'pending' ? ' selected' : '' ?>>Pending (awaiting approval)</option>
              <option value="active"<?= $cur === 'active' ? ' selected' : '' ?>>Active</option>
              <option value="inactive"<?= $cur === 'inactive' ? ' selected' : '' ?>>Inactive</option>
              <option value="rejected"<?= $cur === 'rejected' ? ' selected' : '' ?>>Rejected</option>
            </select>
            <?php if ($editUser && !empty($editUser['residency_proof'])): ?>
              <div class="hint"><a href="<?= e(proof_url($editUser['residency_proof'])) ?>" target="_blank" rel="noopener"><?= icon('image', 'i i-sm') ?> View uploaded proof of residency</a></div>
            <?php endif; ?>
          </div>
          <div class="field full">
            <label>Password <?= $editUser ? '<span class="muted small">(leave empty to keep current)</span>' : '<span class="req">*</span>' ?></label>
            <input class="input" type="password" name="password" <?= $editUser ? '' : 'required' ?>>
            <div class="hint">At least 8 characters, with letters and numbers.</div>
          </div>
        </div>
        <div class="flex mt-3">
          <button class="btn btn-teal" type="submit"><?= icon('check', 'i i-sm') ?> <?= $editUser ? 'Save changes' : 'Create user' ?></button>
          <a class="btn btn-ghost" href="<?= base_url('admin/users.php') ?>">Cancel</a>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<form class="filter-bar" method="get">
  <div class="field" style="flex:2">
    <label>Search</label>
    <input class="input" name="q" value="<?= e($q) ?>" placeholder="Name or email…">
  </div>
  <div class="field fit">
    <label>Role</label>
    <select class="input" name="role">
      <option value="">All roles</option>
      <option value="resident"<?= $fRole === 'resident' ? ' selected' : '' ?>>Resident</option>
      <option value="admin"<?= $fRole === 'admin' ? ' selected' : '' ?>>Admin</option>
    </select>
  </div>
  <div class="field fit">
    <label>Status</label>
    <select class="input" name="status">
      <option value="">All (hide rejected)</option>
      <option value="pending"<?= $fStatus === 'pending' ? ' selected' : '' ?>>Pending</option>
      <option value="active"<?= $fStatus === 'active' ? ' selected' : '' ?>>Active</option>
      <option value="inactive"<?= $fStatus === 'inactive' ? ' selected' : '' ?>>Inactive</option>
      <option value="rejected"<?= $fStatus === 'rejected' ? ' selected' : '' ?>>Rejected</option>
    </select>
  </div>
  <button class="btn btn-primary" type="submit"><?= icon('search', 'i i-sm') ?> Filter</button>
  <a class="btn btn-ghost" href="<?= base_url('admin/users.php') ?>">Reset</a>
</form>

<div class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>User</th><th>Contact</th><th>Role</th><th>Status</th><th>Proof of residency</th><th>Joined</th><th class="td-actions">Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($users as $usr): ?>
        <tr>
          <td>
            <div class="flex">
              <div class="avatar"><?= e(initials($usr['full_name'])) ?></div>
              <div>
                <strong><?= e($usr['full_name']) ?></strong> <?= (int)$usr['id'] === $uid ? '<span class="faint">(you)</span>' : '' ?>
                <div class="faint"><?= e($usr['email']) ?></div>
              </div>
            </div>
          </td>
          <td class="muted small">
            <?= e($usr['contact_number']) ?><br><?= e($usr['address']) ?>
          </td>
          <td><span class="badge badge-plain <?= $usr['role'] === 'admin' ? 'badge-match' : 'badge-info' ?>">
            <?= $usr['role'] === 'admin' ? 'Admin' : 'Resident' ?></span></td>
          <td><?= status_badge($usr['status']) ?></td>
          <td class="muted small">
            <?php if (!empty($usr['residency_proof'])): ?>
              <a href="<?= e(proof_url($usr['residency_proof'])) ?>" target="_blank" rel="noopener">
                <?= icon('image', 'i i-sm') ?> View proof
              </a>
            <?php else: ?>
              &mdash;
            <?php endif; ?>
          </td>
          <td class="muted small"><?= fmt_date($usr['created_at']) ?></td>
          <td class="td-actions">
            <div class="actions">
              <a class="btn btn-outline btn-sm" href="<?= base_url('admin/users.php?edit=' . (int)$usr['id']) ?>">
                <?= icon('edit', 'i i-sm') ?> Edit
              </a>
              <?php if ($usr['status'] === 'pending'): ?>
                <details class="menu action-menu">
                  <summary class="icon-btn" aria-label="Registration actions" title="Actions">
                    <?= icon('gear') ?>
                  </summary>
                  <div class="menu-body">
                    <div class="menu-head">
                      <strong><?= e($usr['full_name']) ?></strong>
                      <span>Registration review</span>
                    </div>
                    <form method="post" action="<?= e(base_url('admin/users.php')) ?>" style="margin:0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                      <button type="submit" data-confirm="Approve the registration of <?= e($usr['full_name']) ?>? They will be notified and can log in right away.">
                        <?= icon('check', 'i i-sm') ?> Approve registration
                      </button>
                    </form>
                    <button type="button" class="txt-danger" data-reject="<?= (int)$usr['id'] ?>" data-name="<?= e($usr['full_name']) ?>">
                      <?= icon('x', 'i i-sm') ?> Reject (with remarks)…
                    </button>
                  </div>
                </details>
              <?php endif; ?>
              <?php if ((int)$usr['id'] !== $uid && in_array($usr['status'], ['active', 'inactive'], true)): ?>
                <a class="btn btn-ghost btn-sm"
                   href="<?= base_url('admin/users.php?action=toggle&id=' . (int)$usr['id'] . '&csrf=' . csrf_token()) ?>">
                  <?= $usr['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                </a>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$usr['id'] ?>">
                  <button class="btn btn-ghost btn-sm" style="color:var(--danger)"
                          data-confirm="Permanently delete <?= e($usr['full_name']) ?> and ALL of their reports? Deactivating is safer.">
                    <?= icon('trash', 'i i-sm') ?>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
