<?php
// u/learning.php

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

// 2. PULL USER NAME
$userName = htmlspecialchars($_SESSION['user_name'] ?? 'Parent User');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1.0" />
  <title>KidsGrow - Learning</title>

  <!-- Fonts & Icons -->
  <link
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
    rel="stylesheet"
  />
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css"
  />

  <style>
    :root {
      --bg-teal: #009688;
      --bg-card: #ffffff;
      --bg-gray: #f2f2f2;
      --text-color: #333;
      --sidebar-width: 220px;
      --primary-accent: #009688;
      --accent-color: #009688;
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
      text-align: center; margin-bottom: 40px;
      font-size: 24px; font-weight: 700;
      display: flex; align-items: center; justify-content: center; gap: 8px;
    }
    .logo i { font-size: 28px; }
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
    .nav-links a:hover,
    .nav-links a.active {
      background-color: rgba(255,255,255,0.2);
    }

    /* User Profile & Sign‑Out */
    .user-profile {
      position: relative;
      padding: 10px 20px;
      display: flex; align-items: center; gap: 12px;
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
      bottom: 70px; left: 0;
      background-color: #fff; color: #333;
      border-radius: 8px; min-width: 150px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.2);
      z-index: 999; padding: 10px 0;
    }
    .profile-menu a {
      display: block; padding: 8px 12px;
      color: #333; text-decoration: none;
    }
    .profile-menu a:hover { background-color: #f2f2f2; }

    /* Main content */
    .main-content {
      margin-left: var(--sidebar-width);
      padding: 20px;
    }
    .dashboard-header {
      font-size: 28px; font-weight: 700; margin-bottom: 24px;
    }

    /* Learning page specifics */
    .download-section {
      margin-bottom: 30px;
    }
    .download-section h3 {
      font-size: 20px; font-weight: 600; margin-bottom: 10px;
    }
    .download-btn {
      display: inline-block; padding: 12px 25px;
      border: 2px solid var(--accent-color);
      color: var(--accent-color);
      border-radius: 10px; font-weight: 600;
      text-decoration: none; transition: background 0.3s;
    }
    .download-btn:hover {
      background: var(--accent-color); color: white;
    }

    .articles h3 {
      font-size: 20px; margin-bottom: 20px;
    }
    .article-cards {
      display: flex; gap: 20px; flex-wrap: wrap;
    }
    .card {
      width: 300px; background: white; border-radius: 15px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.05);
      overflow: hidden; display: flex; flex-direction: column;
    }
    .card img {
      width: 100%; height: 200px; object-fit: cover;
    }
    .card .card-body {
      padding: 20px; flex: 1; display: flex; flex-direction: column;
    }
    .card .card-body h4 {
      font-size: 16px; font-weight: 700; margin-bottom: 10px;
    }
    .card .card-body p {
      font-size: 13px; line-height: 1.6; flex: 1;
    }
    .card .card-body a {
      color: #269fb1; font-size: 13px; font-weight: 600;
      text-decoration: none; margin-top: 10px; align-self: flex-end;
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
        <a href="dashboard.php"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="vaccination_tracker.php"><i class="fas fa-syringe"></i> Vaccination</a>
        <a href="growth_tracker.php"><i class="fas fa-chart-line"></i> Growth Tracker</a>
        <a href="learning.php" class="active"><i class="fas fa-book"></i> Learning</a>
      </div>
    </div>
    <div class="user-profile" id="userProfile">
      <img src="images/user.png" alt="User" />
      <div class="user-info">
        <span class="name"><?php echo $userName; ?></span>
      </div>
      <div class="profile-menu" id="profileMenu">
        <a href="logout.php">Sign Out</a>
      </div>
    </div>
  </div>
  <!-- END SIDEBAR -->

  <!-- MAIN CONTENT -->
  <div class="main-content">
    <div class="dashboard-header">Learning</div>

    <div class="download-section">
      <h3>Supporting Materials</h3>
      <a href="downloads/CHDR.pdf" class="download-btn" download>Download PDF</a>
    </div>

    <div class="articles">
      <h3>Articles</h3>
      <div class="article-cards">
        <div class="card">
          <img src="images/articles/article1.png" alt="Article 1">
          <div class="card-body">
            <h4>Unbreakable Connection: The Essence of Motherhood</h4>
            <p>A mother’s love is one of the purest and most profound connections in the world...</p>
            <a href="#">see more</a>
          </div>
        </div>

        <div class="card">
          <img src="images/articles/article1.png" alt="Article 2">
          <div class="card-body">
            <h4>The Essence of Motherhood: Nourishing with Love</h4>
            <p>Breastfeeding is more than nourishment; it is an act of love, connection, and care...</p>
            <a href="#">see more</a>
          </div>
        </div>

        <div class="card">
          <img src="images/articles/article1.png" alt="Article 3">
          <div class="card-body">
            <h4>Bubble Kisses & Bathtime Bliss</h4>
            <p>Bath time is not just about cleanliness—it is a moment of joy, bonding, and love...</p>
            <a href="#">see more</a>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- END MAIN CONTENT -->

  <!-- Profile‐menu toggle script -->
  <script>
    const userProfile = document.getElementById('userProfile');
    const profileMenu = document.getElementById('profileMenu');
    userProfile.addEventListener('click', e => {
      e.stopPropagation();
      profileMenu.style.display = profileMenu.style.display === 'block'
        ? 'none' : 'block';
    });
    document.addEventListener('click', () => {
      profileMenu.style.display = 'none';
    });
  </script>
</body>
</html>
