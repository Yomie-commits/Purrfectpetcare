<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'fetch':
            $user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? '';
            if (empty($user_id)) throw new Exception('User ID required');
            $stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50');
            $stmt->execute([$user_id]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            $response['data'] = $notifications;
            break;
        case 'mark_read':
            $notif_id = $_POST['id'] ?? $_GET['id'] ?? '';
            if (empty($notif_id)) throw new Exception('Notification ID required');
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ?');
            $stmt->execute([$notif_id]);
            $response['success'] = true;
            $response['message'] = 'Notification marked as read';
            break;
        case 'create':
            $user_id = $_POST['user_id'] ?? '';
            $message = $_POST['message'] ?? '';
            if (empty($user_id) || empty($message)) throw new Exception('User ID and message required');
            $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message) VALUES (?, ?)');
            $stmt->execute([$user_id, $message]);
            $response['success'] = true;
            $response['message'] = 'Notification created';
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response); 