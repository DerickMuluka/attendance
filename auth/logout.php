<?php
require_once '../config.php';

// Start session
session_start();

// Destroy session
session_unset();
session_destroy();

// Clear local storage via JavaScript and redirect
echo "<script>
    localStorage.removeItem('authToken');
    localStorage.removeItem('userData');
    window.location.href = '../index.html';
</script>";
exit;
?>