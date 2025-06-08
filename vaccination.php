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

// *******************************
// Handle DELETE Request (AJAX)
// *******************************
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_vaccinationid'])) {
    $vaccinationid = $_POST['delete_vaccinationid'];

    try {
        // Prepare and execute the delete query
        $stmt = $pdo->prepare("DELETE FROM vaccination WHERE vaccinationid = ?");
        $stmt->execute([$vaccinationid]);

        // Success response
        echo json_encode(["success" => true]);
        exit;
    } catch (PDOException $e) {
        echo json_encode(["error" => "Delete failed: " . $e->getMessage()]);
        exit;
    }
}

// *******************************
// Handle UPDATE Request (AJAX)
// *******************************
// We assume that update requests carry vaccinationid, vaccinationname, batchno, and sideeffects
// and do NOT include a child_id field (which is present only for adding a new record)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["vaccinationid"]) && isset($_POST["vaccinationname"]) && isset($_POST["batchno"]) && isset($_POST["sideeffects"]) && isset($_POST["dosageno"]) && isset($_POST["administrativeroute"]) && isset($_POST["healthcare_provider"]) && !isset($_POST["child_id"])) {
    $vaccinationid   = $_POST["vaccinationid"];
    $vaccinationname = $_POST["vaccinationname"];
    $batchno         = $_POST["batchno"];
    $sideeffects     = $_POST["sideeffects"];
    $dosageno = $_POST["dosageno"];
    $administrativeroute = $_POST["administrativeroute"];
    $healthcare_provider = $_POST["healthcare_provider"];

    try {
        // Begin transaction for atomic update
        $pdo->beginTransaction();
        // Prepare and execute the update query
        $stmt = $pdo->prepare("UPDATE vaccination SET vaccinationname = ?, batchno = ?, sideeffects = ?, dosageno = ?, administrativeroute = ?, healthcare_provider = ? WHERE vaccinationid = ?");
        $stmt->execute([$vaccinationname, $batchno, $sideeffects, $dosageno, $administrativeroute, $healthcare_provider, $vaccinationid]);
        $pdo->commit();

        echo json_encode(["success" => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(["error" => "Update failed: " . $e->getMessage()]);
    }
    exit;
}

// *******************************
// Handle ADD (Insert) Request
// *******************************
// The add form sends the following fields: child_id, vaccinationname, datavaccination, dosageno,
// administrativeroute, healthcare_provider, batchno, sideeffects, and status.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['child_id']) && isset($_POST['vaccinationname']) && isset($_POST['datavaccination']) && isset($_POST['dosageno']) && isset($_POST['administrativeroute']) && isset($_POST['healthcare_provider']) && isset($_POST['batchno']) && isset($_POST['sideeffects']) && isset($_POST['status'])) {
    try {
        $query = "INSERT INTO vaccination (child_id, vaccinationname, datavaccination, dosageno, administrativeroute, healthcare_provider, batchno, sideeffects, status)
                  VALUES (:child_id, :vaccinationname, :datavaccination, :dosageno, :administrativeroute, :healthcare_provider, :batchno, :sideeffects, :status)";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':child_id'          => $_POST['child_id'],
            ':vaccinationname'   => $_POST['vaccinationname'],
            ':datavaccination'   => $_POST['datavaccination'],
            ':dosageno'          => $_POST['dosageno'],
            ':administrativeroute'=> $_POST['administrativeroute'],
            ':healthcare_provider'=> $_POST['healthcare_provider'],
            ':batchno'           => $_POST['batchno'],
            ':sideeffects'       => $_POST['sideeffects'],
            ':status'            => $_POST['status']
        ]);

        header("Location: " . $_SERVER['PHP_SELF']);
    } catch(PDOException $e) {
        die("Error: " . $e->getMessage());
    }
    exit;
}

// *******************************
// GET Request: Fetch Data to Display
// *******************************
$query = "SELECT * FROM vaccination WHERE status = 'given'";
$query = "SELECT 
    vaccinationid, 
    child_id, 
    vaccinationname, 
    datavaccination, 
    dosageno, 
    administrativeroute, 
    healthcare_provider, 
    batchno, 
    sideeffects, 
    status 
