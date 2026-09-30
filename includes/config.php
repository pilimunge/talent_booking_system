<?php
/**
 * config.php
 * Central configuration constants for the Talent Booking Management System.
 * Edit these values to match your local XAMPP/WAMP MySQL setup.
 */

// ----- Database configuration -----
define('DB_HOST', 'sql302.infinityfree.com');
define('DB_NAME', 'if0_43045939_talentbooking');
define('DB_USER', 'if0_43045939');
define('DB_PASS', 'pilipo1234'); // default XAMPP root password is empty

// ----- Application configuration -----
define('APP_NAME', 'Talent Booking System');
define('APP_URL', 'https://talentbooking.infinityfree.me'); // change if your folder name differs

// ----- Upload paths (relative to project root) -----
define('UPLOAD_PROFILE_DIR', __DIR__ . '/../uploads/profile_images/');
define('UPLOAD_COVER_DIR', __DIR__ . '/../uploads/cover_images/');
define('UPLOAD_GALLERY_DIR', __DIR__ . '/../uploads/gallery/');

define('UPLOAD_PROFILE_URL', APP_URL . '/uploads/profile_images/');
define('UPLOAD_COVER_URL', APP_URL . '/uploads/cover_images/');
define('UPLOAD_GALLERY_URL', APP_URL . '/uploads/gallery/');

// ----- Upload constraints -----
define('MAX_UPLOAD_SIZE', 3 * 1024 * 1024); // 3MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// ----- Session -----
// Session lifetime for "Remember Me" cookie, in seconds (30 days)
define('REMEMBER_ME_LIFETIME', 30 * 24 * 60 * 60);

// ----- Login rate limiting -----
// Max failed login attempts allowed (per email and per IP) within the
// lockout window before further attempts are blocked.
define('LOGIN_MAX_ATTEMPTS', 5);
// Lockout / lookback window, in minutes.
define('LOGIN_LOCKOUT_MINUTES', 15);

// ----- Error display (set to 0 in production) -----
define('APP_DEBUG', 1);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

date_default_timezone_set('Africa/Dar_es_Salaam');
