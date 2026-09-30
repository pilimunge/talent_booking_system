<?php
/**
 * auth.php
 * Session bootstrap + authentication/role guard helpers.
 * Include this at the TOP of every protected page, after config.php/db.php/functions.php:
 *
 *   require_once __DIR__ . '/../includes/config.php';
 *   require_once __DIR__ . '/../includes/db.php';
 *   require_once __DIR__ . '/../includes/functions.php';
 *   require_once __DIR__ . '/../includes/auth.php';
 *   require_role('admin'); // or 'customer' / 'provider'
 */

if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookie against XSS/CSRF-style theft.
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/**
 * Attempt to restore a session from the "remember me" cookie, if present
 * and no active session exists yet. Call this once, early, on public entry
 * points (e.g. index.php) before checking is_logged_in().
 */
function attempt_remember_login(PDO $pdo): void
{
    if (!empty($_SESSION['user_id']) || empty($_COOKIE['remember_token'])) {
        return;
    }

    // The cookie only ever holds the raw, random token. The database only
    // ever stores its SHA-256 hash, so a leaked DB backup (or a read-only
    // SQL injection) can't be replayed as a valid "remember me" cookie.
    $hashedToken = hash('sha256', $_COOKIE['remember_token']);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE remember_token = :token AND status = "active" LIMIT 1');
    $stmt->execute(['token' => $hashedToken]);
    $user = $stmt->fetch();

    if ($user) {
        // Regenerate the session id when a session is (re)established from
        // the remember-me cookie, same as a normal password login, to
        // prevent session fixation.
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['fullname'];
    }
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function current_user_role(): ?string
{
    return $_SESSION['user_role'] ?? null;
}

function current_user_name(): ?string
{
    return $_SESSION['user_name'] ?? null;
}

/**
 * Require the visitor to be logged in. Redirects to login page otherwise.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('auth/login.php');
    }
}

/**
 * Require the visitor to be logged in AND hold one of the given role(s).
 * Usage: require_role('admin'); or require_role('customer', 'provider');
 */
function require_role(string ...$roles): void
{
    require_login();

    if (!in_array(current_user_role(), $roles, true)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center;">
                <h2>403 - Access Denied</h2>
                <p>You do not have permission to view this page.</p>
                <a href="' . e(APP_URL) . '/index.php">Return home</a>
             </div>');
    }
}

/**
 * Log the current user out: clears session and remember-me cookie/token.
 */
function do_logout(PDO $pdo): void
{
    if (is_logged_in()) {
        $stmt = $pdo->prepare('UPDATE users SET remember_token = NULL WHERE id = :id');
        $stmt->execute(['id' => current_user_id()]);
    }

    $_SESSION = [];
    session_destroy();

    if (!empty($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
}
