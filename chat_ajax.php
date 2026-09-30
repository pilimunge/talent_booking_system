<?php
/**
 * chat_ajax.php
 *
 * Shared customer <-> provider chat endpoint.
 *
 * GET:
 *   chat_ajax.php?action=poll&booking_id=1&after_id=0
 *
 * POST:
 *   action=send
 *   booking_id=1
 *   message=Hello
 *   csrf_token=...
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

require_role('customer', 'provider');

header('Content-Type: application/json; charset=utf-8');

try {

    /*
    |--------------------------------------------------------------------------
    | Get request information
    |--------------------------------------------------------------------------
    */

    $bookingId = (int) ($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);

    $action = trim(
        $_GET['action']
        ?? $_POST['action']
        ?? ''
    );

    if ($bookingId <= 0) {
        http_response_code(422);

        echo json_encode([
            'error' => 'Invalid booking ID.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Find booking and participants
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        'SELECT
            b.id,
            b.customer_id,
            p.id AS provider_id,
            p.user_id AS provider_user_id
         FROM bookings b
         INNER JOIN providers p
            ON p.id = b.provider_id
         WHERE b.id = :booking_id
         LIMIT 1'
    );

    $stmt->execute([
        'booking_id' => $bookingId
    ]);

    $booking = $stmt->fetch();

    if (!$booking) {
        http_response_code(404);

        echo json_encode([
            'error' => 'Booking not found.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Current logged-in user
    |--------------------------------------------------------------------------
    */

    $myId = (int) current_user_id();

    $customerId = (int) $booking['customer_id'];
    $providerUserId = (int) $booking['provider_user_id'];

    /*
    |--------------------------------------------------------------------------
    | Make sure current user belongs to this booking
    |--------------------------------------------------------------------------
    */

    $isCustomer = ($myId === $customerId);
    $isProvider = ($myId === $providerUserId);

    if (!$isCustomer && !$isProvider) {

        http_response_code(403);

        echo json_encode([
            'error' => 'You are not allowed to access this conversation.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Determine receiver
    |--------------------------------------------------------------------------
    */

    if ($isCustomer) {
        $receiverId = $providerUserId;
    } else {
        $receiverId = $customerId;
    }

    /*
    |--------------------------------------------------------------------------
    | POLL
    |--------------------------------------------------------------------------
    */

    if ($action === 'poll') {

        $afterId = max(
            0,
            (int) ($_GET['after_id'] ?? 0)
        );

        $stmt = $pdo->prepare(
            'SELECT
                id,
                sender_id,
                receiver_id,
                message,
                created_at
             FROM messages
             WHERE booking_id = :booking_id
               AND id > :after_id
             ORDER BY id ASC'
        );

        $stmt->execute([
            'booking_id' => $bookingId,
            'after_id' => $afterId
        ]);

        $rows = $stmt->fetchAll();

        $messages = [];

        foreach ($rows as $row) {

            $messages[] = [
                'id' => (int) $row['id'],

                'mine' =>
                    ((int) $row['sender_id'] === $myId),

                'message' =>
                    (string) $row['message'],

                'time' =>
                    date(
                        'H:i',
                        strtotime($row['created_at'])
                    )
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Mark messages received by current user as read
        |--------------------------------------------------------------------------
        */

        $markRead = $pdo->prepare(
            'UPDATE messages
             SET is_read = 1
             WHERE booking_id = :booking_id
               AND receiver_id = :receiver_id
               AND is_read = 0'
        );

        $markRead->execute([
            'booking_id' => $bookingId,
            'receiver_id' => $myId
        ]);

        echo json_encode([
            'success' => true,
            'messages' => $messages
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | SEND
    |--------------------------------------------------------------------------
    */

    if (
        $action === 'send'
        && $_SERVER['REQUEST_METHOD'] === 'POST'
    ) {

        /*
        | CSRF protection
        */
        verify_csrf();

        $message = trim(
            (string) ($_POST['message'] ?? '')
        );

        if ($message === '') {

            http_response_code(422);

            echo json_encode([
                'error' => 'Message cannot be empty.'
            ]);

            exit;
        }

        /*
        | Limit message length
        */
        if (mb_strlen($message) > 2000) {
            $message = mb_substr(
                $message,
                0,
                2000
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Insert message
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare(
            'INSERT INTO messages
                (
                    booking_id,
                    sender_id,
                    receiver_id,
                    message,
                    is_read
                )
             VALUES
                (
                    :booking_id,
                    :sender_id,
                    :receiver_id,
                    :message,
                    0
                )'
        );

        $stmt->execute([
            'booking_id' => $bookingId,
            'sender_id' => $myId,
            'receiver_id' => $receiverId,
            'message' => $message
        ]);

        $newId = (int) $pdo->lastInsertId();

        /*
        |--------------------------------------------------------------------------
        | Notification
        |--------------------------------------------------------------------------
        */

        try {

            push_notification(
                $pdo,
                $receiverId,
                'New message',
                current_user_name()
                . ' sent you a message about booking #'
                . $bookingId
                . '.',
                current_user_role()
            );

        } catch (Throwable $notificationError) {

            /*
            | Notification failure should NOT prevent
            | the chat message from being sent.
            */
        }

        /*
        |--------------------------------------------------------------------------
        | Return newly created message
        |--------------------------------------------------------------------------
        */

        echo json_encode([
            'success' => true,
            'id' => $newId,
            'mine' => true,
            'message' => $message,
            'time' => date('H:i')
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Unknown action
    |--------------------------------------------------------------------------
    */

    http_response_code(400);

    echo json_encode([
        'error' => 'Unknown chat action.'
    ]);

    exit;

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | IMPORTANT:
    | Return JSON instead of HTML/PHP error.
    |--------------------------------------------------------------------------
    */

    http_response_code(500);

    echo json_encode([
        'error' => 'Chat server error.',
        'details' => $e->getMessage()
    ]);

    exit;
}