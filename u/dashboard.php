<?php
session_start();

$selectedChildId = null;

// Handle form POST when child is selected
if (isset($_POST['selected_child'])) {
    $selectedChildId = $_POST['selected_child'];
    $_SESSION['selected_child_id'] = $selectedChildId;
    $_SESSION['child_selection_shown'] = true;
}

$showModal = !isset($_SESSION['child_selection_shown']);


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

// 3. FETCH THE PARENT RECORD
$loggedInUserId = $_SESSION['user_id'];
$stmtParent = $pdo->prepare("SELECT parent_id FROM parent WHERE user_id = :uid");
$stmtParent->execute([':uid' => $loggedInUserId]);
$parentRow = $stmtParent->fetch(PDO::FETCH_ASSOC);

if (!$parentRow) {
    die("No parent record found for this user.");
}
$parentId = $parentRow['parent_id'];

// 4. FETCH ALL CHILDREN FOR THIS PARENT
$stmtChildren = $pdo->prepare("SELECT child_id, name FROM child WHERE parent_id = :pid");
$stmtChildren->execute([':pid' => $parentId]);
$children = $stmtChildren->fetchAll(PDO::FETCH_ASSOC);

// 5. CHECK IF CHILD IS SELECTED (FROM POST OR SESSION)
$selectedChildId = null;
if (isset($_POST['selected_child'])) {
    $selectedChildId = $_POST['selected_child'];
    $_SESSION['selected_child_id'] = $selectedChildId;
} elseif (isset($_SESSION['selected_child_id'])) {
    $selectedChildId = $_SESSION['selected_child_id'];
}

// 6. FETCH SELECTED CHILD DATA
$childData = [
    'child_id' => null,
    'name'   => 'No Child Selected',
    'age'    => '-',
    'gender' => '-',
    'weight' => '-',
    'height' => '-'
];
$bmiData = [];
$upcomingVaccination = [
    'name'         => '-',
    'date'         => '-',
    'time_left'    => '-',
    'status'       => '-'
];
$healthSummary = [
    'last_checkup' => '-',
    'next_checkup' => '-'
];
$recommendation = 'No recommendations available';

