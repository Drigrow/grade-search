<?php
// Start the session
session_start();

// Destroy all session variables
session_unset();

// Destroy the session itself
session_destroy();

// Redirect to login page or homepage
header("Location: login.php"); 
exit();
?>
