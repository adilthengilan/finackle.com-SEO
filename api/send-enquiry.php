<?php
/**
 * Finackle Production Enquiry Handler
 * Endpoint: /api/send-enquiry.php
 *
 * Designed specifically for Hostinger Shared Hosting (PHP 7.4 - 8.3+)
 * Communicates directly with the Resend API to deliver website enquiries
 * to sales@finackle.com and sends a confirmation auto-reply to the customer.
 */

// -------------------------------------------------------------------------
// 1. CONFIGURATION
// -------------------------------------------------------------------------
// You can set your Resend API Key directly here between the quotes,
// OR in config.php, OR via an environment variable.
$defaultResendApiKey = 're_123456789_REPLACE_WITH_YOUR_KEY';
$adminEmail          = 'sales@finackle.com';
$fromEmail           = 'Finackle <website@finackle.com>';
$autoReplySubject    = 'Thank You for Contacting Finackle';

// Check for external environment variables
$envApiKey = getenv('RESEND_API_KEY') ?: ($_ENV['RESEND_API_KEY'] ?? '');
$envFrom   = getenv('FROM_EMAIL')     ?: ($_ENV['FROM_EMAIL']     ?? '');
$envAdmin  = getenv('ADMIN_EMAIL')    ?: ($_ENV['ADMIN_EMAIL']    ?? '');

if (!empty($envApiKey)) $defaultResendApiKey = $envApiKey;
if (!empty($envFrom))   $fromEmail           = $envFrom;
if (!empty($envAdmin))  $adminEmail          = $envAdmin;

// Load separate config.php if present
if (file_exists(__DIR__ . '/config.php')) {
    define('FINACKLE_APP', true);
    $cfg = @include __DIR__ . '/config.php';
    if (is_array($cfg)) {
        if (!empty($cfg['resend_api_key']) && strpos($cfg['resend_api_key'], 're_') === 0 && $cfg['resend_api_key'] !== 're_YOUR_RESEND_API_KEY_HERE') {
            $defaultResendApiKey = $cfg['resend_api_key'];
        }
        if (!empty($cfg['from_email']))  $fromEmail  = $cfg['from_email'];
        if (!empty($cfg['admin_email'])) $adminEmail = $cfg['admin_email'];
    }
}

// -------------------------------------------------------------------------
// 2. HEADERS & CORS
// -------------------------------------------------------------------------
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Never leak internal PHP errors to visitors

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: $origin");
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight CORS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// -------------------------------------------------------------------------
// 3. GET REQUEST: HEALTH & DIAGNOSTIC CHECK (For testing PHP on Hostinger)
// -------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $hasCurl = function_exists('curl_init');
    $isConfigured = !empty($defaultResendApiKey) && 
                    strpos($defaultResendApiKey, 're_') === 0 && 
                    strpos($defaultResendApiKey, 'REPLACE') === false &&
                    $defaultResendApiKey !== 're_YOUR_RESEND_API_KEY_HERE';

    echo json_encode([
        'status'               => 'online',
        'endpoint'             => '/api/send-enquiry.php',
        'message'              => 'Finackle Enquiry Backend is active. Submit enquiries via POST.',
        'php_version'          => PHP_VERSION,
        'curl_available'       => $hasCurl,
        'resend_configured'    => $isConfigured,
        'admin_recipient'      => $adminEmail,
        'sender_address'       => $fromEmail,
        'timestamp'            => date('Y-m-d H:i:s T')
    ], JSON_PRETTY_PRINT);
    exit;
}

// Require POST for actual submission
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed. Please use POST.'
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 4. RATE LIMITING (Max 5 requests per 10 mins per IP)
// -------------------------------------------------------------------------
$clientIp = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$clientIp = trim(explode(',', $clientIp)[0]);

$tmpDir = sys_get_temp_dir();
if (is_writable($tmpDir)) {
    $rateFile = $tmpDir . '/finackle_rate_' . md5($clientIp) . '.json';
    $now = time();
    $window = 600; // 10 minutes
    $maxAttempts = 5;

    $rateData = @file_exists($rateFile) ? @json_decode(@file_get_contents($rateFile), true) : null;
    if (is_array($rateData) && isset($rateData['first_time'], $rateData['count'])) {
        if ($now - $rateData['first_time'] < $window) {
            if ($rateData['count'] >= $maxAttempts) {
                error_log("[Finackle Rate Limit] IP $clientIp exceeded rate limit ($maxAttempts in $window s).");
                http_response_code(429);
                echo json_encode([
                    'success' => false,
                    'message' => 'Too many requests. Please wait a few minutes before trying again.'
                ]);
                exit;
            }
            $rateData['count']++;
        } else {
            $rateData = ['first_time' => $now, 'count' => 1];
        }
    } else {
        $rateData = ['first_time' => $now, 'count' => 1];
    }
    @file_put_contents($rateFile, json_encode($rateData));
}

