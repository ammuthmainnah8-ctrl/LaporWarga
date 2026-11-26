
<?php

/**
 * =====================================================
 * LAPORWARGA - BALASAN WARGA
 * Backend 2
 *
 * Method : POST
 * Role   : warga
 * Fungsi : Mengirim balasan kepada petugas
 * =====================================================
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/riwayat_helper.php';

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

// Hanya warga yang boleh membalas
requireRole('warga');

$userId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$userId || $userId < 1) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session warga tidak valid',
        'data' => null
    ]);
    exit;
}

// Ambil data
$complaintId = filter_input(
    INPUT_POST,
    'complaint_id',
    FILTER_VALIDATE_INT
);

$message = trim($_POST['message'] ?? '');

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

// Validasi pesan
if ($message === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Pesan balasan tidak boleh kosong',
        'data' => null
    ]);
    exit;
}

if (strlen($message) > 5000) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Pesan balasan terlalu panjang',
        'data' => null
    ]);
    exit;
}

try {

    $pdo->beginTransaction();

    // Periksa kepemilikan dan status pengaduan
    $checkSql = "
        SELECT complaint_id, user_id, status
        FROM complaints
        WHERE complaint_id = :complaint_id
          AND user_id = :user_id
        FOR UPDATE
    ";

    $checkStmt = $pdo->prepare($checkSql);

    $checkStmt->execute([
        ':complaint_id' => $complaintId,
        ':user_id' => $userId
    ]);

    $complaint = $checkStmt->fetch();

    if (!$complaint) {
        throw new Exception('Pengaduan tidak ditemukan');
    }

    // Balasan hanya diperbolehkan selama pengaduan aktif
    if (!in_array(
        $complaint['status'],
        ['baru', 'diproses'],
        true
    )) {
        throw new Exception(
            'Balasan tidak dapat dikirim karena pengaduan sudah berstatus final'
        );
    }

    // Periksa apakah petugas sudah memberikan tanggapan
    $responseSql = "
        SELECT cu.update_id
        FROM complaint_updates cu
        INNER JOIN users u
            ON u.user_id = cu.user_id
        WHERE cu.complaint_id = :complaint_id
          AND cu.update_type = 'response'
          AND u.role = 'petugas'
        LIMIT 1
    ";

    $responseStmt = $pdo->prepare($responseSql);

    $responseStmt->execute([
        ':complaint_id' => $complaintId
    ]);

    $officerResponse = $responseStmt->fetch();

    if (!$officerResponse) {
        throw new Exception(
            'Anda belum dapat membalas karena petugas belum memberikan tanggapan'
        );
    }

    // Simpan balasan ke riwayat
    $updateId = catatRiwayat(
        $pdo,
        (int) $complaintId,
        (int) $userId,
        'balasan',
        $message
    );

    // Ambil seluruh akun petugas
    $officerSql = "
        SELECT user_id
        FROM users
        WHERE role = 'petugas'
    ";

    $officerStmt = $pdo->query($officerSql);
    $officers = $officerStmt->fetchAll();

    // Buat notifikasi untuk petugas
    $notificationSql = "
        INSERT INTO notifications (
            user_id,
            complaint_id,
            title,
            message,
            is_read,
            created_at
        ) VALUES (
            :user_id,
            :complaint_id,
            :title,
            :message,
            0,
            NOW()
        )
    ";

    $notificationStmt = $pdo->prepare($notificationSql);

    foreach ($officers as $officer) {
        $notificationStmt->execute([
            ':user_id' => $officer['user_id'],
            ':complaint_id' => $complaintId,
            ':title' => 'Balasan Baru dari Warga',
            ':message' => 'Warga telah mengirim balasan pada pengaduan nomor ' .
                $complaintId . '.'
        ]);
    }

    $pdo->commit();

    http_response_code(201);

    echo json_encode([
        'success' => true,
        'message' => 'Balasan berhasil dikirim',
        'data' => [
            'update_id' => $updateId,
            'complaint_id' => (int) $complaintId,
            'user_id' => (int) $userId,
            'update_type' => 'balasan',
            'message' => $message
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('REPLY ERROR: ' . $e->getMessage());

    if ($e->getMessage() === 'Pengaduan tidak ditemukan') {
        http_response_code(404);
        $errorMessage = $e->getMessage();

    } elseif (
        $e->getMessage() ===
        'Balasan tidak dapat dikirim karena pengaduan sudah berstatus final'
    ) {
        http_response_code(409);
        $errorMessage = $e->getMessage();

    } elseif (
        $e->getMessage() ===
        'Anda belum dapat membalas karena petugas belum memberikan tanggapan'
    ) {
        http_response_code(409);
        $errorMessage = $e->getMessage();

    } else {
        http_response_code(500);
        $errorMessage = 'Terjadi kesalahan saat mengirim balasan';
    }

    echo json_encode([
        'success' => false,
        'message' => $errorMessage,
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
