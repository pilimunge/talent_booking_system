<?php
/**
 * index.php
 * Public landing page.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/language.php';

attempt_remember_login($pdo);

// Featured providers: approved, highest rated first
$featured = $pdo->query(
    'SELECT p.*, u.fullname, u.profile_image, c.category_name
     FROM providers p
     JOIN users u ON u.id = p.user_id
     JOIN categories c ON c.id = p.category_id
     WHERE p.approval_status = "approved"
     ORDER BY p.average_rating DESC, p.created_at DESC
     LIMIT 8'
)->fetchAll();

// Categories with provider counts
$categories = $pdo->query(
    'SELECT c.*, COUNT(p.id) as provider_count
     FROM categories c
     LEFT JOIN providers p ON p.category_id = c.id AND p.approval_status = "approved"
     GROUP BY c.id
     ORDER BY c.category_name'
)->fetchAll();

// Testimonials: recent high-rated reviews
$testimonials = $pdo->query(
    'SELECT r.*, u.fullname as customer_name, pu.fullname as provider_name
     FROM reviews r
     JOIN users u ON u.id = r.customer_id
     JOIN providers p ON p.id = r.provider_id
     JOIN users pu ON pu.id = p.user_id
     WHERE r.rating >= 4 AND r.review IS NOT NULL AND r.review != ""
     ORDER BY r.created_at DESC
     LIMIT 6'
)->fetchAll();

$categoryIcons = [
    'MC' => 'bi-mic', 'Comedian' => 'bi-emoji-laughing', 'Musician' => 'bi-music-note-beamed',
    'Singer' => 'bi-mic-fill', 'DJ' => 'bi-disc', 'Content Creator' => 'bi-camera-reels',
    'Influencer' => 'bi-megaphone', 'Event Host' => 'bi-person-badge',
    'Motivational Speaker' => 'bi-chat-quote', 'Dancer' => 'bi-person-arms-up',
    'Photographer' => 'bi-camera', 'Videographer' => 'bi-camera-video',
];

$page_title = 'Home';
require_once __DIR__ . '/includes/header.php';
?>

<!-- HERO SECTION -->
<section class="hero-section">
    <div class="hero-bg" aria-hidden="true">
        <?php
        // Rotating background images — one per talent category.
        // 'external' => true uses a real photo from Pexels (free to hotlink,
        // no API key/attribution required). 'external' => false uses the
        // local placeholder in assets/images/hero/ — swap those files for
        // real photos any time (keep the same file name, or edit this list).
        // I could only confirm safe, working hotlink URLs for DJ and
        // Photographer this round — Dancer, Comedian, MC and Musician are
        // still placeholders; tell me to keep looking or drop in your own.
        $heroSlides = [
             ['file' => 'hero-commedian.jpeg',         'label' => 'Comedian',        'external' => false],
            ['file' => 'hero-content-creator.jpeg',  'label' => 'Content Creator', 'external' => false],
            ['file' => 'hero-dancer.jpeg',           'label' => 'Dancer',          'external' => false],
            ['file' => 'hero-dj.jpeg',           'label' => 'Dj',          'external' => false],
            ['file' => 'hero-mc.jpeg',           'label' => 'mc',          'external' => false],
            ['file' => 'hero-photographer.jpeg',               'label' => 'Photographer',              'external' => false],
            ['file' => 'hero-singer.jpeg',           'label' => 'Singer',          'external' => false],
            ['file' => 'hero-videographer.jpeg',         'label' => 'videographer',        'external' => false],
            
           
        ];
        $slideCount = count($heroSlides);
        $cycleSeconds = $slideCount * 4; // 4s visible per slide
        foreach ($heroSlides as $i => $slide):
            $imageUrl = $slide['external']
                ? $slide['file']
                : APP_URL . '/assets/images/hero/' . $slide['file'];
        ?>
            <div class="hero-bg-slide"
                 style="background-image:url('<?= e($imageUrl) ?>');
                        animation-duration:<?= (int) $cycleSeconds ?>s;
                        animation-delay:<?= (int) ($i * 4) ?>s;"
                 aria-label="<?= e($slide['label']) ?>"></div>
        <?php endforeach; ?>
        <div class="hero-bg-overlay"></div>
    </div>
    <div class="container position-relative">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <h1 class="display-5 mb-3">Find the Perfect Talent for Your Next Event</h1>
                <p class="lead mb-4 opacity-90">
                    Book trusted MCs, DJs, musicians, comedians, photographers, and more —
                    all in one place. Browse profiles, compare prices, and book with confidence.
                </p>
                <form action="<?= e(APP_URL) ?>/customer/search.php" method="GET" class="d-flex gap-2 flex-wrap">
                    <input type="text" name="q" class="form-control form-control-lg flex-grow-1" placeholder="Search DJs, MCs, comedians..." style="min-width:200px;">
                    <button type="submit" class="btn btn-warning btn-lg fw-semibold text-dark">
                        <i class="bi bi-search"></i> Search
                    </button>
                </form>
            </div>
            <div class="col-lg-5 d-none d-lg-block text-center">
                <i class="bi bi-stars" style="font-size: 12rem; opacity: 0.25;"></i>
            </div>
        </div>
    </div>
</section>

<!-- CATEGORIES -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="fw-bold">Browse by Category</h2>
            <p class="text-secondary">Find exactly the kind of talent you're looking for</p>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <?php foreach ($categories as $cat): ?>
                <a href="<?= e(APP_URL) ?>/customer/search.php?category=<?= (int) $cat['id'] ?>" class="category-pill">
                    <i class="bi <?= e($categoryIcons[$cat['category_name']] ?? 'bi-star') ?> me-1"></i>
                    <?= e($cat['category_name']) ?>
                    <span class="text-secondary">(<?= (int) $cat['provider_count'] ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FEATURED PROVIDERS -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-0">Featured Talent</h2>
                <p class="text-secondary mb-0">Top-rated providers on our platform</p>
            </div>
            <a href="<?= e(APP_URL) ?>/customer/search.php" class="btn btn-outline-primary">View All</a>
        </div>

        <?php if (empty($featured)): ?>
            <p class="text-secondary">No approved providers yet. Check back soon!</p>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($featured as $provider): ?>
                    <div class="col-sm-6 col-lg-3">
                        <a href="<?= e(APP_URL) ?>/customer/view_provider.php?id=<?= (int) $provider['id'] ?>" class="text-decoration-none text-dark">
                            <div class="card talent-card h-100 shadow-sm border-0">
                                <img src="<?= $provider['cover_image'] ? e(UPLOAD_COVER_URL . $provider['cover_image']) : 'https://placehold.co/400x300?text=' . urlencode($provider['fullname']) ?>" class="card-img-top" alt="<?= e($provider['fullname']) ?>">
                                <div class="card-body">
                                    <span class="badge bg-primary-subtle text-primary mb-2"><?= e($provider['category_name']) ?></span>
                                    <h5 class="card-title mb-1"><?= e($provider['fullname']) ?></h5>
                                    <p class="small text-secondary mb-1"><i class="bi bi-geo-alt"></i> <?= e($provider['location'] ?: 'Location not set') ?></p>
                                    <div class="mb-2"><?= render_stars((float) $provider['average_rating']) ?> <span class="small text-secondary">(<?= number_format((float) $provider['average_rating'], 1) ?>)</span></div>
                                    <p class="fw-semibold text-primary mb-0">From <?= format_money($provider['price']) ?></p>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- TESTIMONIALS -->
<?php if (!empty($testimonials)): ?>
<section class="py-5">

</section>
<?php endif; ?>

<!-- CTA for providers -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="row align-items-center bg-dark text-white rounded-4 p-5">
            <div class="col-lg-8">
                <h3 class="fw-bold mb-2">Are you a talented MC, DJ, or performer?</h3>
                <p class="mb-0 opacity-75">Join TalentConnect and start getting booked for events across Tanzania.</p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <a href="<?= e(APP_URL) ?>/auth/register.php" class="btn btn-warning btn-lg fw-semibold text-dark">Join as a Provider</a>
            </div>
        </div>
    </div>
</section>

<!-- CONTACT SECTION -->
<section class="py-5" id="contact">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <h2 class="fw-bold mb-2">Get in Touch</h2>
                <p class="text-secondary mb-4">Questions about booking or joining as a provider? We're here to help.</p>
                <div class="d-flex justify-content-center gap-4 flex-wrap">
                    <div><i class="bi bi-envelope-fill text-primary fs-4"></i><p class="mb-0 small">support@talentbooking.com</p></div>
                    <div><i class="bi bi-telephone-fill text-primary fs-4"></i><p class="mb-0 small">+255 623637559</p></div>
                    <div><i class="bi bi-geo-alt-fill text-primary fs-4"></i><p class="mb-0 small">Dodoma, Tanzania</p></div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
