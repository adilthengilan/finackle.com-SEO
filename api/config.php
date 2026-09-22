<?php
/**
 * Finackle Enquiry System Configuration
 * 
 * IMPORTANT FOR HOSTINGER DEPLOYMENT:
 * 1. Set your Resend API key below (e.g. 're_123456789...').
 * 2. Configure the sender email (FROM_EMAIL) with a verified domain in Resend.
 * 3. Configure ADMIN_EMAIL to receive visitor enquiries.
 * 
 * Security: This file cannot be opened directly in a browser.
 */

if (!defined('FINACKLE_APP')) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Access Forbidden: Configuration cannot be accessed directly.');
}

// 1. Check for optional external environment variables or external .env file outside public_html
$envApiKey = getenv('RESEND_API_KEY');
$envFromEmail = getenv('FROM_EMAIL');
$envAdminEmail = getenv('ADMIN_EMAIL');

// Look for an optional .env file outside public_html (one or two levels up)
$possibleEnvPaths = [
    dirname(__DIR__, 2) . '/.env', // e.g. /home/u123456789/.env (outside public_html)
    dirname(__DIR__, 1) . '/.env',
    __DIR__ . '/.env'
];

foreach ($possibleEnvPaths as $envPath) {
    if (file_exists($envPath) && is_readable($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if ($key === 'RESEND_API_KEY' && empty($envApiKey)) $envApiKey = $val;
            if ($key === 'FROM_EMAIL' && empty($envFromEmail)) $envFromEmail = $val;
            if ($key === 'ADMIN_EMAIL' && empty($envAdminEmail)) $envAdminEmail = $val;
        }
        break;
    }
}

return [
    /**
     * Resend API Key
     * Obtain from https://resend.com/api-keys
     * Replace with your actual Resend API key if not set via environment variable.
     */
    'resend_api_key' => $envApiKey ?: 're_YOUR_RESEND_API_KEY_HERE',

    /**
     * Sender Email Address (From)
     * Must be on a domain verified in your Resend account (e.g., website@finackle.com)
     * During initial testing before domain verification, you can use:
     * 'Finackle <onboarding@resend.dev>'
     */
    'from_email' => $envFromEmail ?: 'Finackle <website@finackle.com>',

    /**
     * Recipient Email Address (Where notifications are delivered)
     */
    'admin_email' => $envAdminEmail ?: 'sales@finackle.com',

    /**
     * Optional auto-reply sender address
     */
    'autoreply_from' => $envFromEmail ?: 'Finackle Team <website@finackle.com>',

    /**
     * Company Information for email footers
     */
    'company_name' => 'Finackle Financial Services',
    'company_website' => 'https://finackle.com',
    'support_phone' => '+971 50 231 6681',
    'office_address' => 'Ajman Free Zone C1 Building, Ajman, United Arab Emirates',
];
