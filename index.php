<?php
session_start();

// DATABASE CONNECTION
$dsn         = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user     = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";
try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Process Contact Us submission
$contact_success = "";
$contact_error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['name']) && isset($_POST['email']) && isset($_POST['message'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $message = trim($_POST['message']);
    if ($name !== "" && $email !== "" && $message !== "") {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_requests (name, email, message) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $message]);
            $contact_success = "Your message has been sent successfully!";
        } catch(PDOException $e) {
            $contact_error = "Error sending your message: " . $e->getMessage();
        }
    } else {
        $contact_error = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en" id="home">
<head>

<!-- Meta Tags for SEO -->
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="author" content="KidsGrow Team">
<meta name="description" content="KidsGrow is a smart digital platform for parents to monitor children's health, track growth, get vaccination reminders, and receive expert medical advice.">
<meta name="keywords" content="KidsGrow, child health, digital health records, vaccination reminders, pediatric care, growth tracking, parenting support, baby health, medical history, child development">
<meta name="robots" content="index, follow">
<meta name="language" content="English">
<meta name="revisit-after" content="7 days">
<meta name="distribution" content="global">

<!-- Open Graph (OG) Tags for Social Media -->
<meta property="og:title" content="KidsGrow - Better Health, Brighter Future">
<meta property="og:description" content="A comprehensive platform to manage children's health records, vaccination schedules, and expert pediatric advice for parents.">
<meta property="og:image" content="https://kidsgrow.xyz/images/kidsgrow-preview.png">
<meta property="og:url" content="https://kidsgrow.xyz/">
<meta property="og:type" content="website">
<meta property="og:site_name" content="KidsGrow">

<!-- Twitter Card for Social Media Sharing -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="KidsGrow - Smart Child Health Platform">
<meta name="twitter:description" content="Monitor child growth, track vaccinations, and get expert medical guidance with KidsGrow.">
<meta name="twitter:image" content="https://kidsgrow.xyz/images/kidsgrow-preview.png">
<meta name="twitter:site" content="@KidsGrowOfficial">

<!-- Canonical Tag (Prevents Duplicate Content Issues) -->
<link rel="canonical" href="https://kidsgrow.xyz/">

<!-- Favicon -->
<link rel="icon" type="image/png" href="https://kidsgrow.xyz/images/icons8-kids-48.png">

  <title>KidsGrow - Better Health, Brighter Future</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.gstatic.com" />
  <link 
    href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" 
    rel="stylesheet"
  />

  <!-- Google tag (gtag.js) -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-4MHDP643CV"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-4MHDP643CV');
  </script>

  <style>
    /* RESET & GLOBAL STYLES */
    * {
      margin: 0; 
      padding: 0; 
      box-sizing: border-box; 
      font-family: 'Poppins', sans-serif;
    }
    body {
      background-color: #fff;
      color: #333;
      line-height: 1.5;
      overflow-x: hidden; /* Hide horizontal overflow */
    }
    a {
      text-decoration: none;
      color: inherit;
    }
    html {
      scroll-behavior: smooth; /* optional smooth scroll */
    }

    /* ALERT (Popup Notification) */
    .alert {
      position: fixed;
      top: 20px;
      right: 20px;
      padding: 15px 25px;
      border-radius: 8px;
      color: #fff;
      font-size: 14px;
      font-weight: 500;
      z-index: 1100;
      display: none;
      animation: slideIn 0.3s ease-out;
    }
    .alert-success { background: #28a745; }
    .alert-error   { background: #dc3545; }
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to   { transform: translateX(0);   opacity: 1; }
    }

    /* NAVBAR */
    .navbar {
      background-color: #fff;
      border-bottom: 1px solid #f2f2f2;
    }
    .navbar-top {
      display: grid;
      grid-template-columns: auto 1fr auto;
      align-items: center;
      gap: 20px;
      padding: 20px 80px;
    }
    /* Ensure the login button occupies the last grid column on the right */
    .navbar-top .login-btn {
    grid-column: 3;
    justify-self: end; /* push the button to the far right */
    }
    .logo {
      display: flex;
      align-items: center;
      font-size: 24px;
      font-weight: 700;
      color: #29B693;
    }
    .logo img {
      width: 40px;
      height: 40px;
      margin-right: 10px;
    }

    /* Make sure the nav-links themselves are centered vertically */
    .nav-links {
    grid-column: 2;
    display: flex;
    align-items: center; /* keeps the text/icons aligned in the middle */
    gap: 30px;
    justify-content: center;
    margin: 0;
    }
    .nav-links a {
      font-weight: 500;
      color: #818181;
      transition: color 0.2s;
    }
    .nav-links a:hover {
      color: #29B693;
    }

    .login-btn {
      background-color: #1ABC9C;
      color: #fff;
      padding: 10px 25px;
      border-radius: 8px;
      font-weight: 700;
      transition: background 0.2s;
      border: none;
      cursor: pointer;
      justify-self: end;
    }
    .login-btn:hover {
      background-color: #17a087;
    }

    /* Hidden login panel (Parents / Staff) */
    .login-panel {
      display: none;
      background-color: #fff;
      border-top: 1px solid #f2f2f2;
      padding: 10px 80px;
      align-items: center;
      justify-content: center;
    }
    .login-panel.show {
      display: flex;
    }
    .login-panel-inner {
      display: flex;
      gap: 30px;
    }
    .login-option {
      display: flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
    }
    .login-option img {
      width: 24px;
      height: 24px;
    }
    .login-option span {
      font-size: 16px;
      color: #333;
      font-weight: 500;
    }
    .login-option:hover span {
      color: #29B693;
    }

    /* HAMBURGER ICON (for mobile) */
    .mobile-menu-icon {
      display: none;  /* hidden on desktop */
      background: none;
      border: none;
      font-size: 24px;
      cursor: pointer;
      color: #818181;
    }
    .mobile-menu-icon:hover {
      color: #29B693;
    }

    /* Mobile Nav Links (vertical menu) */
    .mobile-nav-links {
      display: none; /* hidden by default */
      position: absolute;
      top: 70px; /* below the navbar-top */
      left: 0;
      right: 0;
      background-color: #fff;
      border-top: 1px solid #f2f2f2;
      padding: 20px;
      flex-direction: column;
      gap: 20px;
      z-index: 999;
    }
    .mobile-nav-links a {
      font-size: 16px;
      color: #818181;
      font-weight: 500;
      border-bottom: 1px solid #eee;
      padding-bottom: 8px;
    }
    .mobile-nav-links a:hover {
      color: #29B693;
    }

    /* HERO */
    .hero {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 60px 80px;
      position: relative;
    }
    .hero-content {
      max-width: 600px;
      flex: 1;
    }
    .hero-content h1 {
      font-size: 36px;
      margin-bottom: 20px;
      line-height: 1.2;
    }
    .hero-content h1 span {
      color: #29B693;
    }
    .hero-content p {
      font-size: 18px;
      color: #555;
      margin-bottom: 20px;
    }
    .hero-image {
      position: relative;
      width: 400px;
      height: auto;
      flex: 1;
      display: flex;
      justify-content: center;
    }
    .hero-image img {
      width: 260px;
      height: auto;
      position: relative;
      z-index: 2;
      margin-left: 42em; /* large screens only */
    }
    .quarter-circle {
      position: absolute;
      top: 50%;
      right: -120px;
      transform: translateY(-50%);
      width: 400px;
      height: 400px;
      background-color: #FADADD;
      border-top-left-radius: 400px;
      z-index: 1;
    }

    /* FEATURES */
    #about {
      padding: 60px 80px;
    }
    #about h2 {
      font-size: 28px;
      margin-bottom: 40px;
      font-weight: 600;
      text-align: center;
    }
    .feature-cards {
      display: flex;
      flex-wrap: wrap;
      gap: 20px;
      justify-content: space-between;
    }
    .feature-card {
      flex: 1 1 calc(25% - 20px);
      background-color: #FFEBED;
      border-radius: 20px;
      padding: 30px;
      text-align: center;
      min-width: 220px;
    }
    .feature-card img {
      width: 60px;
      height: 60px;
      margin-bottom: 20px;
    }
    .feature-card h3 {
      font-size: 22px;
      margin-bottom: 10px;
      color: #333;
    }
    .feature-card p {
      color: #555;
      font-size: 16px;
      line-height: 1.4;
    }

    /* TESTIMONIALS */
    #testimonials {
      padding: 60px 80px;
    }
    #testimonials h2 {
      font-size: 28px;
      margin-bottom: 40px;
      font-weight: 600;
      text-align: center;
    }
    .testimonial-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 20px;
    }
    .testimonial-card {
      display: flex;
      gap: 20px;
      align-items: flex-start;
      background-color: rgba(26, 188, 156, 0.2);
      border-radius: 20px;
      padding: 20px;
    }
    .testimonial-card img {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      object-fit: cover;
    }
    .testimonial-card h4 {
      font-size: 16px;
      margin-bottom: 8px;
      color: #333;
    }
    .testimonial-card p {
      color: #555;
      font-size: 14px;
      line-height: 1.4;
    }

    /* CONTACT */
    #contact {
      padding: 60px 80px;
      background-color: rgba(26, 188, 156, 0.2);
      border-radius: 10px;
      margin: 0 80px 60px 80px;
    }
    #contact h2 {
      font-size: 28px;
      margin-bottom: 30px;
      font-weight: 600;
      text-align: center;
    }
    #contact form {
      display: flex;
      flex-direction: column;
      gap: 20px;
      max-width: 500px;
      margin: 0 auto; /* center the form */
    }
    #contact input,
    #contact textarea {
      padding: 10px;
      font-size: 16px;
      border: 1px solid #ccc;
      border-radius: 5px;
      outline: none;
    }
    #contact button {
      background-color: #29B693;
      color: #fff;
      padding: 12px 30px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      width: fit-content;
      font-weight: 600;
      transition: background 0.2s;
    }
    #contact button:hover {
      background-color: #24a289;
    }

    /* FIX SNIPPET: revert section headings to left alignment */
    #about h2,
    #testimonials h2,
    #contact h2 {
    text-align: left !important;
    }

    /* FIX SNIPPET: left-align the Contact Us form */
    #contact form {
    margin: 0;
    text-align: left;
    }

    /* Ensure images and text in login options align horizontally */
    .login-option a {
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    }

    .login-option a img {
    width: 24px;
    height: 24px;
    }

    .login-option a span {
    font-size: 16px;
    color: #333;
    font-weight: 500;
    }

    /* Hover effect for text color */
    .login-option a:hover span {
    color: #29B693;
    }



    /* FOOTER */
    footer {
      background-color: #333;
      color: #fff;
      padding: 40px 80px;
    }
    .footer-top {
      display: grid;
      grid-template-columns: auto 1fr;
      align-items: center;
      gap: 20px;
    }
    .footer-brand {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .footer-brand img {
      width: 40px;
      height: 40px;
    }
    .footer-brand-text {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .footer-brand-text h3 {
      font-size: 20px;
      font-weight: 700;
      margin-bottom: 4px;
      color: #fff;
    }
    .footer-desc {
      font-size: 14px;
      color: #ccc;
      line-height: 1.4;
      max-width: 300px;
    }
    .footer-links {
      display: flex;
      justify-content: center;
      gap: 30px;
      flex-wrap: wrap;
    }
    .footer-links a {
      color: #fff;
      font-weight: 500;
      transition: color 0.2s;
    }
    .footer-links a:hover {
      color: #1abc9c;
    }
    .footer-bottom {
      text-align: center;
      margin-top: 20px;
      font-size: 14px;
      color: #ccc;
    }

    /* RESPONSIVE: Mobile Optimization */
    @media (max-width: 992px) {
      /* Hide the normal nav-links, show hamburger icon */
      .nav-links {
        display: none;
      }
      .mobile-menu-icon {
        display: block;
      }

      .navbar-top {
        padding: 20px 40px;
        grid-template-columns: auto auto auto; 
        /* logo, login, hamburger icon. We keep login on the right, hamburger in the middle or vice versa */
        justify-items: end; /* we can position the hamburger to the right */
      }

      /* Re-position them: 
         - Left: .logo
         - Center: (nothing or hamburger icon)
         - Right: .login-btn
         We’ll handle hamburger icon alignment with separate styles.
      */
      .logo {
        justify-self: start;
      }
      .login-btn {
        justify-self: end;
      }
      .mobile-menu-icon {
        justify-self: end;
        font-size: 24px;
        background: none;
        border: none;
        color: #818181;
      }
      .mobile-menu-icon:hover {
        color: #29B693;
      }

      .hero {
        flex-direction: column;
        text-align: center;
        padding: 40px 20px; /* less horizontal space on mobile */
      }
      .hero-content {
        margin-bottom: 30px;
        max-width: 100%;
      }
      .hero-image {
        width: 100%;
        margin: 0 auto;
        justify-content: center;
      }
      .hero-image img {
        margin-left: 0; /* remove large margin for mobile */
      }
      .quarter-circle {
        display: none; /* hide circle on mobile */
      }
      #about, #testimonials, #contact {
        padding: 40px 20px; /* reduce horizontal padding on tablet */
        margin: 0 20px 40px 20px;
      }
      footer {
        padding: 40px 20px;
      }
      .footer-top {
        grid-template-columns: 1fr;
        text-align: center;
      }
      .footer-brand {
        justify-content: center;
      }
      .feature-cards {
        gap: 30px;
      }
      .feature-card {
        flex: 1 1 calc(50% - 20px); /* 2 columns on tablet */
        margin: 0 auto;
        margin-bottom: 20px;
      }
    }
    @media (max-width: 576px) {
      .navbar-top {
        padding: 15px 20px;
      }
      .hero {
        padding: 30px 20px;
      }
      #about, #testimonials, #contact {
        padding: 20px;
        margin: 0 20px 20px 20px;
      }
      footer {
        padding: 20px;
      }
      .login-panel {
        padding: 10px 20px;
      }
      .login-panel-inner {
        flex-direction: column;
        gap: 20px;
      }
      .feature-card {
        flex: 1 1 100%; /* 1 column on phone */
      }
    }
  </style>
