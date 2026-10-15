<?php
/**
 * LOST2FOUND — Database connection (PDO)
 * Auto-detects environment: local XAMPP vs online hosting.
 * Local lang pag localhost/127.0.0.1, lahat ng ibang domain = online.
 */
$__host = $_SERVER['HTTP_HOST'] ?? 'cli';
$__isLocal = ($__host === 'cli')
    || str_contains($__host, 'localhost')
    || str_contains($__host, '127.0.0.1');
if (!$__isLocal) {
    // --- ONLINE (InfinityFree) ---
    define('DB_HOST', 'sql203.infinityfree.com');
    define('DB_NAME', 'if0_43172445_lost2found');
    define('DB_USER', 'if0_43172445');
    define('DB_PASS', 'S0IddkTyZx');
} else {
    // --- LOCAL (XAMPP) ---
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'lost2found');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}
unset($__host, $__isLocal);

define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads');

function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #e5e9ef;border-radius:12px">'
               . '<h2 style="margin:0 0 8px;color:#b3261e">Database connection failed</h2>'
               . '<p style="color:#555">' . htmlspecialchars($e->getMessage()) . '</p>'
               . '<p style="color:#555">Make sure MySQL is running in XAMPP, then import the file '
               . '<strong>database/lost2found.sql</strong> via phpMyAdmin '
               . '(<a href="http://localhost/phpmyadmin"><strong>localhost/phpmyadmin</strong></a>).</p>'
               . '</div>';
            exit;
        }
    }
    return $pdo;
}
