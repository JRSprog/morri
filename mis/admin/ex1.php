<?php
// Start output buffering
ob_start();

// ==================== SECURE SESSION CONFIGURATION ====================
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => false, // Set to true in production with HTTPS
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
    'sid_length' => 128,
    'sid_bits_per_character' => 6
]);

// Generate CSRF token immediately after session start
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

// ==================== DDoS PROTECTION ====================
$max_requests_per_minute = 60;
$ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$request_time = time();

// Initialize rate limiting in session
if (!isset($_SESSION['requests'])) {
    $_SESSION['requests'] = [
        'count' => 0,
        'first_request' => $request_time,
        'ip' => $ip_address
    ];
}

// Reset if IP changes (session fixation protection)
if (($_SESSION['requests']['ip'] ?? null) !== $ip_address) {
    session_regenerate_id(true);
    $_SESSION['requests'] = [
        'count' => 0,
        'first_request' => $request_time,
        'ip' => $ip_address
    ];
}

// Check rate limit
$_SESSION['requests']['count']++;
$elapsed = $request_time - ($_SESSION['requests']['first_request'] ?? $request_time);
if ($_SESSION['requests']['count'] > $max_requests_per_minute && $elapsed < 60) {
    header('HTTP/1.1 429 Too Many Requests');
    header('Retry-After: 60');
    die(json_encode(['status' => 'error', 'message' => 'Too many requests']));
}

// ==================== DATABASE CONNECTION ====================
require_once '../connect.php';
if (!$con) {
    header('HTTP/1.1 503 Service Unavailable');
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed']));
}

// ==================== SECURITY FUNCTIONS ====================
function validateCsrfToken() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        error_log('CSRF token validation failed for IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return false;
    }
    
    // Optional: Token expiration (1 hour)
    if (time() - ($_SESSION['csrf_token_time'] ?? 0) > 3600) {
        error_log('CSRF token expired');
        return false;
    }
    
    return true;
}

function sendJsonResponse($data, $statusCode = 200) {
    ob_end_clean();
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

// ==================== AUTHENTICATION CHECK ====================
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
}

