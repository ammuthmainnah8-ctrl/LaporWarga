<?php

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

// Pastikan pengguna sudah login
requireLogin();

// Ambil identitas pengguna dari session
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

// Periksa role pengguna
if (!in_array($role, ['warga', 'petugas'], true)) {
    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Role pengguna tidak valid',
        'data' => null
    ]);

    exit;
}

try {

    // Warga hanya dapat melihat pengaduan miliknya.
    // Petugas dapat melihat seluruh pengaduan.
    if ($role === 'warga') {

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
            WHERE c.user_id = :user_id
            ORDER BY c.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ':user_id' => $userId
        ]);

    } else {

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
            ORDER BY c.created_at DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    }

    $complaints = $stmt->fetchAll();

    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'Data pengaduan berhasil diambil',
        'total' => count($complaints),
        'data' => $complaints
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat mengambil data pengaduan',
        'data' => null
    ]);

}