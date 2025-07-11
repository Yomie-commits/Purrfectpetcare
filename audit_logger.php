<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

class AuditLogger {
    private $pdo;
    private $user_id;
    private $user_role;
    private $session_id;
    
    public function __construct($pdo, $user_id = null, $user_role = null) {
        $this->pdo = $pdo;
        $this->user_id = $user_id;
        $this->user_role = $user_role;
        $this->session_id = session_id();
    }
    
    public function logAction($action, $details = '', $entity_type = null, $entity_id = null, $severity = 'info') {
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO audit_logs 
                (user_id, user_role, session_id, action, details, entity_type, entity_id, severity, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            
            $stmt->execute([
                $this->user_id,
                $this->user_role,
                $this->session_id,
                $action,
                $details,
                $entity_type,
                $entity_id,
                $severity,
                $this->getClientIP(),
                $this->getUserAgent()
            ]);
            
            return $this->pdo->lastInsertId();
            
        } catch (Exception $e) {
            error_log('Audit logging error: ' . $e->getMessage());
            return false;
        }
    }
    
    public function logLogin($user_id, $user_role, $success = true, $details = '') {
        $action = $success ? 'login_success' : 'login_failed';
        $severity = $success ? 'info' : 'warning';
        
        return $this->logAction($action, $details, 'user', $user_id, $severity);
    }
    
    public function logLogout($user_id, $user_role) {
        return $this->logAction('logout', 'User logged out', 'user', $user_id, 'info');
    }
    
    public function logDataAccess($entity_type, $entity_id, $access_type = 'read') {
        $action = $access_type . '_' . $entity_type;
        return $this->logAction($action, "Accessed {$entity_type} ID: {$entity_id}", $entity_type, $entity_id, 'info');
    }
    
    public function logDataModification($entity_type, $entity_id, $operation, $old_data = null, $new_data = null) {
        $action = $operation . '_' . $entity_type;
        $details = "Modified {$entity_type} ID: {$entity_id}";
        
        if ($old_data && $new_data) {
            $changes = $this->getDataChanges($old_data, $new_data);
            $details .= " - Changes: " . json_encode($changes);
        }
        
        return $this->logAction($action, $details, $entity_type, $entity_id, 'info');
    }
    
    public function logDataDeletion($entity_type, $entity_id, $deleted_data = null) {
        $action = 'delete_' . $entity_type;
        $details = "Deleted {$entity_type} ID: {$entity_id}";
        
        if ($deleted_data) {
            $details .= " - Data: " . json_encode($deleted_data);
        }
        
        return $this->logAction($action, $details, $entity_type, $entity_id, 'warning');
    }
    
    public function logSecurityEvent($event_type, $details = '', $severity = 'warning') {
        return $this->logAction('security_' . $event_type, $details, 'security', null, $severity);
    }
    
    public function logSystemEvent($event_type, $details = '', $severity = 'info') {
        return $this->logAction('system_' . $event_type, $details, 'system', null, $severity);
    }
    
    public function logError($error_type, $error_message, $stack_trace = null) {
        $details = $error_message;
        if ($stack_trace) {
            $details .= " - Stack trace: " . $stack_trace;
        }
        
        return $this->logAction('error_' . $error_type, $details, 'error', null, 'error');
    }
    
    public function logAdminAction($action, $details = '', $entity_type = null, $entity_id = null) {
        if ($this->user_role !== 'admin') {
            $this->logSecurityEvent('unauthorized_admin_action', "Non-admin user attempted admin action: {$action}", 'high');
            return false;
        }
        
        return $this->logAction('admin_' . $action, $details, $entity_type, $entity_id, 'info');
    }
    
    public function logUserManagement($action, $target_user_id, $details = '') {
        return $this->logAction('user_management_' . $action, $details, 'user', $target_user_id, 'info');
    }
    
    public function logConfigurationChange($setting_name, $old_value, $new_value) {
        $details = "Setting '{$setting_name}' changed from '{$old_value}' to '{$new_value}'";
        return $this->logAction('config_change', $details, 'configuration', null, 'info');
    }
    
    public function logBackupOperation($operation, $details = '') {
        return $this->logAction('backup_' . $operation, $details, 'backup', null, 'info');
    }
    
