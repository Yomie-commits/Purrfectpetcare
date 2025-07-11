<?php
header('Content-Type: application/json');
require_once 'db_connect.php';

$response = ['success' => false];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Configuration
$upload_dir = 'uploads/';
$allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$max_file_size = 10 * 1024 * 1024; // 10MB
$max_dimensions = ['width' => 4000, 'height' => 4000];
$thumbnail_sizes = [
    'small' => ['width' => 150, 'height' => 150],
    'medium' => ['width' => 300, 'height' => 300],
    'large' => ['width' => 800, 'height' => 800]
];

try {
    switch ($action) {
        case 'upload_pet_photo':
            $pet_id = $_POST['pet_id'] ?? '';
            $user_id = $_POST['user_id'] ?? '';
            $photo_type = $_POST['photo_type'] ?? 'general'; // general, medical, before_after
            
            if (!$pet_id || !$user_id || !isset($_FILES['photo'])) {
                throw new Exception('Pet ID, user ID, and photo file required');
            }
            
            $file = $_FILES['photo'];
            
            // Validate file
            $validation = validateFile($file);
            if (!$validation['valid']) {
                throw new Exception($validation['error']);
            }
            
            // Process and save image
            $result = processAndSaveImage($file, $pet_id, $photo_type);
            
            if ($result['success']) {
                // Save to database
                $stmt = $pdo->prepare('
                    INSERT INTO pet_photos 
                    (pet_id, user_id, original_url, thumbnail_url, photo_type, file_size, dimensions, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ');
                $stmt->execute([
                    $pet_id, $user_id, $result['original_url'], $result['thumbnail_url'],
                    $photo_type, $result['file_size'], $result['dimensions']
                ]);
                
                $photo_id = $pdo->lastInsertId();
                
                // Log upload
                logFileUpload($photo_id, $user_id, 'pet_photo', $result['file_size']);
                
                $response['success'] = true;
                $response['message'] = 'Photo uploaded successfully';
                $response['photo_id'] = $photo_id;
                $response['urls'] = [
                    'original' => $result['original_url'],
                    'thumbnail' => $result['thumbnail_url']
                ];
            } else {
                throw new Exception('Failed to process image');
            }
            break;
            
        case 'upload_medical_document':
            $pet_id = $_POST['pet_id'] ?? '';
            $user_id = $_POST['user_id'] ?? '';
            $document_type = $_POST['document_type'] ?? ''; // xray, lab_result, prescription, etc.
            $description = $_POST['description'] ?? '';
            
            if (!$pet_id || !$user_id || !isset($_FILES['document'])) {
                throw new Exception('Pet ID, user ID, and document file required');
            }
            
            $file = $_FILES['document'];
            
            // Validate document
            $validation = validateDocument($file);
            if (!$validation['valid']) {
                throw new Exception($validation['error']);
            }
            
            // Save document
            $result = saveDocument($file, $pet_id, $document_type);
            
            if ($result['success']) {
                // Save to database
                $stmt = $pdo->prepare('
                    INSERT INTO medical_documents 
                    (pet_id, user_id, document_type, file_url, description, file_size, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ');
                $stmt->execute([
                    $pet_id, $user_id, $document_type, $result['file_url'],
                    $description, $result['file_size']
                ]);
                
                $document_id = $pdo->lastInsertId();
                
                // Log upload
                logFileUpload($document_id, $user_id, 'medical_document', $result['file_size']);
                
                $response['success'] = true;
                $response['message'] = 'Document uploaded successfully';
                $response['document_id'] = $document_id;
                $response['file_url'] = $result['file_url'];
            } else {
                throw new Exception('Failed to save document');
            }
            break;
            
        case 'delete_file':
            $file_id = $_POST['file_id'] ?? '';
            $file_type = $_POST['file_type'] ?? ''; // pet_photo, medical_document
            $user_id = $_POST['user_id'] ?? '';
            
            if (!$file_id || !$file_type || !$user_id) {
                throw new Exception('File ID, file type, and user ID required');
            }
            
            // Get file details
            if ($file_type === 'pet_photo') {
                $stmt = $pdo->prepare('SELECT * FROM pet_photos WHERE id = ? AND user_id = ?');
            } else {
                $stmt = $pdo->prepare('SELECT * FROM medical_documents WHERE id = ? AND user_id = ?');
            }
            $stmt->execute([$file_id, $user_id]);
            $file = $stmt->fetch();
            
            if (!$file) {
                throw new Exception('File not found or access denied');
            }
            
            // Delete physical files
            if ($file_type === 'pet_photo') {
                deleteImageFiles($file['original_url'], $file['thumbnail_url']);
            } else {
                deleteDocumentFile($file['file_url']);
            }
            
            // Delete from database
            if ($file_type === 'pet_photo') {
                $stmt = $pdo->prepare('DELETE FROM pet_photos WHERE id = ?');
            } else {
                $stmt = $pdo->prepare('DELETE FROM medical_documents WHERE id = ?');
            }
            $stmt->execute([$file_id]);
            
            $response['success'] = true;
            $response['message'] = 'File deleted successfully';
            break;
            
        case 'get_pet_photos':
            $pet_id = $_POST['pet_id'] ?? $_GET['pet_id'] ?? '';
            
            if (!$pet_id) {
                throw new Exception('Pet ID required');
            }
            
            $stmt = $pdo->prepare('
                SELECT * FROM pet_photos 
                WHERE pet_id = ? 
                ORDER BY created_at DESC
            ');
            $stmt->execute([$pet_id]);
            $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['data'] = $photos;
            break;
            
        case 'get_medical_documents':
            $pet_id = $_POST['pet_id'] ?? $_GET['pet_id'] ?? '';
            
            if (!$pet_id) {
                throw new Exception('Pet ID required');
            }
            
            $stmt = $pdo->prepare('
                SELECT * FROM medical_documents 
                WHERE pet_id = ? 
                ORDER BY created_at DESC
            ');
            $stmt->execute([$pet_id]);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $response['success'] = true;
            $response['data'] = $documents;
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);

// Helper functions
function validateFile($file) {
    global $allowed_types, $max_file_size;
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'File upload error: ' . $file['error']];
    }
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['valid' => false, 'error' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
    }
    
    if ($file['size'] > $max_file_size) {
        return ['valid' => false, 'error' => 'File too large. Maximum size: ' . formatBytes($max_file_size)];
    }
    
    return ['valid' => true];
}

function validateDocument($file) {
    $allowed_doc_types = [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    $max_doc_size = 50 * 1024 * 1024; // 50MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'File upload error: ' . $file['error']];
    }
    
    if (!in_array($file['type'], $allowed_doc_types)) {
        return ['valid' => false, 'error' => 'Invalid document type'];
    }
    
    if ($file['size'] > $max_doc_size) {
        return ['valid' => false, 'error' => 'Document too large. Maximum size: ' . formatBytes($max_doc_size)];
    }
    
    return ['valid' => true];
}

function processAndSaveImage($file, $pet_id, $photo_type) {
    global $upload_dir, $thumbnail_sizes;
    
    // Create directories
    $pet_dir = $upload_dir . 'pets/' . $pet_id . '/';
    $original_dir = $pet_dir . 'original/';
    $thumbnail_dir = $pet_dir . 'thumbnails/';
    
    createDirectories([$pet_dir, $original_dir, $thumbnail_dir]);
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    
    // Save original
    $original_path = $original_dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $original_path)) {
        return ['success' => false, 'error' => 'Failed to save original image'];
    }
    
    // Get image info
    $image_info = getimagesize($original_path);
    $dimensions = $image_info[0] . 'x' . $image_info[1];
    
    // Create thumbnails
    $thumbnail_filename = 'thumb_' . $filename;
    $thumbnail_path = $thumbnail_dir . $thumbnail_filename;
    
    if (!createThumbnail($original_path, $thumbnail_path, $thumbnail_sizes['medium'])) {
        return ['success' => false, 'error' => 'Failed to create thumbnail'];
    }
    
    return [
        'success' => true,
        'original_url' => $original_path,
        'thumbnail_url' => $thumbnail_path,
        'file_size' => $file['size'],
        'dimensions' => $dimensions
    ];
}

function saveDocument($file, $pet_id, $document_type) {
    global $upload_dir;
    
    // Create directory
    $doc_dir = $upload_dir . 'documents/' . $pet_id . '/' . $document_type . '/';
    createDirectories([$doc_dir]);
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    
    // Save file
    $file_path = $doc_dir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
        return ['success' => false, 'error' => 'Failed to save document'];
    }
    
    return [
        'success' => true,
        'file_url' => $file_path,
        'file_size' => $file['size']
    ];
}

function createThumbnail($source_path, $dest_path, $size) {
    $image_info = getimagesize($source_path);
    $source_width = $image_info[0];
    $source_height = $image_info[1];
    $source_type = $image_info[2];
    
    // Calculate new dimensions
    $ratio = min($size['width'] / $source_width, $size['height'] / $source_height);
    $new_width = round($source_width * $ratio);
    $new_height = round($source_height * $ratio);
    
    // Create image resource
    switch ($source_type) {
        case IMAGETYPE_JPEG:
            $source = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }
    
    // Create thumbnail
    $thumbnail = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $new_width, $new_height, $source_width, $source_height);
    
    // Save thumbnail
    $result = imagejpeg($thumbnail, $dest_path, 85);
    
    // Clean up
    imagedestroy($source);
    imagedestroy($thumbnail);
    
    return $result;
}

function createDirectories($dirs) {
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function deleteImageFiles($original_path, $thumbnail_path) {
    if (file_exists($original_path)) {
        unlink($original_path);
    }
    if (file_exists($thumbnail_path)) {
        unlink($thumbnail_path);
    }
}

function deleteDocumentFile($file_path) {
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

function logFileUpload($file_id, $user_id, $file_type, $file_size) {
    global $pdo;
    
    $stmt = $pdo->prepare('
        INSERT INTO file_upload_logs 
        (file_id, user_id, file_type, file_size, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ');
    $stmt->execute([$file_id, $user_id, $file_type, $file_size]);
}

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}
