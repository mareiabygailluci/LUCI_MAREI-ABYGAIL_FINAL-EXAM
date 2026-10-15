<?php
/**
 * LOST2FOUND — My claims (list + submit new claim request)
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$uid = (int)$_SESSION['user']['id'];
$db  = get_db();

$newId = (int)($_GET['new'] ?? 0);
$claimItem = null;

/* --- Load the found item we are claiming (for the submission form) --- */
if ($newId > 0) {
    $st = $db->prepare(
        'SELECT f.*, c.category_name, loc.location_name, u.full_name AS finder_name
         FROM found_items f
         JOIN categories c ON c.id = f.category_id
         JOIN locations loc ON loc.id = f.location_id
         JOIN users u ON u.id = f.user_id
         WHERE f.id = ?'
    );
    $st->execute([$newId]);
    $claimItem = $st->fetch();
}

/* --- Load a pending claim for editing --- */
$editId   = (int)($_GET['edit'] ?? 0);
$editClaim = null;
if ($editId > 0) {
    $st = $db->prepare(
        'SELECT cr.*, f.item_name, f.photo AS item_photo, f.status AS item_status,
                c.category_name, loc.location_name, u.full_name AS finder_name, f.date_found
         FROM claim_requests cr
         JOIN found_items f ON f.id = cr.found_item_id
         JOIN categories c ON c.id = f.category_id
         JOIN locations loc ON loc.id = f.location_id
         JOIN users u ON u.id = f.user_id
         WHERE cr.id = ? AND cr.claimant_id = ?'
    );
    $st->execute([$editId, $uid]);
    $editClaim = $st->fetch();
}

/* --- Handle submission --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'submit_claim') {
    verify_csrf();
    $fid    = (int)post('found_item_id');
    $reason = post('reason');

    $st = $db->prepare('SELECT * FROM found_items WHERE id = ?');
    $st->execute([$fid]);
    $item = $st->fetch();

    $ok = $item
        && in_array($item['status'], ['approved', 'matched'], true)
        && (int)$item['user_id'] !== $uid;

    if ($ok) {
        $dup = $db->prepare("SELECT id FROM claim_requests WHERE found_item_id = ? AND claimant_id = ? AND status IN ('pending','approved')");
        $dup->execute([$fid, $uid]);
        $ok = !$dup->fetch();
    }

    if (!$ok) {
        flash('danger', 'This item cannot be claimed (already claimed, returned, or it is your own report).');
        redirect('resident/claims.php');
    }
    if ($reason === '') {
        flash('danger', 'Please explain why the item belongs to you.');
        redirect('resident/claims.php?new=' . $fid);
    }

    try {
        $proof = handle_image_upload('proof', 'proof');
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
        redirect('resident/claims.php?new=' . $fid);
    }

    $db->prepare('INSERT INTO claim_requests (found_item_id, claimant_id, reason, proof) VALUES (?, ?, ?, ?)')
       ->execute([$fid, $uid, $reason, $proof]);

    notify_admins(
        'New claim request from ' . $_SESSION['user']['full_name'] . ' for found item "' . $item['item_name'] . '".',
        'claim', 'admin/claims.php'
    );
    flash('success', 'Claim request submitted! The barangay admin will review it and notify you of the result.');
    redirect('resident/claims.php');
}

/* --- Delete own claim --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(post('action'), ['cancel', 'delete'], true)) {
    verify_csrf();
    $id = (int)post('id');
    $st = $db->prepare('SELECT id FROM claim_requests WHERE id = ? AND claimant_id = ?');
    $st->execute([$id, $uid]);
    if ($st->fetch()) {
        $db->prepare('DELETE FROM claim_requests WHERE id = ?')->execute([$id]);
        flash('success', 'Claim request deleted.');
    }
    redirect('resident/claims.php');
}

/* --- Update own claim --- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'update_claim') {
    verify_csrf();
    $id     = (int)post('id');
    $reason = post('reason');

    $st = $db->prepare('SELECT id, found_item_id FROM claim_requests WHERE id = ? AND claimant_id = ?');
    $st->execute([$id, $uid]);
    $claim = $st->fetch();

    if (!$claim) {
        flash('danger', 'This claim cannot be edited.');
        redirect('resident/claims.php');
    }
    if ($reason === '') {
        flash('danger', 'Please explain why the item belongs to you.');
        redirect('resident/claims.php?edit=' . $id);
    }

    $proof = null;
    try {
        $proof = handle_image_upload('proof', 'proof');
    } catch (RuntimeException $ex) {
        flash('danger', $ex->getMessage());
        redirect('resident/claims.php?edit=' . $id);
    }

    if ($proof !== null) {
        $db->prepare('UPDATE claim_requests SET reason = ?, proof = ? WHERE id = ?')
           ->execute([$reason, $proof, $id]);
    } else {
        $db->prepare('UPDATE claim_requests SET reason = ? WHERE id = ?')
           ->execute([$reason, $id]);
    }

    flash('success', 'Claim request updated.');
    redirect('resident/claims.php');
}

/* --- My claims list --- */
$claims = $db->prepare(
    'SELECT cr.*, f.item_name, f.photo AS item_photo, f.status AS item_status, f.date_found
     FROM claim_requests cr
     JOIN found_items f ON f.id = cr.found_item_id
     WHERE cr.claimant_id = ?
     ORDER BY cr.created_at DESC'
);
$claims->execute([$uid]);
$claims = $claims->fetchAll();

