<?php
// config/functions.php
function loadEnv($path = __DIR__ . '/../.env')
{
    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Split into key and value
        list($name, $value) = array_map('trim', explode('=', $line, 2));

        // Remove quotes if present
        $value = trim($value, "\"'");

        // Set environment variable
        $_ENV[$name] = $value;
    }
}

loadEnv();
