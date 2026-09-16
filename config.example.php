<?php
/**
 * Copy this file to config.php and fill in your real values.
 * config.php is git-ignored so real credentials never get committed.
 */

// --- Database connection (Hostinger MySQL) ---
define('DB_HOST', 'localhost');           // Hostinger MySQL host - almost always 'localhost'
define('DB_NAME', 'u288510777_kscdb');    // Your new database name
define('DB_USER', 'u288510777_admin');    // Your database username
define('DB_PASS', 'PUT_YOUR_DB_PASSWORD_HERE');

// --- Site ---
define('SITE_NAME', 'Kearney Senior Center Report');
define('SITE_TIMEZONE', 'America/Chicago');

// --- Error display (turn OFF on production once things are working) ---
define('APP_DEBUG', false);
