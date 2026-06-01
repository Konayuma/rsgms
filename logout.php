<?php
// File: logout.php
// Logout script
session_start();
session_destroy();
header('Location: index.php');
exit();
?>
