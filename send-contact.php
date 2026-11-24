<?php
/**
 * Contact Form Handler for Dental Show 2027
 * Handles Zoho CPaaS / ZeptoMail Email Delivery & Google reCAPTCHA Verification
 */

header('Content-Type: application/json; charset=UTF-8');

// Configuration
$zohoApiKey = 'PHtE6r1YF+DjjzF98EcB5/+6Q5GjZI4rqOkxKlZF4YdCWaIASU1T/doslj+w+U1+BKJKQaPJmt47s+zNtrjRIma4ZGgaWmqyqK3sx/VYSPOZsbq6x00VtFoSc0fdUIDvdd5v0CfWvtbYNA==';
$recaptchaSecret = '6LdkQi0uAAAAAILAnqmbz4tCkgwANt7bz4uA-2_b';
$adminEmails = [
    'dentalshoworgsecy@gmail.com'
];
$fromEmail = 'orgsecy@dentalshow.in';
$replyToEmail = 'dentalshoworgsecy@gmail.com';

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

// Sanitize inputs
$name = isset($_POST['name']) ? trim(strip_tags($_POST['name'])) : '';
$email = isset($_POST['email']) ? trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL)) : '';
$subject = isset($_POST['subject']) && trim($_POST['subject']) !== '' ? trim(strip_tags($_POST['subject'])) : 'Website Enquiry';
$message = isset($_POST['message']) ? trim(strip_tags($_POST['message'])) : '';
$recaptchaResponse = isset($_POST['g-recaptcha-response']) ? trim($_POST['g-recaptcha-response']) : '';

// Validation
if (empty($name) || empty($email) || empty($message)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all required fields (Name, Email, Message).']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
    exit;
}

// Google reCAPTCHA Verification
if (!empty($recaptchaSecret)) {
    if (empty($recaptchaResponse)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Please complete the Google reCAPTCHA check.']);
        exit;
    }

    $verifyUrl = 'https://www.google.com/recaptcha/api/siteverify';
    $postFields = http_build_query([
        'secret' => $recaptchaSecret,
        'response' => $recaptchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);

    $ch = curl_init($verifyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $verifyResult = curl_exec($ch);
    curl_close($ch);

    $captchaSuccess = false;
    if ($verifyResult) {
        $json = json_decode($verifyResult, true);
        if (!empty($json['success'])) {
            // In v3, check score if available (score >= 0.3 considered human)
            if (isset($json['score'])) {
                if ($json['score'] >= 0.3) {
                    $captchaSuccess = true;
                }
            } else {
                $captchaSuccess = true;
            }
        }
    }

    if (!$captchaSuccess) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'reCAPTCHA verification failed. Please try again.']);
        exit;
    }
}

/**
 * Send email via Zoho CPaaS / ZeptoMail API
 */
function sendZohoZeptoMail($apiKey, $fromEmail, $fromName, $toEmail, $toName, $replyTo, $subject, $htmlBody, $textBody)
{
    $endpoints = [
        'https://api.zeptomail.in/v1.1/email',
        'https://cpaas.zoho.in/v1.1/email'
    ];

    $payload = [
        'from' => [
            'address' => $fromEmail,
            'name' => $fromName
        ],
        'to' => [
            [
                'email_address' => [
                    'address' => $toEmail,
                    'name' => $toName
                ]
            ]
        ],
        'subject' => $subject,
        'htmlbody' => $htmlBody,
        'textbody' => $textBody
    ];

    if (!empty($replyTo)) {
        $payload['reply_to'] = [
            [
                'address' => $replyTo,
                'name' => $fromName
            ]
        ];
    }

    $dataString = json_encode($payload);

    foreach ($endpoints as $endpoint) {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataString);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Zoho-enczapikey ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'response' => $result];
        }
    }

    return ['success' => false, 'response' => $result ?? '', 'http_code' => $httpCode ?? 0];
}

// -------------------------------------------------------------
// 1. Email to Admin
// -------------------------------------------------------------
$adminSubject = "New Enquiry from Website: " . $subject;
$adminTextBody = "Dear Team,\n\nWe have got a new enquiry\n\n"
    . "Name: " . $name . "\n"
    . "Email: " . $email . "\n"
    . "Subject: " . $subject . "\n"
    . "Message:\n" . $message . "\n\n"
    . "Submitted on: " . date('d-m-Y H:i:s') . "\n\n"
    . "Thank you.\n\nRegards,\nDental Show Website";

