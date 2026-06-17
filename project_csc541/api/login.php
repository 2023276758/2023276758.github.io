<?php
require 'config.php';

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->pin)) {
    $query = "SELECT * FROM admins WHERE username = 'admin'";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    if($stmt->rowCount() > 0) {
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if(password_verify($data->pin, $admin['password_hash'])) {
            echo json_encode([
                "success" => true,
                "token" => base64_encode("admin:" . $data->pin)
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["message" => "Invalid PIN"]);
        }
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "PIN is required"]);
}
?>