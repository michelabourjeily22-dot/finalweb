<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    switch ($method) {
        case 'GET':
            // Get comments for a car
            $carId = isset($_GET['car_id']) ? $_GET['car_id'] : null;
            
            if (!$carId) {
                echo json_encode(['success' => false, 'error' => 'Car ID required']);
                exit;
            }
            
            $stmt = $pdo->prepare("
                SELECT c.*, u.full_name, u.profile_picture, u.username
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.car_id = ?
                ORDER BY c.created_at DESC
            ");
            $stmt->execute([$carId]);
            $comments = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'comments' => $comments]);
            break;
            
        case 'POST':
            // Add a comment
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'error' => 'not_logged_in']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['car_id']) || !isset($input['comment_text']) || empty(trim($input['comment_text']))) {
                echo json_encode(['success' => false, 'error' => 'Invalid request']);
                exit;
            }
            
            $carId = $input['car_id'];
            $commentText = trim($input['comment_text']);
            $userId = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare("INSERT INTO comments (car_id, user_id, comment_text) VALUES (?, ?, ?)");
            $stmt->execute([$carId, $userId, $commentText]);
            
            $commentId = $pdo->lastInsertId();
            
            // Get the comment with user info
            $stmt = $pdo->prepare("
                SELECT c.*, u.full_name, u.profile_picture, u.username
                FROM comments c
                JOIN users u ON c.user_id = u.id
                WHERE c.id = ?
            ");
            $stmt->execute([$commentId]);
            $comment = $stmt->fetch();
            
            echo json_encode(['success' => true, 'comment' => $comment]);
            break;
            
        case 'DELETE':
            // Delete a comment (only by owner)
            if (!isLoggedIn()) {
                echo json_encode(['success' => false, 'error' => 'not_logged_in']);
                exit;
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $commentId = $input['comment_id'] ?? null;
            $userId = $_SESSION['user_id'];
            
            if (!$commentId) {
                echo json_encode(['success' => false, 'error' => 'Comment ID required']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
            $stmt->execute([$commentId, $userId]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Comment not found or unauthorized']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Comments API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error']);
}

