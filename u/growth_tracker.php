<?php
session_start();

// 1. CHECK USER SESSION & ROLE (Parent Only)
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Parent') {
    header('Location: signin.php');
    exit;
}

$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Parent User');

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

// 3. GET SELECTED CHILD ID
$selectedChildId = $_SESSION['selected_child_id'] ?? null;

// 4. FETCH CHILD BMI GROWTH DATA
$bmiGrowthRows = [];
if ($selectedChildId) {
    $stmt = $pdo->prepare("SELECT measurement_date, height, weight, bmi, nutrition_status, medical_recommendation FROM child_growth_details WHERE child_id = :cid ORDER BY measurement_date ASC");
    $stmt->execute([':cid' => $selectedChildId]);
    $bmiGrowthRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>KidsGrow - Growth Tracker</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    :root {
      --bg-teal: #009688;
      --bg-card: #ffffff;
      --bg-gray: #f2f2f2;
      --text-color: #333;
      --sidebar-width: 220px;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
    body { background-color: var(--bg-gray); }

    .sidebar {
      position: fixed;
      top: 0; left: 0;
      width: var(--sidebar-width); height: 100vh;
      background-color: var(--bg-teal); color: #fff;
      display: flex; flex-direction: column; justify-content: space-between;
      padding: 20px 0;
    }
    .logo {
      text-align: center; margin-bottom: 40px; font-size: 24px; font-weight: 700;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .nav-links {
      flex: 1; display: flex; flex-direction: column; gap: 8px; padding: 0 20px;
    }
    .nav-links a {
      text-decoration: none; color: #fff;
      font-weight: 500; padding: 12px; border-radius: 8px;
      transition: background 0.2s;
      display: flex; align-items: center; gap: 12px;
    }
    .nav-links a:hover, .nav-links a.active { background-color: rgba(255,255,255,0.2); }

    .user-profile {
      position: relative;
      padding: 10px 20px;
      display: flex; align-items: center; gap: 12px;
      background-color: rgba(255,255,255,0.2);
      border-radius: 8px; margin: 0 20px 20px 20px; cursor: pointer;
    }
    .user-profile img {
      width: 45px; height: 45px; border-radius: 50%; object-fit: cover;
    }
    .user-info {
      font-size: 14px;
    }
    .profile-menu {
      display: none;
      position: absolute;
      bottom: 70px;
      left: 0;
      background-color: #fff; color: #333;
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
    .profile-menu a:hover { background-color: #f2f2f2; }

    .main-content {
      margin-left: var(--sidebar-width);
      padding: 30px;
    }
    .main-content h2 {
      font-size: 24px;
      margin-bottom: 20px;
      color: #333;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      margin-bottom: 30px;
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    th, td {
      padding: 12px 15px;
      text-align: center;
      border-bottom: 1px solid #eee;
    }
    th {
      background-color: var(--bg-teal);
      color: white;
    }
    .chart-container {
      background: white;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    #bmiChart {
      width: 100%;
      max-height: 300px;
    }
  </style>
</head>
<body>

  <!-- SIDEBAR -->
  <div class="sidebar">
    <div>
      <div class="logo">
        <i class="fas fa-child"></i> KidsGrow
      </div>
      <div class="nav-links">
        <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="vaccination_tracker.php"><i class="fas fa-syringe"></i> Vaccination</a>
        <a href="child_growth_tracker.php" class="active"><i class="fas fa-chart-line"></i> Growth Tracker</a>
        <a href="learning.php"><i class="fas fa-book"></i> Learning</a>
      </div>
    </div>
    <div class="user-profile" id="userProfile">
      <img src="images/user.png" alt="User" />
      <div class="user-info"><?php echo $userName; ?></div>
      <div class="profile-menu" id="profileMenu">
        <a href="logout.php">Sign Out</a>
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <div class="main-content">
    <h2>Growth Tracker</h2>

    <table>
      <thead>
        <tr>
          <th>Measured Date</th>
          <th>Height</th>
          <th>Weight</th>
          <th>BMI</th>
          <th>Nutrition Status</th>
          <th>Recommendation</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($bmiGrowthRows) > 0): ?>
          <?php foreach ($bmiGrowthRows as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['measurement_date']); ?></td>
              <td><?php echo htmlspecialchars($row['height']) . 'cm'; ?></td>
              <td><?php echo htmlspecialchars($row['weight']) . 'kg'; ?></td>
              <td><?php echo htmlspecialchars($row['bmi']); ?></td>
              <td><?php echo htmlspecialchars($row['nutrition_status']); ?></td>
              <td><?php echo htmlspecialchars($row['medical_recommendation'] ?? '-'); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="6">No growth records available.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="chart-container">
      <h3 style="color:#009688; margin-bottom:10px;">BMI Chart</h3>
      <canvas id="bmiChart"></canvas>
    </div>
  </div>

  <!-- JS SCRIPT -->
  <script>
    const userProfile = document.getElementById('userProfile');
    const profileMenu = document.getElementById('profileMenu');
    userProfile.addEventListener('click', function(e) {
      e.stopPropagation();
      profileMenu.style.display = profileMenu.style.display === 'block' ? 'none' : 'block';
    });
    document.addEventListener('click', function() {
      profileMenu.style.display = 'none';
    });

    const chartData = <?php echo json_encode($bmiGrowthRows); ?>;
    const labels = chartData.map(row => row.measurement_date);
    const bmiValues = chartData.map(row => parseFloat(row.bmi));

    const ctx = document.getElementById('bmiChart').getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'BMI Progress',
          data: bmiValues,
          borderColor: '#009688',
          borderWidth: 2,
          fill: false,
          tension: 0.2,
          pointRadius: 4
        }]
      },
      options: {
        responsive: true,
        scales: {
          x: { title: { display: true, text: 'Date' } },
          y: { title: { display: true, text: 'BMI' }, beginAtZero: false }
        }
      }
    });
  </script>
</body>
</html>
