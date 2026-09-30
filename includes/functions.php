<?php
/**
 * functions.php
 * Reusable helper functions used across the whole system.
 * Include after db.php.
 */

/**
 * Escape output to prevent XSS. Use this around EVERY piece of
 * user-supplied or database-sourced data before echoing into HTML.
 */
function e($string): string
{
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect helper. Always call exit after using this in a script
 * (this function itself calls exit, so no extra code runs after).
 */
function redirect(string $path): never
{
    header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    exit;
}

/**
 * Store a one-time flash message in the session, shown on the next page load.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Retrieve and clear the flash message, if any.
 */
function get_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Generate and store a CSRF token for the current session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output a hidden CSRF input field for use inside <form> tags.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify a submitted CSRF token matches the session token. Call at the
 * top of every POST-handling script before touching the database.
 */
function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(419);
        die('Your session has expired. Please refresh the page and try again.');
    }
}

/**
 * Push a notification row for a given user.
 */
function push_notification(PDO $pdo,
 int $userId,
 string $title, string $message,
  ?string $senderRole = null
): void {

    $stmt = $pdo->prepare("
        INSERT INTO notifications
        (user_id, title, message, sender_role)
        VALUES
        (:user_id, :title, :message, :sender_role)
    ");

    $stmt->execute([
        'user_id' => $userId,
        'title' => $title,
        'message' => $message,
        'sender_role' => $senderRole
    ]);
}

/**
 * Write an entry to the activity_logs table.
 */
function log_activity(PDO $pdo, ?int $userId, string $activity): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, activity) VALUES (:user_id, :activity)');
    $stmt->execute(['user_id' => $userId, 'activity' => $activity]);
}

/**
 * Format a decimal amount as Tanzanian Shillings, e.g. 500000.00 -> "TZS 500,000".
 */
function format_money($amount): string
{
    return 'TZS ' . number_format((float) $amount, 0);
}

/**
 * Format a date as "12 Sep 2026".
 */
function format_date(?string $date): string
{
    if (empty($date)) {
        return '-';
    }
    return date('d M Y', strtotime($date));
}

/**
 * Render a Bootstrap badge class for a given booking status.
 */
function booking_status_badge(string $status): string
{
    return match ($status) {
        'pending'   => 'bg-warning text-dark',
        'accepted'  => 'bg-info text-dark',
        'rejected'  => 'bg-danger',
        'cancelled' => 'bg-secondary',
        'ongoing'   => 'bg-primary',
        'completed' => 'bg-success',
        default     => 'bg-secondary',
    };
}

/**
 * Render a Bootstrap badge class for a given payment status.
 */
function payment_status_badge(string $status): string
{
    return match ($status) {
        'pending'  => 'bg-warning text-dark',
        'paid'     => 'bg-success',
        'failed'   => 'bg-danger',
        'refunded' => 'bg-secondary',
        default    => 'bg-secondary',
    };
}

/**
 * Render a Bootstrap badge class for a provider approval status.
 */
function approval_status_badge(string $status): string
{
    return match ($status) {
        'pending'   => 'bg-warning text-dark',
        'approved'  => 'bg-success',
        'rejected'  => 'bg-danger',
        'suspended' => 'bg-secondary',
        default     => 'bg-secondary',
    };
}

/**
 * Render a 1-5 star rating as Bootstrap Icons HTML.
 */
function render_stars(float $rating): string
{
    $rating = max(0, min(5, $rating));
    $full = (int) floor($rating);
    $half = ($rating - $full) >= 0.5 ? 1 : 0;
    $empty = 5 - $full - $half;

    $html = str_repeat('<i class="bi bi-star-fill text-warning"></i>', $full);
    if ($half) {
        $html .= '<i class="bi bi-star-half text-warning"></i>';
    }
    $html .= str_repeat('<i class="bi bi-star text-warning"></i>', $empty);

    return $html;
}

/**
 * Handle a single image upload safely: validates type/size and stores
 * with a random filename. Returns the stored filename on success, or
 * null if no file was uploaded. Throws RuntimeException on validation failure.
 */
function handle_image_upload(array $file, string $destinationDir): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('File upload failed.');
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new RuntimeException('Image is too large.');
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        throw new RuntimeException('Only JPG, PNG and WEBP images are allowed.');
    }

    if (!is_dir($destinationDir)) {
        mkdir($destinationDir, 0777, true);
    }

    $extension = match ($mime) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        default      => 'jpg',
    };

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;

    $destination = rtrim($destinationDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Failed to save uploaded image.');
    }

    return $filename;
}
/**
 * ---------------------------------------------------------------------
 * Login rate limiting
 * ---------------------------------------------------------------------
 * Backed by the `login_attempts` table. Failed attempts are tracked
 * separately by email and by IP address, so both a single account under
 * credential-stuffing attack and a single source brute-forcing many
 * accounts get locked out. Successful logins clear the counters.
 */

/**
 * Record one failed login attempt for a given identifier (an email
 * address or an IP address).
 */
function record_login_attempt(PDO $pdo, string $type, string $identifier): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO login_attempts (identifier_type, identifier_value) VALUES (:type, :identifier)'
    );
    $stmt->execute([
        'type' => $type,
        'identifier' => $identifier,
    ]);
}

/**
 * Count failed attempts for an identifier within the lockout window.
 */
function recent_login_attempts(PDO $pdo, string $type, string $identifier): int
{
    $since = date('Y-m-d H:i:s', time() - (LOGIN_LOCKOUT_MINUTES * 60));

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE identifier_type = :type
           AND identifier_value = :identifier
           AND attempted_at >= :since'
    );
    $stmt->execute([
        'type' => $type,
        'identifier' => $identifier,
        'since' => $since,
    ]);

    return (int) $stmt->fetchColumn();
}

/**
 * True if this identifier (email or IP) has hit the failed-attempt limit
 * and should be blocked from trying again right now.
 */
function is_login_locked(PDO $pdo, string $type, string $identifier): bool
{
    return recent_login_attempts($pdo, $type, $identifier) >= LOGIN_MAX_ATTEMPTS;
}

/**
 * Clear failed-attempt history for an identifier (call after a
 * successful login).
 */
function clear_login_attempts(PDO $pdo, string $type, string $identifier): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM login_attempts WHERE identifier_type = :type AND identifier_value = :identifier'
    );
    $stmt->execute([
        'type' => $type,
        'identifier' => $identifier,
    ]);
}

function audit_log(
    PDO $pdo,
    string $action,
    string $description,
    ?string $targetType = null,
    ?int $targetId = null
): void {

    // Get currently logged-in user
    $userId = isset($_SESSION['user_id'])
        ? (int) $_SESSION['user_id']
        : null;

    $userRole = $_SESSION['user_role'] ?? null;

    // Do not create audit log without authenticated user
    if (!$userId || !$userRole) {
        return;
    }

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;

    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (
            user_id,
            user_role,
            action,
            description,
            target_type,
            target_id,
            ip_address,
            user_agent
        )
        VALUES (
            :user_id,
            :user_role,
            :action,
            :description,
            :target_type,
            :target_id,
            :ip_address,
            :user_agent
        )
    ");

    $stmt->execute([
        'user_id'      => $userId,
        'user_role'    => $userRole,
        'action'       => $action,
        'description'  => $description,
        'target_type'  => $targetType,
        'target_id'    => $targetId,
        'ip_address'   => $ipAddress,
        'user_agent'   => $userAgent
    ]);
}