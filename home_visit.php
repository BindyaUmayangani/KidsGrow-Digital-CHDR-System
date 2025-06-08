<?php
session_start(); // Start the session

// Check if the user is logged in and allowed (Admin or SuperAdmin)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: signin.php');
    exit;
}
$allowed_roles = ['Admin','SuperAdmin', 'Doctor'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    header('Location: unauthorized.php');
    exit;
}

// Database connection
$dsn         = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user     = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Set user name and role if available
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Sarah Smith';
$userRole = isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Doctor';

/**
 * sendUpcomingVisitEmail
 * Called asynchronously after the DB is updated.
 * Retrieves the parent's email from a join: child → parent → users.
 * 
 * This version sends the email content as plain text.
 */
function sendUpcomingVisitEmail($pdo, $child_id, $visit_date, $isReschedule = false) {
    // Get parent email and child name
    $sql = "SELECT u.email, c.name AS child_name
            FROM child c
            JOIN parent p ON c.parent_id = p.parent_id
            JOIN users u ON p.user_id = u.id
            WHERE c.child_id = :child_id
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':child_id' => $child_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row || empty($row['email'])) return;
    
    $parentEmail = $row['email'];
    $childName   = $row['child_name'];
    
    // Prepare plain text email content (subject and body in one plain text message)
    $subject = $isReschedule ? "Home Visit Rescheduled" : "Upcoming Home Visit";
    $body    = $isReschedule
        ? "Dear Parent,\n\nThe home visit for your child ($childName) has been RESCHEDULED to: $visit_date.\nPlease be available.\n\nThank you,\nKidsGrow Team"
        : "Dear Parent,\n\nA home visit for your child ($childName) has been SCHEDULED on: $visit_date.\nPlease be available.\n\nThank you,\nKidsGrow Team";
    
    // Combine subject and body into one plain text message.
    $plainText = "Subject: " . $subject . "\n\n" . $body;
    
    $ch = curl_init("http://54.179.72.79:5001/send-email");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $plainText,
        CURLOPT_HTTPHEADER => [
            'Content-Type: text/plain',
            'Content-Length: ' . strlen($plainText)
        ],
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FAILONERROR => true
    ]);
    
    try {
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            error_log("Email API error: " . curl_error($ch));
        }
    } catch (Exception $e) {
        error_log("Email send exception: " . $e->getMessage());
    } finally {
        curl_close($ch);
    }
}

