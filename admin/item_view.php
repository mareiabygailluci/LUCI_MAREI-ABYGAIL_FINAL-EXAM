<?php
/**
 * LOST2FOUND — Admin: item detail view (lost or found)
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$db     = get_db();
$type   = ($_GET['type'] ?? 'lost') === 'found' ? 'found' : 'lost';
$id     = (int)($_GET['id'] ?? 0);
$table  = $type . '_items';
$back   = in_array($_GET['back'] ?? '', ['claims', 'matches'], true) ? $_GET['back'] : $table;
$dateCol = $type === 'lost' ? 'date_lost' : 'date_found';

$st = $db->prepare(
    "SELECT i.*, u.full_name AS owner, u.email AS owner_email, u.contact_number AS owner_contact,
            u.address AS owner_address, c.category_name, loc.location_name
     FROM {$table} i
     JOIN users u ON u.id = i.user_id
     JOIN categories c ON c.id = i.category_id
     JOIN locations loc ON loc.id = i.location_id
     WHERE i.id = ?"
);
$st->execute([$id]);
$item = $st->fetch();

if (!$item) {
    flash('danger', 'Item not found.');
    redirect('admin/' . $back . '.php');
}

/* Matches involving this item */
$st = $db->prepare(
    "(SELECT m.*, f.item_name AS other_name, f.status AS other_status, 'found' AS other_type
        FROM matches m JOIN found_items f ON f.id = m.found_item_id
        WHERE m.lost_item_id = ?)
     UNION ALL
     (SELECT m.*, l.item_name AS other_name, l.status AS other_status, 'lost' AS other_type
        FROM matches m JOIN lost_items l ON l.id = m.lost_item_id
        WHERE m.found_item_id = ?)
     ORDER BY match_score DESC"
);
$st->execute([$id, $id]);
$matches = $st->fetchAll();

/* Claims (found items only) */
$claims = [];
if ($type === 'found') {
    $st = $db->prepare(
        'SELECT cr.*, u.full_name, u.contact_number
         FROM claim_requests cr JOIN users u ON u.id = cr.claimant_id
         WHERE cr.found_item_id = ? ORDER BY cr.created_at DESC'
    );
    $st->execute([$id]);
    $claims = $st->fetchAll();
}

$title  = ($type === 'lost' ? 'Lost report' : 'Found report') . ' — ' . $item['item_name'];
$active = in_array($back, ['claims', 'matches'], true) ? $back : $type;
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1><?= e($item['item_name']) ?> <?= status_badge($item['status']) ?></h1>
    <p><?= $type === 'lost' ? 'Lost item report' : 'Found item report' ?> ·
       filed <?= time_ago($item['created_at']) ?></p>
  </div>
  <a class="btn btn-ghost" href="<?= base_url('admin/' . $back . '.php') ?>"><?= icon('back', 'i i-sm') ?> Back to list</a>
</div>

<div class="grid-2-1">
  <div class="card">
    <div class="card-head"><h3>Item details</h3></div>
    <div class="card-body">
      <?php if ($item['photo']): ?>
        <img src="<?= e(photo_url($item['photo'])) ?>" alt="<?= e($item['item_name']) ?>"
             style="max-height:320px;border-radius:12px;border:1px solid var(--border);margin-bottom:18px">
      <?php endif; ?>

      <div class="form-grid">
        <div class="field"><label>Category</label><p><?= e($item['category_name']) ?></p></div>
        <div class="field"><label><?= $type === 'lost' ? 'Location lost' : 'Location found' ?></label><p><?= e($item['location_name']) ?></p></div>
        <div class="field"><label>Color</label><p><?= e($item['color']) ?></p></div>
        <div class="field"><label>Brand</label><p><?= e($item['brand'] ?: '—') ?></p></div>
        <div class="field"><label><?= $type === 'lost' ? 'Date lost' : 'Date found' ?></label><p><?= fmt_date($item[$dateCol]) ?></p></div>
        <div class="field"><label>Reported by</label><p><?= e($item['owner']) ?></p></div>
        <div class="field full"><label>Description</label><p><?= e($item['description']) ?></p></div>
      </div>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:20px">
    <div class="card">
      <div class="card-head"><h3>Reporter contact</h3></div>
      <div class="card-body">
        <div class="flex mb-2"><span style="color:var(--text-faint)"><?= icon('user', 'i i-sm') ?></span> <?= e($item['owner']) ?></div>
        <div class="flex mb-2"><span style="color:var(--text-faint)"><?= icon('phone', 'i i-sm') ?></span> <?= e($item['owner_contact']) ?></div>
        <div class="flex mb-2"><span style="color:var(--text-faint)"><?= icon('mail', 'i i-sm') ?></span> <?= e($item['owner_email']) ?></div>
        <div class="flex"><span style="color:var(--text-faint)"><?= icon('home', 'i i-sm') ?></span> <?= e($item['owner_address']) ?></div>
      </div>
    </div>

    <div class="card">
      <div class="card-head"><h3>Possible matches (<?= count($matches) ?>)</h3></div>
      <?php if (!$matches): ?>
        <div class="empty" style="padding:26px 16px"><p>No matches for this item yet.</p></div>
      <?php else: ?>
        <?php foreach ($matches as $m): ?>
          <div class="notif">
            <div class="score-ring" style="width:44px;height:44px;font-size:.78rem"><?= (int)$m['match_score'] ?>%</div>
            <div style="min-width:0">
              <p><strong><?= e($m['other_name']) ?></strong> <span class="faint">(<?= $m['other_type'] ?>)</span></p>
              <div class="when"><?= status_badge($m['status']) ?> · <?= time_ago($m['created_at']) ?></div>
            </div>
            <div style="margin-left:auto">
              <a class="btn btn-ghost btn-sm" href="<?= base_url('admin/item_view.php?type=' . $m['other_type'] . '&id=' . (int)($m['other_type'] === 'found' ? $m['found_item_id'] : $m['lost_item_id']) . '&back=' . e($back)) ?>">
                <?= icon('eye', 'i i-sm') ?>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if ($type === 'found'): ?>
      <div class="card">
        <div class="card-head"><h3>Claim requests (<?= count($claims) ?>)</h3></div>
        <?php if (!$claims): ?>
          <div class="empty" style="padding:26px 16px"><p>No claims filed for this item.</p></div>
        <?php else: foreach ($claims as $c): ?>
          <div class="notif">
            <div class="notif-ico bg-primary"><?= icon('shield', 'i i-sm') ?></div>
            <div style="min-width:0">
              <p><strong><?= e($c['full_name']) ?></strong> · <?= e($c['contact_number']) ?></p>
              <div class="when" style="white-space:normal"><?= e(mb_strimwidth($c['reason'], 0, 90, '…')) ?></div>
              <div class="when"><?= status_badge($c['status']) ?> · <?= time_ago($c['created_at']) ?></div>
            </div>
          </div>
        <?php endforeach; endif; ?>
        <?php if ($claims): ?>
          <div class="card-body p-sm"><a class="btn btn-outline btn-sm btn-block" href="<?= base_url('admin/claims.php') ?>">Review claims</a></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
