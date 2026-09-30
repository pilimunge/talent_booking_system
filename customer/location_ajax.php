<?php
/**
 * customer/location_ajax.php
 *
 * AJAX endpoint used by customer/location_share.php. The customer's
 * browser calls this repeatedly (via fetch, POST, form-urlencoded so
 * verify_csrf() works exactly like every other form in this app) to:
 *   - action=start  : turn live sharing ON for a booking and store the
 *                      first position
 *   - action=update : push a fresh position while sharing is ON
 *   - action=stop    : turn sharing OFF (row is kept, just hidden from
 *                      the provider)
 *
 * Location is only ever accepted for a booking that:
 *   - belongs to the currently logged-in customer, AND
 *   - is currently in 'ongoing' status
 * so a customer can never be tracked before/after the event window,
 * and never for someone else's booking.
 *
 * Always responds with JSON.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

header('Content-Type: application/json');

require_role('customer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed.']);
    exit;
}

verify_csrf();

$bookingId = (int) ($_POST['booking_id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($bookingId <= 0 || !in_array($action, ['start', 'update', 'stop'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

/*
|--------------------------------------------------------------------------
| Ownership + status check
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare(
    'SELECT id, booking_status, event_name
     FROM bookings
     WHERE id = :id AND customer_id = :cid
     LIMIT 1'
);
$stmt->execute(['id' => $bookingId, 'cid' => current_user_id()]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Booking not found.']);
    exit;
}

if ($booking['booking_status'] !== 'ongoing') {
    http_response_code(409);
    echo json_encode([
        'ok' => false,
        'error' => 'Location sharing is only available while the booking is ongoing.',
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Stop sharing - just flip the flag off, keep the row
|--------------------------------------------------------------------------
*/
if ($action === 'stop') {

    $update = $pdo->prepare(
        'UPDATE booking_locations SET is_sharing = 0 WHERE booking_id = :bid AND customer_id = :cid'
    );
    $update->execute(['bid' => $bookingId, 'cid' => current_user_id()]);

    echo json_encode(['ok' => true, 'sharing' => false]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Start / update - requires a real position from the browser
|--------------------------------------------------------------------------
*/
$latitude = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
$longitude = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
$accuracy = filter_input(INPUT_POST, 'accuracy', FILTER_VALIDATE_FLOAT);

if (
    $latitude === false || $latitude === null || $latitude < -90 || $latitude > 90 ||
    $longitude === false || $longitude === null || $longitude < -180 || $longitude > 180
) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Invalid coordinates.']);
    exit;
}

// Upsert: one row per booking. INSERT ... ON DUPLICATE KEY UPDATE relies on
// the UNIQUE constraint on booking_locations.booking_id.
$upsert = $pdo->prepare(
    'INSERT INTO booking_locations (booking_id, customer_id, latitude, longitude, accuracy_meters, is_sharing)
     VALUES (:bid, :cid, :lat, :lng, :acc, 1)
     ON DUPLICATE KEY UPDATE
        latitude = VALUES(latitude),
        longitude = VALUES(longitude),
        accuracy_meters = VALUES(accuracy_meters),
        is_sharing = 1'
);
$upsert->execute([
    'bid' => $bookingId,
    'cid' => current_user_id(),
    'lat' => $latitude,
    'lng' => $longitude,
    'acc' => $accuracy !== false && $accuracy !== null ? $accuracy : null,
]);

if ($action === 'start') {
    audit_log(
        $pdo,
        'Started Location Sharing',
        'Started sharing live location for booking "' . $booking['event_name'] . '".',
        'booking',
        $bookingId
    );
}

echo json_encode(['ok' => true, 'sharing' => true]);
