<?php
session_start(); // Start the session

// --------------------------------------------------------------------
// 1) Check if the user is logged in and is allowed (Admin or SuperAdmin)
// --------------------------------------------------------------------
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: signin.php');
    exit;
}
$allowed_roles = ['Admin', 'SuperAdmin'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    header('Location: unauthorized.php'); // or a page that says "Not allowed"
    exit;
}

// --------------------------------------------------------------------
// 2) Database Connection
// --------------------------------------------------------------------
$dsn         = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user     = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --------------------------------------------------------------------
// Set user name and role if available (for sidebar display)
// --------------------------------------------------------------------
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Sarah Smith';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Doctor';

// --------------------------------------------------------------------
// 3) Handle AJAX request to auto-fill Child Info (child_name, latest BMI)
// --------------------------------------------------------------------
if (isset($_GET['get_child_info']) && isset($_GET['child_id'])) {
    header('Content-Type: application/json');
    $child_id = (int) $_GET['child_id'];
    try {
        // We LEFT JOIN the latest BMI from child_growth_details
        // using a LATERAL subquery that picks the most recent measurement.
        $stmt = $pdo->prepare("
            SELECT c.name AS child_name,
                   latest_gd.bmi AS bmi
            FROM child c
            LEFT JOIN LATERAL (
                SELECT bmi
                FROM child_growth_details
                WHERE child_id = c.child_id
                ORDER BY measurement_date DESC
                LIMIT 1
            ) AS latest_gd ON true
            WHERE c.child_id = :child_id
            LIMIT 1
        ");
        $stmt->execute([':child_id' => $child_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            echo json_encode([
                'status'     => 'success',
                'child_name' => $result['child_name'] ?? '',
                'bmi'        => $result['bmi'] ?? ''
            ]);
        } else {
            echo json_encode([
                'status'  => 'error',
                'message' => 'No matching child found.'
            ]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// --------------------------------------------------------------------
// 4) Handle ADD new Child Thriposha Distribution (AJAX POST)
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $child_id           = (int) $_POST['child_id'];
    $distribution_month = trim($_POST['distribution_month']);
    $given_date         = !empty($_POST['given_date']) ? $_POST['given_date'] : null;
    $status             = trim($_POST['status']);
    $bmi                = !empty($_POST['bmi']) ? $_POST['bmi'] : null;
    $notes              = trim($_POST['notes']);

    // Basic validation
    if ($child_id <= 0 || empty($distribution_month) || empty($status)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing or invalid fields.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO thriposha_distribution_child
                (child_id, distribution_month, given_date, status, bmi, notes)
            VALUES
                (:child_id, :distribution_month, :given_date, :status, :bmi, :notes)
        ");
        $stmt->execute([
            ':child_id'           => $child_id,
            ':distribution_month' => $distribution_month,
            ':given_date'         => $given_date,
            ':status'             => $status,
            ':bmi'                => $bmi,
            ':notes'              => $notes
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Thriposha Distribution record added successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --------------------------------------------------------------------
// 5) Handle UPDATE existing Child Thriposha Distribution (AJAX POST)
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $distribution_id    = (int) $_POST['distribution_id'];
    $child_id           = (int) $_POST['child_id'];
    $distribution_month = trim($_POST['distribution_month']);
    $given_date         = !empty($_POST['given_date']) ? $_POST['given_date'] : null;
    $status             = trim($_POST['status']);
    $bmi                = !empty($_POST['bmi']) ? $_POST['bmi'] : null;
    $notes              = trim($_POST['notes']);

    if ($distribution_id <= 0 || $child_id <= 0 || empty($distribution_month) || empty($status)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing or invalid fields for update.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE thriposha_distribution_child
            SET
                child_id = :child_id,
                distribution_month = :distribution_month,
                given_date = :given_date,
                status = :status,
                bmi = :bmi,
                notes = :notes,
                updated_at = NOW()
            WHERE distribution_id = :distribution_id
        ");
        $stmt->execute([
            ':child_id'           => $child_id,
            ':distribution_month' => $distribution_month,
            ':given_date'         => $given_date,
            ':status'             => $status,
            ':bmi'                => $bmi,
            ':notes'              => $notes,
            ':distribution_id'    => $distribution_id
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Thriposha Distribution record updated successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --------------------------------------------------------------------
// 6) Handle DELETE Child Thriposha Distribution (AJAX POST)
// --------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $distribution_id = (int) $_POST['distribution_id'];
    if ($distribution_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid distribution ID.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM thriposha_distribution_child WHERE distribution_id = :distribution_id");
        $stmt->execute([':distribution_id' => $distribution_id]);
        echo json_encode(['status' => 'success', 'message' => 'Record deleted successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --------------------------------------------------------------------
// 7) Handle normal page load or AJAX fetch with search
// --------------------------------------------------------------------
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$query = "
    SELECT td.distribution_id,
           td.child_id,
           c.name AS child_name,
           td.distribution_month,
           td.given_date,
           td.status,
           td.bmi,
           td.notes
    FROM thriposha_distribution_child td
    JOIN child c ON td.child_id = c.child_id
    WHERE 1=1
";
$params = [];

if ($searchTerm !== '') {
    $query .= " AND (
        CAST(td.child_id AS TEXT) ILIKE :search
        OR c.name ILIKE :search
        OR td.distribution_month ILIKE :search
    )";
    $params[':search'] = "%$searchTerm%";
}

$query .= " ORDER BY td.distribution_id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$distributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Child Thriposha Distribution</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- Use Poppins font -->
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
  >
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"
  >

  <style>
    :root {
      --primary-color: #274FB4;
      --secondary-color: #8FC4F1;
      --text-dark: #333;
      --text-light: #666;
      --white: #fff;
      --border-radius: 8px;
      --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
    }

    /* Layout: sidebar fixed, main content scroll */
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
    .main-content {
      margin-left: 220px;
      height: 100vh;
      overflow-y: auto;
      padding: 0 20px 20px 20px;
      position: relative;
    }

    /* Sidebar top section (logo & menu) */
    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 40px;
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
      color: white;
      text-decoration: none;
      font-size: 16px;
      font-weight: 700;
    }
    .menu-item:hover {
      background-color: rgba(255, 255, 255, 0.2);
      border-radius: var(--border-radius);
      padding-left: 10px;
    }

    /* User Profile at bottom of sidebar */
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

    /* Search bar area - sticky at top of main content */
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

    /* Table container styling */
    .distribution-container {
      background: white;
      border-radius: 10px;
      overflow: hidden;
    }
    .distribution-container .sticky-header {
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
    .distribution-container .table-container {
      max-height: 600px; /* adjust as needed */
      overflow-y: auto;
      overflow-x: auto;
      padding: 20px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      table-layout: auto;
    }
    th, td {
      padding: 16px 20px;
      text-align: center;
      font-size: 14px;
      white-space: nowrap;
    }
    th {
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

    /* Modals & Overlays */
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

    .form-group {
      margin-bottom: 20px;
    }
    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: #333;
      font-size: 14px;
      font-weight: 500;
    }
    .form-control {
      width: 100%;
      padding: 8px;
      border: 1px solid #ddd;
      border-radius: 4px;
      background: #f8f8f8;
    }
    .button-group {
      margin-top: 30px;
      display: flex;
      justify-content: flex-end;
      gap: 10px;
    }
    .btn {
      padding: 8px 24px;
      border-radius: 4px;
      border: none;
      cursor: pointer;
      font-size: 14px;
      font-weight: 600;
    }
    .btn-cancel {
      background: white;
      border: 1px solid #3366cc;
      color: #3366cc;
    }
    .btn-primary {
      background: #274FB4;
      color: #fff;
      border: none;
    }
    .btn-secondary {
      background: #fff;
      color: #274FB4;
      border: 1px solid #274FB4;
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
      z-index: 1001;
      display: none;
      animation: slideIn 0.3s ease-out;
    }
    .alert-success {
      background: #28a745;
    }
    .alert-error {
      background: #dc3545;
    }
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
    <a href="vaccination.php" class="menu-item">
      <i class="fas fa-syringe"></i>
      <span>Vaccination</span>
    </a>
    <a href="home_visit.php" class="menu-item">
      <i class="fas fa-home"></i>
      <span>Home Visit</span>
    </a>
    <!-- If you have two separate pages for Thriposha Distribution (child vs parent),
         you might rename or separate them. For example: -->
    <a href="thriposha_distribution.php" class="menu-item">
      <i class="fas fa-box"></i>
      <span>Thriposha (Parent)</span>
    </a>
    <a href="child_thriposha_distribution.php" class="menu-item" 
       style="background-color: rgba(255, 255, 255, 0.2); 
              border-radius: var(--border-radius); padding-left: 15px">
      <i class="fas fa-box"></i>
      <span>Thriposha (Child)</span>
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
    <img src="https://placehold.co/45x45" alt="User">
    <div class="sidebar-user-info">
      <span class="sidebar-user-name"><?php echo htmlspecialchars($userName); ?></span>
      <span class="sidebar-user-role"><?php echo htmlspecialchars($userRole); ?></span>
    </div>
    <div class="sidebar-user-menu" id="sidebarUserMenu">
      <a href="logout.php">Sign Out</a>
    </div>
  </div>
</div>

<!-- Main Content -->
<div class="main-content">
  <!-- Top bar with search and add button -->
  <div class="search-bar">
    <div class="search-container">
      <input type="text" id="searchInput" placeholder="Search by Child ID, Name, or Month...">
      <i class="fas fa-search search-icon"></i>
    </div>
    <button class="add-child-btn" id="openAddModal">
      <i class="fas fa-plus"></i> Add Thriposha Distribution
    </button>
  </div>

  <!-- Distribution Table -->
  <div class="distribution-container">
    <div class="sticky-header">
      <h2>Child Thriposha Distribution</h2>
    </div>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Child ID</th>
            <th>Child Name</th>
            <th>Distribution Month</th>
            <th>Given Date</th>
            <th>Status</th>
            <th>BMI</th>
            <th>Notes</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="distributionTableBody">
          <?php if (count($distributions) === 0): ?>
            <tr>
              <td colspan="9">No distribution records found.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($distributions as $dist): ?>
              <tr data-id="<?php echo $dist['distribution_id']; ?>">
                <td><?php echo htmlspecialchars($dist['distribution_id']); ?></td>
                <td><?php echo htmlspecialchars($dist['child_id']); ?></td>
                <td><?php echo htmlspecialchars($dist['child_name']); ?></td>
                <td><?php echo htmlspecialchars($dist['distribution_month']); ?></td>
                <td>
                  <?php 
                    echo !empty($dist['given_date']) 
                      ? htmlspecialchars($dist['given_date']) 
                      : '-';
                  ?>
                </td>
                <td><?php echo htmlspecialchars($dist['status']); ?></td>
                <td>
                  <?php echo $dist['bmi'] !== null ? htmlspecialchars($dist['bmi']) : '-'; ?>
                </td>
                <td><?php echo htmlspecialchars($dist['notes']); ?></td>
                <td class="action-icons">
                  <i class="fas fa-edit edit-btn" style="color:#274FB4; cursor:pointer;"></i>
                  <i class="fas fa-trash delete-icon delete-btn" style="cursor:pointer;"></i>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ================== MODALS ================== -->

<!-- Add Modal -->
<div class="modal-overlay" id="addModalOverlay"></div>
<div class="modal" id="addModal">
  <h2>ADD NEW THRIPOSHA DISTRIBUTION</h2>
  <form id="addForm">
    <input type="hidden" name="action" value="add">

    <div class="form-group">
      <label for="addChildId">Child ID</label>
      <input type="number" class="form-control" id="addChildId" name="child_id" required>
    </div>

    <div class="form-group">
      <label for="addChildName">Child Name</label>
      <input type="text" class="form-control" id="addChildName" readonly>
      <!-- For display only, not submitted. -->
    </div>

    <div class="form-group">
      <label for="addDistributionMonth">Distribution Month</label>
      <select class="form-control" id="addDistributionMonth" name="distribution_month" required>
        <option value="">-- Select Month --</option>
        <option>January</option><option>February</option><option>March</option>
        <option>April</option><option>May</option><option>June</option>
        <option>July</option><option>August</option><option>September</option>
        <option>October</option><option>November</option><option>December</option>
      </select>
    </div>

    <div class="form-group">
      <label for="addGivenDate">Given Date</label>
      <input type="date" class="form-control" id="addGivenDate" name="given_date">
    </div>

    <div class="form-group">
      <label for="addStatus">Status</label>
      <select class="form-control" id="addStatus" name="status" required>
        <option value="Pending">Pending</option>
        <option value="Given">Given</option>
        <option value="Missed">Missed</option>
      </select>
    </div>

    <div class="form-group">
      <label for="addBmi">BMI</label>
      <input type="text" class="form-control" id="addBmi" name="bmi" placeholder="e.g. 18.5">
    </div>

    <div class="form-group">
      <label for="addNotes">Notes</label>
      <textarea class="form-control" id="addNotes" name="notes" rows="3"></textarea>
    </div>

    <div class="button-group">
      <button type="button" class="btn btn-cancel" id="cancelAdd">Cancel</button>
      <button type="submit" class="btn btn-primary">Save</button>
    </div>
  </form>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModalOverlay"></div>
<div class="modal" id="editModal">
  <h2>UPDATE THRIPOSHA DISTRIBUTION</h2>
  <form id="editForm">
    <input type="hidden" name="action" value="update">
    <input type="hidden" id="editDistributionId" name="distribution_id">

    <div class="form-group">
      <label for="editChildId">Child ID</label>
      <input type="number" class="form-control" id="editChildId" name="child_id" required>
    </div>

    <div class="form-group">
      <label for="editChildName">Child Name</label>
      <input type="text" class="form-control" id="editChildName" readonly>
      <!-- For display only -->
    </div>

    <div class="form-group">
      <label for="editDistributionMonth">Distribution Month</label>
      <select class="form-control" id="editDistributionMonth" name="distribution_month" required>
        <option>January</option><option>February</option><option>March</option>
        <option>April</option><option>May</option><option>June</option>
        <option>July</option><option>August</option><option>September</option>
        <option>October</option><option>November</option><option>December</option>
      </select>
    </div>

    <div class="form-group">
      <label for="editGivenDate">Given Date</label>
      <input type="date" class="form-control" id="editGivenDate" name="given_date">
    </div>

    <div class="form-group">
      <label for="editStatus">Status</label>
      <select class="form-control" id="editStatus" name="status" required>
        <option value="Pending">Pending</option>
        <option value="Given">Given</option>
        <option value="Missed">Missed</option>
      </select>
    </div>

    <div class="form-group">
      <label for="editBmi">BMI</label>
      <input type="text" class="form-control" id="editBmi" name="bmi" placeholder="e.g. 18.5">
    </div>

    <div class="form-group">
      <label for="editNotes">Notes</label>
      <textarea class="form-control" id="editNotes" name="notes" rows="3"></textarea>
    </div>

    <div class="button-group">
      <button type="button" class="btn btn-cancel" id="cancelEdit">Cancel</button>
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
  </form>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModalOverlay"></div>
<div class="modal" id="deleteModal">
  <h2>DELETE CONFIRMATION</h2>
  <p>Are you sure you want to delete this record?</p>
  <div class="button-group">
    <button type="button" class="btn btn-secondary" id="cancelDelete">Cancel</button>
    <button type="button" class="btn btn-primary" id="confirmDelete">Delete</button>
  </div>
</div>

<!-- Alert Box -->
<div class="alert" id="alertBox"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  // Toggle user menu in the bottom sidebar
  function toggleSidebarUserMenu() {
    const menu = document.getElementById('sidebarUserMenu');
    if (menu.style.display === 'block') {
      menu.style.display = 'none';
    } else {
      menu.style.display = 'block';
    }
  }

  // Show alert
  function showAlert(type, message) {
    const alertBox = $("#alertBox");
    alertBox.removeClass("alert-success alert-error");
    if (type === "success") {
      alertBox.addClass("alert-success");
    } else {
      alertBox.addClass("alert-error");
    }
    alertBox.text(message).fadeIn();
    setTimeout(() => { alertBox.fadeOut(); }, 3000);
  }

  $(document).ready(function(){
    // Toggle user menu
    $('#sidebarUserProfile').on('click', function(e){
      e.stopPropagation();
      toggleSidebarUserMenu();
    });
    $(document).on('click', function(){
      $('#sidebarUserMenu').hide();
    });

    // -------------------------------
    // 1) SEARCH Child Thriposha
    // -------------------------------
    let searchRequest = null;
    let searchTimeout = null;
    $("#searchInput").on("input", function(){
      const val = $(this).val().trim();
      clearTimeout(searchTimeout);
      if (searchRequest) {
        searchRequest.abort();
      }
      searchTimeout = setTimeout(function(){
        searchRequest = $.ajax({
          url: window.location.pathname,
          method: "GET",
          data: { search: val },
          dataType: "html",
          success: function(response) {
            // We'll parse out the new table body
            const parser = new DOMParser();
            const doc = parser.parseFromString(response, "text/html");
            const newTbody = doc.querySelector("#distributionTableBody");
            $("#distributionTableBody").html(newTbody.innerHTML);
          },
          error: function(xhr, status, error) {
            if (status !== "abort") {
              console.error("Search error:", error);
            }
          }
        });
      }, 300);
    });

    // -------------------------------
    // 2) ADD Modal
    // -------------------------------
    $("#openAddModal").click(function(){
      $("#addModalOverlay, #addModal").fadeIn();
    });
    $("#cancelAdd").click(function(){
      $("#addModalOverlay, #addModal").fadeOut();
    });

    // Auto-fill child info (name, latest BMI) when child_id changes
    $("#addChildId").on("change blur", function(){
      const childId = $(this).val().trim();
      if (childId) {
        $.ajax({
          url: window.location.pathname,
          method: "GET",
          data: { get_child_info: true, child_id: childId },
          dataType: "json",
          success: function(res) {
            if (res.status === "success") {
              $("#addChildName").val(res.child_name);
              $("#addBmi").val(res.bmi || "");
            } else {
              showAlert("error", res.message);
              $("#addChildName").val("");
              $("#addBmi").val("");
            }
          },
          error: function(xhr, status, error) {
            console.error("Auto-fill child info error:", error);
          }
        });
      }
    });

    // Submit Add Form
    $("#addForm").submit(function(e){
      e.preventDefault();
      const formData = $(this).serialize(); // includes action=add
      $.ajax({
        url: window.location.pathname,
        method: "POST",
        data: formData,
        dataType: "json",
        success: function(response) {
          if (response.status === "success") {
            showAlert("success", response.message);
            setTimeout(() => { location.reload(); }, 1200);
          } else {
            showAlert("error", response.message);
          }
        },
        error: function(xhr, status, error) {
          console.error("Add distribution error:", error);
        }
      });
      $("#addModalOverlay, #addModal").fadeOut();
    });

    // -------------------------------
    // 3) EDIT Modal
    // -------------------------------
    $(document).on("click", ".edit-btn", function(){
      const row = $(this).closest("tr");
      const distId    = row.data("id");
      const childId   = row.children().eq(1).text().trim();
      const childName = row.children().eq(2).text().trim();
      const distMonth = row.children().eq(3).text().trim();
      const givenDate = row.children().eq(4).text().trim();
      const status    = row.children().eq(5).text().trim();
      const bmi       = row.children().eq(6).text().trim();
      const notes     = row.children().eq(7).text().trim();

      $("#editDistributionId").val(distId);
      $("#editChildId").val(childId);
      $("#editChildName").val(childName);
      $("#editDistributionMonth").val(distMonth);
      $("#editGivenDate").val(givenDate === "-" ? "" : givenDate);
      $("#editStatus").val(status);
      $("#editBmi").val(bmi === "-" ? "" : bmi);
      $("#editNotes").val(notes);

      $("#editModalOverlay, #editModal").fadeIn();
    });

    $("#cancelEdit").click(function(){
      $("#editModalOverlay, #editModal").fadeOut();
    });

    // Auto-fill child info in EDIT form when child_id changes
    $("#editChildId").on("change blur", function(){
      const childId = $(this).val().trim();
      if (childId) {
        $.ajax({
          url: window.location.pathname,
          method: "GET",
          data: { get_child_info: true, child_id: childId },
          dataType: "json",
          success: function(res) {
            if (res.status === "success") {
              $("#editChildName").val(res.child_name);
              $("#editBmi").val(res.bmi || "");
            } else {
              showAlert("error", res.message);
              $("#editChildName").val("");
              $("#editBmi").val("");
            }
          },
          error: function(xhr, status, error) {
            console.error("Auto-fill child info error:", error);
          }
        });
      }
    });

    // Submit Edit Form
    $("#editForm").submit(function(e){
      e.preventDefault();
      const formData = $(this).serialize(); // includes action=update
      $.ajax({
        url: window.location.pathname,
        method: "POST",
        data: formData,
        dataType: "json",
        success: function(response) {
          if (response.status === "success") {
            showAlert("success", response.message);
            setTimeout(() => { location.reload(); }, 1200);
          } else {
            showAlert("error", response.message);
          }
        },
        error: function(xhr, status, error) {
          console.error("Update distribution error:", error);
        }
      });
      $("#editModalOverlay, #editModal").fadeOut();
    });

    // -------------------------------
    // 4) DELETE Modal
    // -------------------------------
    let deleteDistId = null;
    $(document).on("click", ".delete-btn", function(){
      const row = $(this).closest("tr");
      deleteDistId = row.data("id");
      $("#deleteModalOverlay, #deleteModal").fadeIn();
    });

    $("#cancelDelete").click(function(){
      $("#deleteModalOverlay, #deleteModal").fadeOut();
    });

    $("#confirmDelete").click(function(){
      if (!deleteDistId) return;
      $.ajax({
        url: window.location.pathname,
        method: "POST",
        data: { action: "delete", distribution_id: deleteDistId },
        dataType: "json",
        success: function(response){
          if (response.status === "success") {
            showAlert("success", response.message);
            setTimeout(() => { location.reload(); }, 1200);
          } else {
            showAlert("error", response.message);
          }
        },
        error: function(xhr, status, error){
          console.error("Delete distribution error:", error);
        }
      });
      $("#deleteModalOverlay, #deleteModal").fadeOut();
    });

    // Hide user menu if clicked outside
    document.addEventListener('click', function(e) {
      const userProfile = document.getElementById('sidebarUserProfile');
      const userMenu = document.getElementById('sidebarUserMenu');
      if (!userProfile.contains(e.target)) {
        userMenu.style.display = 'none';
      }
    });
  });
</script>
</body>
</html>