</head>
<body>
  <!-- ALERT POPUP -->
  <div class="alert" id="alertBox"></div>

  <!-- NAVBAR -->
  <header class="navbar">
    <div class="navbar-top">
      <!-- Left: KidsGrow Logo -->
      <div class="logo">
        <img src="images/mother-and-son head 1.png" alt="KidsGrow Logo" />
        KidsGrow
      </div>

    <!-- Standard Desktop Nav Links -->
    <div class="nav-links">
      <a href="#home">Home</a>
      <a href="#about">About</a>
      <a href="#testimonials">Testimonials</a>
      <a href="#contact">Contact</a>
    </div>

      <!-- Mobile Hamburger Icon (hidden on desktop) -->
      <button class="mobile-menu-icon" id="mobileMenuIcon">&#9776;</button>

      <!-- Right: Log In Button -->
      <button class="login-btn" id="loginBtn">Log In</button>
    </div>



    <!-- Mobile Nav Links (hidden by default) -->
    <div class="mobile-nav-links" id="mobileNavLinks">
      <a href="#home">Home</a>
      <a href="#about">About</a>
      <a href="#testimonials">Testimonials</a>
      <a href="#contact">Contact</a>
    </div>

    <!-- Parents/Staff login panel -->
    <div class="login-panel" id="loginPanel">
      <div class="login-panel-inner">
        <div class="login-option">
        <a href="u/signin.php">
          <img src="images/icons8-parent-48.png" alt="Parents icon" />
          <span>Parents</span>
        </a>
        </div>
        <div class="login-option">
        <a href="signin.php">
          <img src="images/icons8-staff-48.png" alt="Staff icon" />
          <span>Staff</span>
        </a>
        </div>
      </div>
    </div>
  </header>

  <!-- HERO (Home) -->
  <section class="hero" id="home">
    <div class="hero-content">
      <h1>
        Better Health, Brighter Future with <br />
        <span>KidsGrow!</span>
      </h1>
      <p>
        KidsGrow is a smart digital platform designed to monitor and enhance
        children's health and development. Providing seamless access to medical
        records, vaccination reminders, and expert advice, KidsGrow ensures a
        healthier future with trusted care—anytime, anywhere.
      </p>
    </div>
    <div class="hero-image">
      <div class="quarter-circle"></div>
      <img src="images/mother.png" alt="Mother holding and smiling at her baby" />
    </div>
  </section>

  <!-- FEATURES (About) -->
  <section id="about">
    <h2>Why Choose KidsGrow?</h2>
    <div class="feature-cards">
      <div class="feature-card">
        <img src="images/healthrec.png" alt="Medical records icon" />
        <h3>Smart Health Records</h3>
        <p>Securely store and manage your child's medical history.</p>
      </div>
      <div class="feature-card">
        <img src="images/icons8-vaccine-50.png" alt="Vaccination reminder icon" />
        <h3>Vaccination Reminders</h3>
        <p>Never miss an important vaccine date with timely notifications.</p>
      </div>
      <div class="feature-card">
        <img src="images/icons8-doctor-68.png" alt="Expert advice icon" />
        <h3>Expert Advice</h3>
        <p>Consult pediatric professionals for the best health guidance.</p>
      </div>
      <div class="feature-card">
        <img src="images/icons8-growth-50.png" alt="Growth tracking icon" />
        <h3>Growth Tracking</h3>
        <p>Track your child's physical and developmental milestones with ease.</p>
      </div>
    </div>
  </section>

  <!-- TESTIMONIALS -->
  <section id="testimonials">
    <h2>What Parents Say...</h2>
    <div class="testimonial-grid">
      <div class="testimonial-card">
        <img src="images/user1.png" alt="Parent testimonial avatar" />
        <div>
          <h4>S. Bryan Silva</h4>
          <p>
            “I no longer miss vaccination dates, and tracking growth has never
            been simpler. Highly recommend it to all parents!”
          </p>
        </div>
      </div>
      <div class="testimonial-card">
        <img src="images/user2.png" alt="Parent testimonial avatar" />
        <div>
          <h4>Praveen Jayarathna</h4>
          <p>
            “KidsGrow is the perfect health companion for my little one. The
            expert advice and easy-to-use interface make it an essential tool
            for every parent!”
          </p>
        </div>
      </div>
      <div class="testimonial-card">
        <img src="images/user3.png" alt="Parent testimonial avatar" />
        <div>
          <h4>Diana Perera</h4>
          <p>
            “As a first-time parent, I was overwhelmed with keeping up with
            doctor visits and health records. KidsGrow keeps everything organized
            and accessible. A lifesaver!”
          </p>
        </div>
      </div>
      <div class="testimonial-card">
        <img src="images/user4.png" alt="Parent testimonial avatar" />
        <div>
          <h4>Sahan Dilhara</h4>
          <p>
            “This platform gives me peace of mind. I love how I can track my
            child's medical history and get reminders for checkups. It truly
            makes parenting stress-free!”
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- CONTACT -->
  <section id="contact">
    <h2>Contact Us</h2>
    <form method="POST" action="">
      <input type="text" name="name" placeholder="Name" required />
      <input type="email" name="email" placeholder="Email Address" required />
      <textarea name="message" placeholder="Message" rows="5" required></textarea>
      <button type="submit">Submit</button>
    </form>
  </section>

  <!-- FOOTER -->
  <footer>
    <div class="footer-top">
      <div class="footer-brand">
        <img src="images/mother-and-son 1.png" alt="KidsGrow logo" />
        <div class="footer-brand-text">
          <h3>KidsGrow</h3>
          <p class="footer-desc">
            Monitor children's health with medical records, vaccination reminders,
            and expert advice. Growing strong, growing smart!
          </p>
        </div>
      </div>
      <div class="footer-links">
        <a href="#home">Home</a>
        <a href="#about">About</a>
        <a href="#testimonials">Testimonials</a>
        <a href="#contact">Contact</a>
      </div>
    </div>
    <div class="footer-bottom">
      © 2025 KidsGrow Educational Solutions. All Rights Reserved.
    </div>
  </footer>

  <!-- JS for smooth scrolling, login panel, and mobile menu -->
  <script>
    // Show success or error popup for 3 seconds
    function showAlert(type, message) {
      const alertBox = document.getElementById("alertBox");
      alertBox.className = "alert " + (type === "success" ? "alert-success" : "alert-error");
      alertBox.textContent = message;
      alertBox.style.display = "block";
      setTimeout(() => {
        alertBox.style.display = "none";
      }, 3000);
    }

    // If contact form was submitted, show a popup (using PHP variables)
    <?php if (!empty($contact_success)): ?>
      showAlert("success", "<?php echo addslashes($contact_success); ?>");
    <?php elseif (!empty($contact_error)): ?>
      showAlert("error", "<?php echo addslashes($contact_error); ?>");
    <?php endif; ?>

    // Smooth scrolling for internal links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const targetID = this.getAttribute('href');
        const targetEl = document.querySelector(targetID);
        if (targetEl) {
          targetEl.scrollIntoView({ behavior: 'smooth' });
        }
      });
    });

    // Toggle the login panel (small horizontal bar)
    const loginBtn = document.getElementById("loginBtn");
    const loginPanel = document.getElementById("loginPanel");
    loginBtn.addEventListener("click", (e) => {
      e.preventDefault();
      loginPanel.classList.toggle("show");
    });

    // MOBILE MENU (Hamburger) Toggle
    const mobileMenuIcon = document.getElementById("mobileMenuIcon");
    const mobileNavLinks = document.getElementById("mobileNavLinks");
    let mobileMenuOpen = false;

    mobileMenuIcon.addEventListener("click", () => {
      if (!mobileMenuOpen) {
        // Show mobile nav
        mobileNavLinks.style.display = "flex";
        mobileMenuOpen = true;
      } else {
        // Hide mobile nav
        mobileNavLinks.style.display = "none";
        mobileMenuOpen = false;
      }
    });
  </script>

  <!-- Tawk.to Script -->
  <script type="text/javascript">
    var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
    (function(){
      var s1=document.createElement("script"), s0=document.getElementsByTagName("script")[0];
      s1.async=true;
      s1.src='https://embed.tawk.to/67b5e764c756b2190a983955/1ikf8vp47';
      s1.charset='UTF-8';
      s1.setAttribute('crossorigin','*');
      s0.parentNode.insertBefore(s1,s0);
      Tawk_API.onLoad = function(){
        console.log("Tawk.to loaded successfully.");
      };
    })();
  </script>
  <!-- Google Tag Manager (noscript) -->
  <!-- <noscript>
    <iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PWKBHVTZ"
    height="0" width="0" style="display:none;visibility:hidden"></iframe>
  </noscript> -->
  <!-- End Google Tag Manager (noscript) -->
</body>
</html>
