<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $to_email = $_POST['to_email'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];

    // Validate form data
    if (empty($to_email) || empty($subject) || empty($message)) {
        echo "Error: All fields are required.";
        exit;
    }

    // Prepare JSON payload
    $data = json_encode([
        "to_email" => $to_email,
        "subject" => $subject,
        "message" => $message
    ]);

    // Initialize cURL
    $ch = curl_init("http://54.179.72.79:5001/send-email");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,  // Return the response as a string
        CURLOPT_POST => true,            // Send as POST request
        CURLOPT_POSTFIELDS => $data,      // Attach the JSON payload
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ],
        CURLOPT_FAILONERROR => true       // Fail on HTTP errors (4xx, 5xx)
    ]);

    // Execute cURL request
    $response = curl_exec($ch);

    // Check for cURL errors
    if (curl_errno($ch)) {
        echo "cURL Error: " . curl_error($ch);
    } else {
        // Display the API response
        echo "Response: " . $response;
    }

    // Close cURL session
    curl_close($ch);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Test Mail API</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }
        form {
            max-width: 400px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input[type="email"], input[type="text"], textarea {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <h2>Send Test Email</h2>
    <form method="POST">
        <label for="to_email">Recipient Email:</label>
        <input type="email" id="to_email" name="to_email" required><br><br>

        <label for="subject">Subject:</label>
        <input type="text" id="subject" name="subject" required><br><br>

        <label for="message">Message:</label>
        <textarea id="message" name="message" rows="5" required></textarea><br><br>

        <button type="submit">Send Email</button>
    </form>
</body>
</html>