FROM vaccination 
WHERE status = 'given'";

$stmt = $pdo->query($query);
$vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$childQuery = "SELECT child_id FROM child";
$childStmt = $pdo->query($childQuery);
$children = $childStmt->fetchAll(PDO::FETCH_ASSOC);

$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Sarah Smith';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Doctor';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>KidsGrow - Child Health Records</title>

  <!-- Use Poppins font -->
  <link rel="preconnect" href="https://fonts.gstatic.com">
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
      padding-left: 10px;
      border-radius: var(--border-radius);
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

    /* Replace your existing .page-button, .upcoming-btn, and .given-btn styles with these: */

.page-button {
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
    text-decoration: none;        /* remove underline from links */
    display: inline-block;        /* make links behave like buttons */
    text-align: center;
}

/* Toggle button (active) */
.toggle-btn.active {
    background-color: #274FB4;    /* solid blue background */
    color: #fff;                  /* white text */
    border: 1px solid #274FB4;    /* optional to keep border for consistency */
}

/* Remove default button styling */
.toggle-btn button {
    background: none;
    border: none;
    color: inherit;
    font: inherit;
    padding: 0;
    cursor: pointer;
}

    .page-button{
      margin-top: 19px;
      margin-bottom: 16px;
    }

    /* Child Profiles Box with sticky header */
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
    /* Scrollable table container */
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

    .filter-title {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 30px;
    }
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
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
    .radio-group {
      display: flex;
      gap: 20px;
    }
    .radio-option {
      display: flex;
      align-items: center;
    }
    .radio-option input[type="radio"] {
      margin-right: 5px;
    }

    .hide-columns {
      margin-top: 30px;
    }
    .column-list {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
    }
    .column-item {
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 14px;
    }
    .checkbox {
      width: 18px;
      height: 18px;
      border: 1px solid #ddd;
      border-radius: 3px;
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
    .btn-apply {
      background: #3366cc;
      color: white;
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
          <a href="#" class="menu-item">
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
          <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="menu-item">
              <i class="fas fa-syringe"></i>
              <span>Vaccination</span>
          </a>
          <a href="#" class="menu-item">
              <i class="fas fa-home"></i>
              <span>Home Visit</span>
          </a>
          <a href="#" class="menu-item">
              <i class="fas fa-box"></i>
              <span>Thriposha Distribution</span>
          </a>
          <a href="#" class="menu-item">
              <i class="fas fa-chart-line"></i>
              <span>Growth Details</span>
          </a>
          <!-- Additional link for SuperAdmin (dummy) -->
      </div>
      <!-- User Profile at Bottom -->
      <div class="sidebar-user-profile" id="sidebarUserProfile">
          <img src="https://placehold.co/45x45" alt="User">
          <div class="sidebar-user-info">
              <span class="sidebar-user-name">Sarah Smith</span>
              <span class="sidebar-user-role">Doctor</span>
          </div>
          <div class="sidebar-user-menu" id="sidebarUserMenu">
              <a href="#">Sign Out</a>
          </div>
      </div>
  </div>

  <!-- Main Content -->
  <div class="main-content">
      <!-- Top bar with search and add child -->
      <div class="search-bar">
          <div class="search-container">
              <input type="text" id="searchInput" placeholder="Search by name or hospital...">
              <i class="fas fa-search search-icon"></i>
          </div>
          <!-- <button class="add-child-btn" id="openAddChildModal">
              <i class="fas fa-plus"></i> Add Vaccine
          </button> -->
      </div>

      <div class="page-button">
        <a href="upcoming_vaccination.php" class="toggle-btn">
            <button>Upcoming</button>
        </a>
        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="toggle-btn active">
            <button>Given</button>
        </a>
        <a href="missed_vaccination.php" class="toggle-btn">
            <button>Missed</button>
        </a>
    </div>

      <!-- Child Profiles Table with sticky header -->
      <div class="child-profiles">
          <div class="sticky-header">
              <h2>Vaccination</h2>
          </div>
          <div class="table-container">
              <table>
                  <thead>
                      <tr>
                        <th>Vaccination ID</th>
                        <th>Child ID</th>
                        <th>Vaccination Name</th>
                        <th>Date of Vaccination</th> 
                        <th>Dosage Number</th> 
                        <th>Administration Route</th> 
                        <th>Healthcare Provider</th> 
                        <th>Batch No.</th> 
                        <th>Side Effects Noted</th> 
                        <th>Status</th> 
                        <th></th>
                      </tr>
                  </thead>
                  <tbody id="tableBody">
                      <?php foreach ($vaccinations as $row): ?>
                      <tr data-id="<?= htmlspecialchars($row['vaccinationid']) ?>"
                          data-child_id="<?= htmlspecialchars($row['child_id']) ?>"
                          data-vaccinationname="<?= htmlspecialchars($row['vaccinationname']) ?>"
                          data-dosageno="<?= htmlspecialchars($row['dosageno']) ?>"
                          data-administrativeroute="<?= htmlspecialchars($row['administrativeroute']) ?>"
                          data-healthcare_provider="<?= htmlspecialchars($row['healthcare_provider']) ?>"
                          data-batchno="<?= htmlspecialchars($row['batchno']) ?>"
                          data-sideeffects="<?= htmlspecialchars($row['sideeffects']) ?>">
                          <td><?= htmlspecialchars($row['vaccinationid']) ?></td>
                          <td><?= htmlspecialchars($row['child_id']) ?></td>
                          <td><?= htmlspecialchars($row['vaccinationname']) ?></td>
                          <td><?= htmlspecialchars($row['datavaccination']) ?></td>
                          <td><?= htmlspecialchars($row['dosageno']) ?></td>
                          <td><?= htmlspecialchars($row['administrativeroute']) ?></td>
                          <td><?= htmlspecialchars($row['healthcare_provider']) ?></td>
                          <td><?= htmlspecialchars($row['batchno']) ?></td>
                          <td><?= htmlspecialchars($row['sideeffects']) ?></td>
                          <td><?= htmlspecialchars($row['status']) ?></td>
                          <td class="action-icons">
                              <i class="fas fa-edit edit-btn" data-id="<?= htmlspecialchars($row['vaccinationid']) ?>"></i>
                              <i class="fas fa-trash delete-icon delete-btn" data-id="<?= htmlspecialchars($row['vaccinationid']) ?>"></i>
                          </td>
                      </tr>
                      <?php endforeach; ?>
                  </tbody>
              </table>
          </div>
      </div>
  </div>

  <!-- Update Modal -->
  <div class="modal-overlay" id="modalOverlay"></div>
  <div class="modal" id="editModal">
      <h3 style="padding-bottom: 20px;">UPDATE VACCINATION</h3>
      <form id="editForm">
          <input type="hidden" id="editChildId">
          <div class="form-group" style="display: flex; align-items: center;">
              <label style="margin-bottom: 0; flex: 0 0 80px; margin-right: 3px;">Child ID:</label>
              <input type="text" class="form-control" name="child_id" readonly style="border: none; background: none; padding: 0; margin: 0; box-shadow: none; font-weight: 600;">
          </div>
          <div class="form-group">
              <label>Vaccination Name</label>
              <input type="text" class="form-control" name="vaccinationname">
          </div>
          <div class="form-group">
            <label>Dosage Number</label>
            <input type="text" class="form-control" name="dosageno">
          </div>
          <div class="form-group">
              <label>Administration Route</label>
              <input type="text" class="form-control" name="administrativeroute">
          </div>
          <div class="form-group">
              <label>Healthcare Provider</label>
              <input type="text" class="form-control" name="healthcare_provider">
          </div>
          <div class="form-group">
              <label>Batch No</label>
              <input type="text" class="form-control" name="batchno">
          </div>
          <div class="form-group">
              <label>Adverse events following immunization</label>
              <input type="text" class="form-control" name="sideeffects">
          </div>
          <div class="button-group">
              <button type="button" class="btn btn-secondary" id="cancelEdit">Cancel</button>
              <button type="submit" class="btn btn-primary">Save </button>
          </div>
      </form>
  </div>

  <!-- Add Child Modal -->
  <!-- <div class="modal-overlay" id="addChildModalOverlay"></div>
  <div class="modal" id="addChildModal">
      <h2 class="filter-title">ADD NEW VACCINATION</h2>
      <form id="addChildForm" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
          <div class="form-grid">
              <div class="form-group">
                  <label>Child ID</label>
                  <select class="form-control" name="child_id">
                      <option value="">Select Child ID</option>
                      <?php foreach ($children as $child): ?>
                          <option value="<?= htmlspecialchars($child['child_id']) ?>">
                              <?= htmlspecialchars($child['child_id']) ?>
                          </option>
                      <?php endforeach; ?>
                  </select>
              </div>
              <div class="form-group">
                  <label>Vaccination Name</label>
                  <input type="text" class="form-control" name="vaccinationname">
              </div>
              <div class="form-group">
                  <label>Date of Vaccination</label>
                  <input type="date" class="form-control" name="datavaccination">
              </div>
              <div class="form-group">
                  <label>Dosage Number</label>
                  <input type="text" class="form-control" name="dosageno">
              </div>
              <div class="form-group">
                  <label>Administration Route</label>
                  <input type="text" class="form-control" name="administrativeroute">
              </div>
              <div class="form-group">
                  <label>Healthcare Provider</label>
                  <input type="text" class="form-control" name="healthcare_provider">
              </div>
              <div class="form-group">
                  <label>Batch No.</label>
                  <input type="text" class="form-control" name="batchno">
              </div>
              <div class="form-group">
                  <label>Side Effects Noted</label>
                  <input type="text" class="form-control" name="sideeffects">
              </div>
              <div class="form-group">
                  <label>Status</label>
                  <select class="form-control" name="status">
                      <option value="">Select Status</option>
                      <option value="pending">Pending</option>
                      <option value="given">Given</option>
                  </select>
              </div>
          </div>
          <div class="button-group">
              <button type="button" class="btn btn-cancel" id="cancelAddChild">Cancel</button>
              <button type="submit" class="btn btn-apply">Save</button>
          </div>
      </form>
  </div> -->

  <!-- Delete Confirmation Modal -->
  <div class="modal-overlay" id="deleteConfirmModalOverlay"></div>
  <div class="modal" id="deleteConfirmModal">
      <h2>Confirm Delete</h2>
      <p>Are you sure you want to delete Record?</p>
      <div class="button-group">
          <button type="button" class="btn btn-secondary" id="cancelDelete">Cancel</button>
          <button type="button" class="btn btn-primary" id="confirmDelete">Delete</button>
      </div>
  </div>

  <!-- Alert Box -->
  <div class="alert" id="alertBox"></div>

  <!-- jQuery and Script -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    // Toggle user menu in the sidebar
    function toggleSidebarUserMenu() {
      const menu = document.getElementById('sidebarUserMenu');
      menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }

    function showAlert(type, message) {
      const alertBox = $("#alertBox");
      alertBox.removeClass("alert-success alert-error");
      if (type === "success") {
          alertBox.addClass("alert-success");
      } else {
          alertBox.addClass("alert-error");
      }
      alertBox.text(message);
      alertBox.fadeIn().delay(2000).fadeOut();
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

      // Toggle Add Child Modal
      $("#openAddChildModal").click(function(){
          $("#addChildModalOverlay, #addChildModal").fadeIn();
      });
      $("#cancelAddChild").click(function(){
          $("#addChildModalOverlay, #addChildModal").fadeOut();
          $("#addChildForm")[0].reset();
      });

      // Dummy AJAX search simulation
      $("#searchInput").on("input", function(){
          console.log("Searching...");
      });
      
      // Delete Confirmation Modal
      $(document).on("click", ".delete-btn", function() {
          let vaccinationId = $(this).data("id");
          $("#deleteConfirmModal").data("vaccinationId", vaccinationId);
          $("#deleteConfirmModalOverlay, #deleteConfirmModal").fadeIn();
      });

      $("#cancelDelete").click(function() {
          $("#deleteConfirmModalOverlay, #deleteConfirmModal").fadeOut();
      });

      $("#confirmDelete").click(function() {
          let vaccinationId = $("#deleteConfirmModal").data("vaccinationId");

          $.ajax({
              url: "<?php echo $_SERVER['PHP_SELF']; ?>",
              type: "POST",
              data: { delete_vaccinationid: vaccinationId },
              dataType: "json",
              success: function(response) {
                  if (response.success) {
                      $("tr[data-id='" + vaccinationId + "']").remove();
                      $("#deleteConfirmModalOverlay, #deleteConfirmModal").fadeOut();
                      showAlert("success", "Record deleted successfully!");
                  } else {
                      showAlert("error", response.error);
                  }
              },
              error: function() {
                  showAlert("error", "Delete failed. Please try again.");
              }
          });
      });

      // Open Edit Modal
      $(document).on("click", ".edit-btn", function(){
          let row = $(this).closest("tr");
          $("#editForm input[name='child_id']").val(row.data("child_id"));
          $("#editForm input[name='vaccinationname']").val(row.data("vaccinationname"));
          $("#editForm input[name='dosageno']").val(row.data("dosageno"));
          $("#editForm input[name='administrativeroute']").val(row.data("administrativeroute"));
          $("#editForm input[name='healthcare_provider']").val(row.data("healthcare_provider"));
          $("#editForm input[name='batchno']").val(row.data("batchno"));
          $("#editForm input[name='sideeffects']").val(row.data("sideeffects"));
          $("#editForm").data("id", row.data("id"));
          $("#modalOverlay, #editModal").fadeIn();
      });

      // Close the update modal and reset the form
      $("#cancelEdit").click(function(){
          $("#modalOverlay, #editModal").fadeOut();
          $("#editForm")[0].reset();
      });

      // Update AJAX
      $("#editForm").submit(function(e){
          e.preventDefault();

          let vaccinationid   = $("#editForm").data("id");
          let vaccinationname = $("#editForm input[name='vaccinationname']").val();
          let batchno         = $("#editForm input[name='batchno']").val();
          let sideeffects     = $("#editForm input[name='sideeffects']").val();
          let dosageno = $("#editForm input[name='dosageno']").val();
          let administrativeroute = $("#editForm input[name='administrativeroute']").val();
          let healthcare_provider = $("#editForm input[name='healthcare_provider']").val();

          $.ajax({
              url: "<?php echo $_SERVER['PHP_SELF']; ?>",
              type: "POST",
              data: {
                  vaccinationid: vaccinationid,
                  vaccinationname: vaccinationname,
                  batchno: batchno,
                  sideeffects: sideeffects,
                  dosageno: dosageno,
                  administrativeroute: administrativeroute,
                  healthcare_provider: healthcare_provider
              },
              dataType: "json",
              success: function(response) {
                  if (response.success) {
                      let row = $("tr[data-id='" + vaccinationid + "']");
                      row.find("td:nth-child(3)").text(vaccinationname);
                      row.find("td:nth-child(5)").text(dosageno);
                      row.find("td:nth-child(6)").text(administrativeroute);
                      row.find("td:nth-child(7)").text(healthcare_provider);
                      row.find("td:nth-child(8)").text(batchno);
                      row.find("td:nth-child(9)").text(sideeffects);
                      $("#modalOverlay, #editModal").fadeOut();
                      $("#editForm")[0].reset();
                  } else {
                      alert(response.error);
                  }
              },
              error: function() {
                  alert("Update failed. Please try again.");
              }
          });
      });
    });

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
