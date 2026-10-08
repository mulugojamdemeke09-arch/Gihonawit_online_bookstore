<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  register.php — customer sign-up.
 * ===========================================================================
 *  New accounts are always created with role = 'customer'; the role is never
 *  taken from the request, so nobody can promote themselves to administrator.
 * ===========================================================================
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$values = ['username' => '', 'email' => ''];

if (is_post()) {
    csrf_guard();

    $values['username'] = post('username');
    $values['email']    = strtolower(post('email'));
    $password           = (string) ($_POST['password'] ?? '');
    $confirm            = (string) ($_POST['password_confirm'] ?? '');

    if (!preg_match('/^[A-Za-z0-9_.\-]{3,30}$/', $values['username'])) {
        $errors['username'] = 'Usernames are 3–30 characters: letters, numbers, dot, dash or underscore.';
    } elseif ((int) db_value('SELECT COUNT(*) FROM users WHERE username = :u', [':u' => $values['username']]) > 0) {
        $errors['username'] = 'That username is already taken.';
    }

    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    } elseif ((int) db_value('SELECT COUNT(*) FROM users WHERE email = :e', [':e' => $values['email']]) > 0) {
        $errors['email'] = 'That email address is already registered — try signing in instead.';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Passwords must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Include at least one letter and one number for a stronger password.';
    }

    if ($password !== $confirm) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    if (empty($_POST['terms'])) {
        $errors['terms'] = 'Please accept the terms to create an account.';
    }

    if ($errors === []) {
        db_exec(
            'INSERT INTO users (username, email, password_hash, role, created_at)
             VALUES (:username, :email, :hash, :role, NOW())',
            [
                ':username' => $values['username'],
                ':email'    => $values['email'],
                /* bcrypt via PASSWORD_DEFAULT — the hash is never reversible. */
                ':hash'     => password_hash($password, PASSWORD_DEFAULT),
                ':role'     => 'customer',
            ]
        );

        $account = db_one('SELECT * FROM users WHERE id = :id LIMIT 1', [':id' => db_last_id()]);

        if ($account !== null) {
            login_user($account);
            flash('success', 'Welcome to ' . SITE_NAME . ', ' . $values['username'] . '. Your account is ready.');
            redirect('index.php');
        }

        $errors['general'] = 'The account could not be created. Please try again.';
    }
}

$page_title = t('auth.sign_up') . ' — ' . SITE_NAME;
$active_nav = '';
$body_class = 'auth-page';

require GWB_ROOT . '/includes/header.php';
?>

<div class="auth-wrap">
    <div class="auth-card">
        <p class="eyebrow"><?= e(SITE_TAGLINE) ?></p>
        <h1><?= e(t('auth.sign_up')) ?></h1>
        <p class="auth-card__lead">Save your cart, track orders and keep a wishlist.</p>

        <?php if (isset($errors['general'])): ?>
            <div class="notice notice--error" role="alert"><span><?= e($errors['general']) ?></span></div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('register.php')) ?>" novalidate>
            <?= csrf_field() ?>

            <div class="field">
                <label for="username"><?= e(t('auth.username')) ?></label>
                <input class="input" type="text" id="username" name="username" required minlength="3" maxlength="30"
                       value="<?= e($values['username']) ?>" autocomplete="nickname"
                       aria-invalid="<?= isset($errors['username']) ? 'true' : 'false' ?>">
                <?php if (isset($errors['username'])): ?><span class="field__error"><?= e($errors['username']) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label for="email"><?= e(t('auth.email')) ?></label>
                <input class="input" type="email" id="email" name="email" required maxlength="190"
                       value="<?= e($values['email']) ?>" autocomplete="email"
                       aria-invalid="<?= isset($errors['email']) ? 'true' : 'false' ?>">
                <?php if (isset($errors['email'])): ?><span class="field__error"><?= e($errors['email']) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label for="password"><?= e(t('auth.password')) ?></label>
                <input class="input" type="password" id="password" name="password" required minlength="8"
                       autocomplete="new-password" aria-invalid="<?= isset($errors['password']) ? 'true' : 'false' ?>">
                <span class="field__hint">At least 8 characters, including a letter and a number.</span>
                <?php if (isset($errors['password'])): ?><span class="field__error"><?= e($errors['password']) ?></span><?php endif; ?>
            </div>

            <div class="field">
                <label for="password_confirm"><?= e(t('auth.confirm')) ?></label>
                <input class="input" type="password" id="password_confirm" name="password_confirm" required
                       autocomplete="new-password" aria-invalid="<?= isset($errors['password_confirm']) ? 'true' : 'false' ?>">
                <?php if (isset($errors['password_confirm'])): ?><span class="field__error"><?= e($errors['password_confirm']) ?></span><?php endif; ?>
            </div>

            <label class="checkbox field">
                <input type="checkbox" name="terms" value="1" <?= !empty($_POST['terms']) ? 'checked' : '' ?>>
                <span>I agree to the shop's terms of service and 30-day returns policy.</span>
            </label>
            <?php if (isset($errors['terms'])): ?><span class="field__error"><?= e($errors['terms']) ?></span><?php endif; ?>

            <button type="submit" class="btn btn--primary btn--block btn--lg"><?= e(t('auth.sign_up')) ?></button>
        </form>

        <p class="auth-card__alt">
            <?= e(t('auth.have_account')) ?>
            <a href="<?= e(url('login.php')) ?>"><?= e(t('auth.sign_in')) ?></a>
        </p>
    </div>
</div>

<?php require GWB_ROOT . '/includes/footer.php'; ?>
