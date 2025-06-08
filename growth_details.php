<?php
ob_start();
/*****************************************************
 * growth_details.php
 *
 * Complete code for child growth records with:
 * - Proper POST submission for adding records (data sent in POST, not in query string).
 * - Auto-calculated BMI and nutrition status (fields are read-only).
 * - AJAX-based edit and delete with proper error handling.
 * - Search & filter functionality similar to child_profile.php.
 * - No hide/show columns feature.
 *****************************************************/

// Start session
session_start();

// Helper function: Determine if the request is AJAX
function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// 1) Check if the user is logged in and allowed (Admin or SuperAdmin)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    if (is_ajax_request()) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "User not authenticated"]);
        exit;
    } else {
        header('Location: signin.php');
        exit;
    }
}
$allowed_roles = ['Admin', 'SuperAdmin', 'Doctor'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    if (is_ajax_request()) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
        exit;
    } else {
        header('Location: unauthorized.php');
        exit;
    }
}

// 2) Database connection (PostgreSQL)
$dsn         = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user     = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";
try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed.");
}

// For display in the sidebar
$userName = $_SESSION['user_name'] ?? 'Sarah Smith';
$userRole = $_SESSION['user_role'] ?? 'Doctor';

/////////////////////
// AJAX Endpoints
/////////////////////

// DELETE a growth record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_growth_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    $growthId = (int) $_POST['delete_growth_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM child_growth_details WHERE growth_id = :gid");
        $stmt->execute([':gid' => $growthId]);
        echo json_encode(["status" => "success", "message" => "Growth record deleted successfully!"]);
    } catch (PDOException $e) {
        error_log("Delete error: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error deleting record."]);
    }
    exit;
}

