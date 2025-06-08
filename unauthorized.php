<?php
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KidsGrow - Access Denied</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #8FC4F1, #274FB4);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            text-align: center;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
        }
        .icon {
            font-size: 64px;
            color: #ff6b6b;
            margin-bottom: 20px;
            width: 100px;
            height: 100px;
            line-height: 100px;
            background-color: #ff6b6b;
            border-radius: 50%;
            margin: 0 auto 30px;
            color: white;
        }
        h1 {
            color: #274FB4;
            font-size: 28px;
            margin-bottom: 20px;
            line-height: 1.4;
        }
        .message {
            color: #555;
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .button {
            background-color: #274FB4;
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 18px;
            border-radius: 30px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.2s;
        }
        .button:hover {
            background-color: #1a3a8a;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">!</div>
        <h1>Sorry, You Are Not Allowed to Access This Page</h1>
        <p class="message">It seems you don't have the necessary permissions to view this content. Please sign in with the appropriate credentials or contact the administrator for assistance.</p>
        <button class="button" onclick="window.location.href='logout.php'">Go to Sign In Page</button>
    </div>
</body>
</html>
