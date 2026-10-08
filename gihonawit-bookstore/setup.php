<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  setup.php — one-click installer for the XAMPP environment.
 * ===========================================================================
 *  What it does
 *    1. Connects to MySQL using the credentials in config/db.php
 *    2. Runs database/online_bookstore.sql (quote-aware statement splitter)
 *    3. Re-hashes the three demo passwords with THIS machine's PHP build, so
 *       the accounts below always work regardless of where the dump was made
 *    4. Prints a report
 *
 *  DELETE THIS FILE once the shop is running — a re-install drops the database.
 * ===========================================================================
 */

/* Connection failures become catchable exceptions so this page can report them
   instead of dying with the generic connection error. */
define('GWB_CATCH_DB_ERRORS', true);

require_once __DIR__ . '/includes/bootstrap.php';

/** The demo logins, always (re)created by the installer. */
const DEMO_ACCOUNTS = [
    ['admin', 'admin@gihonawit.test', 'Admin@123',    'admin'],
    ['selam', 'selam@gihonawit.test', 'Customer@123', 'customer'],
    ['dawit', 'dawit@gihonawit.test', 'Customer@123', 'customer'],
];

/**
 * Split a SQL dump into single statements.
 *
 * explode(';') would corrupt a dump containing semicolons inside string
 * literals, so this walks the file and tracks quotes and comments.
 *
 * @return array<int,string>
 */
