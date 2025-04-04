<?php
include '../connect.php';
// Set headers
header('Content-Type: application/json');

// Handle request
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Query to get records with particular 'Diploma' or 'TOR'
    $query = "SELECT id, name, stid, particular, amount, date, type 
              FROM record 
              WHERE particular IN ('Diploma', 'TOR')";
    
    // Add pagination if parameters exist
    if (isset($_GET['page']) && isset($_GET['limit'])) {
        $page = max(1, (int)$_GET['page']);
        $limit = max(1, min(100, (int)$_GET['limit']));
        $offset = ($page - 1) * $limit;
        $query .= " LIMIT $limit OFFSET $offset";
    }
    
    $result = mysqli_query($con, $query);
    
    if (!$result) {
        die(json_encode([
            'success' => false,
            'message' => 'Database query failed'
        ]));
    }
    
    $records = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $records[] = [
            'id' => (int)$row['id'],
            'name' => htmlspecialchars($row['name']),
            'stid' => (int)$row['stid'],
            'particular' => htmlspecialchars($row['particular']),
            'amount' => htmlspecialchars($row['amount']),
            'date' => $row['date'],
            'type' => htmlspecialchars($row['type'])
        ];
    }
    
    // Get total count
    $count_result = mysqli_query($con, "SELECT COUNT(*) FROM record WHERE particular IN ('Diploma', 'TOR')");
    $total_count = mysqli_fetch_row($count_result)[0];
    
    // Return response
    echo json_encode([
        'success' => true,
        'data' => $records,
        'count' => count($records),
        'total' => (int)$total_count
    ]);
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Only GET requests are allowed'
    ]);
}

// Close connection
mysqli_close($con);
?>