<?php
require 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // Get all feedback
        $query = "SELECT * FROM feedback WHERE is_deleted = 0 ORDER BY created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        $feedback = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Get replies for each feedback
            $replyQuery = "SELECT * FROM replies WHERE feedback_id = ?";
            $replyStmt = $conn->prepare($replyQuery);
            $replyStmt->execute([$row['id']]);
            $row['replies'] = $replyStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $feedback[] = $row;
        }
        
        echo json_encode($feedback);
        break;
        
    case 'POST':
        // Add new feedback or reply
        $data = json_decode(file_get_contents("php://input"));
        
        if(!empty($data->content)) {
            if(empty($data->feedback_id)) {
                // New feedback
                $query = "INSERT INTO feedback (author, content) VALUES (?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->execute([
                    $data->author ?? 'Anonymous',
                    $data->content
                ]);
                
                $feedbackId = $conn->lastInsertId();
                echo json_encode(["id" => $feedbackId]);
            } else {
                // New reply
                $query = "INSERT INTO replies (feedback_id, author, content) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->execute([
                    $data->feedback_id,
                    $data->author ?? 'Anonymous',
                    $data->content
                ]);
                
                echo json_encode(["id" => $conn->lastInsertId()]);
            }
        } else {
            http_response_code(400);
            echo json_encode(["message" => "Content is required"]);
        }
        break;
        
    case 'DELETE':
        // Delete feedback (admin only)
        $headers = getallheaders();
        if(empty($headers['Authorization'])) {
            http_response_code(401);
            echo json_encode(["message" => "Unauthorized"]);
            exit();
        }
        
        $token = base64_decode(str_replace('Basic ', '', $headers['Authorization']));
        list($username, $pin) = explode(':', $token);
        
        // Verify admin
        $adminQuery = "SELECT * FROM admins WHERE username = ?";
        $adminStmt = $conn->prepare($adminQuery);
        $adminStmt->execute([$username]);
        
        if($adminStmt->rowCount() === 0 || 
           !password_verify($pin, $adminStmt->fetch(PDO::FETCH_ASSOC)['password_hash'])) {
            http_response_code(401);
            echo json_encode(["message" => "Invalid credentials"]);
            exit();
        }
        
        // Soft delete feedback
        $id = $_GET['id'] ?? null;
        if($id) {
            $query = "UPDATE feedback SET is_deleted = 1 WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$id]);
            
            echo json_encode(["message" => "Feedback deleted"]);
        } else {
            http_response_code(400);
            echo json_encode(["message" => "ID is required"]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(["message" => "Method not allowed"]);
}
?>