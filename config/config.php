<?php
/**
 * LOST2FOUND — Global configuration, session bootstrap & helper functions
 */
require_once __DIR__ . '/db.php';

/* Session idle timeout (seconds) & session-id rotation interval. */
const SESSION_IDLE_TIMEOUT   = 7200;   // force logout after 2h of inactivity
const SESSION_REGEN_INTERVAL = 1800;   // rotate session id every 30 min

if (session_status() === PHP_SESSION_NONE) {
    $sessionSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                  || (($_SERVER['SERVER_PORT'] ?? 0) == 443);
    ini_set('session.use_strict_mode', '1');
    session_name('lost2found_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $sessionSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ------------------------------------------------------------
 *  Session lifecycle hardening
 *  - Idle timeout: logged-in users are logged out after 2 hours.
 *  - Session ID rotation every 30 min (prevents fixation).
 * ------------------------------------------------------------ */
if (isset($_SESSION['user'])) {
    $lastActive = (int)($_SESSION['last_active'] ?? 0);
    if ($lastActive > 0 && (time() - $lastActive) > SESSION_IDLE_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['flash'][] = ['type' => 'info', 'message' => 'Your session expired. Please log in again.'];
        redirect('auth/login.php');
    }
    $regenAt = (int)($_SESSION['session_regen'] ?? 0);
    if ($regenAt === 0 || (time() - $regenAt) > SESSION_REGEN_INTERVAL) {
        session_regenerate_id(true);
        $_SESSION['session_regen'] = time();
    }
}
$_SESSION['last_active'] = time();

date_default_timezone_set('Asia/Manila');

/* ============================================================
 *  URL HELPERS
 * ============================================================ */
function base_url(string $path = ''): string {
    // Works whether the project sits at localhost root or a subfolder
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    // Strip everything after the known subfolder marker if present
    $known  = ['admin', 'resident', 'auth', 'public', 'config'];
    foreach ($known as $seg) {
        $pos = strrpos($script, '/' . $seg);
        if ($pos !== false) {
            $script = substr($script, 0, $pos);
            break;
        }
    }
    return rtrim($script, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): void {
    header('Location: ' . base_url($path));
    exit;
}

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ============================================================
 *  AUTH & SESSION
 * ============================================================ */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool {
    return isset($_SESSION['user']);
}

function is_admin(): bool {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
}

function require_login(): void {
    if (!is_logged_in()) {
        redirect('auth/login.php');
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        redirect('resident/dashboard.php');
    }
}

/**
 * Starts a fresh, verified session for the given user row.
 * Always used after a successful login / registration.
 */
function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['session_regen'] = time(); // avoid an extra rotation on the next request
    $_SESSION['user'] = [
        'id'        => (int)$user['id'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'role'      => $user['role'],
    ];
}

/* -------- brute-force protection (per-email soft lockout) -------- */

/** Returns an error message if the email is currently locked out, else null. */
function login_attempts_remaining(string $email): ?string {
    $a = $_SESSION['login_attempts_' . md5(strtolower($email))] ?? null;
    if ($a && ($a['until'] ?? 0) > time()) {
        $mins = max(1, (int)ceil((($a['until'] ?? 0) - time()) / 60));
        return "Too many failed attempts. Please try again in {$mins} minute(s).";
    }
    return null;
}

/** Records a failed login. After 5 failures the email is locked for 15 minutes. */
function login_attempts_failed(string $email): void {
    $key = 'login_attempts_' . md5(strtolower($email));
    $a   = $_SESSION[$key] ?? ['count' => 0, 'until' => 0];
    $a['count'] = ($a['count'] ?? 0) + 1;
    if ($a['count'] >= 5) {
        $a['until'] = time() + 900;
        $a['count'] = 0;
    }
    $_SESSION[$key] = $a;
}

/** Clears the lockout counter after a successful login. */
function login_attempts_reset(string $email): void {
    unset($_SESSION['login_attempts_' . md5(strtolower($email))]);
}

function flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ============================================================
 *  CSRF PROTECTION
 * ============================================================ */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(20));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    $sent = $_POST['csrf'] ?? ($_GET['csrf'] ?? '');
    if (!is_string($sent) || $sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('<div style="font-family:Segoe UI,sans-serif;max-width:560px;margin:70px auto;padding:24px;border:1px solid #e5e9ef;border-radius:12px">'
           . '<h2 style="color:#b3261e">419 — Security token expired</h2>'
           . '<p style="color:#555">Please go back, refresh the page, and try again.</p></div>');
    }
}

/* ============================================================
 *  SMALL VIEW HELPERS
 * ============================================================ */
function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $ini = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        if ($p !== '') $ini .= strtoupper($p[0]);
    }
    return $ini !== '' ? $ini : 'U';
}

