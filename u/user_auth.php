<?php
session_start();
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// PHPMailer include
require __DIR__ . '/../PHPMailer-master/src/Exception.php';
require __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer-master/src/SMTP.php';

$dsn  = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$user = "neondb_owner";
$pass = "npg_39CVAQbvIlOy";

try {
  $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
  http_response_code(500);
  echo json_encode(['success' => false, 'message' => 'DB connection failed']);
  exit;
}

function fail($m) { echo json_encode(['success' => false, 'message' => $m]); exit; }
function ok($d)   { echo json_encode($d); exit; }

$act = $_POST['action'] ?? '';

switch ($act) {
  // — Parent Sign‑In —
  case 'sign_in':
    $email = trim($_POST['email'] ?? '');
    $pw    = $_POST['password'] ?? '';

    if (!$email) fail('Email is required');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Invalid email');
    if (!$pw) fail('Password is required');

    $stmt = $pdo->prepare("SELECT id, full_name, role, password FROM users WHERE email = :e");
    $stmt->execute([':e' => $email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u || !password_verify($pw, $u['password'])) fail('Invalid email or password');
    if ($u['role'] !== 'Parent') ok(['success' => true, 'role' => $u['role'], 'message' => 'Not a parent account']);

    $_SESSION['user_id']   = $u['id'];
    $_SESSION['user_name'] = $u['full_name'];
    $_SESSION['user_role'] = $u['role'];
    ok(['success' => true, 'role' => $u['role'], 'message' => 'Signed in successfully']);
    break;

  // — Send OTP via Email (merged)
  case 'send_otp':
    $email = trim($_POST['email'] ?? '');
    $email = preg_replace('/[\x00-\x1F\x7F]/', '', $email);

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Invalid email address');

    // Check parent user
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = :email AND role = 'Parent'");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) fail('Parent account not found with this email.');

    // Generate & store OTP
    $otp = random_int(100000, 999999);
    $pdo->prepare("UPDATE users SET otp = :otp WHERE email = :email")
        ->execute([':otp' => $otp, ':email' => $email]);

    // Respond first
    $response = json_encode(['success' => true, 'message' => 'OTP sent to your email.']);
    session_write_close();
    while (ob_get_level()) ob_end_clean();
    header('Content-Length: ' . strlen($response));
    echo $response;
    flush();
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

    // Send email
    $mail = new PHPMailer(true);
    try {
      $mail->isSMTP();
      $mail->Host       = 'smtp.gmail.com';
      $mail->SMTPAuth   = true;
      $mail->Username   = 'ypremium.in1@gmail.com';
      $mail->Password   = 'vgjjpcoakjfprtip';
      $mail->SMTPSecure = 'tls';
      $mail->Port       = 587;

      $mail->CharSet = 'UTF-8'; // ✅ Fix encoding

      $mail->setFrom('ypremium.in1@gmail.com', 'KidsGrow Team');
      $mail->addAddress($email, $user['full_name']);
      $mail->isHTML(true);
      $mail->Subject = 'KidsGrow – OTP for Password Reset';
      $mail->Body = "
        <p>Dear {$user['full_name']},</p>
        <p>Your OTP for resetting your KidsGrow account password is:</p>
        <h2 style='color:#009688;'>$otp</h2>
        <p>This OTP is valid for a short period. If you did not request this, you can safely ignore this email.</p>
        <br>
        <p>Best regards,<br>KidsGrow Support Team</p>
      ";

      $mail->send();
      error_log("OTP email sent to $email");
    } catch (Exception $e) {
      error_log("PHPMailer Error: " . $mail->ErrorInfo);
    }
    break;

  // — Verify OTP —
  case 'verify_otp':
    $otp = trim($_POST['otp'] ?? '');
    if (!$otp) fail('OTP is required');
    $stmt = $pdo->prepare("SELECT email, id FROM users WHERE otp = :otp");
    $stmt->execute([':otp' => $otp]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) fail('Invalid OTP');
    $_SESSION['reset_email'] = $u['email'];
    $pdo->prepare("UPDATE users SET otp = NULL WHERE id = :id")->execute([':id' => $u['id']]);
    ok(['success' => true, 'message' => 'OTP verified']);
    break;

  // — Reset Password —
  case 'reset_password':
    $new = $_POST['new_password'] ?? '';
    if (!$new) fail('New password is required');
    if (!isset($_SESSION['reset_email'])) fail('Unauthorized');
    $hash = password_hash($new, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = :p WHERE email = :e")
        ->execute([':p' => $hash, ':e' => $_SESSION['reset_email']]);
    unset($_SESSION['reset_email']);
    ok(['success' => true, 'message' => 'Password reset successfully']);
    break;

  default:
    fail('Invalid action');
}
