
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

// Pastikan pengguna sudah login
requireLogin();

// Ambil session pengguna
$userId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

$role = $_SESSION['role'] ?? '';

// Validasi session
if (!$userId || $userId < 1) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Session pengguna tidak valid',
        'data' => null
    ]);

    exit;
}

// Validasi role
if (!in_array($role, ['warga', 'petugas'], true)) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Anda tidak memiliki hak akses',
        'data' => null
    ]);

    exit;
}

// Ambil ID pengaduan dari URL
$complaintId = filter_input(
    INPUT_GET,
    'complaint_id',
    FILTER_VALIDATE_INT
);

// Validasi ID pengaduan
if (!$complaintId || $complaintId < 1) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'ID pengaduan tidak valid',
        'data' => null
    ]);

    exit;
}

try {

    // Periksa keberadaan dan hak akses pengaduan
    $checkSql = "
        SELECT complaint_id
        FROM complaints
        WHERE complaint_id = :complaint_id
    ";

    $checkParams = [
        ':complaint_id' => $complaintId
    ];

    // Warga hanya boleh mengakses pengaduan miliknya
    if ($role === 'warga') {
        $checkSql .= " AND user_id = :user_id";

        $checkParams[':user_id'] = $userId;
    }

    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute($checkParams);

    $complaint = $checkStmt->fetch(PDO::FETCH_ASSOC);

    // Jika pengaduan tidak ditemukan atau tidak boleh diakses
    if (!$complaint) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Pengaduan tidak ditemukan',
            'data' => null
        ]);

        exit;
    }

    // Ambil riwayat pengaduan
    $sql = "
        SELECT
            cu.update_id,
            cu.complaint_id,
            cu.user_id,
            u.name AS user_name,
            cu.update_type,
            cu.old_status,
            cu.new_status,
            cu.message,
            cu.documentation_photo,
            cu.created_at
        FROM complaint_updates cu
        INNER JOIN users u
            ON cu.user_id = u.user_id
        WHERE cu.complaint_id = :complaint_id
        ORDER BY cu.created_at ASC, cu.update_id ASC
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':complaint_id' => $complaintId
    ]);

    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Berhasil mengambil riwayat
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'Riwayat pengaduan berhasil diambil',
        'total' => count($history),
        'data' => $history
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat mengambil riwayat pengaduan',
        'data' => null
    ]);

}
