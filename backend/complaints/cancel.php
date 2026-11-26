
<?php

/**
 * =====================================================
 * LAPORWARGA - CANCEL COMPLAINT
 * Backend 2
 *
 * Method : POST
 * Role   : warga
 * Status : baru -> dibatalkan
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
        'message' => 'Method tidak diizinkan.',
        'data' => null
    ]);

    exit;
}

// Hanya warga yang boleh membatalkan pengaduan
requireRole('warga');

// Ambil identitas warga dari session
$userId = (int) $_SESSION['user_id'];

// Validasi complaint_id
$complaintId = filter_var(
    $_POST['complaint_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$complaintId || $complaintId <= 0) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'ID pengaduan tidak valid.',
        'data' => null
    ]);

    exit;
}

try {

    $pdo->beginTransaction();

    // Ambil pengaduan milik warga dan kunci baris
    $stmt = $pdo->prepare("
        SELECT complaint_id, user_id, status
        FROM complaints
        WHERE complaint_id = :complaint_id
          AND user_id = :user_id
        FOR UPDATE
    ");

    $stmt->execute([
        ':complaint_id' => $complaintId,
        ':user_id' => $userId
    ]);

    $complaint = $stmt->fetch();

    // Pengaduan tidak ditemukan atau bukan milik warga
    if (!$complaint) {
        $pdo->rollBack();

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Pengaduan tidak ditemukan.',
            'data' => null
        ]);

        exit;
    }

    // Hanya status baru yang boleh dibatalkan
    if ($complaint['status'] !== 'baru') {
        $pdo->rollBack();

        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' => 'Pengaduan tidak dapat dibatalkan karena sudah diproses atau telah berstatus final.',
            'data' => null
        ]);

        exit;
    }

    // Ubah status pengaduan
    $update = $pdo->prepare("
        UPDATE complaints
        SET status = 'dibatalkan'
        WHERE complaint_id = :complaint_id
          AND user_id = :user_id
          AND status = 'baru'
    ");

    $update->execute([
        ':complaint_id' => $complaintId,
        ':user_id' => $userId
    ]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException(
            'Status pengaduan gagal diperbarui.'
        );
    }

    // Catat riwayat pembatalan
    $historyId = catatRiwayat(
        $pdo,
        (int) $complaintId,
        $userId,
        'pembatalan',
        'Warga membatalkan pengaduan.',
        'baru',
        'dibatalkan'
    );

    // Simpan transaksi
    $pdo->commit();

    http_response_code(200);

    echo json_encode([
        'success' => true,
        'message' => 'Pengaduan berhasil dibatalkan.',
        'data' => [
            'complaint_id' => (int) $complaintId,
            'old_status' => 'baru',
            'new_status' => 'dibatalkan',
            'history_id' => $historyId
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Cancel complaint error: ' . $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat membatalkan pengaduan.',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
