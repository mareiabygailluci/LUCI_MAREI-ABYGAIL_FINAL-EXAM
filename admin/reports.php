<?php
/**
 * LOST2FOUND — Admin: statistics & reports
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$db = get_db();

$stats = $db->query(
    "SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'resident')           AS residents,
        (SELECT COUNT(*) FROM lost_items)                              AS lost,
        (SELECT COUNT(*) FROM found_items)                             AS found,
        (SELECT COUNT(*) FROM matches)                                 AS matches,
        (SELECT COUNT(*) FROM matches WHERE status = 'confirmed')      AS confirmed,
        (SELECT COUNT(*) FROM claim_requests)                          AS claims,
        (SELECT COUNT(*) FROM claim_requests WHERE status = 'approved') AS claims_approved,
        (SELECT COUNT(*) FROM found_items WHERE status = 'returned')   AS returned,
        (SELECT COALESCE(ROUND(AVG(match_score)), 0) FROM matches)     AS avg_score"
)->fetch();

$lostByCat = $db->query(
    "SELECT c.category_name lbl, COUNT(li.id) n
     FROM categories c LEFT JOIN lost_items li ON li.category_id = c.id
     GROUP BY c.id HAVING n > 0 ORDER BY n DESC"
)->fetchAll();

$foundByCat = $db->query(
    "SELECT c.category_name lbl, COUNT(fi.id) n
     FROM categories c LEFT JOIN found_items fi ON fi.category_id = c.id
     GROUP BY c.id HAVING n > 0 ORDER BY n DESC"
)->fetchAll();

$byLocation = $db->query(
    "SELECT l.location_name lbl,
        (SELECT COUNT(*) FROM lost_items  li WHERE li.location_id = l.id)
      + (SELECT COUNT(*) FROM found_items fi WHERE fi.location_id = l.id) n
     FROM locations l
     GROUP BY l.id HAVING n > 0
     ORDER BY n DESC LIMIT 8"
)->fetchAll();

$lostStatusRows = $db->query('SELECT status, COUNT(*) n FROM lost_items GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
$lostStatus = [];
foreach (['pending', 'approved', 'matched', 'claimed', 'closed', 'rejected'] as $s) {
    $lostStatus[ucfirst($s)] = (int)($lostStatusRows[$s] ?? 0);
}

$lostCount  = max((int)$stats['lost'], 1);
$returnRate = round((int)$stats['returned'] / $lostCount * 100);

$title  = 'Statistics';
$active = 'reports';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Statistics &amp; Reports</h1>
    <p>System-wide figures for documentation and barangay reporting.</p>
  </div>
</div>

<div class="stat-grid">
  <div class="stat">
    <div class="stat-ico bg-primary"><?= icon('users') ?></div>
    <div><div class="stat-num"><?= (int)$stats['residents'] ?></div><div class="stat-lbl">Registered Residents</div></div>
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
    <div><div class="stat-num"><?= (int)$stats['claims'] ?></div><div class="stat-lbl">Claims (<?= (int)$stats['claims_approved'] ?> approved)</div></div>
  </div>
  <div class="stat">
    <div class="stat-ico bg-success"><?= icon('check-circle') ?></div>
    <div><div class="stat-num"><?= (int)$stats['returned'] ?></div><div class="stat-lbl">Items Returned</div></div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><h3>Lost reports by category</h3></div>
    <div class="card-body"><?= render_chart(array_column($lostByCat, 'n', 'lbl')) ?></div>
  </div>
  <div class="card">
    <div class="card-head"><h3>Found reports by category</h3></div>
    <div class="card-body"><?= render_chart(array_column($foundByCat, 'n', 'lbl'), true) ?></div>
  </div>
  <div class="card">
    <div class="card-head"><h3>Reports per location</h3></div>
    <div class="card-body"><?= render_chart(array_column($byLocation, 'n', 'lbl')) ?></div>
  </div>
  <div class="card">
    <div class="card-head"><h3>Lost reports by status</h3></div>
    <div class="card-body"><?= render_chart($lostStatus, true) ?></div>
  </div>
</div>

<div class="card mt-3" style="max-width:520px">
  <div class="card-head"><h3>Key figures</h3></div>
  <div class="card-body">
    <div class="kv"><span class="k">Average match score</span><span class="v"><?= (int)$stats['avg_score'] ?>%</span></div>
    <div class="kv"><span class="k">Confirmed matches</span><span class="v"><?= (int)$stats['confirmed'] ?></span></div>
    <div class="kv"><span class="k">Return rate (returned vs lost)</span><span class="v"><?= $returnRate ?>%</span></div>
    <div class="kv"><span class="k">Claim approval rate</span><span class="v"><?= (int)$stats['claims'] > 0 ? round((int)$stats['claims_approved'] / (int)$stats['claims'] * 100) : 0 ?>%</span></div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
