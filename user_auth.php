<?php
session_start();

// Database connection details
$dsn         = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user     = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Database connection failed: " . $e->getMessage()]);
    exit;
}

// Utility function for input validation
function validateInput($input, $type) {
    if (empty($input)) {
        return "$type is required";
    }
    if ($type === 'email' && !filter_var($input, FILTER_VALIDATE_EMAIL)) {
        return "Invalid email format";
    }
    // For sign-up password validation, enforce complexity.
    // For sign-in, we'll only require a non-empty password.
    if ($type === 'password' && (strlen($input) < 8 ||
        !preg_match('/[A-Z]/', $input) ||
        !preg_match('/[a-z]/', $input) ||
        !preg_match('/[0-9]/', $input) ||
        !preg_match('/[\W_]/', $input))) {
        return "Password must be at least 8 characters long and include uppercase, lowercase, a number, and a special character.";
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'sign_in') {
        // Sign In Action
        $email    = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        // Validate email only
        $email_error = validateInput($email, 'email');
        if ($email_error) {
            echo json_encode(['success' => false, 'message' => $email_error]);
            exit;
        }
        if (empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Password is required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['user_role'] = $user['role'];
                echo json_encode(['success' => true, 'message' => 'Sign in successful', 'role' => $user['role']]);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
                exit;
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An unexpected error occurred.']);
            exit;
        }
    } elseif ($action === 'verify_otp') {
        // OTP Verification Action
        $email   = $_POST['email'] ?? '';
        $otp_input = $_POST['otp'] ?? '';

        if (empty($email) || empty($otp_input)) {
            echo json_encode(['success' => false, 'message' => 'Email and OTP are required.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email AND otp = :otp");
            $stmt->execute([':email' => $email, ':otp' => $otp_input]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                // OTP verified – clear the OTP and allow reset by saving email in session.
                $updateStmt = $pdo->prepare("UPDATE users SET otp = NULL WHERE email = :email");
                $updateStmt->execute([':email' => $email]);
                $_SESSION['reset_email'] = $email;
                echo json_encode(['success' => true, 'message' => 'OTP verified.']);
                exit;
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid OTP.']);
                exit;
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
            exit;
        }
    } elseif ($action === 'reset_password') {
        // Reset Password Action
        if (!isset($_SESSION['reset_email'])) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized request.']);
            exit;
        }
        $email = $_SESSION['reset_email'];
        $new_password = $_POST['new_password'] ?? '';
        // Here you might enforce full password complexity if this is a signup action.
        // But if you want to enforce it, leave this validation as is.
        // Otherwise, you can simply check if new_password is provided.
        $password_error = validateInput($new_password, 'password');
        if ($password_error) {
            echo json_encode(['success' => false, 'message' => $password_error]);
            exit;
        }
        try {
            $passwordHash = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = :password WHERE email = :email");
            $updateStmt->execute([':password' => $passwordHash, ':email' => $email]);
            unset($_SESSION['reset_email']);
            echo json_encode(['success' => true, 'message' => 'Password has been reset successfully.']);
            exit;
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again later.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}
?>
