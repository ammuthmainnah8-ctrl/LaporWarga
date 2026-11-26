
<?php

/**
 * =====================================================
 * LAPORWARGA - DASHBOARD WARGA
 * Backend 2
 *
 * Method : GET
 * Role   : warga
 * Fungsi : Mengambil statistik dan pengaduan terbaru
 * =====================================================
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_check.php';

// Hanya menerima request GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method tidak diizinkan',
        'data' => null
    ]);

    exit;
}

// Hanya warga yang dapat mengakses dashboard ini
requireRole('warga');

// Ambil ID warga dari session
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

    // Mengambil jumlah pengaduan berdasarkan status
    $statsSql = "
        SELECT
            COUNT(*) AS total,
            COALESCE(SUM(status = 'baru'), 0) AS baru,
            COALESCE(SUM(status = 'diproses'), 0) AS diproses,
            COALESCE(SUM(status = 'selesai'), 0) AS selesai,
            COALESCE(SUM(status = 'ditolak'), 0) AS ditolak,
            COALESCE(SUM(status = 'dibatalkan'), 0) AS dibatalkan
        FROM complaints
        WHERE user_id = :user_id
    ";

    $statsStmt = $pdo->prepare($statsSql);

    $statsStmt->execute([
        ':user_id' => $userId
    ]);

    $stats = $statsStmt->fetch();

    // Mengambil 5 pengaduan terbaru milik warga
    $recentSql = "
        SELECT
            c.complaint_id,
            c.ticket_number,
            c.title,
            c.location,
            c.status,
            c.created_at,
            cat.category_name
        FROM complaints c
        INNER JOIN categories cat
            ON c.category_id = cat.category_id
        WHERE c.user_id = :user_id
        ORDER BY c.created_at DESC, c.complaint_id DESC
        LIMIT 5
    ";

    $recentStmt = $pdo->prepare($recentSql);

    $recentStmt->execute([
        ':user_id' => $userId
    ]);

    $recentComplaints = $recentStmt->fetchAll();

    // Berhasil mengambil data dashboard
    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'Data dashboard berhasil diambil',
        'data' => [
            'statistics' => [
                'total' => (int) $stats['total'],
                'baru' => (int) $stats['baru'],
                'diproses' => (int) $stats['diproses'],
                'selesai' => (int) $stats['selesai'],
                'ditolak' => (int) $stats['ditolak'],
                'dibatalkan' => (int) $stats['dibatalkan']
            ],
            'recent_complaints' => $recentComplaints
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {

    error_log('DASHBOARD ERROR: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat mengambil data dashboard',
        'data' => null
    ]);

}
