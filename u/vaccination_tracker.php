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
$dsn          = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user      = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// 3. FETCH THE PARENT RECORD
$loggedInUserId = $_SESSION['user_id'];
$stmtParent = $pdo->prepare("SELECT parent_id FROM parent WHERE user_id = :uid");
$stmtParent->execute([':uid' => $loggedInUserId]);
$parentRow = $stmtParent->fetch(PDO::FETCH_ASSOC);

if (!$parentRow) {
    die("No parent record found for this user.");
}
$parentId = $parentRow['parent_id'];

// 4. FETCH CHILD RECORDS FOR THIS PARENT
$stmtChildren = $pdo->prepare("SELECT * FROM child WHERE parent_id = :pid");
$stmtChildren->execute([':pid' => $parentId]);
$children = $stmtChildren->fetchAll(PDO::FETCH_ASSOC);

// 5. GET SELECTED CHILD ID FROM SESSION
$selectedChildId = isset($_SESSION['selected_child_id']) ? $_SESSION['selected_child_id'] : null;

// 6. FETCH VACCINATION DATA FOR SELECTED CHILD
$vaccinationData = [];
if ($selectedChildId) {
    $stmtVaccinations = $pdo->prepare("
        SELECT vaccinationid, vaccinationname, datavaccination, dosageno, 
               administrativeroute, healthcare_provider, batchno, 
               sideeffects, status, child_age
        FROM vaccination
        WHERE child_id = :cid
        ORDER BY child_age ASC, datavaccination ASC
    ");
    $stmtVaccinations->execute([':cid' => $selectedChildId]);
    $vaccinationData = $stmtVaccinations->fetchAll(PDO::FETCH_ASSOC);
}

// 7. GET CHILD NAME FOR DISPLAY
$childName = "No Child Selected";
if ($selectedChildId) {
    foreach ($children as $child) {
        if ($child['child_id'] == $selectedChildId) {
            $childName = $child['name'];
            break;
        }
    }
}

// 8. USER NAME from session
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Parent User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>KidsGrow - Vaccination Tracker</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
    <style>
        :root {
            --bg-teal: #009688;
            --bg-card: #ffffff;
            --bg-gray: #f2f2f2;
            --text-color: #333;
            --sidebar-width: 220px;
            --primary-accent: #009688;
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
            top: 0; left: 0;
            width: var(--sidebar-width); height: 100vh;
            background-color: var(--primary-accent); color: #fff;
            display: flex; flex-direction: column;
            justify-content: space-between; padding: 20px 0;
        }
        .logo {
            text-align: center; margin-bottom: 40px; font-size: 24px; font-weight: 700;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .logo i {
            font-size: 28px;
        }
        .nav-links {
            flex: 1; display: flex; flex-direction: column; gap: 8px;
            padding: 0 20px;
        }
        .nav-links a {
            text-decoration: none; color: #fff;
            font-weight: 500; padding: 12px; border-radius: 8px;
            transition: background 0.2s;
            display: flex; align-items: center; gap: 12px;
        }
        .nav-links a:hover {
            background-color: rgba(255,255,255,0.2);
        }

        /* User Profile */
        .user-profile {
            position: relative;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            margin: 0 20px 20px 20px;
        }
        .user-profile img {
            width: 45px; height: 45px;
            border-radius: 50%; object-fit: cover;
        }
        .user-info {
            display: flex; flex-direction: column;
            font-size: 14px; line-height: 1.2;
        }

        .profile-menu {
            display: none;
            position: absolute;
            bottom: 70px;
            left: 0;
            background-color: #fff;
            color: #333;
            border-radius: 8px;
            min-width: 150px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 999;
            padding: 10px 0;
        }
        .profile-menu a {
            display: block;
            padding: 8px 12px;
            color: #333; text-decoration: none;
        }
        .profile-menu a:hover {
            background-color: #f2f2f2;
        }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            height: 100vh;
            overflow-y: auto;
            padding: 20px;
            position: relative;
        }

        /* Search and header */
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
        
        .child-name-header {
            color: var(--primary-accent);
            margin-bottom: 20px;
            font-size: 24px;
        }

        /* Vaccination table */
        .vaccination-content {
        background: transparent;
        padding-top: 0px;
        padding-bottom: 20px;
        padding-left: 20px;
        padding-right: 20px;


        }
        
        .vaccination-header {
            color:rgb(0, 0, 0);
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .vaccination-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .vaccination-table th {
            background-color: #c5e8e3;
            color: #333;
            font-weight: 600;
            padding: 16px 20px;
            text-align: left;
        }
        
        .vaccination-table td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }


        .age-group {
            font-weight: bold;
        }
        .status-given {
            font-weight: 500;
            color: #28a745;
        }
        .status-pending {
            font-weight: 500;
            color: #ff9800;
        }
        .status-missed {
            font-weight: 500;
            color: #bb0202;
        }
 

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
        
        /* No child selected message */
        .no-child-selected {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div>
            <div class="logo">
                <i class="fas fa-child"></i>
                <span>KidsGrow</span>
            </div>
            <div class="nav-links">
                <a href="dashboard.php">
                    <i class="fas fa-th-large"></i> Dashboard
                </a>
                <a href="vaccination_tracker.php">
                    <i class="fas fa-syringe"></i> Vaccination
                </a>
                <a href="growth_tracker.php">
                    <i class="fas fa-chart-line"></i> Growth Tracker
                </a>
                <a href="learning.php">
                    <i class="fas fa-book"></i> Learning
                </a>
            </div>
        </div>
        <div class="user-profile" id="userProfile">
            <img src="images/user.png" alt="User" />
            <div class="user-info">
                <span class="name"><?php echo htmlspecialchars($userName); ?></span>
            </div>
            <div class="profile-menu" id="profileMenu">
                <a href="logout.php">Sign Out</a>
            </div>
        </div>
    </div>

    <div class="main-content">
    <div class="search-bar">
    </div>
    
    <div class="vaccination-content">
        <h2 class="vaccination-header">Vaccination</h2>

        <?php if (empty($selectedChildId)): ?>
            <div class="no-child-selected">
                Please select a child from the dashboard to view vaccination records.
            </div>
        <?php elseif (empty($vaccinationData)): ?>
            <div class="no-child-selected">
                No vaccination records found for this child.
            </div>
        <?php else: ?>
            <table class="vaccination-table">
                <thead>
                    <tr>
                        <th>Age</th>
                        <th>Vaccine Name</th>
                        <th>Vaccination Date</th>
                        <th>Status</th>
                        <th>Side Effects</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $currentAgeGroup = null;
                    foreach ($vaccinationData as $index => $vaccine): 
                        $ageDisplay = ($vaccine['child_age'] == 0) ? 'At Birth' : $vaccine['child_age'] . ' Months';
                        
                        // Check if we need to display a new age group header
                        if ($currentAgeGroup !== $vaccine['child_age']) {
                            $currentAgeGroup = $vaccine['child_age'];
                            if ($index > 0): ?>
                            <?php endif; ?>
                            <tr>
                                <td class="age-group"><?php echo $ageDisplay; ?></td>
                                <td><?php echo htmlspecialchars($vaccine['vaccinationname']); ?></td>
                                <td><?php echo htmlspecialchars($vaccine['datavaccination']); ?></td>
                                <td class="status-<?php echo htmlspecialchars($vaccine['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($vaccine['status'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($vaccine['sideeffects'] ?: '-'); ?></td>
                            </tr>
                        <?php } else { ?>
                            <tr>
                                <td></td>
                                <td><?php echo htmlspecialchars($vaccine['vaccinationname']); ?></td>
                                <td><?php echo htmlspecialchars($vaccine['datavaccination']); ?></td>
                                <td class="status-<?php echo htmlspecialchars($vaccine['status']); ?>">
                                    <?php echo ucfirst(htmlspecialchars($vaccine['status'])); ?>
                                </td>
                                <td><?php echo htmlspecialchars($vaccine['sideeffects'] ?: '-'); ?></td>
                            </tr>
                        <?php } ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

    <div class="alert" id="alertBox"></div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Toggle the profile menu on user profile click
        const userProfile = document.getElementById('userProfile');
        const profileMenu = document.getElementById('profileMenu');

        userProfile.addEventListener('click', function(e) {
            e.stopPropagation();
            if (profileMenu.style.display === 'block') {
                profileMenu.style.display = 'none';
            } else {
                profileMenu.style.display = 'block';
            }
        });

        // Hide menu if clicked outside
        document.addEventListener('click', function() {
            profileMenu.style.display = 'none';
        });

        // Alert Function
        function showAlert(type, message){
            let alertBox = $("#alertBox");
            alertBox.removeClass("alert-success alert-error");
            alertBox.addClass(type === "success" ? "alert-success" : "alert-error");
            alertBox.text(message).fadeIn();
            setTimeout(function(){ alertBox.fadeOut(); }, 3000);
        }
    </script>
</body>
</html>