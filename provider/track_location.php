<?php
/**
 * provider/track_location.php
 *
 * Shows a live map of the customer's shared location for one of the
 * provider's own 'ongoing' bookings, so the provider can find the
 * event location more easily. Polls provider/location_poll.php every
 * few seconds; also offers a one-tap "Call Customer" fallback for
 * before the customer turns sharing on (or if they choose not to).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/language.php';

require_role('provider');
require_once __DIR__ . '/_require_profile.php';

$bookingId = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare(
    'SELECT b.*, u.fullname AS customer_name, u.phone AS customer_phone
     FROM bookings b
     JOIN users u ON u.id = b.customer_id
     WHERE b.id = :id AND b.provider_id = :pid
     LIMIT 1'
);
$stmt->execute(['id' => $bookingId, 'pid' => $providerProfile['id']]);
$booking = $stmt->fetch();

if (!$booking) {
    set_flash('error', 'Booking not found.');
    redirect('provider/bookings.php');
}

if ($booking['booking_status'] !== 'ongoing') {
    set_flash('error', 'Location tracking is only available while the booking is ongoing.');
    redirect('provider/booking_view.php?id=' . $bookingId);
}

$current_page = 'bookings';
$page_title = 'Track Customer Location';
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <a href="<?= e(APP_URL) ?>/provider/booking_view.php?id=<?= (int) $bookingId ?>" class="small text-decoration-none">
                <i class="bi bi-arrow-left"></i> Back to booking
            </a>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body p-4">

                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-1">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-geo-alt-fill text-primary"></i>
                            <?= e($booking['customer_name']) ?>'s Location
                        </h5>

                        <?php if (!empty($booking['customer_phone'])): ?>
                            <a href="tel:<?= e($booking['customer_phone']) ?>" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-telephone"></i> Call Customer
                            </a>
                        <?php endif; ?>
                    </div>

                    <p class="text-secondary small mb-4">
                        For "<?= e($booking['event_name']) ?>" at <?= e($booking['event_location']) ?>.
                        This map updates automatically while the customer has sharing turned on.
                    </p>

                    <div id="status-banner" class="alert alert-secondary small">
                        <i class="bi bi-hourglass-split"></i>
                        Waiting for the customer to share their location&hellip;
                    </div>

                    <div id="map" style="height: 420px; border-radius: .5rem; display:none;"></div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function () {
    const bookingId = <?= (int) $bookingId ?>;
    const pollUrl = '<?= e(APP_URL) ?>/provider/location_poll.php?booking_id=' + bookingId;

    const statusBanner = document.getElementById('status-banner');
    const mapEl = document.getElementById('map');

    let map = null;
    let marker = null;
    let accuracyCircle = null;
    let pollTimer = null;

    function timeAgo(isoLike) {
        // MySQL TIMESTAMP strings ("YYYY-MM-DD HH:MM:SS") parse fine cross-browser
        // once the space is swapped for "T".
        const then = new Date(isoLike.replace(' ', 'T'));
        const seconds = Math.max(0, Math.round((Date.now() - then.getTime()) / 1000));
        if (seconds < 60) return seconds + 's ago';
        return Math.round(seconds / 60) + 'm ago';
    }

    function showLocation(data) {
        statusBanner.classList.add('d-none');
        mapEl.style.display = 'block';

        const lat = data.latitude, lng = data.longitude;

        if (!map) {
            map = L.map('map').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);
            marker = L.marker([lat, lng]).addTo(map)
                .bindPopup('Customer\'s last known location');
        } else {
            marker.setLatLng([lat, lng]);
        }

        if (data.accuracy_meters) {
            if (accuracyCircle) {
                accuracyCircle.setLatLng([lat, lng]).setRadius(data.accuracy_meters);
            } else {
                accuracyCircle = L.circle([lat, lng], {
                    radius: data.accuracy_meters,
                    color: '#0d6efd',
                    fillOpacity: 0.1,
                }).addTo(map);
            }
        }

        marker.setPopupContent('Last updated ' + timeAgo(data.updated_at));
        setTimeout(() => map.invalidateSize(), 100);
    }

    function showWaiting(message) {
        mapEl.style.display = 'none';
        statusBanner.classList.remove('d-none');
        statusBanner.innerHTML = '<i class="bi bi-hourglass-split"></i> ' + message;
    }

    function poll() {
        fetch(pollUrl)
            .then(res => res.json())
            .then(data => {
                if (!data.ok) {
                    showWaiting('Could not check location right now.');
                    return;
                }
                if (data.sharing) {
                    showLocation(data);
                } else if (data.reason === 'not_ongoing') {
                    showWaiting('This booking is no longer ongoing.');
                    clearInterval(pollTimer);
                } else {
                    showWaiting('Waiting for the customer to share their location&hellip;');
                }
            })
            .catch(() => showWaiting('Could not reach the server. Retrying&hellip;'));
    }

    poll();
    pollTimer = setInterval(poll, 5000);

    window.addEventListener('beforeunload', () => clearInterval(pollTimer));
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