// -------------------------------------------------------
// AJAX endpoints (filtering code omitted for brevity)
// -------------------------------------------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === 'true') {
    // ... your filtering code here (unchanged) ...
    header('Content-Type: application/json');
    echo json_encode($rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    // 2a) Add Upcoming
    if ($action === 'add_upcoming') {
        $child_id   = $_POST['child_id'] ?? '';
        $visit_date = $_POST['visit_date'] ?? '';
        if (!$child_id || !$visit_date) {
            echo json_encode(['status' => 'error', 'message' => 'Child ID and Visit Date are required.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO home_visit (child_id, visit_date, visited)
                                   VALUES (:child_id, :visit_date, FALSE)
                                   RETURNING visit_id");
            $stmt->execute([':child_id' => $child_id, ':visit_date' => $visit_date]);
            $visit_id = $stmt->fetchColumn();
            echo json_encode(['status' => 'success', 'message' => 'Upcoming visit added!', 'visit_id' => $visit_id]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // 2b) Update Upcoming
    if ($action === 'update_upcoming') {
        $visit_id   = $_POST['visit_id'] ?? '';
        $visit_date = $_POST['visit_date'] ?? '';
        if (!$visit_id || !$visit_date) {
            echo json_encode(['status' => 'error', 'message' => 'Visit ID and Visit Date are required.']);
            exit;
        }
        try {
            $stmtCheck = $pdo->prepare("SELECT child_id FROM home_visit WHERE visit_id = :vid AND visited = FALSE");
            $stmtCheck->execute([':vid' => $visit_id]);
            $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                echo json_encode(['status' => 'error', 'message' => 'Record not found or already visited.']);
                exit;
            }
            $child_id = $row['child_id'];
            $stmt = $pdo->prepare("UPDATE home_visit SET visit_date = :vdate, updated_at = NOW() WHERE visit_id = :vid AND visited = FALSE");
            $stmt->execute([':vdate' => $visit_date, ':vid' => $visit_id]);
            echo json_encode(['status' => 'success', 'message' => 'Upcoming visit updated!', 'child_id' => $child_id]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // 2c) Delete Upcoming
    if ($action === 'delete_upcoming') {
        $visit_id = $_POST['visit_id'] ?? '';
        if (!$visit_id) {
            echo json_encode(['status' => 'error', 'message' => 'Visit ID is required.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM home_visit WHERE visit_id = :vid AND visited = FALSE");
            $stmt->execute([':vid' => $visit_id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Upcoming Visit deleted!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Record not found or already visited.']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // 2d) Add Visited
    if ($action === 'add_visited') {
        $child_id   = $_POST['child_id'] ?? '';
        $visit_date = $_POST['visited_date'] ?? '';
        $disorders  = $_POST['disorders'] ?? '';
        $taken      = $_POST['actionTaken'] ?? '';
        $note       = $_POST['note'] ?? '';
        if (!$child_id || !$visit_date) {
            echo json_encode(['status' => 'error', 'message' => 'Child ID and Date are required.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO home_visit (child_id, visit_date, visited, identified_disorders, action_taken, note)
                                   VALUES (:cid, :vdate, TRUE, :disorders, :taken, :note)");
            $stmt->execute([
                ':cid' => $child_id,
                ':vdate' => $visit_date,
                ':disorders' => $disorders,
                ':taken' => $taken,
                ':note' => $note
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Visited record added!']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // 2e) Update Visited
    if ($action === 'update_visited') {
        $visit_id   = $_POST['visit_id'] ?? '';
        $visit_date = $_POST['visited_date'] ?? '';
        $disorders  = $_POST['disorders'] ?? '';
        $taken      = $_POST['actionTaken'] ?? '';
        $note       = $_POST['note'] ?? '';
        if (!$visit_id || !$visit_date) {
            echo json_encode(['status' => 'error', 'message' => 'Visit ID and Date are required.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("UPDATE home_visit SET visit_date = :vdate, identified_disorders = :dis, action_taken = :act, note = :note, updated_at = NOW() WHERE visit_id = :vid AND visited = TRUE");
            $stmt->execute([
                ':vdate' => $visit_date,
                ':dis' => $disorders,
                ':act' => $taken,
                ':note' => $note,
                ':vid' => $visit_id
            ]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Visited record updated!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Record not found or not visited.']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
    // 2f) Delete Visited
    if ($action === 'delete_visited') {
        $visit_id = $_POST['visit_id'] ?? '';
        if (!$visit_id) {
            echo json_encode(['status' => 'error', 'message' => 'Visit ID is required.']);
            exit;
        }
        try {
            $stmt = $pdo->prepare("DELETE FROM home_visit WHERE visit_id = :vid AND visited = TRUE");
            $stmt->execute([':vid' => $visit_id]);
            if ($stmt->rowCount() > 0) {
                echo json_encode(['status' => 'success', 'message' => 'Visited record deleted!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Record not found or not visited.']);
            }
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
        exit;
    }
}

// 3) Asynchronous Mail Endpoint
if (isset($_GET['send_mail'])) {
    header('Content-Type: text/plain'); // Respond as plain text
    $child_id   = isset($_GET['child_id']) ? $_GET['child_id'] : '';
    $visit_date = isset($_GET['visit_date']) ? $_GET['visit_date'] : '';
    $isResch    = isset($_GET['reschedule']) ? $_GET['reschedule'] : '0';
    if (!$child_id || !$visit_date) {
        echo "Missing child_id or visit_date";
        exit;
    }
    $reschedule = ($isResch === '1');
    sendUpcomingVisitEmail($pdo, $child_id, $visit_date, $reschedule);
    echo "Email sent successfully";
    exit;
}

// -------------------------------------------------------
// On normal page load, fetch upcoming & visited
// -------------------------------------------------------
try {
    $sqlUp = "SELECT hv.*, c.name AS child_name
              FROM home_visit hv
              JOIN child c ON hv.child_id = c.child_id
              WHERE hv.visited = FALSE
              ORDER BY hv.visit_date ASC";
    $stmtUp = $pdo->query($sqlUp);
    $upcomingData = $stmtUp->fetchAll(PDO::FETCH_ASSOC);

    $sqlV = "SELECT hv.*, c.name AS child_name
             FROM home_visit hv
             JOIN child c ON hv.child_id = c.child_id
             WHERE hv.visited = TRUE
             ORDER BY hv.visit_date DESC";
    $stmtV = $pdo->query($sqlV);
    $visitedData = $stmtV->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching home visits: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>KidsGrow - Home Visit</title>
  <!-- Poppins Font -->
  <link rel="preconnect" href="https://fonts.gstatic.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css"/>
  <style>
    /* CSS styles remain unchanged */
    :root {
      --primary-color: #274FB4;
      --secondary-color: #8FC4F1;
      --text-dark: #333;
      --text-light: #666;
      --white: #fff;
      --gray-bg: #f0f0f0;
      --border-radius: 8px;
      --shadow: 0 2px 4px rgba(0,0,0,0.1);
      --danger-color: #ff4444;
      --success-color: #28a745;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
    body { background-color: var(--gray-bg); margin: 0; }
    .sidebar { position: fixed; top:0; left:0; bottom:0; width:220px; background: var(--primary-color); color: var(--white); padding: 20px; overflow-y: auto; z-index: 999; display: flex; flex-direction: column; justify-content: space-between; }
    .logo { display: flex; align-items: center; gap: 10px; margin-bottom: 40px; }
    .logo span { font-size: 24px; font-weight: 800; }
    .menu-item { display: flex; align-items: center; gap: 10px; padding: 12px 0; cursor: pointer; color: var(--white); text-decoration: none; font-size: 16px; font-weight: 700; }
    .menu-item:hover, .menu-item.active { background-color: rgba(255,255,255,0.2); padding-left: 10px; border-radius: var(--border-radius); }
    .sidebar-user-profile { display: flex; align-items: center; gap: 12px; margin-top: 40px; padding: 10px; background: rgba(255,255,255,0.2); border-radius: var(--border-radius); cursor: pointer; position: relative; }
    .sidebar-user-profile img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; }
    .sidebar-user-info { display: flex; flex-direction: column; font-size: 14px; color: var(--white); line-height: 1.2; }
    .sidebar-user-name { font-weight: 700; font-size: 16px; }
    .sidebar-user-role { font-weight: 400; font-size: 14px; }
    .sidebar-user-menu { display: none; position: absolute; bottom: 60px; left: 0; background: #fff; border: 1px solid #ddd; border-radius: 5px; min-width: 120px; box-shadow: var(--shadow); padding: 5px 0; color: #333; z-index: 1000; }
    .sidebar-user-menu a { display: block; padding: 8px 12px; text-decoration: none; color: #333; font-size: 14px; }
    .sidebar-user-menu a:hover { background-color: #f0f0f0; }
    .main-content { margin-left: 220px; height: 100vh; overflow-y: auto; padding: 0 20px 20px 20px; position: relative; }
    .search-bar { position: sticky; top: 0; z-index: 100; background-color: var(--gray-bg); padding: 20px 0 10px 0; display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
    .search-container { position: relative; width: 400px; }
    .search-container input { width: 100%; padding: 10px 40px 10px 10px; border-radius: 5px; border: 1px solid #ddd; }
    .search-container .search-icon { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #4a90e2; }
    .btn { padding: 10px 24px; border-radius: 5px; cursor: pointer; font-size: 14px; font-weight: 600; min-width: 120px; border: none; transition: all 0.3s ease; }
    .btn-primary { background: var(--primary-color); color: var(--white); }
    .btn-primary:hover { background: #1a3a8a; }
    .btn-outline { background: var(--white); color: var(--primary-color); border: 2px solid var(--primary-color); }
    .btn-outline:hover { background: var(--primary-color); color: var(--white); }
    .btn-danger { background: var(--danger-color); color: var(--white); }
    .btn-danger:hover { background: #cc0000; }
    .button-group { margin-bottom: 20px; display: flex; gap: 10px; }
    .child-profiles { background: var(--white); border-radius: 10px; overflow: hidden; margin-bottom: 20px; }
    .child-profiles .sticky-header { position: sticky; top: 0; z-index: 10; background: var(--white); padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; }
    .child-profiles .table-container { max-height: 600px; overflow-y: auto; overflow-x: auto; padding: 20px; }
    .child-profiles table { width: 100%; border-collapse: collapse; table-layout: auto; }
    .child-profiles th, .child-profiles td { padding: 16px 20px; text-align: center; font-size: 14px; white-space: nowrap; }
    .child-profiles th { color: #666; font-weight: 600; }
    .action-icons { display: flex; align-items: center; justify-content: center; gap: 10px; }
    .delete-icon { color: var(--danger-color); cursor: pointer; }
    .edit-icon { color: var(--primary-color); cursor: pointer; }
    .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(5px); z-index: 1000; display: none; }
    .modal { position: fixed; top: 50%; left: 50%; transform: translate(-50%,-50%); background: var(--white); padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--shadow); width: 600px; max-width: 90%; z-index: 1001; display: none; }
    .modal h2 { margin-bottom: 1.5rem; color: var(--primary-color); }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; color: var(--text-dark); }
    .form-control { width: 100%; padding: 0.8rem; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
    .modal-actions { margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end; }
    .form-columns { display: flex; gap: 20px; }
    .form-column { flex: 1; }
    .hidden { display: none; }
    .form-control:disabled { background-color: #f0f0f0; color: #666; cursor: not-allowed; }
    .filter-icon { cursor: pointer; }
    .alert { position: fixed; top: 20px; right: 20px; padding: 15px 25px; border-radius: var(--border-radius); color: #fff; font-size: 14px; font-weight: 500; z-index: 1001; display: none; }
    .alert-success { background: var(--success-color); }
    .alert-error { background: var(--danger-color); }
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
    <a href="#" class="menu-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
    <a href="child_profile.php" class="menu-item"><i class="fas fa-child"></i><span>Child Profiles</span></a>
    <a href="parent_profile.php" class="menu-item"><i class="fas fa-users"></i><span>Parent Profiles</span></a>
    <a href="vaccination.php" class="menu-item"><i class="fas fa-syringe"></i><span>Vaccination</span></a>
    <a href="home_visit.php" class="menu-item active"><i class="fas fa-home"></i><span>Home Visit</span></a>
    <a href="thriposha_distribution.php" class="menu-item"><i class="fas fa-box"></i><span>Thriposha Distribution</span></a>
    <a href="growth_details.php" class="menu-item"><i class="fas fa-chart-line"></i><span>Growth Details</span></a>
    <?php if ($_SESSION['user_role'] === 'SuperAdmin'): ?>
      <a href="add_admin.php" class="menu-item"><i class="fas fa-user-shield"></i><span>Add Admin</span></a>
    <?php endif; ?>
  </div>
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
<!-- MAIN CONTENT -->
<div class="main-content">
  <div class="search-bar">
    <div class="search-container">
      <input type="text" placeholder="Search by name or date..." id="searchInput">
      <i class="fas fa-search search-icon"></i>
    </div>
    <button class="btn btn-primary" id="addBtn">+ Add Home Visit</button>
  </div>
  <div class="button-group">
    <button class="btn btn-outline" id="upcomingBtn">Upcoming</button>
    <button class="btn btn-primary" id="visitedBtn">Visited</button>
  </div>
  <div class="button-group" style="margin-top:-10px; margin-bottom:20px; justify-content:flex-end;">
    <i class="fas fa-filter" id="filterIcon" style="cursor:pointer; font-size:20px;"></i>
  </div>
  <!-- UPCOMING TABLE -->
  <div class="child-profiles" id="upcomingTable">
    <div class="sticky-header">
      <h2>Home Visit - Upcoming</h2>
    </div>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Child ID</th>
            <th>Child Name</th>
            <th>Upcoming Home Visit Date</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($upcomingData)): ?>
            <?php foreach($upcomingData as $up): ?>
              <tr>
                <td><?php echo htmlspecialchars($up['child_id']); ?></td>
                <td><?php echo htmlspecialchars($up['child_name']); ?></td>
                <td><?php echo htmlspecialchars($up['visit_date']); ?></td>
                <td class="action-icons">
                  <i class="fas fa-edit edit-icon"
                     data-visit-id="<?php echo $up['visit_id']; ?>"
                     data-child-id="<?php echo $up['child_id']; ?>"
                     data-child-name="<?php echo htmlspecialchars($up['child_name']); ?>"
                     data-visit-date="<?php echo $up['visit_date']; ?>"
                     data-type="upcoming"></i>
                  <i class="fas fa-trash delete-icon"
                     data-visit-id="<?php echo $up['visit_id']; ?>"
                     data-type="upcoming"></i>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4">No Upcoming Visits</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <!-- VISITED TABLE -->
  <div class="child-profiles hidden" id="visitedTable">
    <div class="sticky-header">
      <h2>Home Visit - Visited</h2>
    </div>
    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>Child ID</th>
            <th>Child Name</th>
            <th>Date</th>
            <th>Identified Disorders</th>
            <th>Action Taken</th>
            <th>Note</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!empty($visitedData)): ?>
            <?php foreach($visitedData as $vd): ?>
              <tr>
                <td><?php echo htmlspecialchars($vd['child_id']); ?></td>
                <td><?php echo htmlspecialchars($vd['child_name']); ?></td>
                <td><?php echo htmlspecialchars($vd['visit_date']); ?></td>
                <td><?php echo htmlspecialchars($vd['identified_disorders']); ?></td>
                <td><?php echo htmlspecialchars($vd['action_taken']); ?></td>
                <td><?php echo htmlspecialchars($vd['note']); ?></td>
                <td class="action-icons">
                  <i class="fas fa-edit edit-icon"
                     data-visit-id="<?php echo $vd['visit_id']; ?>"
                     data-child-id="<?php echo $vd['child_id']; ?>"
                     data-child-name="<?php echo htmlspecialchars($vd['child_name']); ?>"
                     data-visit-date="<?php echo $vd['visit_date']; ?>"
                     data-disorders="<?php echo htmlspecialchars($vd['identified_disorders']); ?>"
                     data-action="<?php echo htmlspecialchars($vd['action_taken']); ?>"
                     data-note="<?php echo htmlspecialchars($vd['note']); ?>"
                     data-type="visited"></i>
                  <i class="fas fa-trash delete-icon"
                     data-visit-id="<?php echo $vd['visit_id']; ?>"
                     data-type="visited"></i>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="7">No Visited Records</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Filter Modal -->
<div class="modal-overlay" id="filterModalOverlay"></div>
<div class="modal" id="filterModal">
  <h2>Filter Home Visits</h2>
  <form id="filterForm">
    <div class="form-columns">
      <div class="form-column">
        <div class="form-group">
          <label>Child ID</label>
          <input type="text" class="form-control" name="child_id">
        </div>
        <div class="form-group">
          <label>Child Name</label>
          <input type="text" class="form-control" name="child_name">
        </div>
        <div class="form-group">
          <label>Visit Date</label>
          <input type="date" class="form-control" name="visit_date">
        </div>
      </div>
      <div class="form-column">
        <div class="form-group">
          <label>Identified Disorders</label>
          <input type="text" class="form-control" name="disorders">
        </div>
        <div class="form-group">
          <label>Action Taken</label>
          <input type="text" class="form-control" name="action_taken">
        </div>
        <div class="form-group">
          <label>Note</label>
          <input type="text" class="form-control" name="note">
        </div>
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-outline" id="cancelFilter">Cancel</button>
      <button type="submit" class="btn btn-primary">Apply</button>
    </div>
  </form>
</div>

<!-- ADD UPCOMING MODAL -->
<div class="modal-overlay" id="addUpcomingOverlay"></div>
<div class="modal" id="addUpcomingModal">
  <h2>Add Upcoming Visit</h2>
  <form id="addUpcomingForm">
    <div class="form-columns">
      <div class="form-column">
        <div class="form-group">
          <label>Child ID</label>
          <input type="text" class="form-control" name="child_id" required>
        </div>
        <div class="form-group">
          <label>Upcoming Home Visit Date</label>
          <input type="date" class="form-control" name="visit_date" required>
        </div>
      </div>
      <div class="form-column">
        <div class="form-group">
          <label>Child Name (optional display)</label>
          <input type="text" class="form-control" name="child_name">
        </div>
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-outline" id="cancelAddUpcoming">Cancel</button>
      <button type="submit" class="btn btn-primary" id="addUpcomingSaveBtn">Save</button>
    </div>
  </form>
</div>

<!-- EDIT UPCOMING MODAL -->
<div class="modal-overlay" id="editUpcomingOverlay"></div>
<div class="modal" id="editUpcomingModal">
  <h2>Update Upcoming Visit</h2>
  <form id="editUpcomingForm">
    <input type="hidden" name="visit_id" id="editUpcomingVisitId">
    <div class="form-columns">
      <div class="form-column">
        <div class="form-group">
          <label>Child Name</label>
          <input type="text" class="form-control" name="child_name" id="editUpcomingChildName" disabled>
        </div>
      </div>
      <div class="form-column">
        <div class="form-group">
          <label>Upcoming Home Visit Date</label>
          <input type="date" class="form-control" name="visit_date" id="editUpcomingDate" required>
        </div>
      </div>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-outline" id="cancelEditUpcoming">Cancel</button>
      <button type="submit" class="btn btn-primary" id="editUpcomingSaveBtn">Save</button>
    </div>
  </form>
</div>

<!-- DELETE UPCOMING MODAL -->
<div class="modal-overlay" id="deleteUpcomingOverlay"></div>
<div class="modal" id="deleteUpcomingModal">
  <h2>Confirm Delete</h2>
  <p>Are you sure you want to delete this upcoming record?</p>
  <div class="modal-actions">
    <button type="button" class="btn btn-outline" id="cancelDeleteUpcoming">Cancel</button>
    <button type="button" class="btn btn-danger" id="confirmDeleteUpcoming">Delete</button>
  </div>
</div>

<!-- ADD VISITED MODAL -->
<div class="modal-overlay" id="addVisitedOverlay"></div>
<div class="modal" id="addVisitedModal">
  <h2>Add Visited Record</h2>
  <form id="addVisitedForm">
    <div class="form-columns">
      <div class="form-column">
        <div class="form-group">
          <label>Child ID</label>
          <input type="text" class="form-control" name="child_id" required>
        </div>
        <div class="form-group">
          <label>Date</label>
          <input type="date" class="form-control" name="visited_date" required>
        </div>
      </div>
      <div class="form-column">
        <div class="form-group">
          <label>Child Name (optional display)</label>
          <input type="text" class="form-control" name="child_name">
        </div>
      </div>
    </div>
    <div class="form-columns">
      <div class="form-column">
        <div class="form-group">
          <label>Identified Disorders</label>
          <input type="text" class="form-control" name="disorders">
        </div>
      </div>
      <div class="form-column">
        <div class="form-group">
          <label>Action Taken</label>
          <input type="text" class="form-control" name="actionTaken">
        </div>
      </div>
    </div>
    <div class="form-group">
      <label>Note</label>
      <textarea class="form-control" name="note" rows="3"></textarea>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-outline" id="cancelAddVisited">Cancel</button>
      <button type="submit" class="btn btn-primary" id="addVisitedSaveBtn">Save</button>
    </div>
  </form>
</div>

<!-- EDIT VISITED MODAL -->
<div class="modal-overlay" id="editVisitedOverlay"></div>
<div class="modal" id="editVisitedModal">
  <h2>Update Visited Record</h2>
  <form id="editVisitedForm">
    <input type="hidden" name="visit_id" id="editVisitedVisitId">
    <div class="form-columns">
      <div class="form-column">
        <div class="form-group">
          <label>Child Name</label>
          <input type="text" class="form-control" name="childName" id="editVisitedChildName" disabled>
        </div>
        <div class="form-group">
          <label>Date</label>
          <input type="date" class="form-control" name="visited_date" id="editVisitedDate" required>
        </div>
      </div>
      <div class="form-column">
        <div class="form-group">
          <label>Identified Disorders</label>
          <input type="text" class="form-control" name="disorders" id="editVisitedDisorders">
        </div>
        <div class="form-group">
          <label>Action Taken</label>
          <input type="text" class="form-control" name="actionTaken" id="editVisitedAction">
        </div>
      </div>
    </div>
    <div class="form-group">
      <label>Note</label>
      <textarea class="form-control" name="note" id="editVisitedNote" rows="3"></textarea>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-outline" id="cancelEditVisited">Cancel</button>
      <button type="submit" class="btn btn-primary" id="editVisitedSaveBtn">Save</button>
    </div>
  </form>
</div>

<!-- DELETE VISITED MODAL -->
<div class="modal-overlay" id="deleteVisitedOverlay"></div>
<div class="modal" id="deleteVisitedModal">
  <h2>Confirm Delete</h2>
  <p>Are you sure you want to delete this visited record?</p>
  <div class="modal-actions">
    <button type="button" class="btn btn-outline" id="cancelDeleteVisited">Cancel</button>
    <button type="button" class="btn btn-danger" id="confirmDeleteVisited">Delete</button>
  </div>
</div>

<!-- Alert Box -->
<div class="alert" id="alertBox"></div>

<script>
  // Global flag to ensure the mail endpoint is only called once per update
  let mailSent = false;
  
  // Helper: show alert (success in green, error in red)
  function showAlert(type, message) {
    const alertBox = document.getElementById('alertBox');
    alertBox.classList.remove('alert-success', 'alert-error');
    alertBox.classList.add(type === 'success' ? 'alert-success' : 'alert-error');
    alertBox.textContent = message;
    alertBox.style.display = 'block';
    setTimeout(() => { alertBox.style.display = 'none'; }, 3000);
  }

  let currentTab = 'visited'; // default
  const upcomingBtn = document.getElementById('upcomingBtn');
  const visitedBtn = document.getElementById('visitedBtn');
  const upcomingTable = document.getElementById('upcomingTable');
  const visitedTable = document.getElementById('visitedTable');
  const addBtn = document.getElementById('addBtn');

  // Switch tabs
  upcomingBtn.addEventListener('click', () => {
    currentTab = 'upcoming';
    upcomingBtn.classList.remove('btn-outline'); 
    upcomingBtn.classList.add('btn-primary');
    visitedBtn.classList.remove('btn-primary'); 
    visitedBtn.classList.add('btn-outline');
    upcomingTable.classList.remove('hidden'); 
    visitedTable.classList.add('hidden');
  });
  visitedBtn.addEventListener('click', () => {
    currentTab = 'visited';
    visitedBtn.classList.remove('btn-outline'); 
    visitedBtn.classList.add('btn-primary');
    upcomingBtn.classList.remove('btn-primary'); 
    upcomingBtn.classList.add('btn-outline');
    visitedTable.classList.remove('hidden'); 
    upcomingTable.classList.add('hidden');
  });

  // Toggle user menu
  const sidebarUserProfile = document.getElementById('sidebarUserProfile');
  const sidebarUserMenu = document.getElementById('sidebarUserMenu');
  sidebarUserProfile.addEventListener('click', (e) => {
    e.stopPropagation();
    sidebarUserMenu.style.display = (sidebarUserMenu.style.display === 'block') ? 'none' : 'block';
  });
  document.addEventListener('click', (e) => {
    if (!sidebarUserProfile.contains(e.target)) {
      sidebarUserMenu.style.display = 'none';
    }
  });

  // Filter Modal
  const filterIcon = document.getElementById('filterIcon');
  const filterModalOverlay = document.getElementById('filterModalOverlay');
  const filterModal = document.getElementById('filterModal');
  const filterForm = document.getElementById('filterForm');
  const cancelFilter = document.getElementById('cancelFilter');

  filterIcon.addEventListener('click', () => {
    filterModalOverlay.style.display = 'block';
    filterModal.style.display = 'block';
  });
  cancelFilter.addEventListener('click', () => {
    filterModalOverlay.style.display = 'none';
    filterModal.style.display = 'none';
  });
  filterModalOverlay.addEventListener('click', (e) => {
    if (e.target === filterModalOverlay) {
      filterModalOverlay.style.display = 'none';
      filterModal.style.display = 'none';
    }
  });

  filterForm.onsubmit = function(e) {
    e.preventDefault();
    const formData = new FormData(filterForm);
    let params = new URLSearchParams();
    params.append('ajax', 'true');
    params.append('visited', currentTab === 'visited' ? 'true' : 'false');
    for (let [key, val] of formData.entries()) {
      if (val.trim() !== '') {
        params.append(key, val.trim());
      }
    }
    fetch('?' + params.toString())
      .then(r => r.json())
      .then(rows => {
        updateTable(rows);
        filterModalOverlay.style.display = 'none';
        filterModal.style.display = 'none';
      })
      .catch(err => console.error(err));
  };

  // Add Button
  addBtn.addEventListener('click', () => {
    if (currentTab === 'upcoming') {
      showModal(addUpcomingOverlay, addUpcomingModal);
    } else {
      showModal(addVisitedOverlay, addVisitedModal);
    }
  });

  // Generic modal show/hide
  function showModal(overlay, modal) {
    overlay.style.display = 'block';
    modal.style.display = 'block';
  }
  function hideModal(overlay, modal) {
    overlay.style.display = 'none';
    modal.style.display = 'none';
  }

  // ============ UPCOMING ADD =============
  const addUpcomingOverlay = document.getElementById('addUpcomingOverlay');
  const addUpcomingModal = document.getElementById('addUpcomingModal');
  const addUpcomingForm = document.getElementById('addUpcomingForm');
  const cancelAddUpcoming = document.getElementById('cancelAddUpcoming');
  const addUpcomingSaveBtn = document.getElementById('addUpcomingSaveBtn');

  cancelAddUpcoming.onclick = () => hideModal(addUpcomingOverlay, addUpcomingModal);
  addUpcomingOverlay.onclick = (e) => { if (e.target === addUpcomingOverlay) hideModal(addUpcomingOverlay, addUpcomingModal); };

  addUpcomingForm.onsubmit = (e) => {
    e.preventDefault();
    addUpcomingSaveBtn.disabled = true;
    const fd = new FormData(addUpcomingForm);
    fd.append('action', 'add_upcoming');
    fetch('', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        addUpcomingSaveBtn.disabled = false;
        if (d.status === 'success') {
          showAlert('success', d.message);
          hideModal(addUpcomingOverlay, addUpcomingModal);
          // Reset mailSent flag
          mailSent = false;
          const child_id = fd.get('child_id');
          const visit_date = fd.get('visit_date');
          // Call mail endpoint only if not already sent
          if (!mailSent) {
            fetch(`?send_mail=1&child_id=${encodeURIComponent(child_id)}&visit_date=${encodeURIComponent(visit_date)}&reschedule=0`)
              .then(response => response.text())
              .then(mailResponse => {
                console.log("Mail:", mailResponse);
                mailSent = true;
              })
              .catch(err => console.error("Email sending error:", err));
          }
          setTimeout(() => { location.reload(); }, 1500);
        } else {
          showAlert('error', d.message);
        }
      })
      .catch(er => {
        addUpcomingSaveBtn.disabled = false;
        console.error(er);
      });
  };

  // ============ UPCOMING EDIT =============
  const editUpcomingOverlay = document.getElementById('editUpcomingOverlay');
  const editUpcomingModal = document.getElementById('editUpcomingModal');
  const editUpcomingForm = document.getElementById('editUpcomingForm');
  const cancelEditUpcoming = document.getElementById('cancelEditUpcoming');
  const editUpcomingSaveBtn = document.getElementById('editUpcomingSaveBtn');
  const editUpcomingVisitId = document.getElementById('editUpcomingVisitId');
  const editUpcomingChildName = document.getElementById('editUpcomingChildName');
  const editUpcomingDate = document.getElementById('editUpcomingDate');

  cancelEditUpcoming.onclick = () => hideModal(editUpcomingOverlay, editUpcomingModal);
  editUpcomingOverlay.onclick = (e) => { if (e.target === editUpcomingOverlay) hideModal(editUpcomingOverlay, editUpcomingModal); };

  editUpcomingForm.onsubmit = (e) => {
    e.preventDefault();
    editUpcomingSaveBtn.disabled = true;
    const fd = new FormData(editUpcomingForm);
    fd.append('action', 'update_upcoming');
    fetch('', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        editUpcomingSaveBtn.disabled = false;
        if (d.status === 'success') {
          showAlert('success', d.message);
          hideModal(editUpcomingOverlay, editUpcomingModal);
          // Reset mailSent flag and call mail endpoint once
          mailSent = false;
          const child_id = d.child_id; // returned from backend update
          const visit_date = fd.get('visit_date');
          if (!mailSent) {
            fetch(`?send_mail=1&child_id=${encodeURIComponent(child_id)}&visit_date=${encodeURIComponent(visit_date)}&reschedule=1`)
              .then(response => response.text())
              .then(mailResponse => {
                console.log("Mail:", mailResponse);
                mailSent = true;
              })
              .catch(err => console.error("Email sending error:", err));
          }
          setTimeout(() => { location.reload(); }, 1500);
        } else {
          showAlert('error', d.message);
        }
      })
      .catch(er => {
        editUpcomingSaveBtn.disabled = false;
        console.error(er);
      });
  };

  // ============ UPCOMING DELETE =============
  const deleteUpcomingOverlay = document.getElementById('deleteUpcomingOverlay');
  const deleteUpcomingModal = document.getElementById('deleteUpcomingModal');
  const cancelDeleteUpcoming = document.getElementById('cancelDeleteUpcoming');
  const confirmDeleteUpcoming = document.getElementById('confirmDeleteUpcoming');
  let upcomingDeleteId = null;

  cancelDeleteUpcoming.onclick = () => hideModal(deleteUpcomingOverlay, deleteUpcomingModal);
  deleteUpcomingOverlay.onclick = (e) => { if (e.target === deleteUpcomingOverlay) hideModal(deleteUpcomingOverlay, deleteUpcomingModal); };

  confirmDeleteUpcoming.onclick = () => {
    if (!upcomingDeleteId) return;
    confirmDeleteUpcoming.disabled = true;
    const fd = new FormData();
    fd.append('action', 'delete_upcoming');
    fd.append('visit_id', upcomingDeleteId);
    fetch('', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        confirmDeleteUpcoming.disabled = false;
        if (d.status === 'success') {
          showAlert('success', d.message);
          hideModal(deleteUpcomingOverlay, deleteUpcomingModal);
          setTimeout(() => { location.reload(); }, 1500);
        } else {
          showAlert('error', d.message);
        }
      })
      .catch(er => {
        confirmDeleteUpcoming.disabled = false;
        console.error(er);
      });
  };

  // ============ VISITED ADD =============
  const addVisitedOverlay = document.getElementById('addVisitedOverlay');
  const addVisitedModal = document.getElementById('addVisitedModal');
  const addVisitedForm = document.getElementById('addVisitedForm');
  const cancelAddVisited = document.getElementById('cancelAddVisited');
  const addVisitedSaveBtn = document.getElementById('addVisitedSaveBtn');

  cancelAddVisited.onclick = () => hideModal(addVisitedOverlay, addVisitedModal);
  addVisitedOverlay.onclick = (e) => { if (e.target === addVisitedOverlay) hideModal(addVisitedOverlay, addVisitedModal); };

  addVisitedForm.onsubmit = (e) => {
    e.preventDefault();
    addVisitedSaveBtn.disabled = true;
    const fd = new FormData(addVisitedForm);
    fd.append('action', 'add_visited');
    fetch('', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        addVisitedSaveBtn.disabled = false;
        if (d.status === 'success') {
          showAlert('success', d.message);
          hideModal(addVisitedOverlay, addVisitedModal);
          setTimeout(() => { location.reload(); }, 1500);
        } else {
          showAlert('error', d.message);
        }
      })
      .catch(er => {
        addVisitedSaveBtn.disabled = false;
        console.error(er);
      });
  };

  // ============ VISITED EDIT =============
  const editVisitedOverlay = document.getElementById('editVisitedOverlay');
  const editVisitedModal = document.getElementById('editVisitedModal');
  const editVisitedForm = document.getElementById('editVisitedForm');
  const cancelEditVisited = document.getElementById('cancelEditVisited');
  const editVisitedSaveBtn = document.getElementById('editVisitedSaveBtn');
  const editVisitedVisitId = document.getElementById('editVisitedVisitId');
  const editVisitedChildName = document.getElementById('editVisitedChildName');
  const editVisitedDate = document.getElementById('editVisitedDate');
  const editVisitedDisorders = document.getElementById('editVisitedDisorders');
  const editVisitedAction = document.getElementById('editVisitedAction');
  const editVisitedNote = document.getElementById('editVisitedNote');

  cancelEditVisited.onclick = () => hideModal(editVisitedOverlay, editVisitedModal);
  editVisitedOverlay.onclick = (e) => { if (e.target === editVisitedOverlay) hideModal(editVisitedOverlay, editVisitedModal); };

  editVisitedForm.onsubmit = (e) => {
    e.preventDefault();
    editVisitedSaveBtn.disabled = true;
    const fd = new FormData(editVisitedForm);
    fd.append('action', 'update_visited');
    fetch('', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        editVisitedSaveBtn.disabled = false;
        if (d.status === 'success') {
          showAlert('success', d.message);
          hideModal(editVisitedOverlay, editVisitedModal);
          setTimeout(() => { location.reload(); }, 1500);
        } else {
          showAlert('error', d.message);
        }
      })
      .catch(er => {
        editVisitedSaveBtn.disabled = false;
        console.error(er);
      });
  };

  // ============ VISITED DELETE =============
  const deleteVisitedOverlay = document.getElementById('deleteVisitedOverlay');
  const deleteVisitedModal = document.getElementById('deleteVisitedModal');
  const cancelDeleteVisited = document.getElementById('cancelDeleteVisited');
  const confirmDeleteVisited = document.getElementById('confirmDeleteVisited');
  let visitedDeleteId = null;

  cancelDeleteVisited.onclick = () => hideModal(deleteVisitedOverlay, deleteVisitedModal);
  deleteVisitedOverlay.onclick = (e) => { if (e.target === deleteVisitedOverlay) hideModal(deleteVisitedOverlay, deleteVisitedModal); };

  confirmDeleteVisited.onclick = () => {
    if (!visitedDeleteId) return;
    confirmDeleteVisited.disabled = true;
    const fd = new FormData();
    fd.append('action', 'delete_visited');
    fd.append('visit_id', visitedDeleteId);
    fetch('', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        confirmDeleteVisited.disabled = false;
        if (d.status === 'success') {
          showAlert('success', d.message);
          hideModal(deleteVisitedOverlay, deleteVisitedModal);
          setTimeout(() => { location.reload(); }, 1500);
        } else {
          showAlert('error', d.message);
        }
      })
      .catch(er => {
        confirmDeleteVisited.disabled = false;
        console.error(er);
      });
  };

  // ============ EDIT/DELETE ICONS =============
  document.addEventListener('click', (e) => {
    if (e.target.classList.contains('edit-icon')) {
      const type = e.target.getAttribute('data-type');
      const visitId = e.target.getAttribute('data-visit-id');
      const childName = e.target.getAttribute('data-child-name');
      const visitDate = e.target.getAttribute('data-visit-date');
      if (type === 'upcoming') {
        editUpcomingVisitId.value = visitId;
        editUpcomingChildName.value = childName;
        editUpcomingDate.value = visitDate;
        showModal(editUpcomingOverlay, editUpcomingModal);
      } else {
        const disorders = e.target.getAttribute('data-disorders') || '';
        const action = e.target.getAttribute('data-action') || '';
        const note = e.target.getAttribute('data-note') || '';
        editVisitedVisitId.value = visitId;
        editVisitedChildName.value = childName;
        editVisitedDate.value = visitDate;
        editVisitedDisorders.value = disorders;
        editVisitedAction.value = action;
        editVisitedNote.value = note;
        showModal(editVisitedOverlay, editVisitedModal);
      }
    }
    if (e.target.classList.contains('delete-icon')) {
      const type = e.target.getAttribute('data-type');
      const visitId = e.target.getAttribute('data-visit-id');
      if (type === 'upcoming') {
        upcomingDeleteId = visitId;
        showModal(deleteUpcomingOverlay, deleteUpcomingModal);
      } else {
        visitedDeleteId = visitId;
        showModal(deleteVisitedOverlay, deleteVisitedModal);
      }
    }
  });

  // ============ SEARCH (text) =============
  const searchInput = document.getElementById('searchInput');
  let searchTimer = null;
  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      const val = searchInput.value.trim();
      const visitedParam = (currentTab === 'visited') ? 'true' : 'false';
      const url = `?ajax=true&search=${encodeURIComponent(val)}&visited=${visitedParam}`;
      fetch(url)
        .then(r => r.json())
        .then(rows => { updateTable(rows); })
        .catch(err => console.error(err));
    }, 300);
  });

  // ============ updateTable (after search/filter) =============
  function updateTable(rows) {
    if (currentTab === 'upcoming') {
      const tb = document.querySelector('#upcomingTable tbody');
      tb.innerHTML = '';
      if (!rows.length) {
        tb.innerHTML = '<tr><td colspan="4">No records found</td></tr>';
        return;
      }
      rows.forEach(r => {
        tb.innerHTML += `
          <tr>
            <td>${r.child_id}</td>
            <td>${r.child_name}</td>
            <td>${r.visit_date}</td>
            <td class="action-icons">
              <i class="fas fa-edit edit-icon"
                 data-visit-id="${r.visit_id}"
                 data-child-id="${r.child_id}"
                 data-child-name="${r.child_name}"
                 data-visit-date="${r.visit_date}"
                 data-type="upcoming"></i>
              <i class="fas fa-trash delete-icon"
                 data-visit-id="${r.visit_id}"
                 data-type="upcoming"></i>
            </td>
          </tr>
        `;
      });
    } else {
      const tb = document.querySelector('#visitedTable tbody');
      tb.innerHTML = '';
      if (!rows.length) {
        tb.innerHTML = '<tr><td colspan="7">No records found</td></tr>';
        return;
      }
      rows.forEach(r => {
        tb.innerHTML += `
          <tr>
            <td>${r.child_id}</td>
            <td>${r.child_name}</td>
            <td>${r.visit_date}</td>
            <td>${r.identified_disorders || ''}</td>
            <td>${r.action_taken || ''}</td>
            <td>${r.note || ''}</td>
            <td class="action-icons">
              <i class="fas fa-edit edit-icon"
                 data-visit-id="${r.visit_id}"
                 data-child-id="${r.child_id}"
                 data-child-name="${r.child_name}"
                 data-visit-date="${r.visit_date}"
                 data-disorders="${r.identified_disorders || ''}"
                 data-action="${r.action_taken || ''}"
                 data-note="${r.note || ''}"
                 data-type="visited"></i>
              <i class="fas fa-trash delete-icon"
                 data-visit-id="${r.visit_id}"
                 data-type="visited"></i>
            </td>
          </tr>
        `;
      });
    }
  }
</script>
</body>
</html>