function split_sql_statements(string $sql): array
{
    $statements = [];
    $buffer     = '';
    $length     = strlen($sql);
    $inString   = false;
    $inLine     = false;
    $inBlock    = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($inLine) {
            if ($char === "\n") {
                $inLine  = false;
                $buffer .= "\n";
            }
            continue;
        }

        if ($inBlock) {
            if ($char === '*' && $next === '/') {
                $inBlock = false;
                $i++;
            }
            continue;
        }

        if ($inString) {
            $buffer .= $char;

            if ($char === '\\' && $next !== '') {
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($char === "'") {
                if ($next === "'") {          // SQL-escaped quote
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                $inString = false;
            }
            continue;
        }

        if ($char === '-' && $next === '-') { $inLine = true;  $i++; continue; }
        if ($char === '#')                  { $inLine = true;        continue; }
        if ($char === '/' && $next === '*') { $inBlock = true; $i++; continue; }

        if ($char === "'") {
            $inString = true;
            $buffer  .= $char;
            continue;
        }

        if ($char === ';') {
            $trimmed = trim($buffer);
            if ($trimmed !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $trimmed = trim($buffer);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}

$sqlPath = __DIR__ . '/database/online_bookstore.sql';
$report  = [];
$errors  = [];

/* -------------------------------------------------------------------------- */
/* Environment probe                                                          */
/* -------------------------------------------------------------------------- */
$server = null;

try {
    $server   = Database::serverConnection();
    $report[] = 'Connected to MySQL at ' . DB_HOST . ':' . DB_PORT . ' as "' . DB_USER . '".';
    $report[] = 'MySQL server version: ' . (string) $server->getAttribute(PDO::ATTR_SERVER_VERSION);
} catch (Throwable $error) {
    $errors[] = 'Cannot reach MySQL: ' . $error->getMessage()
        . ' — start MySQL in the XAMPP Control Panel and check config/db.php.';
}

$dbExists   = Database::isInstalled();
$tableCount = 0;
$counts     = [];

if ($dbExists && $server !== null) {
    try {
        $app        = Database::connection();
        $tableCount = (int) $app->query(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        )->fetchColumn();

        foreach (['users', 'books', 'orders', 'order_items'] as $table) {
            $counts[$table] = (int) $app->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        }
    } catch (Throwable $error) {
        $errors[] = 'The database exists but could not be inspected: ' . $error->getMessage();
    }
}

/* -------------------------------------------------------------------------- */
/* Install                                                                    */
/* -------------------------------------------------------------------------- */
if (is_post()) {
    csrf_guard();

    if (!is_file($sqlPath)) {
        $errors[] = 'Missing SQL dump at database/online_bookstore.sql';
    } elseif ($server === null) {
        $errors[] = 'MySQL is not reachable, so nothing was installed.';
    } else {
        try {
            /* Clean slate keeps repeated installs deterministic. */
            $server->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
            $report[] = 'Dropped the existing "' . DB_NAME . '" database (clean install).';

            $statements = split_sql_statements((string) file_get_contents($sqlPath));
            $executed   = 0;

            /* `USE` runs on this same connection, so everything that follows
               lands inside the application database. */
            foreach ($statements as $statement) {
                $server->exec($statement);
                $executed++;
            }

            $report[] = 'Executed ' . $executed . ' SQL statements from online_bookstore.sql.';

            /* Re-hash the demo passwords locally so the logins always work. */
            $app = Database::connection();
            $app->beginTransaction();

            foreach (DEMO_ACCOUNTS as [$username, $email, $plain, $role]) {
                $hash = password_hash($plain, PASSWORD_DEFAULT);

                $existing = $app->prepare('SELECT id FROM users WHERE email = :email');
                $existing->execute([':email' => $email]);
                $id = $existing->fetchColumn();

                if ($id !== false) {
                    $update = $app->prepare('UPDATE users SET password_hash = :hash, role = :role WHERE id = :id');
                    $update->execute([':hash' => $hash, ':role' => $role, ':id' => (int) $id]);
                } else {
                    $insert = $app->prepare(
                        'INSERT INTO users (username, email, password_hash, role, created_at)
                         VALUES (:username, :email, :hash, :role, NOW())'
                    );
                    $insert->execute([
                        ':username' => $username,
                        ':email'    => $email,
                        ':hash'     => $hash,
                        ':role'     => $role,
                    ]);
                }
            }

            $app->commit();
            $report[] = 'Demo account passwords re-hashed with this PHP build.';

            $dbExists   = true;
            $tableCount = (int) $app->query(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
            )->fetchColumn();

            foreach (['users', 'books', 'orders', 'order_items'] as $table) {
                $counts[$table] = (int) $app->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
            }

            $report[] = sprintf(
                'Installed: %d users, %d books, %d orders, %d order lines.',
                $counts['users'] ?? 0,
                $counts['books'] ?? 0,
                $counts['orders'] ?? 0,
                $counts['order_items'] ?? 0
            );
        } catch (Throwable $error) {
            if (isset($app) && $app instanceof PDO && $app->inTransaction()) {
                $app->rollBack();
            }
            $errors[] = 'Installation failed: ' . $error->getMessage();
        }
    }
}

$ready = $dbExists && ($counts['books'] ?? 0) > 0;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · <?= e(SITE_NAME) ?></title>
<link rel="stylesheet" href="<?= e(url('css/style.css')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<style>
  .installer { max-width: 780px; margin: 40px auto; padding: 32px; background: var(--white);
               border: 1px solid var(--line); border-top: 5px solid var(--forest);
               border-radius: var(--radius-lg); box-shadow: var(--shadow); }
  .installer h1 { margin-bottom: 8px; }
  .installer code { background: var(--cream); border: 1px solid var(--line); border-radius: 4px; padding: 1px 6px; }
</style>
</head>
<body>

<main class="wrap">
    <div class="installer">
        <a class="brand" href="<?= e(url('index.php')) ?>" style="margin-bottom:18px">
            <?= brand_mark() ?>
            <span>
                <span class="brand__name"><?= e(SITE_NAME) ?></span>
                <span class="brand__sub"><?= e(SITE_SUFFIX) ?></span>
                <span class="brand__tag"><?= e(SITE_TAGLINE) ?></span>
            </span>
        </a>

        <h1>Storefront installer</h1>
        <p class="muted">
            Creates the <code><?= e(DB_NAME) ?></code> database on
            <code><?= e(DB_HOST . ':' . DB_PORT) ?></code>, imports the schema with the sample
            catalogue, then prepares the login accounts.
        </p>

        <?php foreach ($errors as $error): ?>
            <div class="notice notice--error" role="alert"><span><?= e($error) ?></span></div>
        <?php endforeach; ?>

        <?php foreach ($report as $line): ?>
            <div class="notice notice--success" role="status"><span><?= e($line) ?></span></div>
        <?php endforeach; ?>

        <div class="spec-list" style="margin:22px 0">
            <div class="spec"><dt>PHP</dt><dd><?= e(PHP_VERSION) ?></dd></div>
            <div class="spec"><dt>Database</dt><dd><?= $dbExists ? 'present' : 'missing' ?></dd></div>
            <div class="spec"><dt>Tables</dt><dd><?= (int) $tableCount ?></dd></div>
            <div class="spec"><dt>Books</dt><dd><?= (int) ($counts['books'] ?? 0) ?></dd></div>
            <div class="spec"><dt>Users</dt><dd><?= (int) ($counts['users'] ?? 0) ?></dd></div>
            <div class="spec"><dt>Orders</dt><dd><?= (int) ($counts['orders'] ?? 0) ?></dd></div>
        </div>

        <?php if ($ready): ?>
            <div class="notice notice--success" role="status">
                <span>The storefront is ready. Sign in with one of the demo accounts below.</span>
            </div>

            <div class="table-wrap" style="margin-bottom:18px">
                <table class="table">
                    <thead><tr><th>Role</th><th>Email</th><th>Password</th></tr></thead>
                    <tbody>
                        <?php foreach (DEMO_ACCOUNTS as [$username, $email, $plain, $role]): ?>
                            <tr>
                                <td><?= e(ucfirst($role)) ?></td>
                                <td><code><?= e($email) ?></code></td>
                                <td><code><?= e($plain) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="row" style="gap:10px">
                <a class="btn btn--primary" href="<?= e(url('index.php')) ?>">Open the storefront</a>
                <a class="btn btn--outline" href="<?= e(url('admin/dashboard.php')) ?>">Admin dashboard</a>
                <a class="btn btn--outline" href="<?= e(url('login.php')) ?>">Sign in</a>
            </div>

            <p class="muted small" style="margin-top:16px">
                Security note: <strong>delete <code>setup.php</code></strong> now — it can drop the database.
            </p>
        <?php endif; ?>

        <form method="post" style="margin-top:26px;padding-top:22px;border-top:1px solid var(--line)">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--primary btn--block btn--lg">
                <?= $ready ? 'Reinstall (drops all data)' : 'Install the database' ?>
            </button>
            <p class="muted small" style="margin-top:10px">
                A re-install runs <code>DROP DATABASE</code> first, so orders placed during testing are erased.
            </p>
        </form>

        <details style="margin-top:22px;padding:16px 18px;background:var(--cream);border:1px solid var(--line);border-radius:10px">
            <summary style="cursor:pointer;font-weight:600">Prefer to import by hand in phpMyAdmin?</summary>
            <ol style="margin:12px 0 0 20px">
                <li>Start <strong>Apache</strong> and <strong>MySQL</strong> in the XAMPP Control Panel.</li>
                <li>Open <a href="http://localhost/phpmyadmin" target="_blank" rel="noopener">phpMyAdmin</a>.</li>
                <li>Choose <strong>Import</strong>, select <code>database/online_bookstore.sql</code>, then <strong>Go</strong>.</li>
                <li>Reload this page to confirm the counts above.</li>
            </ol>
        </details>
    </div>
</main>

</body>
</html>
