<?php
/**
 * Dental Show 2027 - Space Booking / Exhibitor Application Handler
 * Saves exhibitor application to Hostinger MySQL database & dispatches notification emails via Zoho CPaaS
 */

// Enable error reporting for debugging if needed, but return clean JSON / HTML
error_reporting(E_ALL);
ini_set('display_errors', '0');

$isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    } else {
        header('Location: space-booking.html');
    }
    exit;
}

// -------------------------------------------------------------
// Database Configuration (Hostinger MySQL)
// -------------------------------------------------------------
$dbConfig = [
    'database' => 'u672271818_dentalshow',
    'username' => 'u672271818_dentalshowuser',
    'password' => '49~rg*:94W&j',
    'port' => 3306,
    // Tries localhost first (standard on Hostinger shared/cloud server), then remote host
    'hosts' => ['localhost', 'srv2204.hstgr.io', '127.0.0.1']
];

$pdo = null;
$dbError = '';

foreach ($dbConfig['hosts'] as $host) {
    try {
        $dsn = "mysql:host={$host};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5
        ]);
        break; // Successfully connected
    } catch (PDOException $e) {
        $dbError = $e->getMessage();
    }
}

if (!$pdo) {
    // If database connection fails, log error and respond
    error_log("Database connection failed: " . $dbError);
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed. Please contact the organizers directly at dentalshoworgsecy@gmail.com.']);
        exit;
    } else {
        die("<h3>Database connection error. Please contact the administrator at dentalshoworgsecy@gmail.com.</h3>");
    }
}

// Auto-create table if not exists (Only company_name, fascia_name, and email are strictly required)
try {
    $createTableSql = "
    CREATE TABLE IF NOT EXISTS `exhibitor_bookings` (
      `id` INT(11) NOT NULL AUTO_INCREMENT,
      `booking_ref` VARCHAR(30) NOT NULL UNIQUE,
      `membership_status` VARCHAR(50) DEFAULT 'TNDTMA Member',
      `company_name` VARCHAR(255) NOT NULL,
      `address` TEXT DEFAULT NULL,
      `city_state` VARCHAR(150) DEFAULT NULL,
      `pincode` VARCHAR(20) DEFAULT NULL,
      `country` VARCHAR(100) DEFAULT 'India',
      `gst_no` VARCHAR(50) DEFAULT NULL,
      `pan_no` VARCHAR(50) DEFAULT NULL,
      `phone` VARCHAR(50) DEFAULT NULL,
      `email` VARCHAR(150) NOT NULL,
      `website` VARCHAR(255) DEFAULT NULL,
      `contact_person` VARCHAR(150) DEFAULT NULL,
      `mobile` VARCHAR(50) DEFAULT NULL,
      `designation` VARCHAR(100) DEFAULT NULL,
      `alt_contact_no` VARCHAR(50) DEFAULT NULL,
      `fascia_name` VARCHAR(255) NOT NULL,
      `stall_type` VARCHAR(100) DEFAULT NULL,
      `stalls_required` INT(11) DEFAULT 0,
      `islands_required` INT(11) DEFAULT 0,
      `preferred_zone` VARCHAR(150) DEFAULT NULL,
      `space_sqmtr` VARCHAR(50) DEFAULT NULL,
      `signatory_name` VARCHAR(150) DEFAULT NULL,
      `signatory_designation` VARCHAR(100) DEFAULT NULL,
      `agreed_terms` TINYINT(1) DEFAULT 1,
      `ip_address` VARCHAR(45) DEFAULT NULL,
      `user_agent` TEXT DEFAULT NULL,
      `status` ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'pending',
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_booking_ref` (`booking_ref`),
      KEY `idx_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $pdo->exec($createTableSql);
} catch (PDOException $e) {
    error_log("Table creation check failed: " . $e->getMessage());
}

// -------------------------------------------------------------
// Sanitize and Validate Inputs
// -------------------------------------------------------------
function cleanInput($data)
{
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES, 'UTF-8');
}