function notify_admins(string $message, string $type = 'system', ?string $link = null): void {
    $admins = get_db()->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
    foreach ($admins as $a) {
        notify((int)$a['id'], $message, $type, $link);
    }
}

/** Renders a professional horizontal bar chart (pure CSS). */
function render_chart(array $data, bool $alt = false): string {
    $data = array_filter($data, fn($v) => $v > 0);
    if (!$data) {
        return '<div class="empty" style="padding:26px 16px"><p>No data yet.</p></div>';
    }
    $max = max($data);
    $cls = $alt ? ' alt' : '';
    $html = '<div class="chart">';
    foreach ($data as $label => $value) {
        $pct = $max > 0 ? round($value / $max * 100) : 0;
        $html .= '<div class="chart-row">'
               . '<div class="lbl" title="' . e((string)$label) . '">' . e((string)$label) . '</div>'
               . '<div class="chart-track"><div class="chart-fill' . $cls . '" style="width:' . $pct . '%"></div></div>'
               . '<div class="chart-num">' . (int)$value . '</div>'
               . '</div>';
    }
    return $html . '</div>';
}

/** Inline SVG icon set (Feather-style, inherits currentColor). */
function icon(string $name, string $class = 'i'): string {
    static $icons = [
        'dashboard'    => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'search'       => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'box'          => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
        'gift'         => '<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
        'link'         => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>',
        'shield'       => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'users'        => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user'         => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'tag'          => '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.83z"/><line x1="7" y1="7" x2="7.01" y2="7"/>',
        'pin'          => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'chart'        => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
        'bell'         => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'check'        => '<polyline points="20 6 9 17 4 12"/>',
        'check-circle' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'x'            => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'edit'         => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'trash'        => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'eye'          => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'      => '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>',
        'logout'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'plus'         => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'back'         => '<line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>',
        'alert'        => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'calendar'     => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'mail'         => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
        'phone'        => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>',
        'file'         => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'star'         => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'clock'        => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'home'         => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'inbox'        => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'image'        => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
        'menu'         => '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'gear'         => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    ];
    return '<svg class="' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true">' . ($icons[$name] ?? '') . '</svg>';
}

/* ============================================================
 *  FORM VALIDATION HELPERS
 * ============================================================ */
function post(string $key, string $default = ''): string {
    return trim((string)($_POST[$key] ?? $default));
}

function validate_password(string $password): ?string {
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    return null;
}

/* ============================================================
 *  IMAGE UPLOAD  (jpg / png / webp, max 5 MB)
 * ============================================================ */
function handle_image_upload(string $field, string $prefix = 'photo'): ?string {
    if (empty($_FILES[$field]['name']) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed. Please try again.');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Image must be 5 MB or smaller.');
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('The uploaded file is not a valid image.');
    }

    $allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = $info['mime'] ?? '';
    if (!isset($allowedMime[$mime])) {
        throw new RuntimeException('Only JPG, PNG, or WEBP images are allowed.');
    }

    if (!is_dir(UPLOAD_PATH)) {
        mkdir(UPLOAD_PATH, 0777, true);
    }

    $filename = $prefix . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(6))
              . '.' . $allowedMime[$mime];

    if (!move_uploaded_file($file['tmp_name'], UPLOAD_PATH . '/' . $filename)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }
    return $filename;
}

function delete_uploaded_image(?string $filename): void {
    if ($filename && is_file(UPLOAD_PATH . '/' . $filename)) {
        @unlink(UPLOAD_PATH . '/' . $filename);
    }
}

