<?php
// forum.php - Forum-related backend logic

function get_post($pdo, $data) {
    validateInput($data, ['author']);
    $author = $data['author'];
    $stmt = $pdo->prepare("SELECT * FROM forum_posts WHERE author = ?");
    $stmt->execute([$author]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($post) {
        return [
            'success' => true,
            'data' => $post,
            'message' => 'Post data retrieved successfully'
        ];
    } else {
        throw new Exception('Post not found');
    }
}

function approve_post($pdo, $data) {
    validateInput($data, ['author']);
    $author = $data['author'];
    $stmt = $pdo->prepare("UPDATE forum_posts SET status = 'Approved' WHERE author = ?");
    $result = $stmt->execute([$author]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Post approved successfully'
        ];
    } else {
        throw new Exception('Failed to approve post');
    }
}

function reject_post($pdo, $data) {
    validateInput($data, ['author']);
    $author = $data['author'];
    $stmt = $pdo->prepare("UPDATE forum_posts SET status = 'Rejected' WHERE author = ?");
    $result = $stmt->execute([$author]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Post rejected successfully'
        ];
    } else {
        throw new Exception('Failed to reject post');
    }
}

function add_forum_post($pdo, $data) {
    validateInput($data, ['author', 'content']);
    $author = $data['author'];
    $content = $data['content'];
    $date = $data['date'] ?? date('Y-m-d');
    $status = $data['status'] ?? 'Pending';
    $stmt = $pdo->prepare("INSERT INTO forum_posts (author, content, date, status) VALUES (?, ?, ?, ?)");
    $result = $stmt->execute([$author, $content, $date, $status]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Forum post added successfully'
        ];
    } else {
        throw new Exception('Failed to add forum post');
    }
}

function update_forum_post($pdo, $data) {
    validateInput($data, ['id', 'content', 'status']);
    $id = $data['id'];
    $content = $data['content'];
    $status = $data['status'];
    $stmt = $pdo->prepare("UPDATE forum_posts SET content = ?, status = ? WHERE id = ?");
    $result = $stmt->execute([$content, $status, $id]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Forum post updated successfully'
        ];
    } else {
        throw new Exception('Failed to update forum post');
    }
}

function delete_forum_post($pdo, $data) {
    validateInput($data, ['id']);
    $id = $data['id'];
    $stmt = $pdo->prepare("DELETE FROM forum_posts WHERE id = ?");
    $result = $stmt->execute([$id]);
    if ($result) {
        return [
            'success' => true,
            'message' => 'Forum post deleted successfully'
        ];
    } else {
        throw new Exception('Failed to delete forum post');
    }
}

function get_forum_posts($pdo, $data) {
    $stmt = $pdo->prepare("SELECT * FROM forum_posts WHERE status = 'Approved' ORDER BY date DESC");
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return [
        'success' => true,
        'data' => $posts,
        'message' => 'Forum posts retrieved successfully'
    ];
} 