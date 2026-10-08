<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  Gihonawit Online Bookstore — includes/bootstrap.php
 * ===========================================================================
 *  Single entry point for every page. Start each script with:
 *
 *      require_once __DIR__ . '/includes/bootstrap.php';       // root pages
 *      require_once dirname(__DIR__) . '/includes/bootstrap.php'; // subfolders
 *
 *  It wires up: error reporting, the session, the BASE_URL constant, the PDO
 *  connection, the configuration, the helper library, the translations, the
 *  cart/wishlist and the language switch.
 * ===========================================================================
 */

/* -------------------------------------------------------------------------- */
/* Project paths                                                              */
/* -------------------------------------------------------------------------- */
define('GWB_ROOT', dirname(__DIR__));

/* -------------------------------------------------------------------------- */
/* Error reporting — set display_errors to 0 before putting this online       */
/* -------------------------------------------------------------------------- */
ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set(getenv('GWB_TZ') !== false ? (string) getenv('GWB_TZ') : 'UTC');

/* -------------------------------------------------------------------------- */
/* Hardened session cookie                                                    */
/* -------------------------------------------------------------------------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => (bool) $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('GIHSESSID');
    session_start();
}

/* -------------------------------------------------------------------------- */
/* BASE_URL auto-detection                                                    */
/* -------------------------------------------------------------------------- */
/* Resolves to "/gihonawit-bookstore" under XAMPP, or "" when the project is
   served from the root of a virtual host — with no manual configuration. */
$__docRoot = str_replace('\\', '/', rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
$__appRoot = str_replace('\\', '/', GWB_ROOT);
$__base    = '';

if ($__docRoot !== '' && strpos($__appRoot, $__docRoot) === 0) {
    $__base = substr($__appRoot, strlen($__docRoot));
}
if (PHP_SAPI === 'cli') {
    $__base = '/gihonawit-bookstore';
}

define('BASE_URL', rtrim($__base, '/'));
unset($__docRoot, $__appRoot, $__base);

/* -------------------------------------------------------------------------- */
/* Application layers                                                         */
/* -------------------------------------------------------------------------- */
require_once GWB_ROOT . '/config/db.php';
require_once GWB_ROOT . '/includes/config.php';
require_once GWB_ROOT . '/includes/functions.php';
require_once GWB_ROOT . '/includes/lang.php';
require_once GWB_ROOT . '/includes/cart.php';

/* -------------------------------------------------------------------------- */
/* Language switch — any page can be switched with ?lang=am or ?lang=en       */
/* -------------------------------------------------------------------------- */
if (isset($_GET['lang']) && is_string($_GET['lang'])) {
    $requested = strtolower(trim($_GET['lang']));

    if (isset(languages()[$requested])) {
        $_SESSION['lang'] = $requested;
    }

    /* Redirect back to the same page without the lang parameter so the
       browser's back button and refresh behaviour stay predictable. */
    $query = $_GET;
    unset($query['lang']);

    $target = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    if ($query !== []) {
        $target .= '?' . http_build_query($query);
    }

    redirect($target);
}
