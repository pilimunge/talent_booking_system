/* =====================================================================
   script.js
   Shared JavaScript behaviors used across the Talent Booking Management
   System: interactive star ratings, image previews, confirm dialogs.
   ===================================================================== */

document.addEventListener('DOMContentLoaded', function () {

    /* -----------------------------------------------------------------
       1. Interactive star rating input
       Usage: <div class="rating-input" data-input="#rating_value">
                <i class="bi bi-star" data-value="1"></i> ... x5
              </div>
              <input type="hidden" id="rating_value" name="rating" value="0">
    ----------------------------------------------------------------- */
    document.querySelectorAll('.rating-input').forEach(function (container) {
        const hiddenInputSelector = container.dataset.input;
        const hiddenInput = document.querySelector(hiddenInputSelector);
        const stars = container.querySelectorAll('i[data-value]');

        function paintStars(rating) {
            stars.forEach(function (star) {
                const value = parseInt(star.dataset.value, 10);
                star.classList.toggle('bi-star-fill', value <= rating);
                star.classList.toggle('bi-star', value > rating);
            });
        }

        stars.forEach(function (star) {
            star.addEventListener('click', function () {
                const value = star.dataset.value;
                if (hiddenInput) hiddenInput.value = value;
                paintStars(parseInt(value, 10));
            });
            star.addEventListener('mouseenter', function () {
                paintStars(parseInt(star.dataset.value, 10));
            });
        });

        container.addEventListener('mouseleave', function () {
            const current = hiddenInput ? parseInt(hiddenInput.value || '0', 10) : 0;
            paintStars(current);
        });

        // Initialize with existing value, if any
        if (hiddenInput && hiddenInput.value) {
            paintStars(parseInt(hiddenInput.value, 10));
        }
    });

    /* -----------------------------------------------------------------
       2. Live image preview for file inputs
       Usage: <input type="file" class="image-preview-input" data-preview="#previewImg">
              <img id="previewImg" src="..." >
    ----------------------------------------------------------------- */
    document.querySelectorAll('.image-preview-input').forEach(function (input) {
        const previewSelector = input.dataset.preview;
        const previewEl = document.querySelector(previewSelector);
        if (!previewEl) return;

        input.addEventListener('change', function () {
            const file = input.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function (e) {
                previewEl.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    });

    /* -----------------------------------------------------------------
       3. Confirm dialogs for destructive actions
       Usage: <form data-confirm="Are you sure you want to delete this?">
    ----------------------------------------------------------------- */
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const message = form.dataset.confirm || 'Are you sure?';
            if (!window.confirm(message)) {
                e.preventDefault();
            }
        });
    });

    /* -----------------------------------------------------------------
       4. Auto-dismiss alerts after 5 seconds
    ----------------------------------------------------------------- */
    document.querySelectorAll('.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });

    /* -----------------------------------------------------------------
       5. Bootstrap tooltip activation (if any [data-bs-toggle="tooltip"])
    ----------------------------------------------------------------- */
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el);
    });

    /* -----------------------------------------------------------------
       6. Multi-image gallery preview count
       Usage: <input type="file" id="galleryInput" multiple>
              <span id="galleryCount"></span>
    ----------------------------------------------------------------- */
    const galleryInput = document.getElementById('galleryInput');
    const galleryCount = document.getElementById('galleryCount');
    if (galleryInput && galleryCount) {
        galleryInput.addEventListener('change', function () {
            const count = galleryInput.files.length;
            galleryCount.textContent = count > 0 ? count + ' image(s) selected' : '';
        });
    }
});
