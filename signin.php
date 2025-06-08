<?php
session_start();
$errorMessage   = $_SESSION['error_message'] ?? '';
$successMessage = $_SESSION['success_message'] ?? '';

// Clear messages from the session
unset($_SESSION['error_message'], $_SESSION['success_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>

  <meta charset="UTF-8" />
  <!-- Responsive meta tag -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
  <title>KidsGrow - Login</title>
  
  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-4MHDP643CV"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-4MHDP643CV');
  </script>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
  <style>
    /* Global Styles */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }
    body {
      min-height: 100vh;
      background: #f4f7ff;
      display: flex;
    }
    .container {
      display: flex;
      width: 100%;
    }
    /* Left Section - Desktop */
    .left-section {
      flex: 1;
      background: linear-gradient(135deg, #0061ff, #60efff);
      padding: 40px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      color: white;
      position: relative;
      overflow: hidden;
    }
    .left-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      /* Uncomment and update URL to use an illustration image:
      background: url('https://placehold.co/600x400/0061ff/ffffff?text=KidsGrow+Illustration') center/cover; */
      opacity: 0.1;
    }
    .brand {
      position: relative;
      z-index: 1;
    }
    .brand h1 {
      font-size: 24px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .brand h1 i {
      font-size: 28px;
    }
    .hero-content {
      position: relative;
      z-index: 1;
      max-width: 500px;
      margin: auto;
      text-align: center;
    }
    .hero-content h2 {
      font-size: 36px;
      margin-bottom: 20px;
    }
    .hero-content p {
      font-size: 18px;
      opacity: 0.9;
      line-height: 1.6;
    }
    .copyright {
      position: relative;
      z-index: 1;
      font-size: 14px;
      opacity: 0.8;
      text-align: center;
    }
    /* Right Section - Desktop */
    .right-section {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px;
    }
    .form-container {
      width: 100%;
      max-width: 400px;
      background: white;
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }
    .form-header {
      text-align: center;
      margin-bottom: 30px;
    }
    .form-header h2 {
      color: #333;
      font-size: 28px;
      margin-bottom: 10px;
    }
    .form-header p {
      color: #666;
      font-size: 16px;
    }
    .form-group {
      margin-bottom: 25px;
    }
    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: #333;
      font-weight: 500;
    }
    .form-group input {
      width: 100%;
      padding: 12px 15px;
      border: 2px solid #e1e1e1;
      border-radius: 10px;
      font-size: 16px;
      transition: all 0.3s ease;
    }
    .form-group input:focus {
      border-color: #0061ff;
      outline: none;
      box-shadow: 0 0 0 3px rgba(0, 97, 255, 0.1);
    }
    .password-container {
      position: relative;
    }
    .toggle-password {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #666;
      cursor: pointer;
      font-size: 18px;
    }
    .action-button {
      width: 100%;
      padding: 14px;
      background: #0061ff;
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.3s ease;
    }
    .action-button:hover {
      background: #0052d6;
    }
    .link-group {
      text-align: center;
      margin-top: 20px;
    }
    .link-group a {
      color: #0061ff;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
    }
    .link-group a:hover {
      text-decoration: underline;
    }
    /* Popup Notification Styles */
    .popup {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 25px;
      border-radius: 10px;
      color: white;
      font-size: 14px;
      opacity: 0;
      transform: translateY(-20px);
      transition: all 0.3s ease;
    }
    .error-popup {
      background: #ff4d4d;
    }
    .success-popup {
      background: #00c853;
    }
    .popup.show {
      opacity: 1;
      transform: translateY(0);
    }
    /* Hidden sections */
    .hidden {
      display: none;
    }
    /* Mobile-specific styles */
    @media screen and (max-width: 768px) {
      .container {
        flex-direction: column;
      }
      /* On mobile, show the left (branding) section on top */
      .left-section {
        order: 1;
        padding: 20px;
        text-align: center;
      }
      .right-section {
        order: 2;
        padding: 20px;
      }
      .form-container {
        max-width: 90%;
        padding: 20px;
        margin: 0 auto;
      }
      .form-header h2 {
        font-size: 24px;
      }
      .hero-content h2 {
        font-size: 28px;
      }
      .hero-content p {
        font-size: 16px;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Left Section (Branding / Welcome) -->
    <div class="left-section">
      <div class="brand">
        <h1><i class="fas fa-child"></i> KidsGrow</h1>
      </div>
      <div class="hero-content">
        <h2>Welcome to KidsGrow!</h2>
        <p>Empowering healthy childhoods with accurate records and personalized care. Join us in safeguarding the future, one child at a time.</p>
      </div>
      <div class="copyright">
        <p>&copy; 2025 KidsGrow Educational Solutions. All Rights Reserved.</p>
      </div>
    </div>
    <!-- Right Section (Form) -->
    <div class="right-section">
      <div class="form-container">
        <!-- Sign In Section -->
        <div id="loginSection">
          <div class="form-header">
            <h2>Sign In</h2>
            <p>Access your KidsGrow account</p>
          </div>
          <form id="login-form" method="post" action="user_auth.php?no_rewrite=1">
            <input type="hidden" name="action" value="sign_in">
            <div class="form-group">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="form-group">
              <label for="password">Password</label>
              <div class="password-container">
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">
                  <i class="fas fa-eye"></i>
                </button>
              </div>
            </div>
            <button type="submit" class="action-button">Sign In</button>
            <div class="link-group">
              <a onclick="showSection('forgotSection')">Forgot Password?</a>
            </div>
          </form>
        </div>
        <!-- Forgot Password: Send OTP -->
        <div id="forgotSection" class="hidden">
          <div class="form-header">
            <h2>Forgot Password</h2>
            <p>Enter your email to receive an OTP</p>
          </div>
          <div class="form-group">
            <label for="forgot-email">Email Address</label>
            <input type="email" id="forgot-email" name="email" placeholder="Enter your email" required>
          </div>
          <button class="action-button" onclick="sendOTP()">Send OTP</button>
          <div class="link-group">
            <a onclick="showSection('loginSection')">Back to Sign In</a>
          </div>
        </div>
        <!-- OTP Verification -->
        <div id="otpSection" class="hidden">
          <div class="form-header">
            <h2>OTP Verification</h2>
            <p>Enter the OTP sent to your email</p>
          </div>
          <div class="form-group">
            <label for="otp-input">OTP</label>
            <input type="text" id="otp-input" placeholder="Enter OTP" required>
          </div>
          <div class="form-group">
            <label for="otp-email">Email Address</label>
            <input type="email" id="otp-email" placeholder="Enter your email" readonly required>
          </div>
          <button class="action-button" onclick="verifyOTP()">Verify OTP</button>
          <div class="link-group">
            <a onclick="showSection('forgotSection')">Back</a>
          </div>
        </div>
        <!-- Reset Password -->
        <div id="resetSection" class="hidden">
          <div class="form-header">
            <h2>Reset Password</h2>
            <p>Enter your new password</p>
          </div>
          <div class="form-group">
            <label for="new-password">New Password</label>
            <div class="password-container">
              <input type="password" id="new-password" placeholder="Enter new password" required>
              <button type="button" class="toggle-password" onclick="togglePasswordVisibility('new-password', this)">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>
          <button class="action-button" onclick="resetPassword()">Reset Password</button>
          <div class="link-group">
            <a onclick="showSection('loginSection')">Back to Sign In</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Popup Notifications -->
  <div id="errorPopup" class="popup error-popup"><?php echo htmlspecialchars($errorMessage); ?></div>
  <div id="successPopup" class="popup success-popup"><?php echo htmlspecialchars($successMessage); ?></div>

  <script>
    document.addEventListener("DOMContentLoaded", function() {
      const loginForm = document.getElementById("login-form");

      // Toggle password visibility
      window.togglePasswordVisibility = function(inputId = "password", btn = null) {
        const passwordInput = document.getElementById(inputId);
        let toggleIcon;
        if (btn) {
          toggleIcon = btn.querySelector("i");
        } else {
          toggleIcon = document.querySelector(".toggle-password i");
        }
        if (passwordInput.type === "password") {
          passwordInput.type = "text";
          toggleIcon.className = "fas fa-eye-slash";
        } else {
          passwordInput.type = "password";
          toggleIcon.className = "fas fa-eye";
        }
      };

      // Show popup notifications
      window.showPopup = function(message, type) {
        const popup = document.getElementById(type === "error" ? "errorPopup" : "successPopup");
        popup.textContent = message;
        popup.classList.add("show");
        setTimeout(() => {
          popup.classList.remove("show");
        }, 3000);
      };

      // Switch sections
      window.showSection = function(sectionId) {
        document.getElementById("loginSection").classList.add("hidden");
        document.getElementById("forgotSection").classList.add("hidden");
        document.getElementById("otpSection").classList.add("hidden");
        document.getElementById("resetSection").classList.add("hidden");
        document.getElementById(sectionId).classList.remove("hidden");
      };

      // Handle sign in submission via AJAX
      loginForm.addEventListener("submit", function(e) {
        e.preventDefault();
        const formData = new FormData(loginForm);
        fetch("user_auth.php?no_rewrite=1", {
          method: "POST",
          body: new URLSearchParams(formData)
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showPopup(data.message, "success");
            // Redirect based on user role:
            if (data.role === "Parent") {
              window.location.href = "./u/dashboard.php";
            } else {
              window.location.href = "dashboard.php";
            }
          } else {
            showPopup(data.message, "error");
          }
        })
        .catch(error => {
          console.error("Error:", error);
          showPopup("An error occurred. Please try again.", "error");
        });
      });

      // Attach keydown listener to the entire login form to trigger submit on Enter
      loginForm.addEventListener("keydown", function(e) {
        if (e.key === "Enter") {
          e.preventDefault();
          loginForm.dispatchEvent(new Event("submit", { cancelable: true }));
        }
      });

      // sendOTP function
      window.sendOTP = function() {
        const email = document.getElementById("forgot-email").value;
        if (!email) {
          showPopup("Please enter your email.", "error");
          return;
        }
        console.log("Email being sent:", email);
        const formData = new URLSearchParams();
        formData.append("email", email);
        console.log("POST body:", formData.toString());
        fetch("sendotp", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: formData.toString()
        })
        .then(response => response.json())
        .then(data => {
          console.log("Response data:", data);
          if (data.success) {
            showPopup(data.message, "success");
            document.getElementById("otp-email").value = email;
            showSection("otpSection");
          } else {
            showPopup(data.message, "error");
          }
        })
        .catch(error => {
          console.error("Fetch Error:", error);
          showPopup("An error occurred. Please try again.", "error");
        });
      };

      // verifyOTP function
      window.verifyOTP = function() {
        const email = document.getElementById("otp-email").value;
        const otp = document.getElementById("otp-input").value;
        if (!email || !otp) {
          showPopup("Please enter both email and OTP.", "error");
          return;
        }
        const formData = new URLSearchParams();
        formData.append("action", "verify_otp");
        formData.append("email", email);
        formData.append("otp", otp);
        fetch("user_auth.php?no_rewrite=1", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: formData.toString()
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showPopup(data.message, "success");
            showSection("resetSection");
          } else {
            showPopup(data.message, "error");
          }
        })
        .catch(error => {
          console.error("Error:", error);
          showPopup("An error occurred. Please try again.", "error");
        });
      };

      // resetPassword function
      window.resetPassword = function() {
        const newPassword = document.getElementById("new-password").value;
        if (!newPassword) {
          showPopup("Please enter your new password.", "error");
          return;
        }
        const formData = new URLSearchParams();
        formData.append("action", "reset_password");
        formData.append("new_password", newPassword);
        fetch("user_auth", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: formData.toString()
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showPopup(data.message, "success");
            setTimeout(() => {
              showSection("loginSection");
            }, 3000);
          } else {
            showPopup(data.message, "error");
          }
        })
        .catch(error => {
          console.error("Error:", error);
          showPopup("An error occurred. Please try again.", "error");
        });
      };
    });
  </script>
</body>
</html>
