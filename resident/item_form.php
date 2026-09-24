<?php
/**
 * LOST2FOUND — Report item form (create + edit, lost & found)
 * Usage: item_form.php?type=lost|found          → create
 *        item_form.php?type=lost|found&id=N     → edit own report
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$uid = (int)$_SESSION['user']['id'];
$db  = get_db();

$type   = ($_GET['type'] ?? $_POST['type'] ?? 'lost') === 'found' ? 'found' : 'lost';
$id     = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editing = $id > 0;
$dateCol = 'date_' . $type;
$listUrl = 'resident/' . $type . '_items.php';

$myShowName = (int)($_SESSION['user']['show_name'] ?? 1) === 1;
$myAnon     = ensure_anonymous_name($uid);

$categories = $db->query('SELECT id, category_name FROM categories ORDER BY category_name')->fetchAll();
$locations  = $db->query('SELECT id, location_name FROM locations ORDER BY location_name')->fetchAll();

$item = null;
if ($editing) {
    $st = $db->prepare("SELECT * FROM {$type}_items WHERE id = ? AND user_id = ?");
    $st->execute([$id, $uid]);
    $item = $st->fetch();

    if (!$item) {
        flash('danger', 'Report not found or you do not have permission to edit it.');
        redirect($listUrl);
    }
    if (!in_array($item['status'], ['pending', 'approved', 'rejected'], true)) {
        flash('warn', 'This report is already being processed and can no longer be edited.');
        redirect($listUrl);
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $itemName  = post('item_name');
    $desc      = post('description');
    $color     = post('color');
    $brand     = post('brand'); // optional
    $catId     = (int)post('category_id');
    $locId     = (int)post('location_id');
    $dateVal   = post($dateCol);
    $privacy   = post('reporter_privacy') === 'anonymous' ? 'anonymous' : 'name';

    if ($itemName === '')                     $errors[] = 'Item name is required.';
    if (mb_strlen($itemName) > 100)           $errors[] = 'Item name must be 100 characters or fewer.';
    if ($desc === '')                         $errors[] = 'Description is required.';
    if ($color === '')                        $errors[] = 'Color is required.';
    if (!$catId || !$locId)                   $errors[] = 'Please choose a category and a location.';
    if ($dateVal === '' || !strtotime($dateVal)) {
        $errors[] = 'A valid date is required.';
    } elseif (strtotime($dateVal) > time()) {
        $errors[] = 'The date cannot be in the future.';
    }

    /* Optional photo (replaces the old one when editing) */
    $photo = null;
    try {
        $photo = handle_image_upload('photo', $type);
    } catch (RuntimeException $ex) {
        $errors[] = $ex->getMessage();
    }

    if (!$errors) {
        if ($editing) {
            if ($photo) {
                delete_uploaded_image($item['photo']);
            } else {
                $photo = $item['photo'];
            }
            $db->prepare(
                "UPDATE {$type}_items
                 SET item_name = ?, description = ?, color = ?, brand = ?,
                     category_id = ?, location_id = ?, {$dateCol} = ?, photo = ?, reporter_privacy = ?
                 WHERE id = ?"
            )->execute([$itemName, $desc, $color, $brand !== '' ? $brand : null,
                        $catId, $locId, $dateVal, $photo, $privacy, $id]);

            run_matching($type === 'lost' ? $id : null, $type === 'found' ? $id : null);
            flash('success', 'Report updated. The matching engine has re-checked your item.');
        } else {
            $db->prepare(
                "INSERT INTO {$type}_items
                    (user_id, category_id, location_id, item_name, description, color, brand, {$dateCol}, photo, status, reporter_privacy)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?)"
            )->execute([$uid, $catId, $locId, $itemName, $desc, $color,
                        $brand !== '' ? $brand : null, $dateVal, $photo, $privacy]);
            $newId = (int)$db->lastInsertId();

            run_matching($type === 'lost' ? $newId : null, $type === 'found' ? $newId : null);
            notify_admins(
                'New ' . $type . ' item report "' . $itemName . '" was submitted and added to the matching engine automatically.',
                'status', 'admin/' . $type . '_items.php'
            );
            flash('success', 'Report submitted! The matching engine has automatically checked it against the opposite reports. You will be notified of any possible matches with their accuracy rating.');
        }
        redirect($listUrl);
    }
}

/* Values to re-display after a failed submit or when editing */
$val = fn(string $key, $fallback = '') => e((string)($_POST[$key] ?? $fallback ?? ''));
$sel = fn($a, $b) => ((string)$a === (string)$b ? ' selected' : '');

