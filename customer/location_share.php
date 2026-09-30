<?php
/**
 * customer/location_share.php
 *
 * Lets the customer turn on live GPS sharing for a single 'ongoing'
 * booking, so the assigned provider can see roughly where to go. Uses
 * the browser's Geolocation API (navigator.geolocation.watchPosition)
 * and pushes updates to customer/location_ajax.php.
 *
 * Note: browsers only grant Geolocation permission on HTTPS (or
 * http://localhost during development) - see the note in the page body.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');

$bookingId = (int) ($_GET['booking_id'] ?? 0);

if ($bookingId <= 0) {
    set_flash('error', 'Invalid booking ID.');
    redirect('customer/bookings.php');
}

$stmt = $pdo->prepare(
    'SELECT b.*, u.fullname AS provider_name
     FROM bookings b
     JOIN providers p ON p.id = b.provider_id
     JOIN users u ON u.id = p.user_id
     WHERE b.id = :id AND b.customer_id = :cid
     LIMIT 1'
);
$stmt->execute(['id' => $bookingId, 'cid' => current_user_id()]);
$booking = $stmt->fetch();

if (!$booking) {
    set_flash('error', 'Booking not found.');
    redirect('customer/bookings.php');
}

if ($booking['booking_status'] !== 'ongoing') {
    set_flash('error', 'Location sharing is only available while the booking is ongoing.');
    redirect('customer/booking_view.php?id=' . $bookingId);
}

$locStmt = $pdo->prepare('SELECT * FROM booking_locations WHERE booking_id = :bid');
$locStmt->execute(['bid' => $bookingId]);
$existingLocation = $locStmt->fetch();

$current_page = 'bookings';
$page_title = 'Share My Location';
require_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <a href="<?= e(APP_URL) ?>/customer/booking_view.php?id=<?= (int) $bookingId ?>" class="small text-decoration-none">
                <i class="bi bi-arrow-left"></i> Back to booking
            </a>

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body p-4">

                    <h5 class="fw-bold mb-1"><i class="bi bi-geo-alt-fill text-primary"></i> Share My Location</h5>
                    <p class="text-secondary small mb-4">
                        Help <?= e($booking['provider_name']) ?> find you for
                        "<?= e($booking['event_name']) ?>". Your location is only visible
                        to this provider, and only while this booking is ongoing.
                    </p>

                    <div id="geo-unsupported" class="alert alert-warning d-none">
                        <i class="bi bi-exclamation-triangle"></i>
                        Your browser doesn't support location sharing.
                    </div>

                    <div id="geo-insecure" class="alert alert-warning d-none">
                        <i class="bi bi-exclamation-triangle"></i>
                        Location sharing needs a secure (HTTPS) connection. Ask the site
                        owner to enable HTTPS, or test on <code>http://localhost</code>.
                    </div>

                    <div id="geo-error" class="alert alert-danger d-none"></div>

                    <div class="d-flex align-items-center justify-content-between border rounded-3 p-3 mb-3">
                        <div>
                            <div class="fw-semibold">
                                <span id="status-dot" class="d-inline-block rounded-circle bg-secondary" style="width:10px;height:10px;"></span>
                                <span id="status-text">Not sharing</span>
                            </div>
                            <div class="small text-secondary" id="status-updated">
                                <?= $existingLocation && $existingLocation['is_sharing']
                                    ? 'Last updated ' . e(format_date($existingLocation['updated_at']))
                                    : 'Turn on sharing so ' . e($booking['provider_name']) . ' can see where you are.' ?>
                            </div>
                        </div>
                        <button id="toggle-btn" type="button" class="btn btn-primary">
                            <i class="bi bi-broadcast"></i> Start Sharing
                        </button>
                    </div>

                    <div id="map" style="height: 320px; border-radius: .5rem; display:none;"></div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script>
(function () {
    const bookingId = <?= (int) $bookingId ?>;
    const ajaxUrl = '<?= e(APP_URL) ?>/customer/location_ajax.php';
    const csrfToken = '<?= e(csrf_token()) ?>';

    const toggleBtn = document.getElementById('toggle-btn');
    const statusDot = document.getElementById('status-dot');
    const statusText = document.getElementById('status-text');
    const statusUpdated = document.getElementById('status-updated');
    const geoError = document.getElementById('geo-error');
    const mapEl = document.getElementById('map');

    let watchId = null;
    let sharing = false;
    let map = null;
    let marker = null;

    if (!('geolocation' in navigator)) {
        document.getElementById('geo-unsupported').classList.remove('d-none');
        toggleBtn.disabled = true;
        return;
    }

    // Geolocation requires a "secure context" (HTTPS or localhost).
    if (!window.isSecureContext) {
        document.getElementById('geo-insecure').classList.remove('d-none');
        toggleBtn.disabled = true;
        return;
    }

    function setSharingUI(isSharing) {
        sharing = isSharing;
        statusDot.classList.toggle('bg-secondary', !isSharing);
        statusDot.classList.toggle('bg-success', isSharing);
        statusText.textContent = isSharing ? 'Sharing live' : 'Not sharing';
        toggleBtn.innerHTML = isSharing
            ? '<i class="bi bi-stop-circle"></i> Stop Sharing'
            : '<i class="bi bi-broadcast"></i> Start Sharing';
        toggleBtn.classList.toggle('btn-primary', !isSharing);
        toggleBtn.classList.toggle('btn-outline-danger', isSharing);
        mapEl.style.display = isSharing ? 'block' : 'none';
        if (isSharing && map) {
            setTimeout(() => map.invalidateSize(), 100);
        }
    }

    function ensureMap(lat, lng) {
        if (!map) {
            map = L.map('map').setView([lat, lng], 15);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);
            marker = L.marker([lat, lng]).addTo(map);
        } else {
            marker.setLatLng([lat, lng]);
            map.panTo([lat, lng]);
        }
    }

    function sendPosition(action, position) {
        const body = new URLSearchParams({
            csrf_token: csrfToken,
            booking_id: bookingId,
            action: action,
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy ?? '',
        });

        fetch(ajaxUrl, { method: 'POST', body: body })
            .then(res => res.json())
            .then(data => {
                if (!data.ok) {
                    geoError.textContent = data.error || 'Something went wrong.';
                    geoError.classList.remove('d-none');
                    stopSharing();
                    return;
                }
                geoError.classList.add('d-none');
                ensureMap(position.coords.latitude, position.coords.longitude);
                statusUpdated.textContent = 'Last updated just now';
            })
            .catch(() => {
                geoError.textContent = 'Could not reach the server. Will retry on the next update.';
                geoError.classList.remove('d-none');
            });
    }

    function startSharing() {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setSharingUI(true);
                sendPosition('start', position);

                watchId = navigator.geolocation.watchPosition(
                    (pos) => sendPosition('update', pos),
                    (err) => {
                        geoError.textContent = 'Location error: ' + err.message;
                        geoError.classList.remove('d-none');
                    },
                    { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 }
                );
            },
            (err) => {
                geoError.textContent = 'Could not get your location: ' + err.message;
                geoError.classList.remove('d-none');
            },
            { enableHighAccuracy: true, timeout: 15000 }
        );
    }

    function stopSharing() {
        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }
        setSharingUI(false);

        const body = new URLSearchParams({
            csrf_token: csrfToken,
            booking_id: bookingId,
            action: 'stop',
        });
        fetch(ajaxUrl, { method: 'POST', body: body }).catch(() => {});
    }

    toggleBtn.addEventListener('click', () => {
        if (sharing) {
            stopSharing();
        } else {
            startSharing();
        }
    });

    // Make sure we stop sharing (best-effort) if the customer navigates away
    // while it's on, so we're not silently tracking in the background.
    window.addEventListener('beforeunload', () => {
        if (sharing) {
            navigator.sendBeacon(ajaxUrl, new URLSearchParams({
                csrf_token: csrfToken,
                booking_id: bookingId,
                action: 'stop',
            }));
        }
    });

    <?php if ($existingLocation && $existingLocation['is_sharing']): ?>
    // Resume the UI as "sharing" if a previous session left it on, but we
    // still need a fresh watchPosition since browsers don't let us read
    // location without a new permission-backed call.
    setSharingUI(true);
    ensureMap(<?= (float) $existingLocation['latitude'] ?>, <?= (float) $existingLocation['longitude'] ?>);
    <?php endif; ?>
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