$membershipStatus = cleanInput($_POST['membership_status'] ?? 'TNDTMA Member');
$companyName = cleanInput($_POST['company_name'] ?? '');
$address = cleanInput($_POST['address'] ?? '');
$cityState = cleanInput($_POST['city_state'] ?? '');
$pincode = cleanInput($_POST['pincode'] ?? '');
$country = cleanInput($_POST['country'] ?? 'India');
$gstNo = cleanInput($_POST['gst_no'] ?? '');
$panNo = cleanInput($_POST['pan_no'] ?? '');
$phone = cleanInput($_POST['phone'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$website = cleanInput($_POST['website'] ?? '');
$contactPerson = cleanInput($_POST['contact_person'] ?? '');
$mobile = cleanInput($_POST['mobile'] ?? '');
$designation = cleanInput($_POST['designation'] ?? '');
$altContactNo = cleanInput($_POST['alt_contact_no'] ?? '');
$fasciaName = cleanInput($_POST['fascia_name'] ?? '');

$stallType = cleanInput($_POST['stall_type'] ?? 'Regular Stall');
$stallsRequired = intval($_POST['stalls_required'] ?? 0);
$islandsRequired = intval($_POST['islands_required'] ?? 0);
$preferredZone = cleanInput($_POST['preferred_zone'] ?? '');
$spaceSqmtr = cleanInput($_POST['space_sqmtr'] ?? '');

$signatoryName = cleanInput($_POST['signatory_name'] ?? '');
$signatoryDesignation = cleanInput($_POST['signatory_designation'] ?? '');
$agreeDeclaration = isset($_POST['agree_declaration']) ? 1 : 0;

// Validation rules: ONLY Company Name, Fascia Display Name, and Email are mandatory
$errors = [];
if (empty($companyName)) {
    $errors[] = "Company / Organisation name is required.";
}
if (empty($fasciaName)) {
    $errors[] = "Fascia / Display Name is required.";
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "A valid Email address is required.";
}

if (!empty($errors)) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => implode(' ', $errors), 'errors' => $errors]);
    } else {
        echo "<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>Validation Error – Dental Show 2027</title>
  <link rel='stylesheet' href='css/style.css'>
</head>
<body style='background: #f8fafc; font-family: Inter, sans-serif; margin: 0; padding: 40px 16px;'>
  <div style='max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 16px; padding: 36px 30px; box-shadow: 0 10px 40px rgba(2, 62, 115, 0.08); text-align: center;'>
    <div style='width: 68px; height: 68px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px;'>
      <svg width='36' height='36' viewBox='0 0 24 24' fill='none' stroke='#dc2626' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'><circle cx='12' cy='12' r='10'></circle><line x1='12' y1='8' x2='12' y2='12'></line><line x1='12' y1='16' x2='12.01' y2='16'></line></svg>
    </div>
    <h1 style='color: #023E73; font-size: 24px; margin: 0 0 10px; font-weight: 800;'>Please Complete Mandatory Fields</h1>
    <p style='color: #64748b; font-size: 14.5px; margin: 0 0 20px;'>The following mandatory information is missing or incomplete:</p>
    
    <div style='background: #fef2f2; border: 1px solid #fecaca; border-radius: 10px; padding: 16px 20px; text-align: left; margin-bottom: 24px;'>
      <ul style='margin: 0; padding-left: 20px; color: #b91c1c; font-size: 14px; line-height: 1.7;'>";
        foreach ($errors as $err) {
            echo "<li><strong>{$err}</strong></li>";
        }
        echo "</ul>
    </div>
    
    <a href='javascript:history.back()' style='display: inline-block; padding: 12px 28px; background: #00A86B; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14.5px; box-shadow: 0 4px 14px rgba(0, 168, 107, 0.3);'>← Go Back &amp; Complete Form</a>
  </div>
</body>
</html>";
    }
    exit;
}

// Generate unique Booking Reference: e.g. DS27-EXH-84729
$bookingRef = 'DS27-EXH-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));