$title  = ($editing ? 'Edit ' : 'Report ') . ($type === 'lost' ? 'Lost Item' : 'Found Item');
$active = $type;
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1><?= e($title) ?></h1>
    <p><?= $editing
            ? 'Update the details of your report. Only while it is still being processed.'
            : 'Fill out the details below. Fields marked with * are required.' ?></p>
  </div>
  <a class="btn btn-ghost" href="<?= base_url($listUrl) ?>"><?= icon('back', 'i i-sm') ?> Back to my reports</a>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger">
    <span><strong>Please fix the following:</strong><br>&bull; <?= implode('<br>&bull; ', array_map('e', $errors)) ?></span>
  </div>
<?php endif; ?>

<div class="card" style="max-width:860px">
  <div class="card-body">
    <form method="post" enctype="multipart/form-data" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="type" value="<?= e($type) ?>">
      <?php if ($editing): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

      <div class="form-grid">
        <div class="field">
          <label for="item_name">Item name <span class="req">*</span></label>
          <input class="input" id="item_name" name="item_name" maxlength="100"
                 value="<?= $val('item_name', $item['item_name'] ?? '') ?>" placeholder="e.g. Black Wallet">
        </div>
        <div class="field">
          <label for="category_id">Category <span class="req">*</span></label>
          <select class="input" id="category_id" name="category_id" required>
            <option value="">Select category…</option>
            <?php foreach ($categories as $c): ?>
              <option value="<?= (int)$c['id'] ?>"<?= $sel($_POST['category_id'] ?? ($item['category_id'] ?? ''), $c['id']) ?>>
                <?= e($c['category_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="color">Color <span class="req">*</span></label>
          <input class="input" id="color" name="color" maxlength="40"
                 value="<?= $val('color', $item['color'] ?? '') ?>" placeholder="e.g. Black">
        </div>
        <div class="field">
          <label for="brand">Brand <span class="hint-inline muted small">(optional)</span></label>
          <input class="input" id="brand" name="brand" maxlength="60"
                 value="<?= $val('brand', $item['brand'] ?? '') ?>" placeholder="e.g. Seiko">
        </div>
        <div class="field">
          <label for="location_id"><?= $type === 'lost' ? 'Location where it was lost' : 'Location where it was found' ?> <span class="req">*</span></label>
          <select class="input" id="location_id" name="location_id" required>
            <option value="">Select location…</option>
            <?php foreach ($locations as $l): ?>
              <option value="<?= (int)$l['id'] ?>"<?= $sel($_POST['location_id'] ?? ($item['location_id'] ?? ''), $l['id']) ?>>
                <?= e($l['location_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="date_field">Date <?= $type === 'lost' ? 'lost' : 'found' ?> <span class="req">*</span></label>
          <input class="input" type="date" id="date_field" name="<?= e($dateCol) ?>"
                 max="<?= date('Y-m-d') ?>"
                 value="<?= $val($dateCol, $item[$dateCol] ?? '') ?>">
        </div>
        <div class="field">
          <label for="reporter_privacy">Show my identity as</label>
          <select class="input" id="reporter_privacy" name="reporter_privacy">
            <?php $privacyVal = (string)($_POST['reporter_privacy'] ?? ($item['reporter_privacy'] ?? ($myShowName ? 'name' : 'anonymous'))); ?>
            <option value="name"<?= $sel($privacyVal, 'name') ?>>My real name</option>
            <option value="anonymous"<?= $sel($privacyVal, 'anonymous') ?>>Anonymous — <?= e($myAnon) ?></option>
          </select>
          <div class="hint">Choose who other residents see on this report. The barangay admin always sees your real name and contact details.</div>
        </div>
        <div class="field full">
          <label for="description">Description <span class="req">*</span></label>
          <textarea class="input" id="description" name="description"
                    placeholder="Describe distinguishing marks, contents, scratches, etc."><?= $val('description', $item['description'] ?? '') ?></textarea>
          <div class="hint">Specific details help the matching engine and the barangay verify claims.</div>
        </div>
        <div class="field full">
          <label for="photo">Photo <span class="muted small">(optional — JPG, PNG or WEBP, max 5 MB)</span></label>
          <input class="input" type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" data-preview="photoPreview">
          <?php if ($editing && $item['photo']): ?>
            <div class="hint">Leave empty to keep the current photo.</div>
          <?php endif; ?>
          <div class="photo-preview" id="photoPreview">
            <?php if ($editing && $item['photo']): ?>
              <img src="<?= e(photo_url($item['photo'])) ?>" alt="Current photo">
            <?php else: ?>
              <img src="" alt="Preview" style="display:none">
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="flex mt-3">
        <button class="btn btn-teal" type="submit"><?= icon('check', 'i i-sm') ?> <?= $editing ? 'Save changes' : 'Submit report' ?></button>
        <a class="btn btn-ghost" href="<?= base_url($listUrl) ?>">Cancel</a>
      </div>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
