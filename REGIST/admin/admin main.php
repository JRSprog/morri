<?php
include 'request_db_connect.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['update_status'])) {
        // Updating the status
        $request_id = $_POST['request_id'];
        $new_status = $_POST['status'];
        
        $update_sql = "UPDATE document_requests SET status = '$new_status' WHERE id = $request_id";
        if ($conn->query($update_sql) === TRUE) {
            header("Location: admin main.php"); // Refresh page after update
            exit();
        } else {
            echo "Error updating status: " . $conn->error;
        }
    } elseif (isset($_POST['delete_request'])) {
        // Deleting the request
        $request_id = $_POST['request_id'];
        
        $delete_sql = "DELETE FROM document_requests WHERE id = $request_id";
        if ($conn->query($delete_sql) === TRUE) {
            header("Location: admin main.php"); // Refresh page after deletion
            exit();
        } else {
            echo "Error deleting request: " . $conn->error;
        }
    }
}


// Search functionality
$search = "";
if (isset($_GET['search'])) {
    $search = $_GET['search'];
}

// Modify SQL query to filter results
$sql = "SELECT * FROM document_requests 
        WHERE last_name LIKE '%$search%' 
        OR first_name LIKE '%$search%' 
        OR middle_name LIKE '%$search%'
        OR student_id LIKE '%$search%'
        OR document_type LIKE '%$search%'
        OR program LIKE '%$search%'
        ORDER BY request_date DESC";

$result = $conn->query($sql);

// Handle status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $request_id = $_POST['request_id'];
    $new_status = $_POST['status'];
    
    $update_sql = "UPDATE document_requests SET status = '$new_status' WHERE id = $request_id";
    if ($conn->query($update_sql) === TRUE) {
        header("Location: admin main.php"); // Refresh page after update
        exit();
    } else {
        echo "Error updating status: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Page</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="./newdash1 css/styles6.css">
    <link rel="shortcut icon" href="../images/blogo.png" type="x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
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

* Main Content Area */
.main-content {
    padding: 20px;
    background-color: #ecf0f1;
    min-height: 100vh; /* Ensure content fills the screen */
    transition: background-color 0.3s ease; /* Smooth transition for background color */
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

/* Overlay Effect for Entire Dashboard Except Sidebar */
.overlay {
    background-color: rgba(0, 0, 0, 0.3); /* Darkened background for the whole dashboard except sidebar */
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 999; /* Layer the overlay above content but below the sidebar */
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    /* Adjust the layout when the sidebar is hidden on mobile */
    .sidebar {
        width: 250px;
    }

    .main-content {
        margin-left: 0; /* Remove the left margin for the sidebar when it's hidden */
        padding: 10px;
    }

    .header {
        display: flex;
        padding: 10px;
        box-shadow: none;
    }

    .header h1 {
        font-size: 30px;
    }
}
</style>
<body>



<div id="sidebar" class="sidebar">
    <div class="logo">
        <img src="images/blogo.png" alt="Logo">
        <p>ADMIN</p>
    </div>
    <ul class="sidebar-nav">
        <li><a href="admin main.php"><i class="fas fa-folder-open"></i>&nbsp; Document requests</a></li>
        <li><a href="studentrecord.php"><i class="fas fa-user"></i>&nbsp; Student Information</a></li>
        <li><a href="studentlevel.php"><i class="fas fa-user"></i>&nbsp; Student Account</a></li>
        <li><a href="adminuser.php"><i class="fas fa-user"></i>&nbsp; Add users</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i>&nbsp; Logout</a></li>
    </ul>
</div>

<div id="main-content" class="main-content">
    <header class="header">
        <button id="sidebar-toggle" class="sidebar-toggle">&#9776;</button>
        <h1>ADMIN</h1>
    </header>
    
    <div class="container mt-5">
        <h2 class="mb-4">REQUESTS</h2>

        <!-- Search Form -->
        <form method="GET" class="mb-3 d-flex">
            <input type="text" name="search" class="form-control me-2" placeholder="Search by name, student ID, or document type" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
        </form>

        <table class="table table-bordered table-striped">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Student Name</th>
                    <th>Student ID</th>
                    <th>Program</th>
                    <th>Year Level</th>
                    <th>Document Type</th>
                    <th>Request Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['last_name'] . ", " . $row['first_name'] . " " . $row['middle_name']; ?></td>
                    <td><?php echo $row['student_id']; ?></td>
                    <td><?php echo $row['program']; ?></td>
                    <td><?php echo $row['year_level']; ?></td>
                    <td><?php echo $row['document_type']; ?></td>
                    <td><?php echo $row['request_date']; ?></td>
                    <td>
                        <span class="badge bg-<?php echo ($row['status'] == 'Approved') ? 'success' : (($row['status'] == 'Not Eligible') ? 'danger' : 'warning'); ?>">
                            <?php echo $row['status'] ?? 'Pending'; ?>
                        </span>
                    </td>
                    <td>
    <form method="POST" class="d-flex">
        <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
        
        <!-- Status Update Dropdown -->
        <select name="status" class="form-select me-2">
            <option value="Pending" <?php if ($row['status'] == 'Pending') echo 'selected'; ?>>Pending</option>
            <option value="Approved" <?php if ($row['status'] == 'Approved') echo 'selected'; ?>>Approved</option>
            <option value="Not Eligible" <?php if ($row['status'] == 'Not Eligible') echo 'selected'; ?>>Not Eligible</option>
        </select>

        <!-- Update Button -->
        <button type="submit" name="update_status" class="btn btn-primary me-2">Update</button>

        <!-- Delete Button -->
        <button type="submit" name="delete_request" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this request?');">
            Delete
        </button>
    </form>
</td>

                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>
                </main></main>
<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const overlay = document.getElementById('overlay');

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

    window.addEventListener('load', function() {
        setTimeout(function() {
            document.getElementById('loading').style.display = 'none';
        }, 2000);
    });
</script>

</body>
</html>
