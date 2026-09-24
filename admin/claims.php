<?php
/**
 * LOST2FOUND — Admin: claim request verification
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$db = get_db();

/* ---------- GET actions: approve / reject / returned ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') !== '') {
    verify_csrf();
    $id = (int)($_GET['id'] ?? 0);

    $st = $db->prepare(
        'SELECT cr.*, f.item_name, f.user_id AS finder_id, u.full_name AS claimant_name
         FROM claim_requests cr
         JOIN found_items f ON f.id = cr.found_item_id
         JOIN users u ON u.id = cr.claimant_id
         WHERE cr.id = ?'
    );
    $st->execute([$id]);
    $claim = $st->fetch();

    if (!$claim) {
        flash('danger', 'Claim request not found.');
        redirect('admin/claims.php');
    }

    $action = $_GET['action'] ?? '';
    $stamp  = $db->prepare('UPDATE claim_requests SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?');

    if ($action === 'approve' && $claim['status'] === 'pending') {
        $stamp->execute(['approved', (int)$_SESSION['user']['id'], $id]);

        /* If the claimant owns a lost item matched to this found item, mark it "claimed" */
        $st = $db->prepare(
            'SELECT m.lost_item_id FROM matches m
             JOIN lost_items l ON l.id = m.lost_item_id
             WHERE m.found_item_id = ? AND l.user_id = ?'
        );
        $st->execute([(int)$claim['found_item_id'], (int)$claim['claimant_id']]);
        if ($lostId = $st->fetchColumn()) {
            $db->prepare("UPDATE lost_items SET status = 'claimed' WHERE id = ? AND status IN ('matched','approved')")
               ->execute([(int)$lostId]);
        }

        notify((int)$claim['claimant_id'],
            "Your claim request for \"{$claim['item_name']}\" has been APPROVED. Please coordinate with the barangay office to schedule the return.",
            'claim', 'resident/claims.php');
        notify((int)$claim['finder_id'],
            "The claim for your found item \"{$claim['item_name']}\" has been approved. The barangay will coordinate the return.",
            'claim', 'resident/found_items.php');
        flash('success', "Claim approved. Verify the claimant's identity at the barangay office before releasing the item.");
    } elseif ($action === 'reject' && $claim['status'] === 'pending') {
        $stamp->execute(['rejected', (int)$_SESSION['user']['id'], $id]);
        notify((int)$claim['claimant_id'],
            "Your claim request for \"{$claim['item_name']}\" has been rejected. Please visit the barangay office for more information.",
            'claim', 'resident/claims.php');
        flash('success', 'Claim rejected. The claimant has been notified.');
    } elseif ($action === 'returned' && $claim['status'] === 'approved') {
        /* Officially return the item and close related lost reports */
        $db->prepare("UPDATE found_items SET status = 'returned' WHERE id = ?")
           ->execute([(int)$claim['found_item_id']]);

        $st = $db->prepare('SELECT lost_item_id FROM matches WHERE found_item_id = ?');
        $st->execute([(int)$claim['found_item_id']]);
        $closeLost = $db->prepare("UPDATE lost_items SET status = 'closed' WHERE id = ? AND status IN ('matched','claimed')");
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $lostId) {
            $closeLost->execute([(int)$lostId]);
        }

        notify((int)$claim['claimant_id'],
            "The item \"{$claim['item_name']}\" has been officially returned to you. Thank you for using Lost2Found!",
            'status', 'resident/claims.php');
        notify((int)$claim['finder_id'],
            "Your found item \"{$claim['item_name']}\" has been returned to its rightful owner. Thank you for your honesty!",
            'status', 'resident/found_items.php');
        flash('success', 'Item marked as returned. Related lost reports were closed.');
    } else {
        flash('warn', 'That action is not available for this claim.');
    }
    redirect('admin/claims.php');
}

/* ---------- List ---------- */
$fStatus = $_GET['status'] ?? '';
$where = ''; $params = [];
if (in_array($fStatus, ['pending', 'approved', 'rejected'], true)) {
    $where = ' WHERE cr.status = ?';
    $params = [$fStatus];
}

$st = $db->prepare(
    'SELECT cr.*, f.item_name, f.photo AS item_photo, f.status AS item_status, f.date_found,
            u.full_name AS claimant_name, u.contact_number AS claimant_contact, u.address AS claimant_address
     FROM claim_requests cr
     JOIN found_items f ON f.id = cr.found_item_id
     JOIN users u ON u.id = cr.claimant_id'
    . $where .
    " ORDER BY cr.status = 'pending' DESC, cr.created_at DESC"
);
$st->execute($params);
$claims = $st->fetchAll();

