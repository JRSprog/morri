<?php
include 'request_db_connect.php';

// Handle account creation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_user'])) {
    $uname = $_POST['uname'];
    $pass = password_hash($_POST['pass'], PASSWORD_BCRYPT);
    $role = $_POST['role'];

    $insert_sql = "INSERT INTO user1 (uname, pass, role) VALUES ('$uname', '$pass', '$role')";
    if ($conn->query($insert_sql) === TRUE) {
        header("Location: admin addusers.php");
        exit();
    } else {
        echo "<div class='alert alert-danger'>Error adding user: " . $conn->error . "</div>";
    }
}

// Handle account deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $user_id = $_POST['user_id'];
    $delete_sql = "DELETE FROM user1 WHERE id = $user_id";
    if ($conn->query($delete_sql) === TRUE) {
        header("Location: admin addusers.php");
        exit();
    } else {
        echo "<div class='alert alert-danger'>Error deleting user: " . $conn->error . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - User Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./newdash1 css/styles6.css">
    <link rel="shortcut icon" href="../images/blogo.png" type="x-icon">
</head>
<body>
    <style>
            /* General Styles */
body, html {
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
    height: 100%;
    background-color: aliceblue;
}

h2, h1 {
    margin: 0;
}

ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

a {
    text-decoration: none;
    color: #333;
}

/* Sidebar */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 300px;
    height: 100%;
    background-color: #fff; /* Darker background for visibility */
    color: black; /* Change text color to white for contrast */
    transform: translateX(-100%); /* Hide initially */
    transition: transform 0.3s ease-in-out;
    padding-top: 20px;
    z-index: 1000; /* Ensure it sits on top */
    border-top-right-radius: 15px;
    border-bottom-right-radius: 10px;
    box-shadow: 1px 2px 4px 1px rgba(0, 0, 0, 0.2);
}

.sidebar .logo {
    text-align: center;
    margin-bottom: 40px;
}

.sidebar .logo img {
    width: 100px;
}

.sidebar .logo  {
    text-align: center;
    color: gray;
}

.logo {
    border-bottom: solid gray 1px;
}

.sidebar-toggle {
    background: none;
    color: black;
}

.sidebar.active {
    transform: translateX(0); /* Slide in */
}

.sidebar-nav li {
    padding: 15px;
}

.sidebar-nav li a {
    display: flex;
    align-items: center;
    color: black; /* Keep link color */
    padding: 10px 20px;
    margin-left: 10px;
    text-decoration: none !important; /* Force removal */
}

.sidebar-nav li a:hover {
    background-color: #34495e;
    color: white;
    border-radius: 10px;
    text-decoration: none !important; /* Ensure underline does not appear on hover */
}

.header {
    background-color: white;
    padding: 15px;
    display: flex;
    align-items: center;
    top: 0;
    position: sticky;
    box-shadow: 0 4px 8px 0 rgba(0, 0, 0, 0.2);
}

.header h1 {
    margin-top: 5px;
}

/* Submit Button */
button {    
    border: none;
    padding: 10px;
    cursor: pointer;
    background-color: #3498db; /* Ensure the submit button has a distinct color */
    color: white; /* Change text color to white for contrast */
    font-size: 25px;
    border-radius: 5px; /* Add some border radius */
}

button:focus {
    outline: none;
}
    </style>

    <!-- Sidebar -->
    <div id="sidebar" class="sidebar">
        <div class="logo">
            <img src="../images/blogo.png" alt="Logo">
            <p>Morri</p>
        </div>
        <ul class="sidebar-nav">
               <li><a href="admin main.php"><i class="fas fa-folder-open"></i>&nbsp; Document requests</a></li>
               <li><a href="studentrecord.php"><i class="fas fa-user"></i>&nbsp; Student Information</a></li>
               <li><a href="studentlevel.php"><i class="fas fa-user"></i>&nbsp; Student Account</a></li>
               <li><a href="adminuser.php"><i class="fas fa-user-plus"></i>&nbsp; Add users</a></li>
               <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i>&nbsp; Logout</a></li>
        </ul>
    </div>

    <div id="main-content" class="main-content">
        <header class="header">
            <button id="sidebar-toggle" class="sidebar-toggle">&#9776;</button>
            <h1>Users</h1>
        </header>
        
        <div class="container mt-5">
            <h2 class="mb-4">User Management</h2>
            
            <!-- Add User Form -->
            <form method="POST" action="" class="mb-4">
                <div class="mb-3">
                    <input type="text" name="uname" class="form-control" placeholder="Username" required>
                </div>
                <div class="mb-3">
                    <input type="password" name="pass" class="form-control" placeholder="Password" required>
                </div>
                <div class="mb-3">
                    <select name="role" class="form-select">
                        <option value="admin">Admin</option>
                        <option value="student">Student</option>
                    </select>
                </div>
                <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
            </form>

            <!-- Display Users -->
            <h3>Existing Users</h3>
            <table class="table table-bordered table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("SELECT * FROM user1");
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>{$row['id']}</td>
                                <td>{$row['uname']}</td>
                                <td>{$row['role']}</td>
                                <td>
                                    <form method='POST' action='' class='d-inline' onsubmit='return confirmDelete()'>
                                        <input type='hidden' name='user_id' value='{$row['id']}'>
                                        <button type='submit' name='delete_user' class='btn btn-danger btn-sm'>Delete</button>
                                    </form>
                                </td>
                              </tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete() {
    return confirm("Are you sure you want to delete this user?");
}
        // Sidebar toggle functionality
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebar-toggle');
        const overlay = document.createElement('div');
        overlay.id = "overlay";
        document.body.appendChild(overlay);

        sidebarToggle.addEventListener('click', function(event) {
            event.stopPropagation();
            sidebar.classList.toggle('active');
            overlay.style.display = overlay.style.display === 'block' ? 'none' : 'block';
        });

        document.addEventListener('click', function(event) {
            if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                sidebar.classList.remove('active');
                overlay.style.display = 'none';
            }
        });

        sidebar.addEventListener('click', function(event) {
            event.stopPropagation();
        });
    </script>
</body>
</html>
