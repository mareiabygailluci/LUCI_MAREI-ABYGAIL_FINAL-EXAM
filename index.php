<?php
/**
 * LOST2FOUND — Public landing page
 */
require_once __DIR__ . '/config/config.php';

$db = get_db();
$stats = $db->query(
    "SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'resident' AND status = 'active') AS residents,
        (SELECT COUNT(*) FROM lost_items)  AS lost,
        (SELECT COUNT(*) FROM found_items) AS found,
        (SELECT COUNT(*) FROM found_items WHERE status = 'returned') AS returned,
        (SELECT COUNT(*) FROM matches) AS matches"
)->fetch();

$loggedIn = is_logged_in();
$reportHref  = $loggedIn ? base_url('resident/item_form.php?type=lost')  : base_url('auth/register.php');
$reportLabel = $loggedIn ? 'Report Lost Item' : 'Get Started — It\'s Free';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lost2Found · Barangay Libis Lost &amp; Found</title>
<link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>">
</head>
<body>

<nav class="navbar">
  <div class="flex">
    <div class="brand-mark" style="width:30px;height:30px;font-size:12px">L2F</div>
    <span class="brand-name" style="font-size:.95rem">LOST<span>2</span>FOUND</span>
  </div>
  <div class="links">
    <a href="#how">How it works</a>
    <a href="<?= base_url('resident/browse.php') ?>">Browse Items</a>
    <?php if ($loggedIn): ?>
      <a class="cta" href="<?= is_admin() ? base_url('admin/dashboard.php') : base_url('resident/dashboard.php') ?>">My Dashboard</a>
    <?php else: ?>
      <a href="<?= base_url('auth/login.php') ?>">Log in</a>
      <a class="cta" href="<?= base_url('auth/register.php') ?>">Register</a>
    <?php endif; ?>
  </div>
</nav>

<header class="hero">
  <div class="hero-inner">
    <span class="pill"><?= icon('pin', 'i i-sm') ?> Barangay Libis &middot; Official Lost &amp; Found</span>
    <h1>Lost something? <em>Found</em> something?<br>Start here.</h1>
    <p>Lost2Found connects residents who lost items with those who found them.
       Report an item, let the system find possible matches, and recover your belongings
       through a verified claim process.</p>
    <div class="hero-btns">
      <a class="btn-hero-primary" href="<?= $reportHref ?>"><?= e($reportLabel) ?></a>
      <a class="btn-hero-ghost" href="<?= base_url('resident/browse.php') ?>">Browse Found Items</a>
    </div>
  </div>
</header>

<section class="section" id="how">
  <div class="section-head">
    <h2>How it works</h2>
    <p>Three simple steps between losing an item and getting it back.</p>
  </div>
  <div class="steps">
    <div class="step">
      <div class="num"><?= icon('file') ?></div>
      <h3>1. Report it</h3>
      <p>Create a lost or found report with the item details, location, date, and a photo.
         You can choose to show your real name or report anonymously.</p>
    </div>
    <div class="step">
      <div class="num"><?= icon('link') ?></div>
      <h3>2. Get matched</h3>
      <p>The system automatically compares lost and found reports by category, color,
         location, date, and brand &mdash; and alerts both parties of possible matches.</p>
    </div>
    <div class="step">
      <div class="num"><?= icon('shield') ?></div>
      <h3>3. Claim &amp; return</h3>
      <p>Submit a claim request with proof of ownership. Once the barangay verifies it,
         the item is coordinated for return to its rightful owner.</p>
    </div>
  </div>

  <div class="landing-stats">
    <div class="landing-stat"><b><?= (int)$stats['lost'] + (int)$stats['found'] ?></b><span>Items Reported</span></div>
    <div class="landing-stat"><b><?= (int)$stats['matches'] ?></b><span>Possible Matches Found</span></div>
    <div class="landing-stat"><b><?= (int)$stats['returned'] ?></b><span>Items Returned</span></div>
    <div class="landing-stat"><b><?= (int)$stats['residents'] ?></b><span>Registered Residents</span></div>
  </div>
</section>

<footer class="public-footer">
  <b>LOST2FOUND</b> · Barangay Libis Lost &amp; Found Management System<br>
  &copy; <?= date('Y') ?> Barangay Libis. All rights reserved.
</footer>

</body>
</html>
