<?php

session_start(); // Start the session

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: signin.php');
    exit;
}


// Clear messages after displaying them
$errorMessage = $_SESSION['error_message'] ?? '';
$successMessage = $_SESSION['success_message'] ?? '';
unset($_SESSION['error_message'], $_SESSION['success_message']);

// Database connection details
$dsn = "pgsql:host=ep-purple-sea-a1d1bece-pooler.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require";
$db_user = "neondb_owner";
$db_password = "npg_39CVAQbvIlOy";

try {
    $pdo = new PDO($dsn, $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database connection failed: " . $e->getMessage();
}

// Initialize error and success messages
$errorMessage = "";
$successMessage = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize form data
    $name = trim($_POST['name']);
    $birth_date = $_POST['birth_date'];
    $birth_hospital = trim($_POST['birth_hospital']);
    $breastfeeding = ($_POST['breastfeeding'] === 'Yes') ? true : false; // Convert to boolean
    $hypothyroidism = ($_POST['hypothyroidism'] === 'Yes') ? true : false;
    $hypothyroidism_result = $_POST['hypothyroidism_result'];
    $reasons_to_preserve = trim($_POST['reasons_to_preserve']);
    $weight = $_POST['weight'];
    $gender = $_POST['gender'];
    $health_medical_officer_division = trim($_POST['health_medical_officer_division']);
    $family_health_medical_officer_division = trim($_POST['family_health_medical_officer_division']);
    $mother_id = $_POST['mother_id']; // Assuming this is the parent_id

    // Validate mother_id as an integer
    if (!filter_var($mother_id, FILTER_VALIDATE_INT)) {
        $errorMessage = "Invalid Mother ID. Please provide a valid integer.";
    } else {
        // Check if the mother exists in the parent table
        $checkParent = $pdo->prepare("SELECT COUNT(*) FROM parent WHERE parent_id = ?");
        $checkParent->execute([$mother_id]);
        $parentExists = $checkParent->fetchColumn();

        if (!$parentExists) {
            $errorMessage = "Mother is not registered. Please register the mother first.";
        } else {
            try {
                // Prepare the SQL query
                $stmt = $pdo->prepare("
                    INSERT INTO child (
                        parent_id, name, birth_date, birth_hospital, breastfeeding_within_1h, 
                        congenital_hypothyroidism_check, hypothyroidism_test_results, reasons_to_preserve, 
                        weight, sex, health_medical_officer_division, family_health_medical_officer_division
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                // Execute the query with form data
                $stmt->execute([
                    $mother_id, $name, $birth_date, $birth_hospital, $breastfeeding,
                    $hypothyroidism, $hypothyroidism_result, $reasons_to_preserve,
                    $weight, $gender, $health_medical_officer_division, $family_health_medical_officer_division
                ]);

                $successMessage = "Child record added successfully.";
            } catch (PDOException $e) {
                $errorMessage = "Error adding child record: " . $e->getMessage();
            }
        }
    }
}

// Store messages in session variables to display on the same page
$_SESSION['error_message'] = $errorMessage;
$_SESSION['success_message'] = $successMessage;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Child</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Roboto, sans-serif;
        }
        
        body {
            display: flex;
            background-color: #F4F4F4;
        }
        
        .sidebar {
            width: 250px;
            height: 100vh;
            position: fixed;
            top: 0px;
            left: 0px;
            background: linear-gradient(to bottom, #8FC4F1, #274FB4);
            color: #fff;
            padding: 20px;
        }
        
        .sidebar h1 {
            font-size: 20px;
            margin-bottom: 30px;
        }
        
        .sidebar a {
            display: flex;
            align-items: center;
            color: #fff;
            text-decoration: none;
            padding: 10px 0;
        }
        
        .sidebar a i {
            margin-right: 10px;
        }
        
        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        /* Form Container */
        .form-container {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0px 3px 12px rgba(0, 0, 0, 0.1);
            max-width: 800px;
            width: 100%;
            margin: 0 auto;
        }

        /* Form Title */
        .form-title {
            font-size: 22px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 25px;
        }

        /* Form Group Layout */
        .form-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        /* Labels */
        .form-group label {
            width: 45%;
            font-weight: 600;
            color: #333;
        }

        /* General Input & Select Styling */
        .form-group input,
        .form-group select {
            width: 50%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f5f5f5; /* Uniform background color */
            font-size: 14px;
            color: #333;
            font-family: inherit; /* Ensures uniform font */
        }

        /* Date Picker Input Styling */
        .form-group input[type="date"] {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-color: #f5f5f5 !important; /* Force matching background */
            color: #333 !important; /* Ensures text color consistency */
            font-size: 14px;
            font-family: inherit;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            width: 50%;
            position: relative;
        }

        /* Override User Agent Styles */
        .form-group input[type="date"]::-webkit-datetime-edit {
            color: #333 !important; /* Ensures text color matches other inputs */
            font-size: 14px;
            font-family: inherit;
        }

        /* Hide Default Calendar Icon */
        .form-group input[type="date"]::-webkit-calendar-picker-indicator {
            opacity: 0;
            position: absolute;
            right: 10px;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        /* Custom Calendar Icon */
        .form-group input[type="date"] {
            background: url('https://img.icons8.com/?size=100&id=WpQIVxfhhzqt&format=png&color=000000') no-repeat right 10px center;
            background-size: 20px 20px;
            padding-right: 35px;
        }

        /* Hover & Focus Effects for Consistency */
        .form-group input[type="date"]:hover,
        .form-group input[type="date"]:focus {
            background-color: #eaeaea; /* Matches other input hover color */
            border-color: #274FB4;
            outline: none;
            box-shadow: 0 0 5px rgba(39, 79, 180, 0.3);
        }


        /* Radio Button Group */
        .radio-group {
            display: flex;
            gap: 20px;
            width: 50%;
            align-items: center;
        }

        /* Radio Button Alignment */
        .radio-option {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Fix misalignment of Yes/No */
        .radio-option input[type="radio"] {
            margin: 0;
            width: 16px; /* Adjusted for better alignment */
            height: 16px;
        }

        /* Ensure labels are inline with radio buttons */
        .radio-option label {
            font-size: 14px;
            line-height: 1;
            display: inline-block;
            margin-left: 4px;
            vertical-align: middle;
        }

        /* Buttons Layout */
        .button-group {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
        }

        /* Buttons */
        .btn {
            padding: 10px 22px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            display: inline-block;
            transition: 0.3s ease-in-out;
        }

        /* Cancel Button */
        .btn-cancel {
            background: white;
            border: 2px solid #274FB4;
            color: #274FB4;
            transition: 0.3s ease-in-out;
            display: inline-block; /* Prevent button from expanding */
            text-align: center;
            padding: 10px 22px; /* Ensures consistent button size */
        }

        /* Ensure <a> inside the button looks and behaves like a button */
        .btn-cancel a {
            color: inherit; /* Inherit button text color */
            text-decoration: none; /* Remove underline */
            display: block; /* Make entire button clickable */
            width: 100%;
            height: 100%;
        }

        /* Hover Effect */
        .btn-cancel:hover {
            background: #274FB4;
            color: white;
        }

        /* Ensure link inside keeps correct color on hover */
        .btn-cancel:hover a {
            color: white;
        }

        /* Save Button */
        .btn-save {
            background: #274FB4;
            color: white;
            transition: 0.3s ease-in-out;
        }

        .btn-save:hover {
            background: #1f3a8a;
        }



        
    .popup {
        display: none;
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translateX(-50%);
        padding: 15px 20px;
        border-radius: 5px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        font-size: 14px;
        z-index: 1000;
        color: white;
        text-align: center;
    }
    .error-popup {
        background-color: #ff4d4d; /* Red for errors */
    }
    .success-popup {
        background-color: #4caf50; /* Green for success */
    }
    .popup.show {
        display: block;
    }
</style>
</head>
<body>
    <div class="sidebar">
        <h1><i class="fas fa-heartbeat" style="padding-right: 6px;"></i>Ministry of Health</h1>
        <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="child_profile.php"><i class="fas fa-child"></i> Child Profiles</a>
        <a href="parent_profile.php"><i class="fas fa-users"></i> Parent Profiles</a>
        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'SuperAdmin'): ?>
        <a href="add_admin.php"><i class="fas fa-user-shield"></i> Add Admin</a>
        <?php endif; ?>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
    </div>
    <div class="container">
        <div class="form-container">
            <div class="form-title">ADD NEW CHILD</div>
            <form method="POST" action="">
                <div class="form-group">
                    <label>Name</label>
                    <input type="text"  class="form-control" name="name" required>
                </div>
                <div class="form-group">
                    <label>Birth Date</label>
                    <input type="date"  class="form-control" name="birth_date" required>
                </div>
                <div class="form-group">
                    <label>Registered Date</label>
                    <input type="date"  class="form-control" name="registered_date" required>
                </div>
                <div class="form-group">
                    <label>Health Medical Officer Division</label>
                    <input type="text"  class="form-control" name="health_medical_officer_division" required>
                </div>
                <div class="form-group">
                    <label>Weight (kg)</label>
                    <input type="number" step="0.1"  class="form-control" name="weight" min="0.5" max="4.5" step="0.1" required>
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="gender-male" name="gender" value="Male" required>
                            <label for="gender-male">Male</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="gender-female" name="gender" value="Female" required>
                            <label for="gender-female">Female</label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Family Health Medical Officer Division</label>
                    <input type="text"  class="form-control" name="family_health_medical_officer_division" required>
                </div>
                <div class="form-group">
                    <label>Supplementary Regional Record Number</label>
                    <input type="text"  class="form-control" name="supplementary_regional_record_number">
                </div>
                <div class="form-group">
                    <label>Grama Niladhari Record Number</label>
                    <input type="text"  class="form-control" name="grama_niladhari_record_number">
                </div>
                <div class="form-group">
                    <label>Birth Hospital</label>
                    <input type="text"  class="form-control" name="birth_hospital" required>
                </div>
                <div class="form-group">
                    <label>Mother's ID</label>
                    <input type="text"  class="form-control" name="mother_id" required>
                </div>
                <div class="form-group">
                    <label>Did start breastfeeding within an hour after delivery?</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="breastfeeding-yes" name="breastfeeding" value="Yes" required>
                            <label for="breastfeeding-yes">Yes</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="breastfeeding-no" name="breastfeeding" value="No" required>
                            <label for="breastfeeding-no">No</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Tested for hypothyroidism?</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="hypothyroidism-yes" name="hypothyroidism" value="Yes" required>
                            <label for="hypothyroidism-yes">Yes</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="hypothyroidism-no" name="hypothyroidism" value="No" required>
                            <label for="hypothyroidism-no">No</label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Hypothyroidism Test Result</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="hypothyroidism-positive" name="hypothyroidism_result" value="Positive" required>
                            <label for="hypothyroidism-positive">Positive</label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="hypothyroidism-negative" name="hypothyroidism_result" value="Negative" required>
                            <label for="hypothyroidism-negative">Negative</label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label>Reasons to Preserve</label>
                    <input type="text"  class="form-control" name="reasons_to_preserve">
                </div>
                <div class="button-group">
                    <button class="btn btn-cancel">
                        <a href="child_profile.php">Cancel</a>
                    </button>
                    <button type="submit" class="btn btn-save">Save</button>
                </div>


            </form>
        </div>
        <!-- Error Popup -->
        <div id="errorPopup" class="popup error-popup <?php echo $errorMessage ? 'show' : ''; ?>">
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>

        <!-- Success Popup -->
        <div id="successPopup" class="popup success-popup <?php echo $successMessage ? 'show' : ''; ?>">
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
    </div>
    <script>
    document.addEventListener("DOMContentLoaded", function () {
    let userRole = "<?php echo $_SESSION['user_role'] ?? ''; ?>"; // Get role from PHP session

    if (userRole !== "SuperAdmin") {
        let addAdminLink = document.querySelector(".sidebar a[href='add_admin.php']");
        if (addAdminLink) {
            addAdminLink.style.display = "none"; // Hide the Add Admin link
        }
    }
});

    document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    const errorPopup = document.getElementById('errorPopup');

    function showError(message) {
        if (errorPopup) {
            errorPopup.textContent = message;
            errorPopup.classList.add('show');
            setTimeout(() => {
                errorPopup.classList.remove('show');
                errorPopup.textContent = '';
            }, 3000);
        }
    }

    form.addEventListener('submit', function (event) {
        let isValid = true;

        // Validate Name
        const name = document.querySelector('input[name="name"]');
        if (!name.value.trim()) {
            showError('Name is required.');
            isValid = false;
        }

        // Validate Birth Date
        const birthDate = document.querySelector('input[name="birth_date"]');
        if (!birthDate.value) {
            showError('Birth Date is required.');
            isValid = false;
        }

        // Validate Registered Date
        const registeredDate = document.querySelector('input[name="registered_date"]');
        if (!registeredDate.value) {
            showError('Registered Date is required.');
            isValid = false;
        }

        // Validate Health Medical Officer Division
        const healthMedicalOfficerDivision = document.querySelector('input[name="health_medical_officer_division"]');
        if (!healthMedicalOfficerDivision.value.trim()) {
            showError('Health Medical Officer Division is required.');
            isValid = false;
        }

        // Validate Weight (Only Max 4.5 kg)
        const weight = document.querySelector('input[name="weight"]');
        const weightValue = parseFloat(weight.value);

        if (!weight.value || isNaN(weightValue) || weightValue <= 0 || weightValue > 4.5) {
            showError('Weight must be a valid number and not exceed 4.5 kg.');
            isValid = false;
        }


        // Validate Gender
        const gender = document.querySelectorAll('input[name="gender"]');
        let genderSelected = false;
        gender.forEach(radio => {
            if (radio.checked) genderSelected = true;
        });
        if (!genderSelected) {
            showError('Gender is required.');
            isValid = false;
        }

        // Validate Family Health Medical Officer Division
        const familyHealthMedicalOfficerDivision = document.querySelector('input[name="family_health_medical_officer_division"]');
        if (!familyHealthMedicalOfficerDivision.value.trim()) {
            showError('Family Health Medical Officer Division is required.');
            isValid = false;
        }

        // Validate Birth Hospital
        const birthHospital = document.querySelector('input[name="birth_hospital"]');
        if (!birthHospital.value.trim()) {
            showError('Birth Hospital is required.');
            isValid = false;
        }

        // Validate Mother's ID
        const motherId = document.querySelector('input[name="mother_id"]');
        if (!motherId.value || isNaN(motherId.value) || parseInt(motherId.value) <= 0) {
            showError('Mother\'s ID must be a valid positive integer.');
            isValid = false;
        }

        // Validate Breastfeeding
        const breastfeeding = document.querySelectorAll('input[name="breastfeeding"]');
        let breastfeedingSelected = false;
        breastfeeding.forEach(radio => {
            if (radio.checked) breastfeedingSelected = true;
        });
        if (!breastfeedingSelected) {
            showError('Breastfeeding status is required.');
            isValid = false;
        }

        // Validate Hypothyroidism
        const hypothyroidism = document.querySelectorAll('input[name="hypothyroidism"]');
        let hypothyroidismSelected = false;
        hypothyroidism.forEach(radio => {
            if (radio.checked) hypothyroidismSelected = true;
        });
        if (!hypothyroidismSelected) {
            showError('Hypothyroidism status is required.');
            isValid = false;
        }

        // Validate Hypothyroidism Test Result
        const hypothyroidismResult = document.querySelectorAll('input[name="hypothyroidism_result"]');
        let hypothyroidismResultSelected = false;
        hypothyroidismResult.forEach(radio => {
            if (radio.checked) hypothyroidismResultSelected = true;
        });
        if (!hypothyroidismResultSelected) {
            showError('Hypothyroidism Test Result is required.');
            isValid = false;
        }

        // Prevent form submission if any validation fails
        if (!isValid) {
            event.preventDefault();
        }
    });
});

document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    const birthDateInput = document.querySelector('input[name="birth_date"]');
    const registeredDateInput = document.querySelector('input[name="registered_date"]');
    const errorPopup = document.getElementById('errorPopup');

    function showError(message) {
        if (errorPopup) {
            errorPopup.textContent = message;
            errorPopup.classList.add('show');
            setTimeout(() => {
                errorPopup.classList.remove('show');
                errorPopup.textContent = '';
            }, 3000);
        }
    }

    form.addEventListener('submit', function (event) {
        const birthDate = new Date(birthDateInput.value);
        const registeredDate = new Date(registeredDateInput.value);

        if (registeredDate < birthDate) {
            showError('Registered date cannot be before the birth date.');
            event.preventDefault(); // Prevent form submission
        }
    });
});



document.addEventListener("DOMContentLoaded", function () {
    const errorPopup = document.getElementById("errorPopup");
    const successPopup = document.getElementById("successPopup");

    // Function to hide popup after 3 seconds
    const hidePopup = (popup) => {
        if (popup) {
            setTimeout(() => {
                popup.classList.remove("show");
                popup.textContent = ''; // Clear content
            }, 3000);
        }
    };

    // Handle error popup
    if (errorPopup && errorPopup.classList.contains("show")) {
        hidePopup(errorPopup);
    }

    // Handle success popup
    if (successPopup && successPopup.classList.contains("show")) {
        hidePopup(successPopup);
    }
});




    </script>
</body>
</html>