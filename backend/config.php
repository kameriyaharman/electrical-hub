<?php
/**
 * Electrical Hub — configuration
 *
 * Railway: values come from environment variables automatically.
 * BigRock (cPanel): replace the fallback values ('localhost', 'your_db' ...)
 * with the MySQL database / user / password you create in cPanel.
 */

function env_or($keys, $default) {
    foreach ((array)$keys as $k) {
        $v = getenv($k);
        if ($v !== false && $v !== '') return $v;
    }
    return $default;
}

define('DB_HOST', env_or(['DB_HOST', 'MYSQLHOST'], 'localhost'));
define('DB_PORT', env_or(['DB_PORT', 'MYSQLPORT'], '3306'));
define('DB_NAME', env_or(['DB_NAME', 'MYSQLDATABASE'], 'your_db'));
define('DB_USER', env_or(['DB_USER', 'MYSQLUSER'], 'your_db_user'));
define('DB_PASS', env_or(['DB_PASS', 'MYSQLPASSWORD'], 'your_db_password'));

// Used to sign admin sessions. Change this on BigRock to any long random string.
define('APP_SECRET', env_or('APP_SECRET', 'change-this-to-a-long-random-string'));

define('APP_NAME', 'Electrical Hub');
define('UPLOAD_DIR', env_or('UPLOAD_DIR', __DIR__ . '/uploads'));
define('UPLOAD_URL', 'uploads');
define('MAX_SHOP_PHOTOS', 10);   // photos allowed per shop

date_default_timezone_set('Asia/Kolkata');
