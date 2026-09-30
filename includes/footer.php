<?php

/**
 * =========================================================
 * footer.php
 * Shared Footer - Talent Booking System
 * =========================================================
 */

?>

<!-- =========================================================
     FOOTER
     ========================================================= -->

<footer class="bg-dark text-light mt-5 pt-5 pb-4">

    <div class="container">

        <div class="row g-4">


            <!-- =================================================
                 ABOUT
                 ================================================= -->

            <div class="col-md-4">

                <h5 class="fw-bold">

                    <i class="bi bi-stars"></i>

                    <?= e(APP_NAME) ?>

                </h5>

                <p class="text-secondary small">

                    Connecting customers with talented MCs,
                    DJs, musicians, comedians, photographers,
                    and more for unforgettable events.

                </p>

            </div>


            <!-- =================================================
                 QUICK LINKS
                 ================================================= -->

            <div class="col-md-4">

                <h6 class="fw-bold">
                    Quick Links
                </h6>

                <ul class="list-unstyled small">


                    <li class="mb-2">

                        <a
                            href="<?= e(APP_URL) ?>/index.php"
                            class="text-secondary text-decoration-none"
                        >

                            <?= e(__t('home')) ?>

                        </a>

                    </li>


                    <li class="mb-2">

                        <a
                            href="<?= e(APP_URL) ?>/customer/search.php"
                            class="text-secondary text-decoration-none"
                        >

                            <?= e(__t('browse_talent')) ?>

                        </a>

                    </li>


                    <li class="mb-2">

                        <a
                            href="<?= e(APP_URL) ?>/auth/register.php"
                            class="text-secondary text-decoration-none"
                        >

                            <?= e(__t('register')) ?>

                        </a>

                    </li>


                </ul>

            </div>


            <!-- =================================================
                 CONTACT
                 ================================================= -->

            <div class="col-md-4">

                <h6 class="fw-bold">
                    <?= e(__t('contact_us')) ?>
                </h6>


                <ul
                    class="list-unstyled small text-secondary"
                >

                    <li class="mb-2">

                        <i class="bi bi-envelope me-2"></i>

                        support@talentbooking.com

                    </li>


                    <li class="mb-2">

                        <i class="bi bi-telephone me-2"></i>

                        +255 700 000 000

                    </li>

                </ul>

            </div>


        </div>


        <hr class="border-secondary">


        <p class="text-center small text-secondary mb-0">

            &copy;

            <?= date('Y') ?>

            <?= e(APP_NAME) ?>.

            All rights reserved.

        </p>

    </div>

</footer>


<!-- =========================================================
     BOOTSTRAP JAVASCRIPT
     IMPORTANT:
     KEEP THIS ONLY ONCE
     ========================================================= -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<!-- =========================================================
     CUSTOM JAVASCRIPT
     ========================================================= -->

<script
    src="<?= e(APP_URL) ?>/assets/js/script.js"
></script>


</body>

</html>