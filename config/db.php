<?php
/**
 * LOST2FOUND — Database connection (PDO)
 * Adjust the constants below to match your XAMPP MySQL setup.
 */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'lost2found');
define('DB_USER', 'root');
define('DB_PASS', '');

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
