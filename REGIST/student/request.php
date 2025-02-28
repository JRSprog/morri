<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'request_db_connect.php'; // Ensure this file exists and has correct DB connection

$id = (isset($_GET['id']) ? $_GET['id'] : '');
	$sql = "SELECT * FROM `user1` where id = '$id'";
	$result = mysqli_query($conn, $sql);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $student_number = $_POST['student_number'];
    $password = $_POST['password'];

    // Debugging: Check if values are received
    if (empty($student_number) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Missing Credentials"]);
        exit();
    }

    // Query to check user credentials
    $stmt = $conn->prepare("SELECT * FROM users WHERE student_number = ? AND password = MD5(?)");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "SQL Error: " . $conn->error]);
        exit();
    }

    $stmt->bind_param("ss", $student_number, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    // Debugging: Check if the query returned results
    if ($result->num_rows > 0) {
        $_SESSION['student_number'] = $student_number;
        echo json_encode(["status" => "success"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid Credentials"]);
    }

    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Font Awesome CDN for icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="../css/styles.css">
  <link rel="stylesheet" href="../css/styles6.css">
   <link rel="shortcut icon" href="../images/blogo.png" type="x-icon">
  <title>Dashboard</title>
</head>
<body>

    <div id="loading">
        <div class="loader"></div>
    </div>

    <!-- Overlay Sidebar -->
    <div id="sidebar" class="sidebar">
      <div class="logo">
      <img src="../images/blogo.png" alt="Logo">
          <p>Morri</p>
      </div>
      <ul class="sidebar-nav">
        <li><a href="home-page.php?id=<?php echo $id?>"><i class="fas fa-home"></i>&nbsp; Home</a></li>
        <li><a href="profile-page.php?id=<?php echo $id?>"><i class="fas fa-user"></i>&nbsp; Profile</a></li>
        <li><a href="newdash1.php?id=<?php echo $id?>"><i class="fas fa-folder-open"></i>&nbsp; Document request</a></li>
        <li><a href="request-status.php?id=<?php echo $id?>"><i class="fas fa-cogs"></i>&nbsp; Document request status</a></li>
        <li><a href="#"><i class="fas fa-cogs"></i>&nbsp; Settings</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i>&nbsp; Logout</a></li>
      </ul>
    </div>

  <!-- Main Content -->
  <div id="main-content" class="main-content">
    <!-- Header -->
    <header class="header">
      <button id="sidebar-toggle" class="sidebar-toggle">&#9776;</button>
      <h1>Dashboard</h1>
    </header><br><br><br><br><br>

    <!-- Main Body Content -->
    <form action="submit_request.php" method="POST">
  <label for="last_name">Last Name:</label><br>
  <input type="text" name="last_name" id="last_name" required><br><br>

  <label for="first_name">First Name:</label><br>
  <input type="text" name="first_name" id="first_name" required><br><br>

  <label for="middle_name">Middle Name:</label><br>
  <input type="text" name="middle_name" id="middle_name"><br><br>

  <label for="student_id">Student ID Number:</label><br>
  <input type="text" name="student_id" id="student_id" required><br><br>

  <label for="program">Program/Course:</label><br>
  <select name="program" id="program" required>
    <option value="BSIT">BSIT</option>
    <option value="BSTM">BSTM</option>
    <option value="BSBA">BSBA</option>
    <option value="BSHM">BSHM</option>
    <option value="BSCRIM">BSCRIM</option>
  </select><br><br>

  <label for="year_level">Year Level:</label><br>
  <select name="year_level" id="year_level" required>
    <option value="1st year">1st year</option>
    <option value="2nd year">2nd year</option>
    <option value="3rd year">3rd year</option>
    <option value="4th year">4th year</option>
  </select><br><br>

  <label for="document_type">Document Type:</label><br>
  <select name="document_type" id="document_type" required>
    <option value="Diploma">Diploma</option>
    <option value="Transcript of records">Transcript of records</option>
    <option value="Medical certificate">Medical certificate</option>
    <option value="Form-137">Form-137</option>
    <option value="Good moral">Good moral</option>
  </select><br><br>

  <button type="submit" class="request1">Submit Request</button>
</form>


  </div>

  <!-- Overlay Background (Initially Hidden) -->
  <div id="overlay" class="overlay" style="display: none;"></div>

  <script>
    // Get elements
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const overlay = document.getElementById('overlay');

    // Toggle sidebar visibility and overlay effect
    sidebarToggle.addEventListener('click', function(event) {
      event.stopPropagation(); // Prevent this click from closing the sidebar
      sidebar.classList.toggle('active');
      
      // Toggle the overlay effect (dark background)
      overlay.style.display = overlay.style.display === 'block' ? 'none' : 'block';
    });

    // Close the sidebar if clicked outside (removes overlay as well)
    document.addEventListener('click', function(event) {
      if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
        sidebar.classList.remove('active');
        overlay.style.display = 'none'; // Remove overlay when sidebar is closed
      }
    });

    // Prevent click on the sidebar from closing it (click inside the sidebar should do nothing)
    sidebar.addEventListener('click', function(event) {
      event.stopPropagation();
    });

    // Hide the loading screen after 2 seconds (simulating loading)
    window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('loading').style.display = 'none';
            }, 2000); // Change the duration (in ms) as needed
        });
  </script>
<script>
document.getElementById('login-form').addEventListener('submit', function (event) {
    event.preventDefault();
    const loginButton = document.getElementById('login-btn');
    loginButton.classList.add('loading');

    const formData = new FormData(this);

    fetch("login.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.text()) // Get raw text for debugging
    .then(text => {
        console.log("Raw Response:", text); // Debugging: Log response
        try {
            const data = JSON.parse(text);
            loginButton.classList.remove('loading');

            if (data.status === "success") {
                window.location.href = "newdash1.php"; // Redirect on success
            } else {
                alert("Login Failed: " + data.message);
            }
        } catch (error) {
            alert("Unexpected response. Check console for details.");
            console.error("Parsing Error:", error, "Response Text:", text);
        }
    })
    .catch(error => {
        console.error("Fetch Error:", error);
        alert("An error occurred. Please check the console.");
        loginButton.classList.remove('loading');
    });
});

</script>

</body>
</html>
