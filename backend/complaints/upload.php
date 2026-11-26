
<?php

/**
 * =====================================================
 * LAPORWARGA - UPLOAD FOTO AWAL PENGADUAN
 * Backend 2
 *
 * Method : POST
 * Maksimal: 1 foto
 * Ukuran  : 5 MB
 * Format  : JPG, JPEG, PNG
 * Status  : Hanya pengaduan baru
 * =====================================================
 */

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

// Hanya warga yang boleh mengunggah foto awal
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

// Ambil ID pengaduan
$complaintId = filter_input(
    INPUT_POST,
    'complaint_id',
    FILTER_VALIDATE_INT
);

if (!$complaintId || $complaintId < 1) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'ID pengaduan tidak valid',
        'data' => null
    ]);

    exit;
}

// Periksa keberadaan file
if (!isset($_FILES['photo'])) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Foto wajib dipilih',
        'data' => null
    ]);

    exit;
}

$file = $_FILES['photo'];

// Periksa error upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Foto gagal diunggah',
        'data' => null
    ]);

    exit;
}

// Validasi ukuran maksimal 5 MB
$maxSize = 5 * 1024 * 1024;

if ($file['size'] < 1 || $file['size'] > $maxSize) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Ukuran foto maksimal 5 MB',
        'data' => null
    ]);

    exit;
}

// Pastikan file merupakan hasil upload HTTP
if (!is_uploaded_file($file['tmp_name'])) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'File upload tidak valid',
        'data' => null
    ]);

    exit;
}

try {

    // Periksa kepemilikan, status, dan foto sebelumnya
    $sql = "
        SELECT
            complaint_id,
            status,
            initial_photo
        FROM complaints
        WHERE complaint_id = :complaint_id
          AND user_id = :user_id
        FOR UPDATE
    ";

    // Gunakan transaksi agar pemeriksaan dan penyimpanan aman
    $pdo->beginTransaction();

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':complaint_id' => $complaintId,
        ':user_id' => $userId
    ]);

    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$complaint) {
        $pdo->rollBack();

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Pengaduan tidak ditemukan atau bukan milik Anda',
            'data' => null
        ]);

        exit;
    }

    // Foto hanya boleh ditambahkan ketika status baru
    if ($complaint['status'] !== 'baru') {
        $pdo->rollBack();

        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'Foto hanya dapat diunggah pada pengaduan berstatus baru',
            'data' => null
        ]);

        exit;
    }

    // Cegah penggantian foto awal
    if (!empty($complaint['initial_photo'])) {
        $pdo->rollBack();

        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' => 'Foto awal pengaduan sudah tersedia dan tidak dapat diganti',
            'data' => null
        ]);

        exit;
    }

    // Validasi MIME file berdasarkan isi sebenarnya
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];

    if (!isset($allowedTypes[$mimeType])) {
        $pdo->rollBack();

        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'Format foto harus JPG, JPEG, atau PNG',
            'data' => null
        ]);

        exit;
    }

    // Folder khusus foto awal pengaduan
    $uploadDir = __DIR__ . '/../../uploads/complaints/';

    if (
        !is_dir($uploadDir) &&
        !mkdir($uploadDir, 0755, true)
    ) {
        throw new RuntimeException(
            'Folder upload gagal dibuat'
        );
    }

    // Buat nama file acak
    $fileName = bin2hex(random_bytes(16))
        . '.'
        . $allowedTypes[$mimeType];

    $targetPath = $uploadDir . $fileName;

    // Simpan file ke folder
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException(
            'Gagal menyimpan foto'
        );
    }

    // Lokasi relatif foto
    $relativePath = 'uploads/complaints/' . $fileName;

    // Simpan lokasi foto ke database
    $updateSql = "
        UPDATE complaints
        SET initial_photo = :photo
        WHERE complaint_id = :complaint_id
          AND user_id = :user_id
          AND status = 'baru'
          AND initial_photo IS NULL
    ";

    $updateStmt = $pdo->prepare($updateSql);

    $updateStmt->execute([
        ':photo' => $relativePath,
        ':complaint_id' => $complaintId,
        ':user_id' => $userId
    ]);

    if ($updateStmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Foto gagal dikaitkan dengan pengaduan'
        );
    }

    // Selesaikan transaksi
    $pdo->commit();

    http_response_code(201);

    echo json_encode([
        'success' => true,
        'message' => 'Foto awal pengaduan berhasil diunggah',
        'data' => [
            'complaint_id' => (int) $complaintId,
            'photo_type' => 'initial',
            'photo_path' => $relativePath
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {

    // Batalkan transaksi jika masih aktif
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Hapus file jika sudah tersimpan tetapi proses gagal
    if (isset($targetPath) && is_file($targetPath)) {
        unlink($targetPath);
    }

    error_log($e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Terjadi kesalahan saat mengunggah foto',
        'data' => null
    ], JSON_UNESCAPED_UNICODE);
}
