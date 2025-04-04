<?php
// Start session with secure settings
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true, // Enable in production with HTTPS
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

// Include database connection
include '../connect.php';

// DDoS Protection Measures
$max_requests_per_minute = 60; // Adjust based on your needs
$ip_address = $_SERVER['REMOTE_ADDR'];
$request_time = time();

// Initialize rate limiting in session
if (!isset($_SESSION['requests'])) {
    $_SESSION['requests'] = [];
}

// Clean up old requests (older than 1 minute)
$_SESSION['requests'] = array_filter($_SESSION['requests'], function($time) use ($request_time) {
    return ($request_time - $time) < 60;
});

// Check if rate limit exceeded
if (count($_SESSION['requests']) >= $max_requests_per_minute) {
    header('HTTP/1.1 429 Too Many Requests');
    header('Retry-After: 60');
    die('Too many requests. Please try again later.');
}

// Record this request
$_SESSION['requests'][] = $request_time;

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Implement CSRF token for export requests
if (isset($_GET['export'])) {
    // Validate CSRF token for export requests
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        header('HTTP/1.1 403 Forbidden');
        die('Invalid CSRF token');
    }

    // Limit export frequency (once per 30 seconds)
    if (isset($_SESSION['last_export']) && (time() - $_SESSION['last_export']) < 30) {
        header('HTTP/1.1 429 Too Many Requests');
        die('Export requests are limited to once every 30 seconds.');
    }
    $_SESSION['last_export'] = time();

    $export_type = $_GET['export'];
    $filename = "payment_records_" . date('Y-m-d');
    
    // Implement query limits for exports
    $limit = 5000; // Maximum records to export
    $select = "SELECT * FROM record ORDER BY date ASC LIMIT $limit";
    $result = mysqli_query($con, $select);
    
    if (!$result) {
        die('Database error: ' . mysqli_error($con));
    }

    if ($export_type === 'excel') {
        header("Content-Type: application/vnd.ms-excel");
        header("Content-Disposition: attachment; filename=$filename.xls");
        
        $data = "<table border='1'>";
        $data .= "<tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Particular</th>
                    <th>Amount</th>
                    <th>Payment Date</th>
                    <th>Payment Type</th>
                  </tr>";
        
        while ($row = mysqli_fetch_assoc($result)) {
            $data .= "<tr>
                        <td>s".htmlspecialchars($row['stid'])."</td>
                        <td>".htmlspecialchars($row['name'])."</td>
                        <td>".htmlspecialchars($row['particular'])."</td>
                        <td>".htmlspecialchars($row['amount'])."</td>
                        <td>".htmlspecialchars(date('F j, Y', strtotime($row['date'])))."</td>
                        <td>".htmlspecialchars($row['type'])."</td>
                      </tr>";
        }
        
        $data .= "</table>";
        echo $data;
        exit();
        
    } elseif ($export_type === 'csv') {
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment; filename=$filename.csv");
        
        $output = fopen("php://output", "w");
        fputcsv($output, array('Student ID', 'Name', 'Particular', 'Amount', 'Payment Date', 'Payment Type'));
        
        while ($row = mysqli_fetch_assoc($result)) {
            fputcsv($output, array(
                's'.htmlspecialchars($row['stid']),
                htmlspecialchars($row['name']),
                htmlspecialchars($row['particular']),
                htmlspecialchars($row['amount']),
                htmlspecialchars(date('F j, Y', strtotime($row['date']))),
                htmlspecialchars($row['type'])
            ));
        }
        
        fclose($output);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Payment Record</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="shortcut icon" href="../uploads/blogo.png" type="x-icon">
  <link rel="stylesheet" href="../css/style.css?v=1.2">
  <link rel="stylesheet" href="../css/styles.css?v=1.2">
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
      <h1>Payment Record</h1>
      <div class="search-container1">
        <i class="fa-solid fa-magnifying-glass"></i><br><br>
        <input type="search" id="searchInput" placeholder="Search here...">
        <button class="voice" id="recognition"><i class="fa-solid fa-microphone"></i></button>
      </div><br><br>  

      <div class="dropdown1">
        <button onclick="toggleDropdown1()" class="dl"><i class="fa-solid fa-download"></i>&nbsp; Download</button>
        <div id="downloadDropdown1" class="dropdown-content1">
          <a href="?export=excel&csrf_token=<?php echo $_SESSION['csrf_token']; ?>"><i class="fa-solid fa-file-excel"></i> Excel</a>
          <a href="?export=csv&csrf_token=<?php echo $_SESSION['csrf_token']; ?>"><i class="fa-solid fa-file-csv"></i> CSV</a>
          <a href="#" onclick="printTable()"><i class="fa-solid fa-file-pdf"></i> PDF (Print)</a>
        </div>
      </div>
      
      <table id="dataTable">
        <thead>
          <tr>
            <th>Student ID</th>
            <th>Name</th>
            <th>Particular</th>
            <th>Amount</th>
            <th>Payment Date</th>
            <th>Payment type</th>
          </tr>
        </thead>
        <tbody>
          <?php
           $select = "SELECT * FROM record WHERE status = 'approved' ORDER BY date ASC";
            $result = mysqli_query($con, $select);
            while($row = mysqli_fetch_assoc($result)) {
              echo '<tr>';
              echo '<form method="post">';
              echo '<td><input type="text" name="stidd" value="s' . htmlspecialchars($row['stid']) . '" readonly></td>';
              echo '<td><input type="text" name="named" value="' . htmlspecialchars($row['name']) . '" readonly></td>';
              echo '<td><input type="text" name="particulard" value="' . htmlspecialchars($row['particular']) . '" readonly></td>';
              echo '<td><input type="text" name="amountd" value="' . htmlspecialchars(number_format($row['amount'], 2)) . '" readonly></td>';
              echo '<td><input type="text" name="dated" value="' . htmlspecialchars(date('F j, Y', strtotime($row['date']))) . '" readonly></td>';
              echo '<td><input type="text" name="typed" value="' . htmlspecialchars($row['type']) . '" readonly></td>';
              echo '</form>';
              echo '</tr>';
            }
            ?> 
        </tbody>
      </table>
    </div>
  </div>

<script>
  // Toggle download dropdown
  function toggleDropdown1() {
    document.getElementById("downloadDropdown1").classList.toggle("show");
  }
  
  // Close the dropdown if clicked outside
  window.onclick = function(event) {
    if (!event.target.matches('.dl')) {
      var dropdowns = document.getElementsByClassName("dropdown-content1");
      for (var i = 0; i < dropdowns.length; i++) {
        var openDropdown = dropdowns[i];
        if (openDropdown.classList.contains('show')) {
          openDropdown.classList.remove('show');
        }
      }
    }
  }
  
  // Print table as PDF alternative
  function printTable() {
    var printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Payment Records</title>');
    printWindow.document.write('<style>table {border-collapse: collapse; width: 100%;} th, td {border: 1px solid #ddd; padding: 8px; text-align: left;} th {background-color: #f2f2f2;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h1>Payment Records</h1>');
    printWindow.document.write(document.getElementById("dataTable").outerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
  }
  
  // Improved search functionality
  document.getElementById('searchInput').addEventListener('input', function() {
    var input = this.value.toLowerCase();
    var rows = document.querySelectorAll('#dataTable tbody tr');
    var found = false;

    rows.forEach(function(row) {
      var cells = row.querySelectorAll('td');
      var match = false;

      cells.forEach(function(cell) {
        var inputField = cell.querySelector('input');
        var cellText = inputField ? inputField.value.toLowerCase() : cell.textContent.toLowerCase();
        if (cellText.includes(input)) {
          match = true;
          found = true;
        }
      });

      row.style.display = match ? '' : 'none';
    });

    if (!found && input.trim() !== '') {
      speak("No matching records found.");
    }
  });

  // Enhanced Voice Recognition System
  const recognitionButton = document.getElementById('recognition');
  const searchInput = document.getElementById('searchInput');
  let recognition;
  let isListening = false;
  let isSystemSpeaking = false;
  let lastVoiceCommand = '';

  // Initialize voice recognition
  function initVoiceRecognition() {
    if (!('webkitSpeechRecognition' in window)) {
      recognitionButton.style.display = 'none';
      console.warn('Web Speech API not supported');
      return;
    }

    recognition = new webkitSpeechRecognition();
    recognition.continuous = false;
    recognition.interimResults = false;
    recognition.lang = 'en-US';
    recognition.maxAlternatives = 1;

    recognition.onstart = function() {
      isListening = true;
      updateMicButton();
    };

    recognition.onresult = function(event) {
      const transcript = event.results[0][0].transcript;
      lastVoiceCommand = transcript;
      searchInput.value = transcript;
      searchInput.dispatchEvent(new Event('input'));
      stopRecognition(); // Auto-stop after getting result
    };

    recognition.onerror = function(event) {
      console.error('Recognition error:', event.error);
      stopRecognition();
      setTimeout(() => {
        if (event.error === 'no-speech') {
          speak("I didn't hear anything. Please try again.");
        } else if (event.error === 'audio-capture') {
          speak("Microphone not available.");
        } else if (event.error === 'not-allowed') {
          speak("Microphone access denied.");
        } else {
          speak("Sorry, something went wrong.");
        }
      }, 500);
    };

    recognition.onend = function() {
      if (isListening) {
        setTimeout(() => recognition.start(), 100);
      }
    };
  }

  // Update microphone button visual state
  function updateMicButton() {
    if (isListening) {
      recognitionButton.innerHTML = '<i class="fa-solid fa-microphone-slash"></i>';
      recognitionButton.classList.add('active');
    } else {
      recognitionButton.innerHTML = '<i class="fa-solid fa-microphone"></i>';
      recognitionButton.classList.remove('active');
    }
  }

  // Improved speech synthesis
  function speak(text, callback) {
    if (!('speechSynthesis' in window)) {
      if (callback) callback();
      return;
    }

    isSystemSpeaking = true;
    window.speechSynthesis.cancel();

    const utterance = new SpeechSynthesisUtterance(text);
    utterance.rate = 0.9;
    utterance.pitch = 1.1;

    utterance.onend = utterance.onerror = function() {
      isSystemSpeaking = false;
      if (callback) callback();
    };

    window.speechSynthesis.speak(utterance);
  }

  // Search functionality with voice feedback
  document.getElementById('searchInput').addEventListener('input', function() {
    const input = this.value.toLowerCase();
    const rows = document.querySelectorAll('#dataTable tbody tr');
    let found = false;

    rows.forEach(function(row) {
      const cells = row.querySelectorAll('td');
      let match = false;

      cells.forEach(function(cell) {
        const inputField = cell.querySelector('input');
        const cellText = inputField ? inputField.value.toLowerCase() : cell.textContent.toLowerCase();
        if (cellText.includes(input)) {
          match = true;
          found = true;
        }
      });

      row.style.display = match ? '' : 'none';
    });

    // Voice feedback when no results found
    if (!found && input.trim() !== '') {
      if (lastVoiceCommand) {
        speak(`There is no "${lastVoiceCommand}" data, boss.`);
        lastVoiceCommand = ''; // Reset after speaking
      } else {
        speak("There is no matching data, boss.");
      }
    }
  });

  // Toggle recognition with proper timing
  function toggleRecognition() {
    if (!recognition) initVoiceRecognition();

    if (isListening) {
      stopRecognition();
    } else {
      if (isSystemSpeaking) {
        const checkSpeaking = setInterval(() => {
          if (!isSystemSpeaking) {
            clearInterval(checkSpeaking);
            startRecognition();
          }
        }, 100);
      } else {
        startRecognition();
      }
    }
  }

  function startRecognition() {
    try {
      speak("Listening to your command, boss", function() {
        recognition.start();
      });
    } catch (error) {
      console.error('Recognition start error:', error);
      speak("Microphone error. Please try again.");
    }
  }

  function stopRecognition() {
    isListening = false;
    updateMicButton();
    if (recognition) {
      recognition.stop();
    }
  }

  // Button click handler
  recognitionButton.addEventListener('click', function() {
    if (isSystemSpeaking) return;
    toggleRecognition();
  });

  // Initialize on page load
  if ('webkitSpeechRecognition' in window) {
    initVoiceRecognition();
  }




  // JavaScript to handle the form submission with SweetAlert
document.querySelector('.send').addEventListener('click', function() {
    // Get all input values from the table
    const inputs = document.querySelectorAll('#dataTable tbody tr:not([style*="display: none"]) input');
    const formData = new FormData();
    
    // Add CSRF token
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');
    formData.append('send', true);
    
    // Collect data from visible rows only
    document.querySelectorAll('#dataTable tbody tr:not([style*="display: none"])').forEach((row, index) => {
        const rowInputs = row.querySelectorAll('input');
        formData.append(`stidd[${index}]`, rowInputs[0].value);
        formData.append(`named[${index}]`, rowInputs[1].value);
        formData.append(`particulard[${index}]`, rowInputs[2].value);
        formData.append(`amountd[${index}]`, rowInputs[3].value);
        formData.append(`dated[${index}]`, rowInputs[4].value);
        formData.append(`typed[${index}]`, rowInputs[5].value);
    });

    // Show loading indicator
    Swal.fire({
        title: 'Sending Data',
        html: 'Please wait while we process your request...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    // AJAX request
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if(data.status === 'success') {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                showConfirmButton: false,
                timer: 2000
            });
            // Optional: Clear the form or do something with the response data
            console.log('Data sent:', data.data);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.message,
                confirmButtonText: 'Try Again'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Network Error',
            text: 'Failed to send data. Please check your connection.',
            confirmButtonText: 'OK'
        });
        console.error('Error:', error);
    });
});
</script>
<script src="../js/script.js"></script>
</body>
</html>