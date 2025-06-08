<?php
session_start();

// Check if the user is logged in and allowed (Admin or SuperAdmin)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: signin.php');
    exit;
}
$allowed_roles = ['Admin', 'SuperAdmin'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    header('Location: unauthorized.php');
    exit;
}

$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Sarah Smith';
$userRole = $_SESSION['user_role'];

// ================== DATABASE CONNECTION ==================
$dsn         = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user     = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ================== DELETE USER ==================
// Only delete if user role is Doctor, Midwife, or Parent
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    $id = $_POST['delete_id'];
    try {
        $stmt = $pdo->prepare("
            DELETE FROM users 
            WHERE id = :id 
              AND role IN ('Doctor','Midwife','Parent')
        ");
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "message" => "User not found or cannot be deleted."]);
        }
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// ================== UPDATE IMAGE ==================
// To update an existing user’s profile image
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_image']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    // Get current image delete URL from DB
    $stmt = $pdo->prepare("SELECT profile_image_delete_url, email FROM users WHERE id = :id");
    $stmt->execute([':id' => $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $old_delete_url = $user['profile_image_delete_url'];
    $email = $user['email'];
    // Delete old image if exists
    if (!empty($old_delete_url)) {
        @file_get_contents($old_delete_url);
    }
    // Process new image upload if file is provided
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $image_content = file_get_contents($_FILES['profile_image']['tmp_name']);
        $encoded_image = base64_encode($image_content);
        // Use email to create a unique image name (replace @ and .)
        $image_name = str_replace(['@', '.'], '_', $email);
        $api_key = "6114e595f0f58654aca55291325e135e";
        $upload_url = "https://api.imgbb.com/1/upload?key={$api_key}&name={$image_name}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $upload_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => $encoded_image]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        if ($data['success']) {
            $new_image_url = $data['data']['url'];
            $new_delete_url = $data['data']['delete_url'];
            // Update DB with new image info
            $stmt = $pdo->prepare("UPDATE users SET profile_image_url = :url, profile_image_delete_url = :delete_url WHERE id = :id");
            $stmt->execute([
                ':url' => $new_image_url,
                ':delete_url' => $new_delete_url,
                ':id' => $user_id
            ]);
            echo json_encode(["status" => "success", "message" => "Profile image updated successfully!"]);
        } else {
            echo json_encode(["status" => "error", "message" => "Image upload failed."]);
        }
    } else {
        echo json_encode(["status" => "error", "message" => "No image file uploaded."]);
    }
    exit;
}

// ================== ADD USER ==================
// Only add if role is Doctor, Midwife, or Parent (and not updating image)
if ($_SERVER["REQUEST_METHOD"] === "POST" 
    && isset($_POST['email']) 
    && isset($_POST['name']) 
    && isset($_POST['password']) 
    && isset($_POST['userType'])
    && !isset($_POST['update_image'])
) {
    $full_name = trim($_POST['name']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];
    $role      = $_POST['userType'];

    // Ensure it's one of Doctor, Midwife, Parent
    $allowed_roles = ['Doctor','Midwife','Parent'];
    if (!in_array($role, $allowed_roles)) {
        echo json_encode(["status" => "error", "message" => "Invalid role selected."]);
        exit;
    }

    if (empty($full_name) || empty($email) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "All fields are required!"]);
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Process profile image upload if provided
    $profile_image_url = null;
    $profile_image_delete_url = null;
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $image_content = file_get_contents($_FILES['profile_image']['tmp_name']);
        $encoded_image = base64_encode($image_content);
        // Use email as naming convention (replace @ and .)
        $image_name = str_replace(['@', '.'], '_', $email);
        $api_key = "6114e595f0f58654aca55291325e135e";
        $upload_url = "https://api.imgbb.com/1/upload?key={$api_key}&name={$image_name}";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $upload_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['image' => $encoded_image]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        if ($data['success']) {
            $profile_image_url = $data['data']['url'];
            $profile_image_delete_url = $data['data']['delete_url'];
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (full_name, email, password, role, profile_image_url, profile_image_delete_url)
            VALUES (:full_name, :email, :password, :role, :profile_image_url, :profile_image_delete_url)
        ");
        $stmt->execute([
            ':full_name' => $full_name,
            ':email'     => $email,
            ':password'  => $hashed_password,
            ':role'      => $role,
            ':profile_image_url' => $profile_image_url,
            ':profile_image_delete_url' => $profile_image_delete_url
        ]);
        echo json_encode(["status" => "success", "message" => "User added successfully!"]);
    } catch(PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Error adding user: " . $e->getMessage()]);
    }
    exit;
}

