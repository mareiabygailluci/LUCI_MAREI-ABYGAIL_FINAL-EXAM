<?php
/**
 * LOST2FOUND — Possible matches (unique feature, resident view)
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$uid = (int)$_SESSION['user']['id'];
$db  = get_db();

$matches = $db->prepare(
    "SELECT m.*, m.status AS match_status,
            l.id AS lost_id, l.item_name AS lost_name, l.description AS lost_desc,
            l.color AS lost_color, l.brand AS lost_brand, l.date_lost, l.status AS lost_status,
            c1.category_name AS lost_cat, lo1.location_name AS lost_loc,
            f.id AS found_id, f.item_name AS found_name, f.description AS found_desc,
            f.color AS found_color, f.brand AS found_brand, f.date_found, f.status AS found_status,
            c2.category_name AS found_cat, lo2.location_name AS found_loc,
            l.user_id AS lost_user, f.user_id AS found_user
     FROM matches m
     JOIN lost_items  l   ON l.id  = m.lost_item_id
     JOIN found_items f   ON f.id  = m.found_item_id
     JOIN categories  c1  ON c1.id = l.category_id
     JOIN locations   lo1 ON lo1.id = l.location_id
     JOIN categories  c2  ON c2.id = f.category_id
     JOIN locations   lo2 ON lo2.id = f.location_id
     WHERE l.user_id = ? OR f.user_id = ?
     ORDER BY m.status = 'pending' DESC, m.match_score DESC, m.created_at DESC"
);
$matches->execute([$uid, $uid]);
$matches = $matches->fetchAll();

/* My claims, so we don't offer to claim twice */
$myClaims = $db->prepare("SELECT found_item_id FROM claim_requests WHERE claimant_id = ? AND status IN ('pending','approved')");
$myClaims->execute([$uid]);
$myClaimIds = array_map('intval', $myClaims->fetchAll(PDO::FETCH_COLUMN));

$scoreClass = fn(int $s) => $s >= 85 ? '' : ($s >= 70 ? ' mid' : ' low');

$title  = 'Possible Matches';
$active = 'matches';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Possible Matches</h1>
    <p>The system automatically compares lost and found reports using weighted criteria:
       category, color, location, date, and brand — then rates the match accuracy as a percentage.
       Matches rated 85% or higher are auto-verified by the system.</p>
  </div>
</div>

<?php if (!$matches): ?>
  <div class="card">
    <div class="empty">
      <?= icon('link', 'i') ?>
      <h3>No matches yet</h3>
      <p>When your lost report lines up with a found report (or vice versa), the possible match
         will appear here automatically.</p>
      <a class="btn btn-teal mt-3" href="<?= base_url('resident/browse.php') ?>">Browse items</a>
    </div>
  </div>
<?php else: ?>
  <?php foreach ($matches as $m): ?>
    <div class="card mb-3">
      <div class="card-head">
        <div class="flex">
          <div class="score-ring<?= $scoreClass((int)$m['match_score']) ?>"><?= (int)$m['match_score'] ?>%</div>
          <div>
            <h3><?= e($m['lost_name']) ?> &harr; <?= e($m['found_name']) ?></h3>
            <div class="faint">Matched <?= time_ago($m['created_at']) ?></div>
          </div>
        </div>
        <div class="flex">
          <?= status_badge($m['match_status']) ?>
        </div>
      </div>

      <div class="compare">
        <div class="compare-col">
          <h4><?= icon('box', 'i i-sm') ?> Lost report</h4>
          <div class="kv"><span class="k">Item</span><span class="v"><?= e($m['lost_name']) ?></span></div>
          <div class="kv"><span class="k">Category</span><span class="v"><?= e($m['lost_cat']) ?></span></div>
          <div class="kv"><span class="k">Color</span><span class="v"><?= e($m['lost_color']) ?></span></div>
          <div class="kv"><span class="k">Brand</span><span class="v"><?= e($m['lost_brand'] ?? '—') ?></span></div>
          <div class="kv"><span class="k">Location</span><span class="v"><?= e($m['lost_loc']) ?></span></div>
          <div class="kv"><span class="k">Date lost</span><span class="v"><?= fmt_date($m['date_lost']) ?></span></div>
          <div class="kv"><span class="k">Description</span><span class="v" style="max-width:280px;text-align:right"><?= e(mb_strimwidth($m['lost_desc'], 0, 90, '…')) ?></span></div>
        </div>
        <div class="compare-col">
          <h4><?= icon('gift', 'i i-sm') ?> Found report</h4>
          <div class="kv"><span class="k">Item</span><span class="v"><?= e($m['found_name']) ?></span></div>
          <div class="kv"><span class="k">Category</span><span class="v"><?= e($m['found_cat']) ?></span></div>
          <div class="kv"><span class="k">Color</span><span class="v"><?= e($m['found_color']) ?></span></div>
          <div class="kv"><span class="k">Brand</span><span class="v"><?= e($m['found_brand'] ?? '—') ?></span></div>
          <div class="kv"><span class="k">Location</span><span class="v"><?= e($m['found_loc']) ?></span></div>
          <div class="kv"><span class="k">Date found</span><span class="v"><?= fmt_date($m['date_found']) ?></span></div>
          <div class="kv"><span class="k">Description</span><span class="v" style="max-width:280px;text-align:right"><?= e(mb_strimwidth($m['found_desc'], 0, 90, '…')) ?></span></div>
        </div>
      </div>

      <div class="card-body p-sm flex-between">
        <div class="flex">
          <span class="small muted">Lost item:</span> <?= status_badge($m['lost_status']) ?>
          <span class="small muted" style="margin-left:10px">Found item:</span> <?= status_badge($m['found_status']) ?>
        </div>
        <?php if ((int)$m['found_user'] === $uid): ?>
          <span class="faint">You reported this found item — waiting for the owner's claim.</span>
        <?php elseif ((int)$m['lost_user'] === $uid && $m['match_status'] !== 'dismissed' && !in_array($m['found_status'], ['returned', 'closed'], true)): ?>
          <?php if (in_array((int)$m['found_id'], $myClaimIds, true)): ?>
            <span class="badge badge-warn">Claim already submitted</span>
          <?php else: ?>
            <a class="btn btn-teal btn-sm" href="<?= base_url('resident/claims.php?new=' . (int)$m['found_id']) ?>">
              <?= icon('shield', 'i i-sm') ?> File a claim request
            </a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
