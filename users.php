<?php
// users.php - User-related backend logic

function login($pdo, $data) {
    validateInput($data, ['email', 'password']);
    $email = $data['email'];
    $password = $data['password'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'Active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        return [
            'success' => true,
            'data' => $user,
            'message' => 'Login successful'
        ];
    } else {
        throw new Exception('Invalid email or password');
    }
}

function register($pdo, $data) {
    validateInput($data, ['name', 'email', 'password']);
    $name = $data['name'];
    $email = $data['email'];
    $password = $data['password'];
    $role = $data['role'] ?? 'owner';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email already exists');
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'Active')");
    $result = $stmt->execute([$name, $email, $hashedPassword, $role]);
    if ($result) {
        $userId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        return [
            'success' => true,
            'data' => $user,
            'message' => 'Registration successful'
        ];
    } else {
        throw new Exception('Failed to register user');
    }
}

function get_user($pdo, $data) {
    validateInput($data, ['email']);
    $email = $data['email'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        return [
            'success' => true,
            'data' => $user,
            'message' => 'User data retrieved successfully'
        ];
    } else {
        throw new Exception('User not found');
    }
}

function update_user($pdo, $data) {
    validateInput($data, ['email', 'name', 'role', 'status']);
    $email = $data['email'];
    $name = $data['name'];
    $role = $data['role'];
    $status = $data['status'];
    $stmt = $pdo->prepare("UPDATE users SET name = ?, role = ?, status = ? WHERE email = ?");
    $result = $stmt->execute([$name, $role, $status, $email]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'User updated successfully'
        ];
    } else {
        throw new Exception('Failed to update user');
    }
}

function toggle_user_status($pdo, $data) {
    validateInput($data, ['email']);
    $email = $data['email'];
    $stmt = $pdo->prepare("SELECT status FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $newStatus = $user['status'] === 'Active' ? 'Inactive' : 'Active';
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE email = ?");
        $result = $stmt->execute([$newStatus, $email]);
        if ($result) {
            return [
                'success' => true,
                'message' => "User status changed to $newStatus"
            ];
        } else {
            throw new Exception('Failed to update user status');
        }
    } else {
        throw new Exception('User not found');
    }
}

function delete_user($pdo, $data) {
    validateInput($data, ['email']);
    $email = $data['email'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
    $result = $stmt->execute([$email]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'User deleted successfully'
        ];
    } else {
        throw new Exception('Failed to delete user');
    }
}

function add_user($pdo, $data) {
    validateInput($data, ['admin_id', 'name', 'email', 'password', 'role']);
    checkUserRole('admin', $data['admin_id']);
    $name = $data['name'];
    $email = $data['email'];
    $password = $data['password'];
    $role = $data['role'];
    $status = $data['status'] ?? 'Active';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        throw new Exception('Email already exists');
    }
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, ?)");
    $result = $stmt->execute([$name, $email, $hashedPassword, $role, $status]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'User added successfully'
        ];
    } else {
        throw new Exception('Failed to add user');
    }
}

function get_users($pdo, $data) {
    validateInput($data, ['user_id']);
    checkUserRole('admin', $data['user_id']);
    $stmt = $pdo->prepare("SELECT * FROM users ORDER BY name");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return [
        'success' => true,
        'data' => $users,
        'message' => 'Users retrieved successfully'
    ];
}

function delete_account($pdo, $data) {
    validateInput($data, ['user_id', 'email']);
    $userId = $data['user_id'];
    $email = $data['email'];
    $reason = $data['reason'] ?? '';
    $otherReason = $data['other_reason'] ?? '';
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND email = ?");
    $stmt->execute([$userId, $email]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid user or email');
    }
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $result = $stmt->execute([$userId]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Account deleted successfully'
        ];
    } else {
        throw new Exception('Failed to delete account');
    }
}

function forgot_password($pdo, $data) {
    validateInput($data, ['email']);
    $email = $data['email'];
    $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'Active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $resetToken = bin2hex(random_bytes(32));
        $resetExpiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE email = ?");
        $result = $stmt->execute([$resetToken, $resetExpiry, $email]);
        if ($result) {
            return [
                'success' => true,
                'message' => 'Password reset instructions have been sent to your email',
                'data' => ['reset_token' => $resetToken] // Remove this in production
            ];
        } else {
            throw new Exception('Failed to process password reset request');
        }
    } else {
        return [
            'success' => true,
            'message' => 'If the email exists, password reset instructions have been sent'
        ];
    }
}

function reset_password($pdo, $data) {
    validateInput($data, ['token', 'new_password']);
    $token = $data['token'];
    $newPassword = $data['new_password'];
    $stmt = $pdo->prepare("SELECT id, email FROM users WHERE reset_token = ? AND reset_expiry > NOW() AND status = 'Active'");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
        $result = $stmt->execute([$hashedPassword, $user['id']]);
        if ($result) {
            return [
                'success' => true,
                'message' => 'Password has been reset successfully'
            ];
        } else {
            throw new Exception('Failed to reset password');
        }
    } else {
        throw new Exception('Invalid or expired reset token');
    }
} 