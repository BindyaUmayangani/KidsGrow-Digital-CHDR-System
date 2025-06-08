<?php
// IMPORTANT: Ensure no whitespace or BOM exists before the opening <?php tag!

session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include PHPMailer classes – adjust paths as needed
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Ensure we respond in JSON format
header('Content-Type: application/json');

// --- Debug Step 1: Read raw input and log it ---
$input = file_get_contents('php://input');
error_log("sendusercred.php - Received raw input: " . $input);

// --- Step 2: Decode JSON data ---
$data  = json_decode($input, true);
if (!$data) {
    error_log("sendusercred.php - No valid JSON data provided.");
    echo json_encode([
        "status"  => "error",
        "message" => "Missing required fields."
    ]);
    exit;
}

// --- Step 3: Extract and validate fields ---
$email    = trim($data['email'] ?? '');
$fullName = trim($data['name'] ?? '');  // Expecting key "name"
$password = $data['password'] ?? '';

error_log("sendusercred.php - Parsed email: $email");
error_log("sendusercred.php - Parsed name: $fullName");
error_log("sendusercred.php - Parsed password length: " . strlen($password));

if (empty($email) || empty($fullName) || empty($password)) {
    $missing = "";
    $missing .= empty($email) ? 'email ' : '';
    $missing .= empty($fullName) ? 'name ' : '';
    $missing .= empty($password) ? 'password' : '';
    error_log("sendusercred.php - Validation failed. Missing: $missing");
    echo json_encode([
        "status"  => "error",
        "message" => "Missing required fields: $missing"
    ]);
    exit;
}

// --- Step 4: Initialize PHPMailer and configure SMTP ---
$mail = new PHPMailer(true);
try {
    // Enable SMTP debugging to error_log for detailed info
    $mail->isSMTP();
    $mail->SMTPDebug   = 2;
    $mail->Debugoutput = function($str, $level) {
        error_log("SMTP Debug (level $level): $str");
    };

    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ypremium.in1@gmail.com'; // Your SMTP username
    $mail->Password   = 'vgjjpcoakjfprtip';         // Your SMTP password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // Set sender and recipient
    $mail->setFrom('ypremium.in1@gmail.com', 'KidsGrow Team');
    $mail->addAddress($email, $fullName);
    $mail->isHTML(false); // Plain text email

    // Set email subject and body
    $mail->Subject = "Your KidsGrow Account Credentials";
    $mail->Body    = "Dear $fullName,\n\n"
                   . "Your KidsGrow user account has been created successfully.\n\n"
                   . "Email: $email\n"
                   . "Password: $password\n\n"
                   . "Please log in and change your password immediately.\n\n"
                   . "Best regards,\n"
                   . "KidsGrow Team";

    error_log("sendusercred.php - Attempting to send email to $email (Name: $fullName)");
    
    // --- Step 5: Attempt to send the email ---
    $mail->send();
    error_log("sendusercred.php - Email sent successfully to $email");
    echo json_encode([
        "status"  => "success",
        "message" => "User credentials email sent."
    ]);
} catch (Exception $e) {
    error_log("sendusercred.php - PHPMailer Exception: " . $mail->ErrorInfo);
    echo json_encode([
        "status"  => "error",
        "message" => "Failed to send email."
    ]);
}
