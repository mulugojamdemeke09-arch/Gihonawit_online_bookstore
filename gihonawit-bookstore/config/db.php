<?php
declare(strict_types=1);

/**
 * ===========================================================================
 *  Gihonawit Online Bookstore — config/db.php
 *  Unified PDO connectivity script.
 * ===========================================================================
 *  Every database access in this application goes through this file. The
 *  connection is created once per request and handed out by db().
 *
 *  Security posture
 *    - PDO with ERRMODE_EXCEPTION          — failures are loud, never silent
 *    - ATTR_EMULATE_PREPARES = false       — TRUE server-side prepared
 *                                            statements, so a parameter can
 *                                            never be re-interpreted as SQL
 *    - every query helper below binds its values as parameters; no page in
 *      this project concatenates user input into SQL
 *
 *  XAMPP defaults (Settings -> change here if your MySQL differs):
 *      Host 127.0.0.1 · Port 3306 · User root · Password ""
 * ===========================================================================
 */

define('DB_HOST', getenv('GWB_DB_HOST') !== false ? (string) getenv('GWB_DB_HOST') : '127.0.0.1');
define('DB_PORT', (int) (getenv('GWB_DB_PORT') !== false ? getenv('GWB_DB_PORT') : 3306));
define('DB_NAME', getenv('GWB_DB_NAME') !== false ? (string) getenv('GWB_DB_NAME') : 'online_bookstore');
define('DB_USER', getenv('GWB_DB_USER') !== false ? (string) getenv('GWB_DB_USER') : 'root');
define('DB_PASS', getenv('GWB_DB_PASS') !== false ? (string) getenv('GWB_DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Connection holder.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private function __construct()
    {
    }

    /** Connection to the application database. */
    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        self::$pdo = self::make(self::dsn(DB_NAME));

        return self::$pdo;
    }

    /**
     * Connection to the MySQL server with no database selected.
     * Used by setup.php, which must create the database before it can be
     * connected to.
     */
    public static function serverConnection(): PDO
    {
        return self::make(self::dsn(null));
    }

    /** Does the application database already exist? */
    public static function isInstalled(): bool
    {
        try {
            $statement = self::serverConnection()->prepare(
                'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :name'
            );
            $statement->execute([':name' => DB_NAME]);

            return (bool) $statement->fetchColumn();
        } catch (Throwable $error) {
            return false;
        }
    }

    private static function dsn(?string $database): string
    {
        if ($database !== null && $database !== '') {
            return sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST,
                DB_PORT,
                $database,
                DB_CHARSET
            );
        }

        return sprintf('mysql:host=%s;port=%d;charset=%s', DB_HOST, DB_PORT, DB_CHARSET);
    }

    private static function make(string $dsn): PDO
    {
        try {
            return new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (PDOException $error) {
            self::fail($error);
        }
    }

    /**
     * Render an actionable message instead of a raw stack trace (which would
     * print the MySQL password on a shared machine).
     */
    private static function fail(PDOException $error): void
    {
        if (defined('GWB_CATCH_DB_ERRORS') && GWB_CATCH_DB_ERRORS === true) {
            throw new RuntimeException('Cannot connect to MySQL: ' . $error->getMessage(), 0, $error);
        }

        $isApi = strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), '/api/') !== false;
        http_response_code(500);

        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'      => false,
                'message' => 'The catalogue database is unavailable. Is MySQL running in XAMPP?',
            ]);
            exit;
        }

        $detail = htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
        $user   = DB_USER;
        $name   = DB_NAME;

        echo <<<HTML
<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Database connection failed — Gihonawit</title>
<style>
  body{margin:0;min-height:100vh;display:grid;place-items:center;background:#fcfbf7;
       color:#14181f;font:16px/1.65 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
  .box{max-width:660px;margin:24px;padding:34px;background:#fff;border:1px solid #e7e2d8;
       border-top:6px solid #0f3d2a;border-radius:14px;box-shadow:0 18px 50px rgba(15,61,42,.12)}
  h1{margin:0 0 10px;font:700 22px/1.25 Georgia,serif;color:#0f3d2a}
  code{background:#f6f3ec;border:1px solid #e7e2d8;border-radius:5px;padding:2px 6px;font-size:13px}
  pre{background:#f6f3ec;border:1px solid #e7e2d8;border-radius:8px;padding:12px;overflow:auto;font-size:13px}
  ol{padding-left:20px} li{margin:6px 0}
  a{color:#0f3d2a;font-weight:600}
</style></head>
<body><div class="box">
  <h1>Database connection failed</h1>
  <p>Could not connect to MySQL as <code>{$user}</code> on database <code>{$name}</code>.</p>
  <ol>
    <li>Open the <strong>XAMPP Control Panel</strong> and start <strong>MySQL</strong>.</li>
    <li>Import <code>database/online_bookstore.sql</code> in phpMyAdmin, or run the
        installer at <a href="setup.php">setup.php</a>.</li>
    <li>If your MySQL password is not empty, edit <code>config/db.php</code>.</li>
  </ol>
  <pre>{$detail}</pre>
</div></body></html>
HTML;
        exit;
    }
}

/* -------------------------------------------------------------------------- */
/* Query helpers — the only sanctioned way to talk to the database            */
/* -------------------------------------------------------------------------- */

/** Shared PDO handle. */
function db(): PDO
{
    return Database::connection();
}

/**
 * Run a prepared statement and return every row.
 *
 * @param  array<string|int,mixed> $params
 * @return array<int,array<string,mixed>>
 */
function db_all(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

/**
 * Run a prepared statement and return the first row, or null.
 *
 * @param  array<string|int,mixed> $params
 * @return array<string,mixed>|null
 */
function db_one(string $sql, array $params = []): ?array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();

    return $row === false ? null : $row;
}

/**
 * Run a prepared statement and return the first column of the first row.
 *
 * @param  array<string|int,mixed> $params
 * @return mixed
 */
function db_value(string $sql, array $params = [])
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchColumn();
}

/**
 * Run a write statement and return the number of affected rows.
 *
 * @param array<string|int,mixed> $params
 */
function db_exec(string $sql, array $params = []): int
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->rowCount();
}

/** Id generated by the last INSERT. */
function db_last_id(): int
{
    return (int) db()->lastInsertId();
}