// Insert into Database
try {
    $stmt = $pdo->prepare("
        INSERT INTO `exhibitor_bookings` (
            `booking_ref`, `membership_status`, `company_name`, `address`, `city_state`,
            `pincode`, `country`, `gst_no`, `pan_no`, `phone`, `email`, `website`,
            `contact_person`, `mobile`, `designation`, `alt_contact_no`, `fascia_name`,
            `stall_type`, `stalls_required`, `islands_required`, `preferred_zone`, `space_sqmtr`,
            `signatory_name`, `signatory_designation`, `agreed_terms`, `ip_address`, `user_agent`
        ) VALUES (
            :booking_ref, :membership_status, :company_name, :address, :city_state,
            :pincode, :country, :gst_no, :pan_no, :phone, :email, :website,
            :contact_person, :mobile, :designation, :alt_contact_no, :fascia_name,
            :stall_type, :stalls_required, :islands_required, :preferred_zone, :space_sqmtr,
            :signatory_name, :signatory_designation, :agreed_terms, :ip_address, :user_agent
        )
    ");

    $stmt->execute([
        ':booking_ref' => $bookingRef,
        ':membership_status' => $membershipStatus,
        ':company_name' => $companyName,
        ':address' => $address,
        ':city_state' => $cityState,
        ':pincode' => $pincode,
        ':country' => $country,
        ':gst_no' => $gstNo,
        ':pan_no' => $panNo,
        ':phone' => $phone,
        ':email' => $email,
        ':website' => $website,
        ':contact_person' => $contactPerson,
        ':mobile' => $mobile,
        ':designation' => $designation,
        ':alt_contact_no' => $altContactNo,
        ':fascia_name' => $fasciaName,
        ':stall_type' => $stallType,
        ':stalls_required' => $stallsRequired,
        ':islands_required' => $islandsRequired,
        ':preferred_zone' => $preferredZone,
        ':space_sqmtr' => $spaceSqmtr,
        ':signatory_name' => $signatoryName,
        ':signatory_designation' => $signatoryDesignation,
        ':agreed_terms' => $agreeDeclaration,
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);

} catch (PDOException $e) {
    error_log("Database insert error: " . $e->getMessage());
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to save booking. Please try again.']);
    } else {
        die("<h3>System error saving your application. Please contact dentalshoworgsecy@gmail.com.</h3>");
    }
    exit;
}

// -------------------------------------------------------------
// Dispatch Email via Zoho CPaaS / ZeptoMail
// -------------------------------------------------------------
$zohoApiKey = 'PHtE6r1YF+DjjzF98EcB5/+6Q5GjZI4rqOkxKlZF4YdCWaIASU1T/doslj+w+U1+BKJKQaPJmt47s+zNtrjRIma4ZGgaWmqyqK3sx/VYSPOZsbq6x00VtFoSc0fdUIDvdd5v0CfWvtbYNA==';
$fromEmail = 'orgsecy@dentalshow.in';
$adminEmails = [
    'mailinaiworks@gmail.com',
    'cosuniqtechnologies@gmail.com',
    'dentalshoworgsecy@gmail.com'
];

function sendZohoMail($apiKey, $fromEmail, $fromName, $toEmail, $toName, $replyTo, $subject, $htmlBody, $textBody)
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
        'reply_to' => [
            [
                'address' => $replyTo,
                'name' => 'Team Dental Show'
            ]
        ],
        'subject' => $subject,
        'htmlbody' => $htmlBody,
        'textbody' => $textBody
    ];

    $jsonData = json_encode($payload);

    foreach ($endpoints as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Zoho-enczapikey ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }
    }
    return false;
}

// 1. Email to Applicant
$contactDisplay = !empty($contactPerson) ? "{$contactPerson} ({$companyName})" : $companyName;
$recipientName = !empty($contactPerson) ? $contactPerson : $companyName;