$title  = 'My Claims';
$active = 'claims';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>My Claim Requests</h1>
    <p>File a claim when you see your item, then track the barangay's review here.</p>
  </div>
  <?php if (!$editClaim): ?>
    <a class="btn btn-outline" href="<?= base_url('resident/browse.php?type=found') ?>"><?= icon('search', 'i i-sm') ?> Browse found items</a>
  <?php endif; ?>
</div>

<?php if ($claimItem): ?>
  <div class="card mb-3" style="max-width:860px">
    <div class="card-head">
      <h3><?= icon('shield', 'i i-sm') ?> File a claim request</h3>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('resident/claims.php') ?>">Close</a>
    </div>
    <div class="card-body">
      <div class="flex mb-3" style="align-items:flex-start">
        <?php if ($claimItem['photo']): ?>
          <img class="thumb-sm" style="width:64px;height:64px" src="<?= e(photo_url($claimItem['photo'])) ?>" alt="">
        <?php else: ?>
          <span class="thumb-sm placeholder" style="width:64px;height:64px"><?= icon('image', 'i') ?></span>
        <?php endif; ?>
        <div>
          <strong><?= e($claimItem['item_name']) ?></strong>
          <div class="small muted">
            <?= e($claimItem['category_name']) ?> · <?= e($claimItem['location_name']) ?> ·
            Found on <?= fmt_date($claimItem['date_found']) ?> ·
            Reported by <?= e($claimItem['finder_name']) ?>
          </div>
          <div class="small muted">“<?= e(mb_strimwidth($claimItem['description'], 0, 140, '…')) ?>”</div>
        </div>
      </div>

      <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="submit_claim">
        <input type="hidden" name="found_item_id" value="<?= (int)$claimItem['id'] ?>">

        <div class="field">
          <label for="reason">Why does this item belong to you? <span class="req">*</span></label>
          <textarea class="input" id="reason" name="reason" required
                    placeholder="Describe specific details only the owner would know — marks, contents, engravings, when and where you lost it…"></textarea>
        </div>
        <div class="field mt-2">
          <label for="proof">Supporting proof photo <span class="muted small">(optional)</span></label>
          <input class="input" type="file" id="proof" name="proof" accept="image/jpeg,image/png,image/webp" data-preview="proofPreview">
          <div class="hint">e.g. a photo of you with the item, or a receipt.</div>
          <div class="photo-preview" id="proofPreview"><img src="" alt="Preview" style="display:none"></div>
        </div>

        <div class="flex mt-3">
          <button class="btn btn-teal" type="submit"><?= icon('check', 'i i-sm') ?> Submit claim request</button>
          <a class="btn btn-ghost" href="<?= base_url('resident/claims.php') ?>">Cancel</a>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if ($editClaim): ?>
  <div class="card mb-3" style="max-width:860px">
    <div class="card-head">
      <h3><?= icon('edit', 'i i-sm') ?> Edit claim request</h3>
      <a class="btn btn-ghost btn-sm" href="<?= base_url('resident/claims.php') ?>">Close</a>
    </div>
    <div class="card-body">
      <div class="flex mb-3" style="align-items:flex-start">
        <?php if ($editClaim['item_photo']): ?>
          <img class="thumb-sm" style="width:64px;height:64px" src="<?= e(photo_url($editClaim['item_photo'])) ?>" alt="">
        <?php else: ?>
          <span class="thumb-sm placeholder" style="width:64px;height:64px"><?= icon('image', 'i') ?></span>
        <?php endif; ?>
        <div>
          <strong><?= e($editClaim['item_name']) ?></strong>
          <div class="small muted">
            <?= e($editClaim['category_name']) ?> · <?= e($editClaim['location_name']) ?> ·
            Found on <?= fmt_date($editClaim['date_found']) ?> ·
            Reported by <?= e($editClaim['finder_name']) ?>
          </div>
        </div>
      </div>

      <?php if (!empty($editClaim['proof'])): ?>
        <div class="mb-3">
          <div class="small muted mb-1">Current proof photo:</div>
          <img class="thumb-sm" style="width:80px;height:80px;object-fit:cover" src="<?= e(photo_url($editClaim['proof'])) ?>" alt="Proof">
        </div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_claim">
        <input type="hidden" name="id" value="<?= (int)$editClaim['id'] ?>">

        <div class="field">
          <label for="edit_reason">Why does this item belong to you? <span class="req">*</span></label>
          <textarea class="input" id="edit_reason" name="reason" required
                    placeholder="Describe specific details only the owner would know — marks, contents, engravings, when and where you lost it…"><?= e($editClaim['reason']) ?></textarea>
        </div>
        <div class="field mt-2">
          <label for="edit_proof">Replace proof photo <span class="muted small">(optional — leave empty to keep current)</span></label>
          <input class="input" type="file" id="edit_proof" name="proof" accept="image/jpeg,image/png,image/webp" data-preview="editProofPreview">
          <div class="photo-preview" id="editProofPreview"><img src="" alt="Preview" style="display:none"></div>
        </div>

        <div class="flex mt-3">
          <button class="btn btn-teal" type="submit"><?= icon('check', 'i i-sm') ?> Save changes</button>
          <a class="btn btn-ghost" href="<?= base_url('resident/claims.php') ?>">Cancel</a>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php if (!$editClaim && !$claims): ?>
  <div class="card">
    <div class="empty">
      <?= icon('shield', 'i') ?>
      <h3>No claim requests yet</h3>
      <p>Browse found items or check your possible matches — when you spot something that is
         yours, file a claim request and the barangay will verify it.</p>
      <a class="btn btn-teal mt-3" href="<?= base_url('resident/browse.php?type=found') ?>">Browse found items</a>
    </div>
  </div>
