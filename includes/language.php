<?php

/**
 * =========================================================
 * LANGUAGE SYSTEM
 * Talent Booking System
 * =========================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Change language
|--------------------------------------------------------------------------
*/

if (isset($_GET['lang'])) {

    $selectedLanguage = $_GET['lang'];

    /*
     * Only allow languages that actually exist.
     */
    $allowedLanguages = ['en', 'sw'];

    if (in_array($selectedLanguage, $allowedLanguages, true)) {

        $_SESSION['language'] = $selectedLanguage;

    }

    /*
     * Remove ?lang=en from URL after changing language.
     */
    $redirectUrl = strtok($_SERVER['REQUEST_URI'], '?');

    /*
     * Keep other GET parameters.
     */
    $queryParameters = $_GET;

    unset($queryParameters['lang']);

    if (!empty($queryParameters)) {
        $redirectUrl .= '?' . http_build_query($queryParameters);
    }

    header('Location: ' . $redirectUrl);
    exit;
}


/*
|--------------------------------------------------------------------------
| Default language
|--------------------------------------------------------------------------
*/

$currentLanguage = $_SESSION['language'] ?? 'en';


/*
|--------------------------------------------------------------------------
| Load language file
|--------------------------------------------------------------------------
*/

if ($currentLanguage === 'sw') {

    $lang = require __DIR__ . '/lang_sw.php';

} else {

    $lang = require __DIR__ . '/lang_en.php';

}


/*
|--------------------------------------------------------------------------
| Translation helper
|--------------------------------------------------------------------------
*/

function __t(string $key): string
{
    global $lang;

    return $lang[$key] ?? $key;
}