<?php
/**
 * LOST2FOUND — Resident dashboard
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$u   = current_user();
$uid = (int)$u['id'];
$db  = get_db();

$stats = $db->prepare(
    "SELECT
        (SELECT COUNT(*) FROM lost_items  WHERE user_id = ?) AS lost,
        (SELECT COUNT(*) FROM found_items WHERE user_id = ?) AS found,
        (SELECT COUNT(*) FROM claim_requests WHERE claimant_id = ?) AS claims,
        (SELECT COUNT(*) FROM claim_requests WHERE claimant_id = ? AND status = 'pending') AS claims_pending"
);
$stats->execute([$uid, $uid, $uid, $uid]);
$stats = $stats->fetch();

$st = $db->prepare(
    "SELECT COUNT(*) FROM matches m
     JOIN lost_items  l ON l.id = m.lost_item_id
     JOIN found_items f ON f.id = m.found_item_id
     WHERE m.status = 'pending' AND (l.user_id = ? OR f.user_id = ?)"
);
$st->execute([$uid, $uid]);
$myMatches = (int)$st->fetchColumn();

$recent = $db->prepare(
    "(SELECT 'lost' AS rtype, id, item_name, status, created_at FROM lost_items WHERE user_id = :u1)
     UNION ALL
     (SELECT 'found', id, item_name, status, created_at FROM found_items WHERE user_id = :u2)
     ORDER BY created_at DESC LIMIT 6"
);
$recent->execute([':u1' => $uid, ':u2' => $uid]);
$recent = $recent->fetchAll();

$firstName = explode(' ', trim($u['full_name']))[0];
$title  = 'Dashboard';
$active = 'dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Magandang araw, <?= e($firstName) ?>!</h1>
    <p>Here is an overview of your reports, matches, and claim requests.</p>
  </div>
  <div class="flex">
    <a class="btn btn-outline" href="<?= base_url('resident/item_form.php?type=found') ?>"><?= icon('gift', 'i i-sm') ?> I Found Something</a>
    <a class="btn btn-teal" href="<?= base_url('resident/item_form.php?type=lost') ?>"><?= icon('box', 'i i-sm') ?> I Lost Something</a>
  </div>
</div>

<div class="stat-grid">
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
    <div><div class="stat-num"><?= $myMatches ?></div><div class="stat-lbl">Possible Matches</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico bg-warn"><?= icon('shield') ?></div>
    <div><div class="stat-num"><?= (int)$stats['claims'] ?></div><div class="stat-lbl">Claims Filed (<?= (int)$stats['claims_pending'] ?> pending)</div></div>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h3>My recent reports</h3>
    <a class="btn btn-ghost btn-sm" href="<?= base_url('resident/lost_items.php') ?>">View all</a>
  </div>
  <?php if (!$recent): ?>
    <div class="empty">
      <?= icon('inbox', 'i') ?>
      <h3>No reports yet</h3>
      <p>Lost something or found something? Create your first report and the system will
         automatically look for possible matches.</p>
      <a class="btn btn-teal mt-3" href="<?= base_url('resident/item_form.php?type=lost') ?>">Create a report</a>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Type</th><th>Item</th><th>Status</th><th>Filed</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
          <tr>
            <td><?= $r['rtype'] === 'lost'
                    ? '<span class="badge badge-plain badge-info">Lost</span>'
                    : '<span class="badge badge-plain badge-success">Found</span>' ?></td>
            <td><strong><?= e($r['item_name']) ?></strong></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="muted small"><?= time_ago($r['created_at']) ?></td>
            <td class="td-actions">
              <a class="btn btn-ghost btn-sm"
                 href="<?= base_url('resident/' . ($r['rtype'] === 'lost' ? 'lost' : 'found') . '_items.php') ?>">
                <?= icon('eye', 'i i-sm') ?> Manage
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
