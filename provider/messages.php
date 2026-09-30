<?php

/**
 * provider/messages.php
 * Provider message inbox
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('provider');

require_once __DIR__ . '/_require_profile.php';

$page_title = 'Messages';
$current_page = 'messages';

/*
|--------------------------------------------------------------------------
| Get all conversations belonging to current provider
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT
        b.id AS booking_id,
        b.event_name,

        u.id AS customer_user_id,
        u.fullname AS customer_name,

        m.message AS last_message,
        m.created_at AS last_message_time,

        (
            SELECT COUNT(*)
            FROM messages mr
            WHERE mr.booking_id = b.id
              AND mr.receiver_id = :my_id_unread
              AND mr.is_read = 0
        ) AS unread_count

     FROM bookings b

     INNER JOIN users u
        ON u.id = b.customer_id

     LEFT JOIN messages m
        ON m.id = (
            SELECT MAX(m2.id)
            FROM messages m2
            WHERE m2.booking_id = b.id
        )

     WHERE b.provider_id = :provider_id

     ORDER BY
        COALESCE(m.created_at, b.created_at) DESC'
);

$stmt->execute([
    'my_id_unread' => current_user_id(),
    'provider_id' => $providerProfile['id']
]);

$conversations = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';

?>

<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-9">

            <!-- PAGE HEADER -->

            <div class="d-flex justify-content-between align-items-center mb-4">

                <div>

                    <h3 class="mb-1">

                        <i class="bi bi-envelope-fill text-primary"></i>

                        Messages

                    </h3>

                    <p class="text-secondary mb-0">
                        Your conversations with customers.
                    </p>

                </div>

            </div>
            <a
    href="<?= e(APP_URL) ?>/index.php"
    class="btn btn-primary mb-3"
>
    <i class="bi bi-house-door"></i>
    Back to Home
</a>


            <?php if (empty($conversations)): ?>

                <!-- NO MESSAGES -->

                <div class="card border-0 shadow-sm">

                    <div class="card-body text-center py-5">

                        <i
                            class="bi bi-chat-square-text"
                            style="font-size: 3rem;"
                        ></i>

                        <h5 class="mt-3">
                            No messages yet
                        </h5>

                        <p class="text-secondary">
                            Your conversations with customers will appear here.
                        </p>

                        <a
                            href="<?= e(APP_URL) ?>/provider/bookings.php"
                            class="btn btn-primary"
                        >

                            <i class="bi bi-calendar-check"></i>

                            View Bookings

                        </a>

                    </div>

                </div>

            <?php else: ?>

                <!-- CONVERSATIONS -->

                <div class="card border-0 shadow-sm">

                    <div class="list-group list-group-flush">

                        <?php foreach ($conversations as $conversation): ?>

                            <?php
                            $unread = (int) $conversation['unread_count'];
                            ?>

                            <a
                                href="<?= e(APP_URL) ?>/provider/chat.php?booking_id=<?= (int) $conversation['booking_id'] ?>"
                                class="list-group-item list-group-item-action py-3"
                            >

                                <div class="d-flex align-items-center">

                                    <!-- AVATAR -->

                                    <div
                                        class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3"
                                        style="
                                            width:48px;
                                            height:48px;
                                            flex-shrink:0;
                                        "
                                    >

                                        <i class="bi bi-person-fill fs-5"></i>

                                    </div>


                                    <!-- MESSAGE INFO -->

                                    <div class="flex-grow-1 min-width-0">

                                        <div class="d-flex justify-content-between align-items-center">

                                            <h6 class="mb-1">

                                                <?= e($conversation['customer_name']) ?>

                                                <?php if ($unread > 0): ?>

                                                    <span class="badge bg-primary ms-1">

                                                        <?= $unread ?>

                                                    </span>

                                                <?php endif; ?>

                                            </h6>


                                            <?php if (!empty($conversation['last_message_time'])): ?>

                                                <small class="text-secondary">

                                                    <?= e(
                                                        date(
                                                            'd M, H:i',
                                                            strtotime(
                                                                $conversation['last_message_time']
                                                            )
                                                        )
                                                    ) ?>

                                                </small>

                                            <?php endif; ?>

                                        </div>


                                        <div class="small text-secondary mb-1">

                                            Booking:
                                            <?= e($conversation['event_name']) ?>

                                        </div>


                                        <div
                                            class="text-secondary text-truncate"
                                            style="max-width:90%;"
                                        >

                                            <?php if (!empty($conversation['last_message'])): ?>

                                                <?= e($conversation['last_message']) ?>

                                            <?php else: ?>

                                                <span class="fst-italic">
                                                    No messages yet. Start the conversation.
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    </div>


                                    <!-- ARROW -->

                                    <div class="ms-3">

                                        <i class="bi bi-chevron-right text-secondary"></i>

                                    </div>

                                </div>

                            </a>

                        <?php endforeach; ?>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>