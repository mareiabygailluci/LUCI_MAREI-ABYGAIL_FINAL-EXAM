<?php
/**
 * LOST2FOUND — Admin dashboard
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$db = get_db();

$stats = $db->query(
    "SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'resident')                        AS residents,
        (SELECT COUNT(*) FROM users WHERE status = 'pending')                       AS pending_users,
        (SELECT COUNT(*) FROM lost_items)                                           AS lost,
        (SELECT COUNT(*) FROM found_items)                                          AS found,
        (SELECT COUNT(*) FROM matches)                                              AS matches,
        (SELECT COUNT(*) FROM claim_requests WHERE status = 'pending')              AS pending_claims,
        (SELECT COUNT(*) FROM found_items WHERE status = 'returned')                AS returned"
)->fetch();

$recent = $db->query(
    "(SELECT li.id, 'lost' AS rtype, li.item_name, li.status, li.created_at, u.full_name, c.category_name
        FROM lost_items li JOIN users u ON u.id = li.user_id JOIN categories c ON c.id = li.category_id)
     UNION ALL
     (SELECT fi.id, 'found', fi.item_name, fi.status, fi.created_at, u.full_name, c.category_name
        FROM found_items fi JOIN users u ON u.id = fi.user_id JOIN categories c ON c.id = fi.category_id)
     ORDER BY created_at DESC
     LIMIT 7"
)->fetchAll();

$lostByCat = $db->query(
    "SELECT c.category_name lbl, COUNT(li.id) n
     FROM categories c LEFT JOIN lost_items li ON li.category_id = c.id
     GROUP BY c.id HAVING n > 0 ORDER BY n DESC LIMIT 6"
)->fetchAll();
$foundByCat = $db->query(
    "SELECT c.category_name lbl, COUNT(fi.id) n
     FROM categories c LEFT JOIN found_items fi ON fi.category_id = c.id
     GROUP BY c.id HAVING n > 0 ORDER BY n DESC LIMIT 6"
)->fetchAll();

$lostChartData  = array_column($lostByCat, 'n', 'lbl');
$foundChartData = array_column($foundByCat, 'n', 'lbl');

$pendingList = $db->query(
    "SELECT id, full_name, email, contact_number, address, residency_proof, created_at
     FROM users WHERE status = 'pending' ORDER BY created_at ASC"
)->fetchAll();

$title  = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>LOST2FOUND Dashboard</h1>
    <p>Barangay Libis · <?= date('l, F j, Y') ?></p>
  </div>
  <a class="btn btn-outline" href="<?= base_url('admin/reports.php') ?>"><?= icon('chart', 'i i-sm') ?> View statistics</a>
</div>

<div class="stat-grid">
  <div class="stat">
    <div class="stat-ico bg-primary"><?= icon('users') ?></div>
    <div><div class="stat-num"><?= (int)$stats['residents'] ?></div><div class="stat-lbl">Total Residents</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico bg-teal"><?= icon('box') ?></div>
    <div><div class="stat-num"><?= (int)$stats['lost'] ?></div><div class="stat-lbl">Lost Reports</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico bg-info"><?= icon('gift') ?></div>
    <div><div class="stat-num"><?= (int)$stats['found'] ?></div><div class="stat-lbl">Found Reports</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico bg-purple"><?= icon('link') ?></div>
    <div><div class="stat-num"><?= (int)$stats['matches'] ?></div><div class="stat-lbl">Possible Matches</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico bg-warn"><?= icon('shield') ?></div>
    <div><div class="stat-num"><?= (int)$stats['pending_claims'] ?></div><div class="stat-lbl">Pending Claims</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico bg-success"><?= icon('check-circle') ?></div>
    <div><div class="stat-num"><?= (int)$stats['returned'] ?></div><div class="stat-lbl">Items Returned</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico bg-warn"><?= icon('user') ?></div>
    <div><div class="stat-num"><?= (int)$stats['pending_users'] ?></div><div class="stat-lbl">Pending Approvals</div></div>
  </div>
</div>

<?php if ($pendingList): ?>
<div class="card mb-3">
  <div class="card-head">
    <h3><?= icon('user', 'i i-sm') ?> Registrations awaiting approval (<?= count($pendingList) ?>)</h3>
    <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/users.php?status=pending') ?>">Open user management</a>
  </div>
  <div class="card-body" style="padding-top:6px">
    <p class="muted small" style="margin-bottom:10px">Review each uploaded proof of residency (valid ID / barangay certificate), then approve or reject. Approved residents can log in immediately; rejected ones are notified.</p>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Applicant</th><th>Contact</th><th>Purok / Address</th><th>Proof</th><th>Registered</th><th class="td-actions">Action</th></tr></thead>
        <tbody>
        <?php foreach ($pendingList as $p): ?>
          <tr>
            <td>
              <strong><?= e($p['full_name']) ?></strong>
              <div class="faint small"><?= e($p['email']) ?></div>
            </td>
            <td class="muted small"><?= e($p['contact_number']) ?></td>
            <td class="muted small"><?= e($p['address']) ?></td>
            <td>
              <?php if (!empty($p['residency_proof'])): ?>
                <a class="btn btn-outline btn-sm" href="<?= e(proof_url($p['residency_proof'])) ?>" target="_blank" rel="noopener">
                  <?= icon('image', 'i i-sm') ?> View proof
                </a>
              <?php else: ?>
                <span class="badge badge-muted">None</span>
              <?php endif; ?>
            </td>
            <td class="muted small"><?= time_ago($p['created_at']) ?></td>
            <td class="td-actions">
              <div class="actions">
                <details class="menu action-menu">
                  <summary class="icon-btn" aria-label="Registration actions" title="Actions">
                    <?= icon('gear') ?>
                  </summary>
                  <div class="menu-body">
                    <div class="menu-head">
                      <strong><?= e($p['full_name']) ?></strong>
                      <span>Registration review</span>
                    </div>
                    <form method="post" action="<?= e(base_url('admin/users.php')) ?>" style="margin:0">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="approve">
                      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                      <button type="submit" data-confirm="Approve the registration of <?= e($p['full_name']) ?>? They will be notified and can log in right away.">
                        <?= icon('check', 'i i-sm') ?> Approve registration
                      </button>
                    </form>
                    <button type="button" class="txt-danger" data-reject="<?= (int)$p['id'] ?>" data-name="<?= e($p['full_name']) ?>">
                      <?= icon('x', 'i i-sm') ?> Reject (with remarks)…
                    </button>
                  </div>
                </details>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="grid-2-1">
  <div class="card">
    <div class="card-head">
      <h3>Recent reports</h3>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/lost_items.php') ?>">Manage reports</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Item</th><th>Reporter</th><th>Category</th><th>Type</th><th>Status</th><th>Filed</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><strong><?= e($r['item_name']) ?></strong></td>
            <td class="muted"><?= e($r['full_name']) ?></td>
            <td class="muted"><?= e($r['category_name']) ?></td>
            <td><?= $r['rtype'] === 'lost'
                    ? '<span class="badge badge-plain badge-info">Lost</span>'
                    : '<span class="badge badge-plain badge-success">Found</span>' ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="muted small"><?= time_ago($r['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card">
      <div class="card-head"><h3>Lost by category</h3></div>
      <div class="card-body"><?= render_chart($lostChartData) ?></div>
    </div>
    <div class="card">
      <div class="card-head"><h3>Found by category</h3></div>
      <div class="card-body"><?= render_chart($foundChartData, true) ?></div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
