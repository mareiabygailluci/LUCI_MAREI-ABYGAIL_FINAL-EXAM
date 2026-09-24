<?php
/**
 * LOST2FOUND — Category management (CRUD)
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$db = get_db();

/* ---------- POST: create / update / delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    $name   = post('category_name');

    if ($action === 'delete') {
        try {
            $db->prepare('DELETE FROM categories WHERE id = ?')->execute([(int)post('id')]);
            flash('success', 'Category deleted.');
        } catch (PDOException $e) {
            flash('danger', 'Cannot delete this category — it is still used by item reports.');
        }
        redirect('admin/categories.php');
    }

    if ($name === '') {
        flash('danger', 'Category name is required.');
    } else {
        $id = (int)post('id');
        $dup = $db->prepare('SELECT id FROM categories WHERE category_name = ? AND id != ?');
        $dup->execute([$name, $id]);
        if ($dup->fetch()) {
            flash('danger', 'A category with that name already exists.');
        } elseif ($action === 'create') {
            $db->prepare('INSERT INTO categories (category_name) VALUES (?)')->execute([$name]);
            flash('success', "Category \"{$name}\" added.");
        } else {
            $db->prepare('UPDATE categories SET category_name = ? WHERE id = ?')->execute([$name, $id]);
            flash('success', 'Category updated.');
        }
    }
    redirect('admin/categories.php');
}

/* ---------- Edit target ---------- */
$editCat = null;
if (isset($_GET['edit'])) {
    $st = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $editCat = $st->fetch() ?: null;
}

/* ---------- List with usage counts ---------- */
$categories = $db->query(
    "SELECT c.*,
        (SELECT COUNT(*) FROM lost_items  l WHERE l.category_id = c.id)
      + (SELECT COUNT(*) FROM found_items f WHERE f.category_id = c.id) AS usage_count
     FROM categories c
     ORDER BY c.category_name"
)->fetchAll();

$title  = 'Categories';
$active = 'categories';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Category Management</h1>
    <p>Categories classify items and feed the automated matching engine (20% of the match score).</p>
  </div>
</div>

<div class="grid-2-1">
  <div class="card">
    <div class="card-head"><h3>All categories (<?= count($categories) ?>)</h3></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Category</th><th>Used by</th><th>Created</th><th class="td-actions">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
          <tr>
            <td><strong><?= e($c['category_name']) ?></strong></td>
            <td><span class="badge badge-plain badge-plain"><?= (int)$c['usage_count'] ?> report<?= (int)$c['usage_count'] === 1 ? '' : 's' ?></span></td>
            <td class="muted small"><?= fmt_date($c['created_at']) ?></td>
            <td class="td-actions">
              <div class="actions">
                <a class="btn btn-outline btn-sm" href="<?= base_url('admin/categories.php?edit=' . (int)$c['id']) ?>">
                  <?= icon('edit', 'i i-sm') ?> Edit
                </a>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                  <button class="btn btn-ghost btn-sm" style="color:var(--danger)"
                          data-confirm="Delete category <?= e($c['category_name']) ?>? It cannot be deleted while item reports still use it.">
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
  </div>

  <div class="card">
    <div class="card-head"><h3><?= $editCat ? 'Edit category' : 'Add category' ?></h3></div>
    <div class="card-body">
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editCat ? 'update' : 'create' ?>">
        <?php if ($editCat): ?><input type="hidden" name="id" value="<?= (int)$editCat['id'] ?>"><?php endif; ?>
        <div class="field">
          <label for="category_name">Category name</label>
          <input class="input" id="category_name" name="category_name" maxlength="80"
                 value="<?= e($editCat['category_name'] ?? '') ?>" placeholder="e.g. Electronics" required>
        </div>
        <div class="flex mt-3">
          <button class="btn btn-teal" type="submit"><?= icon('check', 'i i-sm') ?> <?= $editCat ? 'Save changes' : 'Add category' ?></button>
          <?php if ($editCat): ?><a class="btn btn-ghost" href="<?= base_url('admin/categories.php') ?>">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
