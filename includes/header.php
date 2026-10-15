<?php
/**
 * LOST2FOUND — Shared app shell (sidebar + topbar + flash alerts).
 * Pages set $title and $active before including this file.
 */
require_once __DIR__ . '/../config/config.php';
require_login();

$u       = current_user();
$isAdmin = is_admin();
$uid     = (int)$u['id'];
if (!isset($u['full_name']) && isset($u['name'])) {
    $u['full_name'] = $u['name']; // legacy session key
}
$u['full_name'] = $u['full_name'] ?? 'User';
$db      = get_db();

/* ---- sidebar badge counts ---- */
if ($isAdmin) {
    $pendingMatches = (int)$db->query("SELECT COUNT(*) FROM matches WHERE status = 'pending'")->fetchColumn();
    $pendingClaims  = (int)$db->query("SELECT COUNT(*) FROM claim_requests WHERE status = 'pending'")->fetchColumn();
    $pendingUsers   = (int)$db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn();
    $myMatches      = 0;
} else {
    $st = $db->prepare(
        "SELECT COUNT(*) FROM matches m
         JOIN lost_items  l ON l.id = m.lost_item_id
         JOIN found_items f ON f.id = m.found_item_id
         WHERE m.status = 'pending' AND (l.user_id = ? OR f.user_id = ?)"
    );
    $st->execute([$uid, $uid]);
    $myMatches      = (int)$st->fetchColumn();
    $pendingMatches = $pendingClaims = 0;
}

$navSections = $isAdmin ? [
    ['Main', [
        ['dashboard', 'admin/dashboard.php', 'dashboard', 'Dashboard', 0],
    ]],
    ['Reports & Items', [
        ['lost',    'admin/lost_items.php',  'box',    'Lost Reports',     0],
        ['found',   'admin/found_items.php', 'gift',   'Found Reports',    0],
        ['matches', 'admin/matches.php',     'link',   'Possible Matches', $pendingMatches],
        ['claims',  'admin/claims.php',      'shield', 'Claim Requests',   $pendingClaims],
    ]],
    ['Management', [
        ['users',      'admin/users.php',      'users', 'Users',      $pendingUsers],
        ['categories', 'admin/categories.php', 'tag',   'Categories', 0],
        ['locations',  'admin/locations.php',  'pin',   'Locations',  0],
    ]],
    ['Insights', [
        ['reports', 'admin/reports.php', 'chart', 'Statistics', 0],
    ]],
] : [
    ['Main', [
        ['dashboard', 'resident/dashboard.php', 'dashboard', 'Dashboard',    0],
        ['browse',    'resident/browse.php',    'search',    'Browse Items', 0],
    ]],
    ['My Reports', [
        ['lost',    'resident/lost_items.php',  'box',    'Lost Reports',     0],
        ['found',   'resident/found_items.php', 'gift',   'Found Reports',    0],
        ['matches', 'resident/matches.php',     'link',   'Possible Matches', $myMatches],
        ['claims',  'resident/claims.php',      'shield', 'My Claims',        0],
    ]],
    ['Account', [
        ['profile', 'resident/profile.php', 'user', 'My Profile', 0],
    ]],
];

/* ---- topbar notifications (latest 3, "see all" opens the full list) ---- */
$nstmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3');
$nstmt->execute([$uid]);
$notifs = $nstmt->fetchAll();
$unread = unread_notifications();

function notif_icon(string $type): array {
    return match ($type) {
        'match'  => ['link', 'bg-purple'],
        'claim'  => ['shield', 'bg-primary'],
        'status' => ['check-circle', 'bg-success'],
        default  => ['bell', 'bg-info'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? 'Lost2Found') ?> · Lost2Found</title>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>?v=<?= (int)filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>
<div class="app">

  <aside class="sidebar">
    <div class="brand">
      <div class="brand-mark">L2F</div>
      <div class="brand-name">LOST<span>2</span>FOUND</div>
    </div>
    <nav class="nav">
      <?php foreach ($navSections as [$section, $navItems]): ?>
        <div class="nav-label"><?= e($section) ?></div>
        <?php foreach ($navItems as [$key, $href, $ico, $label, $count]): ?>
          <a href="<?= base_url($href) ?>" class="<?= ($active ?? '') === $key ? 'active' : '' ?>">
            <?= icon($ico) ?><span><?= e($label) ?></span>
            <?php if ($count > 0): ?>
              <span class="count"><?= $count > 99 ? '99+' : $count ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
      <strong><?= e($u['full_name']) ?></strong>
      <?= $isAdmin ? 'Barangay Admin' : 'Resident' ?> · Barangay Libis
    </div>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="icon-btn sidebar-toggle" data-toggle="nav" aria-label="Toggle menu"><?= icon('menu') ?></button>
      <h2><?= e($title ?? '') ?></h2>
      <span class="spacer"></span>

      <details class="menu">
        <summary class="icon-btn<?= $unread > 0 ? ' has-unread' : '' ?>" aria-label="Notifications">
          <?= icon('bell') ?>
          <?php if ($unread > 0): ?><span class="dot"><?= $unread > 9 ? '9+' : $unread ?></span><?php endif; ?>
        </summary>
        <div class="menu-body" style="min-width:330px">
          <div class="menu-head flex-between">
            <strong>Notifications</strong>
            <a class="small" href="<?= base_url('resident/notifications.php?mark_all=1&csrf=' . csrf_token()) ?>">Mark all read</a>
          </div>
          <?php if (!$notifs): ?>
            <p class="faint" style="padding:10px 11px">No notifications yet.</p>
          <?php else: foreach ($notifs as $n): [$ni, $nc] = notif_icon($n['type']); $isUnread = (int)$n['is_read'] === 0; ?>
            <a class="notif-row<?= $isUnread ? ' is-unread' : '' ?>"
               href="<?= base_url('resident/notifications.php?read=' . (int)$n['id'] . '&goto=' . rawurlencode((string)($n['link'] ?? ''))) ?>">
              <span class="notif-ico <?= $nc ?>" style="width:28px;height:28px;border-radius:8px"><?= icon($ni, 'i i-sm') ?></span>
              <span style="min-width:0;flex:1">
                <span class="small" style="display:block;white-space:normal"><?= e($n['message']) ?></span>
                <span class="faint"><?= time_ago($n['created_at']) ?></span>
              </span>
              <?php if ($isUnread): ?><span class="new-pill">New</span><?php endif; ?>
            </a>
          <?php endforeach; endif; ?>
          <a href="<?= base_url('resident/notifications.php') ?>"
             style="justify-content:center;font-weight:700;color:var(--primary)">See all notifications</a>
        </div>
      </details>

      <details class="menu">
        <summary>
          <div class="avatar"><?= e(initials($u['full_name'])) ?></div>
          <span class="nowrap"><strong class="small"><?= e($u['full_name']) ?></strong></span>
        </summary>
        <div class="menu-body">
          <div class="menu-head">
            <strong><?= e($u['full_name']) ?></strong>
            <span><?= e($u['email']) ?></span>
          </div>
          <a href="<?= base_url('auth/logout.php') ?>"><?= icon('logout', 'i i-sm') ?> Sign out</a>
        </div>
      </details>
    </header>

    <main class="content">
      <?php foreach (get_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>">
          <span><?= e($f['message']) ?></span>
          <button class="close" aria-label="Dismiss">&times;</button>
        </div>
      <?php endforeach; ?>
