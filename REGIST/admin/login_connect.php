<?php
$host = 'localhost'; // Change if using a different server
$user = 'root'; // Default XAMPP username
$pass = ''; // Default XAMPP password (empty)
$dbname = 'documentrequestdb'; // Your database name

// Create connection
$con = mysqli_connect($host, $user, $pass, $dbname);

// Check connection
if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
