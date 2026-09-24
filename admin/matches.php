<?php
/**
 * LOST2FOUND — Admin: possible matches review (confirm / dismiss)
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$db = get_db();

/** True if the item still has other open matches (pending/confirmed). */
function has_open_matches(PDO $db, string $side, int $itemId, int $excludeMatchId): bool {
    $col = $side === 'lost' ? 'lost_item_id' : 'found_item_id';
    $st = $db->prepare(
        "SELECT COUNT(*) FROM matches
         WHERE {$col} = ? AND id != ? AND status IN ('pending','confirmed')"
    );
    $st->execute([$itemId, $excludeMatchId]);
    return (int)$st->fetchColumn() > 0;
}

/* ---------- GET actions: confirm / dismiss ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') !== '') {
    verify_csrf();
    $id = (int)($_GET['id'] ?? 0);

    $st = $db->prepare(
        'SELECT m.*, l.item_name AS lost_name, f.item_name AS found_name,
                l.user_id AS lost_user, f.user_id AS found_user
         FROM matches m
         JOIN lost_items l ON l.id = m.lost_item_id
         JOIN found_items f ON f.id = m.found_item_id
         WHERE m.id = ?'
    );
    $st->execute([$id]);
    $m = $st->fetch();

    if (!$m) {
        flash('danger', 'Match not found.');
    } elseif (($_GET['action'] ?? '') === 'confirm' && $m['status'] === 'pending') {
        $db->prepare("UPDATE matches SET status = 'confirmed' WHERE id = ?")->execute([$id]);
        notify((int)$m['lost_user'], "Great news! The match for your lost item \"{$m['lost_name']}\" has been verified by the barangay admin. You may now file a claim request.", 'match', 'resident/matches.php');
        notify((int)$m['found_user'], "The match for your found item \"{$m['found_name']}\" has been verified. Waiting for the owner's claim request.", 'match', 'resident/matches.php');
        flash('success', 'Match confirmed. Both owners have been notified.');
    } elseif (($_GET['action'] ?? '') === 'dismiss' && $m['status'] !== 'dismissed') {
        $db->prepare("UPDATE matches SET status = 'dismissed' WHERE id = ?")->execute([$id]);

        // If neither item has other open matches, revert them from "matched" to "approved"
        foreach (['lost' => (int)$m['lost_item_id'], 'found' => (int)$m['found_item_id']] as $side => $itemId) {
            if (!has_open_matches($db, $side, $itemId, $id)) {
                $table = $side . '_items';
                $db->prepare("UPDATE {$table} SET status = 'approved' WHERE id = ? AND status = 'matched'")->execute([$itemId]);
            }
        }
        notify((int)$m['lost_user'], "A possible match involving your lost item \"{$m['lost_name']}\" was reviewed and dismissed by the admin.", 'match', 'resident/matches.php');
        notify((int)$m['found_user'], "A possible match involving your found item \"{$m['found_name']}\" was reviewed and dismissed by the admin.", 'match', 'resident/matches.php');
        flash('success', 'Match dismissed. Items reverted to approved if no other open matches.');
    } else {
        flash('warn', 'That action is not available for this match.');
    }
    redirect('admin/matches.php');
}

/* ---------- List ---------- */
$fStatus = $_GET['status'] ?? '';
$where = ''; $params = [];
if (in_array($fStatus, ['pending', 'confirmed', 'dismissed'], true)) {
    $where = ' WHERE m.status = ?';
    $params = [$fStatus];
}

$st = $db->prepare(
    "SELECT m.*, l.item_name AS lost_name, l.status AS lost_status, u1.full_name AS lost_owner,
            f.item_name AS found_name, f.status AS found_status, u2.full_name AS found_owner
     FROM matches m
     JOIN lost_items l ON l.id = m.lost_item_id
     JOIN found_items f ON f.id = m.found_item_id
     JOIN users u1 ON u1.id = l.user_id
     JOIN users u2 ON u2.id = f.user_id"
    . $where .
    " ORDER BY m.status = 'pending' DESC, m.match_score DESC, m.created_at DESC"
);
$st->execute($params);
$matches = $st->fetchAll();

$scoreClass = fn(int $s) => $s >= 85 ? '' : ($s >= 70 ? ' mid' : ' low');

$title  = 'Possible Matches';
$active = 'matches';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Possible Matches</h1>
    <p>The matching engine verifies reports automatically (threshold: 60% accuracy).
       Matches rated 85% or higher are auto-confirmed by the system. Use this page only to
       dismiss false positives.</p>
  </div>
</div>

<form class="filter-bar" method="get">
  <div class="field fit">
    <label>Status</label>
    <select class="input" name="status">
      <option value="">All statuses</option>
      <?php foreach (['pending', 'confirmed', 'dismissed'] as $s): ?>
        <option value="<?= $s ?>"<?= $fStatus === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-primary" type="submit">Filter</button>
  <a class="btn btn-ghost" href="<?= base_url('admin/matches.php') ?>">Reset</a>
</form>

<div class="card">
  <?php if (!$matches): ?>
    <div class="empty">
      <?= icon('link', 'i') ?>
      <h3>No matches</h3>
      <p>Matches appear automatically when lost and found reports score 60% accuracy or higher.
       High-accuracy matches (85%+) are verified by the system.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Lost item</th><th>Found item</th><th>Score</th><th>Status</th><th>Detected</th><th class="td-actions">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($matches as $m): ?>
          <tr>
            <td>
              <strong><?= e($m['lost_name']) ?></strong>
              <div class="faint"><?= e($m['lost_owner']) ?> · <?= status_badge($m['lost_status']) ?></div>
            </td>
            <td>
              <strong><?= e($m['found_name']) ?></strong>
              <div class="faint"><?= e($m['found_owner']) ?> · <?= status_badge($m['found_status']) ?></div>
            </td>
            <td><div class="score-ring<?= $scoreClass((int)$m['match_score']) ?>" style="width:48px;height:48px;font-size:.8rem"><?= (int)$m['match_score'] ?>%</div></td>
            <td><?= status_badge($m['status']) ?></td>
            <td class="muted small"><?= time_ago($m['created_at']) ?></td>
            <td class="td-actions">
              <div class="actions">
                <a class="btn btn-outline btn-sm" href="<?= base_url('admin/item_view.php?type=lost&id=' . (int)$m['lost_item_id']) ?>">
                  <?= icon('eye', 'i i-sm') ?> View
                </a>
                <?php if ($m['status'] === 'pending'): ?>
                  <a class="btn btn-teal btn-sm"
                     href="<?= base_url('admin/matches.php?action=confirm&id=' . (int)$m['id'] . '&csrf=' . csrf_token()) ?>">
                    <?= icon('check', 'i i-sm') ?> Confirm
                  </a>
                  <a class="btn btn-ghost btn-sm" style="color:var(--danger)"
                     href="<?= base_url('admin/matches.php?action=dismiss&id=' . (int)$m['id'] . '&csrf=' . csrf_token()) ?>"
                     data-confirm="Dismiss this match? Both items go back to approved if they have no other open matches.">
                    <?= icon('x', 'i i-sm') ?> Dismiss
                  </a>
                <?php elseif ($m['status'] === 'confirmed'): ?>
                  <a class="btn btn-ghost btn-sm" style="color:var(--danger)"
                     href="<?= base_url('admin/matches.php?action=dismiss&id=' . (int)$m['id'] . '&csrf=' . csrf_token()) ?>"
                     data-confirm="Dismiss this confirmed match?">
                    Dismiss
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