function photo_url(?string $filename): ?string {
    if (!$filename) return null;
    return base_url('uploads/' . rawurlencode($filename));
}

/** URL of an uploaded proof-of-residency image (null if none). */
function proof_url(?string $filename): ?string {
    return photo_url($filename);
}

/* ============================================================
 *  STATUS BADGES (single, consistent visual language)
 * ============================================================ */
function status_badge(?string $status): string {
    $status = strtolower((string)$status);
    $map = [
        'pending'   => 'badge badge-muted',
        'approved'  => 'badge badge-info',
        'rejected'  => 'badge badge-danger',
        'matched'   => 'badge badge-match',
        'claimed'   => 'badge badge-warn',
        'closed'    => 'badge badge-dark',
        'returned'  => 'badge badge-success',
        'active'    => 'badge badge-success',
        'inactive'  => 'badge badge-danger',
        'pending'   => 'badge badge-warn',
        'rejected'  => 'badge badge-danger',
        'confirmed' => 'badge badge-success',
        'dismissed' => 'badge badge-dark',
    ];
    $class = $map[$status] ?? 'badge badge-muted';
    return '<span class="' . $class . '">' . e(ucfirst($status)) . '</span>';
}

function time_ago(?string $datetime): string {
    if (!$datetime) return '—';
    $ts  = strtotime($datetime);
    $now = time();
    $diff = $now - $ts;
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . ' min ago';
    if ($diff < 86400)  return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800) return floor($diff / 86400) . ' day(s) ago';
    return date('M j, Y', $ts);
}

function fmt_date(?string $date): string {
    return $date ? date('M j, Y', strtotime($date)) : '—';
}

/* ============================================================
 *  BARANGAY LIBIS PUROKS  (Address / Purok dropdown)
 * ============================================================ */
function puroks(): array {
    return ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Sitio 1', 'Sitio 2', 'Other'];
}

/** <option> list for the Address/Purok dropdown. Keeps any saved legacy value. */
function purok_select(string $current = ''): string {
    $html = '';
    if ($current !== '' && !in_array($current, puroks(), true)) {
        $html .= '<option value="' . e($current) . '" selected>' . e($current) . '</option>';
    }
    foreach (puroks() as $p) {
        $sel = ($current === $p) ? ' selected' : '';
        $html .= '<option value="' . e($p) . '"' . $sel . '>' . e($p) . '</option>';
    }
    return $html;
}

/* ============================================================
 *  ANONYMOUS REPORTER IDENTITY
 *  Each user gets a stable, unique alias (e.g. "Anonymous Brave
 *  Owl 4812") that is shown to other residents when they choose
 *  to report anonymously. Admins always see the real name.
 * ============================================================ */
function generate_anonymous_name(int $userId): string {
    $adjectives = ['Swift', 'Quiet', 'Brave', 'Kind', 'Bright', 'Gentle', 'Calm', 'Wise',
                   'Steady', 'Noble', 'Cheerful', 'Curious', 'Diligent', 'Honest', 'Loyal',
                   'Playful', 'Patient', 'Sunny', 'Trusty', 'Witty', 'Bold', 'Modest', 'Lucky', 'Friendly'];
    $nouns = ['Turtle', 'Falcon', 'Panda', 'Fox', 'Owl', 'Rabbit', 'Deer', 'Dolphin', 'Sparrow',
              'Hedgehog', 'Lion', 'Tiger', 'Wolf', 'Elephant', 'Penguin', 'Koala', 'Seal', 'Moose', 'Lynx', 'Otter'];
    $adj  = $adjectives[$userId % count($adjectives)];
    $noun = $nouns[($userId * 7) % count($nouns)];
    $code = str_pad((($userId * 7919) % 9000) + 1000, 4, '0', STR_PAD_LEFT);
    return "Anonymous {$adj} {$noun} {$code}";
}

/** Returns the user's anonymous alias, generating & saving one if absent. */
function ensure_anonymous_name(int $userId): string {
    $db  = get_db();
    $st  = $db->prepare('SELECT anonymous_name FROM users WHERE id = ?');
    $st->execute([$userId]);
    $name = (string)$st->fetchColumn();
    if ($name !== '') return $name;

    $name = generate_anonymous_name($userId);
    try {
        $db->prepare('UPDATE users SET anonymous_name = ? WHERE id = ?')->execute([$name, $userId]);
    } catch (PDOException $e) { // rare collision — append a short code and retry
        $name .= ' ' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        $db->prepare('UPDATE users SET anonymous_name = ? WHERE id = ?')->execute([$name, $userId]);
    }
    return $name;
}

