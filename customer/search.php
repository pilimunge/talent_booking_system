<?php
/**
 * customer/search.php
 *
 * Public search page (guests can browse too).
 * Filters:
 * - Keyword
 * - Category
 * - Location
 * - Minimum rating
 * - Maximum price
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/language.php';
require_once __DIR__ . '/../includes/header.php';


attempt_remember_login($pdo);


/*
|--------------------------------------------------------------------------
| Get Search Filters
|--------------------------------------------------------------------------
*/
$q = trim($_GET['q'] ?? '');

$categoryId = $_GET['category'] ?? '';

$location = trim(
    $_GET['location'] ?? ''
);

$minRating = $_GET['min_rating'] ?? '';

$maxPrice = $_GET['max_price'] ?? '';


/*
|--------------------------------------------------------------------------
| Build Search Conditions
|--------------------------------------------------------------------------
*/
$conditions = [
    'p.approval_status = "approved"'
];

$params = [];


/*
|--------------------------------------------------------------------------
| Keyword Search
|--------------------------------------------------------------------------
*/
if ($q !== '') {

    $conditions[] = '
        (
            u.fullname LIKE :q1
            OR p.bio LIKE :q2
            OR p.skills LIKE :q3
        )
    ';

    $params['q1'] = '%' . $q . '%';
    $params['q2'] = '%' . $q . '%';
    $params['q3'] = '%' . $q . '%';
}


/*
|--------------------------------------------------------------------------
| Category Filter
|--------------------------------------------------------------------------
*/
if ($categoryId !== '') {

    $conditions[] =
        'p.category_id = :category_id';

    $params['category_id'] =
        (int) $categoryId;
}


/*
|--------------------------------------------------------------------------
| Location Filter
|--------------------------------------------------------------------------
*/
if ($location !== '') {

    $conditions[] =
        'p.location LIKE :location';

    $params['location'] =
        '%' . $location . '%';
}


/*
|--------------------------------------------------------------------------
| Minimum Rating Filter
|--------------------------------------------------------------------------
*/
if ($minRating !== '') {

    $conditions[] =
        'p.average_rating >= :min_rating';

    $params['min_rating'] =
        (float) $minRating;
}


/*
|--------------------------------------------------------------------------
| Maximum Price Filter
|--------------------------------------------------------------------------
*/
if ($maxPrice !== '') {

    $conditions[] =
        'p.price <= :max_price';

    $params['max_price'] =
        (float) $maxPrice;
}


$whereClause = implode(
    ' AND ',
    $conditions
);


/*
|--------------------------------------------------------------------------
| Pagination
|--------------------------------------------------------------------------
*/
$perPage = 12;

$page = max(
    1,
    (int) ($_GET['page'] ?? 1)
);

$offset =
    ($page - 1) * $perPage;


/*
|--------------------------------------------------------------------------
| Count Total Providers
|--------------------------------------------------------------------------
*/
$sqlCount = "
    SELECT COUNT(*)
    FROM providers p
    JOIN users u
        ON u.id = p.user_id
    WHERE {$whereClause}
";

$countStmt = $pdo->prepare(
    $sqlCount
);


/*
|--------------------------------------------------------------------------
| Bind Search Parameters
|--------------------------------------------------------------------------
*/
foreach ($params as $key => $value) {

    if (is_int($value)) {

        $countStmt->bindValue(
            ':' . $key,
            $value,
            PDO::PARAM_INT
        );

    } else {

        $countStmt->bindValue(
            ':' . $key,
            $value
        );
    }
}


/*
|--------------------------------------------------------------------------
| Execute Count Query
|--------------------------------------------------------------------------
*/
$countStmt->execute();


$totalResults = (int)
    $countStmt->fetchColumn();


$totalPages = max(
    1,
    (int) ceil(
        $totalResults / $perPage
    )
);


/*
|--------------------------------------------------------------------------
| Get Providers
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        p.*,
        u.fullname,
        u.profile_image,
        c.category_name
    FROM providers p

    JOIN users u
        ON u.id = p.user_id

    JOIN categories c
        ON c.id = p.category_id

    WHERE {$whereClause}

    ORDER BY
        p.average_rating DESC,
        p.created_at DESC

    LIMIT :limit
    OFFSET :offset
";


$stmt = $pdo->prepare(
    $sql
);


/*
|--------------------------------------------------------------------------
| Bind Search Parameters
|--------------------------------------------------------------------------
*/
foreach ($params as $key => $value) {

    if (is_int($value)) {

        $stmt->bindValue(
            ':' . $key,
            $value,
            PDO::PARAM_INT
        );

    } else {

        $stmt->bindValue(
            ':' . $key,
            $value
        );
    }
}


/*
|--------------------------------------------------------------------------
| Bind Pagination
|--------------------------------------------------------------------------
*/
$stmt->bindValue(
    ':limit',
    $perPage,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);


/*
|--------------------------------------------------------------------------
| Execute Provider Query
|--------------------------------------------------------------------------
*/
$stmt->execute();

$providers =
    $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Get Categories
|--------------------------------------------------------------------------
*/
$categories = $pdo->query(
    'SELECT *
     FROM categories
     ORDER BY category_name'
)->fetchAll();


/*
|--------------------------------------------------------------------------
| Page Settings
|--------------------------------------------------------------------------
*/
$page_title = 'Find Talent';

require_once __DIR__ . '/../includes/header.php';
?>


