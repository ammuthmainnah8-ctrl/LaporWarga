
<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_check.php';

// Wajib login
requireLogin();

try {
    $stmt = $pdo->prepare("
        SELECT user_id, name, username, email, role
        FROM users
        WHERE user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $_SESSION['user_id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Data pengguna tidak ditemukan',
            'data' => null
        ]);

        exit;
    }

    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'Profil berhasil ditampilkan',
        'data' => [
            'user_id' => (int) $user['user_id'],
            'name' => $user['name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);

} catch (Throwable $e) {
    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat mengambil profil',
        'data' => null
    ]);
}
