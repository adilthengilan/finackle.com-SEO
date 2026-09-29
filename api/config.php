<?php
/**
 * Finackle Enquiry System Configuration
 * 
 * HOSTINGER CONFIGURATION INSTRUCTIONS:
 * 1. Open this file in Hostinger File Manager (/public_html/api/config.php).
 * 2. Paste your Resend API Key into 'resend_api_key' below (starts with "re_").
 * 3. Verify that 'admin_email' is where you want to receive enquiry emails.
 * 4. Save the file.
 * 
 * Security: This file has an access guard so it cannot be browsed directly.
 */

if (!defined('FINACKLE_APP')) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Access Forbidden: Configuration cannot be accessed directly.');
}

// Check for optional external environment variables
$envApiKey = getenv('RESEND_API_KEY') ?: ($_ENV['RESEND_API_KEY'] ?? '');
$envFrom   = getenv('FROM_EMAIL')     ?: ($_ENV['FROM_EMAIL']     ?? '');
$envAdmin  = getenv('ADMIN_EMAIL')    ?: ($_ENV['ADMIN_EMAIL']    ?? '');

return [
    /**
     * Resend API Key:
     * Get your API key from https://resend.com/api-keys
     * Replace 're_YOUR_RESEND_API_KEY_HERE' with your real key.
     */
    'resend_api_key' => $envApiKey ?: 're_YOUR_RESEND_API_KEY_HERE',

    /**
     * Sender Email Address (From):
     * Should be a verified domain in Resend (e.g. 'Finackle <website@finackle.com>').
     * If testing before domain verification, you can temporarily use:
     * 'Finackle <onboarding@resend.dev>'
     */
    'from_email' => $envFrom ?: 'Finackle <website@finackle.com>',

    /**
     * Recipient Email Address (To):
     * Where customer enquiries are delivered.
     */
    'admin_email' => $envAdmin ?: 'sales@finackle.com',
];