$adminHtmlBody = "<html><body style='font-family:Arial,sans-serif;color:#1e293b;line-height:1.6;'>"
    . "<p>Dear Team,</p>"
    . "<p>We have got a new enquiry</p>"
    . "<table style='border-collapse:collapse;width:100%;max-width:600px;margin:16px 0;font-size:14px;'>"
    . "<tr><td style='padding:8px 12px;border:1px solid #cbd5e1;font-weight:bold;background:#f8fafc;width:130px;'>Name</td><td style='padding:8px 12px;border:1px solid #cbd5e1;'>" . htmlspecialchars($name) . "</td></tr>"
    . "<tr><td style='padding:8px 12px;border:1px solid #cbd5e1;font-weight:bold;background:#f8fafc;'>Email</td><td style='padding:8px 12px;border:1px solid #cbd5e1;'><a href='mailto:" . htmlspecialchars($email) . "'>" . htmlspecialchars($email) . "</a></td></tr>"
    . "<tr><td style='padding:8px 12px;border:1px solid #cbd5e1;font-weight:bold;background:#f8fafc;'>Subject</td><td style='padding:8px 12px;border:1px solid #cbd5e1;'>" . htmlspecialchars($subject) . "</td></tr>"
    . "<tr><td style='padding:8px 12px;border:1px solid #cbd5e1;font-weight:bold;background:#f8fafc;'>Message</td><td style='padding:8px 12px;border:1px solid #cbd5e1;'>" . nl2br(htmlspecialchars($message)) . "</td></tr>"
    . "<tr><td style='padding:8px 12px;border:1px solid #cbd5e1;font-weight:bold;background:#f8fafc;'>Date & Time</td><td style='padding:8px 12px;border:1px solid #cbd5e1;'>" . date('d M Y, h:i A') . "</td></tr>"
    . "</table>"
    . "<p>Thank you.</p>"
    . "<p>Regards,<br><strong>Dental Show Website</strong></p>"
    . "</body></html>";

$adminSendResult = ['success' => false];
foreach ($adminEmails as $adminTo) {
    if (!empty($adminTo)) {
        $res = sendZohoZeptoMail(
            $zohoApiKey,
            $fromEmail,
            'Dental Show Website',
            $adminTo,
            'Dental Show Admin',
            $email, // Reply-To person who inquired
            $adminSubject,
            $adminHtmlBody,
            $adminTextBody
        );
        if (!empty($res['success'])) {
            $adminSendResult['success'] = true;
        }
    }
}

// -------------------------------------------------------------
// 2. Auto-Response to the person filling contact form
// -------------------------------------------------------------
$userSubject = "Thank you for your enquiry - Dental Show 2027";
$userTextBody = "Dear " . $name . "\n\n"
    . "Thank you for your enquiry.\n\n"
    . "One of team member will connect with you shortly.\n\n"
    . "Thank you.\n\n"
    . "Regards,\nTeam Dental Show";

$userHtmlBody = "<html><body style='font-family:Arial,sans-serif;color:#1e293b;line-height:1.6;'>"
    . "<p>Dear " . htmlspecialchars($name) . ",</p>"
    . "<p>Thank you for your enquiry.</p>"
    . "<p>One of team member will connect with you shortly.</p>"
    . "<p>Thank you.</p>"
    . "<p>Regards,<br><strong>Team Dental Show</strong><br><a href='https://dentalshow.in' style='color:#0477BF;'>www.dentalshow.in</a></p>"
    . "</body></html>";

$userSendResult = sendZohoZeptoMail(
    $zohoApiKey,
    $fromEmail,
    'Dental Show',
    $email,
    $name,
    $replyToEmail, // Reply-To dentalshoworgsecy@gmail.com
    $userSubject,
    $userHtmlBody,
    $userTextBody
);

// Return response
if ($adminSendResult['success'] || $userSendResult['success']) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Thank you! Your message has been sent successfully. One of our team members will connect with you shortly.'
    ]);
} else {
    // If API returned failure, log and return informative error
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unable to send message at this time. Please try again or reach out directly at dentalshoworgsecy@gmail.com.'
    ]);
}
