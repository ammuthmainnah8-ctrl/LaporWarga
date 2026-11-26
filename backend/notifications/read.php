
<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_check.php';

// Hanya menerima GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method tidak diizinkan',
        'data' => null
    ]);

    exit;
}

// Warga dan petugas dapat membaca notifikasinya sendiri
requireLogin();

$role = $_SESSION['role'] ?? '';

if (!in_array($role, ['warga', 'petugas'], true)) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Anda tidak memiliki hak akses',
        'data' => null
    ]);

    exit;
}
$userId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$userId || $userId < 1) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Session pengguna tidak valid',
        'data' => null
    ]);

    exit;
}

try {

    // Mengambil notifikasi milik warga
    $sql = "
        SELECT
            notification_id,
            complaint_id,
            title,
            message,
            is_read,
            created_at
        FROM notifications
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT 50
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Menghitung jumlah notifikasi yang belum dibaca
    $countSql = "
        SELECT COUNT(*)
        FROM notifications
        WHERE user_id = :user_id
        AND is_read = 0
    ";

    $countStmt = $pdo->prepare($countSql);

    $countStmt->execute([
        ':user_id' => $userId
    ]);

    $unreadCount = (int) $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'message' => 'Notifikasi berhasil diambil',
        'data' => [
            'unread_count' => $unreadCount,
            'notifications' => $notifications
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat mengambil notifikasi',
        'data' => null
    ]);
}
