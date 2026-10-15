<?php
/**
 * LOST2FOUND — Browse & search items (search/filter feature)
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$uid = (int)$_SESSION['user']['id'];
$db  = get_db();

$tab = (string)($_GET['type'] ?? 'all');
$tab = in_array($tab, ['all', 'lost', 'found'], true) ? $tab : 'all';

$q        = trim((string)($_GET['q'] ?? ''));
$color    = trim((string)($_GET['color'] ?? ''));
$fCat     = (int)($_GET['category_id'] ?? 0);
$fLoc     = (int)($_GET['location_id'] ?? 0);

$filters = [];
$params  = [];
if ($q !== '') {
    $filters[] = '(i.item_name LIKE ? OR i.description LIKE ?)';
    $params[]  = "%$q%";
    $params[]  = "%$q%";
}
if ($color !== '') { $filters[] = 'i.color LIKE ?';  $params[] = "%$color%"; }
if ($fCat > 0)     { $filters[] = 'i.category_id = ?'; $params[] = $fCat; }
if ($fLoc > 0)     { $filters[] = 'i.location_id = ?'; $params[] = $fLoc; }
$extra = $filters ? ' AND ' . implode(' AND ', $filters) : '';

$branch = static function (string $table, string $rtype, string $statuses, string $dateCol) use ($extra): string {
    return "SELECT i.*, '{$rtype}' AS rtype, i.{$dateCol} AS item_date,
                   c.category_name, loc.location_name,
                   u.full_name AS owner_name, u.anonymous_name AS owner_anonymous
            FROM {$table} i
            JOIN categories c ON c.id = i.category_id
            JOIN locations loc ON loc.id = i.location_id
            JOIN users u ON u.id = i.user_id
            WHERE i.status IN {$statuses}{$extra}";
};

$lostSql  = $branch('lost_items',  'lost',  "('approved','matched','claimed','closed')", 'date_lost');
$foundSql = $branch('found_items', 'found', "('approved','matched','returned')",         'date_found');

/* Default view merges both reports so a brand-new account immediately sees
   everything the other residents have posted. */
if ($tab === 'lost') {
    $sql = $lostSql;
    $exe = $params;
} elseif ($tab === 'found') {
    $sql = $foundSql;
    $exe = $params;
} else {
    $sql  = $lostSql . ' UNION ALL ' . $foundSql;
    $exe  = array_merge($params, $params);
}
$sql .= ' ORDER BY created_at DESC';

$st = $db->prepare($sql);
$st->execute($exe);
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
    <a class="btn <?= $tab === 'all' ? 'btn-primary' : 'btn-outline' ?>"
       href="<?= base_url('resident/browse.php?type=all') ?>"><?= icon('search', 'i i-sm') ?> All Items</a>
    <a class="btn <?= $tab === 'found' ? 'btn-primary' : 'btn-outline' ?>"
       href="<?= base_url('resident/browse.php?type=found') ?>"><?= icon('gift', 'i i-sm') ?> Found Items</a>
    <a class="btn <?= $tab === 'lost' ? 'btn-primary' : 'btn-outline' ?>"
       href="<?= base_url('resident/browse.php?type=lost') ?>"><?= icon('box', 'i i-sm') ?> Lost Items</a>
  </div>
</div>

<form class="filter-bar" method="get">
  <input type="hidden" name="type" value="<?= e($tab) ?>">
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
  <a class="btn btn-ghost" href="<?= base_url('resident/browse.php?type=' . e($tab)) ?>">Reset</a>
</form>

<p class="faint" style="margin-bottom:14px">
  <?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> found
</p>

<?php if (!$items): ?>
  <div class="card">
    <div class="empty">
      <?= icon('search', 'i') ?>
      <h3><?= ($q !== '' || $color !== '' || $fCat || $fLoc) ? 'No items match your search' : 'No reports yet' ?></h3>
      <p>Try different keywords, or check back later — new reports from other residents appear here right away.</p>
    </div>
  </div>
<?php else: ?>
  <div class="item-grid">
    <?php foreach ($items as $it):
          $viewUrl = base_url('resident/item_view.php?type=' . $it['rtype'] . '&id=' . (int)$it['id'] . '&tab=' . $tab); ?>
      <div class="item-card" data-href="<?= e($viewUrl) ?>" title="View details" tabindex="0">
        <div class="item-thumb">
          <?php if ($it['photo']): ?>
            <img src="<?= e(photo_url($it['photo'])) ?>" alt="<?= e($it['item_name']) ?>">
          <?php else: ?>
            <?= icon('image', 'i i-lg') ?>
          <?php endif; ?>
        </div>
        <div class="item-body">
          <h4><a href="<?= e($viewUrl) ?>"><?= e($it['item_name']) ?></a></h4>
          <div class="item-meta">
            <div class="row"><?= icon('tag') ?> <?= e($it['category_name']) ?></div>
            <div class="row"><?= icon('pin') ?> <?= e($it['location_name']) ?></div>
            <div class="row"><?= icon('calendar') ?> <?= fmt_date($it['item_date']) ?></div>
            <div class="row"><?= icon('user') ?> <?= e(reporter_label($it['owner_name'], $it['owner_anonymous'] ?? null, $it['reporter_privacy'])) ?></div>
          </div>
          <div class="item-foot">
            <span class="flex" style="gap:6px;min-width:0">
              <span class="badge <?= $it['rtype'] === 'lost' ? 'badge-info' : 'badge-success' ?>">
                <?= $it['rtype'] === 'lost' ? 'Lost' : 'Found' ?>
              </span>
              <span class="faint"><?= e($it['color']) ?></span>
            </span>
            <?php if ($it['rtype'] === 'found' && (int)$it['user_id'] === $uid): ?>
              <span class="faint">Your report</span>
            <?php elseif ($it['rtype'] === 'found' && in_array((int)$it['id'], $myClaimIds, true)): ?>
              <span class="badge badge-warn">Claim submitted</span>
            <?php elseif ($it['rtype'] === 'found' && in_array($it['status'], ['approved', 'matched'], true)): ?>
              <a class="btn btn-teal btn-sm" href="<?= base_url('resident/claims.php?new=' . (int)$it['id']) ?>">
                This is mine →
              </a>
            <?php else: ?>
              <a class="btn btn-ghost btn-sm" href="<?= e($viewUrl) ?>">View details →</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
