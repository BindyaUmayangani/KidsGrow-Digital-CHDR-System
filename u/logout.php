<?php
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Redirect the user to the sign-in page
header("Location: signin.php");
exit();
?>
