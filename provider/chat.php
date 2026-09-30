<?php

/**
 * provider/chat.php
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('provider');

require_once __DIR__ . '/_require_profile.php';

$bookingId = (int) ($_GET['booking_id'] ?? 0);

if ($bookingId <= 0) {

    set_flash(
        'error',
        'Invalid booking ID.'
    );

    redirect('provider/bookings.php');
}


/*
|--------------------------------------------------------------------------
| Load booking
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT
        b.id,
        b.event_name,
        u.fullname AS customer_name
     FROM bookings b
     INNER JOIN users u
        ON u.id = b.customer_id
     WHERE b.id = :booking_id
       AND b.provider_id = :provider_id
     LIMIT 1'
);

$stmt->execute([
    'booking_id' => $bookingId,
    'provider_id' => $providerProfile['id']
]);

$booking = $stmt->fetch();

if (!$booking) {

    set_flash(
        'error',
        'Booking not found.'
    );

    redirect('provider/bookings.php');
}


$current_page = 'bookings';
$page_title = 'Chat';

require_once __DIR__ . '/../includes/header.php';

?>

<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-7">

            <a
                href="<?= e(APP_URL) ?>/provider/booking_view.php?id=<?= (int) $bookingId ?>"
                class="small text-decoration-none"
            >

                <i class="bi bi-arrow-left"></i>

                Back to booking

            </a>


            <div class="card border-0 shadow-sm mt-3">

                <div class="card-header bg-white py-3">

                    <h5 class="mb-0">

                        <i class="bi bi-chat-dots-fill text-primary"></i>

                        <?= e($booking['customer_name']) ?>

                    </h5>

                    <small class="text-secondary">

                        "<?= e($booking['event_name']) ?>"

                    </small>

                </div>


                <!-- CHAT LOG -->

                <div
                    id="chat-log"
                    class="card-body"
                    style="
                        height:420px;
                        overflow-y:auto;
                        display:flex;
                        flex-direction:column;
                        gap:.5rem;
                    "
                >

                    <p
                        class="text-secondary text-center small mt-auto mb-2"
                        id="chat-empty"
                    >
                        No messages yet — say hello!
                    </p>

                </div>


                <!-- FORM -->

                <div class="card-footer bg-white">

                    <form
                        id="chat-form"
                        class="d-flex gap-2"
                    >

                        <input
                            type="text"
                            id="chat-input"
                            class="form-control"
                            placeholder="Type a message..."
                            maxlength="2000"
                            autocomplete="off"
                            required
                        >

                        <button
                            type="submit"
                            class="btn btn-primary"
                        >

                            <i class="bi bi-send-fill"></i>

                        </button>

                    </form>


                    <div
                        id="chat-error"
                        class="alert alert-danger mt-2 mb-0 d-none"
                    ></div>

                </div>

            </div>

        </div>

    </div>

</div>


<script>

(function () {

    const bookingId =
        <?= (int) $bookingId ?>;


    const csrfToken =
        '<?= e($_SESSION['csrf_token'] ?? '') ?>';


    const chatLog =
        document.getElementById('chat-log');


    const emptyNote =
        document.getElementById('chat-empty');


    const form =
        document.getElementById('chat-form');


    const input =
        document.getElementById('chat-input');


    const errorBox =
        document.getElementById('chat-error');


    let lastId = 0;


    /*
    |--------------------------------------------------------------------------
    | Error
    |--------------------------------------------------------------------------
    */

    function showError(message) {

        errorBox.textContent =
            message;

        errorBox.classList.remove(
            'd-none'
        );
    }


    function hideError() {

        errorBox.textContent = '';

        errorBox.classList.add(
            'd-none'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Append message
    |--------------------------------------------------------------------------
    */

    function appendMessage(message) {

        if (emptyNote) {

            emptyNote.remove();
        }


        const wrapper =
            document.createElement('div');


        wrapper.style.alignSelf =
            message.mine
                ? 'flex-end'
                : 'flex-start';


        wrapper.style.maxWidth =
            '75%';


        const bubble =
            document.createElement('div');


        bubble.className =
            message.mine
                ? 'px-3 py-2 rounded-3 bg-primary text-white'
                : 'px-3 py-2 rounded-3 bg-light';


        bubble.textContent =
            message.message;


        const time =
            document.createElement('div');


        time.className =
            'small text-secondary mt-1'
            + (
                message.mine
                    ? ' text-end'
                    : ''
            );


        time.textContent =
            message.time;


        wrapper.appendChild(
            bubble
        );


        wrapper.appendChild(
            time
        );


        chatLog.appendChild(
            wrapper
        );


        chatLog.scrollTop =
            chatLog.scrollHeight;


        lastId =
            Math.max(
                lastId,
                Number(message.id)
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Poll
    |--------------------------------------------------------------------------
    */

    async function poll() {

        try {

            const url =
                '<?= e(APP_URL) ?>/chat_ajax.php'
                + '?action=poll'
                + '&booking_id='
                + encodeURIComponent(bookingId)
                + '&after_id='
                + encodeURIComponent(lastId);


            const response =
                await fetch(
                    url,
                    {
                        method: 'GET',
                        cache: 'no-store',
                        credentials: 'same-origin'
                    }
                );


            const data =
                await response.json();


            if (!response.ok) {

                throw new Error(
                    data.error
                    || data.details
                    || 'Failed to load messages.'
                );
            }


            hideError();


            if (Array.isArray(data.messages)) {

                data.messages.forEach(
                    appendMessage
                );
            }


        } catch (error) {

            console.error(
                'Chat polling error:',
                error
            );


            showError(
                error.message
                || 'Unable to load messages.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Send
    |--------------------------------------------------------------------------
    */

    form.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();

            hideError();


            const message =
                input.value.trim();


            if (!message) {

                return;
            }


            const body =
                new URLSearchParams();


            body.append(
                'action',
                'send'
            );


            body.append(
                'booking_id',
                bookingId
            );


            body.append(
                'message',
                message
            );


            body.append(
                'csrf_token',
                csrfToken
            );


            try {

                const response =
                    await fetch(
                        '<?= e(APP_URL) ?>/chat_ajax.php',
                        {
                            method: 'POST',

                            headers: {
                                'Content-Type':
                                    'application/x-www-form-urlencoded; charset=UTF-8'
                            },

                            body: body.toString(),

                            credentials:
                                'same-origin'
                        }
                    );


                const data =
                    await response.json();


                if (!response.ok) {

                    throw new Error(
                        data.error
                        || data.details
                        || 'Message was not sent.'
                    );
                }


                if (data.error) {

                    throw new Error(
                        data.error
                    );
                }


                input.value = '';

                appendMessage(data);

                input.focus();


            } catch (error) {

                console.error(
                    'Chat send error:',
                    error
                );


                showError(
                    error.message
                    || 'Unable to send message.'
                );
            }

        }
    );


    /*
    |--------------------------------------------------------------------------
    | Start
    |--------------------------------------------------------------------------
    */

    poll();

    setInterval(
        poll,
        3000
    );

})();

</script>


<?php require_once __DIR__ . '/../includes/footer.php'; ?>