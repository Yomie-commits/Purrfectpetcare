<?php
// payments.php - Payment-related backend logic

function get_payment($pdo, $data) {
    validateInput($data, ['date']);
    $date = $data['date'];
    $stmt = $pdo->prepare("SELECT * FROM payments WHERE date = ?");
    $stmt->execute([$date]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($payment) {
        return [
            'success' => true,
            'data' => $payment,
            'message' => 'Payment data retrieved successfully'
        ];
    } else {
        throw new Exception('Payment not found');
    }
}

function add_payment($pdo, $data) {
    validateInput($data, ['user', 'amount', 'method']);
    $user = $data['user'];
    $amount = $data['amount'];
    $method = $data['method'];
    $description = $data['description'] ?? '';
    $reference = $data['reference'] ?? '';
    $transactionId = strtoupper($method) . date('YmdHis') . rand(1000, 9999);
    $stmt = $pdo->prepare("INSERT INTO payments (user, amount, method, status, date, transaction_id, reference, description) VALUES (?, ?, ?, 'Completed', CURDATE(), ?, ?, ?)");
    $result = $stmt->execute([$user, $amount, $method, $transactionId, $reference, $description]);
    if ($result) {
        $paymentId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        return [
            'success' => true,
            'data' => $payment,
            'message' => 'Payment processed successfully'
        ];
    } else {
        throw new Exception('Failed to process payment');
    }
}

function update_payment($pdo, $data) {
    validateInput($data, ['id', 'status']);
    $id = $data['id'];
    $status = $data['status'];
    $transaction_id = $data['transaction_id'] ?? '';
    $reference = $data['reference'] ?? '';
    $description = $data['description'] ?? '';
    $stmt = $pdo->prepare("UPDATE payments SET status = ?, transaction_id = ?, reference = ?, description = ? WHERE id = ?");
    $result = $stmt->execute([$status, $transaction_id, $reference, $description, $id]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Payment updated successfully'
        ];
    } else {
        throw new Exception('Failed to update payment');
    }
}

function delete_payment($pdo, $data) {
    validateInput($data, ['id']);
    $id = $data['id'];
    $stmt = $pdo->prepare("DELETE FROM payments WHERE id = ?");
    $result = $stmt->execute([$id]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Payment deleted successfully'
        ];
    } else {
        throw new Exception('Failed to delete payment');
    }
}

function get_payments($pdo, $data) {
    validateInput($data, ['user_id']);
    $user_id = $data['user_id'];
    $role = $data['role'] ?? '';
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if ($user) {
        if ($role === 'owner') {
            $stmt = $pdo->prepare("SELECT * FROM payments WHERE user = ? ORDER BY date DESC");
            $stmt->execute([$user['name']]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM payments ORDER BY date DESC");
            $stmt->execute();
        }
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'success' => true,
            'data' => $payments,
            'message' => 'Payments retrieved successfully'
        ];
    } else {
        throw new Exception('User not found');
    }
}

function get_revenue_analytics($pdo, $data) {
    $adminId = $data['admin_id'] ?? '';
    $period = $data['period'] ?? 'month';
    checkUserRole('admin', $adminId);
    $dataArr = [];
    if ($period === 'week') {
        $stmt = $pdo->query("SELECT DATE(date) as period, SUM(amount) as amount FROM payments WHERE date >= DATE_SUB(NOW(), INTERVAL 1 WEEK) AND status = 'Completed' GROUP BY DATE(date) ORDER BY date");
    } elseif ($period === 'year') {
        $stmt = $pdo->query("SELECT DATE_FORMAT(date, '%Y-%m') as period, SUM(amount) as amount FROM payments WHERE date >= DATE_SUB(NOW(), INTERVAL 1 YEAR) AND status = 'Completed' GROUP BY DATE_FORMAT(date, '%Y-%m') ORDER BY date");
    } else {
        $stmt = $pdo->query("SELECT DATE_FORMAT(date, '%Y-%m-%d') as period, SUM(amount) as amount FROM payments WHERE date >= DATE_SUB(NOW(), INTERVAL 1 MONTH) AND status = 'Completed' GROUP BY DATE(date) ORDER BY date");
    }
    $dataArr = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return [
        'success' => true,
        'data' => $dataArr,
        'message' => 'Revenue analytics retrieved successfully'
    ];
} 