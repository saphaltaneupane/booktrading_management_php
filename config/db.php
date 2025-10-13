<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'project');

// Khalti credentials
define('KHALTI_SECRET_KEY', 'b42d1cff70d84d759d823a75f0ac17d5');
define('KHALTI_PUBLIC_KEY', 'c6e784a644ca4f3bbe85d89b25213fd1');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

session_start();
?>