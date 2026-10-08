<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  logout.php — end the session.
 * ===========================================================================
 *  Accepts GET so the "Sign out" link is a single click. Signing somebody out
 *  grants no access and reveals nothing, so a CSRF token would add friction
 *  without adding safety. (Wrap the link in a POST form if you prefer strict
 *  POST-only logout.)
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';

$wasSignedIn = is_logged_in();

logout_user();

/* Fresh session so the confirmation message can be shown. */
session_start();
session_regenerate_id(true);

flash('info', $wasSignedIn
    ? 'You have been signed out. Your cart is still waiting for you.'
    : 'You were not signed in.');

redirect('index.php');
