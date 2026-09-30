<?php
/**
 * provider/location_poll.php
 *
 * AJAX endpoint polled by provider/track_location.php (fetch, every few
 * seconds) to get the customer's latest shared position for a booking.
 * Only returns data when the booking:
 *   - belongs to the currently logged-in provider, AND
 *   - is currently 'ongoing', AND
 *   - the customer currently has sharing turned on
 *
 * Always responds with JSON.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

require_role('provider');
require_once __DIR__ . '/_require_profile.php';

$bookingId = (int) ($_GET['booking_id'] ?? 0);

if ($bookingId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, booking_status FROM bookings WHERE id = :id AND provider_id = :pid LIMIT 1'
);
$stmt->execute(['id' => $bookingId, 'pid' => $providerProfile['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Booking not found.']);
    exit;
}

if ($booking['booking_status'] !== 'ongoing') {
    echo json_encode(['ok' => true, 'sharing' => false, 'reason' => 'not_ongoing']);
    exit;
}

$locStmt = $pdo->prepare(
    'SELECT latitude, longitude, accuracy_meters, is_sharing, updated_at
     FROM booking_locations
     WHERE booking_id = :bid
     LIMIT 1'
);
$locStmt->execute(['bid' => $bookingId]);
$location = $locStmt->fetch();

if (!$location || !$location['is_sharing']) {
    echo json_encode(['ok' => true, 'sharing' => false, 'reason' => 'not_sharing']);
    exit;
}

echo json_encode([
    'ok' => true,
    'sharing' => true,
    'latitude' => (float) $location['latitude'],
    'longitude' => (float) $location['longitude'],
    'accuracy_meters' => $location['accuracy_meters'] !== null ? (float) $location['accuracy_meters'] : null,
    'updated_at' => $location['updated_at'],
]);