// Default placeholder image
$defaultImage = "https://placehold.co/150x150";

// Retrieve profile image from database
$stmt = $pdo->prepare("SELECT profile_image_url FROM users WHERE id = :id");
$stmt->execute([':id' => $_SESSION['user_id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if user has an uploaded profile image
$profileImage = !empty($result['profile_image_url']) ? $result['profile_image_url'] : $defaultImage;

// Apply ImgBB Parameters for Optimization (Resize, Quality & Sharpening)
$optimizedImage = $profileImage . "?w=90&h=90&quality=95&sharpness=2";


// ================== AJAX SEARCH ==================
// Return only Doctor, Midwife, Parent
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $query  = "SELECT id, full_name, email, role, profile_image_url FROM users WHERE role IN ('Doctor','Midwife','Parent')";
    $params = [];
    if (!empty($search)) {
        $query .= " AND (full_name ILIKE :search OR email ILIKE :search)";
        $params[':search'] = "%$search%";
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $userData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($userData);
    exit;
}

// ================== FETCH USERS (DEFAULT) ==================
try {
    $stmt = $pdo->query("
        SELECT id, full_name, email, role, profile_image_url 
        FROM users
        WHERE role IN ('Doctor','Midwife','Parent')
        ORDER BY id ASC
    ");
    $userData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching user records: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>KidsGrow - User Management</title>
  <!-- Use Poppins font -->
  <link rel="preconnect" href="https://fonts.gstatic.com" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
  <style>
    :root {
      --primary-color: #274FB4;
      --secondary-color: #8FC4F1;
      --text-dark: #333;
      --text-light: #666;
      --white: #fff;
      --gray-bg: #f0f0f0;
      --border-radius: 8px;
      --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      --danger-color: #ff4444;
      --success-color: #28a745;
    }
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }
    body {
      background-color: var(--gray-bg);
      margin: 0;
    }
    /* Fixed Sidebar */
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
    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 40px;
    }
    .logo img {
      width: 42px;
      height: 44px;
      object-fit: cover;
    }
    .logo span {
      font-size: 24px;
      font-weight: 800;
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
    .menu-item:hover,
    .menu-item.active {
      background-color: rgba(255, 255, 255, 0.2);
      padding-left: 10px;
      border-radius: var(--border-radius);
    }
    /* Sidebar User Profile */
    .sidebar-user-profile {
      position: relative;
      display: flex;
      align-items: center;
      gap: 12px;
      margin-top: 40px;
      padding: 10px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: var(--border-radius);
      cursor: pointer;
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
    /* Main Content */
    .main-content {
      margin-left: 220px;
      height: 100vh;
      overflow-y: auto;
      padding: 0 20px 20px 20px;
      position: relative;
    }
    /* Search Bar */
    .search-bar {
      position: sticky;
      top: 0;
      z-index: 100;
      background-color: var(--gray-bg);
      padding: 20px 0 10px 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 10px;
    }
    .search-container {
      position: relative;
      width: 400px;
    }
    .search-container input {
      width: 100%;
      padding: 10px 40px 10px 10px;
      border-radius: 5px;
      border: 1px solid #ddd;
    }
    .search-container .search-icon {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      color: #4a90e2;
    }
    .btn {
      padding: 10px 24px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
      min-width: 120px;
      border: none;
      transition: all 0.3s ease;
    }
    .btn-primary {
      background: var(--primary-color);
      color: #fff;
    }
    .btn-primary:hover {
      background: #1a3a8a;
    }
    /* User Profiles (Table) Container */
    .user-profiles {
      background: var(--white);
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: 20px;
    }
    .user-profiles .sticky-header {
      background: var(--white);
      padding: 20px;
      border-bottom: 1px solid #ddd;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .user-profiles .table-container {
      max-height: 600px;
      overflow-y: auto;
      overflow-x: auto;
      padding: 20px;
    }
    .user-profiles table {
      width: 100%;
      border-collapse: collapse;
      table-layout: auto;
    }
    .user-profiles th,
    .user-profiles td {
      padding: 16px 20px;
      text-align: left;
      font-size: 14px;
      white-space: nowrap;
    }
    .user-profiles th {
      color: #666;
      font-weight: 600;
    }
    .action-icons {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .delete-icon {
      color: var(--danger-color);
      cursor: pointer;
    }
    .update-icon {
      color: var(--secondary-color);
      cursor: pointer;
    }
    /* Profile Image Hover Effect (View Image Modal) */
    .profile-img-container {
      position: relative;
      display: inline-block;
    }
    .profile-img-container img {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      display: block;
    }
    .profile-img-container .img-overlay {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    .profile-img-container:hover .img-overlay {
      opacity: 1;
    }
    .profile-img-container .img-overlay i {
      color: #fff;
      font-size: 18px;
    }
    /* Modals & Overlays */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.5);
      backdrop-filter: blur(5px);
      z-index: 1000;
      display: none;
    }
    .modal {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      background: var(--white);
      padding: 2rem;
      border-radius: var(--border-radius);
      box-shadow: var(--shadow);
      width: 450px;
      max-width: 80%;
      z-index: 1001;
      display: none;
    }
    .modal h2 {
      margin-bottom: 1.5rem;
      color: var(--primary-color);
    }
    .form-group {
      margin-bottom: 1rem;
    }
    .form-group label {
      display: block;
      margin-bottom: 0.5rem;
      color: var(--text-dark);
    }
    .form-control {
      width: 100%;
      padding: 0.8rem;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
    }
    .modal-actions {
      margin-top: 2rem;
      display: flex;
      gap: 1rem;
      justify-content: flex-end;
    }
    .file-input-wrapper {
      position: relative;
      overflow: hidden;
      display: inline-block;
    }
    .file-input-button {
      border: 2px solid var(--primary-color);
      color: var(--primary-color);
      background-color: #fff;
      padding: 8px 16px;
      border-radius: var(--border-radius);
      cursor: pointer;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }
    .file-input-button i {
      font-size: 18px;
    }
    .file-input {
      font-size: 100px;
      position: absolute;
      left: 0;
      top: 0;
      opacity: 0;
      cursor: pointer;
    }
    /* Alert Box */
    .alert {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 25px;
      border-radius: var(--border-radius);
      color: #fff;
      font-size: 14px;
      font-weight: 500;
      z-index: 1100;
      display: none;
      animation: slideIn 0.3s ease-out;
    }
    .alert-success { background: #28a745; }
    .alert-error { background: #ff4444; }
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
    /* Password Form */
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
  </style>
</head>
<body>
  <!-- SIDEBAR -->
  <div class="sidebar">
    <div>
      <div class="logo">
        <i class="fas fa-child" style="font-size:24px;"></i>
        <span>KidsGrow</span>
      </div>
      <a href="dashboard.php" class="menu-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
      <a href="child_profile.php" class="menu-item"><i class="fas fa-child"></i><span>Child Profiles</span></a>
      <a href="parent_profile.php" class="menu-item"><i class="fas fa-users"></i><span>Parent Profiles</span></a>
      <a href="#" class="menu-item"><i class="fas fa-syringe"></i><span>Vaccination</span></a>
      <a href="home_visit.php" class="menu-item"><i class="fas fa-home"></i><span>Home Visit</span></a>
      <a href="thriposha_distribution.php" class="menu-item"><i class="fas fa-box"></i><span>Thriposha Distribution</span></a>
      <a href="growth_details.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Growth Details</span></a>
      <a href="user_management.php" class="menu-item"><i class="fas fa-user"></i><span>User Management</span></a>
    </div>
    <!-- Sidebar User Profile with toggleable Sign Out menu -->
    <div class="sidebar-user-profile" id="sidebarUserProfile">
      <img src="<?php echo htmlspecialchars($optimizedImage); ?>" alt="User Profile" />
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

  <!-- MAIN CONTENT -->
  <div class="main-content">
    <!-- SEARCH BAR -->
    <div class="search-bar">
      <div class="search-container">
        <input type="text" placeholder="Search by name or email..." id="searchInput">
        <i class="fas fa-search search-icon"></i>
      </div>
      <button class="btn btn-primary" id="addBtn">+ Add User</button>
    </div>

    <!-- User Profiles Table -->
    <div class="user-profiles">
      <div class="sticky-header">
        <h2>User Management</h2>
      </div>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Email</th>
              <th>Name</th>
              <th>User Type</th>
              <th>Image</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="userTableBody">
            <?php if (!empty($userData)): ?>
              <?php foreach ($userData as $user): ?>
                <tr data-userid="<?= htmlspecialchars($user['id']) ?>">
                  <td><?= htmlspecialchars($user['email']) ?></td>
                  <td><?= htmlspecialchars($user['full_name']) ?></td>
                  <td><?= htmlspecialchars($user['role']) ?></td>
                  <td>
                    <?php if(!empty($user['profile_image_url'])): ?>
                      <div class="profile-img-container">
                        <img src="<?= htmlspecialchars($user['profile_image_url']) ?>" alt="Profile" style="width:50px;height:50px;border-radius:50%;">
                        <div class="img-overlay"><i class="fas fa-eye"></i></div>
                      </div>
                    <?php else: ?>
                      <span style="color:gray;">No Image</span>
                    <?php endif; ?>
                  </td>
                  <td class="action-icons">
                    <i class="fas fa-image update-icon" data-id="<?= htmlspecialchars($user['id']) ?>" title="Update Image"></i>
                    <i class="fas fa-trash delete-icon" data-id="<?= htmlspecialchars($user['id']) ?>" title="Delete User"></i>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" style="color:gray;">No users found.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ==================== MODALS FOR USER MANAGEMENT ==================== -->
  <!-- ADD USER MODAL -->
  <div class="modal-overlay" id="addUserOverlay"></div>
  <div class="modal" id="addUserModal">
    <h2>Add User</h2>
    <form id="addUserForm" enctype="multipart/form-data">
      <div class="form-columns">
        <div class="form-column">
          <div class="form-group">
            <label>Email</label>
            <input type="email" class="form-control" name="email" required>
          </div>
          <div class="form-group">
            <label>Name</label>
            <input type="text" class="form-control" name="name" required>
          </div>
          <div class="form-group">
            <label>Password</label>
            <input type="password" class="form-control" name="password" required>
          </div>
          <div class="form-group">
            <label>User Type</label>
            <select class="form-control" name="userType" required>
              <option value="Doctor">Doctor</option>
              <option value="Midwife">Midwife</option>
              <option value="Parent">Parent</option>
            </select>
          </div>
          <div class="form-group">
            <label>Profile Image</label>
            <div class="file-input-wrapper">
              <button type="button" class="file-input-button">
                <i class="fas fa-image"></i> Choose Image
              </button>
              <input type="file" class="file-input" name="profile_image" accept="image/*">
            </div>
            <div id="fileName" style="margin-top: 5px;font-size: 14px;color: #555;"></div>
          </div>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" id="cancelAddUser">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>

  <!-- UPDATE IMAGE MODAL -->
  <div class="modal-overlay" id="updateImageOverlay"></div>
  <div class="modal" id="updateImageModal">
    <h2>Update Profile Image</h2>
    <form id="updateImageForm" enctype="multipart/form-data">
      <input type="hidden" name="user_id" id="updateUserId">
      <div class="form-group">
        <label>New Profile Image</label>
        <div class="file-input-wrapper">
          <button type="button" class="file-input-button">
            <i class="fas fa-image"></i> Choose Image
          </button>
          <input type="file" class="file-input" name="profile_image" accept="image/*">
        </div>
        <div id="updateFileName" style="margin-top: 5px;font-size: 14px;color: #555;"></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-outline" id="cancelUpdateImage">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Image</button>
      </div>
      <input type="hidden" name="update_image" value="1">
    </form>
  </div>

  <!-- DELETE USER MODAL -->
  <div class="modal-overlay" id="deleteUserOverlay"></div>
  <div class="modal" id="deleteUserModal">
    <h2>Confirm Delete</h2>
    <p>Are you sure you want to delete this user?</p>
    <div class="modal-actions">
      <button type="button" class="btn btn-outline" id="cancelDeleteUser">Cancel</button>
      <button type="button" class="btn btn-danger" id="confirmDeleteUser">Delete</button>
    </div>
  </div>

  <!-- IMAGE VIEW MODAL -->
  <div class="modal-overlay" id="imageViewOverlay"></div>
  <div class="modal" id="imageViewModal" style="max-width: 500px;">
    <span id="closeImageModal" style="cursor:pointer;position:absolute;top:10px;right:15px;font-size:24px;">&times;</span>
    <img id="modalImage" src="" alt="Profile Image" style="width:100%;border-radius:10px;">
  </div>

  <!-- Alert Box -->
  <div class="alert" id="alertBox"></div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    // Toggle Sidebar User Menu (Sign Out)
    function toggleSidebarUserMenu() {
      const menu = document.getElementById('sidebarUserMenu');
      menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }
    document.getElementById('sidebarUserProfile').addEventListener('click', function(e) {
      e.stopPropagation();
      toggleSidebarUserMenu();
    });
    document.addEventListener('click', function() {
      const menu = document.getElementById('sidebarUserMenu');
      if (menu.style.display === 'block') {
        menu.style.display = 'none';
      }
    });

    // Show Alert
    function showAlert(type, message) {
      const alertBox = document.getElementById('alertBox');
      alertBox.className = 'alert ' + (type === 'success' ? 'alert-success' : 'alert-error');
      alertBox.textContent = message;
      alertBox.style.display = 'block';
      setTimeout(() => { alertBox.style.display = 'none'; }, 3000);
    }

    // Modal helper functions
    function showModal(overlayId, modalId) {
      document.getElementById(overlayId).style.display = 'block';
      document.getElementById(modalId).style.display = 'block';
    }
    function hideModal(overlayId, modalId) {
      document.getElementById(overlayId).style.display = 'none';
      document.getElementById(modalId).style.display = 'none';
    }

    // File input for "Add User" modal
    const fileInput = document.querySelector('#addUserModal .file-input');
    const fileNameDiv = document.getElementById('fileName');
    fileInput.addEventListener('change', function() {
      fileNameDiv.textContent = this.files[0] ? this.files[0].name : '';
    });
    // File input for "Update Image" modal
    const updateFileInput = document.querySelector('#updateImageModal .file-input');
    const updateFileNameDiv = document.getElementById('updateFileName');
    updateFileInput.addEventListener('change', function() {
      updateFileNameDiv.textContent = this.files[0] ? this.files[0].name : '';
    });

    // ADD USER MODAL and AJAX Submission
    const addBtn = document.getElementById('addBtn');
    const addUserOverlay = document.getElementById('addUserOverlay');
    const addUserModal = document.getElementById('addUserModal');
    const addUserForm = document.getElementById('addUserForm');
    const cancelAddUser = document.getElementById('cancelAddUser');

    addBtn.addEventListener('click', () => showModal('addUserOverlay', 'addUserModal'));
    cancelAddUser.addEventListener('click', () => hideModal('addUserOverlay', 'addUserModal'));
    document.getElementById('addUserOverlay').addEventListener('click', () => hideModal('addUserOverlay', 'addUserModal'));

    addUserForm.onsubmit = (e) => {
      e.preventDefault();
      const formData = new FormData(addUserForm);
      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          showAlert('success', data.message);
          setTimeout(() => location.reload(), 3000);
        } else {
          showAlert('error', data.message || 'Error adding user');
        }
      })
      .catch(err => {
        showAlert('error', 'Error adding user');
      })
      .finally(() => {
        hideModal('addUserOverlay', 'addUserModal');
      });
    };

    // UPDATE IMAGE MODAL and AJAX Submission
    let currentUserId = null;
    const updateImageOverlay = document.getElementById('updateImageOverlay');
    const updateImageModal = document.getElementById('updateImageModal');
    const updateImageForm = document.getElementById('updateImageForm');
    const cancelUpdateImage = document.getElementById('cancelUpdateImage');
    // Open update modal when update icon is clicked
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('update-icon')) {
        currentUserId = e.target.getAttribute('data-id');
        document.getElementById('updateUserId').value = currentUserId;
        showModal('updateImageOverlay', 'updateImageModal');
      }
    });
    cancelUpdateImage.addEventListener('click', () => hideModal('updateImageOverlay', 'updateImageModal'));
    updateImageOverlay.addEventListener('click', () => hideModal('updateImageOverlay', 'updateImageModal'));

    updateImageForm.onsubmit = (e) => {
      e.preventDefault();
      const formData = new FormData(updateImageForm);
      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          showAlert('success', data.message);
          setTimeout(() => location.reload(), 3000);
        } else {
          showAlert('error', data.message || 'Error updating image');
        }
      })
      .catch(err => {
        showAlert('error', 'Error updating image');
      })
      .finally(() => {
        hideModal('updateImageOverlay', 'updateImageModal');
      });
    };

    // DELETE USER (AJAX)
    let userIdToDelete = null;
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('delete-icon')) {
        userIdToDelete = e.target.getAttribute('data-id');
        showModal('deleteUserOverlay', 'deleteUserModal');
      }
    });
    document.getElementById('confirmDeleteUser').onclick = () => {
      if (!userIdToDelete) return;
      const formData = new FormData();
      formData.append('delete_id', userIdToDelete);
      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.status === 'success') {
          showAlert('success', 'User deleted successfully!');
          location.reload();
        } else {
          showAlert('error', data.message || 'Error deleting user');
        }
      })
      .catch(err => {
        showAlert('error', 'Error deleting user');
      })
      .finally(() => {
        userIdToDelete = null;
        hideModal('deleteUserOverlay', 'deleteUserModal');
      });
    };

    // SEARCH (AJAX)
    const searchInput = document.getElementById('searchInput');
    let searchTimer = null;
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimer);
      const query = searchInput.value.trim();
      searchTimer = setTimeout(() => {
        const url = new URL(window.location.href);
        url.searchParams.set('ajax', 'true');
        url.searchParams.set('search', query);
        fetch(url)
        .then(res => res.json())
        .then(data => {
          updateTable(data);
        })
        .catch(err => {
          showAlert('error', 'Error fetching search results');
        });
      }, 300);
    });

    function updateTable(users) {
      const tableBody = document.getElementById('userTableBody');
      tableBody.innerHTML = '';
      if (!users || users.length === 0) {
        tableBody.innerHTML = '<tr><td colspan="5" style="color:gray;">No users found.</td></tr>';
        return;
      }
      users.forEach(user => {
        const row = document.createElement('tr');
        row.setAttribute('data-userid', user.id);
        row.innerHTML = `
          <td>${user.email}</td>
          <td>${user.full_name}</td>
          <td>${user.role}</td>
          <td>${user.profile_image_url ? `<div class="profile-img-container"><img src="${user.profile_image_url}" alt="Profile" style="width:50px;height:50px;border-radius:50%;"><div class="img-overlay"><i class="fas fa-eye"></i></div></div>` : '<span style="color:gray;">No Image</span>'}</td>
          <td class="action-icons">
            <i class="fas fa-image update-icon" data-id="${user.id}" title="Update Image"></i>
            <i class="fas fa-trash delete-icon" data-id="${user.id}" title="Delete User"></i>
          </td>
        `;
        tableBody.appendChild(row);
      });
    }

    // Open "Image View" modal when clicking on a profile image in table
    document.addEventListener("click", function (event) {
      if (event.target.closest(".profile-img-container")) {
        let imageUrl = event.target.closest(".profile-img-container").querySelector("img").src;
        document.getElementById("modalImage").src = imageUrl;
        showModal("imageViewOverlay", "imageViewModal");
      }
    });
    // Close Image View modal
    document.getElementById("closeImageModal").addEventListener("click", function () {
      hideModal("imageViewOverlay", "imageViewModal");
    });
    document.getElementById("imageViewOverlay").addEventListener("click", function () {
      hideModal("imageViewOverlay", "imageViewModal");
    });
  </script>
</body>
</html>
