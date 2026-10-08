<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  login.php — customer and administrator sign-in.
 * ===========================================================================
 *  Passwords are verified with password_verify() against a bcrypt hash, and
 *  the whole session id is regenerated on success so a stolen cookie from
 *  before the login cannot be reused. A light per-session throttle blunts
 *  repeated guessing.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(is_admin() ? 'admin/dashboard.php' : 'index.php');
}

const MAX_ATTEMPTS   = 5;
const LOCKOUT_SECONDS = 60;

$errors = [];
$email  = '';

if (is_post()) {
    csrf_guard();

    $email    = strtolower(post('email'));
    $password = (string) ($_POST['password'] ?? '');

    $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
    $remaining   = $lockedUntil - time();

    if ($remaining > 0) {
        $errors[] = 'Too many failed attempts. Please try again in ' . $remaining . ' seconds.';
    } else {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter the email address you registered with.';
        }
        if ($password === '') {
            $errors[] = 'Enter your password.';
        }

        if ($errors === []) {
            $account = db_one(
                'SELECT * FROM users WHERE email = :email LIMIT 1',
                [':email' => $email]
            );

            if ($account !== null && password_verify($password, (string) $account['password_hash'])) {
                /* Transparently upgrade the hash if the cost has changed. */
                if (password_needs_rehash((string) $account['password_hash'], PASSWORD_DEFAULT)) {
                    db_exec(
                        'UPDATE users SET password_hash = :hash WHERE id = :id',
                        [':hash' => password_hash($password, PASSWORD_DEFAULT), ':id' => (int) $account['id']]
                    );
                }

                unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
                login_user($account);

                flash('success', 'Welcome back, ' . (string) $account['username'] . '.');
                redirect(intended_url(is_admin() ? 'admin/dashboard.php' : 'index.php'));
            }

            $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
            $_SESSION['login_attempts'] = $attempts;

            if ($attempts >= MAX_ATTEMPTS) {
                $_SESSION['login_locked_until'] = time() + LOCKOUT_SECONDS;
                $_SESSION['login_attempts']     = 0;
                $errors[] = 'Too many failed attempts. Sign-in is locked for ' . LOCKOUT_SECONDS . ' seconds.';
            } else {
                $left     = MAX_ATTEMPTS - $attempts;
                $errors[] = 'Those credentials do not match our records. ' . $left . ' attempt' . ($left === 1 ? '' : 's') . ' remaining.';
            }
        }
    }
}

$page_title = t('auth.sign_in') . ' — ' . SITE_NAME;
$active_nav = '';
$body_class = 'auth-page';

require GWB_ROOT . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-card">
        <p class="eyebrow"><?= e(SITE_TAGLINE) ?></p>
        <h1><?= e(t('auth.sign_in')) ?></h1>
        <p class="auth-card__lead">Access your cart, order history and wishlist.</p>

        <?php foreach ($errors as $error): ?>
            <div class="notice notice--error" role="alert"><span><?= e($error) ?></span></div>
        <?php endforeach; ?>

        <form method="post" action="<?= e(url('login.php')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="email"><?= e(t('auth.email')) ?></label>
                <input class="input" type="email" id="email" name="email" required
                       value="<?= e($email) ?>" autocomplete="username" autofocus placeholder="you@example.com">
            </div>

            <div class="field">
                <label for="password"><?= e(t('auth.password')) ?></label>
                <input class="input" type="password" id="password" name="password" required
                       autocomplete="current-password" placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn--primary btn--block btn--lg"><?= e(t('auth.sign_in')) ?></button>
        </form>

        <div class="demo-box">
            <strong><?= e(t('auth.demo')) ?></strong><br>
            Administrator — <code>admin@gihonawit.test</code> / <code>Admin@123</code><br>
            Customer — <code>selam@gihonawit.test</code> / <code>Customer@123</code>
            <div class="row" style="gap:8px;margin-top:10px">
                <button type="button" class="btn btn--outline btn--sm" data-fill-email="admin@gihonawit.test" data-fill-password="Admin@123">
                    Fill admin
                </button>
                <button type="button" class="btn btn--outline btn--sm" data-fill-email="selam@gihonawit.test" data-fill-password="Customer@123">
                    Fill customer
                </button>
            </div>
        </div>

        <p class="auth-card__alt">
            <?= e(t('auth.no_account')) ?>
            <a href="<?= e(url('register.php')) ?>"><?= e(t('auth.sign_up')) ?></a>
        </p>
    </div>
</div>

<script>
/* One-click fill for the demo accounts shown above. */
document.querySelectorAll('[data-fill-email]').forEach(function (button) {
  button.addEventListener('click', function () {
    var email = document.querySelector('[name="email"]');
    var password = document.querySelector('[name="password"]');

    if (email) { email.value = button.getAttribute('data-fill-email'); }
    if (password) { password.value = button.getAttribute('data-fill-password'); password.focus(); }
  });
});
</script>

<?php require GWB_ROOT . '/includes/footer.php'; ?>
