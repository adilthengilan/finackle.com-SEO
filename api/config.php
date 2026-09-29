<?php
/**
 * Finackle Enquiry System Configuration
 *
 * All configuration parameters are strictly loaded from environment variables:
 * - RESEND_API_KEY: Resend API Key (starts with "re_")
 * - FROM_EMAIL: Verified sender address (e.g. 'Finackle <website@finackle.com>')
 * - ADMIN_EMAIL: Recipient address (default: 'info@finackle.com')
 *
 * Security: This file has an access guard so it cannot be browsed directly.
 */

if (!defined('FINACKLE_APP')) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Access Forbidden: Configuration cannot be accessed directly.');
}

// Helper to safely load .env file into environment if present
if (!function_exists('load_finackle_env_file')) {
    function load_finackle_env_file($filePath) {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;

            list($name, $value) = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim($value);

            // Strip surrounding single/double quotes if present
            if ((strpos($value, '"') === 0 && substr($value, -1) === '"') ||
                (strpos($value, "'") === 0 && substr($value, -1) === "'")) {
                $value = substr($value, 1, -1);
            }

            if (!array_key_exists($name, $_ENV)) {
                $_ENV[$name] = $value;
            }
            if (!array_key_exists($name, $_SERVER)) {
                $_SERVER[$name] = $value;
            }
            if (getenv($name) === false) {
                putenv("$name=$value");
            }
        }
    }
}

// Search for .env file in standard locations (current dir, parent dirs, root)
load_finackle_env_file(__DIR__ . '/.env');
load_finackle_env_file(dirname(__DIR__) . '/.env');
load_finackle_env_file(dirname(dirname(__DIR__)) . '/.env');

// Exclusively load from environment variables (no hardcoded fallbacks)
$resendApiKey = getenv('RESEND_API_KEY') ?: ($_ENV['RESEND_API_KEY'] ?? ($_SERVER['RESEND_API_KEY'] ?? ''));
$fromEmail    = getenv('FROM_EMAIL')     ?: ($_ENV['FROM_EMAIL']     ?? ($_SERVER['FROM_EMAIL']     ?? 'Finackle <website@finackle.com>'));
$adminEmail   = getenv('ADMIN_EMAIL')    ?: ($_ENV['ADMIN_EMAIL']    ?? ($_SERVER['ADMIN_EMAIL']    ?? 'info@finackle.com'));

return [
    /**
     * Resend API Key:
     * Exclusively sourced from RESEND_API_KEY environment variable.
     */   
     'resend_api_key' => $resendApiKey,

    /**
     * Sender Email Address (From):
     * Sourced from FROM_EMAIL environment variable.
     */
    'from_email' => $fromEmail,

    /**
     * Recipient Email Address (To):
     * Sourced from ADMIN_EMAIL environment variable.
     */
    'admin_email' => $adminEmail,
];
