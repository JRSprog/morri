<?php
// Start session
session_start();

// Include database connection
include '../connect.php'; // Ensure this file initializes $con securely


// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header("Location: ../login.php");
    exit();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if(isset($_POST['paid']) && isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $ids = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $stid = isset($_POST['stid']) ? mysqli_real_escape_string($con, $_POST['stid']) : '';
    $paid = "paid";
    
    // Verify the student exists first
    $checkStudent = $con->prepare("SELECT stid FROM students WHERE stid = ?");
    $checkStudent->bind_param("s", $stid);
    $checkStudent->execute();
    $checkStudent->store_result();
    
    if($checkStudent->num_rows > 0) {
        // Update only the specific fee for this student
        $feest = $con->prepare("UPDATE stfees SET status = ? WHERE id = ? AND stid = ?");
        $feest->bind_param("sis", $paid, $ids, $stid);
        
        if($feest->execute()) {
            // Update student balance if needed (optional)
            $amount = isset($_POST['amount']) ? (float)$_POST['amount'] : 0;
            if($amount > 0) {
                $updateBalance = $con->prepare("UPDATE students SET balance = balance - ? WHERE stid = ?");
                $updateBalance->bind_param("ds", $amount, $stid);
                $updateBalance->execute();
                $updateBalance->close();
            }
            
            $_SESSION['success_message'] = "Fee successfully marked as paid!";
        } else {
            $_SESSION['error_message'] = "Error updating fee status: " . $con->error;
        }
        $feest->close();
    } else {
        $_SESSION['error_message'] = "Student not found!";
    }
    $checkStudent->close();
    
    // Redirect to prevent form resubmission
    header("Location: ".$_SERVER['PHP_SELF']);
    exit();
}

