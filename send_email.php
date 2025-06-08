<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $full_name = $_POST['full_name'] ?? '';

    if (empty($email) || empty($full_name)) {
        error_log("Email or full name is missing.");
        echo json_encode(['status' => 'error', 'message' => 'Invalid email or name']);
        exit;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'ypremium.in1@gmail.com'; // SMTP username
        $mail->Password = 'vgjjpcoakjfprtip'; // SMTP password (ensure this is correct)
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('ypremium.in1@gmail.com', 'KidsGrow Team');
        $mail->addAddress($email, $full_name);
        $mail->Subject = "Welcome to KidsGrow!";
        $mail->Body = "Dear $full_name,\n\nWelcome to KidsGrow!\n\nThank you for joining our platform. If you need assistance, feel free to reach out.\n\nBest regards,\nThe KidsGrow Team";

        if ($mail->send()) {
            error_log("Email sent successfully to $email");
            echo json_encode(['status' => 'success', 'message' => 'Email sent successfully']);
        } else {
            error_log("Email sending failed to $email. Error: " . $mail->ErrorInfo);
            echo json_encode(['status' => 'error', 'message' => 'Email sending failed']);
        }

    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Failed to send email']);
    }
}
?>