if ($selectedChildId) {
    // Fetch child details
    $stmtChild = $pdo->prepare("SELECT * FROM child WHERE child_id = :cid");
    $stmtChild->execute([':cid' => $selectedChildId]);
    $childRow = $stmtChild->fetch(PDO::FETCH_ASSOC);

    if ($childRow) {
        // Calculate age
        $birthDate = $childRow['birth_date'];
        $ageString = '-';
        if (!empty($birthDate)) {
            try {
                $dob = new DateTime($birthDate);
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

        $childData = [
            'child_id' => $childRow['child_id'],
            'name'     => $childRow['name'],
            'age'      => $ageString,
            'gender'   => $childRow['sex'],
            'weight'   => isset($childRow['weight']) ? $childRow['weight'].' Kg' : '-',
            'height'   => isset($childRow['height']) ? $childRow['height'].' cm' : '-',
        ];

        // Fetch BMI data
        $stmtBmi = $pdo->prepare("
            SELECT recorded_at, weight, height, bmi
            FROM bmi_history
            WHERE child_id = :cid
            ORDER BY recorded_at ASC
        ");
        $stmtBmi->execute([':cid' => $selectedChildId]);
        $bmiRows = $stmtBmi->fetchAll(PDO::FETCH_ASSOC);

        foreach ($bmiRows as $row) {
            $recordedDate = (new DateTime($row['recorded_at']))->format('Y-m-d H:i:s');
            $bmiData[] = [
                'recorded_at' => $recordedDate,
                'weight'      => (float)$row['weight'],
                'height'      => (float)$row['height'],
                'bmi'         => (float)$row['bmi'],
            ];
        }

        // Fetch upcoming vaccination
        $currentDate = new DateTime();
        $stmtVaccination = $pdo->prepare("
            SELECT vaccinationname, datavaccination, status
            FROM vaccination
            WHERE child_id = :cid AND status = 'pending'
            ORDER BY datavaccination ASC
            LIMIT 1
        ");
        $stmtVaccination->execute([':cid' => $selectedChildId]);
        $vaccinationRow = $stmtVaccination->fetch(PDO::FETCH_ASSOC);

        if ($vaccinationRow) {
            $vaccinationDate = new DateTime($vaccinationRow['datavaccination']);
            $interval = $currentDate->diff($vaccinationDate);
            $daysLeft = $interval->format('%a days');
            
            $upcomingVaccination = [
                'name'         => $vaccinationRow['vaccinationname'],
                'date'         => $vaccinationDate->format('Y-m-d'),
                'time_left'    => $daysLeft,
                'status'       => ucfirst($vaccinationRow['status'])
            ];
        }

        // Fetch health summary (home visits)
        $healthSummary = [
          'last_checkup' => '-',
          'next_checkup' => '-'
        ];

        if ($selectedChildId) {
          // Fetch last visited checkup
          $stmtLastVisit = $pdo->prepare("
              SELECT visit_date
              FROM home_visit
              WHERE child_id = :cid AND visited = true
              ORDER BY visit_date DESC
              LIMIT 1
          ");
          $stmtLastVisit->execute([':cid' => $selectedChildId]);
          $lastVisitRow = $stmtLastVisit->fetch(PDO::FETCH_ASSOC);

          if ($lastVisitRow) {
              $healthSummary['last_checkup'] = (new DateTime($lastVisitRow['visit_date']))->format('Y-m-d');
          }

          // Find next unvisited checkup
          $stmtNextVisit = $pdo->prepare("
              SELECT visit_date
              FROM home_visit
              WHERE child_id = :cid AND visited = false AND visit_date > CURRENT_DATE
              ORDER BY visit_date ASC
              LIMIT 1
          ");
          $stmtNextVisit->execute([':cid' => $selectedChildId]);
          $nextVisitRow = $stmtNextVisit->fetch(PDO::FETCH_ASSOC);
          
          if ($nextVisitRow) {
              $healthSummary['next_checkup'] = (new DateTime($nextVisitRow['visit_date']))->format('Y-m-d');
          }
        }

        // Fetch latest recommendation
        $stmtRecommendation = $pdo->prepare("
            SELECT medical_recommendation
            FROM child_growth_details
            WHERE child_id = :cid
            ORDER BY measurement_date DESC
            LIMIT 1
        ");
        $stmtRecommendation->execute([':cid' => $selectedChildId]);
        $recommendationRow = $stmtRecommendation->fetch(PDO::FETCH_ASSOC);

        if ($recommendationRow && !empty($recommendationRow['medical_recommendation'])) {
            $recommendation = $recommendationRow['medical_recommendation'];
        }
    }
}

// 7. USER NAME from session
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Parent User';

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>KidsGrow - Dashboard</title>

  <!-- Fonts & Icons -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    :root {
      --bg-teal: #009688;
      --bg-card: #ffffff;
      --bg-gray: #f2f2f2;
      --text-color: #333;
      --sidebar-width: 220px;
      --primary-accent: #009688;
    }
    * {
      margin: 0; padding: 0; box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }
    body {
      background-color: var(--bg-gray);
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

    /* User Profile highlight area with sign-out menu */
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
      padding: 20px;
    }
    .dashboard-header {
      font-size: 28px; font-weight: 700; margin-bottom: 24px;
    }

    /* Cards */
    .cards-container {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 20px; margin-bottom: 20px;
    }
    .card {
      background-color: var(--bg-card);
      padding: 20px; border-radius: 10px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      color: var(--text-color);
    }
    .card h3 {
      margin-bottom: 12px; font-size: 18px; font-weight: 600;
      color: var(--primary-accent);
    }

    .bottom-cards-container {
      display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 20px;
    }
    #bmiChart {
      width: 100%; height: 220px;
    }

    /* Child Selection Modal */
    .modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.5);
      backdrop-filter: blur(5px);
      z-index: 1000;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .child-selection-modal {
      background: white;
      border-radius: 10px;
      padding: 30px;
      width: 400px;
      max-width: 90%;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    .child-selection-modal h2 {
      margin-bottom: 20px;
      color: var(--primary-accent);
      text-align: center;
    }
    .child-list {
      display: flex;
      flex-direction: column;
      gap: 10px;
      margin-bottom: 20px;
    }
    .child-item {
      padding: 15px;
      border: 1px solid #ddd;
      border-radius: 5px;
      cursor: pointer;
      transition: all 0.2s;
    }
    .child-item:hover {
      background-color: #f5f5f5;
      border-color: var(--primary-accent);
    }
    .no-children {
      text-align: center;
      color: #666;
      padding: 20px 0;
    }

    .cards-container a,
    .bottom-cards-container a {
       text-decoration: none;
       color: inherit; 
    }

    /* Force show modal (new style) */
    .force-show-modal {
      display: flex !important;
    }
  </style>
</head>
<body>

  <!-- SIDEBAR -->
  <div class="sidebar">
    <div>
      <div class="logo">
        <i class="fas fa-child"></i>
        <span>KidsGrow</span>
      </div>
      <div class="nav-links">
        <a href="dashboard.php" class="menu-item active">
          <i class="fas fa-th-large"></i> Dashboard
        </a>
        <a href="vaccination_tracker.php" class="menu-item">
          <i class="fas fa-syringe"></i> Vaccination
        </a>
        <a href="growth_tracker.php" class="menu-item">
          <i class="fas fa-chart-line"></i> Growth Tracker
        </a>
        <a href="learning.php" class="menu-item">
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
  <!-- END SIDEBAR -->

  <!-- MAIN CONTENT -->
  <div class="main-content">
    <div class="dashboard-header">Dashboard</div>

    <div class="cards-container">
    <a href="child_details.php?child_id=<?php echo htmlspecialchars($childData['child_id']); ?>">      <div class="card">
        <h3>Child Details</h3>
        <p><strong>Child Name:</strong> <?php echo htmlspecialchars($childData['name']); ?></p>
        <p><strong>Age:</strong> <?php echo htmlspecialchars($childData['age']); ?></p>
        <p><strong>Gender:</strong> <?php echo htmlspecialchars($childData['gender']); ?></p>
        <p><strong>Weight:</strong> <?php echo htmlspecialchars($childData['weight']); ?></p>
        <p><strong>Height:</strong> <?php echo htmlspecialchars($childData['height']); ?></p>
      </div>
      </a>

      <a href="vaccination_tracker.php">
      <div class="card">
        <h3>Upcoming Vaccinations</h3>
        <p><strong>Vaccination Name:</strong> <?php echo htmlspecialchars($upcomingVaccination['name']); ?></p>
        <p><strong>Scheduled Date:</strong> <?php echo htmlspecialchars($upcomingVaccination['date']); ?></p>
        <p><strong>Time Left:</strong> <?php echo htmlspecialchars($upcomingVaccination['time_left']); ?></p>
        <p><strong>Status:</strong> <?php echo htmlspecialchars($upcomingVaccination['status']); ?></p>
      </div>
    </div>
    </a>


    <div class="bottom-cards-container">
      <div class="card">
        <h3>BMI Chart</h3>
        <canvas id="bmiChart"></canvas>
      </div>

      <a href="growth_tracker.php">
      <div class="card">
        <h3>Health & Growth Summary</h3>
        <p><strong>Last Checkup Date:</strong> <?php echo htmlspecialchars($healthSummary['last_checkup']); ?></p>
        <p><strong>Next Checkup Date:</strong> <?php echo htmlspecialchars($healthSummary['next_checkup']); ?></p>
      </div>
      </a>

      <div class="card">
        <h3>Recommendation</h3>
        <p><?php echo htmlspecialchars($recommendation); ?></p>
      </div>
    </div>
  </div>
  <!-- END MAIN CONTENT -->

 <!-- CHILD SELECTION MODAL -->

 <?php if ($showModal): ?>
  <div class="modal-overlay force-show-modal" id="childSelectModal">
    <div class="child-selection-modal">
      <h2>Select a Child</h2>
      <form method="POST">
        <div class="child-list">
          <?php foreach ($children as $child): ?>
            <button type="submit" name="selected_child" value="<?php echo $child['child_id']; ?>" class="child-item">
              <?php echo htmlspecialchars($child['name']); ?>
            </button>
          <?php endforeach; ?>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>


  <!-- CHART & MENU SCRIPT -->
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

    // Build Chart.js line chart from PHP array
    const bmiDataFromPHP = <?php echo json_encode($bmiData); ?>;
    const labels = bmiDataFromPHP.map(item => item.recorded_at);
    const bmiValues = bmiDataFromPHP.map(item => item.bmi);

    const ctx = document.getElementById('bmiChart').getContext('2d');
    const bmiChart = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'BMI Over Time',
          data: bmiValues,
          borderColor: 'rgb(75, 192, 192)',
          borderWidth: 2,
          fill: false,
          tension: 0.1,
          pointRadius: 3
        }]
      },
      options: {
        responsive: true,
        scales: {
          x: {
            display: true,
            title: { display: true, text: 'Date' }
          },
          y: {
            display: true,
            title: { display: true, text: 'BMI' }
          }
        }
      }
    });

    document.addEventListener('click', function(e) {
    const modal = document.getElementById('childSelectModal');
    if (modal && e.target === modal) {
      modal.style.display = 'none';
    }
  });


  </script>
</body>
</html>