<div class="container py-4">

    <!-- Page Title -->
    <h2 class="fw-bold mb-4">
        <i class="bi bi-search"></i>
        Find Talent
    </h2>


    <!-- Filters -->
    <form
        method="GET"
        action=""
        class="card border-0 shadow-sm p-3 p-md-4 mb-4"
    >

        <div class="row g-3">


            <!-- Keyword -->
            <div class="col-md-3">

                <label class="form-label small fw-semibold">
                    Keyword
                </label>

                <input
                    type="text"
                    name="q"
                    class="form-control"
                    value="<?= e($q) ?>"
                    placeholder="Name, skill..."
                >

            </div>


            <!-- Category -->
            <div class="col-md-3">

                <label class="form-label small fw-semibold">
                    Category
                </label>

                <select
                    name="category"
                    class="form-select"
                >

                    <option value="">
                        All Categories
                    </option>


                    <?php foreach ($categories as $cat): ?>

                        <option
                            value="<?= (int) $cat['id'] ?>"
                            <?= (string) $categoryId ===
                                (string) $cat['id']
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= e(
                                $cat['category_name']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Location -->
            <div class="col-md-2">

                <label class="form-label small fw-semibold">
                    Location
                </label>

                <input
                    type="text"
                    name="location"
                    class="form-control"
                    value="<?= e($location) ?>"
                    placeholder="City"
                >

            </div>


            <!-- Minimum Rating -->
            <div class="col-md-2">

                <label class="form-label small fw-semibold">
                    Min Rating
                </label>

                <select
                    name="min_rating"
                    class="form-select"
                >

                    <option value="">
                        Any
                    </option>


                    <?php foreach ([4, 3, 2, 1] as $r): ?>

                        <option
                            value="<?= $r ?>"
                            <?= (string) $minRating ===
                                (string) $r
                                ? 'selected'
                                : ''
                            ?>
                        >
                            <?= $r ?>+ Stars
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Maximum Price -->
            <div class="col-md-2">

                <label class="form-label small fw-semibold">
                    Max Price (TZS)
                </label>

                <input
                    type="number"
                    name="max_price"
                    class="form-control"
                    value="<?= e($maxPrice) ?>"
                    placeholder="e.g. 500000"
                    min="0"
                    step="0.01"
                >

            </div>

        </div>


        <!-- Filter Buttons -->
        <div class="mt-3">

            <button
                type="submit"
                class="btn btn-primary px-4"
            >
                <i class="bi bi-funnel"></i>
                Apply Filters
            </button>


            <a
                href="<?= e(APP_URL) ?>/customer/search.php"
                class="btn btn-outline-secondary"
            >
                Clear
            </a>

        </div>

    </form>


    <!-- Result Count -->
    <p class="text-secondary">

        <?= (int) $totalResults ?>
        provider(s) found

    </p>


    <?php if (empty($providers)): ?>

        <!-- No Results -->
        <div class="alert alert-light border text-center py-5">

            <i class="bi bi-emoji-frown fs-1 text-secondary"></i>

            <p class="mt-3 mb-0">
                No providers match your search.
                Try adjusting your filters.
            </p>

        </div>


    <?php else: ?>


        <!-- Providers -->
        <div class="row g-4">

            <?php foreach ($providers as $provider): ?>

                <div class="col-sm-6 col-lg-4 col-xl-3">

                    <a
                        href="<?= e(APP_URL) ?>/customer/view_provider.php?id=<?= (int) $provider['id'] ?>"
                        class="text-decoration-none text-dark"
                    >

                        <div class="card talent-card h-100 shadow-sm border-0">


                            <!-- Cover Image -->
                            <img
                                src="<?= $provider['cover_image']
                                    ? e(
                                        UPLOAD_COVER_URL
                                        . $provider['cover_image']
                                    )
                                    : 'https://placehold.co/400x300?text='
                                      . urlencode(
                                          $provider['fullname']
                                      )
                                ?>"
                                class="card-img-top"
                                alt="<?= e(
                                    $provider['fullname']
                                ) ?>"
                            >


                            <div class="card-body">


                                <!-- Category -->
                                <span
                                    class="badge bg-primary-subtle text-primary mb-2"
                                >
                                    <?= e(
                                        $provider['category_name']
                                    ) ?>
                                </span>


                                <!-- Provider Name -->
                                <h5 class="card-title mb-1">

                                    <?= e(
                                        $provider['fullname']
                                    ) ?>

                                </h5>


                                <!-- Location -->
                                <p class="small text-secondary mb-1">

                                    <i class="bi bi-geo-alt"></i>

                                    <?= e(
                                        $provider['location']
                                        ?: 'N/A'
                                    ) ?>

                                </p>


                                <!-- Rating -->
                                <div class="mb-2">

                                    <?= render_stars(
                                        (float)
                                        $provider['average_rating']
                                    ) ?>

                                </div>


                                <!-- Price -->
                                <p class="fw-semibold text-primary mb-0">

                                    From
                                    <?= format_money(
                                        $provider['price']
                                    ) ?>

                                </p>

                            </div>

                        </div>

                    </a>

                </div>

            <?php endforeach; ?>

        </div>


        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>

            <nav class="mt-4">

                <ul class="pagination justify-content-center">

                    <?php for (
                        $i = 1;
                        $i <= $totalPages;
                        $i++
                    ): ?>

                        <li
                            class="page-item <?= $i === $page
                                ? 'active'
                                : ''
                            ?>"
                        >

                            <a
                                class="page-link"
                                href="?<?= http_build_query(
                                    array_merge(
                                        $_GET,
                                        [
                                            'page' => $i
                                        ]
                                    )
                                ) ?>"
                            >
                                <?= $i ?>
                            </a>

                        </li>

                    <?php endfor; ?>

                </ul>

            </nav>

        <?php endif; ?>

    <?php endif; ?>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>