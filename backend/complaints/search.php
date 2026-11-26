
<?php

/**
 * =====================================================
 * LAPORWARGA - SEARCH & FILTER COMPLAINTS
 * Backend 2
 *
 * Method : GET
 * Role   : warga / petugas
 * =====================================================
 */

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

$userId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

$role = $_SESSION['role'] ?? '';

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

// Ambil parameter pencarian
$keyword = trim($_GET['keyword'] ?? '');
$status = trim($_GET['status'] ?? '');

// Validasi panjang keyword
if (strlen($keyword) > 100) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Kata pencarian maksimal 100 karakter',
        'data' => null
    ]);

    exit;
}

// Validasi status
$allowedStatuses = [
    'baru',
    'diproses',
    'selesai',
    'ditolak',
    'dibatalkan'
];

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Status pengaduan tidak valid',
        'data' => null
    ]);

    exit;
}

try {

    $sql = "
        SELECT
            c.complaint_id,
            c.ticket_number,
            c.title,
            c.description,
            c.incident_date,
            c.location,
            c.initial_photo,
            c.status,
            c.created_at,
            c.updated_at,
            cat.category_id,
            cat.category_name,
            u.name AS reporter_name
        FROM complaints c
        INNER JOIN categories cat
            ON c.category_id = cat.category_id
        INNER JOIN users u
            ON c.user_id = u.user_id
        WHERE 1 = 1
    ";

    $params = [];

    // Warga hanya dapat mencari pengaduan miliknya
    if ($role === 'warga') {
        $sql .= " AND c.user_id = :user_id";
        $params[':user_id'] = $userId;
    }

    // Pencarian judul, nomor tiket, dan lokasi
    if ($keyword !== '') {
        $sql .= "
            AND (
                c.title LIKE :keyword_title
                OR c.ticket_number LIKE :keyword_ticket
                OR c.location LIKE :keyword_location
            )
        ";

        $searchKeyword = '%' . $keyword . '%';

        $params[':keyword_title'] = $searchKeyword;
        $params[':keyword_ticket'] = $searchKeyword;
        $params[':keyword_location'] = $searchKeyword;
    }

    // Filter berdasarkan status
    if ($status !== '') {
        $sql .= " AND c.status = :status";
        $params[':status'] = $status;
    }

    // Urutkan dari pengaduan terbaru
    $sql .= "
        ORDER BY c.created_at DESC, c.complaint_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $complaints = $stmt->fetchAll();

    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'Pencarian pengaduan berhasil',
        'total' => count($complaints),
        'filters' => [
            'keyword' => $keyword,
            'status' => $status
        ],
        'data' => $complaints
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {

    error_log('SEARCH COMPLAINT ERROR: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat mencari pengaduan',
        'data' => null
    ]);

}
