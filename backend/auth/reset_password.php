
<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';

function responseJSON(
    int $code,
    bool $success,
    string $message
): void {
    http_response_code($code);

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => null
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Hanya menerima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responseJSON(405, false, 'Method tidak diizinkan');
}

// Ambil input
$token = trim($_POST['token'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Validasi input
if ($token === '' || $password === '' || $confirmPassword === '') {
    responseJSON(422, false, 'Semua data wajib diisi.');
}

if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
    responseJSON(400, false, 'Tautan reset password tidak valid.');
}

if (strlen($password) < 8) {
    responseJSON(422, false, 'Password minimal 8 karakter.');
}

if ($password !== $confirmPassword) {
    responseJSON(422, false, 'Konfirmasi password tidak cocok.');
}

try {
    $pdo->beginTransaction();

    // Hash token untuk pencarian
    $tokenHash = hash('sha256', $token);

    // Ambil token dan kunci baris selama transaksi
    $stmt = $pdo->prepare(
        "SELECT id, user_id, expires_at, used_at
         FROM password_resets
         WHERE token_hash = :token_hash
         LIMIT 1
         FOR UPDATE"
    );

    $stmt->execute([
        ':token_hash' => $tokenHash
    ]);

    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        $pdo->rollBack();
        responseJSON(400, false, 'Tautan reset tidak valid atau sudah digunakan.');
    }

    // Pastikan token belum digunakan
    if ($reset['used_at'] !== null) {
        $pdo->rollBack();
        responseJSON(400, false, 'Tautan reset tidak valid atau sudah digunakan.');
    }

    // Pastikan token belum kedaluwarsa
    $timezone = new DateTimeZone('Asia/Makassar');

    $now = new DateTimeImmutable('now', $timezone);
    $expiresAt = new DateTimeImmutable(
        $reset['expires_at'],
        $timezone
    );

    if ($now >= $expiresAt) {
        $pdo->rollBack();
        responseJSON(400, false, 'Tautan reset password sudah kedaluwarsa.');
    }

    // Buat hash password baru
    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    if ($passwordHash === false) {
        throw new RuntimeException('Gagal membuat hash password.');
    }

    // Perbarui password akun
    $updatePassword = $pdo->prepare(
        "UPDATE users
         SET password = :password
         WHERE user_id = :user_id"
    );

    $updatePassword->execute([
        ':password' => $passwordHash,
        ':user_id' => $reset['user_id']
    ]);

    if ($updatePassword->rowCount() !== 1) {
        throw new RuntimeException('Gagal memperbarui password akun.');
    }

    // Tandai token sebagai sudah digunakan
    $markUsed = $pdo->prepare(
        "UPDATE password_resets
         SET used_at = :used_at
         WHERE id = :id
         AND used_at IS NULL"
    );

    $markUsed->execute([
        ':used_at' => $now->format('Y-m-d H:i:s'),
        ':id' => $reset['id']
    ]);

    if ($markUsed->rowCount() !== 1) {
        throw new RuntimeException('Gagal menandai token reset.');
    }

    // Nonaktifkan token reset lain milik akun tersebut
    $invalidateOthers = $pdo->prepare(
        "UPDATE password_resets
         SET used_at = :used_at
         WHERE user_id = :user_id
         AND id <> :id
         AND used_at IS NULL"
    );

    $invalidateOthers->execute([
        ':used_at' => $now->format('Y-m-d H:i:s'),
        ':user_id' => $reset['user_id'],
        ':id' => $reset['id']
    ]);

    // Simpan seluruh perubahan
    $pdo->commit();

    responseJSON(
        200,
        true,
        'Password berhasil direset. Silakan login menggunakan password baru.'
    );

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Reset password error: ' . $e->getMessage());

    responseJSON(
        500,
        false,
        'Reset password gagal diproses. Silakan coba lagi.'
    );
}
