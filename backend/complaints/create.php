
<?php

/**
 * =====================================================
 * LAPORWARGA - CREATE COMPLAINT
 * Backend 2
 *
 * Method       : POST
 * Table        : complaints
 * Status       : baru
 * Foto         : JPG, JPEG, PNG
 * Maksimal foto: 1 file, 5 MB
 * =====================================================
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_check.php';
require_once __DIR__ . '/generate_ticket.php';

// Fungsi respons JSON
function responseJSON(
    int $status,
    bool $success,
    string $message,
    $data = null,
    $errors = null
): void {
    http_response_code($status);

    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data
    ];

    if ($errors !== null) {
        $response['errors'] = $errors;
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responseJSON(
        405,
        false,
        'Metode request harus POST'
    );
}

// Hanya warga yang boleh membuat pengaduan
requireRole('warga');

// Ambil ID warga dari session
$userId = filter_var(
    $_SESSION['user_id'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$userId || $userId < 1) {
    responseJSON(
        401,
        false,
        'Session pengguna tidak valid'
    );
}

// Ambil data formulir
$categoryId = filter_input(
    INPUT_POST,
    'category_id',
    FILTER_VALIDATE_INT
);

$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$incidentDate = trim($_POST['incident_date'] ?? '');
$location = trim($_POST['location'] ?? '');

// Menampung kesalahan validasi
$errors = [];

// Validasi kategori
if (!$categoryId || $categoryId < 1) {
    $errors['category_id'] = 'Kategori wajib dipilih';
}

// Validasi judul
if ($title === '') {
    $errors['title'] = 'Judul pengaduan wajib diisi';
} elseif (strlen($title) > 150) {
    $errors['title'] = 'Judul maksimal 150 karakter';
}

// Validasi deskripsi
if ($description === '') {
    $errors['description'] = 'Deskripsi wajib diisi';
}

// Validasi tanggal kejadian
$dateObject = DateTime::createFromFormat(
    '!Y-m-d',
    $incidentDate
);

if (
    !$dateObject ||
    $dateObject->format('Y-m-d') !== $incidentDate
) {
    $errors['incident_date'] =
        'Format tanggal harus YYYY-MM-DD';

} elseif ($incidentDate > date('Y-m-d')) {
    $errors['incident_date'] =
        'Tanggal kejadian tidak boleh di masa depan';
}

// Validasi lokasi
if ($location === '') {
    $errors['location'] = 'Lokasi kejadian wajib diisi';

} elseif (strlen($location) > 255) {
    $errors['location'] =
        'Lokasi maksimal 255 karakter';
}

// =====================================================
// VALIDASI FOTO AWAL
// Foto bersifat opsional, maksimal satu file.
// =====================================================

$photo = null;
$photoExtension = null;

if (isset($_FILES['photo'])) {

    $photo = $_FILES['photo'];

    // Tolak input file berbentuk array
    if (is_array($photo['error'])) {
        $errors['photo'] = 'Upload hanya boleh satu foto';

    } elseif ($photo['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($photo['error'] !== UPLOAD_ERR_OK) {
            $errors['photo'] = 'Foto gagal diunggah';

        } elseif (
            !is_uploaded_file($photo['tmp_name'])
        ) {
            $errors['photo'] = 'File foto tidak valid';

        } elseif (
            $photo['size'] < 1 ||
            $photo['size'] > 5 * 1024 * 1024
        ) {
            $errors['photo'] =
                'Ukuran foto maksimal 5 MB';

        } else {

            // Periksa MIME berdasarkan isi file
            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $mimeType = $finfo->file(
                $photo['tmp_name']
            );

            $allowedTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png'
            ];

            if (!isset($allowedTypes[$mimeType])) {
                $errors['photo'] =
                    'Format foto harus JPG, JPEG, atau PNG';

            } else {
                $photoExtension =
                    $allowedTypes[$mimeType];
            }
        }
    }
}

// Jika validasi gagal
if (!empty($errors)) {
    responseJSON(
        422,
        false,
        'Data pengaduan tidak valid',
        null,
        $errors
    );
}

$targetPath = null;
$relativePath = null;

try {

    // Periksa kategori
    $checkCategory = $pdo->prepare(
        "SELECT category_id
         FROM categories
         WHERE category_id = :category_id"
    );

    $checkCategory->execute([
        ':category_id' => $categoryId
    ]);

    if (!$checkCategory->fetch()) {
        responseJSON(
            422,
            false,
            'Kategori pengaduan tidak ditemukan'
        );
    }

    // Mulai transaksi database
    $pdo->beginTransaction();

    // Buat nomor tiket
    $ticketNumber = generateTicketNumber($pdo);

    // Simpan data pengaduan
    $sql = "
        INSERT INTO complaints (
            user_id,
            category_id,
            ticket_number,
            title,
            description,
            incident_date,
            location,
            status
        ) VALUES (
            :user_id,
            :category_id,
            :ticket_number,
            :title,
            :description,
            :incident_date,
            :location,
            'baru'
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':user_id' => $userId,
        ':category_id' => $categoryId,
        ':ticket_number' => $ticketNumber,
        ':title' => $title,
        ':description' => $description,
        ':incident_date' => $incidentDate,
        ':location' => $location
    ]);

    // Ambil ID pengaduan
    $complaintId = (int) $pdo->lastInsertId();

    // Jika warga memilih foto
    if (
        $photo !== null &&
        $photo['error'] === UPLOAD_ERR_OK
    ) {

        // Folder penyimpanan foto awal
        $uploadDir = __DIR__ .
            '/../../uploads/complaints/';

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
            . $photoExtension;

        $targetPath = $uploadDir . $fileName;

        $relativePath =
            'uploads/complaints/' . $fileName;

        // Pindahkan foto
        if (
            !move_uploaded_file(
                $photo['tmp_name'],
                $targetPath
            )
        ) {
            throw new RuntimeException(
                'Gagal menyimpan foto'
            );
        }

        // Simpan lokasi foto ke database
        $updatePhoto = $pdo->prepare(
            "UPDATE complaints
             SET initial_photo = :photo
             WHERE complaint_id = :complaint_id
               AND user_id = :user_id
               AND status = 'baru'"
        );

        $updatePhoto->execute([
            ':photo' => $relativePath,
            ':complaint_id' => $complaintId,
            ':user_id' => $userId
        ]);

        if ($updatePhoto->rowCount() !== 1) {
            throw new RuntimeException(
                'Gagal menghubungkan foto dengan pengaduan'
            );
        }
    }

    // Simpan transaksi
    $pdo->commit();

    // Respons berhasil
    responseJSON(
        201,
        true,
        'Pengaduan berhasil dikirim',
        [
            'complaint_id' => $complaintId,
            'ticket_number' => $ticketNumber,
            'status' => 'baru',
            'title' => $title,
            'incident_date' => $incidentDate,
            'initial_photo' => $relativePath
        ]
    );

} catch (Throwable $e) {

    // Batalkan transaksi jika masih aktif
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Hapus file jika transaksi gagal
    if (
        $targetPath !== null &&
        is_file($targetPath)
    ) {
        unlink($targetPath);
    }

    // Simpan detail error di log
    error_log($e->getMessage());

    responseJSON(
        500,
        false,
        'Terjadi kesalahan saat menyimpan pengaduan'
    );
}
