<?php
session_start();

// 1) Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: signin.php');
    exit;
}

// Retrieve session details
$userId   = $_SESSION['user_id'];
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Sarah Smith';
$userRole = $_SESSION['user_role'];

// 2) Database connection
$dsn         = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user     = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ============ Retrieve User Info from DB ============
$stmt = $pdo->prepare("SELECT full_name, email, password, profile_image_url, profile_image_delete_url
                       FROM users
                       WHERE id = :id");
$stmt->execute([':id' => $userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// If user not found, handle accordingly
if (!$userData) {
    die("User record not found.");
}

// Default or existing profile image
$currentProfileImage = $userData['profile_image_url']
                       ? $userData['profile_image_url']
                       : "https://placehold.co/225x225"; // fallback placeholder

// For success/error alerts in the page
$alertMessage = "";
$alertType    = "";

// ------------ (A) Handle Update Profile Image ------------
if (isset($_POST['update_image']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start output buffering to prevent stray output
    ob_start();
    try {
        // If there's an old image, delete it from imgbb (suppress errors)
        if (!empty($userData['profile_image_delete_url'])) {
            @file_get_contents($userData['profile_image_delete_url']);
        }

        // Check if a new file is uploaded
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
            $imageContent = file_get_contents($_FILES['profile_image']['tmp_name']);
            $encodedImage = base64_encode($imageContent);

            // Use user’s email to create a unique image name
            $imageName = str_replace(['@','.', '+'], '_', $userData['email']);

            // Upload to imgbb
            $api_key   = "6114e595f0f58654aca55291325e135e"; // your imgbb API key
            $uploadUrl = "https://api.imgbb.com/1/upload?key={$api_key}&name={$imageName}";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $uploadUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'image' => $encodedImage
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);
            // Validate response
            if (!$data || !isset($data['success'])) {
                throw new Exception("Invalid ImgBB API response");
            }

            if ($data['success']) {
                $newImageUrl  = $data['data']['url'];
                $newDeleteUrl = $data['data']['delete_url'];

                // Update DB
                $updateStmt = $pdo->prepare("UPDATE users
                                             SET profile_image_url = :url, profile_image_delete_url = :del
                                             WHERE id = :id");
                $updateStmt->execute([
                    ':url' => $newImageUrl,
                    ':del' => $newDeleteUrl,
                    ':id'  => $userId
                ]);

                $alertMessage = "Profile image updated successfully!";
                $alertType    = "success";
                // Refresh user data in memory
                $currentProfileImage = $newImageUrl;
            } else {
                $error = isset($data['error']['message']) ? $data['error']['message'] : "Unknown ImgBB error";
                throw new Exception("ImgBB Error: " . $error);
            }
        } else {
            throw new Exception("No image file uploaded.");
        }
    } catch (Exception $e) {
        $alertType = "error";
        $alertMessage = $e->getMessage();
    }
    
    // If this is an AJAX request, return a JSON response and exit
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        ob_clean(); // Clear any accidental output
        header('Content-Type: application/json');
        echo json_encode([
            'success'  => ($alertType === 'success'),
            'message'  => $alertMessage,
            'imageUrl' => $newImageUrl ?? $currentProfileImage
        ]);
        exit();
    }
    ob_end_flush();
}

// ------------ (B) Handle Change Password ------------
if (isset($_POST['change_password']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password']     ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    // Basic validation
    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $alertMessage = "All password fields are required.";
        $alertType    = "error";
    } elseif ($newPass !== $confirmPass) {
        $alertMessage = "New password and confirm password do not match.";
        $alertType    = "error";
    } else {
        // Verify current password
        if (!password_verify($currentPass, $userData['password'])) {
            $alertMessage = "Current password is incorrect.";
            $alertType    = "error";
        } else {
            // Hash and update
            $hashedNew = password_hash($newPass, PASSWORD_DEFAULT);
            $stmtUpdate = $pdo->prepare("UPDATE users SET password = :newPass WHERE id = :id");
            $stmtUpdate->execute([
                ':newPass' => $hashedNew,
                ':id'      => $userId
            ]);

            $alertMessage = "Password changed successfully!";
            $alertType    = "success";

            // (C) Send an email notification about the password change
            $postData = http_build_query([
                'email'     => $userData['email'],
                'full_name' => $userData['full_name']
            ]);

            $sendUrl = 'https://kidsgrow.xyz/send_changepwd'; // Adjust if needed
            $ch2 = curl_init($sendUrl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_POST, true);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
            curl_exec($ch2);
            curl_close($ch2);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Manage Profile</title>
  <!-- Google Fonts (Poppins) -->
  <link rel="preconnect" href="https://fonts.gstatic.com" />
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
  />
  <!-- Font Awesome Icons -->
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
  />
  <style>
    /* ====================== Root Variables ====================== */
    :root {
      --primary-color: #274FB4; /* Ministry of Health Blue */
      --secondary-color: #8FC4F1;
      --text-dark: #333;
      --text-light: #666;
      --white: #fff;
      --border-radius: 8px;
      --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      --gray-bg: #f0f0f0;
    }
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }
    body {
      background-color: var(--gray-bg);
    }

    /* ====================== SIDEBAR ====================== */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: 220px;
      background: var(--primary-color);
      color: var(--white);
      padding: 20px;
      overflow-y: auto;
      z-index: 999;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .sidebar .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 40px;
    }
    .sidebar .logo span {
      font-size: 24px;
      font-weight: 800;
    }
    .logo img {
      width: 42px;
      height: 44px;
      object-fit: cover;
    }
    .logo span {
      font-size: 20px;
      font-weight: 700;
    }
    .menu-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 0;
      cursor: pointer;
      color: var(--white);
      text-decoration: none;
      font-size: 16px;
      font-weight: 700;
    }
    .menu-item i {
      font-size: 18px;
    }
    .menu-item:hover,
    .menu-item.active {
      background-color: rgba(255, 255, 255, 0.2);
      padding-left: 10px;
      border-radius: var(--border-radius);
    }

    /* ========== Sidebar Bottom User Profile ========== */
    .sidebar-user-profile {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 40px;
      padding: 10px;
      background: rgba(255,255,255,0.2);
      border-radius: var(--border-radius);
      cursor: pointer;
      position: relative;
    }
    .sidebar-user-profile img {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      object-fit: cover;
    }
    .sidebar-user-info {
      display: flex;
      flex-direction: column;
      font-size: 14px;
      color: var(--white);
      line-height: 1.2;
    }
    .sidebar-user-name {
      font-weight: 700;
      font-size: 16px;
    }
    .sidebar-user-role {
      font-weight: 400;
      font-size: 14px;
    }
    .sidebar-user-menu {
      display: none;
      position: absolute;
      bottom: 60px;
      left: 0;
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 5px;
      min-width: 120px;
      box-shadow: var(--shadow);
      padding: 5px 0;
      color: #333;
      z-index: 1000;
    }
    .sidebar-user-menu a {
      display: block;
      padding: 8px 12px;
      text-decoration: none;
      color: #333;
      font-size: 14px;
    }
    .sidebar-user-menu a:hover {
      background-color: #f0f0f0;
    }

    /* ====================== MAIN CONTENT ====================== */
    .main-content {
      margin-left: 220px;
      min-height: 100vh;
      padding: 20px;
      position: relative;
    }
    .profile-container {
      background: #FFFCFC;
      border-radius: 20px;
      padding: 20px;
      min-height: calc(100vh - 40px);
      position: relative;
    }

    /* ========== Profile Header ========== */
    .profile-header {
      text-align: center;
      margin-bottom: 30px;
      position: relative; /* Added for progress ring positioning */
    }
    .profile-header img {
      width: 225px;
      height: 225px;
      border-radius: 50%;
      border: 3px var(--primary-color) solid;
      object-fit: cover;
      position: relative;
      z-index: 1;
    }

    /* ========== Progress Ring Styles (New) ========== */
    .progress-ring {
      width: 231px;
      height: 231px;
      border-radius: 50%;
      position: absolute;
      top: -3px;
      left: 50%;
      transform: translateX(-50%);
      z-index: 2;
      pointer-events: none;
    }
    .progress-ring circle {
      fill: none;
      stroke-width: 3px;
      stroke-linecap: round;
    }
    .progress-background {
      stroke: var(--primary-color); /* Changed from #e0e0e0 to primary color */
    }
    .progress-bar {
      stroke: var(--primary-color);
      transition: stroke-dashoffset 0.1s linear;
    }
    .profile-header h2 {
      font-size: 24px;
      font-weight: 600;
      margin: 15px 0 5px;
    }
    .profile-header p {
      font-size: 18px;
      color: #333;
    }

    /* ========== Change Profile Photo Button ========== */
    .update-image-btn {
      margin-top: 15px;
      display: inline-block;
      background: #fff;
      color: var(--primary-color);
      border: none;
      border-radius: 15px;
      padding: 8px 20px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
    }
    .update-image-btn:hover {
      opacity: 0.9;
    }
    .file-input {
      display: none;
    }

    /* ========== Password Form ========== */
    .password-form {
      max-width: 600px;
      margin: 0 auto;
      background: var(--primary-color);
      border-radius: 30px;
      padding: 30px;
      color: #fff;
    }
    .password-form h3 {
      font-size: 22px;
      margin-bottom: 20px;
      font-weight: 500;
    }
    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-size: 16px;
      font-weight: 500;
    }
    .form-group input {
      width: 100%;
      padding: 12px;
      border-radius: 10px;
      border: none;
      font-size: 14px;
      outline: none;
      background: #D9D9D9;
      color: #333;
    }
    .form-footer {
      display: flex;
      justify-content: flex-end;
    }
    .submit-btn {
      display: inline-block;
      background: #fff;
      color: var(--primary-color);
      border: none;
      border-radius: 15px;
      padding: 12px 25px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 20px;
    }
    .submit-btn:hover {
      opacity: 0.9;
    }

    /* ========== ALERT BOX ========== */
    .alert-box {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 20px;
      border-radius: 8px;
      color: #fff;
      font-size: 14px;
      font-weight: 500;
      z-index: 1000;
      display: none;
      animation: slideIn 0.3s ease-out;
    }
    .alert-success {
      background: #28a745;
    }
    .alert-error {
      background: #ff4444;
    }
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
  </style>
</head>
<body>
  <!-- ========== SIDEBAR ========== -->
  <div class="sidebar">
    <!-- Logo / Title -->
    <div>
      <div class="logo">
          <i class="fas fa-child" style="font-size: 24px;"></i>
          <span>KidsGrow</span>
      </div>
      <!-- Nav Menu Items -->
      <a href="dashboard.php" class="menu-item"><i class="fas fa-th-large"></i>Dashboard</a>
      <a href="child_profile.php" class="menu-item"><i class="fas fa-child"></i>Child Profiles</a>
      <a href="parent_profile.php" class="menu-item"><i class="fas fa-users"></i>Parent Profiles</a>
      <a href="vaccination.php" class="menu-item"><i class="fas fa-syringe"></i>Vaccination</a>
      <a href="home_visit.php" class="menu-item"><i class="fas fa-home"></i>Home Visit</a>
      <a href="thriposha_distribution.php" class="menu-item"><i class="fas fa-box"></i>Thriposha Distribution</a>
      <a href="growth_details.php" class="menu-item"><i class="fas fa-chart-line"></i>Growth Details</a>
      <?php if ($userRole === 'SuperAdmin'): ?>
        <a href="add_admin.php" class="menu-item"><i class="fas fa-user-shield"></i>Add Admin</a>
      <?php endif; ?>
    </div>

    <!-- Sidebar User Profile at the bottom -->
    <div class="sidebar-user-profile" id="sidebarUserProfile">
      <img src="<?php echo htmlspecialchars($currentProfileImage); ?>" alt="User Profile">
      <div class="sidebar-user-info">
        <span class="sidebar-user-name"><?php echo htmlspecialchars($userName); ?></span>
        <span class="sidebar-user-role"><?php echo htmlspecialchars($userRole); ?></span>
      </div>
      <div class="sidebar-user-menu" id="sidebarUserMenu">
        <a href="logout.php">Sign Out</a>
        <a href="manage_profile.php">Manage Profile</a>
      </div>
    </div>
  </div>

  <!-- ========== MAIN CONTENT ========== -->
  <div class="main-content">
    <div class="profile-container">
      <!-- Profile Header -->
      <div class="profile-header">
        <!-- Progress Ring (overlay) -->
        <div class="progress-ring">
          <svg viewBox="0 0 100 100">
            <circle class="progress-background" cx="50" cy="50" r="48" />
            <circle class="progress-bar" cx="50" cy="50" r="48" stroke-dasharray="301.59" stroke-dashoffset="301.59" />
          </svg>
        </div>
        <img src="<?php echo htmlspecialchars($currentProfileImage); ?>" alt="Profile Image" id="profileImage">
        <h2><?php echo htmlspecialchars($userData['full_name']); ?></h2>
        <p><?php echo htmlspecialchars($userData['email']); ?></p>

        <!-- Update Image Form -->
        <form id="updateImageForm" method="POST" enctype="multipart/form-data" style="margin-top:10px;">
          <input type="hidden" name="update_image" value="1">
          <label for="profileImageFile" class="update-image-btn">
            <i class="fas fa-image"></i> Change Profile Photo
          </label>
          <input
            type="file"
            id="profileImageFile"
            name="profile_image"
            class="file-input"
            accept="image/*"
          />
        </form>
      </div>

      <!-- Change Password Form -->
      <div class="password-form">
        <h3>Change Password</h3>
        <form method="POST" id="changePasswordForm">
          <input type="hidden" name="change_password" value="1">
          <div class="form-group">
            <label>Current Password</label>
            <input type="password" name="current_password" required />
          </div>
          <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" required />
          </div>
          <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required />
          </div>
          <div class="form-footer">
            <button type="submit" class="submit-btn">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ========== ALERT BOX ========== -->
  <div id="alertBox" class="alert-box"></div>

  <script>
    // Toggle user menu in the sidebar
    function toggleSidebarUserMenu() {
      const menu = document.getElementById('sidebarUserMenu');
      menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }
    document.getElementById('sidebarUserProfile').addEventListener('click', (e) => {
      e.stopPropagation();
      toggleSidebarUserMenu();
    });
    document.addEventListener('click', () => {
      const userMenu = document.getElementById('sidebarUserMenu');
      if (userMenu) userMenu.style.display = 'none';
    });

    // Show alert if there's a server message
    function showAlert(type, message) {
      const alertBox = document.getElementById('alertBox');
      alertBox.className = 'alert-box ' + (type === 'success' ? 'alert-success' : 'alert-error');
      alertBox.textContent = message;
      alertBox.style.display = 'block';
      setTimeout(() => {
        alertBox.style.display = 'none';
      }, 3000);
    }

    // AJAX image upload with progress and colour change:
    const profileImageFile = document.getElementById('profileImageFile');
    profileImageFile.addEventListener('change', function() {
      const file = this.files[0];
      if (!file) return;
      
      const formData = new FormData(document.getElementById('updateImageForm'));
      const progressBar = document.querySelector('.progress-bar');
      
      const xhr = new XMLHttpRequest();
      xhr.open('POST', 'manage_profile', true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      
      xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
          const percent = e.loaded / e.total;
          const offset = 301.59 * (1 - percent);
          progressBar.style.strokeDashoffset = offset;
          // Set upload colour to green during upload
          progressBar.style.stroke = 'green';
        }
      });
      
      xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE) {
          // Reset progress ring and ring colour to blue (primary colour)
          progressBar.style.strokeDashoffset = '301.59';
          progressBar.style.stroke = getComputedStyle(document.documentElement)
                                     .getPropertyValue('--primary-color').trim();
          if (xhr.status === 200) {
            try {
              const response = JSON.parse(xhr.responseText);
              if (response.success) {
                document.getElementById('profileImage').src = response.imageUrl;
                document.querySelector('.sidebar-user-profile img').src = response.imageUrl;
                showAlert('success', response.message);
              } else {
                showAlert('error', response.message);
              }
            } catch (e) {
              showAlert('error', 'Error processing response');
            }
          } else {
            showAlert('error', 'Upload failed. Please try again.');
          }
        }
      };
      
      xhr.send(formData);
    });
    
    // Display any alerts from PHP (if any)
    <?php if (!empty($alertMessage)): ?>
      showAlert('<?php echo $alertType; ?>', '<?php echo addslashes($alertMessage); ?>');
    <?php endif; ?>
  </script>
</body>
</html>
