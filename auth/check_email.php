<?php
/**
 * LOST2FOUND — Live email status lookup (used by the login page).
 * GET ?email=...  →  {"status": "active|pending|rejected|inactive|none"}
 *                    + {"remarks": "..."} when the registration was rejected
 *                    + {"suggest": "..."} when no account exists but a very
 *                      similar email is registered (catches typos like
 *                      "gmail.coom" vs "gmail.com").
 * Read-only and returns nothing but the status — no names, no emails.
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$email = strtolower(trim((string)($_GET['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'none']);
    exit;
}

$db  = get_db();
$stmt = $db->prepare('SELECT status, review_remarks FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$row = $stmt->fetch();

if (!$row) {
    /* No exact match — look for a close cousin to catch typos. */
    $suggest = null;

    /* Common domain typos first (cheap and very frequent). */
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    $domains = ['gmail.com' => ['gmail.co', 'gmail.coom', 'gmail.con', 'gmial.com', 'gmai.com', 'gmail.cm'],
                'yahoo.com' => ['yahoo.co', 'yaho.com', 'yahoo.con'],
                'outlook.com' => ['outlok.com', 'outlook.con', 'hotmial.com'],
                'hotmail.com' => ['hotmai.com', 'hotmail.co', 'hotmail.con']];
    foreach ($domains as $correct => $typos) {
        if (in_array($domain, $typos, true)) {
            $candidate = $local . '@' . $correct;
            $chk = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $chk->execute([$candidate]);
            if ((int)$chk->fetchColumn() > 0) { $suggest = $candidate; break; }
        }
    }

    /* Otherwise: same local part, any domain (e.g. typed the wrong provider). */
    if ($suggest === null && $local !== '') {
        $chk = $db->prepare('SELECT email FROM users WHERE email LIKE ? LIMIT 2');
        $chk->execute([$local . '@%']);
        $found = $chk->fetchAll(PDO::FETCH_COLUMN);
        if (count($found) === 1) {
            $suggest = $found[0];          // unambiguous — only one candidate
        } elseif (count($found) > 1) {
            usort($found, fn($a, $b) => levenshtein($email, $a) <=> levenshtein($email, $b));
            if (levenshtein($email, $found[0]) <= 3) $suggest = $found[0];
        }
    }

    $out = ['status' => 'none'];
    if ($suggest !== null && $suggest !== $email) {
        $out['suggest'] = $suggest;
    }
    echo json_encode($out);
    exit;
}

$out = ['status' => $row['status']];
if ($row['status'] === 'rejected' && !empty($row['review_remarks'])) {
    $out['remarks'] = (string)$row['review_remarks'];
}
echo json_encode($out);