// FETCH a single growth record for editing
if (isset($_GET['fetch_growth']) && isset($_GET['growth_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    $growthId = (int) $_GET['growth_id'];
    try {
        $stmt = $pdo->prepare("
            SELECT cg.growth_id, cg.child_id, c.name AS child_name,
                   cg.weight, cg.height, cg.bmi, cg.nutrition_status,
                   cg.medical_recommendation, cg.measurement_date
            FROM child_growth_details cg
            JOIN child c ON c.child_id = cg.child_id
            WHERE cg.growth_id = :gid
            LIMIT 1
        ");
        $stmt->execute([':gid' => $growthId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode(["status" => "success", "data" => $row]);
        } else {
            echo json_encode(["status" => "error", "message" => "Record not found."]);
        }
    } catch (PDOException $e) {
        error_log("Fetch growth record error: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Error fetching record."]);
    }
    exit;
}

// UPDATE a growth record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_growth_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    $growthId    = (int) $_POST['update_growth_id'];
    $weight      = (float) $_POST['update_weight'];
    $height      = (float) $_POST['update_height'];
    $medRec      = trim($_POST['update_medical_recommendation']);
    $dateMeasure = trim($_POST['update_measurement_date']);

    // Calculate BMI and nutrition status
    $bmi = 0;
    $nutStat = '';
    if ($height > 0 && $weight > 0) {
        $bmi = round($weight / ($height * $height), 2);
        if ($bmi < 18.5) {
            $nutStat = 'Underweight';
        } elseif ($bmi < 25) {
            $nutStat = 'Normal';
        } elseif ($bmi < 30) {
            $nutStat = 'Overweight';
        } else {
            $nutStat = 'Obese';
        }
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE child_growth_details
            SET weight = :w,
                height = :h,
                bmi = :b,
                nutrition_status = :n,
                medical_recommendation = :m,
                measurement_date = :md
            WHERE growth_id = :gid
        ");
        $stmt->execute([
            ':w'  => $weight,
            ':h'  => $height,
            ':b'  => $bmi,
            ':n'  => $nutStat,
            ':m'  => $medRec,
            ':md' => $dateMeasure,
            ':gid'=> $growthId
        ]);
        echo json_encode(["status" => "success", "message" => "Growth record updated successfully."]);
    } catch (PDOException $e) {
        error_log("Update error: " . $e->getMessage());
        if (strpos($e->getMessage(), 'duplicate key') !== false) {
            echo json_encode(["status" => "error", "message" => "A growth record for this child on this date already exists."]);
        } else {
            echo json_encode(["status" => "error", "message" => "Error updating record."]);
        }
    }
    exit;
}

// ADD a new growth record
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_growth']) && $_POST['add_growth'] === 'true') {
    // Clean any previous output in the buffer to prevent extra HTML from being appended.
    if (ob_get_length()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');

    // Check required fields
    $requiredFields = ['child_id', 'weight_current', 'height_current', 'measurement_date'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            exit(json_encode(['status' => 'error', 'message' => "Missing required field: $field"]));
        }
    }
    
    $childId         = (int) $_POST['child_id'];
    $weight          = (float) $_POST['weight_current'];
    $height          = (float) $_POST['height_current'];
    $measurementDate = trim($_POST['measurement_date']);
    $medicalRec      = trim($_POST['medical_recommendation'] ?? '');

    // Validate values
    if ($childId <= 0) {
        exit(json_encode(['status' => 'error', 'message' => 'Invalid Child ID']));
    }
    if ($weight <= 0 || $height <= 0) {
        exit(json_encode(['status' => 'error', 'message' => 'Weight and height must be positive']));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $measurementDate)) {
        exit(json_encode(['status' => 'error', 'message' => 'Invalid measurement date format (YYYY-MM-DD required)']));
    }

    // Calculate BMI and nutrition status
    $bmi = round($weight / ($height * $height), 2);
    if ($bmi < 18.5) {
        $nutStat = 'Underweight';
    } elseif ($bmi < 25) {
        $nutStat = 'Normal';
    } elseif ($bmi < 30) {
        $nutStat = 'Overweight';
    } else {
        $nutStat = 'Obese';
    }

    // Debug log for troubleshooting
    error_log("Adding record: child_id=$childId, measurement_date=$measurementDate, weight=$weight, height=$height, bmi=$bmi, nutrition_status=$nutStat");

    try {
        $stmt = $pdo->prepare("
            INSERT INTO child_growth_details
            (child_id, measurement_date, weight, height, bmi, nutrition_status, medical_recommendation)
            VALUES (:cid, :md, :w, :h, :b, :ns, :mr)
        ");
        $stmt->execute([
            ':cid' => $childId,
            ':md'  => $measurementDate,
            ':w'   => $weight,
            ':h'   => $height,
            ':b'   => $bmi,
            ':ns'  => $nutStat,
            ':mr'  => $medicalRec
        ]);
        exit(json_encode(["status" => "success", "message" => "Growth record added successfully!"]));
    } catch (PDOException $e) {
        error_log("Insert error: " . $e->getMessage());
        if (strpos($e->getMessage(), 'duplicate key') !== false) {
            exit(json_encode(["status" => "error", "message" => "A growth record for this child on this date already exists."]));
        } else {
            exit(json_encode(["status" => "error", "message" => "Error adding record."]));
        }
    }
}

// FETCH child info for real-time fill in the add form
if (isset($_GET['fetch_child']) && isset($_GET['child_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    $childId = (int) $_GET['child_id'];
    try {
        $stmt = $pdo->prepare("
            SELECT child_id, name AS child_name, weight AS birth_weight, height AS birth_height
            FROM child
            WHERE child_id = :cid
            LIMIT 1
        ");
        $stmt->execute([':cid' => $childId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            echo json_encode([
                'status'       => 'success',
                'child_id'     => $row['child_id'],
                'child_name'   => $row['child_name'],
                'birth_weight' => $row['birth_weight'],
                'birth_height' => $row['birth_height']
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Child not found']);
        }
    } catch (PDOException $e) {
        error_log("Fetch child error: " . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Error fetching child info']);
    }
    exit;
}

// SEARCH & FILTER AJAX
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    ob_clean();
    header('Content-Type: application/json');
    $search            = isset($_GET['search']) ? trim($_GET['search']) : '';
    $filter_child_id   = isset($_GET['child_id']) ? trim($_GET['child_id']) : '';
    $filter_child_name = isset($_GET['child_name']) ? trim($_GET['child_name']) : '';
    $filter_date       = isset($_GET['measurement_date']) ? trim($_GET['measurement_date']) : '';
    $filter_weight     = isset($_GET['filter_weight']) ? trim($_GET['filter_weight']) : '';
    $filter_bmi        = isset($_GET['filter_bmi']) ? trim($_GET['filter_bmi']) : '';
    $filter_nutrition  = isset($_GET['nutrition_status']) ? trim($_GET['nutrition_status']) : '';
    $filter_medical    = isset($_GET['medical_recommendation']) ? trim($_GET['medical_recommendation']) : '';

    $sql = "
        SELECT cg.growth_id, cg.child_id, c.name AS child_name,
               c.height AS birth_height, c.weight AS birth_weight,
               cg.height, cg.weight, cg.measurement_date,
               cg.bmi, cg.nutrition_status, cg.medical_recommendation
        FROM child_growth_details cg
        JOIN child c ON c.child_id = cg.child_id
        WHERE 1=1
    ";
    $params = [];
    if ($search !== '') {
        $sql .= " AND (c.name ILIKE :search OR cg.medical_recommendation ILIKE :search)";
        $params[':search'] = "%{$search}%";
    }
    if ($filter_child_id !== '') {
        $sql .= " AND cg.child_id = :fcid";
        $params[':fcid'] = $filter_child_id;
    }
    if ($filter_child_name !== '') {
        $sql .= " AND c.name ILIKE :fcn";
        $params[':fcn'] = "%{$filter_child_name}%";
    }
    if ($filter_date !== '') {
        $sql .= " AND cg.measurement_date = :fdate";
        $params[':fdate'] = $filter_date;
    }
    if ($filter_weight !== '') {
        $sql .= " AND cg.weight = :fweight";
        $params[':fweight'] = $filter_weight;
    }
    if ($filter_bmi !== '') {
        $sql .= " AND cg.bmi = :fbmi";
        $params[':fbmi'] = $filter_bmi;
    }
    if ($filter_nutrition !== '') {
        $sql .= " AND cg.nutrition_status ILIKE :fns";
        $params[':fns'] = "%{$filter_nutrition}%";
    }
    if ($filter_medical !== '') {
        $sql .= " AND cg.medical_recommendation ILIKE :fmr";
        $params[':fmr'] = "%{$filter_medical}%";
    }
    $sql .= " ORDER BY cg.growth_id DESC";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($rows);
    } catch (PDOException $e) {
        error_log("Search filter error: " . $e->getMessage());
        echo json_encode(["error" => "Error fetching records."]);
    }
    exit;
}

// ----------------------------------------------------
// Normal page load: fetch all growth records
// ----------------------------------------------------
$growthRows = [];
try {
    $sql = "
      SELECT cg.growth_id, cg.child_id,
             c.name AS child_name,
             c.height AS birth_height,
             c.weight AS birth_weight,
             cg.height, cg.weight,
             cg.measurement_date,
             cg.bmi,
             cg.nutrition_status,
             cg.medical_recommendation
      FROM child_growth_details cg
      JOIN child c ON c.child_id = cg.child_id
      ORDER BY cg.growth_id DESC
    ";
    $stmt = $pdo->query($sql);
    $growthRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Normal load error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>KidsGrow - Growth Details</title>
  <!-- Poppins Font -->
  <link rel="preconnect" href="https://fonts.gstatic.com" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
  <style>
    /* Basic CSS styles */
    :root {
      --primary-color: #274FB4;
      --secondary-color: #8FC4F1;
      --text-dark: #333;
      --text-light: #666;
      --white: #fff;
      --border-radius: 8px;
      --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
    body { background-color: #f0f0f0; margin: 0; }
    /* Sidebar */
    .sidebar {
      position: fixed; top: 0; left: 0; bottom: 0;
      width: 220px; background: #274FB4; color: white; padding: 20px;
      overflow-y: auto; z-index: 999; display: flex; flex-direction: column; justify-content: space-between;
    }
    .main-content {
      margin-left: 220px; height: 100vh; overflow-y: auto; padding: 0 20px 20px 20px; position: relative;
    }
    .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 40px; }
    .logo span { font-size: 24px; font-weight: 800; }
    .menu-item {
      display: flex; align-items: center; gap: 10px; padding: 12px 0; cursor: pointer;
      color: white; text-decoration: none; font-size: 16px; font-weight: 700;
    }
    .menu-item:hover { background-color: rgba(255, 255, 255, 0.2); padding-left: 10px; border-radius: var(--border-radius); }
    .sidebar-user-profile {
      display: flex; align-items: center; gap: 12px; margin-top: 40px;
      padding: 10px; background: rgba(255,255,255,0.2); border-radius: var(--border-radius);
      cursor: pointer; position: relative;
    }
    .sidebar-user-profile img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; }
    .sidebar-user-info { display: flex; flex-direction: column; font-size: 14px; color: #fff; line-height: 1.2; }
    .sidebar-user-name { font-weight: 700; font-size: 16px; }
    .sidebar-user-role { font-weight: 400; font-size: 14px; }
    .sidebar-user-menu {
      display: none; position: absolute; bottom: 60px; left: 0;
      background: #fff; border: 1px solid #ddd; border-radius: 5px; min-width: 120px;
      box-shadow: var(--shadow); padding: 5px 0; color: #333; z-index: 1000;
    }
    .sidebar-user-menu a { display: block; padding: 8px 12px; text-decoration: none; color: #333; font-size: 14px; }
    .sidebar-user-menu a:hover { background-color: #f0f0f0; }
    /* Search bar */
    .search-bar {
      position: sticky; top: 0; z-index: 100; background-color: #f0f0f0;
      padding: 20px 0 10px 0; display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;
    }
    .search-container { position: relative; width: 400px; }
    .search-container input {
      width: 100%; padding: 10px 40px 10px 10px; border-radius: 5px; border: 1px solid #ddd;
    }
    .search-container .search-icon {
      position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #4a90e2;
    }
    .add-child-btn {
      background-color: #1a47b8; color: white; border: none; padding: 10px 20px;
      border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: 600;
    }
    /* Table Box */
    .child-profiles {
      background: white; border-radius: 10px; overflow: hidden;
    }
    .child-profiles .sticky-header {
      position: sticky; top: 0; z-index: 10; background: #fff; padding: 20px;
      border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;
    }
    .child-profiles .table-container {
      max-height: 600px; overflow-y: auto; overflow-x: auto; padding: 20px;
    }
    .child-profiles table { width: 100%; border-collapse: collapse; table-layout: auto; }
    .child-profiles th, .child-profiles td {
      padding: 16px 20px; text-align: center; font-size: 14px; white-space: nowrap;
    }
    .child-profiles th { color: #666; font-weight: 600; }
    .action-icons { display: flex; align-items: center; justify-content: center; gap: 10px; }
    .delete-icon { color: #ff4444 !important; }
    /* Modal Overlays */
    .modal-overlay {
      position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0,0,0,0.5); backdrop-filter: blur(5px);
      z-index: 999; display: none;
    }
    .modal {
      position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%);
      background: var(--white); padding: 2rem; border-radius: var(--border-radius);
      box-shadow: var(--shadow); width: 500px; max-width: 90%; max-height: 90vh;
      overflow-y: auto; z-index: 1000; display: none;
    }
    .filter-title { font-size: 18px; font-weight: 700; margin-bottom: 30px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; color: #333; font-size: 14px; font-weight: 500; }
    .form-control { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #f8f8f8; }
    .radio-group { display: flex; gap: 20px; }
    .radio-option { display: flex; align-items: center; }
    .radio-option input[type="radio"] { margin-right: 5px; }
    .button-group { margin-top: 30px; display: flex; justify-content: flex-end; gap: 10px; }
    .btn { padding: 8px 24px; border-radius: 4px; border: none; cursor: pointer; font-size: 14px; font-weight: 600; }
    .btn-cancel { background: white; border: 1px solid #3366cc; color: #3366cc; }
    .btn-apply { background: #3366cc; color: white; }
    .btn-primary { background: #274FB4; color: #fff; border: none; }
    .btn-secondary { background: #fff; color: #274FB4; border: 1px solid #274FB4; }
    /* Alert Box */
    .alert {
      position: fixed; top: 20px; right: 20px; padding: 15px 25px; border-radius: var(--border-radius);
      color: #fff; font-size: 14px; font-weight: 500; z-index: 1001; display: none;
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
          <a href="vaccination.php" class="menu-item">
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
      <!-- Top Bar with Search & Add Growth Button -->
      <div class="search-bar">
          <div class="search-container">
              <input type="text" id="searchInput" placeholder="Search by name or recommendation...">
              <i class="fas fa-search search-icon"></i>
          </div>
          <button class="add-child-btn" id="openAddModal">
              <i class="fas fa-plus"></i> Add Growth
          </button>
      </div>

      <!-- Table Box -->
      <div class="child-profiles">
          <div class="sticky-header">
              <h2>Child Growth Details</h2>
              <i class="fas fa-filter filter-icon" id="tableFilterIcon"></i>
          </div>
          <div class="table-container">
              <table>
                  <thead>
                      <tr>
                          <th>Growth ID</th>
                          <th>Child ID</th>
                          <th>Child Name</th>
                          <th>Birth Height (m)</th>
                          <th>Birth Weight (kg)</th>
                          <th>Height (m)</th>
                          <th>Weight (kg)</th>
                          <th>Measurement Date</th>
                          <th>BMI</th>
                          <th>Nutrition Status</th>
                          <th>Medical Recommendation</th>
                          <th>Actions</th>
                      </tr>
                  </thead>
                  <tbody id="tableBody">
                      <?php foreach ($growthRows as $gr): ?>
                      <tr data-id="<?php echo $gr['growth_id']; ?>">
                          <td><?php echo htmlspecialchars($gr['growth_id']); ?></td>
                          <td><?php echo htmlspecialchars($gr['child_id']); ?></td>
                          <td><?php echo htmlspecialchars($gr['child_name']); ?></td>
                          <td><?php echo htmlspecialchars($gr['birth_height']); ?></td>
                          <td><?php echo htmlspecialchars($gr['birth_weight']); ?></td>
                          <td><?php echo htmlspecialchars($gr['height']); ?></td>
                          <td><?php echo htmlspecialchars($gr['weight']); ?></td>
                          <td><?php echo htmlspecialchars($gr['measurement_date']); ?></td>
                          <td><?php echo htmlspecialchars($gr['bmi']); ?></td>
                          <td><?php echo htmlspecialchars($gr['nutrition_status']); ?></td>
                          <td><?php echo htmlspecialchars($gr['medical_recommendation']); ?></td>
                          <td class="action-icons">
                              <i class="fas fa-edit edit-btn" data-id="<?php echo $gr['growth_id']; ?>"></i>
                              <i class="fas fa-trash delete-icon delete-btn" data-id="<?php echo $gr['growth_id']; ?>"></i>
                          </td>
                      </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
          </div>
      </div>
  </div>

  <!-- Add Growth Modal -->
  <div class="modal-overlay" id="addModalOverlay"></div>
  <div class="modal" id="addModal">
      <h2 class="filter-title">ADD GROWTH RECORD</h2>
      <form id="addGrowthForm">
          <input type="hidden" name="add_growth" value="true">
          <div class="form-grid">
              <div class="form-group">
                  <label>Child ID</label>
                  <input type="number" class="form-control" name="child_id" id="addChildId" required />
              </div>
              <div class="form-group">
                  <label>Child Name</label>
                  <input type="text" class="form-control" id="addChildName" readonly />
              </div>
              <div class="form-group">
                  <label>Birth Weight (kg)</label>
                  <input type="text" class="form-control" id="addBirthWeight" readonly />
              </div>
              <div class="form-group">
                  <label>Birth Height (m)</label>
                  <input type="text" class="form-control" id="addBirthHeight" readonly />
              </div>
              <div class="form-group">
                  <label>Weight (kg)</label>
                  <input type="number" step="0.1" class="form-control" name="weight_current" id="addWeight" required />
              </div>
              <div class="form-group">
                  <label>Height (m)</label>
                  <input type="number" step="0.01" class="form-control" name="height_current" id="addHeight" required />
              </div>
              <div class="form-group">
                  <label>Measurement Date</label>
                  <input type="date" class="form-control" name="measurement_date" required />
              </div>
              <div class="form-group">
                  <label>Medical Recommendation</label>
                  <input type="text" class="form-control" name="medical_recommendation" />
              </div>
          </div>
          <div class="button-group">
              <button type="button" class="btn btn-cancel" id="cancelAdd">Cancel</button>
              <button type="submit" class="btn btn-apply">Save</button>
          </div>
      </form>
  </div>

  <!-- Edit Growth Modal -->
  <div class="modal-overlay" id="editModalOverlay"></div>
  <div class="modal" id="editModal">
      <h2 class="filter-title">EDIT GROWTH RECORD</h2>
      <form id="editGrowthForm">
          <input type="hidden" id="editGrowthId">
          <div class="form-grid">
              <div class="form-group">
                  <label>Child Name</label>
                  <input type="text" class="form-control" id="editChildName" readonly />
              </div>
              <div class="form-group">
                  <label>Weight (kg)</label>
                  <input type="number" step="0.1" class="form-control" id="editWeight" required />
              </div>
              <div class="form-group">
                  <label>Height (m)</label>
                  <input type="number" step="0.01" class="form-control" id="editHeight" required />
              </div>
              <div class="form-group">
                  <label>Measurement Date</label>
                  <input type="date" class="form-control" id="editMeasurementDate" required />
              </div>
              <div class="form-group">
                  <label>Medical Recommendation</label>
                  <input type="text" class="form-control" id="editMedicalRec" />
              </div>
              <div class="form-group">
                  <label>BMI (Auto)</label>
                  <input type="text" class="form-control" id="editBmi" readonly />
              </div>
              <div class="form-group">
                  <label>Nutrition Status (Auto)</label>
                  <input type="text" class="form-control" id="editNutStatus" readonly />
              </div>
          </div>
          <div class="button-group">
              <button type="button" class="btn btn-cancel" id="cancelEdit">Cancel</button>
              <button type="submit" class="btn btn-apply">Update</button>
          </div>
      </form>
  </div>

  <!-- Filter Modal -->
  <div class="modal-overlay" id="filterModalOverlay"></div>
  <div class="modal" id="filterModal">
      <h2 class="filter-title">FILTER GROWTH DETAILS</h2>
      <form id="filterForm">
          <div class="form-grid">
              <div class="form-group">
                  <label>Child ID</label>
                  <input type="text" class="form-control" name="child_id" />
              </div>
              <div class="form-group">
                  <label>Child Name</label>
                  <input type="text" class="form-control" name="child_name" />
              </div>
              <div class="form-group">
                  <label>Measurement Date</label>
                  <input type="date" class="form-control" name="measurement_date" />
              </div>
              <div class="form-group">
                  <label>Weight</label>
                  <input type="text" class="form-control" name="filter_weight" />
              </div>
              <div class="form-group">
                  <label>BMI</label>
                  <input type="text" class="form-control" name="filter_bmi" />
              </div>
              <div class="form-group">
                  <label>Nutrition Status</label>
                  <input type="text" class="form-control" name="nutrition_status" />
              </div>
              <div class="form-group">
                  <label>Medical Recommendation</label>
                  <input type="text" class="form-control" name="medical_recommendation" />
              </div>
          </div>
          <div class="button-group">
              <button type="button" class="btn btn-cancel" id="cancelFilter">Cancel</button>
              <button type="submit" class="btn btn-apply">Apply</button>
          </div>
      </form>
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

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    // Toggle sidebar user menu
    function toggleSidebarUserMenu() {
      const menu = document.getElementById('sidebarUserMenu');
      menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }

    $(document).ready(function() {
      // Sidebar user menu toggle
      $('#sidebarUserProfile').on('click', function(e){
        e.stopPropagation();
        toggleSidebarUserMenu();
      });
      $(document).on('click', function(){
        $('#sidebarUserMenu').hide();
      });

      // Show/Hide Add Growth Modal
      $("#openAddModal").click(function(){
          $("#addModalOverlay, #addModal").fadeIn();
      });
      $("#cancelAdd").click(function(){
          $("#addModalOverlay, #addModal").fadeOut();
      });

      // Show/Hide Edit Growth Modal
      $("#cancelEdit").click(function(){
          $("#editModalOverlay, #editModal").fadeOut();
      });

      // Show/Hide Filter Modal
      $("#tableFilterIcon").click(function(){
          $("#filterModalOverlay, #filterModal").fadeIn();
      });
      $("#cancelFilter").click(function(){
          $("#filterModalOverlay, #filterModal").fadeOut();
      });

      // Delete Confirmation Modal
      let deleteId = null;
      $(document).on("click", ".delete-btn", function(){
          deleteId = $(this).data("id");
          $("#deleteConfirmModalOverlay, #deleteConfirmModal").fadeIn();
      });
      $("#cancelDelete").click(function(){
          $("#deleteConfirmModalOverlay, #deleteConfirmModal").fadeOut();
      });
      $("#confirmDelete").click(function(){
          if(!deleteId) return;
          $.post("growth_details.php", { delete_growth_id: deleteId }, function(res){
              console.log("Delete response:", res);
              if(res.status === "success"){
                  showAlert("success", res.message);
                  setTimeout(() => { location.reload(); }, 1000);
              } else {
                  showAlert("error", res.message);
              }
          }, "json").fail(function(jqXHR, textStatus, errorThrown){
              console.error("Error deleting record:", textStatus, errorThrown);
          });
          $("#deleteConfirmModalOverlay, #deleteConfirmModal").fadeOut();
      });

      // Fetch child info for Add Form (real-time)
      $("#addChildId").on("input", function(){
          let cid = $(this).val().trim();
          if(cid) {
              $.ajax({
                  url: "growth_details.php",
                  method: "GET",
                  data: { fetch_child: true, child_id: cid },
                  dataType: "json",
                  success: function(res){
                      console.log("Child fetch response:", res);
                      if(res.status === "success"){
                          $("#addChildName").val(res.child_name);
                          $("#addBirthWeight").val(res.birth_weight);
                          $("#addBirthHeight").val(res.birth_height);
                      } else {
                          $("#addChildName").val("");
                          $("#addBirthWeight").val("");
                          $("#addBirthHeight").val("");
                          console.error("Child fetch error:", res.message);
                      }
                  },
                  error: function(jqXHR, textStatus, errorThrown){
                      console.error("AJAX error fetching child info:", textStatus, errorThrown);
                      $("#addChildName").val("");
                      $("#addBirthWeight").val("");
                      $("#addBirthHeight").val("");
                  }
              });
          } else {
              $("#addChildName").val("");
              $("#addBirthWeight").val("");
              $("#addBirthHeight").val("");
          }
      });

      // Add Growth Form submission (AJAX)
      $("#addGrowthForm").submit(function(e){
          e.preventDefault();
          let formData = $(this).serialize();
          console.log("Submitting addGrowthForm with data:", formData);
          $.ajax({
              url: "growth_details.php",
              method: "POST",
              data: formData,
              dataType: "json",
              success: function(res){
                  console.log("Add record response:", res);
                  if(res.status === "success"){
                      showAlert("success", res.message);
                      setTimeout(() => { location.reload(); }, 1000);
                  } else {
                      showAlert("error", res.message);
                  }
              },
              error: function(jqXHR, textStatus, errorThrown){
                  console.error("Error adding growth record:", textStatus, errorThrown);
                  showAlert("error", "Error adding growth record");
              }
          });
          $("#addModalOverlay, #addModal").fadeOut();
      });

      // Edit button: fetch record for editing
      $(document).on("click", ".edit-btn", function(){
          let gId = $(this).data("id");
          console.log("Fetching record for edit, growth_id:", gId);
          $.ajax({
              url: "growth_details.php",
              method: "GET",
              data: { fetch_growth: true, growth_id: gId },
              dataType: "json",
              success: function(res){
                  console.log("Fetch record for edit response:", res);
                  if(res.status === "success"){
                      let d = res.data;
                      $("#editGrowthId").val(d.growth_id);
                      $("#editChildName").val(d.child_name);
                      $("#editWeight").val(d.weight);
                      $("#editHeight").val(d.height);
                      $("#editMedicalRec").val(d.medical_recommendation);
                      $("#editMeasurementDate").val(d.measurement_date);
                      $("#editBmi").val(d.bmi);
                      $("#editNutStatus").val(d.nutrition_status);
                      $("#editModalOverlay, #editModal").fadeIn();
                  } else {
                      showAlert("error", res.message);
                  }
              },
              error: function(jqXHR, textStatus, errorThrown){
                  console.error("Error fetching record for edit:", textStatus, errorThrown);
                  showAlert("error", "Error fetching record");
              }
          });
      });

      // Recalculate BMI & Nutrition in Edit Form
      function recalcEditBmi() {
          let w = parseFloat($("#editWeight").val());
          let h = parseFloat($("#editHeight").val());
          if(w > 0 && h > 0) {
              let b = (w / (h * h)).toFixed(2);
              $("#editBmi").val(b);
              let status = "";
              if(b < 18.5) status = "Underweight";
              else if(b < 25) status = "Normal";
              else if(b < 30) status = "Overweight";
              else status = "Obese";
              $("#editNutStatus").val(status);
          } else {
              $("#editBmi").val("");
              $("#editNutStatus").val("");
          }
      }
      $("#editWeight, #editHeight").on("input", recalcEditBmi);

      // Update Growth Form submission (AJAX)
      $("#editGrowthForm").submit(function(e){
          e.preventDefault();
          let growthId = $("#editGrowthId").val();
          let weight   = $("#editWeight").val();
          let height   = $("#editHeight").val();
          let medRec   = $("#editMedicalRec").val();
          let dateMeas = $("#editMeasurementDate").val();
          console.log("Submitting editGrowthForm for growth_id:", growthId);
          $.ajax({
              url: "growth_details.php",
              method: "POST",
              data: {
                  update_growth_id: growthId,
                  update_weight: weight,
                  update_height: height,
                  update_medical_recommendation: medRec,
                  update_measurement_date: dateMeas
              },
              dataType: "json",
              success: function(res){
                  console.log("Update record response:", res);
                  if(res.status === "success"){
                      showAlert("success", res.message);
                      setTimeout(() => { location.reload(); }, 1000);
                  } else {
                      showAlert("error", res.message);
                  }
              },
              error: function(jqXHR, textStatus, errorThrown){
                  console.error("Error updating record:", textStatus, errorThrown);
                  showAlert("error", "Error updating record");
              }
          });
          $("#editModalOverlay, #editModal").fadeOut();
      });

      // Search with debounce
      let searchTimeout = null;
      $("#searchInput").on("input", function(){
          let val = $(this).val().trim();
          clearTimeout(searchTimeout);
          searchTimeout = setTimeout(function(){
              doFilterSearch(val);
          }, 300);
      });

      // Filter Form submission (AJAX)
      $("#filterForm").submit(function(e){
          e.preventDefault();
          let formData = $(this).serialize();
          doFilterSearch("", formData);
          $("#filterModalOverlay, #filterModal").fadeOut();
      });

      // Combined AJAX for search + filter
      function doFilterSearch(searchText, extraParams = "") {
          let data = "ajax=true";
          if(searchText) data += "&search=" + encodeURIComponent(searchText);
          if(extraParams) data += "&" + extraParams;
          $.ajax({
              url: "growth_details.php",
              method: "GET",
              data: data,
              dataType: "json",
              success: function(rows){
                  updateTable(rows);
              },
              error: function(jqXHR, textStatus, errorThrown){
                  console.error("Error in doFilterSearch:", textStatus, errorThrown);
              }
          });
      }

      function updateTable(rows) {
          let tb = $("#tableBody");
          tb.empty();
          if(!rows || rows.length === 0) {
              tb.append(`<tr><td colspan="12">No records found</td></tr>`);
              return;
          }
          rows.forEach(function(gr){
              let tr = `
                <tr data-id="${gr.growth_id}">
                  <td>${gr.growth_id}</td>
                  <td>${gr.child_id}</td>
                  <td>${gr.child_name}</td>
                  <td>${gr.birth_height}</td>
                  <td>${gr.birth_weight}</td>
                  <td>${gr.height}</td>
                  <td>${gr.weight}</td>
                  <td>${gr.measurement_date}</td>
                  <td>${gr.bmi}</td>
                  <td>${gr.nutrition_status}</td>
                  <td>${gr.medical_recommendation}</td>
                  <td class="action-icons">
                    <i class="fas fa-edit edit-btn" data-id="${gr.growth_id}"></i>
                    <i class="fas fa-trash delete-icon delete-btn" data-id="${gr.growth_id}"></i>
                  </td>
                </tr>
              `;
              tb.append(tr);
          });
      }

      // Alert function
      function showAlert(type, message){
          let box = $("#alertBox");
          box.removeClass("alert-success alert-error");
          if(type === "success") {
              box.addClass("alert-success");
          } else {
              box.addClass("alert-error");
          }
          box.text(message).fadeIn();
          setTimeout(function(){ box.fadeOut(); }, 3000);
      }
    });

    // Hide sidebar user menu if clicked outside
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
<?php
ob_end_flush();
?>
