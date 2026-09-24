<?php
/**
 * LOST2FOUND — Admin: lost reports management
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$db = get_db();

/* ---------- GET actions: approve / reject ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') !== '') {
    verify_csrf();
    $id = (int)($_GET['id'] ?? 0);

    $st = $db->prepare('SELECT li.*, u.full_name FROM lost_items li JOIN users u ON u.id = li.user_id WHERE li.id = ?');
    $st->execute([$id]);
    $item = $st->fetch();

    if (!$item) {
        flash('danger', 'Report not found.');
    } elseif (($_GET['action'] ?? '') === 'approve' && $item['status'] === 'pending') {
        $db->prepare("UPDATE lost_items SET status = 'approved' WHERE id = ?")->execute([$id]);
        notify((int)$item['user_id'], "Your lost item report \"{$item['item_name']}\" has been approved by the barangay admin.", 'status', 'resident/lost_items.php');
        run_matching($id, null);
        flash('success', 'Report approved. Matching has been run against found items.');
    } elseif (($_GET['action'] ?? '') === 'reject' && in_array($item['status'], ['pending', 'approved'], true)) {
        $db->prepare("UPDATE lost_items SET status = 'rejected' WHERE id = ?")->execute([$id]);
        notify((int)$item['user_id'], "Your lost item report \"{$item['item_name']}\" was rejected by the barangay admin. Please contact the barangay office for details.", 'status', 'resident/lost_items.php');
        flash('success', 'Report rejected.');
    } else {
        flash('warn', 'That action is not available for this report.');
    }
    redirect('admin/lost_items.php');
}

/* ---------- POST: delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    verify_csrf();
    $id = (int)post('id');
    $st = $db->prepare('SELECT photo FROM lost_items WHERE id = ?');
    $st->execute([$id]);
    if ($photo = $st->fetchColumn()) delete_uploaded_image($photo);
    $db->prepare('DELETE FROM lost_items WHERE id = ?')->execute([$id]);
    flash('success', 'Lost report deleted.');
    redirect('admin/lost_items.php');
}

/* ---------- Filters ---------- */
$where = []; $params = [];
$q = trim((string)($_GET['q'] ?? ''));
if ($q !== '') {
    $where[]  = '(li.item_name LIKE ? OR li.description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
$fStatus = $_GET['status'] ?? '';
if (in_array($fStatus, ['pending', 'approved', 'rejected', 'matched', 'claimed', 'closed'], true)) {
    $where[] = 'li.status = ?'; $params[] = $fStatus;
}

$st = $db->prepare(
    'SELECT li.*, u.full_name AS reporter, c.category_name, loc.location_name
     FROM lost_items li
     JOIN users u ON u.id = li.user_id
     JOIN categories c ON c.id = li.category_id
     JOIN locations loc ON loc.id = li.location_id'
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY li.created_at DESC'
);
$st->execute($params);
$items = $st->fetchAll();

$title  = 'Lost Reports';
$active = 'lost';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Lost Reports</h1>
    <p>Reports are matched against the opposite reports automatically by the matching engine.
       Admin moderation is still available — reject reports that are invalid.</p>
  </div>
</div>

<form class="filter-bar" method="get">
  <div class="field" style="flex:2">
    <label>Search</label>
    <input class="input" name="q" value="<?= e($q) ?>" placeholder="Item name or description…">
  </div>
  <div class="field fit">
    <label>Status</label>
    <select class="input" name="status">
      <option value="">All statuses</option>
      <?php foreach (['pending', 'approved', 'rejected', 'matched', 'claimed', 'closed'] as $s): ?>
        <option value="<?= $s ?>"<?= $fStatus === $s ? ' selected' : '' ?>><?= e(ucfirst($s)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button class="btn btn-primary" type="submit"><?= icon('search', 'i i-sm') ?> Filter</button>
  <a class="btn btn-ghost" href="<?= base_url('admin/lost_items.php') ?>">Reset</a>
</form>

<div class="card">
  <?php if (!$items): ?>
    <div class="empty">
      <?= icon('inbox', 'i') ?>
      <h3>No lost reports</h3>
      <p>No reports match the current filters.</p>
    </div>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Item</th><th>Reporter</th><th>Category / Location</th><th>Date Lost</th><th>Status</th><th class="td-actions">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $it): ?>
          <tr>
            <td>
              <div class="flex">
                <?php if ($it['photo']): ?>
                  <img class="thumb-sm" src="<?= e(photo_url($it['photo'])) ?>" alt="">
                <?php else: ?>
                  <span class="thumb-sm placeholder"><?= icon('image', 'i i-sm') ?></span>
                <?php endif; ?>
                <div>
                  <strong><?= e($it['item_name']) ?></strong>
                  <div class="faint"><?= e($it['color']) ?><?= $it['brand'] ? ' · ' . e($it['brand']) : '' ?></div>
                </div>
              </div>
            </td>
            <td class="muted"><?= e($it['reporter']) ?></td>
            <td class="muted small"><?= e($it['category_name']) ?><br><?= e($it['location_name']) ?></td>
            <td class="muted"><?= fmt_date($it['date_lost']) ?></td>
            <td><?= status_badge($it['status']) ?></td>
            <td class="td-actions">
              <div class="actions">
                <a class="btn btn-outline btn-sm" href="<?= base_url('admin/item_view.php?type=lost&id=' . (int)$it['id']) ?>">
                  <?= icon('eye', 'i i-sm') ?> View
                </a>
                <?php if ($it['status'] === 'pending'): ?>
                  <a class="btn btn-teal btn-sm"
                     href="<?= base_url('admin/lost_items.php?action=approve&id=' . (int)$it['id'] . '&csrf=' . csrf_token()) ?>">
                    <?= icon('check', 'i i-sm') ?> Approve
                  </a>
                <?php endif; ?>
                <?php if (in_array($it['status'], ['pending', 'approved'], true)): ?>
                  <a class="btn btn-ghost btn-sm" style="color:var(--danger)"
                     href="<?= base_url('admin/lost_items.php?action=reject&id=' . (int)$it['id'] . '&csrf=' . csrf_token()) ?>"
                     data-confirm="Reject this lost report? The owner will be notified.">
                    <?= icon('x', 'i i-sm') ?> Reject
                  </a>
                <?php endif; ?>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <button class="btn btn-ghost btn-sm" style="color:var(--danger)"
                          data-confirm="Permanently delete this lost report and its photo?">
                    <?= icon('trash', 'i i-sm') ?>
                  </button>
                </form>
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
