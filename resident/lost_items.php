<?php
/**
 * LOST2FOUND — My Lost Reports (read / update / delete)
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$uid = (int)$_SESSION['user']['id'];
$db  = get_db();

/* Delete own report — allowed while not matched/claimed/closed */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    verify_csrf();
    $id = (int)post('id');
    $st = $db->prepare('SELECT * FROM lost_items WHERE id = ? AND user_id = ?');
    $st->execute([$id, $uid]);
    if ($item = $st->fetch()) {
        if (in_array($item['status'], ['pending', 'approved', 'rejected'], true)) {
            delete_uploaded_image($item['photo']);
            $db->prepare('DELETE FROM lost_items WHERE id = ?')->execute([$id]);
            flash('success', 'Lost report "' . $item['item_name'] . '" was deleted.');
        } else {
            flash('warn', 'This report can no longer be deleted because it is already being processed.');
        }
    }
    redirect('resident/lost_items.php');
}

$items = $db->prepare(
    'SELECT li.*, c.category_name, loc.location_name
     FROM lost_items li
     JOIN categories c ON c.id = li.category_id
     JOIN locations loc ON loc.id = li.location_id
     WHERE li.user_id = ?
     ORDER BY li.created_at DESC'
);
$items->execute([$uid]);
$items = $items->fetchAll();

$editable = fn(string $s) => in_array($s, ['pending', 'approved', 'rejected'], true);

$title  = 'My Lost Reports';
$active = 'lost';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>My Lost Reports</h1>
    <p>Items you reported as lost. You can edit or delete a report while it is still being processed.</p>
  </div>
  <a class="btn btn-teal" href="<?= base_url('resident/item_form.php?type=lost') ?>"><?= icon('plus', 'i i-sm') ?> New Lost Report</a>
</div>

<?php if (!$items): ?>
  <div class="card">
    <div class="empty">
      <?= icon('box', 'i') ?>
      <h3>No lost reports yet</h3>
      <p>When you lose an item, report it here. The system will automatically compare it
         against found items in the barangay.</p>
      <a class="btn btn-teal mt-3" href="<?= base_url('resident/item_form.php?type=lost') ?>">Report a Lost Item</a>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Item</th><th>Category</th><th>Location</th><th>Date Lost</th><th>Status</th><th class="td-actions">Actions</th></tr>
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
            <td class="muted"><?= e($it['category_name']) ?></td>
            <td class="muted"><?= e($it['location_name']) ?></td>
            <td class="muted"><?= fmt_date($it['date_lost']) ?></td>
            <td><?= status_badge($it['status']) ?></td>
            <td class="td-actions">
              <?php if ($editable($it['status'])): ?>
                <div class="actions">
                  <a class="btn btn-outline btn-sm" href="<?= base_url('resident/item_form.php?type=lost&id=' . (int)$it['id']) ?>">
                    <?= icon('edit', 'i i-sm') ?> Edit
                  </a>
                  <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                    <button class="btn btn-ghost btn-sm" style="color:var(--danger)" data-confirm="Delete this lost report and its photo? This cannot be undone.">
                      <?= icon('trash', 'i i-sm') ?> Delete
                    </button>
                  </form>
                </div>
              <?php else: ?>
                <span class="faint">Locked — under review</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