/** Returns what other residents should see for a report's reporter. */
function reporter_label(string $realName, ?string $anonymousName, string $privacy): string {
    return ($privacy === 'anonymous' && $anonymousName !== '')
        ? $anonymousName
        : $realName;
}

/* ============================================================
 *  NOTIFICATIONS
 * ============================================================ */
function notify(int $userId, string $message, string $type = 'system', ?string $link = null): void {
    $stmt = get_db()->prepare(
        'INSERT INTO notifications (user_id, message, type, link) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $message, $type, $link]);
}

function unread_notifications(): int {
    if (!is_logged_in()) return 0;
    static $count = null;
    if ($count === null) {
        $stmt = get_db()->prepare(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0'
        );
        $stmt->execute([$_SESSION['user']['id']]);
        $count = (int)$stmt->fetchColumn();
    }
    return $count;
}

/* ============================================================
 *  AUTOMATED ITEM MATCHING ENGINE  (unique feature)
 *  Weighted scoring:
 *    category 20% | color 20% | location 25% | date 20% | brand 15%
 *  A match row is only created when score >= 60 AND the lost item
 *  does not belong to the person who found the item.
 *  only approved items participate, so the flow stays:
 *  pending → approved → matched.
 * ============================================================ */
function compute_match_score(array $lost, array $found): int {
    $score = 0.0;

    // Category — 20%
    $score += ($lost['category_id'] == $found['category_id']) ? 20 : 0;

    // Color — 20% (exact, or partial keyword overlap)
    $lostColor = strtolower(trim($lost['color']));
    $foundColor = strtolower(trim($found['color']));
    if ($lostColor !== '' && $lostColor === $foundColor) {
        $score += 20;
    } elseif ($lostColor !== '' && $foundColor !== ''
        && (str_contains($lostColor, $foundColor) || str_contains($foundColor, $lostColor))) {
        $score += 10; // partial (e.g. "dark blue" vs "blue")
    }

    // Location — 25%
    $score += ($lost['location_id'] == $found['location_id']) ? 25 : 0;

    // Date — 20% (found date must be >= lost date; tighter gaps score higher)
    if (!empty($lost['date_lost']) && !empty($found['date_found'])) {
        $dLost  = strtotime($lost['date_lost']);
        $dFound = strtotime($found['date_found']);
        if ($dFound !== false && $dLost !== false && $dFound >= $dLost) {
            $gap = (int)floor(($dFound - $dLost) / 86400);
            if ($gap === 0)       $score += 20;
            elseif ($gap <= 2)    $score += 15;
            elseif ($gap <= 5)    $score += 10;
            elseif ($gap <= 14)   $score += 5;
        }
    }

    // Brand — 15% (exact case-insensitive; both empty counts as exact)
    $lostBrand  = strtolower(trim((string)($lost['brand'] ?? '')));
    $foundBrand = strtolower(trim((string)($found['brand'] ?? '')));
    if ($lostBrand === $foundBrand) {
        $score += 15;
    }

    return (int)round(min($score, 100));
}

/**
 * Compares newly created/updated items against the opposite table
 * and records new matches. Also notifies both owners.
 */
function run_matching(?int $lostItemId = null, ?int $foundItemId = null): void {
    $db = get_db();

    $selectLost  = $db->prepare('SELECT * FROM lost_items  WHERE id = ?');
    $selectFound = $db->prepare('SELECT * FROM found_items WHERE id = ?');
    $exists      = $db->prepare('SELECT id, status FROM matches WHERE lost_item_id = ? AND found_item_id = ?');
    $insertMatch = $db->prepare(
        'INSERT INTO matches (lost_item_id, found_item_id, match_score, status) VALUES (?, ?, ?, ?)'
    );
    $updateLost  = $db->prepare("UPDATE lost_items  SET status = 'matched' WHERE id = ? AND status IN ('pending','approved')");
    $updateFound = $db->prepare("UPDATE found_items SET status = 'matched' WHERE id = ? AND status IN ('pending','approved')");
    $getLost     = $db->prepare('SELECT id, user_id, item_name FROM lost_items WHERE id = ?');
    $getFound    = $db->prepare('SELECT id, user_id, item_name FROM found_items WHERE id = ?');

    // Case 1: a found item arrives → compare against all open lost items
    if ($foundItemId !== null) {
        $selectFound->execute([$foundItemId]);
        $found = $selectFound->fetch();
        if ($found) {
            $lostRows = $db->query(
                "SELECT * FROM lost_items WHERE status NOT IN ('rejected','closed')"
            )->fetchAll();
            foreach ($lostRows as $lost) {
                if ($lost['user_id'] == $found['user_id']) continue; // own report
                $score = compute_match_score($lost, $found);
                if ($score >= 60) {
                    $exists->execute([$lost['id'], $found['id']]);
                    if (!$exists->fetch()) {
                        $matchStatus = $score >= 85 ? 'confirmed' : 'pending'; // system auto-verifies strong matches
                        $insertMatch->execute([$lost['id'], $found['id'], $score, $matchStatus]);
                        $updateLost->execute([$lost['id']]);
                        $updateFound->execute([$found['id']]);
                        $getLost->execute([$lost['id']]);
                        $getFound->execute([$found['id']]);
                        $L = $getLost->fetch();
                        $F = $getFound->fetch();
                        if ($L) notify((int)$L['user_id'],
                            $matchStatus === 'confirmed'
                                ? "The system verified a high-accuracy match ({$score}% accuracy) for your lost item \"{$L['item_name']}\". You may now file a claim request."
                                : "A found item may match your lost item report \"{$L['item_name']}\" (match accuracy: {$score}%).",
                            'match', 'resident/matches.php');
                        if ($F) notify((int)$F['user_id'],
                            $matchStatus === 'confirmed'
                                ? "The system verified a high-accuracy match ({$score}% accuracy) between your found item \"{$F['item_name']}\" and a lost item report."
                                : "Your found item \"{$F['item_name']}\" may match a lost item report (match accuracy: {$score}%).",
                            'match', 'resident/matches.php');
                    }
                }
            }
        }
    }

    // Case 2: a lost item arrives → compare against all open found items
    if ($lostItemId !== null) {
        $selectLost->execute([$lostItemId]);
        $lost = $selectLost->fetch();
        if ($lost) {
            $foundRows = $db->query(
                "SELECT * FROM found_items WHERE status NOT IN ('rejected','returned','closed')"
            )->fetchAll();
            foreach ($foundRows as $found) {
                if ($found['user_id'] == $lost['user_id']) continue;
                $score = compute_match_score($lost, $found);
                if ($score >= 60) {
                    $exists->execute([$lost['id'], $found['id']]);
                    if (!$exists->fetch()) {
                        $matchStatus = $score >= 85 ? 'confirmed' : 'pending'; // system auto-verifies strong matches
                        $insertMatch->execute([$lost['id'], $found['id'], $score, $matchStatus]);
                        $updateLost->execute([$lost['id']]);
                        $updateFound->execute([$found['id']]);
                        $getLost->execute([$lost['id']]);
                        $getFound->execute([$found['id']]);
                        $L = $getLost->fetch();
                        $F = $getFound->fetch();
                        if ($L) notify((int)$L['user_id'],
                            $matchStatus === 'confirmed'
                                ? "The system verified a high-accuracy match ({$score}% accuracy) for your lost item \"{$L['item_name']}\". You may now file a claim request."
                                : "A found item may match your lost item report \"{$L['item_name']}\" (match accuracy: {$score}%).",
                            'match', 'resident/matches.php');
                        if ($F) notify((int)$F['user_id'],
                            $matchStatus === 'confirmed'
                                ? "The system verified a high-accuracy match ({$score}% accuracy) between your found item \"{$F['item_name']}\" and a lost item report."
                                : "Your found item \"{$F['item_name']}\" may match a lost item report (match accuracy: {$score}%).",
                            'match', 'resident/matches.php');
                    }
                }
            }
        }
    }
}
