<?php
session_start();

// 1. CHECK USER SESSION & ROLE (Parent Only)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: signin.php');
    exit;
}
if ($_SESSION['user_role'] !== 'Parent') {
    header('Location: unauthorized.php');
    exit;
}

// 2. DATABASE CONNECTION
$dsn         = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user     = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// 3. GET CHILD ID FROM URL PARAMETER
if (!isset($_GET['child_id'])) {
    header('Location: dashboard.php');
    exit;
}
$childId = $_GET['child_id'];

// 4. FETCH CHILD DETAILS
$stmtChild = $pdo->prepare("SELECT * FROM child WHERE child_id = :cid");
$stmtChild->execute([':cid' => $childId]);
$child = $stmtChild->fetch(PDO::FETCH_ASSOC);

if (!$child) {
    die("Child record not found.");
}

// 5. CALCULATE AGE
$ageString = '-';
if (!empty($child['birth_date'])) {
    try {
        $dob = new DateTime($child['birth_date']);
        $today = new DateTime('today');
        $ageInterval = $dob->diff($today);
        if ($ageInterval->invert !== 1) {
            $years = $ageInterval->y;
            $months = $ageInterval->m;
            $ageString = "{$years} year(s) {$months} month(s)";
        }
    } catch (Exception $ex) {
        $ageString = '-';
    }
}

// 6. FORMAT BOOLEAN VALUES FOR DISPLAY
function formatBoolean($value) {
    if ($value === null) return 'Not recorded';
    return $value ? 'Yes' : 'No';
}

