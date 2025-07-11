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
            $pet_id = $_POST['pet_id'] ?? $_GET['pet_id'] ?? '';
            
            if (empty($user_id) || empty($role)) throw new Exception('User ID and role required');
            
            if ($role === 'vet') {
                $stmt = $pdo->prepare('
                    SELECT p.*, pt.name as pet_name, u.name as owner_name, v.name as vet_name
                    FROM prescriptions p
                    JOIN pets pt ON p.pet_id = pt.id
                    JOIN users u ON p.owner_id = u.id
                    JOIN users v ON p.vet_id = v.id
                    WHERE p.vet_id = ?
                    ORDER BY p.date DESC
                ');
                $stmt->execute([$user_id]);
            } else {
                if ($pet_id) {
                    $stmt = $pdo->prepare('
                        SELECT p.*, pt.name as pet_name, u.name as owner_name, v.name as vet_name
                        FROM prescriptions p
                        JOIN pets pt ON p.pet_id = pt.id
                        JOIN users u ON p.owner_id = u.id
                        JOIN users v ON p.vet_id = v.id
                        WHERE p.owner_id = ? AND p.pet_id = ?
                        ORDER BY p.date DESC
                    ');
                    $stmt->execute([$user_id, $pet_id]);
                } else {
                    $stmt = $pdo->prepare('
                        SELECT p.*, pt.name as pet_name, u.name as owner_name, v.name as vet_name
                        FROM prescriptions p
                        JOIN pets pt ON p.pet_id = pt.id
                        JOIN users u ON p.owner_id = u.id
                        JOIN users v ON p.vet_id = v.id
                        WHERE p.owner_id = ?
                        ORDER BY p.date DESC
                    ');
                    $stmt->execute([$user_id]);
                }
            }
            
            $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['success'] = true;
            $response['data'] = $prescriptions;
            break;
            
        case 'create':
            $pet_id = $_POST['pet_id'] ?? '';
            $vet_id = $_POST['vet_id'] ?? '';
            $owner_id = $_POST['owner_id'] ?? '';
            $medication = $_POST['medication'] ?? '';
            $dosage = $_POST['dosage'] ?? '';
            $frequency = $_POST['frequency'] ?? '';
            $duration = $_POST['duration'] ?? '';
            $instructions = $_POST['instructions'] ?? '';
            $quantity = $_POST['quantity'] ?? '';
            $refills_allowed = $_POST['refills_allowed'] ?? 0;
            $expiry_date = $_POST['expiry_date'] ?? '';
            $priority = $_POST['priority'] ?? 'normal';
            
            if (!$pet_id || !$vet_id || !$medication || !$dosage || !$duration) {
                throw new Exception('Required fields missing');
            }
            
            // Generate prescription number
            $prescription_number = 'RX_' . date('Ymd') . '_' . uniqid();
            
            $stmt = $pdo->prepare('
                INSERT INTO prescriptions 
                (prescription_number, pet_id, vet_id, owner_id, medication, dosage, frequency, duration, 
                instructions, quantity, refills_allowed, expiry_date, priority, status, date, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ');
            $stmt->execute([
                $prescription_number, $pet_id, $vet_id, $owner_id, $medication, $dosage, $frequency, 
                $duration, $instructions, $quantity, $refills_allowed, $expiry_date, $priority, 'active'
            ]);
            
            $prescription_id = $pdo->lastInsertId();
            
            // Log prescription creation
            logPrescriptionAction($prescription_id, 'created', $vet_id);
            
            $response['success'] = true;
            $response['message'] = 'Prescription created successfully';
            $response['prescription_number'] = $prescription_number;
            $response['prescription_id'] = $prescription_id;
            break;
            
        case 'update':
            $id = $_POST['id'] ?? '';
            $medication = $_POST['medication'] ?? '';
            $dosage = $_POST['dosage'] ?? '';
            $frequency = $_POST['frequency'] ?? '';
            $duration = $_POST['duration'] ?? '';
            $instructions = $_POST['instructions'] ?? '';
            $quantity = $_POST['quantity'] ?? '';
            $refills_allowed = $_POST['refills_allowed'] ?? '';
            $expiry_date = $_POST['expiry_date'] ?? '';
            $priority = $_POST['priority'] ?? '';
            $status = $_POST['status'] ?? '';
            
            if (!$id) throw new Exception('Prescription ID required');
            
            $stmt = $pdo->prepare('
                UPDATE prescriptions 
                SET medication = ?, dosage = ?, frequency = ?, duration = ?, instructions = ?, 
                quantity = ?, refills_allowed = ?, expiry_date = ?, priority = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([
                $medication, $dosage, $frequency, $duration, $instructions, 
                $quantity, $refills_allowed, $expiry_date, $priority, $status, $id
            ]);
            
            // Log prescription update
            logPrescriptionAction($id, 'updated', $_POST['user_id'] ?? '');
            
            $response['success'] = true;
            $response['message'] = 'Prescription updated successfully';
            break;
            
        case 'refill':
            $prescription_id = $_POST['prescription_id'] ?? '';
            $user_id = $_POST['user_id'] ?? '';
            $refill_quantity = $_POST['refill_quantity'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            if (!$prescription_id || !$user_id) {
                throw new Exception('Prescription ID and user ID required');
            }
            
            // Check if refill is allowed
            $stmt = $pdo->prepare('
                SELECT refills_allowed, refills_used, status, expiry_date 
                FROM prescriptions WHERE id = ?
            ');
            $stmt->execute([$prescription_id]);
            $prescription = $stmt->fetch();
            
            if (!$prescription) {
                throw new Exception('Prescription not found');
            }
            
            if ($prescription['status'] !== 'active') {
                throw new Exception('Prescription is not active');
            }
            
            if (strtotime($prescription['expiry_date']) < time()) {
                throw new Exception('Prescription has expired');
            }
            
            if ($prescription['refills_used'] >= $prescription['refills_allowed']) {
                throw new Exception('No refills remaining');
            }
            
            // Update refill count
            $stmt = $pdo->prepare('
                UPDATE prescriptions 
                SET refills_used = refills_used + 1, last_refill_date = NOW(), updated_at = NOW()
                WHERE id = ?
            ');
            $stmt->execute([$prescription_id]);
            
            // Log refill
            logPrescriptionAction($prescription_id, 'refilled', $user_id, $notes);
            
            $response['success'] = true;
            $response['message'] = 'Prescription refilled successfully';
            break;
            
        case 'dispense':
            $prescription_id = $_POST['prescription_id'] ?? '';
            $user_id = $_POST['user_id'] ?? '';
            $quantity_dispensed = $_POST['quantity_dispensed'] ?? '';
            $pharmacy_notes = $_POST['pharmacy_notes'] ?? '';
            
            if (!$prescription_id || !$user_id || !$quantity_dispensed) {
                throw new Exception('Prescription ID, user ID, and quantity required');
            }
            
            // Record dispense
            $stmt = $pdo->prepare('
                INSERT INTO prescription_dispenses 
                (prescription_id, dispensed_by, quantity_dispensed, pharmacy_notes, dispense_date)
                VALUES (?, ?, ?, ?, NOW())
            ');
            $stmt->execute([$prescription_id, $user_id, $quantity_dispensed, $pharmacy_notes]);
            
            // Log dispense
            logPrescriptionAction($prescription_id, 'dispensed', $user_id, $pharmacy_notes);
            
            $response['success'] = true;
            $response['message'] = 'Prescription dispensed successfully';
            break;
            
        case 'delete':
            $id = $_POST['id'] ?? '';
            if (!$id) throw new Exception('Prescription ID required');
            
            $stmt = $pdo->prepare('DELETE FROM prescriptions WHERE id = ?');
            $stmt->execute([$id]);
            
            $response['success'] = true;
            $response['message'] = 'Prescription deleted successfully';
            break;
            
        case 'get_medication_history':
            $pet_id = $_POST['pet_id'] ?? $_GET['pet_id'] ?? '';
            
            if (!$pet_id) throw new Exception('Pet ID required');
            
            $stmt = $pdo->prepare('
                SELECT p.*, pd.dispense_date, pd.quantity_dispensed, pd.pharmacy_notes
                FROM prescriptions p
                LEFT JOIN prescription_dispenses pd ON p.id = pd.prescription_id
                WHERE p.pet_id = ?
                ORDER BY p.date DESC, pd.dispense_date DESC
            ');
            $stmt->execute([$pet_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['data'] = $history;
            break;
            
        case 'search_medications':
            $search_term = $_POST['search_term'] ?? $_GET['search_term'] ?? '';
            
            if (!$search_term) {
                throw new Exception('Search term required');
            }
            
            // This would typically query a medication database
            // For demo purposes, return sample data
            $medications = [
                ['name' => 'Amoxicillin', 'dosage_forms' => ['250mg', '500mg'], 'category' => 'Antibiotic'],
                ['name' => 'Rimadyl', 'dosage_forms' => ['25mg', '50mg', '100mg'], 'category' => 'Pain Relief'],
                ['name' => 'Heartgard', 'dosage_forms' => ['30mg', '60mg'], 'category' => 'Heartworm Prevention'],
                ['name' => 'Frontline', 'dosage_forms' => ['Small', 'Medium', 'Large'], 'category' => 'Flea & Tick'],
            ];
            
            $filtered = array_filter($medications, function($med) use ($search_term) {
                return stripos($med['name'], $search_term) !== false;
            });
            
            $response['success'] = true;
            $response['data'] = array_values($filtered);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response); 

// Helper functions
function logPrescriptionAction($prescription_id, $action, $user_id, $notes = '') {
    global $pdo;
    
    $stmt = $pdo->prepare('
        INSERT INTO prescription_logs 
        (prescription_id, action, user_id, notes, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ');
    $stmt->execute([$prescription_id, $action, $user_id, $notes]);
}
