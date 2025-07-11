<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

class NotificationService {
    private $pdo;
    private $email_config;
    private $sms_config;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadConfig();
    }
    
    private function loadConfig() {
        // Email configuration (configure with your SMTP settings)
        $this->email_config = [
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_username' => 'your-email@gmail.com',
            'smtp_password' => 'your-app-password',
            'from_email' => 'noreply@purrfectpetcare.com',
            'from_name' => 'Purrfect Pet Care'
        ];
        
        // SMS configuration (configure with your SMS provider)
        $this->sms_config = [
            'provider' => 'twilio', // or 'africastalking', 'nexmo', etc.
            'account_sid' => 'your-account-sid',
            'auth_token' => 'your-auth-token',
            'from_number' => '+1234567890'
        ];
    }
    
    public function sendEmail($to, $subject, $message, $template = null, $data = []) {
        try {
            if ($template) {
                $message = $this->renderTemplate($template, $data);
            }
            
            // Use PHPMailer or similar library for production
            $headers = [
                'From: ' . $this->email_config['from_name'] . ' <' . $this->email_config['from_email'] . '>',
                'Reply-To: ' . $this->email_config['from_email'],
                'Content-Type: text/html; charset=UTF-8',
                'X-Mailer: PHP/' . phpversion()
            ];
            
            $result = mail($to, $subject, $message, implode("\r\n", $headers));
            
            if ($result) {
                $this->logNotification('email', $to, $subject, 'sent');
                return ['success' => true, 'message' => 'Email sent successfully'];
            } else {
                $this->logNotification('email', $to, $subject, 'failed');
                return ['success' => false, 'message' => 'Failed to send email'];
            }
            
        } catch (Exception $e) {
            $this->logNotification('email', $to, $subject, 'error', $e->getMessage());
            return ['success' => false, 'message' => 'Email error: ' . $e->getMessage()];
        }
    }
    
    public function sendSMS($to, $message, $template = null, $data = []) {
        try {
            if ($template) {
                $message = $this->renderTemplate($template, $data);
            }
            
            // Use SMS provider API (Twilio, AfricasTalking, etc.)
            $result = $this->sendViaSMSProvider($to, $message);
            
            if ($result['success']) {
                $this->logNotification('sms', $to, $message, 'sent');
                return ['success' => true, 'message' => 'SMS sent successfully'];
            } else {
                $this->logNotification('sms', $to, $message, 'failed');
                return ['success' => false, 'message' => 'Failed to send SMS'];
            }
            
        } catch (Exception $e) {
            $this->logNotification('sms', $to, $message, 'error', $e->getMessage());
            return ['success' => false, 'message' => 'SMS error: ' . $e->getMessage()];
        }
    }
    
    private function sendViaSMSProvider($to, $message) {
        // Example using Twilio (install via composer: composer require twilio/sdk)
        if ($this->sms_config['provider'] === 'twilio') {
            return $this->sendViaTwilio($to, $message);
        }
        
        // Example using AfricasTalking
        if ($this->sms_config['provider'] === 'africastalking') {
            return $this->sendViaAfricasTalking($to, $message);
        }
        
        // Fallback to curl request
        return $this->sendViaCurl($to, $message);
    }
    
    private function sendViaTwilio($to, $message) {
        // This requires Twilio SDK
        // require_once 'vendor/autoload.php';
        // use Twilio\Rest\Client;
        
        try {
            // $client = new Client($this->sms_config['account_sid'], $this->sms_config['auth_token']);
            // $message = $client->messages->create(
            //     $to,
            //     [
            //         'from' => $this->sms_config['from_number'],
            //         'body' => $message
            //     ]
            // );
            
            // For demo purposes, simulate success
            return ['success' => true, 'message_id' => 'demo_' . uniqid()];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    private function sendViaAfricasTalking($to, $message) {
        $url = 'https://api.africastalking.com/version1/messaging';
        $data = [
            'username' => $this->sms_config['username'],
            'to' => $to,
            'message' => $message,
            'from' => $this->sms_config['from_number']
        ];
        
        $headers = [
            'ApiKey: ' . $this->sms_config['api_key'],
            'Content-Type: application/x-www-form-urlencoded'
        ];
        
        return $this->makeCurlRequest($url, $data, $headers);
    }
    
    private function sendViaCurl($to, $message) {
        // Generic SMS API call
        $url = 'https://api.smsprovider.com/send';
        $data = [
            'to' => $to,
            'message' => $message,
            'api_key' => $this->sms_config['api_key']
        ];
        
        return $this->makeCurlRequest($url, $data);
    }
    
    private function makeCurlRequest($url, $data, $headers = []) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return ['success' => true, 'response' => $response];
        } else {
            return ['success' => false, 'error' => 'HTTP ' . $httpCode, 'response' => $response];
        }
    }
    
    public function sendAppointmentReminder($appointment_id) {
        $stmt = $this->pdo->prepare('
            SELECT a.*, p.name as pet_name, u.name as owner_name, u.email, u.phone
            FROM appointments a
            JOIN pets p ON a.pet_id = p.id
            JOIN users u ON a.owner_id = u.id
            WHERE a.id = ?
        ');
        $stmt->execute([$appointment_id]);
        $appointment = $stmt->fetch();
        
        if (!$appointment) {
            return ['success' => false, 'message' => 'Appointment not found'];
        }
        
        $data = [
            'owner_name' => $appointment['owner_name'],
            'pet_name' => $appointment['pet_name'],
            'appointment_date' => $appointment['date'],
            'appointment_time' => $appointment['time'],
            'service' => $appointment['service']
        ];
        
        // Send email reminder
        $emailResult = $this->sendEmail(
            $appointment['email'],
            'Appointment Reminder - ' . $appointment['pet_name'],
            '',
            'appointment_reminder',
            $data
        );
        
        // Send SMS reminder
        $smsResult = $this->sendSMS(
            $appointment['phone'],
            '',
            'appointment_reminder_sms',
            $data
        );
        
        return [
            'success' => $emailResult['success'] || $smsResult['success'],
            'email' => $emailResult,
            'sms' => $smsResult
        ];
    }
    
    public function sendVaccinationReminder($pet_id) {
        $stmt = $this->pdo->prepare('
            SELECT p.name as pet_name, u.name as owner_name, u.email, u.phone, v.*
            FROM vaccinations v
            JOIN pets p ON v.pet_id = p.id
            JOIN users u ON p.owner_id = u.id
            WHERE v.pet_id = ? AND v.next_due_date <= DATE_ADD(NOW(), INTERVAL 30 DAY)
        ');
        $stmt->execute([$pet_id]);
        $vaccinations = $stmt->fetchAll();
        
        $results = [];
        foreach ($vaccinations as $vaccination) {
            $data = [
                'owner_name' => $vaccination['owner_name'],
                'pet_name' => $vaccination['pet_name'],
                'vaccine_name' => $vaccination['vaccine_name'],
                'due_date' => $vaccination['next_due_date']
            ];
            
            $results[] = [
                'vaccination' => $vaccination['vaccine_name'],
                'email' => $this->sendEmail(
                    $vaccination['email'],
                    'Vaccination Due - ' . $vaccination['pet_name'],
                    '',
                    'vaccination_reminder',
                    $data
                ),
                'sms' => $this->sendSMS(
                    $vaccination['phone'],
                    '',
                    'vaccination_reminder_sms',
                    $data
                )
            ];
        }
        
        return $results;
    }
    
    public function sendEmergencyAlert($pet_id, $emergency_type, $details) {
        $stmt = $this->pdo->prepare('
            SELECT p.name as pet_name, u.name as owner_name, u.email, u.phone
            FROM pets p
            JOIN users u ON p.owner_id = u.id
            WHERE p.id = ?
        ');
        $stmt->execute([$pet_id]);
        $pet = $stmt->fetch();
        
        if (!$pet) {
            return ['success' => false, 'message' => 'Pet not found'];
        }
        
        $data = [
            'owner_name' => $pet['owner_name'],
            'pet_name' => $pet['pet_name'],
            'emergency_type' => $emergency_type,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Send urgent notifications
        $emailResult = $this->sendEmail(
            $pet['email'],
            'URGENT: ' . $emergency_type . ' - ' . $pet['pet_name'],
            '',
            'emergency_alert',
            $data
        );
        
        $smsResult = $this->sendSMS(
            $pet['phone'],
            '',
            'emergency_alert_sms',
            $data
        );
        
        return [
            'success' => $emailResult['success'] || $smsResult['success'],
            'email' => $emailResult,
            'sms' => $smsResult
        ];
    }
    
    public function sendWelcomeMessage($user_id) {
        $stmt = $this->pdo->prepare('
            SELECT name, email, phone FROM users WHERE id = ?
        ');
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        $data = [
            'name' => $user['name'],
            'welcome_date' => date('Y-m-d')
        ];
        
        $emailResult = $this->sendEmail(
            $user['email'],
            'Welcome to Purrfect Pet Care!',
            '',
            'welcome_message',
            $data
        );
        
        if ($user['phone']) {
            $smsResult = $this->sendSMS(
                $user['phone'],
                '',
                'welcome_message_sms',
                $data
            );
        }
        
        return [
            'success' => $emailResult['success'],
            'email' => $emailResult,
            'sms' => $smsResult ?? ['success' => false, 'message' => 'No phone number']
        ];
    }
    
    private function renderTemplate($template, $data) {
        $template_path = "templates/{$template}.html";
        
        if (!file_exists($template_path)) {
            return $this->getDefaultTemplate($template, $data);
        }
        
        $content = file_get_contents($template_path);
        
        // Replace placeholders with data
        foreach ($data as $key => $value) {
            $content = str_replace("{{" . $key . "}}", $value, $content);
        }
        
        return $content;
    }
    
    private function getDefaultTemplate($template, $data) {
        switch ($template) {
            case 'appointment_reminder':
                return "
                    <h2>Appointment Reminder</h2>
                    <p>Dear {{owner_name}},</p>
                    <p>This is a reminder for your appointment with {{pet_name}} on {{appointment_date}} at {{appointment_time}}.</p>
                    <p>Service: {{service}}</p>
                    <p>Please arrive 10 minutes before your scheduled time.</p>
                    <p>Best regards,<br>Purrfect Pet Care Team</p>
                ";
                
            case 'appointment_reminder_sms':
                return "Hi {{owner_name}}, reminder: {{pet_name}} has an appointment on {{appointment_date}} at {{appointment_time}} for {{service}}. Purrfect Pet Care";
                
            case 'vaccination_reminder':
                return "
                    <h2>Vaccination Due</h2>
                    <p>Dear {{owner_name}},</p>
                    <p>{{pet_name}} is due for {{vaccine_name}} vaccination on {{due_date}}.</p>
                    <p>Please schedule an appointment to ensure your pet stays protected.</p>
                    <p>Best regards,<br>Purrfect Pet Care Team</p>
                ";
                
            case 'vaccination_reminder_sms':
                return "Hi {{owner_name}}, {{pet_name}} is due for {{vaccine_name}} on {{due_date}}. Please schedule an appointment. Purrfect Pet Care";
                
            case 'emergency_alert':
                return "
                    <h2>Emergency Alert</h2>
                    <p>Dear {{owner_name}},</p>
                    <p>This is an emergency alert regarding {{pet_name}}.</p>
                    <p>Type: {{emergency_type}}</p>
                    <p>Details: {{details}}</p>
                    <p>Time: {{timestamp}}</p>
                    <p>Please contact us immediately.</p>
                    <p>Emergency: +254 700 123 456</p>
                ";
                
            case 'emergency_alert_sms':
                return "EMERGENCY: {{pet_name}} - {{emergency_type}}. {{details}}. Call +254 700 123 456 immediately.";
                
            case 'welcome_message':
                return "
                    <h2>Welcome to Purrfect Pet Care!</h2>
                    <p>Dear {{name}},</p>
                    <p>Welcome to our pet care family! We're excited to provide the best care for your furry friends.</p>
                    <p>Join date: {{welcome_date}}</p>
                    <p>Best regards,<br>Purrfect Pet Care Team</p>
                ";
                
            case 'welcome_message_sms':
                return "Welcome {{name}} to Purrfect Pet Care! We're excited to care for your pets. Call +254 700 123 456 for appointments.";
                
            default:
                return "Message: " . json_encode($data);
        }
    }
    
    private function logNotification($type, $recipient, $subject, $status, $error = null) {
        $stmt = $this->pdo->prepare('
            INSERT INTO notification_logs 
            (type, recipient, subject, status, error_message, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ');
        $stmt->execute([$type, $recipient, $subject, $status, $error]);
    }
}

// API endpoint handling
$response = ['success' => false];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    $notificationService = new NotificationService($pdo);
    
    switch ($action) {
        case 'send_email':
            $to = $_POST['to'] ?? '';
            $subject = $_POST['subject'] ?? '';
            $message = $_POST['message'] ?? '';
            $template = $_POST['template'] ?? null;
            $data = $_POST['data'] ?? [];
            
            if (!$to || !$subject) {
                throw new Exception('Recipient and subject required');
            }
            
            $result = $notificationService->sendEmail($to, $subject, $message, $template, $data);
            $response = $result;
            break;
            
        case 'send_sms':
            $to = $_POST['to'] ?? '';
            $message = $_POST['message'] ?? '';
            $template = $_POST['template'] ?? null;
            $data = $_POST['data'] ?? [];
            
            if (!$to || !$message) {
                throw new Exception('Recipient and message required');
            }
            
            $result = $notificationService->sendSMS($to, $message, $template, $data);
            $response = $result;
            break;
            
        case 'send_appointment_reminder':
            $appointment_id = $_POST['appointment_id'] ?? '';
            
            if (!$appointment_id) {
                throw new Exception('Appointment ID required');
            }
            
            $result = $notificationService->sendAppointmentReminder($appointment_id);
            $response = $result;
            break;
            
        case 'send_vaccination_reminder':
            $pet_id = $_POST['pet_id'] ?? '';
            
            if (!$pet_id) {
                throw new Exception('Pet ID required');
            }
            
            $result = $notificationService->sendVaccinationReminder($pet_id);
            $response = ['success' => true, 'data' => $result];
            break;
            
        case 'send_emergency_alert':
            $pet_id = $_POST['pet_id'] ?? '';
            $emergency_type = $_POST['emergency_type'] ?? '';
            $details = $_POST['details'] ?? '';
            
            if (!$pet_id || !$emergency_type) {
                throw new Exception('Pet ID and emergency type required');
            }
            
            $result = $notificationService->sendEmergencyAlert($pet_id, $emergency_type, $details);
            $response = $result;
            break;
            
        case 'send_welcome_message':
            $user_id = $_POST['user_id'] ?? '';
            
            if (!$user_id) {
                throw new Exception('User ID required');
            }
            
            $result = $notificationService->sendWelcomeMessage($user_id);
            $response = $result;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
