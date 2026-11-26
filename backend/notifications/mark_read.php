
<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_check.php';

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method tidak diizinkan',
        'data' => null
    ]);

    exit;
}

// Warga dan petugas dapat menandai notifikasinya sendiri
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

// Ambil ID notifikasi
$notificationId = filter_input(
    INPUT_POST,
    'notification_id',
    FILTER_VALIDATE_INT
);

if (!$notificationId || $notificationId < 1) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'ID notifikasi tidak valid',
        'data' => null
    ]);

    exit;
}

try {

    // Pastikan notifikasi milik warga yang sedang login
    $checkSql = "
        SELECT notification_id
        FROM notifications
        WHERE notification_id = :notification_id
        AND user_id = :user_id
    ";

    $checkStmt = $pdo->prepare($checkSql);

    $checkStmt->execute([
        ':notification_id' => $notificationId,
        ':user_id' => $userId
    ]);

    if (!$checkStmt->fetch()) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Notifikasi tidak ditemukan',
            'data' => null
        ]);

        exit;
    }

    // Tandai notifikasi sebagai sudah dibaca
    $updateSql = "
        UPDATE notifications
        SET is_read = 1
        WHERE notification_id = :notification_id
        AND user_id = :user_id
    ";

    $updateStmt = $pdo->prepare($updateSql);

    $updateStmt->execute([
        ':notification_id' => $notificationId,
        ':user_id' => $userId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Notifikasi berhasil ditandai sebagai sudah dibaca',
        'data' => [
            'notification_id' => (int) $notificationId,
            'is_read' => 1
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat memperbarui notifikasi',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