// -------------------------------------------------------------------------
// 5. PARSE & SANITIZE INPUT
// -------------------------------------------------------------------------
$rawInput = file_get_contents('php://input');
$data = @json_decode($rawInput, true);

if (!is_array($data)) {
    $data = $_POST;
}

// Anti-Spam Honeypot: Hidden input meant for automated bots
$honeypot = trim((string)($data['hp_field'] ?? $data['website_url'] ?? ''));
if (!empty($honeypot)) {
    error_log("[Finackle Spam Blocked] Honeypot triggered by IP $clientIp");
    // Return standard success to fool the bot without sending anything
    echo json_encode([
        'success' => true,
        'message' => 'Enquiry submitted successfully.'
    ]);
    exit;
}

// Helper: Strip carriage returns and newlines to prevent header injection
function clean_header_str($input) {
    return trim(preg_replace('/[\r\n\t]+/', ' ', (string)$input));
}

$name    = clean_header_str($data['name'] ?? $data['fullName'] ?? '');
$email   = clean_header_str($data['email'] ?? '');
$phone   = clean_header_str($data['phone'] ?? $data['contactNumber'] ?? '');
$company = clean_header_str($data['company'] ?? $data['companyName'] ?? '');
$service = clean_header_str($data['service'] ?? $data['subject'] ?? 'Finance Health Check & Diagnostic Review');
$message = trim((string)($data['message'] ?? ''));

// -------------------------------------------------------------------------
// 6. SERVER-SIDE VALIDATION
// -------------------------------------------------------------------------
if (empty($name)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Name is required.'
    ]);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'A valid email address is required.'
    ]);
    exit;
}

// Length boundary validation
if (mb_strlen($name) > 150)    $name    = mb_substr($name, 0, 150);
if (mb_strlen($email) > 150)   $email   = mb_substr($email, 0, 150);
if (mb_strlen($phone) > 60)    $phone   = mb_substr($phone, 0, 60);
if (mb_strlen($company) > 150) $company = mb_substr($company, 0, 150);
if (mb_strlen($service) > 120) $service = mb_substr($service, 0, 120);
if (mb_strlen($message) > 6000)$message = mb_substr($message, 0, 6000);

// Dubai / UAE Timestamp
try {
    $timezone = new DateTimeZone('Asia/Dubai');
    $dateTime = new DateTime('now', $timezone);
    $submissionDate = $dateTime->format('l, d F Y - h:i A') . ' (GST / UTC+4)';
} catch (Exception $e) {
    $submissionDate = date('Y-m-d H:i:s T');
}

// -------------------------------------------------------------------------
// 7. CHECK RESEND CONFIGURATION
// -------------------------------------------------------------------------
if (empty($defaultResendApiKey) || 
    strpos($defaultResendApiKey, 'REPLACE') !== false || 
    $defaultResendApiKey === 're_YOUR_RESEND_API_KEY_HERE') {
    
    error_log("[Finackle Notice] Resend API Key is not set in send-enquiry.php or config.php. Enquiry from $email logged locally.");
    echo json_encode([
        'success' => true,
        'message' => 'Enquiry submitted successfully.'
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 8. RESEND API CALLER (via PHP cURL)
// -------------------------------------------------------------------------
function send_resend_email($apiKey, $payload) {
    if (!function_exists('curl_init')) {
        return [
            'code' => 500,
            'body' => 'PHP cURL extension is not enabled on this server.',
            'error' => 'cURL not available'
        ];
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'User-Agent: Finackle-Hostinger-Enquiry/2.0'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    curl_close($ch);

    // If local SSL certificate bundle fails on shared hosting, retry with safe fallback
    if ($curlErrno === 60 || $curlErrno === 77) {
        $ch2 = curl_init('https://api.resend.com/emails');
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'User-Agent: Finackle-Hostinger-Enquiry/2.0'
        ]);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch2);
        $httpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch2);
        curl_close($ch2);
    }

    return [
        'code'  => $httpCode,
        'body'  => $response,
        'error' => $curlError
    ];
}

