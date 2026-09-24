<?php
/**
 * LOST2FOUND — Location management (CRUD)
 */
require_once __DIR__ . '/../config/config.php';
require_admin();

$db = get_db();

/* ---------- POST: create / update / delete ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post('action');
    $name   = post('location_name');

    if ($action === 'delete') {
        try {
            $db->prepare('DELETE FROM locations WHERE id = ?')->execute([(int)post('id')]);
            flash('success', 'Location deleted.');
        } catch (PDOException $e) {
            flash('danger', 'Cannot delete this location — it is still used by item reports.');
        }
        redirect('admin/locations.php');
    }

    if ($name === '') {
        flash('danger', 'Location name is required.');
    } else {
        $id = (int)post('id');
        $dup = $db->prepare('SELECT id FROM locations WHERE location_name = ? AND id != ?');
        $dup->execute([$name, $id]);
        if ($dup->fetch()) {
            flash('danger', 'A location with that name already exists.');
        } elseif ($action === 'create') {
            $db->prepare('INSERT INTO locations (location_name) VALUES (?)')->execute([$name]);
            flash('success', "Location \"{$name}\" added.");
        } else {
            $db->prepare('UPDATE locations SET location_name = ? WHERE id = ?')->execute([$name, $id]);
            flash('success', 'Location updated.');
        }
    }
    redirect('admin/locations.php');
}

/* ---------- Edit target ---------- */
$editLoc = null;
if (isset($_GET['edit'])) {
    $st = $db->prepare('SELECT * FROM locations WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $editLoc = $st->fetch() ?: null;
}

/* ---------- List with usage counts ---------- */
$locations = $db->query(
    "SELECT l.*,
        (SELECT COUNT(*) FROM lost_items  li WHERE li.location_id = l.id)
      + (SELECT COUNT(*) FROM found_items fi WHERE fi.location_id = l.id) AS usage_count
     FROM locations l
     ORDER BY l.location_name"
)->fetchAll();

$title  = 'Locations';
$active = 'locations';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Location Management</h1>
    <p>Locations within Barangay Libis where items get lost or found. Also used by the matching
       engine (25% of the match score).</p>
  </div>
</div>

<div class="grid-2-1">
  <div class="card">
    <div class="card-head"><h3>All locations (<?= count($locations) ?>)</h3></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Location</th><th>Used by</th><th>Created</th><th class="td-actions">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($locations as $l): ?>
          <tr>
            <td><strong><?= e($l['location_name']) ?></strong></td>
            <td><span class="badge badge-plain"><?= (int)$l['usage_count'] ?> report<?= (int)$l['usage_count'] === 1 ? '' : 's' ?></span></td>
            <td class="muted small"><?= fmt_date($l['created_at']) ?></td>
            <td class="td-actions">
              <div class="actions">
                <a class="btn btn-outline btn-sm" href="<?= base_url('admin/locations.php?edit=' . (int)$l['id']) ?>">
                  <?= icon('edit', 'i i-sm') ?> Edit
                </a>
                <form method="post">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$l['id'] ?>">
                  <button class="btn btn-ghost btn-sm" style="color:var(--danger)"
                          data-confirm="Delete location <?= e($l['location_name']) ?>? It cannot be deleted while item reports still use it.">
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
    <div class="card-head"><h3><?= $editLoc ? 'Edit location' : 'Add location' ?></h3></div>
    <div class="card-body">
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editLoc ? 'update' : 'create' ?>">
        <?php if ($editLoc): ?><input type="hidden" name="id" value="<?= (int)$editLoc['id'] ?>"><?php endif; ?>
        <div class="field">
          <label for="location_name">Location name</label>
          <input class="input" id="location_name" name="location_name" maxlength="100"
                 value="<?= e($editLoc['location_name'] ?? '') ?>" placeholder="e.g. Barangay Hall" required>
        </div>
        <div class="flex mt-3">
          <button class="btn btn-teal" type="submit"><?= icon('check', 'i i-sm') ?> <?= $editLoc ? 'Save changes' : 'Add location' ?></button>
          <?php if ($editLoc): ?><a class="btn btn-ghost" href="<?= base_url('admin/locations.php') ?>">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
