<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'fetch':
            $user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? '';
            $role = $_POST['role'] ?? $_GET['role'] ?? '';
            if (empty($user_id) || empty($role)) throw new Exception('User ID and role required');
            
            if ($role === 'vet') {
                $stmt = $pdo->prepare('
                    SELECT ts.*, p.name as pet_name, u.name as owner_name, v.name as vet_name
                    FROM telemedicine_sessions ts
                    JOIN pets p ON ts.pet_id = p.id
                    JOIN users u ON ts.owner_id = u.id
                    JOIN users v ON ts.vet_id = v.id
                    WHERE ts.vet_id = ? 
                    ORDER BY ts.date DESC, ts.time DESC
                ');
            } else {
                $stmt = $pdo->prepare('
                    SELECT ts.*, p.name as pet_name, u.name as owner_name, v.name as vet_name
                    FROM telemedicine_sessions ts
                    JOIN pets p ON ts.pet_id = p.id
                    JOIN users u ON ts.owner_id = u.id
                    JOIN users v ON ts.vet_id = v.id
                    WHERE ts.owner_id = ? 
                    ORDER BY ts.date DESC, ts.time DESC
                ');
            }
            $stmt->execute([$user_id]);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            $response['data'] = $sessions;
            break;
            
        case 'create':
            $pet_id = $_POST['pet_id'] ?? '';
            $owner_id = $_POST['owner_id'] ?? '';
            $vet_id = $_POST['vet_id'] ?? '';
            $date = $_POST['date'] ?? '';
            $time = $_POST['time'] ?? '';
            $video_url = $_POST['video_url'] ?? '';
            $notes = $_POST['notes'] ?? '';
            $session_type = $_POST['session_type'] ?? 'consultation';
            $duration = $_POST['duration'] ?? 30;
            
            if (!$pet_id || !$owner_id || !$vet_id || !$date || !$time) {
                throw new Exception('All required fields must be provided');
            }
            
            // Generate unique session ID
            $session_id = 'TM_' . date('Ymd') . '_' . uniqid();
            
            // Create video room URL (integrate with your preferred video service)
            $video_room = createVideoRoom($session_id);
            
            $stmt = $pdo->prepare('
                INSERT INTO telemedicine_sessions 
                (session_id, pet_id, owner_id, vet_id, date, time, video_url, notes, session_type, duration, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            $stmt->execute([$session_id, $pet_id, $owner_id, $vet_id, $date, $time, $video_room, $notes, $session_type, $duration, 'scheduled']);
            
            // Send notifications
            sendTelemedicineNotifications($owner_id, $vet_id, $session_id, 'created');
            
            $response['success'] = true;
            $response['message'] = 'Telemedicine session created successfully';
            $response['session_id'] = $session_id;
            $response['video_room'] = $video_room;
            break;
            
        case 'update':
            $id = $_POST['id'] ?? '';
            $status = $_POST['status'] ?? '';
            $notes = $_POST['notes'] ?? '';
            $diagnosis = $_POST['diagnosis'] ?? '';
            $prescription = $_POST['prescription'] ?? '';
            $follow_up = $_POST['follow_up'] ?? '';
            
            if (!$id || !$status) throw new Exception('ID and status required');
            
            $stmt = $pdo->prepare('
                UPDATE telemedicine_sessions 
                SET status = ?, notes = ?, diagnosis = ?, prescription = ?, follow_up = ?, updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([$status, $notes, $diagnosis, $prescription, $follow_up, $id]);
            
            // Get session details for notifications
            $stmt = $pdo->prepare('SELECT owner_id, vet_id, session_id FROM telemedicine_sessions WHERE id = ?');
            $stmt->execute([$id]);
            $session = $stmt->fetch();
            
            if ($session) {
                sendTelemedicineNotifications($session['owner_id'], $session['vet_id'], $session['session_id'], $status);
            }
            
            $response['success'] = true;
            $response['message'] = 'Session updated successfully';
            break;
            
        case 'join_session':
            $session_id = $_POST['session_id'] ?? '';
            $user_id = $_POST['user_id'] ?? '';
            $user_role = $_POST['user_role'] ?? '';
            
            if (!$session_id || !$user_id || !$user_role) {
                throw new Exception('Session ID, user ID, and role required');
            }
            
            // Verify user has access to this session
            $stmt = $pdo->prepare('
                SELECT * FROM telemedicine_sessions 
                WHERE session_id = ? AND (owner_id = ? OR vet_id = ?)
            ');
            $stmt->execute([$session_id, $user_id, $user_id]);
            $session = $stmt->fetch();
            
            if (!$session) {
                throw new Exception('Access denied to this session');
            }
            
            // Update session status to 'in_progress' if starting
            if ($session['status'] === 'scheduled') {
                $stmt = $pdo->prepare('UPDATE telemedicine_sessions SET status = ? WHERE session_id = ?');
                $stmt->execute(['in_progress', $session_id]);
            }
            
            $response['success'] = true;
            $response['message'] = 'Joined session successfully';
            $response['video_room'] = $session['video_url'];
            $response['session_data'] = $session;
            break;
            
        case 'end_session':
            $session_id = $_POST['session_id'] ?? '';
            $user_id = $_POST['user_id'] ?? '';
            
            if (!$session_id || !$user_id) {
                throw new Exception('Session ID and user ID required');
            }
            
            $stmt = $pdo->prepare('UPDATE telemedicine_sessions SET status = ? WHERE session_id = ?');
            $stmt->execute(['completed', $session_id]);
            
            $response['success'] = true;
            $response['message'] = 'Session ended successfully';
            break;
            
        case 'delete':
            $id = $_POST['id'] ?? '';
            if (!$id) throw new Exception('ID required');
            
            $stmt = $pdo->prepare('DELETE FROM telemedicine_sessions WHERE id = ?');
            $stmt->execute([$id]);
            
            $response['success'] = true;
            $response['message'] = 'Session deleted successfully';
            break;
            
        case 'get_available_vets':
            $date = $_POST['date'] ?? date('Y-m-d');
            $time = $_POST['time'] ?? '';
            
            $stmt = $pdo->prepare('
                SELECT u.id, u.name, u.email, u.phone
                FROM users u
                WHERE u.role = "vet" AND u.status = "Active"
                AND u.id NOT IN (
                    SELECT vet_id FROM telemedicine_sessions 
                    WHERE date = ? AND time = ? AND status IN ("scheduled", "in_progress")
                )
            ');
            $stmt->execute([$date, $time]);
            $vets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['data'] = $vets;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response); 

// Helper functions
function createVideoRoom($session_id) {
    // Integrate with your preferred video conferencing service
    // Examples: Twilio Video, Agora, Zoom API, etc.
    
    // For demo purposes, return a placeholder URL
    return "https://meet.example.com/room/{$session_id}";
}

function sendTelemedicineNotifications($owner_id, $vet_id, $session_id, $action) {
    // Send real-time notifications via WebSocket
    $notifications = [
        'owner' => [
            'created' => 'Your telemedicine session has been scheduled',
            'in_progress' => 'Your telemedicine session is starting',
            'completed' => 'Your telemedicine session has ended'
        ],
        'vet' => [
            'created' => 'New telemedicine session assigned',
            'in_progress' => 'Telemedicine session starting',
            'completed' => 'Telemedicine session completed'
        ]
    ];
    
    // Send to owner
    if (isset($notifications['owner'][$action])) {
        // Implementation would send via WebSocket
        error_log("Notification to owner {$owner_id}: {$notifications['owner'][$action]}");
    }
    
    // Send to vet
    if (isset($notifications['vet'][$action])) {
        // Implementation would send via WebSocket
        error_log("Notification to vet {$vet_id}: {$notifications['vet'][$action]}");
    }
}
