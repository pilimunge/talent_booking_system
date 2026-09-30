<?php
/**
 * provider/_require_profile.php
 * Include AFTER require_role('provider') on any provider page that needs
 * an existing providers row (bookings, earnings, reviews, gallery, availability).
 * profile.php itself does NOT include this, since it's where the profile gets created.
 * Sets $providerProfile for the including page to use.
 */
$profileStmt = $pdo->prepare('SELECT * FROM providers WHERE user_id = :uid LIMIT 1');
$profileStmt->execute(['uid' => current_user_id()]);
$providerProfile = $profileStmt->fetch();

if (!$providerProfile) {
    set_flash('error', 'Please complete your provider profile first.');
    redirect('provider/profile.php');
}