<?php elseif (!$editClaim): ?>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Item</th><th>Reason</th><th>Filed</th><th>Status</th><th>Reviewed</th><th class="td-actions">Actions</th></tr>
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
                  <div class="faint">Item status: <?= e(ucfirst($c['item_status'])) ?></div>
                </div>
              </div>
            </td>
            <td class="muted small" style="max-width:280px" title="<?= e($c['reason']) ?>">
              <?= e(mb_strimwidth($c['reason'], 0, 80, '…')) ?>
            </td>
            <td class="muted small"><?= fmt_date($c['created_at']) ?></td>
            <td><?= status_badge($c['status']) ?></td>
            <td class="muted small"><?= $c['reviewed_at'] ? fmt_date($c['reviewed_at']) : '—' ?></td>
            <td class="td-actions">
              <?php if ($editId === (int)$c['id']): ?>
                <a class="btn btn-ghost btn-sm" style="color:var(--teal)"
                   href="<?= base_url('resident/claims.php') ?>">
                  <?= icon('x', 'i i-sm') ?> Close
                </a>
              <?php else: ?>
                <a class="btn btn-ghost btn-sm" style="color:var(--teal)"
                   href="<?= base_url('resident/claims.php?edit=' . (int)$c['id']) ?>">
                  <?= icon('edit', 'i i-sm') ?> Edit
                </a>
              <?php endif; ?>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button class="btn btn-ghost btn-sm" style="color:var(--danger)"
                        data-confirm="Delete this claim request?">
                  <?= icon('trash', 'i i-sm') ?> Delete
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