    public function logDataExport($export_type, $filters = [], $record_count = 0) {
        $details = "Exported {$record_count} records of type {$export_type}";
        if (!empty($filters)) {
            $details .= " with filters: " . json_encode($filters);
        }
        
        return $this->logAction('data_export', $details, 'export', null, 'info');
    }
    
    public function logDataImport($import_type, $record_count = 0, $success_count = 0, $error_count = 0) {
        $details = "Imported {$record_count} records of type {$import_type} - Success: {$success_count}, Errors: {$error_count}";
        return $this->logAction('data_import', $details, 'import', null, 'info');
    }
    
    public function logPaymentEvent($payment_id, $event_type, $amount = null, $details = '') {
        $action = 'payment_' . $event_type;
        $details = "Payment ID: {$payment_id}" . ($amount ? ", Amount: {$amount}" : '') . ($details ? " - {$details}" : '');
        
        return $this->logAction($action, $details, 'payment', $payment_id, 'info');
    }
    
    public function logAppointmentEvent($appointment_id, $event_type, $details = '') {
        $action = 'appointment_' . $event_type;
        return $this->logAction($action, $details, 'appointment', $appointment_id, 'info');
    }
    
    public function logPrescriptionEvent($prescription_id, $event_type, $details = '') {
        $action = 'prescription_' . $event_type;
        return $this->logAction($action, $details, 'prescription', $prescription_id, 'info');
    }
    
    public function logTelemedicineEvent($session_id, $event_type, $details = '') {
        $action = 'telemedicine_' . $event_type;
        return $this->logAction($action, $details, 'telemedicine', $session_id, 'info');
    }
    
    public function logForumModeration($post_id, $action_type, $moderator_id, $reason = '') {
        $details = "Post ID: {$post_id}, Action: {$action_type}" . ($reason ? ", Reason: {$reason}" : '');
        return $this->logAction('forum_moderation', $details, 'forum_post', $post_id, 'info');
    }
    
    public function logFileOperation($operation, $file_path, $file_size = null, $details = '') {
        $action = 'file_' . $operation;
        $details = "File: {$file_path}" . ($file_size ? ", Size: {$file_size}" : '') . ($details ? " - {$details}" : '');
        
        return $this->logAction($action, $details, 'file', null, 'info');
    }
    
    public function logNotificationSent($notification_type, $recipient, $subject = '', $success = true) {
        $action = 'notification_' . $notification_type;
        $details = "Recipient: {$recipient}" . ($subject ? ", Subject: {$subject}" : '') . ", Status: " . ($success ? 'sent' : 'failed');
        
        return $this->logAction($action, $details, 'notification', null, $success ? 'info' : 'warning');
    }
    
