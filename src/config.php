
<?php

# declare(strict_types=1);

// Load environment variables from the project root
$envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

if (!is_file($envFile)) {
    throw new RuntimeException(
        '.env file not found at: ' . $envFile
    );
}

if (!is_readable($envFile)) {
    throw new RuntimeException(
        '.env file is not readable'
    );
}

// Load .env values
$lines = file(
    $envFile,
    FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
);

if ($lines === false) {
    throw new RuntimeException(
        'Unable to read .env file'
    );
}

foreach ($lines as $line) {

    $line = trim($line);

    // Skip empty lines and comments
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    if (!str_contains($line, '=')) {
        continue;
    }

    [$key, $value] = explode('=', $line, 2);

    $key = trim($key);
    $value = trim($value);

    // Remove surrounding matching quotes
    if (strlen($value) >= 2) {

        $first = $value[0];
        $last = substr($value, -1);

        if (
            ($first === '"' && $last === '"') ||
            ($first === "'" && $last === "'")
        ) {
            $value = substr($value, 1, -1);
        }
    }

    $_ENV[$key] = $value;
}

// Get environment variable
function env(string $key, $default = null) {

    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    $value = getenv($key);

    return $value !== false ? $value : $default;
}

// Database connection
function db(): PDO {

    static $pdo = null;

    // Reuse existing connection
    if ($pdo !== null) {
        return $pdo;
    }

    // Read database configuration
    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT');
    $name = env('DB_NAME');
    $user = env('DB_USER');
    $pass = env('DB_PASS', '');

    // Validate database name
    if ($name === null || $name === '') {
        throw new RuntimeException(
            'DB_NAME is missing from .env'
        );
    }

    // Validate database username
    if ($user === null || $user === '') {
        throw new RuntimeException(
            'DB_USER is missing from .env'
        );
    }

    // Validate database port
    if (
        !ctype_digit((string) $port) ||
        (int) $port < 1 ||
        (int) $port > 65535
    ) {
        throw new RuntimeException(
            'Invalid DB_PORT in .env'
        );
    }

    // MySQL connection string
    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    try {

        $pdo = new PDO(
            $dsn,
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE =>
                    PDO::ERRMODE_EXCEPTION,

                PDO::ATTR_DEFAULT_FETCH_MODE =>
                    PDO::FETCH_ASSOC,

                PDO::ATTR_EMULATE_PREPARES =>
                    false
            ]
        );

    } catch (PDOException $e) {

        // Log connection failure
        error_log(
            'MySQL connection failed: ' .
            $e->getMessage()
        );

        // Preserve exception for the caller
        throw $e;
    }

    return $pdo;
}

// JSON response helper
function json_response(
    array $data,
    int $code = 200
): void {

    http_response_code($code);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    echo json_encode($data);

    exit;
}
