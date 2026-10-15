<?php
/**
 * LOST2FOUND — My Found Reports (read / update / delete)
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$uid = (int)$_SESSION['user']['id'];
$db  = get_db();

/* Delete own report — always allowed, matches/claims cascade with it */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete') {
    verify_csrf();
    $id = (int)post('id');
    $st = $db->prepare('SELECT * FROM found_items WHERE id = ? AND user_id = ?');
    $st->execute([$id, $uid]);
    if ($item = $st->fetch()) {
        delete_uploaded_image($item['photo']);
        $db->prepare('DELETE FROM found_items WHERE id = ?')->execute([$id]);
        flash('success', 'Found report "' . $item['item_name'] . '" was deleted.');
    }
    redirect('resident/found_items.php');
}

$items = $db->prepare(
    'SELECT fi.*, c.category_name, loc.location_name
     FROM found_items fi
     JOIN categories c ON c.id = fi.category_id
     JOIN locations loc ON loc.id = fi.location_id
     WHERE fi.user_id = ?
     ORDER BY fi.created_at DESC'
);
$items->execute([$uid]);
$items = $items->fetchAll();

$title  = 'My Found Reports';
$active = 'found';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>My Found Reports</h1>
    <p>Items you found and reported. Once the system matches your item to a lost report, its owner can file a claim request.</p>
  </div>
</div>

<?php if (!$items): ?>
  <div class="card">
    <div class="empty">
      <?= icon('gift', 'i') ?>
      <h3>No found reports yet</h3>
      <p>Found something in the barangay? Report it here so its owner can claim it through
         the barangay office.</p>
      <a class="btn btn-teal mt-3" href="<?= base_url('resident/item_form.php?type=found') ?>">Report a Found Item</a>
    </div>
  </div>
<?php else: ?>
  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Item</th><th>Category</th><th>Location</th><th>Date Found</th><th>Status</th><th class="td-actions">Actions</th></tr>
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
            <td class="muted"><?= fmt_date($it['date_found']) ?></td>
            <td><?= status_badge($it['status']) ?></td>
            <td class="td-actions">
              <div class="actions">
                <a class="btn btn-outline btn-sm" href="<?= base_url('resident/item_form.php?type=found&id=' . (int)$it['id']) ?>">
                  <?= icon('edit', 'i i-sm') ?> Edit
                </a>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
                  <button class="btn btn-ghost btn-sm" style="color:var(--danger)" data-confirm="Delete this found report and its photo? This cannot be undone.">
                    <?= icon('trash', 'i i-sm') ?> Delete
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="mt-3">
    <a class="btn btn-teal" href="<?= base_url('resident/item_form.php?type=found') ?>"><?= icon('plus', 'i i-sm') ?> New Found Report</a>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
