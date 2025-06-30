<?php
// pets.php - Pet-related backend logic

function get_pet($pdo, $data) {
    validateInput($data, ['name']);
    $name = $data['name'];
    $stmt = $pdo->prepare("SELECT * FROM pets WHERE name = ?");
    $stmt->execute([$name]);
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pet) {
        return [
            'success' => true,
            'data' => $pet,
            'message' => 'Pet data retrieved successfully'
        ];
    } else {
        throw new Exception('Pet not found');
    }
}

function get_pet_details($pdo, $data) {
    validateInput($data, ['name']);
    $name = $data['name'];
    $stmt = $pdo->prepare("SELECT * FROM pets WHERE name = ?");
    $stmt->execute([$name]);
    $pet = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($pet) {
        $stmt = $pdo->prepare("SELECT * FROM medical_records WHERE pet_name = ? ORDER BY date DESC LIMIT 5");
        $stmt->execute([$name]);
        $medicalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pet['medical_records'] = $medicalRecords;
        return [
            'success' => true,
            'data' => $pet,
            'message' => 'Pet details retrieved successfully'
        ];
    } else {
        throw new Exception('Pet not found');
    }
}

function update_pet($pdo, $data) {
    validateInput($data, ['name', 'type', 'owner', 'status']);
    $name = $data['name'];
    $type = $data['type'];
    $breed = $data['breed'] ?? '';
    $age = $data['age'] ?? '';
    $owner = $data['owner'];
    $status = $data['status'];
    $stmt = $pdo->prepare("UPDATE pets SET type = ?, breed = ?, age = ?, owner = ?, status = ? WHERE name = ?");
    $result = $stmt->execute([$type, $breed, $age, $owner, $status, $name]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Pet updated successfully'
        ];
    } else {
        throw new Exception('Failed to update pet');
    }
}

function delete_pet($pdo, $data) {
    validateInput($data, ['name']);
    $name = $data['name'];
    $stmt = $pdo->prepare("DELETE FROM pets WHERE name = ?");
    $result = $stmt->execute([$name]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Pet deleted successfully'
        ];
    } else {
        throw new Exception('Failed to delete pet');
    }
}

function get_pets($pdo, $data) {
    validateInput($data, ['user_id']);
    $user_id = $data['user_id'];
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if ($user) {
        $stmt = $pdo->prepare("SELECT * FROM pets WHERE owner = ? ORDER BY name");
        $stmt->execute([$user['name']]);
        $pets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return [
            'success' => true,
            'data' => $pets,
            'message' => 'Pets retrieved successfully'
        ];
    } else {
        throw new Exception('User not found');
    }
}

function add_pet($pdo, $data) {
    validateInput($data, ['name', 'type', 'owner_id']);
    $name = $data['name'];
    $type = $data['type'];
    $breed = $data['breed'] ?? '';
    $age = $data['age'] ?? '';
    $owner_id = $data['owner_id'];
    $status = $data['status'] ?? 'Active';
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$owner_id]);
    $user = $stmt->fetch();
    if ($user) {
        $stmt = $pdo->prepare("INSERT INTO pets (name, type, breed, age, owner, status) VALUES (?, ?, ?, ?, ?, ?)");
        $result = $stmt->execute([$name, $type, $breed, $age, $user['name'], $status]);
        if ($result) {
            return [
                'success' => true,
                'message' => 'Pet added successfully'
            ];
        } else {
            throw new Exception('Failed to add pet');
        }
    } else {
        throw new Exception('User not found');
    }
}

function get_enhanced_pet_details($pdo, $data) {
    // ... logic from api.php for get_enhanced_pet_details ...
}

function upload_pet_photo($pdo, $data) {
    // ... logic from api.php for upload_pet_photo ...
}

function delete_pet_photo($pdo, $data) {
    // ... logic from api.php for delete_pet_photo ...
}

function set_primary_photo($pdo, $data) {
    // ... logic from api.php for set_primary_photo ...
}

function add_medical_record($pdo, $data) {
    // ... logic from api.php for add_medical_record ...
}

function add_vaccination($pdo, $data) {
    // ... logic from api.php for add_vaccination ...
}

function add_health_record($pdo, $data) {
    // ... logic from api.php for add_health_record ...
}

function generate_pet_health_report($pdo, $data) {
    // ... logic from api.php for generate_pet_health_report ...
} 