    public function getAuditLogs($filters = [], $limit = 100, $offset = 0) {
        $where_conditions = [];
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $where_conditions[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $where_conditions[] = 'action LIKE ?';
            $params[] = '%' . $filters['action'] . '%';
        }
        
        if (!empty($filters['entity_type'])) {
            $where_conditions[] = 'entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['severity'])) {
            $where_conditions[] = 'severity = ?';
            $params[] = $filters['severity'];
        }
        
        if (!empty($filters['date_from'])) {
            $where_conditions[] = 'created_at >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_conditions[] = 'created_at <= ?';
            $params[] = $filters['date_to'];
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        $sql = "
            SELECT al.*, u.name as user_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            {$where_clause}
            ORDER BY al.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getAuditSummary($date_from = null, $date_to = null) {
        $where_conditions = [];
        $params = [];
        
        if ($date_from) {
            $where_conditions[] = 'created_at >= ?';
            $params[] = $date_from;
        }
        
        if ($date_to) {
            $where_conditions[] = 'created_at <= ?';
            $params[] = $date_to;
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Get summary statistics
        $sql = "
            SELECT 
                COUNT(*) as total_actions,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(CASE WHEN severity = 'error' THEN 1 END) as errors,
                COUNT(CASE WHEN severity = 'warning' THEN 1 END) as warnings,
                COUNT(CASE WHEN severity = 'high' THEN 1 END) as high_severity,
                COUNT(CASE WHEN action LIKE 'security_%' THEN 1 END) as security_events,
                COUNT(CASE WHEN action LIKE 'admin_%' THEN 1 END) as admin_actions
            FROM audit_logs
            {$where_clause}
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get top actions
        $sql = "
            SELECT action, COUNT(*) as count
            FROM audit_logs
            {$where_clause}
            GROUP BY action
            ORDER BY count DESC
            LIMIT 10
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $top_actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get top users
        $sql = "
            SELECT u.name, COUNT(*) as count
            FROM audit_logs al
            JOIN users u ON al.user_id = u.id
            {$where_clause}
            GROUP BY al.user_id, u.name
            ORDER BY count DESC
            LIMIT 10
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $top_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'summary' => $summary,
            'top_actions' => $top_actions,
            'top_users' => $top_users
        ];
    }
    
    public function exportAuditLogs($filters = [], $format = 'csv') {
        $logs = $this->getAuditLogs($filters, 10000, 0); // Get up to 10,000 records
        
        if ($format === 'csv') {
            return $this->exportToCSV($logs);
        } elseif ($format === 'json') {
            return json_encode($logs, JSON_PRETTY_PRINT);
        }
        
        return false;
    }
    
    private function exportToCSV($logs) {
        if (empty($logs)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        
        // Write headers
        fputcsv($output, array_keys($logs[0]));
        
        // Write data
        foreach ($logs as $log) {
            fputcsv($output, $log);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    private function getDataChanges($old_data, $new_data) {
        $changes = [];
        
        foreach ($new_data as $key => $new_value) {
            if (isset($old_data[$key]) && $old_data[$key] !== $new_value) {
                $changes[$key] = [
                    'old' => $old_data[$key],
                    'new' => $new_value
                ];
            }
        }
        
        return $changes;
    }
    
    private function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    private function getUserAgent() {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    }
    
    public function cleanupOldLogs($days_to_keep = 90) {
        $stmt = $this->pdo->prepare('
            DELETE FROM audit_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ');
        
        $stmt->execute([$days_to_keep]);
        return $stmt->rowCount();
    }
}

// API endpoint handling
$response = ['success' => false];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $user_id = $_POST['user_id'] ?? $_GET['user_id'] ?? null;
    $user_role = $_POST['user_role'] ?? $_GET['user_role'] ?? null;
    
    $auditLogger = new AuditLogger($pdo, $user_id, $user_role);
    
    switch ($action) {
        case 'log_action':
            $action_type = $_POST['action_type'] ?? '';
            $details = $_POST['details'] ?? '';
            $entity_type = $_POST['entity_type'] ?? null;
            $entity_id = $_POST['entity_id'] ?? null;
            $severity = $_POST['severity'] ?? 'info';
            
            if (!$action_type) {
                throw new Exception('Action type required');
            }
            
            $log_id = $auditLogger->logAction($action_type, $details, $entity_type, $entity_id, $severity);
            
            $response['success'] = $log_id !== false;
            $response['log_id'] = $log_id;
            $response['message'] = $log_id ? 'Action logged successfully' : 'Failed to log action';
            break;
            
        case 'get_logs':
            $filters = $_POST['filters'] ?? $_GET['filters'] ?? [];
            $limit = $_POST['limit'] ?? $_GET['limit'] ?? 100;
            $offset = $_POST['offset'] ?? $_GET['offset'] ?? 0;
            
            $logs = $auditLogger->getAuditLogs($filters, $limit, $offset);
            
            $response['success'] = true;
            $response['data'] = $logs;
            break;
            
        case 'get_summary':
            $date_from = $_POST['date_from'] ?? $_GET['date_from'] ?? null;
            $date_to = $_POST['date_to'] ?? $_GET['date_to'] ?? null;
            
            $summary = $auditLogger->getAuditSummary($date_from, $date_to);
            
            $response['success'] = true;
            $response['data'] = $summary;
            break;
            
        case 'export_logs':
            $filters = $_POST['filters'] ?? $_GET['filters'] ?? [];
            $format = $_POST['format'] ?? $_GET['format'] ?? 'csv';
            
            $export_data = $auditLogger->exportAuditLogs($filters, $format);
            
            if ($export_data !== false) {
                $response['success'] = true;
                $response['data'] = $export_data;
                $response['format'] = $format;
            } else {
                throw new Exception('Failed to export logs');
            }
            break;
            
        case 'cleanup_logs':
            $days_to_keep = $_POST['days_to_keep'] ?? $_GET['days_to_keep'] ?? 90;
            
            $deleted_count = $auditLogger->cleanupOldLogs($days_to_keep);
            
            $response['success'] = true;
            $response['deleted_count'] = $deleted_count;
            $response['message'] = "Deleted {$deleted_count} old log entries";
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
