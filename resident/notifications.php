<?php
/**
 * LOST2FOUND — Notifications (read / update)
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$uid = (int)$_SESSION['user']['id'];
$db  = get_db();

/* Mark all as read */
if (isset($_GET['mark_all'])) {
    verify_csrf();
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
    flash('success', 'All notifications marked as read.');
    redirect('resident/notifications.php');
}

/* Open one notification: mark it read then follow its link */
if (isset($_GET['read'])) {
    $id = (int)$_GET['read'];
    $st = $db->prepare('SELECT link FROM notifications WHERE id = ? AND user_id = ?');
    $st->execute([$id, $uid]);
    if ($row = $st->fetch()) {
        $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?')->execute([$id]);
        $to = (string)($_GET['goto'] ?? $row['link'] ?? '');
        if ($to === '' || preg_match('#^[a-z0-9/_-]+\.php#i', $to) !== 1) {
            $to = 'resident/notifications.php';
        }
        redirect($to);
    }
    redirect('resident/notifications.php');
}

$notifs = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
$notifs->execute([$uid]);
$notifs = $notifs->fetchAll();
$unread = count(array_filter($notifs, fn($n) => (int)$n['is_read'] === 0));

$title  = 'Notifications';
$active = 'notifications';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
  <div>
    <h1>Notifications</h1>
    <p><?= $unread > 0 ? $unread . ' unread notification' . ($unread === 1 ? '' : 's') . '.' : 'You are all caught up.' ?></p>
  </div>
  <?php if ($unread > 0): ?>
    <a class="btn btn-outline" href="<?= base_url('resident/notifications.php?mark_all=1&csrf=' . csrf_token()) ?>">
      <?= icon('check', 'i i-sm') ?> Mark all as read
    </a>
  <?php endif; ?>
</div>

<div class="card">
  <?php if (!$notifs): ?>
    <div class="empty">
      <?= icon('bell', 'i') ?>
      <h3>Nothing here yet</h3>
      <p>Match alerts, claim updates, and report status changes will appear here.</p>
    </div>
  <?php else: ?>
    <?php foreach ($notifs as $n): [$ni, $nc] = notif_icon($n['type']); ?>
      <div class="notif <?= (int)$n['is_read'] === 0 ? 'unread' : '' ?>">
        <div class="notif-ico <?= $nc ?>"><?= icon($ni, 'i i-sm') ?></div>
        <div style="flex:1;min-width:0">
          <p><?= e($n['message']) ?></p>
          <div class="when">
            <?= e(ucfirst($n['type'])) ?> · <?= time_ago($n['created_at']) ?>
            <?= (int)$n['is_read'] === 0 ? ' · <strong style="color:var(--teal-dark)">new</strong>' : '' ?>
          </div>
        </div>
        <div class="actions">
          <?php if ($n['link']): ?>
            <a class="btn btn-outline btn-sm"
               href="<?= base_url('resident/notifications.php?read=' . (int)$n['id'] . '&goto=' . rawurlencode((string)$n['link'])) ?>">
              Open
            </a>
          <?php elseif ((int)$n['is_read'] === 0): ?>
            <a class="btn btn-ghost btn-sm"
               href="<?= base_url('resident/notifications.php?read=' . (int)$n['id']) ?>">Mark read</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
