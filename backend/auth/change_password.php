
<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth_check.php';

requireLogin();

function responseJSON(int $code, bool $success, string $message): void
{
    http_response_code($code);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => null
    ]);

    exit;
}

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responseJSON(405, false, 'Method tidak diizinkan');
}

// Ambil input
$currentPassword = $_POST['current_password'] ?? '';
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validasi input
if (
    $currentPassword === '' ||
    $newPassword === '' ||
    $confirmPassword === ''
) {
    responseJSON(422, false, 'Semua kolom password wajib diisi');
}

// Minimal 8 karakter
if (strlen($newPassword) < 8) {
    responseJSON(422, false, 'Password baru minimal 8 karakter');
}

// Konfirmasi password
if ($newPassword !== $confirmPassword) {
    responseJSON(422, false, 'Konfirmasi password tidak cocok');
}

// Password baru harus berbeda
if ($currentPassword === $newPassword) {
    responseJSON(422, false, 'Password baru harus berbeda dari password lama');
}

try {
    $pdo->beginTransaction();

    // Ambil password pengguna dan kunci baris selama transaksi
    $stmt = $pdo->prepare("
        SELECT password
        FROM users
        WHERE user_id = :user_id
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->execute([
        ':user_id' => $_SESSION['user_id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Periksa password lama
    if (!$user || !password_verify($currentPassword, $user['password'])) {
        $pdo->rollBack();

        responseJSON(401, false, 'Password lama tidak sesuai');
    }

    // Buat hash password baru
    $newPasswordHash = password_hash(
        $newPassword,
        PASSWORD_DEFAULT
    );

    if ($newPasswordHash === false) {
        throw new RuntimeException('Gagal membuat hash password.');
    }

    // Simpan password baru
    $update = $pdo->prepare("
        UPDATE users
        SET password = :password
        WHERE user_id = :user_id
    ");

    $update->execute([
        ':password' => $newPasswordHash,
        ':user_id' => $_SESSION['user_id']
    ]);

    // Ambil kembali password dari database
    $check = $pdo->prepare("
        SELECT password
        FROM users
        WHERE user_id = :user_id
        LIMIT 1
    ");

    $check->execute([
        ':user_id' => $_SESSION['user_id']
    ]);

    $savedHash = $check->fetchColumn();

    // Pastikan password benar-benar tersimpan
    if (
        $savedHash === false ||
        !password_verify($newPassword, $savedHash)
    ) {
        throw new RuntimeException(
            'Verifikasi password setelah penyimpanan gagal.'
        );
    }

    // Simpan perubahan secara permanen
    $pdo->commit();

    responseJSON(200, true, 'Password berhasil diubah');

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($e->getMessage());

    responseJSON(
        500,
        false,
        'Password gagal diubah. Silakan coba kembali.'
    );
}
