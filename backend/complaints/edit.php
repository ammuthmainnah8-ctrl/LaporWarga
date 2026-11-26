
<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/../config/riwayat_helper.php';

/**
 * Mengirim respons JSON.
 */
function editResponse(int $statusCode, string $message, $data = null): void
{
    http_response_code($statusCode);

    echo json_encode([
        'success' => $statusCode >= 200 && $statusCode < 300,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Hanya menerima POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    editResponse(405, 'Method tidak diizinkan.');
}

// Hanya warga yang boleh mengedit pengaduan.
requireRole('warga');

// Validasi user ID dari session.
$userId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$userId || $userId < 1) {
    editResponse(401, 'Sesi pengguna tidak valid.');
}

// Ambil data dari request.
$complaintId = filter_input(
    INPUT_POST,
    'complaint_id',
    FILTER_VALIDATE_INT
);

$categoryId = filter_input(
    INPUT_POST,
    'category_id',
    FILTER_VALIDATE_INT
);

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$incidentDate = trim($_POST['incident_date'] ?? '');
$location = trim($_POST['location'] ?? '');

// Validasi ID pengaduan.
if (!$complaintId || $complaintId < 1) {
    editResponse(422, 'ID pengaduan tidak valid.');
}

// Validasi kategori.
if (!$categoryId || $categoryId < 1) {
    editResponse(422, 'Kategori pengaduan tidak valid.');
}

// Validasi judul.
if ($title === '') {
    editResponse(422, 'Judul pengaduan wajib diisi.');
}

if (mb_strlen($title, 'UTF-8') > 150) {
    editResponse(422, 'Judul maksimal 150 karakter.');
}

// Validasi deskripsi.
if ($description === '') {
    editResponse(422, 'Deskripsi pengaduan wajib diisi.');
}

if (mb_strlen($description, 'UTF-8') > 5000) {
    editResponse(422, 'Deskripsi maksimal 5000 karakter.');
}

// Validasi tanggal kejadian.
if ($incidentDate === '') {
    editResponse(422, 'Tanggal kejadian wajib diisi.');
}

$dateObject = DateTime::createFromFormat('!Y-m-d', $incidentDate);

if (
    !$dateObject ||
    $dateObject->format('Y-m-d') !== $incidentDate
) {
    editResponse(422, 'Format tanggal kejadian tidak valid.');
}

// Tanggal kejadian tidak boleh melebihi hari ini.
if ($incidentDate > date('Y-m-d')) {
    editResponse(422, 'Tanggal kejadian tidak boleh melebihi hari ini.');
}

// Validasi lokasi.
if ($location === '') {
    editResponse(422, 'Lokasi kejadian wajib diisi.');
}

if (mb_strlen($location, 'UTF-8') > 255) {
    editResponse(422, 'Lokasi maksimal 255 karakter.');
}

try {

    // Memulai transaksi.
    $pdo->beginTransaction();

    // Ambil pengaduan berdasarkan ID dan pemiliknya.
    // FOR UPDATE mengunci data selama transaksi berlangsung.
    $sql = "
        SELECT complaint_id, category_id, title,
               description, incident_date, location, status
        FROM complaints
        WHERE complaint_id = :complaint_id
        AND user_id = :user_id
        FOR UPDATE
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':complaint_id' => $complaintId,
        ':user_id' => $userId
    ]);

    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pengaduan tidak ditemukan atau bukan milik warga.
    if (!$complaint) {
        $pdo->rollBack();

        editResponse(404, 'Pengaduan tidak ditemukan.');
    }

    // Pengaduan hanya dapat diedit jika status masih baru.
    if ($complaint['status'] !== 'baru') {
        $pdo->rollBack();

        editResponse(
            409,
            'Pengaduan tidak dapat diedit karena sudah diproses atau dibatalkan.'
        );
    }

    // Periksa apakah kategori tersedia.
    $categorySql = "
        SELECT category_id
        FROM categories
        WHERE category_id = :category_id
    ";

    $categoryStmt = $pdo->prepare($categorySql);

    $categoryStmt->execute([
        ':category_id' => $categoryId
    ]);

    if (!$categoryStmt->fetch(PDO::FETCH_ASSOC)) {
        $pdo->rollBack();

        editResponse(422, 'Kategori pengaduan tidak ditemukan.');
    }

    // Periksa apakah ada perubahan data.
    $hasChanges =
        (int) $complaint['category_id'] !== (int) $categoryId ||
        $complaint['title'] !== $title ||
        $complaint['description'] !== $description ||
        $complaint['incident_date'] !== $incidentDate ||
        $complaint['location'] !== $location;

    if (!$hasChanges) {
        $pdo->rollBack();

        editResponse(422, 'Tidak ada perubahan data pengaduan.');
    }

    // Perbarui data pengaduan.
    $updateSql = "
        UPDATE complaints
        SET
            category_id = :category_id,
            title = :title,
            description = :description,
            incident_date = :incident_date,
            location = :location,
            updated_at = CURRENT_TIMESTAMP
        WHERE complaint_id = :complaint_id
        AND user_id = :user_id
        AND status = 'baru'
    ";

    $updateStmt = $pdo->prepare($updateSql);

    $updateStmt->execute([
        ':category_id' => $categoryId,
        ':title' => $title,
        ':description' => $description,
        ':incident_date' => $incidentDate,
        ':location' => $location,
        ':complaint_id' => $complaintId,
        ':user_id' => $userId
    ]);

    // Pastikan data benar-benar berhasil diperbarui.
    if ($updateStmt->rowCount() !== 1) {
        throw new RuntimeException(
            'Data pengaduan gagal diperbarui.'
        );
    }

    // Catat perubahan ke riwayat pengaduan.
    $historyId = catatRiwayat(
        $pdo,
        (int) $complaintId,
        (int) $userId,
        'edit',
        'Warga memperbarui data pengaduan.'
    );

    // Simpan seluruh perubahan.
    $pdo->commit();

    // Kirim respons berhasil.
    editResponse(
        200,
        'Pengaduan berhasil diperbarui.',
        [
            'complaint_id' => (int) $complaintId,
            'category_id' => (int) $categoryId,
            'title' => $title,
            'description' => $description,
            'incident_date' => $incidentDate,
            'location' => $location,
            'history_id' => $historyId
        ]
    );

} catch (Throwable $e) {

    // Batalkan transaksi jika masih aktif.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Catat detail kesalahan ke error log server.
    error_log('Edit Complaint Error: ' . $e->getMessage());

    editResponse(
        500,
        'Terjadi kesalahan saat memperbarui pengaduan.'
    );
}
