<?php
/**
 * LOST2FOUND — Resident: full detail view of a lost or found report
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$db      = get_db();
$uid     = (int)$_SESSION['user']['id'];
$type    = ($_GET['type'] ?? 'lost') === 'found' ? 'found' : 'lost';
$id      = (int)($_GET['id'] ?? 0);
$tab     = in_array((string)($_GET['tab'] ?? ''), ['all', 'lost', 'found'], true) ? (string)$_GET['tab'] : 'all';
$table   = $type . '_items';
$dateCol = $type === 'lost' ? 'date_lost' : 'date_found';

/* Only published reports are viewable — plus anything you posted yourself. */
$visible = $type === 'lost'
    ? "('approved','matched','claimed','closed')"
    : "('approved','matched','returned')";

$st = $db->prepare(
    "SELECT i.*, u.full_name AS owner_name, u.anonymous_name AS owner_anonymous,
            c.category_name, loc.location_name
     FROM {$table} i
     JOIN users u ON u.id = i.user_id
     JOIN categories c ON c.id = i.category_id
     JOIN locations loc ON loc.id = i.location_id
     WHERE i.id = ? AND (i.user_id = ? OR i.status IN {$visible})"
);
$st->execute([$id, $uid]);
$item = $st->fetch();

if (!$item) {
    flash('warn', 'That report is not available.');
    redirect('resident/browse.php?type=' . $tab);
}

$isOwn   = (int)$item['user_id'] === $uid;
$backUrl = 'resident/browse.php?type=' . $tab;

$reportedBy = reporter_label($item['owner_name'], $item['owner_anonymous'] ?? null, $item['reporter_privacy']);

/* Already claimed by me? */
$myClaim = null;
if ($type === 'found' && !$isOwn) {
    $st = $db->prepare("SELECT id, status FROM claim_requests WHERE found_item_id = ? AND claimant_id = ? ORDER BY id DESC LIMIT 1");
    $st->execute([$id, $uid]);
    $myClaim = $st->fetch();
}

$title  = $item['item_name'];
$active = 'browse';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <div class="flex" style="margin-bottom:6px">
      <h1 style="margin:0"><?= e($item['item_name']) ?></h1>
      <span class="badge <?= $type === 'lost' ? 'badge-info' : 'badge-success' ?>">
        <?= $type === 'lost' ? 'Lost' : 'Found' ?>
      </span>
    </div>
    <p>
      <?= $isOwn ? 'Your report' : 'Posted by ' . e($reportedBy) ?>
      · <?= time_ago($item['created_at']) ?>
      · <?= $type === 'lost' ? 'Lost on' : 'Found on' ?> <?= fmt_date($item[$dateCol]) ?>
    </p>
  </div>
  <div class="flex">
    <?php if ($type === 'found' && !$isOwn): ?>
      <?php if ($myClaim): ?>
        <span class="badge badge-warn">Claim <?= e($myClaim['status']) ?></span>
        <a class="btn btn-outline" href="<?= base_url('resident/claims.php') ?>"><?= icon('shield', 'i i-sm') ?> My claims</a>
      <?php elseif (in_array($item['status'], ['approved', 'matched'], true)): ?>
        <a class="btn btn-teal" href="<?= base_url('resident/claims.php?new=' . (int)$item['id']) ?>">
          <?= icon('shield', 'i i-sm') ?> This is mine — file a claim
        </a>
      <?php endif; ?>
    <?php elseif ($isOwn): ?>
      <a class="btn btn-outline"
         href="<?= base_url('resident/item_form.php?type=' . $type . '&id=' . (int)$item['id']) ?>">
        <?= icon('edit', 'i i-sm') ?> Edit report
      </a>
    <?php endif; ?>
    <a class="btn btn-ghost" href="<?= base_url($backUrl) ?>"><?= icon('back', 'i i-sm') ?> Back to browse</a>
  </div>
</div>

<div class="grid-2-1">
  <div class="card">
    <div class="card-head"><h3>Item details</h3></div>
    <div class="card-body">
      <?php if ($item['photo']): ?>
        <img src="<?= e(photo_url($item['photo'])) ?>" alt="<?= e($item['item_name']) ?>"
             style="max-height:340px;width:100%;object-fit:cover;border-radius:12px;border:1px solid var(--border);margin-bottom:18px">
      <?php else: ?>
        <div class="empty" style="padding:26px 16px;border:1px dashed var(--border);border-radius:12px;margin-bottom:18px">
          <?= icon('image', 'i i-lg') ?>
          <p class="faint" style="margin-top:8px">No photo was attached to this report.</p>
        </div>
      <?php endif; ?>

      <div class="form-grid">
        <div class="field"><label>Category</label><p><?= e($item['category_name']) ?></p></div>
        <div class="field">
          <label><?= $type === 'lost' ? 'Location lost' : 'Location found' ?></label>
          <p><?= e($item['location_name']) ?></p>
        </div>
        <div class="field"><label>Color</label><p><?= e($item['color']) ?></p></div>
        <div class="field"><label>Brand</label><p><?= e($item['brand'] ?: '—') ?></p></div>
        <div class="field">
          <label><?= $type === 'lost' ? 'Date lost' : 'Date found' ?></label>
          <p><?= fmt_date($item[$dateCol]) ?></p>
        </div>
        <div class="field"><label>Posted</label><p><?= time_ago($item['created_at']) ?></p></div>
        <div class="field full"><label>Description</label><p><?= nl2br(e($item['description'])) ?></p></div>
      </div>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card">
      <div class="card-head"><h3>Reported by</h3></div>
      <div class="card-body">
        <div class="flex mb-2">
          <span style="color:var(--text-faint)"><?= icon('user', 'i i-sm') ?></span>
          <?= e($reportedBy) ?><?= $isOwn ? ' (you)' : '' ?>
        </div>
        <div class="flex mb-2">
          <span style="color:var(--text-faint)"><?= icon('pin', 'i i-sm') ?></span>
          <?= e($item['location_name']) ?>
        </div>
        <div class="flex">
          <span style="color:var(--text-faint)"><?= icon('calendar', 'i i-sm') ?></span>
          Filed <?= time_ago($item['created_at']) ?>
        </div>
        <?php if (!$isOwn): ?>
          <p class="faint" style="margin-top:12px">
            Coordination and verification of ownership are handled by the barangay office.
          </p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3>What happens next?</h3></div>
      <div class="card-body">
        <p class="small" style="color:var(--text-soft)">
          <?php if ($type === 'found'): ?>
            Found this item? File a claim with proof of ownership — the barangay admin will
            verify it before the item is returned.
          <?php else: ?>
            Found an item like this? Post it as a found report so the system can match it
            with this lost report automatically.
          <?php endif; ?>
        </p>
        <?php if ($type === 'found' && !$isOwn && !$myClaim && in_array($item['status'], ['approved', 'matched'], true)): ?>
          <a class="btn btn-teal btn-block mt-3" href="<?= base_url('resident/claims.php?new=' . (int)$item['id']) ?>">
            <?= icon('shield', 'i i-sm') ?> File a claim
          </a>
        <?php elseif ($type === 'lost' && !$isOwn): ?>
          <a class="btn btn-teal btn-block mt-3" href="<?= base_url('resident/item_form.php?type=found') ?>">
            <?= icon('gift', 'i i-sm') ?> Post a found item
          </a>
        <?php endif; ?>
      </div>
    </div>

    <a class="btn btn-outline btn-block" href="<?= base_url($backUrl) ?>"><?= icon('back', 'i i-sm') ?> Back to browse</a>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
