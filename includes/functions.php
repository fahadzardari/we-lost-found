<?php
require_once 'config.php';

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if current user is admin
 * @return bool
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

/**
 * Redirect to a specific page
 * @param string $page
 */
function redirect($page) {
    header('Location: ' . BASE_URL . '/' . $page);
    exit;
}

/**
 * Display error message
 * @param string $message
 * @return string HTML formatted error message
 */
function showError($message) {
    return '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">' . $message . '</div>';
}

/**
 * Display success message
 * @param string $message
 * @return string HTML formatted success message
 */
function showSuccess($message) {
    return '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">' . $message . '</div>';
}

/**
 * Sanitize user input
 * @param string $data
 * @return string
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Upload image file
 * @param array $file $_FILES array element
 * @return string|bool Image path or false on failure
 */
function uploadImage($file) {
    // Check if file was uploaded without errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log('File upload error code: ' . $file['error']);
        return false;
    }
    
    // Validate file type
    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        error_log('Invalid file type: ' . $ext);
        return false;
    }
    
    // Generate unique filename
    $filename = uniqid() . '.' . $ext;
    $destination = UPLOAD_DIR . $filename;
    
    // Check if directory exists and is writable
    if (!is_dir(UPLOAD_DIR)) {
        // Try to create directory with full permissions
        if (!mkdir(UPLOAD_DIR, 0777, true)) {
            error_log('Failed to create directory: ' . UPLOAD_DIR);
            return false;
        }
    }
    
    // Check if directory is writable
    if (!is_writable(UPLOAD_DIR)) {
        error_log('Upload directory is not writable: ' . UPLOAD_DIR);
        return false;
    }
    
    // Move the uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $filename;
    } else {
        error_log('Failed to move uploaded file to: ' . $destination);
        return false;
    }
}

/**
 * Find potential matches between lost and found items
 * @param int $itemId ID of the item to find matches for
 * @param string $itemType 'lost' or 'found'
 * @return array Array of potential matches
 */
function findMatches($itemId, $itemType) {
    global $conn;
    
    try {
        // Get item details
        $stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            return [];
        }
        
        // Search for opposite type items (lost -> found, found -> lost)
        $oppositeType = ($itemType == 'lost') ? 'found' : 'lost';
        
        // Build query with multiple matching criteria
        $query = "
            SELECT i.*, 
                   (CASE 
                     WHEN i.category = ? THEN 25
                     ELSE 0
                   END +
                   CASE 
                     WHEN i.location = ? THEN 25
                     ELSE 0
                   END +
                   CASE 
                     WHEN DATE(i.date_lost_found) BETWEEN DATE_SUB(?, INTERVAL 7 DAY) AND DATE_ADD(?, INTERVAL 7 DAY) THEN 25
                     ELSE 0
                   END +
                   CASE 
                     WHEN (
                       ? LIKE CONCAT('%', i.keywords, '%') OR 
                       i.keywords LIKE CONCAT('%', ?, '%') OR
                       i.description LIKE CONCAT('%', ?, '%') OR
                       ? LIKE CONCAT('%', i.description, '%')
                     ) THEN 25
                     ELSE 0
                   END) as match_score
            FROM items i
            WHERE i.type = ? 
            AND i.status = 'active'
            HAVING match_score > 0
            ORDER BY match_score DESC
            LIMIT 10
        ";
        
        $stmt = $conn->prepare($query);
        $stmt->execute([
            $item['category'],
            $item['location'],
            $item['date_lost_found'],
            $item['date_lost_found'],
            $item['keywords'],
            $item['keywords'],
            $item['title'],
            $item['title'],
            $oppositeType
        ]);
        
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Store matches in database
        foreach ($matches as $match) {
            // Determine which ID is lost and which is found
            $lostItemId = ($itemType == 'lost') ? $itemId : $match['id'];
            $foundItemId = ($itemType == 'found') ? $itemId : $match['id'];
            $matchPercent = $match['match_score'];
            
            // Check if this match already exists
            $checkStmt = $conn->prepare("
                SELECT id FROM matches 
                WHERE lost_item_id = ? AND found_item_id = ?
            ");
            $checkStmt->execute([$lostItemId, $foundItemId]);
            
            if ($checkStmt->rowCount() == 0) {
                // Insert new match
                $insertStmt = $conn->prepare("
                    INSERT INTO matches (lost_item_id, found_item_id, match_percentage) 
                    VALUES (?, ?, ?)
                ");
                $insertStmt->execute([$lostItemId, $foundItemId, $matchPercent]);
            }
        }
        
        return $matches;
        
    } catch(PDOException $e) {
        return [];
    }
}
?>