// ==================== REQUEST PROCESSING ====================
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token first
        if (!validateCsrfToken()) {
            sendJsonResponse(['status' => 'error', 'message' => 'Invalid security token'], 403);
        }

        // Process form submissions
        if (isset($_POST['submit'])) {
            // Balance update processing
            $id = filter_input(INPUT_POST, 'stid', FILTER_VALIDATE_INT);
            $nbalance = filter_input(INPUT_POST, 'newBalance', FILTER_VALIDATE_FLOAT);
            
            if ($id === false || $nbalance === false || $id <= 0 || $nbalance < 0) {
                sendJsonResponse(['status' => 'error', 'message' => 'Invalid input'], 400);
            }

            // Update student balance
            $stmt = $con->prepare("UPDATE students SET balance = ? WHERE stid = ?");
            $stmt->bind_param("di", $nbalance, $id);
            
            if (!$stmt->execute()) {
                throw new Exception('Database update failed');
            }

            // Insert history
            $stmt = $con->prepare("INSERT INTO history (sel, cbalance, date, studentId) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sdsi",
                filter_input(INPUT_POST, 'sel', FILTER_SANITIZE_STRING),
                filter_input(INPUT_POST, 'cBalance', FILTER_VALIDATE_FLOAT),
                filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING),
                $id
            );
            
            if (!$stmt->execute()) {
                throw new Exception('History insert failed');
            }
            
            sendJsonResponse(['status' => 'success', 'message' => 'Balance updated']);
            
        } elseif (isset($_POST['action'])) {
            // Approval/Rejection processing
            $required = ['rname', 'rstid', 'rparticular', 'ramount', 'rdate', 'stid'];
            foreach ($required as $field) {
                if (empty($_POST[$field] ?? null)) {
                    sendJsonResponse(['status' => 'error', 'message' => "Missing $field"], 400);
                }
            }
            
            $status = ($_POST['action'] === 'approve') ? 'approved' : 'rejected';
            $stid = filter_input(INPUT_POST, 'stid', FILTER_VALIDATE_INT);
            
            if ($stid === false || $stid <= 0) {
                sendJsonResponse(['status' => 'error', 'message' => 'Invalid student ID'], 400);
            }

            // INSERT TO RECORD TABLE ONLY IF APPROVED
            if ($_POST['action'] === 'approve') {
                $app = "approved";
                $stmt1 = $con->prepare("INSERT INTO record (name, stid, particular, amount, date, type, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt1->bind_param("sssdsss",
                    filter_input(INPUT_POST, 'rname', FILTER_SANITIZE_STRING),
                    filter_input(INPUT_POST, 'rstid', FILTER_SANITIZE_STRING),
                    filter_input(INPUT_POST, 'rparticular', FILTER_SANITIZE_STRING),
                    filter_input(INPUT_POST, 'ramount', FILTER_VALIDATE_FLOAT),
                    filter_input(INPUT_POST, 'rdate', FILTER_SANITIZE_STRING),
                    "Hma/Aub",
                    "approved"
                );
                
                if (!$stmt1->execute()) {
                    throw new Exception('Record insert failed: ' . $stmt1->error);
                }
                $stmt1->close();
            }

            // UPDATE APPROVAL STATUS
            $updateStatusQuery = "UPDATE approval SET status = ? WHERE stid = ?";
            $stmt = mysqli_prepare($con, $updateStatusQuery);
            mysqli_stmt_bind_param($stmt, 'si', $status, $stid);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Status update failed: ' . mysqli_error($con));
            }
            
            // Regenerate CSRF token after critical action
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
            
            // Return success response with the approved ID
            sendJsonResponse([
                'status' => 'success',
                'message' => 'Request has been ' . $status,
                'approved_id' => $stid,  // This tells the frontend which item to remove
                'action' => $_POST['action'],  // Indicates whether this was an approval or rejection
                'redirect' => 'approval.php'
            ]);
        }
    }
    
    // If GET request, render the page
    ob_end_clean();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link rel="shortcut icon" href="../uploads/blogo.png" type="x-icon">
    <link rel="stylesheet" href="../css/style.css?v=1.2">
    <link rel="stylesheet" href="../css/styles.css?v=1.2">
    <!-- SweetAlert Library -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .child {
            transition: all 0.3s ease;
            margin-bottom: 15px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
        }
        .approval-form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        button.app {
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button.reject {
            background-color: #f44336;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .fade-out {
            opacity: 0;
            height: 0;
            padding: 0;
            margin: 0;
            border: none;
            overflow: hidden;
            transition: all 0.5s ease;
        }
    </style>
</head>
<body>

<!-- Header -->
<header>
    <div class="menu-container">
        <button class="burger-button" onclick="toggleSidebar()">☰</button>
    </div>
    <div class="dropdown">
        <button class="dropdown-button"><i class="fa-solid fa-user"></i></button>
        <div class="dropdown-content">
            <a href="../logout.php?logout=true"><i class="fa-solid fa-right-from-bracket"></i>&nbsp; Logout</a>
        </div>
    </div>
</header>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="close">
        <span class="close-sidebar" onclick="toggleSidebar()"><i class="fa-solid fa-arrow-left"></i></span>
        <img src="../uploads/blogo.png" alt="Image" class="sidebar-image">
        <p class="sidebar-text"></p>
    </div>

    <div class="sidebar-content">
        <a href="dashboard.php" class="sidebar-item" style="--i: 2"><i class="fa-solid fa-house"></i>&nbsp; Dashboard</a>
        <a href="user.php" class="sidebar-item" style="--i: 2"><i class="fa-solid fa-user"></i>&nbsp; User</a>
        <a href="approval.php" class="sidebar-item" style="--i: 3"><i class="fa-solid fa-credit-card"></i>&nbsp; Online Approval</a>
        <a href="strecord.php" class="sidebar-item" style="--i: 4"><i class="fa-solid fa-clipboard-list"></i>&nbsp; Student Information</a>
        <a href="payrecord.php" class="sidebar-item" style="--i: 5"><i class="fa-solid fa-clipboard-list"></i>&nbsp; Payment Record</a>
        <a href="onfees.php" class="sidebar-item" style="--i: 6"><i class="fa-solid fa-clipboard-list"></i>&nbsp; Ongoing Fees</a>
    </div>
</div>

<div class="main-content">
    <div class="parent">
        <h1 style="text-align: center;">Approval Payment Online</h1>
        <?php
        // Get pending approvals from database
        $query = "SELECT * FROM approval WHERE status = 'pending'";
        $result = mysqli_query($con, $query);
        
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                echo '<div class="child" id="approval-'.htmlspecialchars($row['stid']).'">';
                echo '<form method="post" class="approval-form" onsubmit="return handleApproval(this, event)">';
                echo '<input type="hidden" name="csrf_token" value="'.htmlspecialchars($_SESSION['csrf_token']).'">';
                
                // Display approval information
                echo '<p>Name: <strong>'.htmlspecialchars($row['name']).'</strong></p>';
                echo '<p>Student ID: <strong>'.htmlspecialchars($row['stid']).'</strong></p>';
                echo '<p>Particular: <strong>'.htmlspecialchars($row['particular']).'</strong></p>';
                echo '<p>Proof of Screenshot:</p>';
                echo '<img src="../uploads/h1.jpg" class="zoom-image" style="max-width: 200px; cursor: pointer">';
                echo '<p>Amount: <strong>'.htmlspecialchars($row['amount']).'</strong></p>';
                echo '<p>Date: <strong>'.htmlspecialchars(date('F j, Y', strtotime($row['date']))).'</strong></p>';
                
                // Hidden fields for form processing
                echo '<input type="hidden" name="rname" value="'.htmlspecialchars($row['name']).'">';
                echo '<input type="hidden" name="rstid" value="'.htmlspecialchars($row['stid']).'">';
                echo '<input type="hidden" name="rparticular" value="'.htmlspecialchars($row['particular']).'">';
                echo '<input type="hidden" name="ramount" value="'.htmlspecialchars($row['amount']).'">';
                echo '<input type="hidden" name="rdate" value="'.htmlspecialchars($row['date']).'">';
                echo '<input type="hidden" name="stid" value="'.htmlspecialchars($row['stid']).'">';
                
                // Action buttons
                echo '<div class="action-buttons">';
                echo '<button type="submit" name="action" value="approve" class="app">Approve</button>';
                echo '<button type="submit" name="action" value="reject" class="reject">Reject</button>';
                echo '</div>';
                
                echo '</form>';
                echo '</div>';
            }
        } else {
            echo '<p style="text-align: center; padding: 20px;">No pending approvals found</p>';
        }
        ?>
    </div>

    <div class="form-container">
      <h1>Student Miscellaneous Balance</h1>
      <div class="search-container1">
        <i class="fa-solid fa-magnifying-glass"></i><br><br>
        <input type="search" id="searchInput" placeholder="Search here...">
      </div>
      <table id="dataTable">
        <thead>
          <tr>
            <th>Student ID</th>
            <th>Lastname</th>
            <th>Firstname</th>
            <th>Middlename</th>
            <th>Balance</th>
            <th>Action</th>
          </tr>
        </thead>
        <?php
        // Kunin ang lahat ng estudyante mula sa database
        $select = "SELECT * FROM students";
        $sql = mysqli_query($con, $select);
        while ($row = mysqli_fetch_assoc($sql)) {
          echo '<tbody>';
          echo '<tr>';
          echo '<td>'.'s' . htmlspecialchars($row['stid']) . '</td>';
          echo '<td>' . htmlspecialchars($row['lname']) . '</td>';
          echo '<td>' . htmlspecialchars($row['fname']) . '</td>';
          echo '<td>' . htmlspecialchars($row['mname']) . '</td>';
          echo '<td>' . htmlspecialchars(number_format($row['balance'])) . '</td>';
          echo '<td><button class="update" id="modal" data-id="' . $row['stid'] . '" data-stid="' . htmlspecialchars($row['stid']) . '" data-balance="' . htmlspecialchars($row['balance']) . '">Update Balance</button></td>';
          echo '</tr>';
          echo '</tbody>';
        }
        ?>
      </table>
    </div>

    <!-- Modal1 -->
    <div class="modal" id="updateModal">
      <div class="modal-content">
        <span class="close1" id="closeModal">&times;</span>
        <h2>Update Student Balance</h2><br>
        <form id="updateForm" method="post">
          <input type="hidden" id="studentId" name="id" required>
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
          <label>Student ID:</label>
          <input type="text" id="stid" name="stid" required readonly><br><br>

          <label>Particular :</label><br><br>
          <select name="sel" style="padding: 10px; width:80%;">
              <option value="">select here....</option>
              <option value="Prelim exam">Miscellaneous</option>
              <option value="Prelim exam">Prelim exam</option>
              <option value="Midterm exam">Midterm exam</option>
              <option value="Final exam">Final exam</option>
              <option value="Stage play[Philippines Stagers]">Stage play[Philippines Stagers]</option>
          </select><br><br>

          <label>Current Balance:</label>
          <input type="text" id="cBalance" name="cBalance" required readonly><br><br>

          <label>New Balance:</label>
          <input type="number" id="newBalance" name="newBalance" required><br><br>
            
          <input type="datetime-local" id="datetime" name="date"><br><br>

          <button type="submit" name="submit">Submit</button>
        </form>
      </div>
    </div>

    <!-- Image overlay for zoom -->
    <div class="overlay2" id="overlay2">
      <span class="close2" id="close2">&times;</span>
      <img class="overlay-image" id="overlay-image" />
    </div>
  </div>