$applicantHtml = "
<div style='font-family: Arial, sans-serif; max-width: 650px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 8px; color: #1e293b; background: #ffffff;'>
  <div style='text-align: center; border-bottom: 2px solid #00A86B; padding-bottom: 16px; margin-bottom: 20px;'>
    <h2 style='color: #023E73; margin: 0 0 6px;'>Dental Show 2027</h2>
    <p style='margin: 0; color: #64748b; font-size: 14px;'>Space Booking / Exhibitor Application Received</p>
  </div>
  
  <p>Dear <strong>{$contactDisplay}</strong>,</p>
  <p>Thank you for submitting your Space Booking application for <strong>Dental Show 2027</strong> at Chennai Trade Centre (6–7 February 2027).</p>
  
  <div style='background: #f8fafc; border-left: 4px solid #00A86B; padding: 14px 18px; margin: 20px 0;'>
    <p style='margin: 0 0 8px; font-weight: bold; color: #023E73;'>Application Reference: {$bookingRef}</p>
    <p style='margin: 0 0 4px; font-size: 14px;'><strong>Company:</strong> {$companyName}</p>
    <p style='margin: 0 0 4px; font-size: 14px;'><strong>Membership:</strong> {$membershipStatus}</p>
    <p style='margin: 0 0 4px; font-size: 14px;'><strong>Stall Type:</strong> {$stallType}</p>
    <p style='margin: 0 0 4px; font-size: 14px;'><strong>Stall(s) Required:</strong> {$stallsRequired} | <strong>Island(s):</strong> {$islandsRequired}</p>
    <p style='margin: 0; font-size: 14px;'><strong>Fascia Name:</strong> {$fasciaName}</p>
  </div>
  
  <p>Our exhibition committee will review your space requirements and connect with you shortly regarding stall allotment, advance payment (50%), and invoice schedule.</p>
  
  <h4 style='color: #023E73; margin-top: 24px; margin-bottom: 8px;'>Official Bank Details for Remittance:</h4>
  <p style='font-size: 13.5px; line-height: 1.6; margin: 0; background: #f1f5f9; padding: 12px; border-radius: 6px;'>
    <strong>Account Name:</strong> Tamilnadu dental traders and manufacturers association<br>
    <strong>Bank:</strong> Bank of Baroda<br>
    <strong>A/C No:</strong> 18850200000612<br>
    <strong>IFSC:</strong> BARB0KKNAGA
  </p>
  
  <p style='margin-top: 24px;'>Thank you.<br><br>Regards,<br><strong>Team Dental Show 2027</strong><br>Tamil Nadu Dental Traders & Manufacturers Association (TNDTMA)<br>Mobile: +91 87780 02030 | Email: dentalshoworgsecy@gmail.com</p>
</div>";

$applicantText = "Dear {$recipientName},\n\nThank you for submitting your Space Booking application for Dental Show 2027 (Ref: {$bookingRef}).\nCompany: {$companyName}\nMembership: {$membershipStatus}\nStall Type: {$stallType}\nStall(s): {$stallsRequired}\n\nOur team will connect with you shortly.\n\nRegards,\nTeam Dental Show";

sendZohoMail($zohoApiKey, $fromEmail, 'Dental Show 2027', $email, $recipientName, $adminEmail, "Space Booking Application Received - {$bookingRef} | Dental Show 2027", $applicantHtml, $applicantText);

// 2. Email to Website Admin
$adminHtml = "
<div style='font-family: Arial, sans-serif; max-width: 650px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 8px; color: #1e293b;'>
  <h2 style='color: #023E73; border-bottom: 2px solid #00A86B; padding-bottom: 10px;'>New Exhibitor Booking Application</h2>
  <p><strong>Booking Ref:</strong> {$bookingRef}</p>
  <p><strong>Membership Status:</strong> {$membershipStatus}</p>
  <p><strong>Company / Organisation:</strong> {$companyName}</p>
  <p><strong>Contact Person:</strong> {$contactPerson} ({$designation})</p>
  <p><strong>Email:</strong> {$email}</p>
  <p><strong>Mobile:</strong> {$mobile} | <strong>Alt Contact:</strong> {$altContactNo}</p>
  <p><strong>Address:</strong> {$address}, {$cityState} - {$pincode}, {$country}</p>
  <p><strong>GST No:</strong> {$gstNo} | <strong>PAN No:</strong> {$panNo}</p>
  <p><strong>Website:</strong> {$website}</p>
  <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 16px 0;'>
  <p><strong>Stall Type:</strong> {$stallType}</p>
  <p><strong>Stalls Required:</strong> {$stallsRequired} | <strong>Islands:</strong> {$islandsRequired}</p>
  <p><strong>Preferred Zone:</strong> {$preferredZone}</p>
  <p><strong>Approx Space:</strong> {$spaceSqmtr} sq. mtr.</p>
  <p><strong>Fascia / Display Name:</strong> {$fasciaName}</p>
  <p><strong>Authorised Signatory:</strong> {$signatoryName} ({$signatoryDesignation})</p>
  <p style='color: #64748b; font-size: 13px; margin-top: 20px;'>Submitted on " . date('d M Y, h:i A') . " from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'Unknown') . "</p>