// 7. USER NAME from session
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Parent User';
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
      background: #009688;
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

    /* Child Profiles Box with sticky header */
    .child-profiles {
      background: white;
      border-radius: 10px;
      overflow: hidden;
    }
    h2{
        font-weight: 550;
    }
    /* Sticky header for the table box */
    .child-profiles .sticky-header {
      position: sticky;
      top: 0;
      z-index: 10;
      background: #fff;
      padding: 40px;
      padding-bottom: 0px;
      padding-top: 30px;
      border-bottom: 1px solid #ffffff;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    /* Back button styling */
    .back-button {
      background-color: #009688;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 5px;
      cursor: pointer;
      font-size: 16px;
      font-weight: 600;
      text-decoration: none;
      display: flex;
      align-items: center;
    }
    .back-button:hover {
      background-color: #00796b;
    }

    /* View Child Modal */
    .view-child-modal .card {
      background: white;
      border-radius: 10px;
      padding: 40px;
      padding-top: 20px;
      padding-bottom: 40px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .view-child-modal .child-details {
      margin-bottom: 20px;
    }
    .view-child-modal .detail-row {
      display: flex;
      padding: 8px 0;
    }
    .view-child-modal .detail-row1 {
      display: flex;
      padding: 8px 0;
    }    
    .view-child-modal .detail-row span:first-child {
      font-weight: 500;
    }
    .view-child-modal .detail-row1 span:first-child {
      width: 550px;
      font-weight: 500;
    }    
    .view-child-modal .section-title {
      font-weight: 600;
      font-size: 16px;
      margin: 20px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      border: 1px solid #009688;
      border-bottom: none;
      padding: 30px;
      padding-top: 15px;
      padding-bottom: 15px;
      border-radius: 5px 5px 0 0;
      background-color: #caf2ee;
    }
    .view-child-modal .section-content {
      display: block; 
      border: 1px solid #009688;
      border-top: none;
      border-radius: 0 0 5px 5px;
      padding: 30px;
      padding-top: 5px;
      background-color: #caf2ee;
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

    .header-title-container {
  display: flex;
  align-items: center;
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
          <a href="vaccination_tracker.php" class="menu-item">
              <i class="fas fa-syringe"></i>
              <span>Vaccination</span>
          </a>
          <a href="growth_tracker.php" class="menu-item">
              <i class="fas fa-chart-line"></i>
              <span>Growth Tracker</span>
          </a>
          <a href="learning.php" class="menu-item">
            <i class="fa fa-book-open"></i>
            <span>Learning</span>
        </a>          
      </div>
      <!-- User Profile at Bottom -->
      <div class="sidebar-user-profile" id="sidebarUserProfile">
          <img src="https://placehold.co/45x45" alt="User">
          <div class="sidebar-user-info">
              <span class="sidebar-user-name"><?php echo htmlspecialchars($userName); ?></span>
              <span class="sidebar-user-role">Parent</span>
          </div>
          <div class="sidebar-user-menu" id="sidebarUserMenu">
              <a href="logout.php">Sign Out</a>
          </div>
      </div>
  </div>

  <!-- Main Content -->
  <div class="main-content">
      <!-- Top bar with search and add child -->
      <div class="search-bar">
      </div>

      <!-- Child Profiles Table with sticky header -->
      <div class="child-profiles">
      <div class="sticky-header">
      <div class="header-title-container">
        <a href="dashboard.php" class="back-button">
          <i class="fas fa-arrow-left"></i>
        </a>
        <h2 style="color: #009688; margin-left: 10px;">Child Details</h2>
      </div>
      </div>    
          <div class="view-child-modal">
            <div class="card">
                <div class="child-details">
                    <div class="detail-row">
                        <span>Child Name:&nbsp;</span>
                        <span><?php echo htmlspecialchars($child['name']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Date of Birth:&nbsp;</span>
                        <span><?php echo htmlspecialchars($child['birth_date']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Gender: </span>
                        <span><?php echo htmlspecialchars($child['sex']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Birth Weight:&nbsp;</span>
                        <span><?php echo isset($child['weight']) ? htmlspecialchars($child['weight']) . 'Kg' : '-'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Birth Height:&nbsp;</span>
                        <span><?php echo isset($child['height']) ? htmlspecialchars($child['height']) . 'cm' : '-'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Birth Hospital:&nbsp;</span>
                        <span><?php echo htmlspecialchars($child['birth_hospital']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Registered Date:&nbsp;</span>
                        <span><?php echo isset($child['registered_date']) ? htmlspecialchars($child['registered_date']) : '-'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Health Medical Officer Division:&nbsp;</span>
                        <span><?php echo htmlspecialchars($child['health_medical_officer_division']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Family Health Medical Officer Division:&nbsp;</span>
                        <span><?php echo htmlspecialchars($child['family_health_medical_officer_division']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Supplementary Regional Record Number:&nbsp;</span>
                        <span><?php echo isset($child['supplementary_record_number']) ? htmlspecialchars($child['supplementary_record_number']) : '-'; ?></span>
                    </div>
                    <div class="detail-row">
                        <span>Grama Niladhari Record Number:&nbsp;</span>
                        <span><?php echo isset($child['gn_record_number']) ? htmlspecialchars($child['gn_record_number']) : '-'; ?></span>
                    </div>

                    <div class="section-title">
                        More Information <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="section-content">
                        <div class="detail-row1">
                            <span>Did start breastfeeding within an hour after delivery?</span>
                            <span><?php echo formatBoolean($child['breastfeeding_within_1h']); ?></span>
                        </div>
                        <div class="detail-row1">
                            <span>Tested for hypothyroidism?</span>
                            <span><?php echo formatBoolean($child['congenital_hypothyroidism_check']); ?></span>
                        </div>
                        <div class="detail-row1">
                            <span>Hypothyroidism Test Result</span>
                            <span><?php echo isset($child['hypothyroidism_test_results']) ? htmlspecialchars($child['hypothyroidism_test_results']) : '-'; ?></span>
                        </div>
                        <div class="detail-row1">
                            <span>Reasons to Preserve</span>
                            <span><?php echo isset($child['reasons_to_preserve']) ? htmlspecialchars($child['reasons_to_preserve']) : '-'; ?></span>
                        </div>
                        <div class="detail-row1">
                            <span>Is only Breastfeeding at 2 months?</span>
                            <span><?php echo formatBoolean($child['breastfeeding_only_2m']); ?></span>
                        </div>
                        <div class="detail-row1">
                            <span>Is only Breastfeeding at 4 months?</span>
                            <span><?php echo formatBoolean($child['breastfeeding_only_4m']); ?></span>
                        </div>
                        <div class="detail-row1">
                            <span>Is only Breastfeeding at 6 months?</span>
                            <span><?php echo formatBoolean($child['breastfeeding_only_6m']); ?></span>
                        </div>
                        <div class="detail-row1">
                            <span>Starting complementary foods at 4 months?</span>
                            <span><?php echo formatBoolean($child['started_feeding_other_foods_4m']); ?></span>
                        </div>
                        <div class="detail-row1">
                            <span>Starting complementary foods at 6 months?</span>
                            <span><?php echo formatBoolean($child['started_feeding_other_foods_6m']); ?></span>
                        </div>
                        <div class="detail-row1">
                            <span>Age at first initiation of complementary feeding?</span>
                            <span><?php echo isset($child['age_started_feeding_other_foods']) ? htmlspecialchars($child['age_started_feeding_other_foods']) : '-'; ?></span>
                        </div>
                        <div class="detail-row1">
                            <span>Age when breastfeeding is completely stopped?</span>
                            <span><?php echo isset($child['age_stopped_breastfeeding']) ? htmlspecialchars($child['age_stopped_breastfeeding']) : '-'; ?></span>
                        </div>
                        <div class="detail-row1">
                            <span>Does the child eat normal family meals by the first year?</span>
                            <span><?php echo formatBoolean($child['other_foods_at_1_year']); ?></span>
                        </div>                        
                    </div>
                </div>
            </div>
          </div>
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

    $(document).ready(function(){
      // Sidebar user menu toggle
      $('#sidebarUserProfile').on('click', function(e){
        e.stopPropagation();
        toggleSidebarUserMenu();
      });
      $(document).on('click', function(){
        $('#sidebarUserMenu').hide();
      });

      // Toggle section content
      $(".section-title").click(function(){
          $(this).next(".section-content").slideToggle();
          $(this).find("i").toggleClass("fa-chevron-down fa-chevron-up");
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