<?php
session_start(); // Start the session

// Check if the user is logged in and allowed (Admin or SuperAdmin)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: signin.php');
    exit;
}
$allowed_roles = ['Admin', 'SuperAdmin', 'Doctor'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    header('Location: unauthorized.php'); // Create an unauthorized access page if needed
    exit;
}

// Database connection details
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


// ---------------------------------
// AJAX & POST Request Handling
// ---------------------------------

// 0. Handle BMI fetch for chart
if (isset($_GET['fetch_bmi']) && isset($_GET['child_id'])) {
    header('Content-Type: application/json');
    try {
        // Fetch recorded_at, bmi from the new bmi_history table
        $stmt = $pdo->prepare("SELECT recorded_at, bmi 
                               FROM bmi_history 
                               WHERE child_id = :child_id 
                               ORDER BY recorded_at ASC");
        $stmt->execute([':child_id' => $_GET['child_id']]);
        $bmiData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($bmiData);
    } catch (PDOException $e) {
        echo json_encode(["error" => $e->getMessage()]);
    }
    exit;
}

// 1. Handle delete request (AJAX)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM child WHERE child_id = :child_id");
        $stmt->execute([':child_id' => $_POST['delete_id']]);
        echo json_encode(["status" => "success"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}

// 2. Handle update via modal (for updating an existing child record)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['child_id']) && isset($_POST['health_division'])) {
    $child_id               = $_POST['child_id'];
    $health_division        = $_POST['health_division'];
    $family_health_division = $_POST['family_health_division'];
    $supplementary_record   = $_POST['supplementary_record'];
    $gn_record              = $_POST['gn_record'];

    // Retrieve radio values safely as booleans
    $breast2 = isset($_POST['breast2']) && $_POST['breast2'] === 'Yes' ? true : false;
    $breast4 = isset($_POST['breast4']) && $_POST['breast4'] === 'Yes' ? true : false;

    // Ensure height & weight are properly retrieved and stored as floats
    $weight = isset($_POST['weight']) && is_numeric($_POST['weight']) ? (float) $_POST['weight'] : null;
    $height = isset($_POST['height']) && is_numeric($_POST['height']) ? (float) $_POST['height'] : null;

    try {
        // Begin a transaction for data consistency
        $pdo->beginTransaction();

        // Update the child record
        $stmt = $pdo->prepare("
            UPDATE child
            SET 
                health_medical_officer_division = :health_division,
                family_health_medical_officer_division = :family_health_division,
                supplementary_record_number = :supplementary_record,
                gn_record_number = :gn_record,
                breastfeeding_only_2m = :breast2,
                breastfeeding_only_4m = :breast4,
                weight = :weight,
                height = :height
            WHERE child_id = :child_id
        ");
        
        // Bind parameters
        $stmt->bindParam(':health_division', $health_division);
        $stmt->bindParam(':family_health_division', $family_health_division);
        $stmt->bindParam(':supplementary_record', $supplementary_record);
        $stmt->bindParam(':gn_record', $gn_record);
        $stmt->bindParam(':breast2', $breast2, PDO::PARAM_BOOL);
        $stmt->bindParam(':breast4', $breast4, PDO::PARAM_BOOL);
        $stmt->bindValue(':weight', $weight, PDO::PARAM_NULL | PDO::PARAM_STR); 
        $stmt->bindValue(':height', $height, PDO::PARAM_NULL | PDO::PARAM_STR);
        $stmt->bindParam(':child_id', $child_id);
        $stmt->execute();

        // Check if weight and height are provided and insert/update BMI history
        if (!is_null($weight) && !is_null($height) && $weight > 0 && $height > 0) {

            // Check if there's already a BMI record for this child within the past 24 hours
            $checkStmt = $pdo->prepare("
                SELECT bmi_id FROM bmi_history 
                WHERE child_id = :child_id 
                AND recorded_at >= NOW() - INTERVAL '1 day'
                LIMIT 1
            ");
            $checkStmt->execute([':child_id' => $child_id]);
            $existingBmiRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingBmiRecord) {
                // Update the existing BMI record (without updating the `bmi` column)
                $updateBmiStmt = $pdo->prepare("
                    UPDATE bmi_history 
                    SET weight = :weight, height = :height, recorded_at = NOW()
                    WHERE bmi_id = :bmi_id
                ");
                $updateBmiStmt->execute([
                    ':weight' => $weight,
                    ':height' => $height,
                    ':bmi_id' => $existingBmiRecord['bmi_id']
                ]);
            } else {
                // Insert a new BMI record if none exists in the past 24 hours
                $insertBmiStmt = $pdo->prepare("
                    INSERT INTO bmi_history (child_id, weight, height, recorded_at)
                    VALUES (:child_id, :weight, :height, NOW())
                ");
                $insertBmiStmt->execute([
                    ':child_id' => $child_id,
                    ':weight'   => $weight,
                    ':height'   => $height
                ]);
            }
        }

        // Commit the transaction
        $pdo->commit();

        echo json_encode(["status" => "success", "message" => "Child record and BMI history updated successfully."]);
    } catch (PDOException $e) {
        // Rollback in case of an error
        $pdo->rollBack();
        echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    }
    exit;
}


// 3. Handle Add Child submission via modal
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['name']) && !isset($_POST['child_id'])) {
    // Retrieve and sanitize form data for adding a new child record.
    $name = trim($_POST['name']);
    $birth_date = $_POST['birth_date'];
    $registered_date = $_POST['registered_date'];
    $health_medical_officer_division = trim($_POST['health_medical_officer_division']);
    $weight = $_POST['weight'];
    $gender = $_POST['gender'];
    $family_health_medical_officer_division = trim($_POST['family_health_medical_officer_division']);
    $supplementary_regional_record_number = trim($_POST['supplementary_regional_record_number']);
    $grama_niladhari_record_number = trim($_POST['grama_niladhari_record_number']);
    $birth_hospital = trim($_POST['birth_hospital']);
    $mother_id = $_POST['mother_id'];
    $breastfeeding = ($_POST['breastfeeding'] === 'Yes') ? true : false;
    $hypothyroidism = ($_POST['hypothyroidism'] === 'Yes') ? true : false;
    $hypothyroidism_result = $_POST['hypothyroidism_result'];
    $reasons_to_preserve = trim($_POST['reasons_to_preserve']);

    // NEW: Retrieve height from POST
    $height = isset($_POST['height']) ? $_POST['height'] : 0;

    // Validate mother_id as a positive integer.
    if (!filter_var($mother_id, FILTER_VALIDATE_INT) || (int)$mother_id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid Mother ID. Please provide a valid positive integer."]);
        exit;
    }

    // Check if the mother exists in the parent table.
    $checkParent = $pdo->prepare("SELECT COUNT(*) FROM parent WHERE parent_id = ?");
    $checkParent->execute([$mother_id]);
    $parentExists = $checkParent->fetchColumn();

    if (!$parentExists) {
        echo json_encode(["status" => "error", "message" => "Mother is not registered. Please register the mother first."]);
        exit;
    }

    try {
        // Insert the new child record and return the child_id
        $stmt = $pdo->prepare("
            INSERT INTO child (
                parent_id, name, birth_date, registered_date, birth_hospital,
                breastfeeding_within_1h, congenital_hypothyroidism_check,
                hypothyroidism_test_results, reasons_to_preserve,
                weight, height, sex, health_medical_officer_division,
                family_health_medical_officer_division, supplementary_record_number,
                gn_record_number
            ) VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?
            ) RETURNING child_id
        ");

        $stmt->execute([
            $mother_id,
            $name,
            $birth_date,
            $registered_date,
            $birth_hospital,
            $breastfeeding,
            $hypothyroidism,
            $hypothyroidism_result,
            $reasons_to_preserve,
            $weight,
            $height,  // new height field
            $gender,
            $health_medical_officer_division,
            $family_health_medical_officer_division,
            $supplementary_regional_record_number,
            $grama_niladhari_record_number
        ]);

        // Retrieve the generated child_id
        $newChildId = $stmt->fetchColumn();

        // Insert into bmi_history
        $bmiStmt = $pdo->prepare("
            INSERT INTO bmi_history (child_id, weight, height, recorded_at)
            VALUES (:child_id, :weight, :height, NOW())
        ");
        $bmiStmt->execute([
            ':child_id' => $newChildId,
            ':weight'   => $weight,
            ':height'   => $height
        ]);

        echo json_encode(["status" => "success", "message" => "Child record added successfully, and BMI history updated."]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Error adding child record: " . $e->getMessage()]);
    }
    exit;
}

// 4. Handle AJAX search (and filter) requests
if (isset($_GET['ajax']) && $_GET['ajax'] == 'true') {
    // Free-text search parameter
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    // Additional filter parameters from the filter modal
    $child_id_filter = isset($_GET['child_id']) ? trim($_GET['child_id']) : '';
    $name_filter = isset($_GET['name']) ? trim($_GET['name']) : '';
    $birth_date_filter = isset($_GET['birth_date']) ? trim($_GET['birth_date']) : '';
    $weight_filter = isset($_GET['weight']) ? trim($_GET['weight']) : '';
    $gender_filter = isset($_GET['gender']) ? trim($_GET['gender']) : '';
    $birth_hospital_filter = isset($_GET['birth_hospital']) ? trim($_GET['birth_hospital']) : '';
    $health_division_filter = isset($_GET['health_division']) ? trim($_GET['health_division']) : '';
    $family_health_division_filter = isset($_GET['family_health_division']) ? trim($_GET['family_health_division']) : '';
    $parent_id_filter = isset($_GET['parent_id']) ? trim($_GET['parent_id']) : '';
    $breastfeeding_filter = isset($_GET['breastfeeding']) ? trim($_GET['breastfeeding']) : '';
    $tested_filter = isset($_GET['tested']) ? trim($_GET['tested']) : '';
    $result_filter = isset($_GET['result']) ? trim($_GET['result']) : '';

    $query = "SELECT * FROM child WHERE 1=1";
    $params = [];

    if ($search !== '') {
        $query .= " AND (name ILIKE :search OR birth_hospital ILIKE :search)";
        $params[':search'] = "%$search%";
    }
    if ($child_id_filter !== '') {
        $query .= " AND child_id = :child_id";
        $params[':child_id'] = $child_id_filter;
    }
    if ($name_filter !== '') {
        $query .= " AND name ILIKE :name";
        $params[':name'] = "%$name_filter%";
    }
    if ($birth_date_filter !== '') {
        $query .= " AND birth_date = :birth_date";
        $params[':birth_date'] = $birth_date_filter;
    }
    if ($weight_filter !== '') {
        $query .= " AND weight = :weight";
        $params[':weight'] = $weight_filter;
    }
    if ($gender_filter !== '') {
        $query .= " AND sex = :gender";
        $params[':gender'] = $gender_filter;
    }
    if ($birth_hospital_filter !== '') {
        $query .= " AND birth_hospital ILIKE :birth_hospital";
        $params[':birth_hospital'] = "%$birth_hospital_filter%";
    }
    if ($health_division_filter !== '') {
        $query .= " AND health_medical_officer_division ILIKE :health_division";
        $params[':health_division'] = "%$health_division_filter%";
    }
    if ($family_health_division_filter !== '') {
        $query .= " AND family_health_medical_officer_division ILIKE :family_health_division";
        $params[':family_health_division'] = "%$family_health_division_filter%";
    }
    if ($parent_id_filter !== '') {
        $query .= " AND parent_id = :parent_id";
        $params[':parent_id'] = $parent_id_filter;
    }
    if ($breastfeeding_filter !== '') {
        $query .= " AND breastfeeding_within_1h = :breastfeeding";
        $params[':breastfeeding'] = ($breastfeeding_filter === "Yes") ? true : false;
    }
    if ($tested_filter !== '') {
        $query .= " AND congenital_hypothyroidism_check = :tested";
        $params[':tested'] = ($tested_filter === "Yes") ? true : false;
    }
    if ($result_filter !== '') {
        $query .= " AND hypothyroidism_test_results = :result";
        $params[':result'] = $result_filter;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $childData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($childData);
    exit;
}

// For normal page load, fetch all child records
$stmt = $pdo->query("SELECT * FROM child");
$childData = $stmt->fetchAll(PDO::FETCH_ASSOC);


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

    /* Child Profiles Box with sticky header */
    .child-profiles {
      background: white;
      border-radius: 10px;
      overflow: hidden;
      /* no padding here since header and table container are separate */
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
      max-height: 600px; /* adjust as needed */
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

    /* View Child Modal */
    .view-child-modal .card {
      background: white;
      border-radius: 10px;
      padding: 20px;
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
    .view-child-modal .detail-row span:first-child {
      width: 250px;
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
      border: 1px solid #c8d6e5;
      padding: 10px;
      border-radius: 5px 5px 0 0;
      background-color: #e3eaf2;
    }
    .view-child-modal .section-content {
      display: none;
      border: 1px solid #c8d6e5;
      border-top: none;
      border-radius: 0 0 5px 5px;
      padding: 15px;
      background-color: #f1f6fb;
    }
    .view-child-modal .health-chart {
      width: 100%;
      border-collapse: collapse;
    }
    .view-child-modal .health-chart th,
    .view-child-modal .health-chart td {
      border: 1px solid #c8d6e5;
      padding: 10px;
      text-align: left;
      white-space: nowrap;
    }
    .view-child-modal .close-btn {
      background-color: #ff4444;
      color: white;
      border: none;
      padding: 10px 30px;
      border-radius: 5px;
      cursor: pointer;
      margin-top: 50px;
      margin-bottom: 20px;
      float: right;
    }

    /* BMI Chart */
    .bmi-chart-container {
      margin-top: 30px;
      background: #fff;
      border: 1px solid #c8d6e5;
      border-radius: 5px;
      padding: 15px;
    }
    .bmi-chart-container h3 {
      margin-bottom: 15px;
      font-size: 16px;
      color: #333;
      text-align: center;
      font-weight: 600;
    }
    canvas#bmiChart {
      max-width: 100%;
      height: auto;
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
          <?php if ($_SESSION['user_role'] === 'SuperAdmin'): ?>
          <a href="add_admin.php" class="menu-item">
              <i class="fas fa-user-shield"></i>
              <span>Add Admin</span>
          </a>
          <?php endif; ?>
      </div>
      <!-- User Profile at Bottom -->
      <div class="sidebar-user-profile" id="sidebarUserProfile">
          <img src="<?php echo htmlspecialchars($optimizedImage); ?>" alt="User Profile" />
          <div class="sidebar-user-info">
              <span class="sidebar-user-name"><?php echo htmlspecialchars($userName); ?></span>
              <span class="sidebar-user-role"><?php echo htmlspecialchars($userRole); ?></span>
          </div>
          <div class="sidebar-user-menu" id="sidebarUserMenu">
              <a href="logout.php">Sign Out</a>
              <a href="manage_profile.php">Manage Profile</a>
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
          <!-- Add Child Button on the right side -->
          <button class="add-child-btn" id="openAddChildModal">
              <i class="fas fa-plus"></i> Add Child
          </button>
      </div>

      <!-- Child Profiles Table with sticky header -->
      <div class="child-profiles">
          <div class="sticky-header">
              <h2>Child Profiles</h2>
              <i class="fas fa-filter filter-icon" id="tableFilterIcon"></i>
          </div>
          <div class="table-container">
              <table>
                  <thead>
                      <tr>
                          <!-- Default columns -->
                          <th>Child ID</th>
                          <th>Name</th>
                          <th>Date of Birth</th>
                          <th>Registered Date</th>
                          <th>Health Medical Officer Division</th>
                          <th>Family Health Medical Officer Division</th>
                          <th>Mother's ID</th>
                          <th>Birth Hospital</th>
                          <th>Weight</th>
                          <th>Height</th>
                          <th>Gender</th>
                          <!-- Two-line header is acceptable -->
                          <th>Supplementary<br/>Regional Record Number</th>
                          <th>Grama Niladhari<br/>Record Number</th>
                          <!-- Extra columns in same order as header -->
                          <th class="default-hidden" data-col="breastfeeding_within_1h">Breastfeeding Within 1 Hour</th>
                          <th class="default-hidden" data-col="congenital_hypothyroidism_check">Congenital Hypothyroidism Check</th>
                          <th class="default-hidden" data-col="hypothyroidism_test_results">Hypothyroidism Test Result</th>
                          <th class="default-hidden" data-col="reasons_to_preserve">Reasons To Preserve</th>
                          <th class="default-hidden" data-col="breastfeeding_only_2m">Only Breastfeeding At 2 Months</th>
                          <th class="default-hidden" data-col="breastfeeding_only_4m">Only Breastfeeding At 4 Months</th>
                          <th class="default-hidden" data-col="breastfeeding_only_6m">Only Breastfeeding At 6 Months</th>
                          <th class="default-hidden" data-col="start_feeding_other_foods">Started Feeding Other Foods</th>
                          <th class="default-hidden" data-col="age_started_feeding_other_foods">Age Started Feeding Other Foods</th>
                          <th class="default-hidden" data-col="age_stopped_breastfeeding">Age Stopped Breastfeeding</th>
                          <th class="default-hidden" data-col="other_foods_at_1_year">Other Foods At 1 Year</th>
                          <th class="default-hidden" data-col="started_feeding_other_foods_4m">Started Feeding Other Foods At 4 Months</th>
                          <th class="default-hidden" data-col="started_feeding_other_foods_6m">Started Feeding Other Foods At 6 Months</th>
                          <!-- Actions column -->
                          <th>Actions</th>
                      </tr>
                  </thead>
                  <tbody id="tableBody">
                      <?php foreach ($childData as $child): ?>
                      <tr data-id="<?php echo htmlspecialchars($child['child_id']); ?>">
                          <td><?php echo htmlspecialchars($child['child_id']); ?></td>
                          <td><?php echo htmlspecialchars($child['name']); ?></td>
                          <td><?php echo htmlspecialchars($child['birth_date']); ?></td>
                          <td><?php echo isset($child['registered_date']) ? htmlspecialchars($child['registered_date']) : '-'; ?></td>
                          <td><?php echo htmlspecialchars($child['health_medical_officer_division']); ?></td>
                          <td><?php echo htmlspecialchars($child['family_health_medical_officer_division']); ?></td>
                          <td><?php echo htmlspecialchars($child['parent_id']); ?></td>
                          <td><?php echo htmlspecialchars($child['birth_hospital']); ?></td>
                          <td><?php echo htmlspecialchars($child['weight']); ?></td>
                          <td><?php echo htmlspecialchars($child['height']); ?></td>
                          <td><?php echo htmlspecialchars($child['sex']); ?></td>
                          <td><?php echo htmlspecialchars($child['supplementary_record_number'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($child['gn_record_number'] ?? ''); ?></td>
                          <td class="default-hidden" data-col="breastfeeding_within_1h">
                              <?php echo ($child['breastfeeding_within_1h']) ? 'Yes' : 'No'; ?>
                          </td>
                          <td class="default-hidden" data-col="congenital_hypothyroidism_check">
                              <?php echo ($child['congenital_hypothyroidism_check']) ? 'Yes' : 'No'; ?>
                          </td>
                          <td class="default-hidden" data-col="hypothyroidism_test_results">
                              <?php echo htmlspecialchars($child['hypothyroidism_test_results']); ?>
                          </td>
                          <td class="default-hidden" data-col="reasons_to_preserve">
                              <?php echo htmlspecialchars($child['reasons_to_preserve']); ?>
                          </td>
                          <td class="default-hidden" data-col="breastfeeding_only_2m">
                              <?php echo ($child['breastfeeding_only_2m']) ? 'Yes' : 'No'; ?>
                          </td>
                          <td class="default-hidden" data-col="breastfeeding_only_4m">
                              <?php echo ($child['breastfeeding_only_4m']) ? 'Yes' : 'No'; ?>
                          </td>
                          <td class="default-hidden" data-col="breastfeeding_only_6m">
                              <?php echo ($child['breastfeeding_only_6m']) ? 'Yes' : 'No'; ?>
                          </td>
                          <td class="default-hidden" data-col="start_feeding_other_foods">
                              <?php echo ($child['start_feeding_other_foods']) ? 'Yes' : 'No'; ?>
                          </td>
                          <td class="default-hidden" data-col="age_started_feeding_other_foods">
                              <?php echo htmlspecialchars($child['age_started_feeding_other_foods']); ?>
                          </td>
                          <td class="default-hidden" data-col="age_stopped_breastfeeding">
                              <?php echo htmlspecialchars($child['age_stopped_breastfeeding']); ?>
                          </td>
                          <td class="default-hidden" data-col="other_foods_at_1_year">
                              <?php echo ($child['other_foods_at_1_year']) ? 'Yes' : 'No'; ?>
                          </td>
                          <td class="default-hidden" data-col="started_feeding_other_foods_4m">
                              <?php echo ($child['started_feeding_other_foods_4m']) ? 'Yes' : 'No'; ?>
                          </td>
                          <td class="default-hidden" data-col="started_feeding_other_foods_6m">
                              <?php echo ($child['started_feeding_other_foods_6m']) ? 'Yes' : 'No'; ?>
                          </td>
                          <td class="action-icons">
                              <i class="fas fa-eye view-btn"></i>
                              <i class="fas fa-edit edit-btn"></i>
                              <i class="fas fa-trash delete-icon delete-btn" data-id="<?php echo htmlspecialchars($child['child_id']); ?>"></i>
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
      <h2>Update Child</h2>
      <form id="editForm">
          <input type="hidden" id="editChildId">
          <div class="form-group">
              <label>Child Name</label>
              <input type="text" class="form-control" id="editChildName" readonly>
          </div>
          <div class="form-group">
              <label>Health Medical Officer Division</label>
              <input type="text" class="form-control" id="editHealthDivision">
          </div>
          <div class="form-group">
              <label>Family Health Medical Officer Division</label>
              <input type="text" class="form-control" id="editFamilyHealthDivision">
          </div>
          <div class="form-group">
              <label>Supplementary Regional Record Number</label>
              <input type="text" class="form-control" id="editSupplementaryRecord">
          </div>
          <div class="form-group">
              <label>Grama Niladhari Record Number</label>
              <input type="text" class="form-control" id="editGnRecord">
          </div>
          <div class="form-group">
              <label>Weight (kg)</label>
              <input type="number" step="0.1" class="form-control" id="editChildWeight">
          </div>
          <div class="form-group">
              <label>Height (m)</label>
              <input type="number" step="0.01" class="form-control" id="editChildHeight">
          </div>
          <div class="form-group">
              <label>Is only Breastfeeding at 2 Months?</label>
              <div class="radio-group">
                  <label><input type="radio" name="breast2" id="editBreast2Yes" value="Yes"> Yes</label>
                  <label><input type="radio" name="breast2" id="editBreast2No" value="No"> No</label>
              </div>
          </div>
          <div class="form-group">
              <label>Is only Breastfeeding at 4 Months?</label>
              <div class="radio-group">
                  <label><input type="radio" name="breast4" id="editBreast4Yes" value="Yes"> Yes</label>
                  <label><input type="radio" name="breast4" id="editBreast4No" value="No"> No</label>
              </div>
          </div>
          <div class="button-group">
              <button type="button" class="btn btn-secondary" id="cancelEdit">Cancel</button>
              <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
      </form>
  </div>

  <!-- Add Child Modal -->
  <div class="modal-overlay" id="addChildModalOverlay"></div>
  <div class="modal" id="addChildModal">
      <h2 class="filter-title">ADD NEW CHILD</h2>
      <form id="addChildForm">
          <div class="form-grid">
              <div class="form-group">
                  <label>Name</label>
                  <input type="text" class="form-control" name="name" required>
              </div>
              <div class="form-group">
                  <label>Birth Date</label>
                  <input type="date" class="form-control" name="birth_date" required>
              </div>
              <div class="form-group">
                  <label>Registered Date</label>
                  <input type="date" class="form-control" name="registered_date" required>
              </div>
              <div class="form-group">
                  <label>Health Medical Officer Division</label>
                  <input type="text" class="form-control" name="health_medical_officer_division" required>
              </div>
              <div class="form-group">
                  <label>Weight (kg)</label>
                  <input type="number" step="0.1" class="form-control" name="weight" min="0.5" max="4.5" required>
              </div>
              <div class="form-group">
                  <label>Height (m)</label>
                  <input type="number" step="0.01" class="form-control" name="height" min="0.1" max="2.5">
              </div>
              <div class="form-group">
                  <label>Gender</label>
                  <div class="radio-group">
                      <div class="radio-option">
                          <input type="radio" id="addGenderMale" name="gender" value="Male" required>
                          <label for="addGenderMale">Male</label>
                      </div>
                      <div class="radio-option">
                          <input type="radio" id="addGenderFemale" name="gender" value="Female" required>
                          <label for="addGenderFemale">Female</label>
                      </div>
                  </div>
              </div>
              <div class="form-group">
                  <label>Family Health Medical Officer Division</label>
                  <input type="text" class="form-control" name="family_health_medical_officer_division" required>
              </div>
              <div class="form-group">
                  <label>Supplementary Regional Record Number</label>
                  <input type="text" class="form-control" name="supplementary_regional_record_number">
              </div>
              <div class="form-group">
                  <label>Grama Niladhari Record Number</label>
                  <input type="text" class="form-control" name="grama_niladhari_record_number">
              </div>
              <div class="form-group">
                  <label>Birth Hospital</label>
                  <input type="text" class="form-control" name="birth_hospital" required>
              </div>
              <div class="form-group">
                  <label>Mother's ID</label>
                  <input type="text" class="form-control" name="mother_id" required>
              </div>
              <div class="form-group">
                  <label>Did start breastfeeding within an hour after delivery?</label>
                  <div class="radio-group">
                      <div class="radio-option">
                          <input type="radio" id="addBreastfeedingYes" name="breastfeeding" value="Yes" required>
                          <label for="addBreastfeedingYes">Yes</label>
                      </div>
                      <div class="radio-option">
                          <input type="radio" id="addBreastfeedingNo" name="breastfeeding" value="No" required>
                          <label for="addBreastfeedingNo">No</label>
                      </div>
                  </div>
              </div>
              <div class="form-group">
                  <label>Tested for hypothyroidism?</label>
                  <div class="radio-group">
                      <div class="radio-option">
                          <input type="radio" id="addHypothyroidismYes" name="hypothyroidism" value="Yes" required>
                          <label for="addHypothyroidismYes">Yes</label>
                      </div>
                      <div class="radio-option">
                          <input type="radio" id="addHypothyroidismNo" name="hypothyroidism" value="No" required>
                          <label for="addHypothyroidismNo">No</label>
                      </div>
                  </div>
              </div>
              <div class="form-group">
                  <label>Hypothyroidism Test Result</label>
                  <div class="radio-group">
                      <div class="radio-option">
                          <input type="radio" id="addHypothyroidismPositive" name="hypothyroidism_result" value="Positive" required>
                          <label for="addHypothyroidismPositive">Positive</label>
                      </div>
                      <div class="radio-option">
                          <input type="radio" id="addHypothyroidismNegative" name="hypothyroidism_result" value="Negative" required>
                          <label for="addHypothyroidismNegative">Negative</label>
                      </div>
                  </div>
              </div>
              <div class="form-group">
                  <label>Reasons to Preserve</label>
                  <input type="text" class="form-control" name="reasons_to_preserve">
              </div>
          </div>
          <div class="button-group">
              <button type="button" class="btn btn-cancel" id="cancelAddChild">Cancel</button>
              <button type="submit" class="btn btn-apply">Save</button>
          </div>
      </form>
  </div>
  <!-- End Add Child Modal -->

  <!-- Filter Modal -->
  <div class="modal-overlay" id="filterModalOverlay"></div>
  <div class="modal" id="filterModal">
      <h2 class="filter-title">FILTER CHILD DETAILS</h2>
      <form id="filterForm">
          <div class="form-grid">
              <div class="form-group">
                  <label>Child ID</label>
                  <input type="text" class="form-control" name="child_id">
              </div>
              <div class="form-group">
                  <label>Name</label>
                  <input type="text" class="form-control" name="name">
              </div>
              <div class="form-group">
                  <label>Date of Birth</label>
                  <input type="date" class="form-control" name="birth_date">
              </div>
              <div class="form-group">
                  <label>Weight</label>
                  <input type="text" class="form-control" name="weight">
              </div>
              <div class="form-group">
                  <label>Gender</label>
                  <div class="radio-group">
                      <div class="radio-option">
                          <input type="radio" name="gender" id="filterMale" value="Male">
                          <label for="filterMale">Male</label>
                      </div>
                      <div class="radio-option">
                          <input type="radio" name="gender" id="filterFemale" value="Female">
                          <label for="filterFemale">Female</label>
                      </div>
                  </div>
              </div>
              <div class="form-group">
                  <label>Birth Hospital</label>
                  <input type="text" class="form-control" name="birth_hospital">
              </div>
              <div class="form-group">
                  <label>Health Medical Officer Division</label>
                  <input type="text" class="form-control" name="health_division">
              </div>
              <div class="form-group">
                  <label>Family Health Medical Officer Division</label>
                  <input type="text" class="form-control" name="family_health_division">
              </div>
              <div class="form-group">
                  <label>Mother's ID</label>
                  <input type="text" class="form-control" name="parent_id">
              </div>
              <div class="form-group">
                  <label>Did start breastfeeding within an hour after delivery?</label>
                  <div class="radio-group">
                      <div class="radio-option">
                          <input type="radio" name="breastfeeding" id="filterBreastYes" value="Yes">
                          <label for="filterBreastYes">Yes</label>
                      </div>
                      <div class="radio-option">
                          <input type="radio" name="breastfeeding" id="filterBreastNo" value="No">
                          <label for="filterBreastNo">No</label>
                      </div>
                  </div>
              </div>
              <div class="form-group">
                  <label>Tested for hypothyroidism?</label>
                  <div class="radio-group">
                      <div class="radio-option">
                          <input type="radio" name="tested" id="filterTestedYes" value="Yes">
                          <label for="filterTestedYes">Yes</label>
                      </div>
                      <div class="radio-option">
                          <input type="radio" name="tested" id="filterTestedNo" value="No">
                          <label for="filterTestedNo">No</label>
                      </div>
                  </div>
              </div>
              <div class="form-group">
                  <label>Hypothyroidism Test Result</label>
                  <div class="radio-group">
                      <div class="radio-option">
                          <input type="radio" name="result" id="filterResultPositive" value="Positive">
                          <label for="filterResultPositive">Positive</label>
                      </div>
                      <div class="radio-option">
                          <input type="radio" name="result" id="filterResultNegative" value="Negative">
                          <label for="filterResultNegative">Negative</label>
                      </div>
                  </div>
              </div>
          </div>
          <!-- Hide Columns Section -->
          <div class="hide-columns">
              <h3 class="filter-title">HIDE COLUMNS</h3>
              <div class="column-list">
                  <div class="column-item">
                      <input type="checkbox" class="checkbox hide-col" name="hide_breastfeeding_within_1h" value="breastfeeding_within_1h" checked>
                      <span>Breastfeeding Within 1 Hour</span>
                  </div>
                  <div class="column-item">
                      <input type="checkbox" class="checkbox hide-col" name="hide_congenital_hypothyroidism_check" value="congenital_hypothyroidism_check" checked>
                      <span>Congenital Hypothyroidism Check</span>
                  </div>
                  <div class="column-item">
                      <input type="checkbox" class="checkbox hide-col" name="hide_hypothyroidism_test_results" value="hypothyroidism_test_results" checked>
                      <span>Hypothyroidism Test Result</span>
                  </div>
                  <div class="column-item">
                      <input type="checkbox" class="checkbox hide-col" name="hide_reasons_to_preserve" value="reasons_to_preserve" checked>
                      <span>Reasons To Preserve</span>
                  </div>
                  <div class="column-item">
                      <input type="checkbox" class="checkbox hide-col" name="hide_breastfeeding_only_2m" value="breastfeeding_only_2m" checked>
                      <span>Only Breastfeeding At 2 Months</span>
                  </div>
                  <div class="column-item">
                      <input type="checkbox" class="checkbox hide-col" name="hide_breastfeeding_only_4m" value="breastfeeding_only_4m" checked>
                      <span>Only Breastfeeding At 4 Months</span>
                  </div>
                  <div class="column-item">
                      <input type="checkbox" class="checkbox hide-col" name="hide_breastfeeding_only_6m" value="breastfeeding_only_6m" checked>
                      <span>Only Breastfeeding At 6 Months</span>
                  </div>
                  <div class="column-item">
                      <input type="checkbox" class="checkbox hide-col" name="hide_start_feeding_other_foods" value="start_feeding_other_foods" checked>
                      <span>Started Feeding Other Foods</span>
                  </div>
                  <div class="column-item">
                      <input type="checkbox" class="checkbox hide-col" name="hide_age_started_feeding_other_foods" value="age_started_feeding_other_foods" checked>
                      <span>Age Started Feeding Other Foods</span>
                  </div>
                  <div class="column-item">
                      <input type="checkbox" class="checkbox hide-col" name="hide_age_stopped_breastfeeding" value="age_stopped_breastfeeding" checked>
                      <span>Age Stopped Breastfeeding</span>
                  </div>
                  <div class="column-item">
                      <input type="checkbox" class="checkbox hide-col" name="hide_other_foods_at_1_year" value="other_foods_at_1_year" checked>
                      <span>Other Foods At 1 Year</span>
                  </div>
                  <div class="column-item">
                      <input type="checkbox" class="checkbox hide-col" name="hide_started_feeding_other_foods_4m" value="started_feeding_other_foods_4m" checked>
                      <span>Started Feeding Other Foods At 4 Months</span>
                  </div>
                  <div class="column-item">
                      <input type="checkbox" class="checkbox hide-col" name="hide_started_feeding_other_foods_6m" value="started_feeding_other_foods_6m" checked>
                      <span>Started Feeding Other Foods At 6 Months</span>
                  </div>
              </div>
          </div>
          <div class="button-group">
              <button type="button" class="btn btn-cancel" id="cancelFilter">Cancel</button>
              <button type="submit" class="btn btn-apply">Apply</button>
          </div>
      </form>
  </div>
  <!-- End Filter Modal -->

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

  <!-- VIEW CHILD DETAILS MODAL -->
  <div class="modal-overlay" id="viewChildModalOverlay"></div>
  <div class="modal view-child-modal" id="viewChildModal">
      <div class="card">
          <h2>Child Details</h2>
          <div><strong>Child ID:</strong> <span id="viewChildId"></span></div>
          <div class="child-details">
              <div class="detail-row">
                  <span>Name</span>
                  <span id="viewChildName"></span>
              </div>
              <div class="detail-row">
                  <span>Date of Birth</span>
                  <span id="viewBirthDate"></span>
              </div>
              <div class="detail-row">
                  <span>Registered Date</span>
                  <span id="viewRegisteredDate"></span>
              </div>
              <div class="detail-row">
                  <span>Health Medical Officer Division</span>
                  <span id="viewHealthDivision"></span>
              </div>
              <div class="detail-row">
                  <span>Family Health Medical Officer Division</span>
                  <span id="viewFamilyHealthDivision"></span>
              </div>
              <div class="detail-row">
                  <span>Mother's ID</span>
                  <span id="viewParentId"></span>
              </div>
              <div class="detail-row">
                  <span>Birth Hospital</span>
                  <span id="viewBirthHospital"></span>
              </div>
              <div class="detail-row">
                  <span>Birth Weight</span>
                  <span id="viewWeight"></span>
              </div>
              <div class="detail-row">
                  <span>Height</span>
                  <span id="viewHeight"></span>
              </div>
              <div class="detail-row">
                  <span>Gender</span>
                  <span id="viewGender"></span>
              </div>
              <div class="detail-row">
                  <span>Supplementary Regional Record Number</span>
                  <span id="viewSupplementary"></span>
              </div>
              <div class="detail-row">
                  <span>Grama Niladhari Record Number</span>
                  <span id="viewGnRecord"></span>
              </div>
          </div>

          <div class="section-title">
              More Information <i class="fas fa-chevron-down"></i>
          </div>
          <div class="section-content">
              <div class="detail-row">
                  <span>Did start breastfeeding within an hour after delivery?</span>
                  <span id="viewBreastfeeding"></span>
              </div>
              <div class="detail-row">
                  <span>Tested for hypothyroidism?</span>
                  <span id="viewHypothyroidism"></span>
              </div>
              <div class="detail-row">
                  <span>Hypothyroidism Test Result</span>
                  <span id="viewHypothyroidismResult"></span>
              </div>
              <div class="detail-row">
                  <span>Reasons to Preserve</span>
                  <span id="viewReasons"></span>
              </div>
              <div class="detail-row">
                  <span>Is only Breastfeeding at 2 months?</span>
                  <span id="viewBreastfeeding2m"></span>
              </div>
              <div class="detail-row">
                  <span>Is only Breastfeeding at 4 months?</span>
                  <span id="viewBreastfeeding4m"></span>
              </div>
              <div class="detail-row">
                  <span>Is only Breastfeeding at 6 months?</span>
                  <span id="viewBreastfeeding6m"></span>
              </div>
              <div class="detail-row">
                  <span>Starting complementary foods at 4 months?</span>
                  <span id="viewStartFeeding4m"></span>
              </div>
              <div class="detail-row">
                  <span>Starting complementary foods at 6 months?</span>
                  <span id="viewStartFeeding6m"></span>
              </div>
              <div class="detail-row">
                  <span>Age when breastfeeding is completely stopped?</span>
                  <span id="viewAgeStoppedFeeding"></span>
              </div>
              <div class="detail-row">
                  <span>Does the child eat normal family meals by the first year?</span>
                  <span id="viewOtherFoods"></span>
              </div>
          </div>

          <div class="section-title">
              Health Chart <i class="fas fa-chevron-down"></i>
          </div>
          <div class="section-content">
              <table class="health-chart">
                  <tr>
                      <th></th>
                      <th>1 - 5 Days</th>
                      <th>6 - 10 Days</th>
                      <th>14 - 21 Days</th>
                      <th>Within 42 Days</th>
                  </tr>
                  <tr>
                      <td>Date</td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                  </tr>
                  <tr>
                      <td>Skin Color</td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                  </tr>
                  <tr>
                      <td>Eyes</td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                  </tr>
                  <tr>
                      <td>The characteristics of the navel</td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                  </tr>
                  <tr>
                      <td>Temperature</td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                  </tr>
                  <tr>
                      <td>Exclusive Breastfeeding</td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                  </tr>
                  <tr>
                      <td>Breastfeeding attachment and relationship</td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                  </tr>
                  <tr>
                      <td>Stool Color</td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                  </tr>
                  <tr>
                      <td>Identified Complications</td>
                      <td></td>
                      <td></td>
                      <td></td>
                      <td></td>
                  </tr>
              </table>
          </div>

          <!-- BMI Growth Chart Section -->
          <div class="bmi-chart-container">
              <h3>BMI Growth Chart</h3>
              <canvas id="bmiChart"></canvas>
          </div>

          <button class="close-btn" id="closeViewChild">Close</button>
      </div>
  </div>
  <!-- End View Child Modal -->

  <!-- Alert Box -->
  <div class="alert" id="alertBox"></div>

  <!-- jQuery and Script -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // Toggle user menu in the bottom sidebar
    function toggleSidebarUserMenu() {
      const menu = document.getElementById('sidebarUserMenu');
      if (menu.style.display === 'block') {
        menu.style.display = 'none';
      } else {
        menu.style.display = 'block';
      }
    }

    $(document).ready(function(){
      // Toggle user menu on click
      $('#sidebarUserProfile').on('click', function(e){
        e.stopPropagation();
        toggleSidebarUserMenu();
      });
      // Close user menu if clicked outside
      $(document).on('click', function(){
        $('#sidebarUserMenu').hide();
      });

      // --- Toggle Filter Modal ---
      $("#tableFilterIcon").click(function(){
          $("#filterModalOverlay, #filterModal").fadeIn();
      });
      $("#cancelFilter").click(function(){
          $("#filterModalOverlay, #filterModal").fadeOut();
      });
      
      // --- Toggle Add Child Modal ---
      $("#openAddChildModal").click(function(){
          $("#addChildModalOverlay, #addChildModal").fadeIn();
      });
      $("#cancelAddChild").click(function(){
          $("#addChildModalOverlay, #addChildModal").fadeOut();
      });
      
      // --- Filter Form Submission ---
      $("#filterForm").submit(function(e){
          e.preventDefault();
          var filterData = $(this).serialize();
          $.ajax({
              url: window.location.pathname,
              method: "GET",
              data: filterData + "&ajax=true",
              dataType: "json",
              success: function(data){
                  updateTable(data);
                  $("#filterModalOverlay, #filterModal").fadeOut();
              },
              error: function(xhr, status, error){
                  console.error("Error applying filters:", error);
              }
          });
      });
      
      // Reapply Hide Columns after table update
      function reapplyHideColumns(){
          $(".hide-col").each(function(){
              var colKey = $(this).val();
              if($(this).is(":checked")){
                  $("th[data-col='"+colKey+"'], td[data-col='"+colKey+"']").addClass("default-hidden");
              } else {
                  $("th[data-col='"+colKey+"'], td[data-col='"+colKey+"']").removeClass("default-hidden");
              }
          });
      }
      
      // AJAX Search Functionality
      let ajaxRequest = null;
      let searchTimeout = null;
      $("#searchInput").on("input", function(){
          let query = $(this).val().trim();
          clearTimeout(searchTimeout);
          if(ajaxRequest){
              ajaxRequest.abort();
          }
          searchTimeout = setTimeout(function(){
              ajaxRequest = $.ajax({
                  url: window.location.pathname,
                  method: "GET",
                  data: { search: query, ajax: "true" },
                  dataType: "json",
                  success: function(data){
                      updateTable(data);
                  },
                  error: function(xhr, status, error){
                      if(status !== "abort"){
                          console.error("Error fetching search results:", error);
                      }
                  }
              });
          }, 300);
      });
      
      function updateTable(data){
          let tableBody = $("#tableBody");
          tableBody.empty();
          if(data.length === 0){
              tableBody.append('<tr><td colspan="26">No records found</td></tr>');
              return;
          }
          data.forEach(function(child){
              let row = `
              <tr data-id="${child.child_id}">
                  <td>${child.child_id}</td>
                  <td>${child.name}</td>
                  <td>${child.birth_date}</td>
                  <td>${child.registered_date ? child.registered_date : '-'}</td>
                  <td>${child.health_medical_officer_division}</td>
                  <td>${child.family_health_medical_officer_division}</td>
                  <td>${child.parent_id}</td>
                  <td>${child.birth_hospital}</td>
                  <td>${child.weight}</td>
                  <td>${child.height}</td>
                  <td>${child.sex}</td>
                  <td>${child.supplementary_record_number || ''}</td>
                  <td>${child.gn_record_number || ''}</td>
                  <td class="default-hidden" data-col="breastfeeding_within_1h">${child.breastfeeding_within_1h ? 'Yes' : 'No'}</td>
                  <td class="default-hidden" data-col="congenital_hypothyroidism_check">${child.congenital_hypothyroidism_check ? 'Yes' : 'No'}</td>
                  <td class="default-hidden" data-col="hypothyroidism_test_results">${child.hypothyroidism_test_results}</td>
                  <td class="default-hidden" data-col="reasons_to_preserve">${child.reasons_to_preserve}</td>
                  <td class="default-hidden" data-col="breastfeeding_only_2m">${child.breastfeeding_only_2m ? 'Yes' : 'No'}</td>
                  <td class="default-hidden" data-col="breastfeeding_only_4m">${child.breastfeeding_only_4m ? 'Yes' : 'No'}</td>
                  <td class="default-hidden" data-col="breastfeeding_only_6m">${child.breastfeeding_only_6m ? 'Yes' : 'No'}</td>
                  <td class="default-hidden" data-col="start_feeding_other_foods">${child.start_feeding_other_foods ? 'Yes' : 'No'}</td>
                  <td class="default-hidden" data-col="age_started_feeding_other_foods">${child.age_started_feeding_other_foods || ''}</td>
                  <td class="default-hidden" data-col="age_stopped_breastfeeding">${child.age_stopped_breastfeeding || ''}</td>
                  <td class="default-hidden" data-col="other_foods_at_1_year">${child.other_foods_at_1_year ? 'Yes' : 'No'}</td>
                  <td class="default-hidden" data-col="started_feeding_other_foods_4m">${child.started_feeding_other_foods_4m ? 'Yes' : 'No'}</td>
                  <td class="default-hidden" data-col="started_feeding_other_foods_6m">${child.started_feeding_other_foods_6m ? 'Yes' : 'No'}</td>
                  <td class="action-icons">
                      <i class="fas fa-eye view-btn"></i>
                      <i class="fas fa-edit edit-btn"></i>
                      <i class="fas fa-trash delete-icon delete-btn" data-id="${child.child_id}"></i>
                  </td>
              </tr>`;
              $("#tableBody").append(row);
          });
          reapplyHideColumns();
      }
      
      // Delete Confirmation Modal
      $(document).on("click", ".delete-btn", function(){
          let childId = $(this).data("id");
          $("#deleteConfirmModal").data("childId", childId);
          $("#deleteConfirmModalOverlay, #deleteConfirmModal").fadeIn();
      });
      
      $("#cancelDelete").click(function(){
          $("#deleteConfirmModalOverlay, #deleteConfirmModal").fadeOut();
      });
      
      $("#confirmDelete").click(function(){
          let childId = $("#deleteConfirmModal").data("childId");
          $.post("", { delete_id: childId }, function(response){
              let res = (typeof response === "string") ? JSON.parse(response) : response;
              if(res.status === "success"){
                  showAlert("success", "Record deleted successfully!");
                  setTimeout(function(){ location.reload(); }, 1500);
              } else {
                  showAlert("error", "Error deleting record: " + res.message);
              }
              $("#deleteConfirmModalOverlay, #deleteConfirmModal").fadeOut();
          }, "json");
      });
      
      // Open Modal for Editing
      $(document).on("click", ".edit-btn", function(){
          let row = $(this).closest("tr");
          let childId = row.data("id");
          let childName = row.children().eq(1).text().trim();
          let healthDivision = row.children().eq(4).text().trim();
          let familyHealthDivision = row.children().eq(5).text().trim();
          let supplementaryRecord = row.children().eq(11).text().trim();
          let gnRecord = row.children().eq(12).text().trim();
          let childWeight = row.children().eq(8).text().trim();
          let childHeight = row.children().eq(9).text().trim();

          $("#editChildId").val(childId);
          $("#editChildName").val(childName);
          $("#editHealthDivision").val(healthDivision);
          $("#editFamilyHealthDivision").val(familyHealthDivision);
          $("#editSupplementaryRecord").val(supplementaryRecord);
          $("#editGnRecord").val(gnRecord);
          $("#editChildWeight").val(childWeight);
          $("#editChildHeight").val(childHeight);

          // Handle radio for breastfeeding fields if applicable
          let breast2Val = row.find('[data-col="breastfeeding_only_2m"]').text().trim();
          if (breast2Val === 'Yes') {
              $("#editBreast2Yes").prop("checked", true);
          } else {
              $("#editBreast2No").prop("checked", true);
          }
          let breast4Val = row.find('[data-col="breastfeeding_only_4m"]').text().trim();
          if (breast4Val === 'Yes') {
              $("#editBreast4Yes").prop("checked", true);
          } else {
              $("#editBreast4No").prop("checked", true);
          }

          $("#modalOverlay, #editModal").fadeIn();
      });
      
      // Cancel Edit
      $("#cancelEdit").click(function(){
          $("#modalOverlay, #editModal").fadeOut();
      });
      
      // Submit Edit Form
      $("#editForm").submit(function(e){
          e.preventDefault();
          let childId = $("#editChildId").val();
          let healthDivision = $("#editHealthDivision").val();
          let familyHealthDivision = $("#editFamilyHealthDivision").val();
          let supplementaryRecord = $("#editSupplementaryRecord").val();
          let gnRecord = $("#editGnRecord").val();
          let breast2 = $("input[name='breast2']:checked").val();
          let breast4 = $("input[name='breast4']:checked").val();
          let weight = $("#editChildWeight").val();
          let height = $("#editChildHeight").val();

          $.post("", {
              child_id: childId,
              health_division: healthDivision,
              family_health_division: familyHealthDivision,
              supplementary_record: supplementaryRecord,
              gn_record: gnRecord,
              breast2: breast2,
              breast4: breast4,
              weight: weight,
              height: height
          }, function(response){
              let res = (typeof response === "string") ? JSON.parse(response) : response;
              if(res.status === "success"){
                  showAlert("success", "Child record updated successfully!");
                  setTimeout(function(){ location.reload(); }, 1500);
              } else {
                  showAlert("error", "Error updating record: " + res.message);
              }
          }, "json");
          $("#modalOverlay, #editModal").fadeOut();
      });
      
      // Add Child Form Submission
      $("#addChildForm").submit(function(e){
          e.preventDefault();
          var formData = $(this).serialize();
          $.ajax({
              url: window.location.pathname,
              method: "POST",
              data: formData,
              dataType: "json",
              success: function(response){
                  if(response.status === "success"){
                      showAlert("success", response.message);
                      setTimeout(function(){ location.reload(); }, 1500);
                  } else {
                      showAlert("error", response.message);
                  }
                  $("#addChildModalOverlay, #addChildModal").fadeOut();
              },
              error: function(xhr, status, error){
                  console.error("Error adding child:", error);
              }
          });
      });
      
      // VIEW CHILD MODAL LOGIC
      $(document).on("click", ".view-btn", function(){
          let row = $(this).closest("tr");
          let childId = row.children().eq(0).text().trim();
          let childName = row.children().eq(1).text().trim();
          let birthDate = row.children().eq(2).text().trim();
          let registeredDate = row.children().eq(3).text().trim();
          let healthDivision = row.children().eq(4).text().trim();
          let familyHealthDivision = row.children().eq(5).text().trim();
          let parentId = row.children().eq(6).text().trim();
          let birthHospital = row.children().eq(7).text().trim();
          let weight = row.children().eq(8).text().trim();
          let height = row.children().eq(9).text().trim();
          let gender = row.children().eq(10).text().trim();
          let supplementary = row.children().eq(11).text().trim();
          let gnRecord = row.children().eq(12).text().trim();
          
          // Additional data
          let breastfeeding = row.find('td[data-col="breastfeeding_within_1h"]').text().trim();
          let hypothyroidism = row.find('td[data-col="congenital_hypothyroidism_check"]').text().trim();
          let hypothyroidismResult = row.find('td[data-col="hypothyroidism_test_results"]').text().trim();
          let reasons = row.find('td[data-col="reasons_to_preserve"]').text().trim();
          let breastfeeding2m = row.find('td[data-col="breastfeeding_only_2m"]').text().trim();
          let breastfeeding4m = row.find('td[data-col="breastfeeding_only_4m"]').text().trim();
          let breastfeeding6m = row.find('td[data-col="breastfeeding_only_6m"]').text().trim();
          let startFeeding4m = row.find('td[data-col="started_feeding_other_foods_4m"]').text().trim();
          let startFeeding6m = row.find('td[data-col="started_feeding_other_foods_6m"]').text().trim();
          let ageStartedFeeding = row.find('td[data-col="age_started_feeding_other_foods"]').text().trim();
          let ageStoppedFeeding = row.find('td[data-col="age_stopped_breastfeeding"]').text().trim();
          let otherFoods = row.find('td[data-col="other_foods_at_1_year"]').text().trim();
          
          // Populate modal
          $("#viewChildId").text(childId);
          $("#viewChildName").text(childName);
          $("#viewBirthDate").text(birthDate);
          $("#viewRegisteredDate").text(registeredDate);
          $("#viewHealthDivision").text(healthDivision);
          $("#viewFamilyHealthDivision").text(familyHealthDivision);
          $("#viewParentId").text(parentId);
          $("#viewBirthHospital").text(birthHospital);
          $("#viewWeight").text(weight);
          $("#viewHeight").text(height);
          $("#viewGender").text(gender);
          $("#viewSupplementary").text(supplementary);
          $("#viewGnRecord").text(gnRecord);
          
          $("#viewBreastfeeding").text(breastfeeding);
          $("#viewHypothyroidism").text(hypothyroidism);
          $("#viewHypothyroidismResult").text(hypothyroidismResult);
          $("#viewReasons").text(reasons);
          $("#viewBreastfeeding2m").text(breastfeeding2m);
          $("#viewBreastfeeding4m").text(breastfeeding4m);
          $("#viewBreastfeeding6m").text(breastfeeding6m);
          $("#viewStartFeeding4m").text(startFeeding4m);
          $("#viewStartFeeding6m").text(startFeeding6m);
          $("#viewAgeStartedFeeding").text(ageStartedFeeding);
          $("#viewAgeStoppedFeeding").text(ageStoppedFeeding);
          $("#viewOtherFoods").text(otherFoods);
          
          // Open the view modal
          $("#viewChildModalOverlay, #viewChildModal").fadeIn();

          // Fetch BMI Data for Chart
          fetchBMIDataForChart(childId);
      });
      
      // Toggle collapse for sections
      $(".view-child-modal .section-title").click(function(){
          $(this).next(".section-content").slideToggle();
          let icon = $(this).find("i");
          icon.toggleClass("fa-chevron-down fa-chevron-up");
      });
      
      // Close View Modal
      $("#closeViewChild, #viewChildModalOverlay").click(function(){
          $("#viewChildModalOverlay, #viewChildModal").fadeOut();
      });
      
      // Alert Function
      function showAlert(type, message){
          let alertBox = $("#alertBox");
          alertBox.removeClass("alert-success alert-error");
          if(type === "success"){
              alertBox.addClass("alert-success");
          } else {
              alertBox.addClass("alert-error");
          }
          alertBox.text(message).fadeIn();
          setTimeout(function(){ alertBox.fadeOut(); }, 3000);
      }

      // BMI CHART
      let bmiChart = null;
      function fetchBMIDataForChart(childId) {
        $.ajax({
          url: window.location.pathname,
          method: "GET",
          data: { fetch_bmi: true, child_id: childId },
          dataType: "json",
          success: function(bmiData) {
            if (!bmiData || bmiData.length === 0) {
              if (bmiChart instanceof Chart) {
                bmiChart.destroy();
              }
              return;
            }
            let labels = bmiData.map(entry => {
              let d = new Date(entry.recorded_at);
              return d.toLocaleDateString();
            });
            let bmiValues = bmiData.map(entry => entry.bmi);

            let ctx = document.getElementById("bmiChart").getContext("2d");
            if (bmiChart instanceof Chart) {
              bmiChart.destroy();
            }
            bmiChart = new Chart(ctx, {
              type: "line",
              data: {
                labels: labels,
                datasets: [{
                  label: "BMI Over Time",
                  data: bmiValues,
                  borderColor: "blue",
                  borderWidth: 2,
                  fill: false
                }]
              },
              options: {
                responsive: true,
                scales: {
                  x: {
                    title: { display: true, text: "Date" }
                  },
                  y: {
                    title: { display: true, text: "BMI" },
                    beginAtZero: false
                  }
                }
              }
            });
          },
          error: function(xhr, status, error) {
            console.error("Error fetching BMI data:", error);
          }
        });
      }
    });

    // Additional safety to hide user menu if open and clicked outside
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