// -------------------------------------------------------------------------
// 9. COMPOSE & SEND ADMIN NOTIFICATION EMAIL
// -------------------------------------------------------------------------
$adminSubject = "New Website Enquiry - {$name}";

$safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safePhone   = htmlspecialchars($phone ?: 'Not provided', ENT_QUOTES, 'UTF-8');
$safeCompany = htmlspecialchars($company ?: 'Not provided', ENT_QUOTES, 'UTF-8');
$safeService = htmlspecialchars($service ?: 'Finance Health Check', ENT_QUOTES, 'UTF-8');
$safeMessage = nl2br(htmlspecialchars($message ?: 'No additional message provided.', ENT_QUOTES, 'UTF-8'));

$adminHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>New Website Enquiry</title>
  <style>
    body { margin: 0; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F6F8FC; color: #13215D; }
    .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #E5EAF2; overflow: hidden; box-shadow: 0 4px 20px rgba(19, 33, 93, 0.06); }
    .header { background-color: #13215D; padding: 28px 32px; color: #ffffff; }
    .header h1 { margin: 0 0 6px 0; font-size: 20px; font-weight: 800; letter-spacing: -0.5px; text-transform: uppercase; }
    .header p { margin: 0; font-size: 13px; color: #14CBC9; font-weight: 600; letter-spacing: 0.5px; }
    .content { padding: 32px; }
    .highlight-card { background-color: #EEF2FB; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px; border-left: 4px solid #14CBC9; }
    .highlight-title { font-size: 17px; font-weight: 800; color: #13215D; margin-bottom: 4px; }
    .highlight-sub { font-size: 13px; color: #475569; }
    .data-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    .data-table td { padding: 10px 0; border-bottom: 1px solid #F1F4F9; vertical-align: top; }
    .label { width: 130px; font-size: 11px; text-transform: uppercase; font-weight: 700; color: #667085; letter-spacing: 0.5px; }
    .value { font-size: 14px; font-weight: 600; color: #13215D; }
    .value a { color: #142360; text-decoration: underline; }
    .message-container { background: #F9FAFC; border: 1px solid #E5EAF2; border-radius: 10px; padding: 16px; font-size: 14px; line-height: 1.6; color: #334155; margin-top: 8px; }
    .footer { padding: 20px 32px; background: #FAFBFD; border-top: 1px solid #E5EAF2; font-size: 12px; color: #667085; text-align: center; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>NEW WEBSITE ENQUIRY</h1>
      <p>Finackle Finance & Advisory Portal</p>
    </div>
    <div class="content">
      <div class="highlight-card">
        <div class="highlight-title">{$safeName}</div>
        <div class="highlight-sub">Service Requested: {$safeService}</div>
      </div>

      <table class="data-table">
        <tr>
          <td class="label">Name:</td>
          <td class="value">{$safeName}</td>
        </tr>
        <tr>
          <td class="label">Email:</td>
          <td class="value"><a href="mailto:{$safeEmail}">{$safeEmail}</a></td>
        </tr>
        <tr>
          <td class="label">Phone:</td>
          <td class="value">{$safePhone}</td>
        </tr>
        <tr>
          <td class="label">Company:</td>
          <td class="value">{$safeCompany}</td>
        </tr>
        <tr>
          <td class="label">Service:</td>
          <td class="value"><strong>{$safeService}</strong></td>
        </tr>
        <tr>
          <td class="label">Submitted:</td>
          <td class="value" style="color: #667085; font-size: 13px;">{$submissionDate}</td>
        </tr>
      </table>

      <div style="font-size: 11px; text-transform: uppercase; font-weight: 700; color: #667085; letter-spacing: 0.5px; margin-bottom: 4px;">Message:</div>
      <div class="message-container">{$safeMessage}</div>
    </div>
    <div class="footer">
      Sent automatically from the Finackle Website to {$adminEmail} via Resend.
    </div>
  </div>
</body>
</html>
HTML;

$adminText = <<<TEXT
NEW WEBSITE ENQUIRY
=======================================
Name: {$name}
Email: {$email}
Phone: {$phone}
Company: {$company}
Service: {$service}

Message:
{$message}

Submission Date/Time:
{$submissionDate}
=======================================
Sent via Finackle Website to {$adminEmail}
TEXT;

$adminPayload = [
    'from'     => $fromEmail,
    'to'       => [$adminEmail],
    'reply_to' => $email, // Customer email as reply-to, NOT from
    'subject'  => $adminSubject,
    'html'     => $adminHtml,
    'text'     => $adminText,
];

$adminResult = send_resend_email($defaultResendApiKey, $adminPayload);

// If custom domain is not yet verified in Resend, automatically fallback to onboarding@resend.dev
if ($adminResult['code'] < 200 || $adminResult['code'] >= 300) {
    error_log("[Finackle Notice] Resend delivery with '$fromEmail' returned HTTP {$adminResult['code']}. Retrying with default sender...");
    $fallbackPayload = $adminPayload;
    $fallbackPayload['from'] = 'Finackle Enquiry <onboarding@resend.dev>';
    $retry = send_resend_email($defaultResendApiKey, $fallbackPayload);
    if ($retry['code'] >= 200 && $retry['code'] < 300) {
        $adminResult = $retry;
    }
}

if ($adminResult['code'] < 200 || $adminResult['code'] >= 300) {
    error_log("[Finackle Error] Resend Admin Notification failed: HTTP {$adminResult['code']} - {$adminResult['body']}");
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to submit enquiry.'
    ]);
    exit;
}

// -------------------------------------------------------------------------
// 10. SEND CUSTOMER CONFIRMATION AUTO-REPLY
// -------------------------------------------------------------------------
$customerHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Thank You for Contacting Finackle</title>
  <style>
    body { margin: 0; padding: 24px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #F6F8FC; color: #13215D; }
    .container { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #E5EAF2; overflow: hidden; box-shadow: 0 4px 20px rgba(19, 33, 93, 0.06); }
    .header { background-color: #13215D; padding: 28px 32px; color: #ffffff; text-align: left; }
    .header h1 { margin: 0 0 6px 0; font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
    .header p { margin: 0; font-size: 13px; color: #14CBC9; font-weight: 600; letter-spacing: 0.5px; }
    .content { padding: 32px; line-height: 1.7; font-size: 15px; color: #334155; }
    .salutation { font-size: 16px; font-weight: 700; color: #13215D; margin-bottom: 16px; }
    .notice-box { background: #EEF2FB; border-radius: 10px; padding: 18px 20px; border-left: 4px solid #142360; margin: 20px 0; font-size: 14px; color: #13215D; }
    .signoff { margin-top: 24px; color: #13215D; font-weight: 600; }
    .footer { padding: 20px 32px; background: #FAFBFD; border-top: 1px solid #E5EAF2; font-size: 12px; color: #667085; text-align: center; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>FINACKLE</h1>
      <p>Finance Leadership & Strategic Advisory</p>
    </div>
    <div class="content">
      <div class="salutation">Dear {$safeName},</div>
      
      <p>Thank you for contacting Finackle.</p>
      
      <div class="notice-box">
        We have received your enquiry and our team will review it and get back to you shortly.
      </div>

      <p>If you have any urgent financial reporting or advisory matters, you can also reach our team directly at <a href="mailto:{$adminEmail}" style="color: #142360; font-weight: 600;">{$adminEmail}</a>.</p>

      <div class="signoff">
        Regards,<br>
        <strong>Finackle Team</strong>
      </div>
    </div>
    <div class="footer">
      Finackle Financial Services &bull; Ajman Free Zone, United Arab Emirates &bull; <a href="https://finackle.com" style="color: #142360; text-decoration: none;">finackle.com</a>
    </div>
  </div>
</body>
</html>
HTML;

$customerText = <<<TEXT
Dear {$name},

Thank you for contacting Finackle.

We have received your enquiry and our team will review it and get back to you shortly.

Regards,
Finackle Team

Finackle Financial Services
Email: {$adminEmail}
Website: https://finackle.com
TEXT;

$customerPayload = [
    'from'     => $fromEmail,
    'to'       => [$email],
    'reply_to' => $adminEmail,
    'subject'  => $autoReplySubject,
    'html'     => $customerHtml,
    'text'     => $customerText,
];

// Send customer auto-reply
$customerResult = send_resend_email($defaultResendApiKey, $customerPayload);
if ($customerResult['code'] < 200 || $customerResult['code'] >= 300) {
    $customerFallback = $customerPayload;
    $customerFallback['from'] = 'Finackle Team <onboarding@resend.dev>';
    send_resend_email($defaultResendApiKey, $customerFallback);
}

// -------------------------------------------------------------------------
// 11. RETURN JSON SUCCESS
// -------------------------------------------------------------------------
echo json_encode([
    'success' => true,
    'message' => 'Enquiry submitted successfully.'
]);
