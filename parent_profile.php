<?php
session_start();
// Check if the user is logged in and allowed (Admin or SuperAdmin)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: signin.php');
    exit;
}
$allowed_roles = ['Admin', 'SuperAdmin', 'Doctor'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    header('Location: unauthorized.php');
    exit;
}

// Database connection details
$dsn         = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user     = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Delete Parent
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM parent WHERE parent_id = :parent_id");
        $stmt->execute([':parent_id' => $_POST['delete_id']]);
        echo json_encode(["status" => "success"]);
    } catch(PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// Update Parent via Modal
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['parent_id']) && isset($_POST['mother_name'])) {
    $parent_id      = $_POST['parent_id'];
    $mother_name    = trim($_POST['mother_name']);
    $nic            = trim($_POST['nic']);
    $dob            = $_POST['dob'];
    $address        = trim($_POST['address']);
    $contact_number = trim($_POST['contact_number']);
    try {
        $stmt = $pdo->prepare("UPDATE parent 
                               SET mother_name = :mother_name, 
                                   nic = :nic, 
                                   dob = :dob, 
                                   address = :address, 
                                   contact_number = :contact_number 
                               WHERE parent_id = :parent_id");
        $stmt->execute([
            ':mother_name'    => $mother_name,
            ':nic'            => $nic,
            ':dob'            => $dob,
            ':address'        => $address,
            ':contact_number' => $contact_number,
            ':parent_id'      => $parent_id
        ]);
        echo json_encode(["status" => "success"]);
    } catch(PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// Add Parent
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mother_name']) && !isset($_POST['parent_id'])) {
    $mother_name    = trim($_POST['mother_name']);
    $nic            = trim($_POST['nic']);
    $dob            = $_POST['dob'];
    $address        = trim($_POST['address']);
    $contact_number = trim($_POST['contact_number']);
    try {
        $stmt = $pdo->prepare("INSERT INTO parent (mother_name, nic, dob, address, contact_number) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$mother_name, $nic, $dob, $address, $contact_number]);
        echo json_encode(["status" => "success", "message" => "Parent record added successfully."]);
    } catch(PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Error adding parent record: " . $e->getMessage()]);
    }
    exit;
}

// AJAX Search Request
if (isset($_GET['ajax']) && $_GET['ajax'] == 'true') {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $query = "SELECT * FROM parent";
    $params = [];
    if (!empty($search)) {
        $query .= " WHERE mother_name ILIKE :search OR nic ILIKE :search";
        $params[':search'] = "%$search%";
    }
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $parentData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($parentData);
    exit;
}

try {
    $stmt = $pdo->query("SELECT * FROM parent");
    $parentData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Error fetching parent records: " . $e->getMessage());
}

// Retrieve user info for the sidebar
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Sarah Smith';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Doctor';

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


?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>KidsGrow - Parent Profiles</title>
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <!-- Using Poppins font as in child_profile.php -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
  <style>
    :root {
      --primary-color: #274FB4;
      --secondary-color: #8FC4F1;
      --text-dark: #333;
      --text-light: #666;
      --white: #fff;
      --border-radius: 8px;
      --shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }
    body {
      background-color: #f0f0f0;
      margin: 0;
      display: flex;
    }
    /* Sidebar */
    .sidebar {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: 220px;
      background: #274FB4;
      color: white;
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
    .sidebar .menu-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 0;
      cursor: pointer;
      color: white;
      text-decoration: none;
      font-size: 16px;
      font-weight: 700;
    }
    .sidebar .menu-item:hover {
      background-color: rgba(255, 255, 255, 0.2);
      padding-left: 10px;
      border-radius: var(--border-radius);
    }
    /* User Profile at Bottom */
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
      color: #fff;
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
      flex: 1;
      padding: 20px;
    }
    .search-bar {
      position: sticky;
      top: 0;
      z-index: 100;
      background-color: #f0f0f0;
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
    .add-child-btn {
      background-color: #1a47b8;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
    }
    /* Parent Profiles Box (same style as child-profiles) */
    .child-profiles {
      background: white;
      border-radius: 10px;
      overflow: hidden;
    }
    /* Sticky header for the table box */
    .child-profiles .sticky-header {
      position: sticky;
      top: 0;
      z-index: 10;
      background: #fff;
      padding: 20px;
      border-bottom: 1px solid #ddd;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .child-profiles .table-container {
      max-height: 600px;
      overflow-y: auto;
      overflow-x: auto;
      padding: 20px;
    }
    .child-profiles table {
      width: 100%;
      border-collapse: collapse;
      table-layout: auto;
    }
    .child-profiles th, 
    .child-profiles td {
      padding: 16px 20px;
      text-align: center;
      font-size: 14px;
      white-space: nowrap;
    }
    .child-profiles th {
      color: #666;
      font-weight: 600;
    }
    .action-icons {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
    }
    .delete-icon {
      color: #ff4444 !important;
    }
    .default-hidden {
      display: none;
    }
    /* Modal & Overlay Styles */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.5);
      backdrop-filter: blur(5px);
      z-index: 999;
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
      width: 500px;
      max-width: 90%;
      max-height: 90vh;
      overflow-y: auto;
      z-index: 1000;
      display: none;
    }
    /* Add/Update Parent Modal Styles */
    .form-group {
      margin-bottom: 15px;
    }
    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: bold;
      font-size: 14px;
      color: var(--text-dark);
    }
    .form-group input {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 14px;
    }
    .button-group {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 20px;
    }
    .btn {
      padding: 8px 20px;
      border-radius: 5px;
      border: none;
      cursor: pointer;
      font-size: 14px;
    }
    .btn-primary {
      background: var(--primary-color);
      color: var(--white);
    }
    .btn-secondary {
      background: var(--white);
      border: 1px solid var(--primary-color);
      color: var(--primary-color);
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
    .alert-error { background: #dc3545; }
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
  </style>
</head>
<body>
  <!-- Sidebar -->
  <div class="sidebar">
      <div>
          <!-- Logo & Navigation -->
          <div class="logo">
              <i class="fas fa-child" style="font-size: 24px;"></i>
              <span>KidsGrow</span>
          </div>
          <a href="dashboard.php" class="menu-item">
              <i class="fas fa-th-large"></i>
              <span>Dashboard</span>
          </a>
          <a href="child_profile.php" class="menu-item">
              <i class="fas fa-child"></i>
              <span>Child Profiles</span>
          </a>
          <a href="parent_profile.php" class="menu-item">
              <i class="fas fa-users"></i>
              <span>Parent Profiles</span>
          </a>
          <!-- New Links -->
          <a href="upcoming_vaccination.php" class="menu-item">
              <i class="fas fa-syringe"></i>
              <span>Vaccination</span>
          </a>
          <a href="home_visit.php" class="menu-item">
              <i class="fas fa-home"></i>
              <span>Home Visit</span>
          </a>
          <a href="thriposha_distribution.php" class="menu-item">
              <i class="fas fa-box"></i>
              <span>Thriposha Distribution</span>
          </a>
          <a href="growth_details.php" class="menu-item">
              <i class="fas fa-chart-line"></i>
              <span>Growth Details</span>
          </a>
          <?php if ($_SESSION['user_role'] === 'SuperAdmin'): ?>
          <a href="add_admin.php" class="menu-item">
              <i class="fas fa-user-shield"></i>
              <span>Add Admin</span>
          </a>
          <?php endif; ?>
      </div>
      <!-- User Profile at Bottom -->
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

  <!-- Main Content Area -->
  <div class="main-content">
      <!-- Search Bar -->
      <div class="search-bar">
          <div class="search-container">
              <input type="text" id="searchInput" placeholder="Search by name or NIC...">
              <i class="fas fa-search search-icon"></i>
          </div>
          <!-- Add Parent Button -->
          <button class="add-child-btn" id="openAddParentBtn">
              <i class="fas fa-plus"></i> Add Parent
          </button>
      </div>

      <!-- Parent Profiles Box (matching child_profile style) -->
      <div class="child-profiles">
          <!-- Sticky Header with Title -->
          <div class="sticky-header">
              <h2>Parent Profiles</h2>
          </div>
          <!-- Scrollable Table Container -->
          <div class="table-container">
              <table>
                  <thead>
                      <tr>
                          <th>Parent ID</th>
                          <th>Mother Name</th>
                          <th>NIC</th>
                          <th>Date of Birth</th>
                          <th>Address</th>
                          <th>Contact Number</th>
                          <th>Actions</th>
                      </tr>
                  </thead>
                  <tbody id="tableBody">
                      <?php foreach ($parentData as $parent): ?>
                      <tr data-id="<?php echo htmlspecialchars($parent['parent_id']); ?>">
                          <td><?php echo htmlspecialchars($parent['parent_id']); ?></td>
                          <td><?php echo htmlspecialchars($parent['mother_name']); ?></td>
                          <td><?php echo htmlspecialchars($parent['nic']); ?></td>
                          <td><?php echo htmlspecialchars($parent['dob']); ?></td>
                          <td><?php echo htmlspecialchars($parent['address']); ?></td>
                          <td><?php echo htmlspecialchars($parent['contact_number']); ?></td>
                          <td class="action-icons">
                              <i class="fas fa-eye view-btn"></i>
                              <i class="fas fa-edit update-btn"></i>
                              <i class="fas fa-trash delete-icon delete-btn" data-id="<?php echo htmlspecialchars($parent['parent_id']); ?>"></i>
                          </td>
                      </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
          </div>
      </div>
  </div>

  <!-- Add Parent Modal -->
  <div class="modal-overlay" id="addParentModalOverlay"></div>
  <div class="modal" id="addParentModal">
      <h2>Add Parent</h2>
      <form id="addParentForm">
          <div class="form-group">
              <label>Mother Name</label>
              <input type="text" name="mother_name" required>
          </div>
          <div class="form-group">
              <label>NIC</label>
              <input type="text" name="nic" required>
          </div>
          <div class="form-group">
              <label>Date of Birth</label>
              <input type="date" name="dob" required>
          </div>
          <div class="form-group">
              <label>Address</label>
              <input type="text" name="address" required>
          </div>
          <div class="form-group">
              <label>Contact Number</label>
              <input type="text" name="contact_number" required>
          </div>
          <div class="button-group">
              <button type="button" class="btn btn-secondary" id="cancelAddParent">Cancel</button>
              <button type="submit" class="btn btn-primary">Save</button>
          </div>
      </form>
  </div>

  <!-- Update Parent Modal -->
  <div class="modal-overlay" id="updateParentModalOverlay"></div>
  <div class="modal" id="updateParentModal">
      <h2>Update Parent</h2>
      <form id="updateParentForm">
          <input type="hidden" id="updateParentId">
          <div class="form-group">
              <label>Mother Name</label>
              <input type="text" id="updateMotherName" name="mother_name" required>
          </div>
          <div class="form-group">
              <label>NIC</label>
              <input type="text" id="updateNIC" name="nic" required>
          </div>
          <div class="form-group">
              <label>Date of Birth</label>
              <input type="date" id="updateDOB" name="dob" required>
          </div>
          <div class="form-group">
              <label>Address</label>
              <input type="text" id="updateAddress" name="address" required>
          </div>
          <div class="form-group">
              <label>Contact Number</label>
              <input type="text" id="updateContactNumber" name="contact_number" required>
          </div>
          <div class="button-group">
              <button type="button" class="btn btn-secondary" id="cancelUpdateParent">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
      </form>
  </div>

  <!-- View Parent Modal -->
  <div class="modal-overlay" id="viewParentModalOverlay"></div>
  <div class="modal" id="viewParentModal">
      <h2>View Parent</h2>
      <div class="form-group">
          <label>Parent ID</label>
          <input type="text" id="viewParentId" readonly>
      </div>
      <div class="form-group">
          <label>Mother Name</label>
          <input type="text" id="viewMotherName" readonly>
      </div>
      <div class="form-group">
          <label>NIC</label>
          <input type="text" id="viewNIC" readonly>
      </div>
      <div class="form-group">
          <label>Date of Birth</label>
          <input type="date" id="viewDOB" readonly>
      </div>
      <div class="form-group">
          <label>Address</label>
          <input type="text" id="viewAddress" readonly>
      </div>
      <div class="form-group">
          <label>Contact Number</label>
          <input type="text" id="viewContactNumber" readonly>
      </div>
      <div class="button-group">
          <button type="button" class="btn btn-secondary" id="closeViewParent">Close</button>
      </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal-overlay" id="deleteConfirmModalOverlay"></div>
  <div class="modal" id="deleteConfirmModal">
      <h2>Confirm Delete</h2>
      <p>Are you sure you want to delete this record?</p>
      <div class="button-group">
           <button type="button" class="btn btn-secondary" id="cancelDelete">Cancel</button>
           <button type="button" class="btn btn-primary" id="confirmDelete">Delete</button>
      </div>
  </div>

  <!-- Alert Box -->
  <div class="alert" id="alertBox"></div>

  <!-- jQuery and Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    // Toggle user menu in the sidebar
    function toggleSidebarUserMenu() {
      const menu = document.getElementById('sidebarUserMenu');
      menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }
    $(document).ready(function(){
      // Sidebar user menu toggle
      $('#sidebarUserProfile').on('click', function(e){
        e.stopPropagation();
        toggleSidebarUserMenu();
      });
      $(document).on('click', function(){
        $('#sidebarUserMenu').hide();
      });
      
      // AJAX Search Functionality
      let ajaxRequest = null, searchTimeout = null;
      $("#searchInput").on("input", function(){
          let query = $(this).val().trim();
          clearTimeout(searchTimeout);
          if(ajaxRequest){ ajaxRequest.abort(); }
          searchTimeout = setTimeout(() => {
              ajaxRequest = $.ajax({
                  url: window.location.pathname,
                  method: "GET",
                  data: { search: query, ajax: "true" },
                  dataType: "json",
                  success: function(data){ updateTable(data); },
                  error: function(xhr, status, error){
                      if(status !== "abort"){
                          console.error("Error fetching search results:", error);
                      }
                  }
              });
          }, 300);
      });
      function updateTable(data){
          let tableBody = $("#tableBody");
          tableBody.empty();
          if(data.length === 0){
              tableBody.append("<tr><td colspan='7'>No records found</td></tr>");
              return;
          }
          data.forEach(function(parent){
              let row = `<tr data-id="${parent.parent_id}">
                  <td>${parent.parent_id}</td>
                  <td>${parent.mother_name}</td>
                  <td>${parent.nic}</td>
                  <td>${parent.dob}</td>
                  <td>${parent.address}</td>
                  <td>${parent.contact_number}</td>
                  <td class="action-icons">
                      <i class="fas fa-eye view-btn"></i>
                      <i class="fas fa-edit update-btn"></i>
                      <i class="fas fa-trash delete-icon delete-btn" data-id="${parent.parent_id}"></i>
                  </td>
              </tr>`;
              $("#tableBody").append(row);
          });
      }
      
      // Add Parent Modal
      $("#openAddParentBtn").click(function(){
          $("#addParentModalOverlay, #addParentModal").fadeIn();
      });
      $("#cancelAddParent").click(function(){
          $("#addParentModalOverlay, #addParentModal").fadeOut();
      });
      $("#addParentForm").submit(function(e){
          e.preventDefault();
          let formData = $(this).serialize();
          $.ajax({
              url: window.location.pathname,
              method: "POST",
              data: formData,
              dataType: "json",
              success: function(response){
                  if(response.status === "success"){
                      showAlert("success", response.message);
                      setTimeout(() => location.reload(), 1500);
                  } else {
                      showAlert("error", response.message);
                  }
                  $("#addParentModalOverlay, #addParentModal").fadeOut();
              },
              error: function(xhr, status, error){
                  console.error("Error adding parent:", error);
              }
          });
      });
      
      // Update Parent Modal
      $(document).on("click", ".update-btn", function(){
          let row = $(this).closest("tr");
          let parentId = row.data("id");
          let motherName = row.children().eq(1).text().trim();
          let nic = row.children().eq(2).text().trim();
          let dob = row.children().eq(3).text().trim();
          let address = row.children().eq(4).text().trim();
          let contactNumber = row.children().eq(5).text().trim();
          $("#updateParentId").val(parentId);
          $("#updateMotherName").val(motherName);
          $("#updateNIC").val(nic);
          $("#updateDOB").val(dob);
          $("#updateAddress").val(address);
          $("#updateContactNumber").val(contactNumber);
          $("#updateParentModalOverlay, #updateParentModal").fadeIn();
      });
      $("#cancelUpdateParent").click(function(){
          $("#updateParentModalOverlay, #updateParentModal").fadeOut();
      });
      $("#updateParentForm").submit(function(e){
          e.preventDefault();
          let parentId = $("#updateParentId").val();
          let motherName = $("#updateMotherName").val();
          let nic = $("#updateNIC").val();
          let dob = $("#updateDOB").val();
          let address = $("#updateAddress").val();
          let contactNumber = $("#updateContactNumber").val();
          $.post("", {
              parent_id: parentId,
              mother_name: motherName,
              nic: nic,
              dob: dob,
              address: address,
              contact_number: contactNumber
          }, function(response){
              let res = (typeof response === "string") ? JSON.parse(response) : response;
              if(res.status === "success"){
                  showAlert("success", "Record updated successfully!");
                  setTimeout(() => location.reload(), 1500);
              } else {
                  showAlert("error", res.message || "Error updating record.");
              }
          }, "json");
          $("#updateParentModalOverlay, #updateParentModal").fadeOut();
      });
      
      // View Parent Modal
      $(document).on("click", ".view-btn", function(){
          let row = $(this).closest("tr");
          let parentId = row.data("id");
          let motherName = row.children().eq(1).text().trim();
          let nic = row.children().eq(2).text().trim();
          let dob = row.children().eq(3).text().trim();
          let address = row.children().eq(4).text().trim();
          let contactNumber = row.children().eq(5).text().trim();
          $("#viewParentId").val(parentId);
          $("#viewMotherName").val(motherName);
          $("#viewNIC").val(nic);
          $("#viewDOB").val(dob);
          $("#viewAddress").val(address);
          $("#viewContactNumber").val(contactNumber);
          $("#viewParentModalOverlay, #viewParentModal").fadeIn();
      });
      $("#closeViewParent").click(function(){
          $("#viewParentModalOverlay, #viewParentModal").fadeOut();
      });
      
      // Delete Parent Modal
      $(document).on("click", ".delete-btn", function(){
          let parentId = $(this).data("id");
          $("#deleteConfirmModal").data("parentId", parentId);
          $("#deleteConfirmModalOverlay, #deleteConfirmModal").fadeIn();
      });
      $("#cancelDelete").click(function(){
          $("#deleteConfirmModalOverlay, #deleteConfirmModal").fadeOut();
      });
      $("#confirmDelete").click(function(){
          let parentId = $("#deleteConfirmModal").data("parentId");
          $.post("", { delete_id: parentId }, function(response){
              let res = (typeof response === "string") ? JSON.parse(response) : response;
              if(res.status === "success"){
                  showAlert("success", "Record deleted successfully!");
                  setTimeout(() => location.reload(), 1500);
              } else {
                  showAlert("error", res.message || "Error deleting record.");
              }
              $("#deleteConfirmModalOverlay, #deleteConfirmModal").fadeOut();
          }, "json");
      });
      
      // Alert Function
      function showAlert(type, message){
          let alertBox = $("#alertBox");
          alertBox.removeClass("alert-success alert-error")
                  .addClass(type === "success" ? "alert-success" : "alert-error")
                  .text(message)
                  .fadeIn();
          setTimeout(() => alertBox.fadeOut(), 3000);
      }
    });
    
    // Additional safety to hide user menu if clicked outside
    document.addEventListener('click', function(e) {
      const userProfile = document.getElementById('sidebarUserProfile');
      const userMenu = document.getElementById('sidebarUserMenu');
      if (!userProfile.contains(e.target)) {
        userMenu.style.display = 'none';
      }
    });
  </script>
</body>
</html>
