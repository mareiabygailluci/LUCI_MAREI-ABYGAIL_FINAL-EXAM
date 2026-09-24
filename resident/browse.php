<?php
/**
 * LOST2FOUND — Browse & search items (search/filter feature)
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$uid = (int)$_SESSION['user']['id'];
$db  = get_db();

$type    = ($_GET['type'] ?? 'found') === 'lost' ? 'lost' : 'found';
$table   = $type === 'lost' ? 'lost_items' : 'found_items';
$dateCol = $type === 'lost' ? 'date_lost' : 'date_found';
$visible = $type === 'lost'
    ? "('approved','matched','claimed','closed')"
    : "('approved','matched','returned')";

$q        = trim((string)($_GET['q'] ?? ''));
$color    = trim((string)($_GET['color'] ?? ''));
$fCat     = (int)($_GET['category_id'] ?? 0);
$fLoc     = (int)($_GET['location_id'] ?? 0);

$where  = ["i.status IN $visible"];
$params = [];
if ($q !== '') {
    $where[]  = '(i.item_name LIKE ? OR i.description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($color !== '') {
    $where[]  = 'i.color LIKE ?';
    $params[] = "%$color%";
}
if ($fCat > 0) { $where[] = 'i.category_id = ?'; $params[] = $fCat; }
if ($fLoc > 0) { $where[] = 'i.location_id = ?'; $params[] = $fLoc; }

$sql = "SELECT i.*, c.category_name, loc.location_name, u.full_name AS owner_name, u.anonymous_name AS owner_anonymous
        FROM {$table} i
        JOIN categories c ON c.id = i.category_id
        JOIN locations loc ON loc.id = i.location_id
        JOIN users u ON u.id = i.user_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY i.created_at DESC";

$st = $db->prepare($sql);
$st->execute($params);
$items = $st->fetchAll();

$categories = $db->query('SELECT id, category_name FROM categories ORDER BY category_name')->fetchAll();
$locations  = $db->query('SELECT id, location_name FROM locations ORDER BY location_name')->fetchAll();

/* Claims I already filed (to label cards) */
$myClaims = $db->prepare("SELECT found_item_id FROM claim_requests WHERE claimant_id = ? AND status IN ('pending','approved')");
$myClaims->execute([$uid]);
$myClaimIds = array_map('intval', $myClaims->fetchAll(PDO::FETCH_COLUMN));

$title  = 'Browse Items';
$active = 'browse';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Browse Items</h1>
    <p>Search all approved reports in Barangay Libis. Found something that is yours? File a claim.</p>
  </div>
  <div class="flex">
    <a class="btn <?= $type === 'found' ? 'btn-primary' : 'btn-outline' ?>"
       href="<?= base_url('resident/browse.php?type=found') ?>"><?= icon('gift', 'i i-sm') ?> Found Items</a>
    <a class="btn <?= $type === 'lost' ? 'btn-primary' : 'btn-outline' ?>"
       href="<?= base_url('resident/browse.php?type=lost') ?>"><?= icon('box', 'i i-sm') ?> Lost Items</a>
  </div>
</div>

<form class="filter-bar" method="get">
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <div class="field" style="flex:2">
    <label>Search</label>
    <input class="input" name="q" value="<?= e($q) ?>" placeholder="Item name or description…">
  </div>
  <div class="field">
    <label>Category</label>
    <select class="input" name="category_id">
      <option value="">All categories</option>
      <?php foreach ($categories as $c): ?>
        <option value="<?= (int)$c['id'] ?>"<?= $fCat === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['category_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label>Location</label>
    <select class="input" name="location_id">
      <option value="">All locations</option>
      <?php foreach ($locations as $l): ?>
        <option value="<?= (int)$l['id'] ?>"<?= $fLoc === (int)$l['id'] ? ' selected' : '' ?>><?= e($l['location_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field fit">
    <label>Color</label>
    <input class="input" name="color" value="<?= e($color) ?>" placeholder="e.g. Black">
  </div>
  <button class="btn btn-primary" type="submit"><?= icon('search', 'i i-sm') ?> Search</button>
  <a class="btn btn-ghost" href="<?= base_url('resident/browse.php?type=' . $type) ?>">Reset</a>
</form>

<p class="faint" style="margin-bottom:14px">
  <?= count($items) ?> <?= $type === 'lost' ? 'lost' : 'found' ?> item<?= count($items) === 1 ? '' : 's' ?> found
</p>

<?php if (!$items): ?>
  <div class="card">
    <div class="empty">
      <?= icon('search', 'i') ?>
      <h3>No items match your search</h3>
      <p>Try different keywords, or check back later — new reports are added regularly.</p>
    </div>
  </div>
<?php else: ?>
  <div class="item-grid">
    <?php foreach ($items as $it): ?>
      <div class="item-card">
        <div class="item-thumb">
          <?php if ($it['photo']): ?>
            <img src="<?= e(photo_url($it['photo'])) ?>" alt="<?= e($it['item_name']) ?>">
          <?php else: ?>
            <?= icon('image', 'i i-lg') ?>
          <?php endif; ?>
          <span class="tag"><?= status_badge($it['status']) ?></span>
        </div>
        <div class="item-body">
          <h4><?= e($it['item_name']) ?></h4>
          <div class="item-meta">
            <div class="row"><?= icon('tag') ?> <?= e($it['category_name']) ?></div>
            <div class="row"><?= icon('pin') ?> <?= e($it['location_name']) ?></div>
            <div class="row"><?= icon('calendar') ?> <?= fmt_date($it[$dateCol]) ?></div>
            <div class="row"><?= icon('user') ?> <?= e(reporter_label($it['owner_name'], $it['owner_anonymous'] ?? null, $it['reporter_privacy'])) ?></div>
          </div>
          <div class="item-foot">
            <span class="faint"><?= e($it['color']) ?></span>
            <?php if ($type === 'found'): ?>
              <?php if ((int)$it['user_id'] === $uid): ?>
                <span class="faint">Your report</span>
              <?php elseif (in_array((int)$it['id'], $myClaimIds, true)): ?>
                <span class="badge badge-warn">Claim submitted</span>
              <?php elseif (in_array($it['status'], ['approved', 'matched'], true)): ?>
                <a class="btn btn-teal btn-sm" href="<?= base_url('resident/claims.php?new=' . (int)$it['id']) ?>">
                  This is mine →
                </a>
              <?php endif; ?>
            <?php else: ?>
              <span class="faint">Reported lost</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