if(isset($_POST['paids']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
  $ids1 = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $paid1 = "paid";

  // Use prepared statement to prevent SQL injection
  $feest1 = $con->prepare("UPDATE fees SET status = ? WHERE id = ?");
  $feest1->bind_param("si", $paid1, $ids1);
  $feest1->execute();
  $feest1->close();
  
  // Redirect to prevent form resubmission
  header("Location: ".$_SERVER['PHP_SELF']);
  exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Student Information</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link rel="shortcut icon" href="../uploads/blogo.png" type="image/x-icon">
  <link rel="stylesheet" href="../css/style.css?v=1.2">
  <link rel="stylesheet" href="../css/styles.css?=v1.2">
</head>
<body>

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
    <div class="strecord">
    <h1>Student Information</h1>
      <div class="search-container1">
        <i class="fa-solid fa-magnifying-glass"></i><br><br>
        <input type="search" id="searchInput" placeholder="Search ID/student here...">
      </div><br><br>  
      
      <table id="dataTable">
        <thead>
        <tr>
            <th>Student ID</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Middle Name</th>
            <th>Birthday</th>
            <th>Age</th>
            <th>Email</th>
            <th>Address</th>
            <th>Program</th>
            <th>Year level</th>
          </tr>
        </thead>
        <tbody>
        <?php
        // Fetch students data using prepared statements
        $sql = "SELECT * FROM students";
        $stmt = mysqli_prepare($con, $sql);
        if ($stmt) {
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if (!$result) {
                die("Database query failed: " . mysqli_error($con));
            }

            $index = 0;
            while ($row = mysqli_fetch_assoc($result)) {
                $stid = htmlspecialchars($row['stid']);
                echo '<tr id="student-row-'.$index.'" onclick="togglePayment('.$index.')">';
                echo '<td>s'. $stid .'</td>';
                echo '<td>'. htmlspecialchars($row['fname']) .'</td>';
                echo '<td>'. htmlspecialchars($row['lname']) .'</td>';
                echo '<td>'. htmlspecialchars($row['mname']) .'</td>';
                echo '<td>'. htmlspecialchars(date('F j, Y', strtotime($row['bday']))) .'</td>';
                echo '<td>'. htmlspecialchars($row['age']) .'</td>';
                echo '<td>'. htmlspecialchars($row['email']) .'</td>';
                echo '<td>'. htmlspecialchars($row['address']) .'</td>';
                echo '<td>'. htmlspecialchars($row['program']) .'</td>';
                echo '<td>'. htmlspecialchars($row['level']) .'</td>';
                echo '</tr>';

                // Fetch payments for each student using prepared statements
                $payment_sql = "SELECT balance FROM students WHERE stid = ?";
                $stmt2 = mysqli_prepare($con, $payment_sql);
                if ($stmt2) {
                    mysqli_stmt_bind_param($stmt2, "i", $stid);
                    mysqli_stmt_execute($stmt2);
                    $payment_result = mysqli_stmt_get_result($stmt2);
                    $payments = mysqli_fetch_all($payment_result, MYSQLI_ASSOC);
                    mysqli_stmt_close($stmt2);
                }

                // Fetch stfees data using prepared statements
                $stfees_sql = "SELECT id, payname, amount, status FROM stfees WHERE program = ? AND stid = ?";
                $stmt3 = mysqli_prepare($con, $stfees_sql);
                if ($stmt3) {
                    mysqli_stmt_bind_param($stmt3, "ss", $row['program'], $stid); // Assuming $stid is defined elsewhere
                    mysqli_stmt_execute($stmt3);
                    $stfees_result = mysqli_stmt_get_result($stmt3);
                    $stfees = mysqli_fetch_all($stfees_result, MYSQLI_ASSOC);
                    mysqli_stmt_close($stmt3);
                }

                // Fetch fees data for the specific program
                $fees_sql = "SELECT id, paname, amount, status , Miscellaneous FROM fees WHERE selct = ? AND student_id = ?";
                $stmt4 = mysqli_prepare($con, $fees_sql);
                if ($stmt4) {
                    // Get the student ID - make sure this matches your database column name
                    $student_id = $row['stid']; // or whatever your student ID column is called
                    
                    // Bind both parameters
                    mysqli_stmt_bind_param($stmt4, "ss", $row['program'], $student_id);
                    mysqli_stmt_execute($stmt4);
                    $fees_result = mysqli_stmt_get_result($stmt4);
                    $fees = mysqli_fetch_all($fees_result, MYSQLI_ASSOC);
                    mysqli_stmt_close($stmt4);
                }

                $total_amount = 0;
                $unpaid_total = 0; // This will store only unpaid amounts

                echo '<tr id="payment-'.$index.'" class="payment-details" style="display:none;">
                        <td colspan="10">
                          <form action="" method="POST">
                            <input type="hidden" name="stid" value="'.$stid.'">
                            <input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';

                if ($payments || $stfees || $fees) {
                    echo '<table class="list">
                            <thead>
                              <tr>
                                <th>Payment Name</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Action</th>
                              </tr>
                            </thead>
                            <tbody>';

                    // Display payments from payments table (only if amount > 0)
                    foreach ($payments as $payment) {
                        $amount = $payment['balance'];
                        if ($amount > 0) {
                            $total_amount += $amount;
                            $unpaid_total += $amount; // All balance is considered unpaid
                            echo '<tr>';
                            echo '<td>Miscellaneous</td>';
                            echo '<td>'. number_format($amount, 0) .'</td>';
                            echo '<td>unpaid</td>';
                            echo '<td></td>';
                            echo '</tr>';
                        }
                    }

                   // Display stfees data
                        foreach ($stfees as $stfee) {
                          $amount1 = $stfee['amount'];
                          $status = strtolower($stfee['status']); // Convert to lowercase for consistent comparison
                          echo '<tr>';
                          echo '<td>'. htmlspecialchars($stfee['payname']) .'</td>';
                          echo '<td>'. number_format($amount1) .'</td>';
                          echo '<td>'. htmlspecialchars($status) .'</td>';
                          echo '<td>';
                          
                          // Only show "Mark as paid" button if status is unpaid or empty
                          if ($status === 'unpaid' || $status === '') {
                              $unpaid_total += $amount1;
                              echo '<form method="post">';
                              echo '<input type="hidden" name="id" value="'.htmlspecialchars($stfee['id']).'">';
                              echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
                              echo '<button type="submit" name="paid" class="mark-paid-btn unpaid-status">Mark as paid</button>';
                              echo '</form>';
                          } else {
                              echo 'Paid';
                          }
                          echo '</td>';
                          echo '</tr>';
                        }

                        foreach ($fees as $fee) {

                          $amount2 = $fee['amount'];
                          $status = strtolower($fee['status'] ?? ''); // Convert to lowercase and handle null status
                          $isMiscellaneous = ($fee['Miscellaneous'] === 'Miscellaneous');
                          $status = $isMiscellaneous ? 'paid' : strtolower($fee['status'] ?? '');

                          echo '<tr>';
                          echo '<td>'. htmlspecialchars($fee['paname']) .'</td>';
                          echo '<td>'. number_format($amount2) .'</td>';
                          echo '<td>'. htmlspecialchars($status) .'</td>';
                          echo '<td>';
                          
                          // Only show "Mark as paid" button if status is unpaid or empty
                          if ($status === 'unpaid' || $status === '') {
                              // Only add to unpaid total if NOT Miscellaneous
                                  if (!$isMiscellaneous) {
                                      $unpaid_total += $amount2;
                                  }
                              echo '<form method="post">';
                              // Make sure your fees table has an 'id' column
                              echo '<input type="hidden" name="id" value="'.htmlspecialchars($fee['id']).'">';
                              echo '<input type="hidden" name="csrf_token" value="'.$_SESSION['csrf_token'].'">';
                              echo '<button type="submit" name="paids" class="mark-paid-btn unpaid-status">Mark as paid</button>';
                              echo '</form>';
                          } else {
                              echo 'Paid';
                          }
                          echo '</td>';
                          echo '</tr>';
                      }

                    // Display unpaid total amount
                    echo '<tr style="font-weight: bold; background-color: #f2f2f2;">
                              <td>Total Amount</td>
                              <td>'. number_format($unpaid_total, 0) .'</td>
                              <td></td>
                              <td></td>
                          </tr>';

                    echo '</tbody>
                          </table>';
                }

                echo '</form>
                      </td>
                    </tr>';
                $index++;
            }
            mysqli_stmt_close($stmt);
        }
        ?>
        </tbody>
      </table>
      <div id="noRecordMessage" style="display: none;">No record found</div>
    </div>
  </div>
 <script>
    // Improved search functionality
    document.getElementById('searchInput').addEventListener('input', function() {
      const input = this.value.toLowerCase().trim();
      const rows = document.querySelectorAll('#dataTable tbody tr[id^="student-row"]');
      const noRecordMessage = document.getElementById('noRecordMessage');
      let found = false;

      // First hide all payment rows
      document.querySelectorAll('.payment-details').forEach(row => {
        row.style.display = 'none';
      });

      // Search through student rows only
      rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        if (input === '' || text.includes(input)) {
          row.style.display = '';
          found = true;
        } else {
          row.style.display = 'none';
        }
      });

      noRecordMessage.style.display = found ? 'none' : 'block';
    });

    function togglePayment(index) {
      const paymentRow = document.getElementById("payment-" + index);
      paymentRow.style.display = paymentRow.style.display === 'none' ? 'table-row' : 'none';
      
      // Close all other payment rows when opening a new one
      if (paymentRow.style.display === 'table-row') {
        document.querySelectorAll('.payment-details').forEach(row => {
          if (row.id !== `payment-${index}`) {
            row.style.display = 'none';
          }
        });
      }
    }
  </script>
  <script src="../js/script.js"></script>
</body>
</html>