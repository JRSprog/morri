
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Page - Student Records</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="../images/blogo.png" type="x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* General Styles */
        body, html { margin: 0; padding: 0; font-family: Arial, sans-serif; height: 100%; background-color: #f4f7f9; }
        h2, h1 { margin: 0; }
        ul { list-style: none; padding: 0; margin: 0; }
        a { text-decoration: none; color: #333; }

        /* Sidebar */
        .sidebar { position: fixed; top: 0; left: 0; width: 280px; height: 100%; background-color: #2c3e50; color: #fff; transform: translateX(-100%); transition: transform 0.3s ease-in-out; padding-top: 20px; z-index: 1000; }
        .sidebar .logo { text-align: center; margin-bottom: 40px; }
        .sidebar .logo img { width: 80px; margin-bottom: 10px; }
        .sidebar .logo p { color: #ccc; }
        .sidebar.active { transform: translateX(0); }
        .sidebar-nav li { padding: 15px; }
        .sidebar-nav li a { display: flex; align-items: center; color: #fff; padding: 10px 20px; margin-left: 10px; border-radius: 8px; }
        .sidebar-nav li a:hover { background-color: #34495e; }

        /* Main Content Area */
        .main-content { padding: 20px; margin-left: 0; transition: margin-left 0.3s ease; }
        .header { background-color: #fff; padding: 15px; display: flex; align-items: center; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); margin-bottom: 20px;}
        .header h1 { margin-top: 5px; font-size: 24px; color: #333; }
        .overlay { display: none; background-color: rgba(0, 0, 0, 0.3); position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 999; }

        /* Table Styles */
        .record-container { overflow-x: auto; }
        .table-responsive { overflow-x: auto; }

        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            .sidebar { width: 220px; }
            .main-content { padding: 10px; }
            .header h1 { font-size: 20px; }
        }


        body {
            font-family: sans-serif;
            margin: 20px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 20px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f2f2f2;
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tr:hover {
            background-color: #f0f0f0;
        }

        .record-container{
            overflow-x: auto;
        }
        
        .sidebar-toggle {
            background: none;
            border: none;
        }
        .sidebar {
            background-color: white;
        }
        .sidebar-nav li a{
            color: black;
        }
        .sidebar-nav li a:hover {
            color: white;
        }
    </style>
</head>
<body>

<div id="sidebar" class="sidebar">
    <div class="logo">
        <img src="images/blogo.png" alt="Logo">
        <p style="color:black;">ADMIN PANEL</p>
    </div>
    <ul class="sidebar-nav">
        <li><a href="admin main.php"><i class="fas fa-folder-open"></i>&nbsp; Document requests</a></li>
        <li><a href="admin_strecord.php"><i class="fas fa-user"></i>&nbsp; Student Information</a></li>
        <li><a href="studentlevel.php"><i class="fas fa-user"></i>&nbsp; Student Account</a></li>
        <li><a href="adminuser.php"><i class="fas fa-user-plus"></i>&nbsp; Add users</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i>&nbsp; Logout</a></li>
    </ul>
</div>

<div class="overlay" id="overlay"></div>

<div class="main-content">
    <div class="header">
        <button id="sidebar-toggle" class="sidebar-toggle">&#9776;</button> &nbsp;
        <h1>Student Records</h1>
    </div>

    <div class="record-container">
        <div class="table-responsive">
            <?php
            $servername = "localhost"; $username = "root"; $password = ""; $dbname = "bb_admission";
            $conn = new mysqli($servername, $username, $password, $dbname);
            if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }
            $sql = "SELECT * FROM novaliches"; $result = $conn->query($sql);
            if ($result->num_rows > 0) {
                echo "<table class='table table-striped table-bordered table-hover'>
                        <thead class='table-light'>
                            <tr>
                                <th>Student ID</th> 
                                <th>Name</th> 
                                <th>Email</th> 
                                <th>Phone</th> 
                                <th>Status</th> 
                                <th>Date of Birth</th> 
                                <th>Address</th> 
                                <th>Campus</th> 
                                <th>Photo Path</th>
                                <th>Document Paths</th>
                                 <th>Previous Education</th>
                                  <th>Guardian Name</th>
                                   <th>Guardian Phone</th>
                                    <th>Submission Date</th>
                            </tr>
                        </thead>
                        <tbody>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>
                            <td>" . $row["studentId"] . "</td> 
                            <td>" . $row["name"] . "</td>
                             <td>" . $row["email"] . "</td> 
                             <td>" . $row["phone"] . "</td>
                              <td>" . $row["status"] . "</td> 
                              <td>" . $row["dob"] . "</td>
                               <td>" . $row["address"] . "</td>
                            <td>" . $row["campus"] . "</td> 
                            <td>" . $row["photo_path"] . "</td> 
                            <td>" . $row["document_paths"] . "</td> 
                            <td>" . $row["previous_education"] . "</td> 
                            <td>" . $row["guardian_name"] . "</td> 
                            <td>" . $row["guardian_phone"] . "</td>
                             <td>" . $row["submission_date"] . "</td>
                          </tr>";
                }
                echo "</tbody></table>";
            } else { echo "<p>0 results</p>"; }
            $conn->close();
            ?>
        </div>
    </div>
</div>

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


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>