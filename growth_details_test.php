<?php
// test_add.php
// This script isolates the "add record" process using the same database credentials and table structure.

ob_start();

// Use the same database credentials as in your growth_details.php code.
$dsn         = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user     = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Sample data for the new growth record.
$child_id = 3; // Ensure this child_id exists in your child table.
$measurement_date = '2025-03-29';
$weight = 15.5;
$height = 1.0;

// Calculate BMI and nutrition status.
$bmi = round($weight / ($height * $height), 2);
if ($bmi < 18.5) {
    $nutrition_status = 'Underweight';
} elseif ($bmi < 25) {
    $nutrition_status = 'Normal';
} elseif ($bmi < 30) {
    $nutrition_status = 'Overweight';
} else {
    $nutrition_status = 'Obese';
}
$medical_recommendation = 'No issues noted';

// Prepare and execute the INSERT statement.
try {
    $stmt = $pdo->prepare("
        INSERT INTO child_growth_details
        (child_id, measurement_date, weight, height, bmi, nutrition_status, medical_recommendation)
        VALUES (:child_id, :measurement_date, :weight, :height, :bmi, :nutrition_status, :medical_recommendation)
    ");
    $stmt->execute([
        ':child_id'              => $child_id,
        ':measurement_date'      => $measurement_date,
        ':weight'                => $weight,
        ':height'                => $height,
        ':bmi'                   => $bmi,
        ':nutrition_status'      => $nutrition_status,
        ':medical_recommendation'=> $medical_recommendation
    ]);
    echo "Record added successfully!";
} catch (PDOException $e) {
    echo "Error adding record: " . $e->getMessage();
}

ob_end_flush();
?>