$title  = 'Claim Requests';
$active = 'claims';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Claim Requests</h1>
    <p>Verify each claim by checking the claimant's information, the item description, and
       supporting proof. Approve valid claims, then coordinate the physical return.</p>
  </div>
</div>

<form class="filter-bar" method="get">
  <div class="field fit">
    <label>Status</label>
    <select class="input" name="status">
      <option value="">All statuses</option>
      <?php foreach (['pending', 'approved', 'rejected'] as $s): ?>
        <option value="<?= $s ?>"<?= $fStatus === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-primary" type="submit">Filter</button>
  <a class="btn btn-ghost" href="<?= base_url('admin/claims.php') ?>">Reset</a>
</form>

<div class="card">
  <?php if (!$claims): ?>
    <div class="empty">
      <?= icon('shield', 'i') ?>
      <h3>No claim requests</h3>
      <p>When residents file claims on found items, they will appear here for verification.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Found item</th><th>Claimant</th><th>Reason</th><th>Proof</th><th>Filed</th><th>Status</th><th class="td-actions">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($claims as $c): ?>
          <tr>
            <td>
              <div class="flex">
                <?php if ($c['item_photo']): ?>
                  <img class="thumb-sm" src="<?= e(photo_url($c['item_photo'])) ?>" alt="">
                <?php else: ?>
                  <span class="thumb-sm placeholder"><?= icon('image', 'i i-sm') ?></span>
                <?php endif; ?>
                <div>
                  <strong><?= e($c['item_name']) ?></strong>
                  <div class="faint">Item: <?= e(ucfirst($c['item_status'])) ?> · found <?= fmt_date($c['date_found']) ?></div>
                </div>
              </div>
            </td>
            <td class="muted small">
              <strong style="color:var(--text)"><?= e($c['claimant_name']) ?></strong><br>
              <?= e($c['claimant_contact']) ?><br><?= e($c['claimant_address']) ?>
            </td>
            <td class="muted small" style="max-width:240px" title="<?= e($c['reason']) ?>">
              <?= e(mb_strimwidth($c['reason'], 0, 90, '…')) ?>
            </td>
            <td>
              <?php if ($c['proof']): ?>
                <a href="<?= e(photo_url($c['proof'])) ?>" target="_blank" rel="noopener">
                  <img class="thumb-sm" src="<?= e(photo_url($c['proof'])) ?>" alt="Proof">
                </a>
              <?php else: ?>
                <span class="faint">None</span>
              <?php endif; ?>
            </td>
            <td class="muted small"><?= fmt_date($c['created_at']) ?></td>
            <td>
              <?= status_badge($c['status']) ?>
              <?php if ($c['reviewed_at']): ?>
                <div class="faint"><?= fmt_date($c['reviewed_at']) ?></div>
              <?php endif; ?>
            </td>
            <td class="td-actions">
              <div class="actions">
                <a class="btn btn-outline btn-sm" href="<?= base_url('admin/item_view.php?type=found&id=' . (int)$c['found_item_id']) ?>">
                  <?= icon('eye', 'i i-sm') ?> Item
                </a>
                <?php if ($c['status'] === 'pending'): ?>
                  <a class="btn btn-teal btn-sm"
                     href="<?= base_url('admin/claims.php?action=approve&id=' . (int)$c['id'] . '&csrf=' . csrf_token()) ?>"
                     data-confirm="Approve this claim? The claimant will be told to coordinate with the barangay office.">
                    <?= icon('check', 'i i-sm') ?> Approve
                  </a>
                  <a class="btn btn-ghost btn-sm" style="color:var(--danger)"
                     href="<?= base_url('admin/claims.php?action=reject&id=' . (int)$c['id'] . '&csrf=' . csrf_token()) ?>"
                     data-confirm="Reject this claim request?">
                    <?= icon('x', 'i i-sm') ?> Reject
                  </a>
                <?php elseif ($c['status'] === 'approved'): ?>
                  <a class="btn btn-sm" style="background:var(--success);color:#fff"
                     href="<?= base_url('admin/claims.php?action=returned&id=' . (int)$c['id'] . '&csrf=' . csrf_token()) ?>"
                     data-confirm="Confirm that the item has been handed to the claimant? This closes the case.">
                    <?= icon('gift', 'i i-sm') ?> Mark Returned
                  </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
