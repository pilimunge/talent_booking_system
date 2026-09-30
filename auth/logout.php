<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/language.php';

// Log activity before destroying session if needed
if (is_logged_in()) {
    $userId = (int)($_SESSION['user_id'] ?? 0);

    if ($userId > 0) {
        log_activity(
            $pdo,
            $userId,
            'LOGOUT',
            'User logged out'
        );
    }
}

// Clear remember-me cookie if your system uses it
if (isset($_COOKIE['remember_token'])) {
    setcookie(
        'remember_token',
        '',
        time() - 3600,
        '/',
        '',
        false,
        true
    );
}

// Clear session
$_SESSION = [];

// Remove session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy session
session_destroy();

// Redirect after everything is processed
redirect('index.php');