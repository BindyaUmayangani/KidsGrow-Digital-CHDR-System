<?php
$api_key = "6114e595f0f58654aca55291325e135e"; // Your ImgBB API Key
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Image Upload - ImgBB</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background: #f4f7f6;
            font-family: 'Arial', sans-serif;
        }
        .container {
            max-width: 500px;
            margin-top: 50px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .btn-upload {
            width: 100%;
            background: #007bff;
            color: white;
            font-weight: bold;
        }
        .image-preview {
            display: none;
            margin-top: 20px;
            text-align: center;
        }
        .image-preview img {
            max-width: 100%;
            border-radius: 5px;
        }
        .result {
            margin-top: 20px;
            padding: 10px;
            border-radius: 5px;
            background: #e9ffe9;
            color: #28a745;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="container">
    <h2 class="text-center">Upload Image</h2>
    <form id="uploadForm" enctype="multipart/form-data">
        <input type="file" class="form-control" id="imageInput" name="image" accept="image/*" required>
        <button type="submit" class="btn btn-upload mt-3">Upload</button>
    </form>

    <div class="image-preview">
        <h5>Preview:</h5>
        <img id="previewImage" src="">
    </div>

    <div id="uploadResult" class="result"></div>
</div>

<script>
    document.getElementById('imageInput').addEventListener('change', function(event) {
        const reader = new FileReader();
        reader.onload = function() {
            document.getElementById('previewImage').src = reader.result;
            document.querySelector('.image-preview').style.display = 'block';
        };
        reader.readAsDataURL(event.target.files[0]);
    });

    document.getElementById("uploadForm").addEventListener("submit", function(event) {
        event.preventDefault();
        let formData = new FormData();
        let imageFile = document.getElementById("imageInput").files[0];
        formData.append("image", imageFile);

        fetch("https://api.imgbb.com/1/upload?key=<?php echo $api_key; ?>", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById("uploadResult").innerHTML = 
                    `<a href="${data.data.url}" target="_blank">${data.data.url}</a>`;
            } else {
                document.getElementById("uploadResult").innerHTML = "Upload failed.";
            }
        })
        .catch(error => {
            document.getElementById("uploadResult").innerHTML = "Error: " + error;
        });
    });
</script>

</body>
</html>
