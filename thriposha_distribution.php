<?php
session_start();


if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: signin.php');
    exit;
}
$allowed_roles = ['Admin', 'SuperAdmin', 'Doctor'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    header('Location: unauthorized.php');
    exit;
}

$dsn         = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user     = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Current user name/role for sidebar
$userName = $_SESSION['user_name'] ?? 'Sarah Smith';
$userRole = $_SESSION['user_role'] ?? 'Doctor';


if (isset($_GET['get_child_info']) && isset($_GET['child_id'])) {
    header('Content-Type: application/json');
    $child_id = (int)$_GET['child_id'];
    try {
        // Get child's name + latest BMI from child_growth_details
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
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode([
                'status'     => 'success',
                'child_name' => $row['child_name'] ?? '',
                'bmi'        => $row['bmi'] ?? ''
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No matching child found.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

if (isset($_GET['get_mother_info']) && isset($_GET['child_id'])) {
    header('Content-Type: application/json');
    $child_id = (int)$_GET['child_id'];
    try {
        // Join child -> parent to fetch mother_id, mother_name
        $stmt = $pdo->prepare("
            SELECT c.parent_id AS mother_id, p.mother_name AS mother_name
            FROM child c
            JOIN parent p ON c.parent_id = p.parent_id
            WHERE c.child_id = :child_id
            LIMIT 1
        ");
        $stmt->execute([':child_id' => $child_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            echo json_encode([
                'status'      => 'success',
                'mother_id'   => $row['mother_id'],
                'mother_name' => $row['mother_name']
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No matching child or mother found.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['type'])) {
    $action = $_POST['action'];
    $type   = $_POST['type'];

    if ($action === 'add' && $type === 'child') {
        $child_id           = (int)$_POST['child_id'];
        $distribution_month = trim($_POST['distribution_month']);
        $given_date         = !empty($_POST['given_date']) ? $_POST['given_date'] : null;
        $status             = trim($_POST['status']);
        $bmi                = $_POST['bmi'] ?? null;
        $notes              = trim($_POST['notes'] ?? '');

        if ($child_id <= 0 || empty($distribution_month) || empty($status)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid fields (child).']);
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
            echo json_encode(['status' => 'success', 'message' => 'Child record added successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

   
    if ($action === 'update' && $type === 'child') {
        $distribution_id    = (int)$_POST['distribution_id'];
        $child_id           = (int)$_POST['child_id'];
        $distribution_month = trim($_POST['distribution_month']);
        $given_date         = !empty($_POST['given_date']) ? $_POST['given_date'] : null;
        $status             = trim($_POST['status']);
        $bmi                = $_POST['bmi'] ?? null;
        $notes              = trim($_POST['notes'] ?? '');

        if ($distribution_id <= 0 || $child_id <= 0 || empty($distribution_month) || empty($status)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid fields (child update).']);
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
            echo json_encode(['status' => 'success', 'message' => 'Child record updated successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ---------------------------
    // 4.3) CHILD DELETE
    // ---------------------------
    if ($action === 'delete' && $type === 'child') {
        $distribution_id = (int)$_POST['distribution_id'];
        if ($distribution_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid distribution ID (child).']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM thriposha_distribution_child WHERE distribution_id = :id");
            $stmt->execute([':id' => $distribution_id]);
            echo json_encode(['status' => 'success', 'message' => 'Child record deleted successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ---------------------------
    // 4.4) PARENT ADD
    // ---------------------------
    if ($action === 'add' && $type === 'parent') {
        $child_id           = (int)$_POST['child_id'];
        $mother_id          = (int)$_POST['mother_id'];
        $distribution_month = trim($_POST['distribution_month']);
        $given_date         = !empty($_POST['given_date']) ? $_POST['given_date'] : null;
        $status             = trim($_POST['status']);
        $notes              = trim($_POST['notes'] ?? '');

        if ($child_id <= 0 || $mother_id <= 0 || empty($distribution_month) || empty($status)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid fields (parent).']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                INSERT INTO thriposha_distribution
                    (child_id, mother_id, distribution_month, given_date, status, notes)
                VALUES
                    (:child_id, :mother_id, :distribution_month, :given_date, :status, :notes)
            ");
            $stmt->execute([
                ':child_id'           => $child_id,
                ':mother_id'          => $mother_id,
                ':distribution_month' => $distribution_month,
                ':given_date'         => $given_date,
                ':status'             => $status,
                ':notes'              => $notes
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Parent record added successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ---------------------------
    // 4.5) PARENT UPDATE
    // ---------------------------
    if ($action === 'update' && $type === 'parent') {
        $distribution_id    = (int)$_POST['distribution_id'];
        $child_id           = (int)$_POST['child_id'];
        $mother_id          = (int)$_POST['mother_id'];
        $distribution_month = trim($_POST['distribution_month']);
        $given_date         = !empty($_POST['given_date']) ? $_POST['given_date'] : null;
        $status             = trim($_POST['status']);
        $notes              = trim($_POST['notes'] ?? '');

        if ($distribution_id <= 0 || $child_id <= 0 || $mother_id <= 0 || empty($distribution_month) || empty($status)) {
            echo json_encode(['status' => 'error', 'message' => 'Missing or invalid fields (parent update).']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE thriposha_distribution
                SET
                    child_id = :child_id,
                    mother_id = :mother_id,
                    distribution_month = :distribution_month,
                    given_date = :given_date,
                    status = :status,
                    notes = :notes,
                    updated_at = NOW()
                WHERE distribution_id = :distribution_id
            ");
            $stmt->execute([
                ':child_id'           => $child_id,
                ':mother_id'          => $mother_id,
                ':distribution_month' => $distribution_month,
                ':given_date'         => $given_date,
                ':status'             => $status,
                ':notes'              => $notes,
                ':distribution_id'    => $distribution_id
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Parent record updated successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ---------------------------
    // 4.6) PARENT DELETE
    // ---------------------------
    if ($action === 'delete' && $type === 'parent') {
        $distribution_id = (int)$_POST['distribution_id'];
        if ($distribution_id <= 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid distribution ID (parent).']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM thriposha_distribution WHERE distribution_id = :id");
            $stmt->execute([':id' => $distribution_id]);
            echo json_encode(['status' => 'success', 'message' => 'Parent record deleted successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}


$childSearch  = isset($_GET['child_search']) ? trim($_GET['child_search']) : '';
$parentSearch = isset($_GET['parent_search']) ? trim($_GET['parent_search']) : '';

// ------------------
// 5.1) CHILD Query
// ------------------
$childQuery = "
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
$childParams = [];
if ($childSearch !== '') {
    $childQuery .= " AND (
        CAST(td.child_id AS TEXT) ILIKE :search
        OR c.name ILIKE :search
        OR td.distribution_month ILIKE :search
    )";
    $childParams[':search'] = "%$childSearch%";
}
$childQuery .= " ORDER BY td.distribution_id DESC";
$childStmt = $pdo->prepare($childQuery);
$childStmt->execute($childParams);
$childDistributions = $childStmt->fetchAll(PDO::FETCH_ASSOC);

// ------------------
// 5.2) PARENT Query
// ------------------
$parentQuery = "
    SELECT td.distribution_id,
           td.child_id,
           td.mother_id,
           p.mother_name AS mother_name,
           td.distribution_month,
           td.given_date,
           td.status,
           td.notes
    FROM thriposha_distribution td
    JOIN parent p ON td.mother_id = p.parent_id
    WHERE 1=1
";
$parentParams = [];
if ($parentSearch !== '') {
    $parentQuery .= " AND (
        CAST(td.child_id AS TEXT) ILIKE :psearch
        OR p.mother_name ILIKE :psearch
        OR td.distribution_month ILIKE :psearch
    )";
    $parentParams[':psearch'] = "%$parentSearch%";
}
$parentQuery .= " ORDER BY td.distribution_id DESC";
$parentStmt = $pdo->prepare($parentQuery);
$parentStmt->execute($parentParams);
$parentDistributions = $parentStmt->fetchAll(PDO::FETCH_ASSOC);

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
  <title>Thriposha Distribution (Merged)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Poppins Font -->
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
    /* Sidebar */
    .sidebar {
      position: fixed;
      top: 0; left: 0; bottom: 0;
      width: 220px;
      background: var(--primary-color);
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
      width: 45px; height: 45px;
      border-radius: 50%; object-fit: cover;
    }
    .sidebar-user-info {
      display: flex; flex-direction: column;
      font-size: 14px; color: #fff; line-height: 1.2;
    }
    .sidebar-user-name {
      font-weight: 700; font-size: 16px;
    }
    .sidebar-user-role {
      font-weight: 400; font-size: 14px;
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

    /* Top bar: search + toggle + add */
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
    .left-controls {
      display: flex; align-items: center; gap: 10px;
    }
    .toggle-container {
      margin-top: 1rem;      /* spacing below search bar */
      display: flex;         /* horizontal layout */
      gap: 1rem;             /* space between buttons */
    }
    /* Toggle button (inactive) */
    .toggle-btn {
      background-color: #fff;       /* white background */
      color: #274FB4;               /* blue text */
      border: 1px solid #274FB4;    /* blue border */
      padding: 10px 20px;
      border-radius: 5px;
      font-weight: 600;
      cursor: pointer;
    }
    /* Toggle button (active) */
    .toggle-btn.active {
      background-color: #274FB4;    /* solid blue background */
      color: #fff;                  /* white text */
      border: 1px solid #274FB4;    /* optional to keep border for consistency */
    }
    .search-container {
      position: relative;
    }
    .search-container input {
      width: 300px;
      padding: 10px 40px 10px 10px;
      border-radius: 5px;
      border: 1px solid #ddd;
    }
    .search-icon {
      position: absolute;
      right: 10px; top: 50%;
      transform: translateY(-50%);
      color: #4a90e2;
    }
    .add-btn {
      background-color: #1a47b8;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
    }

    /* Table container */
    .distribution-container {
      background: white;
      border-radius: 10px;
      overflow: hidden;
    }
    .sticky-header {
      position: sticky;
      top: 0; z-index: 10;
      background: #fff;
      padding: 20px;
      border-bottom: 1px solid #ddd;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .table-container {
      max-height: 600px;
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
      color: #666; font-weight: 600;
    }
    .action-icons {
      display: flex; align-items: center; justify-content: center; gap: 10px;
    }
    .delete-icon {
      color: #ff4444 !important;
    }

    /* Hide by default for toggling */
    #childSection, #parentSection {
      display: none;
    }

    /* Modals */
    .modal-overlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.5);
      backdrop-filter: blur(5px);
      z-index: 999;
      display: none;
    }
    .modal {
      position: fixed;
      top: 50%; left: 50%;
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
      color: #333; font-size: 14px; font-weight: 500;
    }
    .form-control {
      width: 100%; padding: 8px;
      border: 1px solid #ddd; border-radius: 4px;
      background: #f8f8f8;
    }
    .button-group {
      margin-top: 30px;
      display: flex; justify-content: flex-end; gap: 10px;
    }
    .btn {
      padding: 8px 24px;
      border-radius: 4px;
      border: none;
      cursor: pointer;
      font-size: 14px; font-weight: 600;
    }
    .btn-cancel {
      background: white; border: 1px solid #3366cc; color: #3366cc;
    }
    .btn-primary {
      background: #274FB4; color: #fff; border: none;
    }
    .btn-secondary {
      background: #fff; color: #274FB4; border: 1px solid #274FB4;
    }

    /* Alert Box */
    .alert {
      position: fixed;
      top: 20px; right: 20px;
      padding: 15px 25px;
      border-radius: var(--border-radius);
      color: #fff;
      font-size: 14px; font-weight: 500;
      z-index: 1001;
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

<!-- SIDEBAR -->
<div class="sidebar">
  <div>
    <div class="logo">
      <i class="fas fa-child" style="font-size:24px;"></i>
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
    <!-- If SuperAdmin, etc. -->
    <?php if ($userRole === 'SuperAdmin'): ?>
      <a href="add_admin.php" class="menu-item"><i class="fas fa-user-shield"></i><span>Add Admin</span></a>
    <?php endif; ?>
  </div>
  <!-- User Profile -->
  <div class="sidebar-user-profile" id="sidebarUserProfile">
    <img src="<?php echo htmlspecialchars($optimizedImage); ?>" alt="User Profile" />
    <div class="sidebar-user-info">
      <span class="sidebar-user-name"><?php echo htmlspecialchars($userName); ?></span>
      <span class="sidebar-user-role"><?php echo htmlspecialchars($userRole); ?></span>
    </div>
    <div class="sidebar-user-menu" id="sidebarUserMenu">
      <a href="logout.php">Sign Out</a>
    </div>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="main-content">
<!-- Replace your existing search bar block with this -->

<!-- Search bar row -->
<div class="search-bar">
  <!-- Left: Search container -->
  <div class="search-container">
    <input type="text" id="searchInput" placeholder="Search...">
    <i class="fas fa-search search-icon"></i>
  </div>
  <!-- Right: Add button -->
  <button class="add-btn" id="openAddModal">Add Thriposha Distribution</button>
</div>

<!-- Child/Parent toggle buttons BELOW the search bar -->
<div class="toggle-container" style="margin-top: 1rem; margin-bottom: 1rem; display: flex; gap: 1rem;">
  <button class="toggle-btn" id="btnChild">Child</button>
  <button class="toggle-btn" id="btnParent">Parent</button>
</div>

  <!-- CHILD SECTION -->
  <div id="childSection">
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
          <tbody id="childTableBody">
            <?php if (count($childDistributions) === 0): ?>
              <tr><td colspan="9">No child distribution records found.</td></tr>
            <?php else: ?>
              <?php foreach ($childDistributions as $dist): ?>
                <tr data-id="<?php echo $dist['distribution_id']; ?>">
                  <td><?php echo htmlspecialchars($dist['distribution_id']); ?></td>
                  <td><?php echo htmlspecialchars($dist['child_id']); ?></td>
                  <td><?php echo htmlspecialchars($dist['child_name']); ?></td>
                  <td><?php echo htmlspecialchars($dist['distribution_month']); ?></td>
                  <td><?php echo $dist['given_date'] ? htmlspecialchars($dist['given_date']) : '-'; ?></td>
                  <td><?php echo htmlspecialchars($dist['status']); ?></td>
                  <td><?php echo ($dist['bmi'] !== null) ? htmlspecialchars($dist['bmi']) : '-'; ?></td>
                  <td><?php echo htmlspecialchars($dist['notes']); ?></td>
                  <td class="action-icons">
                    <i class="fas fa-edit edit-btn" data-type="child" style="color:#274FB4;cursor:pointer;"></i>
                    <i class="fas fa-trash delete-icon delete-btn" data-type="child" style="cursor:pointer;"></i>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- PARENT SECTION -->
  <div id="parentSection">
    <div class="distribution-container">
      <div class="sticky-header">
        <h2>Parent Thriposha Distribution</h2>
      </div>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Child ID</th>
              <th>Mother ID</th>
              <th>Mother Name</th>
              <th>Distribution Month</th>
              <th>Given Date</th>
              <th>Status</th>
              <th>Notes</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="parentTableBody">
            <?php if (count($parentDistributions) === 0): ?>
              <tr><td colspan="9">No parent distribution records found.</td></tr>
            <?php else: ?>
              <?php foreach ($parentDistributions as $dist): ?>
                <tr data-id="<?php echo $dist['distribution_id']; ?>">
                  <td><?php echo htmlspecialchars($dist['distribution_id']); ?></td>
                  <td><?php echo htmlspecialchars($dist['child_id']); ?></td>
                  <td><?php echo htmlspecialchars($dist['mother_id']); ?></td>
                  <td><?php echo htmlspecialchars($dist['mother_name']); ?></td>
                  <td><?php echo htmlspecialchars($dist['distribution_month']); ?></td>
                  <td><?php echo $dist['given_date'] ? htmlspecialchars($dist['given_date']) : '-'; ?></td>
                  <td><?php echo htmlspecialchars($dist['status']); ?></td>
                  <td><?php echo htmlspecialchars($dist['notes']); ?></td>
                  <td class="action-icons">
                    <i class="fas fa-edit edit-btn" data-type="parent" style="color:#274FB4;cursor:pointer;"></i>
                    <i class="fas fa-trash delete-icon delete-btn" data-type="parent" style="cursor:pointer;"></i>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ========== MODALS (CHILD) ========== -->
<div class="modal-overlay" id="childAddOverlay"></div>
<div class="modal" id="childAddModal">
  <h2>ADD NEW THRIPOSHA DISTRIBUTION (Child)</h2>
  <form id="childAddForm">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="type" value="child">

    <div class="form-group">
      <label for="childAddChildId">Child ID</label>
      <input type="number" class="form-control" id="childAddChildId" name="child_id" required>
    </div>

    <div class="form-group">
      <label for="childAddChildName">Child Name</label>
      <input type="text" class="form-control" id="childAddChildName" readonly>
    </div>

    <div class="form-group">
      <label for="childAddMonth">Distribution Month</label>
      <select class="form-control" id="childAddMonth" name="distribution_month" required>
        <option value="">-- Select Month --</option>
        <option>January</option><option>February</option><option>March</option>
        <option>April</option><option>May</option><option>June</option>
        <option>July</option><option>August</option><option>September</option>
        <option>October</option><option>November</option><option>December</option>
      </select>
    </div>

    <div class="form-group">
      <label for="childAddDate">Given Date</label>
      <input type="date" class="form-control" id="childAddDate" name="given_date">
    </div>

    <div class="form-group">
      <label for="childAddStatus">Status</label>
      <select class="form-control" id="childAddStatus" name="status" required>
        <option value="Pending">Pending</option>
        <option value="Given">Given</option>
        <option value="Missed">Missed</option>
      </select>
    </div>

    <div class="form-group">
      <label for="childAddBmi">BMI</label>
      <input type="text" class="form-control" id="childAddBmi" name="bmi">
    </div>

    <div class="form-group">
      <label for="childAddNotes">Notes</label>
      <textarea class="form-control" id="childAddNotes" name="notes" rows="3"></textarea>
    </div>

    <div class="button-group">
      <button type="button" class="btn btn-cancel" id="childAddCancel">Cancel</button>
      <button type="submit" class="btn btn-primary">Save</button>
    </div>
  </form>
</div>

<div class="modal-overlay" id="childEditOverlay"></div>
<div class="modal" id="childEditModal">
  <h2>UPDATE THRIPOSHA DISTRIBUTION (Child)</h2>
  <form id="childEditForm">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="type" value="child">
    <input type="hidden" id="childEditDistributionId" name="distribution_id">

    <div class="form-group">
      <label for="childEditChildId">Child ID</label>
      <input type="number" class="form-control" id="childEditChildId" name="child_id" required>
    </div>

    <div class="form-group">
      <label for="childEditChildName">Child Name</label>
      <input type="text" class="form-control" id="childEditChildName" readonly>
    </div>

    <div class="form-group">
      <label for="childEditMonth">Distribution Month</label>
      <select class="form-control" id="childEditMonth" name="distribution_month" required>
        <option>January</option><option>February</option><option>March</option>
        <option>April</option><option>May</option><option>June</option>
        <option>July</option><option>August</option><option>September</option>
        <option>October</option><option>November</option><option>December</option>
      </select>
    </div>

    <div class="form-group">
      <label for="childEditDate">Given Date</label>
      <input type="date" class="form-control" id="childEditDate" name="given_date">
    </div>

    <div class="form-group">
      <label for="childEditStatus">Status</label>
      <select class="form-control" id="childEditStatus" name="status" required>
        <option value="Pending">Pending</option>
        <option value="Given">Given</option>
        <option value="Missed">Missed</option>
      </select>
    </div>

    <div class="form-group">
      <label for="childEditBmi">BMI</label>
      <input type="text" class="form-control" id="childEditBmi" name="bmi">
    </div>

    <div class="form-group">
      <label for="childEditNotes">Notes</label>
      <textarea class="form-control" id="childEditNotes" name="notes" rows="3"></textarea>
    </div>

    <div class="button-group">
      <button type="button" class="btn btn-cancel" id="childEditCancel">Cancel</button>
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
  </form>
</div>

<div class="modal-overlay" id="childDeleteOverlay"></div>
<div class="modal" id="childDeleteModal">
  <h2>DELETE CONFIRMATION (Child)</h2>
  <p>Are you sure you want to delete this record?</p>
  <div class="button-group">
    <button type="button" class="btn btn-secondary" id="childDeleteCancel">Cancel</button>
    <button type="button" class="btn btn-primary" id="childDeleteConfirm">Delete</button>
  </div>
</div>

<!-- ========== MODALS (PARENT) ========== -->
<div class="modal-overlay" id="parentAddOverlay"></div>
<div class="modal" id="parentAddModal">
  <h2>ADD NEW THRIPOSHA DISTRIBUTION (Parent)</h2>
  <form id="parentAddForm">
    <input type="hidden" name="action" value="add">
    <input type="hidden" name="type" value="parent">

    <div class="form-group">
      <label for="parentAddChildId">Child ID</label>
      <input type="number" class="form-control" id="parentAddChildId" name="child_id" required>
    </div>

    <div class="form-group">
      <label for="parentAddMotherId">Mother's ID</label>
      <input type="number" class="form-control" id="parentAddMotherId" name="mother_id" readonly>
    </div>

    <div class="form-group">
      <label for="parentAddMotherName">Mother's Name</label>
      <input type="text" class="form-control" id="parentAddMotherName" readonly>
    </div>

    <div class="form-group">
      <label for="parentAddMonth">Distribution Month</label>
      <select class="form-control" id="parentAddMonth" name="distribution_month" required>
        <option value="">-- Select Month --</option>
        <option>January</option><option>February</option><option>March</option>
        <option>April</option><option>May</option><option>June</option>
        <option>July</option><option>August</option><option>September</option>
        <option>October</option><option>November</option><option>December</option>
      </select>
    </div>

    <div class="form-group">
      <label for="parentAddDate">Given Date</label>
      <input type="date" class="form-control" id="parentAddDate" name="given_date">
    </div>

    <div class="form-group">
      <label for="parentAddStatus">Status</label>
      <select class="form-control" id="parentAddStatus" name="status" required>
        <option value="Pending">Pending</option>
        <option value="Given">Given</option>
        <option value="Missed">Missed</option>
      </select>
    </div>

    <div class="form-group">
      <label for="parentAddNotes">Notes</label>
      <textarea class="form-control" id="parentAddNotes" name="notes" rows="3"></textarea>
    </div>

    <div class="button-group">
      <button type="button" class="btn btn-cancel" id="parentAddCancel">Cancel</button>
      <button type="submit" class="btn btn-primary">Save</button>
    </div>
  </form>
</div>

<div class="modal-overlay" id="parentEditOverlay"></div>
<div class="modal" id="parentEditModal">
  <h2>UPDATE THRIPOSHA DISTRIBUTION (Parent)</h2>
  <form id="parentEditForm">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="type" value="parent">
    <input type="hidden" id="parentEditDistributionId" name="distribution_id">

    <div class="form-group">
      <label for="parentEditChildId">Child ID</label>
      <input type="number" class="form-control" id="parentEditChildId" name="child_id" required>
    </div>

    <div class="form-group">
      <label for="parentEditMotherId">Mother's ID</label>
      <input type="number" class="form-control" id="parentEditMotherId" name="mother_id" readonly>
    </div>

    <div class="form-group">
      <label for="parentEditMotherName">Mother's Name</label>
      <input type="text" class="form-control" id="parentEditMotherName" readonly>
    </div>

    <div class="form-group">
      <label for="parentEditMonth">Distribution Month</label>
      <select class="form-control" id="parentEditMonth" name="distribution_month" required>
        <option>January</option><option>February</option><option>March</option>
        <option>April</option><option>May</option><option>June</option>
        <option>July</option><option>August</option><option>September</option>
        <option>October</option><option>November</option><option>December</option>
      </select>
    </div>

    <div class="form-group">
      <label for="parentEditDate">Given Date</label>
      <input type="date" class="form-control" id="parentEditDate" name="given_date">
    </div>

    <div class="form-group">
      <label for="parentEditStatus">Status</label>
      <select class="form-control" id="parentEditStatus" name="status" required>
        <option value="Pending">Pending</option>
        <option value="Given">Given</option>
        <option value="Missed">Missed</option>
      </select>
    </div>

    <div class="form-group">
      <label for="parentEditNotes">Notes</label>
      <textarea class="form-control" id="parentEditNotes" name="notes" rows="3"></textarea>
    </div>

    <div class="button-group">
      <button type="button" class="btn btn-cancel" id="parentEditCancel">Cancel</button>
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
  </form>
</div>

<div class="modal-overlay" id="parentDeleteOverlay"></div>
<div class="modal" id="parentDeleteModal">
  <h2>DELETE CONFIRMATION (Parent)</h2>
  <p>Are you sure you want to delete this record?</p>
  <div class="button-group">
    <button type="button" class="btn btn-secondary" id="parentDeleteCancel">Cancel</button>
    <button type="button" class="btn btn-primary" id="parentDeleteConfirm">Delete</button>
  </div>
</div>

<!-- ALERT BOX -->
<div class="alert" id="alertBox"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
(function(){
  // Track which tab is active: "child" or "parent"
  let activeTab = "child"; // default

  // Toggle user menu in sidebar
  function toggleSidebarUserMenu(){
    const menu = document.getElementById("sidebarUserMenu");
    if(menu.style.display === "block") menu.style.display = "none";
    else menu.style.display = "block";
  }
  document.getElementById("sidebarUserProfile").addEventListener("click", function(e){
    e.stopPropagation(); toggleSidebarUserMenu();
  });
  document.addEventListener("click", function(e){
    const userMenu = document.getElementById("sidebarUserMenu");
    if(userMenu && !document.getElementById("sidebarUserProfile").contains(e.target)){
      userMenu.style.display = "none";
    }
  });

  // Show alert
  function showAlert(type, message){
    const alertBox = $("#alertBox");
    alertBox.removeClass("alert-success alert-error");
    if(type === "success") alertBox.addClass("alert-success");
    else alertBox.addClass("alert-error");
    alertBox.text(message).fadeIn();
    setTimeout(()=>{ alertBox.fadeOut(); }, 3000);
  }

  // DOM Elements
  const btnChild = $("#btnChild");
  const btnParent = $("#btnParent");
  const childSection = $("#childSection");
  const parentSection = $("#parentSection");
  const searchInput = $("#searchInput");
  const addBtn = $("#openAddModal");

  // Initially show Child tab
  function activateChildTab(){
    activeTab = "child";
    btnChild.addClass("active");
    btnParent.removeClass("active");
    childSection.show();
    parentSection.hide();
  }
  function activateParentTab(){
    activeTab = "parent";
    btnParent.addClass("active");
    btnChild.removeClass("active");
    childSection.hide();
    parentSection.show();
  }

  // Toggle button events
  btnChild.on("click", activateChildTab);
  btnParent.on("click", activateParentTab);

  // Start with child tab visible
  activateChildTab();

  // 1) SEARCH
  // We'll do a full page reload approach for now, passing child_search or parent_search in GET.
  // If you want partial AJAX, you'd parse partial HTML, but let's keep it simpler:
  searchInput.on("keydown", function(e){
    if(e.key === "Enter"){ // press Enter to search
      e.preventDefault();
      const val = $(this).val().trim();
      if(activeTab === "child"){
        window.location.href = "?child_search=" + encodeURIComponent(val);
      } else {
        window.location.href = "?parent_search=" + encodeURIComponent(val);
      }
    }
  });

  // 2) ADD BUTTON -> open relevant add modal
  addBtn.on("click", function(){
    if(activeTab === "child"){
      $("#childAddOverlay, #childAddModal").fadeIn();
    } else {
      $("#parentAddOverlay, #parentAddModal").fadeIn();
    }
  });

  // =========== CHILD MODALS ===========
  // Close child add modal
  $("#childAddCancel").on("click", function(){
    $("#childAddOverlay, #childAddModal").fadeOut();
  });
  // Auto-fill child info
  $("#childAddChildId").on("change blur", function(){
    const childId = $(this).val().trim();
    if(childId){
      $.ajax({
        url: window.location.pathname,
        method: "GET",
        data: { get_child_info: true, child_id: childId },
        dataType: "json",
        success: function(res){
          if(res.status === "success"){
            $("#childAddChildName").val(res.child_name);
            $("#childAddBmi").val(res.bmi || "");
          } else {
            showAlert("error", res.message);
            $("#childAddChildName").val("");
            $("#childAddBmi").val("");
          }
        },
        error: function(xhr, status, error){
          console.error("child info error:", error);
        }
      });
    }
  });
  // Submit child add form
  $("#childAddForm").on("submit", function(e){
    e.preventDefault();
    const formData = $(this).serialize();
    $.ajax({
      url: window.location.pathname,
      method: "POST",
      data: formData,
      dataType: "json",
      success: function(response){
        if(response.status === "success"){
          showAlert("success", response.message);
          setTimeout(()=>{ location.reload(); },1200);
        } else {
          showAlert("error", response.message);
        }
      },
      error: function(xhr, status, error){
        console.error("child add error:", error);
      }
    });
    $("#childAddOverlay, #childAddModal").fadeOut();
  });

  // Edit
  $("#childEditCancel").on("click", function(){
    $("#childEditOverlay, #childEditModal").fadeOut();
  });
  $(document).on("click", ".edit-btn[data-type='child']", function(){
    const row = $(this).closest("tr");
    const distId = row.data("id");
    const childId = row.children().eq(1).text().trim();
    const childName = row.children().eq(2).text().trim();
    const distMonth = row.children().eq(3).text().trim();
    const givenDate = row.children().eq(4).text().trim();
    const status = row.children().eq(5).text().trim();
    const bmi = row.children().eq(6).text().trim();
    const notes = row.children().eq(7).text().trim();

    $("#childEditDistributionId").val(distId);
    $("#childEditChildId").val(childId);
    $("#childEditChildName").val(childName);
    $("#childEditMonth").val(distMonth);
    $("#childEditDate").val(givenDate === "-" ? "" : givenDate);
    $("#childEditStatus").val(status);
    $("#childEditBmi").val(bmi === "-" ? "" : bmi);
    $("#childEditNotes").val(notes);

    $("#childEditOverlay, #childEditModal").fadeIn();
  });
  // Auto-fill child info on edit
  $("#childEditChildId").on("change blur", function(){
    const childId = $(this).val().trim();
    if(childId){
      $.ajax({
        url: window.location.pathname,
        method: "GET",
        data: { get_child_info: true, child_id: childId },
        dataType: "json",
        success: function(res){
          if(res.status === "success"){
            $("#childEditChildName").val(res.child_name);
            $("#childEditBmi").val(res.bmi || "");
          } else {
            showAlert("error", res.message);
            $("#childEditChildName").val("");
            $("#childEditBmi").val("");
          }
        },
        error: function(xhr, status, error){
          console.error("child info error:", error);
        }
      });
    }
  });
  // Submit child edit form
  $("#childEditForm").on("submit", function(e){
    e.preventDefault();
    const formData = $(this).serialize();
    $.ajax({
      url: window.location.pathname,
      method: "POST",
      data: formData,
      dataType: "json",
      success: function(response){
        if(response.status === "success"){
          showAlert("success", response.message);
          setTimeout(()=>{ location.reload(); },1200);
        } else {
          showAlert("error", response.message);
        }
      },
      error: function(xhr, status, error){
        console.error("child update error:", error);
      }
    });
    $("#childEditOverlay, #childEditModal").fadeOut();
  });

  // Delete
  let deleteChildDistId = null;
  $("#childDeleteCancel").on("click", function(){
    $("#childDeleteOverlay, #childDeleteModal").fadeOut();
  });
  $(document).on("click", ".delete-btn[data-type='child']", function(){
    const row = $(this).closest("tr");
    deleteChildDistId = row.data("id");
    $("#childDeleteOverlay, #childDeleteModal").fadeIn();
  });
  $("#childDeleteConfirm").on("click", function(){
    if(!deleteChildDistId) return;
    $.ajax({
      url: window.location.pathname,
      method: "POST",
      data: { action:"delete", type:"child", distribution_id: deleteChildDistId },
      dataType: "json",
      success: function(response){
        if(response.status === "success"){
          showAlert("success", response.message);
          setTimeout(()=>{ location.reload(); },1200);
        } else {
          showAlert("error", response.message);
        }
      },
      error: function(xhr, status, error){
        console.error("child delete error:", error);
      }
    });
    $("#childDeleteOverlay, #childDeleteModal").fadeOut();
  });

  // =========== PARENT MODALS ===========
  // Close parent add
  $("#parentAddCancel").on("click", function(){
    $("#parentAddOverlay, #parentAddModal").fadeOut();
  });
  // Auto-fill mother info
  $("#parentAddChildId").on("change blur", function(){
    const childId = $(this).val().trim();
    if(childId){
      $.ajax({
        url: window.location.pathname,
        method: "GET",
        data: { get_mother_info: true, child_id: childId },
        dataType: "json",
        success: function(res){
          if(res.status === "success"){
            $("#parentAddMotherId").val(res.mother_id);
            $("#parentAddMotherName").val(res.mother_name);
          } else {
            showAlert("error", res.message);
            $("#parentAddMotherId").val("");
            $("#parentAddMotherName").val("");
          }
        },
        error: function(xhr, status, error){
          console.error("parent mother info error:", error);
        }
      });
    }
  });
  // Submit parent add form
  $("#parentAddForm").on("submit", function(e){
    e.preventDefault();
    const formData = $(this).serialize();
    $.ajax({
      url: window.location.pathname,
      method: "POST",
      data: formData,
      dataType: "json",
      success: function(response){
        if(response.status === "success"){
          showAlert("success", response.message);
          setTimeout(()=>{ location.reload(); },1200);
        } else {
          showAlert("error", response.message);
        }
      },
      error: function(xhr, status, error){
        console.error("parent add error:", error);
      }
    });
    $("#parentAddOverlay, #parentAddModal").fadeOut();
  });

  // Edit
  $("#parentEditCancel").on("click", function(){
    $("#parentEditOverlay, #parentEditModal").fadeOut();
  });
  $(document).on("click", ".edit-btn[data-type='parent']", function(){
    const row = $(this).closest("tr");
    const distId = row.data("id");
    const childId = row.children().eq(1).text().trim();
    const motherId = row.children().eq(2).text().trim();
    const motherName = row.children().eq(3).text().trim();
    const distMonth = row.children().eq(4).text().trim();
    const givenDate = row.children().eq(5).text().trim();
    const status = row.children().eq(6).text().trim();
    const notes = row.children().eq(7).text().trim();

    $("#parentEditDistributionId").val(distId);
    $("#parentEditChildId").val(childId);
    $("#parentEditMotherId").val(motherId);
    $("#parentEditMotherName").val(motherName);
    $("#parentEditMonth").val(distMonth);
    $("#parentEditDate").val(givenDate === "-" ? "" : givenDate);
    $("#parentEditStatus").val(status);
    $("#parentEditNotes").val(notes);

    $("#parentEditOverlay, #parentEditModal").fadeIn();
  });
  // Auto-fill mother info in edit
  $("#parentEditChildId").on("change blur", function(){
    const childId = $(this).val().trim();
    if(childId){
      $.ajax({
        url: window.location.pathname,
        method: "GET",
        data: { get_mother_info: true, child_id: childId },
        dataType: "json",
        success: function(res){
          if(res.status === "success"){
            $("#parentEditMotherId").val(res.mother_id);
            $("#parentEditMotherName").val(res.mother_name);
          } else {
            showAlert("error", res.message);
            $("#parentEditMotherId").val("");
            $("#parentEditMotherName").val("");
          }
        },
        error: function(xhr, status, error){
          console.error("parent mother info error:", error);
        }
      });
    }
  });
  // Submit parent edit
  $("#parentEditForm").on("submit", function(e){
    e.preventDefault();
    const formData = $(this).serialize();
    $.ajax({
      url: window.location.pathname,
      method: "POST",
      data: formData,
      dataType: "json",
      success: function(response){
        if(response.status === "success"){
          showAlert("success", response.message);
          setTimeout(()=>{ location.reload(); },1200);
        } else {
          showAlert("error", response.message);
        }
      },
      error: function(xhr, status, error){
        console.error("parent update error:", error);
      }
    });
    $("#parentEditOverlay, #parentEditModal").fadeOut();
  });

  // Delete
  let deleteParentDistId = null;
  $("#parentDeleteCancel").on("click", function(){
    $("#parentDeleteOverlay, #parentDeleteModal").fadeOut();
  });
  $(document).on("click", ".delete-btn[data-type='parent']", function(){
    const row = $(this).closest("tr");
    deleteParentDistId = row.data("id");
    $("#parentDeleteOverlay, #parentDeleteModal").fadeIn();
  });
  $("#parentDeleteConfirm").on("click", function(){
    if(!deleteParentDistId) return;
    $.ajax({
      url: window.location.pathname,
      method: "POST",
      data: { action:"delete", type:"parent", distribution_id: deleteParentDistId },
      dataType: "json",
      success: function(response){
        if(response.status === "success"){
          showAlert("success", response.message);
          setTimeout(()=>{ location.reload(); },1200);
        } else {
          showAlert("error", response.message);
        }
      },
      error: function(xhr, status, error){
        console.error("parent delete error:", error);
      }
    });
    $("#parentDeleteOverlay, #parentDeleteModal").fadeOut();
  });

})();
</script>
</body>
</html>
