<?php
// Start session with strict settings
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => true,
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true
]);

// DDoS Protection: Rate Limiting
$max_requests = 50; // Max requests per minute
$time_frame = 60; // 60 seconds

if (!isset($_SESSION['request_count'])) {
    $_SESSION['request_count'] = 1;
    $_SESSION['first_request_time'] = time();
} else {
    $_SESSION['request_count']++;
}

if ($_SESSION['request_count'] > $max_requests && 
    (time() - $_SESSION['first_request_time']) < $time_frame) {
    header('HTTP/1.1 429 Too Many Requests');
    die('Rate limit exceeded. Please try again later.');
}

// Reset counter if time frame has passed
if ((time() - $_SESSION['first_request_time']) > $time_frame) {
    $_SESSION['request_count'] = 1;
    $_SESSION['first_request_time'] = time();
}

// Include database connection with error handling
if (!@include '../connect.php') {
    header('HTTP/1.1 500 Internal Server Error');
    die('Database connection error');
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// DDoS Protection: Validate Request Size
$max_post_size = 1024 * 8; // 8KB max for POST data
if ($_SERVER['CONTENT_LENGTH'] > $max_post_size) {
    header('HTTP/1.1 413 Payload Too Large');
    die('Request too large');
}

// CSRF Token Generation with stronger randomness
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(64));
}

// DDoS Protection: Sleep for random time to slow down attacks
usleep(rand(10000, 100000)); // 10-100ms delay

if (isset($_POST['submit'])) {
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('HTTP/1.1 405 Method Not Allowed');
        die('Invalid request method');
    }

    // Check CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header('HTTP/1.1 403 Forbidden');
        die('CSRF token validation failed');
    }

    // DDoS Protection: Validate required fields exist
    $required_fields = ['select1', 'pname', 'paname', 'amount', 'date', 'year'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) {
            header('HTTP/1.1 400 Bad Request');
            die("Missing required field: $field");
        }
    }

    // Get and sanitize inputs with strict validation
    $program = filter_input(INPUT_POST, 'select1', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    $pname = filter_input(INPUT_POST, 'pname', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    $paname = filter_input(INPUT_POST, 'paname', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $year = filter_input(INPUT_POST, 'year', FILTER_VALIDATE_INT);
    $misc_text = isset($_POST['misc']) ? 'Miscellaneous' : '';

    // Validate inputs
    if ($program === false || $pname === false || $paname === false || 
        $amount === false || $date === false || $year === false) {
        header('HTTP/1.1 400 Bad Request');
        die('Invalid input data');
    }

    try {
        // DDoS Protection: Limit transaction time
        $con->query("SET SESSION max_execution_time=5000"); // 5 seconds max
        
        $con->begin_transaction();
        
        // Get all student IDs for the selected program with LIMIT
        $studentQuery = $con->prepare("SELECT stid FROM students WHERE program = ? LIMIT 1000");
        if (!$studentQuery) {
            throw new Exception("Prepare failed: " . $con->error);
        }
        $studentQuery->bind_param("s", $program);
        if (!$studentQuery->execute()) {
            throw new Exception("Execute failed: " . $studentQuery->error);
        }
        $result = $studentQuery->get_result();
        
        // Insert fees for all students with batch processing
        $stmt = $con->prepare("INSERT INTO fees (selct, pname, paname, amount, date, student_id, Miscellaneous, year) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $con->error);
        }
        
        $batch_count = 0;
        while ($row = $result->fetch_assoc()) {
            $student_id = $row['stid'];
            $stmt->bind_param("sssssiss", $program, $pname, $paname, $amount, $date, $student_id, $misc_text, $year);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            // DDoS Protection: Limit batch size
            if (++$batch_count >= 1000) {
                break;
            }
        }
        
        // Update balances ONLY if miscellaneous is checked
        if (isset($_POST['misc'])) {
            $updateStmt = $con->prepare("UPDATE students SET balance = balance + ? WHERE program = ? AND level = ? LIMIT 1000");
            if (!$updateStmt) {
                throw new Exception("Prepare failed: " . $con->error);
            }
            $updateStmt->bind_param("iss", $amount, $program, $year);
            if (!$updateStmt->execute()) {
                throw new Exception("Execute failed: " . $updateStmt->error);
            }
        }
        
        $con->commit();
        $_SESSION['insertMessage'] = "success";
        
    } catch (Exception $e) {
        $con->rollback();
        error_log("Database error: " . $e->getMessage());
        $_SESSION['insertMessage'] = "error: " . $e->getMessage();
    } finally {
        if (isset($studentQuery)) $studentQuery->close();
        if (isset($stmt)) $stmt->close();
        if (isset($updateStmt)) $updateStmt->close();
    }
    
    // Prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['submit1'])) {
    // DDoS Protection: Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('HTTP/1.1 405 Method Not Allowed');
        die('Invalid request method');
    }

    // Check CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header('HTTP/1.1 403 Forbidden');
        die('CSRF token validation failed');
    }

    // Validate required fields
    $required_fields = ['namest', 'idst', 'progs', 'pname', 'paname', 'amount', 'date', 'stid'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field])) {
            header('HTTP/1.1 400 Bad Request');
            die("Missing required field: $field");
        }
    }

    // Sanitize inputs
    $namest = filter_input(INPUT_POST, 'namest', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    $idst = filter_input(INPUT_POST, 'idst', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    $progs = filter_input(INPUT_POST, 'progs', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    $pname = filter_input(INPUT_POST, 'pname', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    $paname = filter_input(INPUT_POST, 'paname', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
    $stid = ltrim(filter_input(INPUT_POST, 'stid', FILTER_SANITIZE_STRING), 's');

    // Validate inputs
    if ($namest === false || $idst === false || $progs === false || 
        $pname === false || $paname === false || $amount === false || 
        $date === false || $stid === false) {
        header('HTTP/1.1 400 Bad Request');
        die('Invalid input data');
    }

    try {
        // Verify student exists
        $stmt = $con->prepare("SELECT stid FROM students WHERE stid = ? LIMIT 1");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $con->error);
        }
        $stmt->bind_param("s", $stid);
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            throw new Exception("Student ID '$stid' does not exist");
        }
        $stmt->close();

        // Insert fee record
        $stmt = $con->prepare("INSERT INTO stfees (pname, stid, program, payname, parname, amount, date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $con->error);
        }
        $stmt->bind_param("sisssis", $namest, $stid, $progs, $pname, $paname, $amount, $date);

        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $_SESSION['insertMessage'] = "success";
        
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['insertMessage'] = "error: " . $e->getMessage();
    } finally {
        if (isset($stmt)) $stmt->close();
    }
    
    // Prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>