</div>";

$adminText = "New Exhibitor Booking Application\nRef: {$bookingRef}\nCompany: {$companyName}\nContact: {$contactPerson} ({$mobile})\nEmail: {$email}\nStall Type: {$stallType}\nStalls: {$stallsRequired}";

foreach ($adminEmails as $adminTo) {
    if (!empty($adminTo)) {
        sendZohoMail($zohoApiKey, $fromEmail, 'Dental Show Portal', $adminTo, 'Dental Show Admin', $email, "New Exhibitor Booking: {$companyName} ({$bookingRef})", $adminHtml, $adminText);
    }
}

// -------------------------------------------------------------
// Response Output
// -------------------------------------------------------------
if ($isAjax) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status' => 'success',
        'booking_ref' => $bookingRef,
        'message' => "Thank you! Your space booking application has been received successfully. Your Reference Number is {$bookingRef}. A confirmation email has been sent to {$email}."
    ]);
} else {
    // Elegant standalone HTML confirmation page
    echo "<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1.0'>
  <title>Application Received – Dental Show 2027</title>
  <link rel='stylesheet' href='css/style.css'>
</head>
<body style='background: #f8fafc; font-family: Inter, sans-serif; margin: 0; padding: 40px 16px;'>
  <div style='max-width: 680px; margin: 40px auto; background: #ffffff; border-radius: 16px; padding: 40px 32px; box-shadow: 0 10px 40px rgba(2, 62, 115, 0.08); text-align: center;'>
    <div style='width: 72px; height: 72px; background: #ecfdf5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;'>
      <svg width='36' height='36' viewBox='0 0 24 24' fill='none' stroke='#00A86B' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='20 6 9 17 4 12'></polyline></svg>
    </div>
    <h1 style='color: #023E73; font-size: 26px; margin: 0 0 10px; font-weight: 800;'>Application Received Successfully!</h1>
    <p style='color: #64748b; font-size: 15px; margin: 0 0 24px;'>Thank you, <strong>{$companyName}</strong>. Your space booking application for Dental Show 2027 has been registered.</p>
    
    <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px 24px; margin-bottom: 28px; text-align: left;'>
      <div style='display: flex; justify-content: space-between; margin-bottom: 8px;'>
        <span style='color: #64748b; font-size: 14px;'>Booking Reference:</span>
        <strong style='color: #00A86B; font-size: 15px;'>{$bookingRef}</strong>
      </div>
      <div style='display: flex; justify-content: space-between; margin-bottom: 8px;'>
        <span style='color: #64748b; font-size: 14px;'>Contact Person:</span>
        <strong style='color: #023E73; font-size: 14px;'>{$contactPerson}</strong>
      </div>
      <div style='display: flex; justify-content: space-between; margin-bottom: 8px;'>
        <span style='color: #64748b; font-size: 14px;'>Email:</span>
        <span style='color: #334155; font-size: 14px;'>{$email}</span>
      </div>
      <div style='display: flex; justify-content: space-between;'>
        <span style='color: #64748b; font-size: 14px;'>Stall Selection:</span>
        <span style='color: #334155; font-size: 14px;'>{$stallType} ({$stallsRequired} Stall(s))</span>
      </div>
    </div>
    
    <p style='color: #475569; font-size: 14px; line-height: 1.6; margin-bottom: 28px;'>
      A confirmation email with the complete application details and remittance guidelines has been sent to <strong>{$email}</strong>.<br>Our team will contact you shortly regarding stall layout and allotment.
    </p>
    
    <div style='display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;'>
      <a href='home' style='display: inline-block; padding: 12px 24px; background: #00A86B; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14.5px;'>Return to Home</a>
      <a href='space-booking' style='display: inline-block; padding: 12px 24px; background: #f1f5f9; color: #023E73; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14.5px;'>Submit Another Form</a>
    </div>
  </div>
</body>
</html>";
}
