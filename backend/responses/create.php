
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

// Hanya petugas yang boleh memberikan tanggapan
requireRole('petugas');

$userId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$userId || $userId < 1) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Session petugas tidak valid',
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

// Validasi tanggapan
if ($message === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Tanggapan tidak boleh kosong',
        'data' => null
    ]);
    exit;
}

if (strlen($message) > 5000) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Tanggapan terlalu panjang',
        'data' => null
    ]);
    exit;
}

try {

    // Memulai transaksi
    $pdo->beginTransaction();

    // Mengambil pemilik pengaduan
    $checkSql = "
        SELECT complaint_id, user_id, status
        FROM complaints
        WHERE complaint_id = :complaint_id
        FOR UPDATE
    ";

    $checkStmt = $pdo->prepare($checkSql);

    $checkStmt->execute([
        ':complaint_id' => $complaintId
    ]);

    $complaint = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$complaint) {
        throw new Exception('Pengaduan tidak ditemukan');
    }
    // Tanggapan hanya boleh diberikan pada pengaduan aktif
if (!in_array($complaint['status'], ['baru', 'diproses'], true)) {
    throw new Exception(
        'Tanggapan tidak dapat diberikan karena pengaduan sudah berstatus final'
    );
}

    // Simpan tanggapan petugas
    $sql = "
        INSERT INTO complaint_updates (
            complaint_id,
            user_id,
            update_type,
            message
        ) VALUES (
            :complaint_id,
            :user_id,
            'response',
            :message
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':complaint_id' => $complaintId,
        ':user_id' => $userId,
        ':message' => $message
    ]);

    $updateId = (int) $pdo->lastInsertId();

    // Buat notifikasi untuk warga pemilik pengaduan
    $notificationTitle = 'Tanggapan Baru';

    $notificationMessage =
        'Petugas telah memberikan tanggapan pada pengaduan nomor ' .
        $complaintId . '.';

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

    $notificationStmt->execute([
        ':user_id' => $complaint['user_id'],
        ':complaint_id' => $complaintId,
        ':title' => $notificationTitle,
        ':message' => $notificationMessage
    ]);

    // Simpan seluruh perubahan
    $pdo->commit();

    http_response_code(201);

    echo json_encode([
        'success' => true,
        'message' => 'Tanggapan berhasil disimpan dan notifikasi berhasil dibuat',
        'data' => [
            'update_id' => $updateId,
            'complaint_id' => (int) $complaintId,
            'user_id' => (int) $userId,
            'update_type' => 'response',
            'message' => $message
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    // Batalkan transaksi jika terjadi kesalahan
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

  if ($e instanceof PDOException) {
    http_response_code(500);
} elseif ($e->getMessage() === 'Pengaduan tidak ditemukan') {
    http_response_code(404);
} elseif (
    $e->getMessage() === 'Tanggapan tidak dapat diberikan karena pengaduan sudah berstatus final'
) {
    http_response_code(403);
} else {
    http_response_code(500);
}

    echo json_encode([
        'success' => false,
        'message' => $e instanceof PDOException
            ? 'Terjadi kesalahan saat menyimpan tanggapan'
            : $e->getMessage(),
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
