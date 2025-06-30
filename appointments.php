<?php
// appointments.php - Appointment-related backend logic

function get_appointment($pdo, $data) {
    validateInput($data, ['date']);
    $date = $data['date'];
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE date = ?");
    $stmt->execute([$date]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($appointment) {
        return [
            'success' => true,
            'data' => $appointment,
            'message' => 'Appointment data retrieved successfully'
        ];
    } else {
        throw new Exception('Appointment not found');
    }
}

function update_appointment($pdo, $data) {
    validateInput($data, ['date', 'new_date', 'time', 'pet', 'owner', 'vet', 'status']);
    $date = $data['date'];
    $newDate = $data['new_date'];
    $time = $data['time'];
    $pet = $data['pet'];
    $owner = $data['owner'];
    $vet = $data['vet'];
    $status = $data['status'];
    $notes = $data['notes'] ?? '';
    $stmt = $pdo->prepare("UPDATE appointments SET date = ?, time = ?, pet = ?, owner = ?, vet = ?, status = ?, notes = ? WHERE date = ?");
    $result = $stmt->execute([$newDate, $time, $pet, $owner, $vet, $status, $notes, $date]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Appointment updated successfully'
        ];
    } else {
        throw new Exception('Failed to update appointment');
    }
}

function cancel_appointment($pdo, $data) {
    validateInput($data, ['id']);
    $id = $data['id'];
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ?");
    $result = $stmt->execute([$id]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Appointment cancelled successfully'
        ];
    } else {
        throw new Exception('Failed to cancel appointment');
    }
}

function start_appointment($pdo, $data) {
    validateInput($data, ['id']);
    $id = $data['id'];
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'In Progress' WHERE id = ?");
    $result = $stmt->execute([$id]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Appointment started successfully'
        ];
    } else {
        throw new Exception('Failed to start appointment');
    }
}

function complete_appointment($pdo, $data) {
    validateInput($data, ['id']);
    $id = $data['id'];
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ?");
    $result = $stmt->execute([$id]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Appointment completed successfully'
        ];
    } else {
        throw new Exception('Failed to complete appointment');
    }
}

function get_appointments($pdo, $data) {
    validateInput($data, ['user_id']);
    $user_id = $data['user_id'];
    $role = $data['role'] ?? '';
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if ($user) {
        if ($role === 'owner') {
            $stmt = $pdo->prepare("SELECT * FROM appointments WHERE owner = ? ORDER BY date DESC, time DESC");
            $stmt->execute([$user['name']]);
        } elseif ($role === 'vet') {
            $stmt = $pdo->prepare("SELECT * FROM appointments WHERE vet = ? ORDER BY date DESC, time DESC");
            $stmt->execute([$user['name']]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM appointments ORDER BY date DESC, time DESC");
            $stmt->execute();
        }
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'success' => true,
            'data' => $appointments,
            'message' => 'Appointments retrieved successfully'
        ];
    } else {
        throw new Exception('User not found');
    }
}

function add_appointment($pdo, $data) {
    validateInput($data, ['date', 'time', 'pet_name', 'owner_id']);
    $date = $data['date'];
    $time = $data['time'];
    $pet_name = $data['pet_name'];
    $owner_id = $data['owner_id'];
    $vet_name = $data['vet_name'] ?? '';
    $purpose = $data['purpose'] ?? '';
    $status = $data['status'] ?? 'Scheduled';
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$owner_id]);
    $user = $stmt->fetch();
    if ($user) {
        $stmt = $pdo->prepare("INSERT INTO appointments (date, time, pet, owner, vet, purpose, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$date, $time, $pet_name, $user['name'], $vet_name, $purpose, $status]);
        if ($result) {
            return [
                'success' => true,
                'message' => 'Appointment added successfully'
            ];
        } else {
            throw new Exception('Failed to add appointment');
        }
    } else {
        throw new Exception('User not found');
    }
}

function get_appointments_analytics($pdo, $data) {
    $adminId = $data['admin_id'] ?? '';
    $period = $data['period'] ?? 'month';
    checkUserRole('admin', $adminId);
    $dataArr = [];
    if ($period === 'week') {
        $stmt = $pdo->query("SELECT DATE(date) as period, COUNT(*) as count FROM appointments WHERE date >= DATE_SUB(NOW(), INTERVAL 1 WEEK) GROUP BY DATE(date) ORDER BY date");
    } elseif ($period === 'year') {
        $stmt = $pdo->query("SELECT DATE_FORMAT(date, '%Y-%m') as period, COUNT(*) as count FROM appointments WHERE date >= DATE_SUB(NOW(), INTERVAL 1 YEAR) GROUP BY DATE_FORMAT(date, '%Y-%m') ORDER BY date");
    } else {
        $stmt = $pdo->query("SELECT DATE_FORMAT(date, '%Y-%m-%d') as period, COUNT(*) as count FROM appointments WHERE date >= DATE_SUB(NOW(), INTERVAL 1 MONTH) GROUP BY DATE(date) ORDER BY date");
    }
    $dataArr = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return [
        'success' => true,
        'data' => $dataArr,
        'message' => 'Appointments analytics retrieved successfully'
    ];
} 