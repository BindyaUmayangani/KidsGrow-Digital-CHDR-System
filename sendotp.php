<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Database connection details
$dsn         = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user     = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit;
}

// Debug: log the raw POST data
error_log("DEBUG: sendotp.php POST data: " . print_r($_POST, true));

// Get, trim, and remove control characters from the email
$email = trim($_POST['email'] ?? '');
$email = preg_replace('/[\x00-\x1F\x7F]+/', '', $email);
error_log("DEBUG: Received email after cleaning: " . $email);

// Validate the email
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    error_log("DEBUG: Email validation failed. Email: " . $email);
    echo json_encode(['success' => false, 'message' => 'Invalid email address.']);
    exit;
}

// Check if the user exists
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        error_log("DEBUG: Email not found: " . $email);
        echo json_encode(['success' => false, 'message' => 'Email not found.']);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("DEBUG: Error checking user: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred.']);
    exit;
}

// Generate a 6-digit OTP and update the user's record
$otp = rand(100000, 999999);
try {
    $updateStmt = $pdo->prepare("UPDATE users SET otp = :otp WHERE email = :email");
    $updateStmt->execute([':otp' => $otp, ':email' => $email]);
    error_log("DEBUG: OTP generated and updated: " . $otp);
} catch (PDOException $e) {
    http_response_code(500);
    error_log("DEBUG: Failed to update OTP: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update OTP.']);
    exit;
}

// Prepare success response
$response = json_encode(['success' => true, 'message' => 'OTP has been sent to your email.']);

// Close session to free session lock
session_write_close();

// Clear output buffers if any
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Encoding: none');
header('Content-Length: ' . strlen($response));
header('Content-Type: application/json');
echo $response;
flush();
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// Now continue processing the email send in the background
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ypremium.in1@gmail.com'; // Your SMTP username
    $mail->Password   = 'vgjjpcoakjfprtip';         // Your SMTP password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('ypremium.in1@gmail.com', 'KidsGrow Team');
    $mail->addAddress($email, $user['full_name']);
    $mail->Subject = "Your OTP for Password Reset";
    $mail->Body    = "Dear " . $user['full_name'] . ",\n\nYour OTP for password reset is: $otp\n\nIf you did not request a password reset, please ignore this email.\n\nBest regards,\nKidsGrow Team";

    $mail->send();
    error_log("DEBUG: OTP email sent successfully to: " . $email);
} catch (Exception $e) {
    error_log("DEBUG: PHPMailer Error: " . $mail->ErrorInfo);
    // Since the response is already sent, we just log the error.
}
exit;
?>
