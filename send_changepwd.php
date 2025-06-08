<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// If using Composer, you'd do: require 'vendor/autoload.php';
// Otherwise, require the local PHPMailer files:
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// 1) Read POST data
$email     = trim($_POST['email'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');

// 2) Validate
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

// 3) Immediately respond so the main script won't hang
$response = json_encode([
    'success' => true,
    'message' => 'Password-change email queued.'
]);

// End session lock so user can continue
session_write_close();
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json');
header('Content-Length: ' . strlen($response));
echo $response;
flush();
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// 4) Send email in background
$mail = new PHPMailer(true);
try {
    // SMTP settings
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; 
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ypremium.in1@gmail.com'; // <-- Updated to your email
    $mail->Password   = 'vgjjpcoakjfprtip';       // <-- Updated to your App Password
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('ypremium.in1@gmail.com', 'KidsGrow Team');
    $mail->addAddress($email, $full_name);

    $mail->Subject = "Your KidsGrow Password Has Been Changed";
    $mail->Body    = "Hello $full_name,\n\n"
                   . "We want to let you know that your KidsGrow account password was just changed.\n"
                   . "If you did not make this change, please contact support immediately.\n\n"
                   . "Best Regards,\nKidsGrow Team";

    $mail->send();
} catch (Exception $e) {
    error_log("send_changepwd.php: PHPMailer Error: " . $mail->ErrorInfo);
}
exit;