<script>
function handleApproval(form, event) {
    event.preventDefault();
    const clickedButton = event.submitter;
    const action = clickedButton.value;
    const formContainer = form.closest('.child');
    
    if (confirm(`Are you sure you want to ${action} this payment?`)) {
        clickedButton.disabled = true;
        clickedButton.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${action.charAt(0).toUpperCase() + action.slice(1)}ing...`;
        
        fetch('', {
            method: 'POST',
            body: new FormData(form)
        })
        .then(response => {
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data.status === 'success') {
                if (data.action === 'approve') {
                    // Fade out and remove the approved item
                    formContainer.classList.add('fade-out');
                    setTimeout(() => {
                        formContainer.remove();
                        
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        // Reload if no items left
                        if (document.querySelectorAll('.child').length === 0) {
                            setTimeout(() => window.location.reload(), 1000);
                        }
                    }, 500);
                } else {
                    // For rejections
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: data.message,
                    }).then(() => {
                        window.location.href = data.redirect;
                    });
                }
            } else {
                throw new Error(data.message || 'Action failed');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'An error occurred',
            });
            clickedButton.disabled = false;
            clickedButton.innerHTML = action.charAt(0).toUpperCase() + action.slice(1);
        });
    }
}

// Image zoom functionality
document.querySelectorAll('.zoom-image').forEach(img => {
    img.addEventListener('click', function() {
        const overlay = document.createElement('div');
        overlay.style.position = 'fixed';
        overlay.style.top = '0';
        overlay.style.left = '0';
        overlay.style.width = '100%';
        overlay.style.height = '100%';
        overlay.style.backgroundColor = 'rgba(0,0,0,0.8)';
        overlay.style.display = 'flex';
        overlay.style.justifyContent = 'center';
        overlay.style.alignItems = 'center';
        overlay.style.zIndex = '1000';
        
        const zoomedImg = document.createElement('img');
        zoomedImg.src = this.src;
        zoomedImg.style.maxWidth = '90%';
        zoomedImg.style.maxHeight = '90%';
        
        overlay.appendChild(zoomedImg);
        overlay.addEventListener('click', () => document.body.removeChild(overlay));
        document.body.appendChild(overlay);
    });
});

// Set current date and time
window.onload = function() {
    const today = new Date(); 
    const dd = String(today.getDate()).padStart(2, '0'); 
    const mm = String(today.getMonth() + 1).padStart(2, '0'); 
    const yyyy = today.getFullYear(); 
    const hours = String(today.getHours()).padStart(2, '0');
    const minutes = String(today.getMinutes()).padStart(2, '0'); 
    const formattedDateTime = `${yyyy}-${mm}-${dd}T${hours}:${minutes}`;
    document.getElementById('datetime').value = formattedDateTime;
}
</script>

</body>
</html>
<?php
} catch (Exception $e) {
    error_log("Application Error: " . $e->getMessage());
    sendJsonResponse(['status' => 'error', 'message' => 'An error occurred'], 500);
}
?>