<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_pending_posts':
            $stmt = $pdo->prepare('
                SELECT fp.*, u.name as author_name, u.email as author_email
                FROM forum_posts fp
                JOIN users u ON fp.author = u.id
                WHERE fp.status = "Pending"
                ORDER BY fp.date DESC
            ');
            $stmt->execute();
            $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['data'] = $posts;
            break;
            
        case 'moderate_post':
            $post_id = $_POST['post_id'] ?? '';
            $action_type = $_POST['action_type'] ?? ''; // approve, reject, flag
            $moderator_id = $_POST['moderator_id'] ?? '';
            $reason = $_POST['reason'] ?? '';
            
            if (!$post_id || !$action_type || !$moderator_id) {
                throw new Exception('Post ID, action type, and moderator ID required');
            }
            
            $status = '';
            switch ($action_type) {
                case 'approve':
                    $status = 'Approved';
                    break;
                case 'reject':
                    $status = 'Rejected';
                    break;
                case 'flag':
                    $status = 'Flagged';
                    break;
                default:
                    throw new Exception('Invalid action type');
            }
            
            // Update post status
            $stmt = $pdo->prepare('UPDATE forum_posts SET status = ?, moderated_by = ?, moderated_at = NOW() WHERE id = ?');
            $stmt->execute([$status, $moderator_id, $post_id]);
            
            // Log moderation action
            $stmt = $pdo->prepare('
                INSERT INTO forum_moderation_logs 
                (post_id, moderator_id, action, reason, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ');
            $stmt->execute([$post_id, $moderator_id, $action_type, $reason]);
            
            // Get post details for notification
            $stmt = $pdo->prepare('SELECT author, content FROM forum_posts WHERE id = ?');
            $stmt->execute([$post_id]);
            $post = $stmt->fetch();
            
            if ($post) {
                // Send notification to author
                $notification_message = "Your forum post has been {$action_type}ed";
                if ($reason) {
                    $notification_message .= " - Reason: {$reason}";
                }
                
                $stmt = $pdo->prepare('INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)');
                $stmt->execute([$post['author'], $notification_message, 'forum_moderation']);
            }
            
            $response['success'] = true;
            $response['message'] = "Post {$action_type}ed successfully";
            break;
            
        case 'bulk_moderate':
            $post_ids = $_POST['post_ids'] ?? [];
            $action_type = $_POST['action_type'] ?? '';
            $moderator_id = $_POST['moderator_id'] ?? '';
            $reason = $_POST['reason'] ?? '';
            
            if (empty($post_ids) || !$action_type || !$moderator_id) {
                throw new Exception('Post IDs, action type, and moderator ID required');
            }
            
            $status = '';
            switch ($action_type) {
                case 'approve':
                    $status = 'Approved';
                    break;
                case 'reject':
                    $status = 'Rejected';
                    break;
                case 'flag':
                    $status = 'Flagged';
                    break;
                default:
                    throw new Exception('Invalid action type');
            }
            
            $pdo->beginTransaction();
            
            try {
                foreach ($post_ids as $post_id) {
                    // Update post status
                    $stmt = $pdo->prepare('UPDATE forum_posts SET status = ?, moderated_by = ?, moderated_at = NOW() WHERE id = ?');
                    $stmt->execute([$status, $moderator_id, $post_id]);
                    
                    // Log moderation action
                    $stmt = $pdo->prepare('
                        INSERT INTO forum_moderation_logs 
                        (post_id, moderator_id, action, reason, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ');
                    $stmt->execute([$post_id, $moderator_id, $action_type, $reason]);
                }
                
                $pdo->commit();
                $response['success'] = true;
                $response['message'] = count($post_ids) . " posts {$action_type}ed successfully";
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        case 'get_moderation_logs':
            $limit = $_POST['limit'] ?? $_GET['limit'] ?? 50;
            $offset = $_POST['offset'] ?? $_GET['offset'] ?? 0;
            
            $stmt = $pdo->prepare('
                SELECT fml.*, u.name as moderator_name, fp.content as post_content
                FROM forum_moderation_logs fml
                JOIN users u ON fml.moderator_id = u.id
                JOIN forum_posts fp ON fml.post_id = fp.id
                ORDER BY fml.created_at DESC
                LIMIT ? OFFSET ?
            ');
            $stmt->execute([$limit, $offset]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['data'] = $logs;
            break;
            
        case 'get_forum_stats':
            // Get forum statistics
            $stats = [];
            
            // Total posts
            $stmt = $pdo->query('SELECT COUNT(*) as total FROM forum_posts');
            $stats['total_posts'] = $stmt->fetch()['total'];
            
            // Posts by status
            $stmt = $pdo->query('SELECT status, COUNT(*) as count FROM forum_posts GROUP BY status');
            $stats['posts_by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Posts by date (last 30 days)
            $stmt = $pdo->query('
                SELECT DATE(date) as post_date, COUNT(*) as count 
                FROM forum_posts 
                WHERE date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(date)
                ORDER BY post_date DESC
            ');
            $stats['posts_by_date'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Top authors
            $stmt = $pdo->query('
                SELECT u.name, COUNT(*) as post_count
                FROM forum_posts fp
                JOIN users u ON fp.author = u.id
                GROUP BY fp.author
                ORDER BY post_count DESC
                LIMIT 10
            ');
            $stats['top_authors'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['data'] = $stats;
            break;
            
        case 'set_auto_moderation':
            $auto_approve = $_POST['auto_approve'] ?? false;
            $auto_reject_keywords = $_POST['auto_reject_keywords'] ?? '';
            $auto_flag_keywords = $_POST['auto_flag_keywords'] ?? '';
            $moderator_id = $_POST['moderator_id'] ?? '';
            
            if (!$moderator_id) {
                throw new Exception('Moderator ID required');
            }
            
            // Update auto-moderation settings
            $stmt = $pdo->prepare('
                INSERT INTO forum_settings (setting_key, setting_value, updated_by, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()
            ');
            
            $stmt->execute(['auto_approve', $auto_approve ? '1' : '0', $moderator_id]);
            $stmt->execute(['auto_reject_keywords', $auto_reject_keywords, $moderator_id]);
            $stmt->execute(['auto_flag_keywords', $auto_flag_keywords, $moderator_id]);
            
            $response['success'] = true;
            $response['message'] = 'Auto-moderation settings updated successfully';
            break;
            
        case 'get_auto_moderation_settings':
            $stmt = $pdo->query('SELECT setting_key, setting_value FROM forum_settings WHERE setting_key LIKE "auto_%"');
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $formatted_settings = [];
            foreach ($settings as $setting) {
                $formatted_settings[$setting['setting_key']] = $setting['setting_value'];
            }
            
            $response['success'] = true;
            $response['data'] = $formatted_settings;
            break;
            
        case 'flag_inappropriate':
            $post_id = $_POST['post_id'] ?? '';
            $reporter_id = $_POST['reporter_id'] ?? '';
            $reason = $_POST['reason'] ?? '';
            
            if (!$post_id || !$reporter_id || !$reason) {
                throw new Exception('Post ID, reporter ID, and reason required');
            }
            
            // Check if already flagged
            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM forum_flags WHERE post_id = ? AND reporter_id = ?');
            $stmt->execute([$post_id, $reporter_id]);
            $existing = $stmt->fetch();
            
            if ($existing['count'] > 0) {
                throw new Exception('You have already flagged this post');
            }
            
            // Add flag
            $stmt = $pdo->prepare('
                INSERT INTO forum_flags (post_id, reporter_id, reason, created_at)
                VALUES (?, ?, ?, NOW())
            ');
            $stmt->execute([$post_id, $reporter_id, $reason]);
            
            // Check if post should be auto-flagged based on number of flags
            $stmt = $pdo->prepare('SELECT COUNT(*) as flag_count FROM forum_flags WHERE post_id = ?');
            $stmt->execute([$post_id]);
            $flag_count = $stmt->fetch()['flag_count'];
            
            if ($flag_count >= 3) {
                // Auto-flag the post
                $stmt = $pdo->prepare('UPDATE forum_posts SET status = "Flagged" WHERE id = ?');
                $stmt->execute([$post_id]);
            }
            
            $response['success'] = true;
            $response['message'] = 'Post flagged successfully';
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
