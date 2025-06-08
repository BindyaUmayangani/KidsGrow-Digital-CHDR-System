<?php
session_start();
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

header('Content-Type: application/json');

// Get JSON data from the request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// If no JSON data is sent, fallback to POST data
if (empty($data)) {
    $email    = trim($_POST['email'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $password = $_POST['password'] ?? ''; // Plain-text password
} else {
    $email    = trim($data['email'] ?? '');
    $fullName = trim($data['full_name'] ?? '');
    $password = $data['password'] ?? ''; // Plain-text password
}

// Log received data for debugging
error_log("Received email request: " . $email . ", " . $fullName);

// Validate required fields
if (empty($email) || empty($fullName) || empty($password)) {
    echo json_encode([
        "status"  => "error", 
        "message" => "Missing required fields: " .
            (empty($email) ? 'email ' : '') .
            (empty($fullName) ? 'full_name ' : '') .
            (empty($password) ? 'password' : '')
    ]);
    exit;
}

$mail = new PHPMailer(true);
try {
    // SMTP configuration
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ypremium.in1@gmail.com'; // Your SMTP username
    $mail->Password   = 'vgjjpcoakjfprtip';         // Your SMTP password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // Email content settings
    $mail->setFrom('ypremium.in1@gmail.com', 'KidsGrow Team');
    $mail->addAddress($email, $fullName);
    $mail->Subject = "Your Admin Account Credentials";
    $mail->Body    = "Dear $fullName,\n\nYour admin account has been created successfully.\n\nEmail: $email\nPassword: $password\n\nPlease log in and change your password immediately.\n\nBest regards,\nKidsGrow Team";

    // Attempt to send the email
    $mail->send();
    echo json_encode(["status" => "success", "message" => "Admin credentials email sent."]);
} catch (Exception $e) {
    error_log("PHPMailer Error: " . $mail->ErrorInfo);
    echo json_encode(["status" => "error", "message" => "Failed to send email."]);
}
?>
