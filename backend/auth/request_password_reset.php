
<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mailer.php';

function responseJSON(int $code, bool $success, string $message): void
{
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

// Ambil email
$email = trim($_POST['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responseJSON(422, false, 'Format email tidak valid');
}

try {
    // Cari akun berdasarkan email
    $stmt = $pdo->prepare(
        "SELECT user_id, name, email
         FROM users
         WHERE email = :email
         LIMIT 1"
    );

    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pesan tetap sama meskipun email tidak terdaftar
    if (!$user) {
        responseJSON(
            200,
            true,
            'Jika email terdaftar, tautan reset password akan dikirim.'
        );
    }

    // Buat token acak yang aman
    $token = bin2hex(random_bytes(32));

    // Simpan hash token, bukan token asli
    $tokenHash = hash('sha256', $token);

    // Token berlaku selama 30 menit
    $expiresAt = (new DateTimeImmutable(
        'now',
        new DateTimeZone('Asia/Makassar')
    ))->modify('+30 minutes')->format('Y-m-d H:i:s');

    // Nonaktifkan token lama yang belum digunakan
    $delete = $pdo->prepare(
        "DELETE FROM password_resets
         WHERE user_id = :user_id
         AND used_at IS NULL"
    );

    $delete->execute([
        ':user_id' => $user['user_id']
    ]);

    // Simpan token baru
    $insert = $pdo->prepare(
        "INSERT INTO password_resets
         (user_id, token_hash, expires_at)
         VALUES (:user_id, :token_hash, :expires_at)"
    );

    $insert->execute([
        ':user_id' => $user['user_id'],
        ':token_hash' => $tokenHash,
        ':expires_at' => $expiresAt
    ]);

    // Link reset password untuk pengembangan lokal
    $resetLink = 'http://localhost/LaporWarga/reset_password.html?token='
        . urlencode($token);

    // Siapkan email
    $mail = createMailer();

    $mail->addAddress($user['email'], $user['name']);
    $mail->isHTML(true);
    $mail->Subject = 'Permintaan Reset Password LaporWarga';

    $nama = htmlspecialchars(
        $user['name'],
        ENT_QUOTES,
        'UTF-8'
    );

    $link = htmlspecialchars(
        $resetLink,
        ENT_QUOTES,
        'UTF-8'
    );

    $mail->Body = "
        <h2>Reset Password LaporWarga</h2>

        <p>Halo, {$nama}.</p>

        <p>Kami menerima permintaan untuk mengatur ulang password akun Anda.</p>

        <p>Klik tombol berikut untuk membuat password baru:</p>

        <p>
            <a href=\"{$link}\"
               style=\"background:#2563eb;color:white;padding:12px 20px;
               text-decoration:none;border-radius:6px;display:inline-block;\">
                Reset Password
            </a>
        </p>

        <p>Tautan ini hanya berlaku selama <b>30 menit</b> dan hanya dapat digunakan satu kali.</p>

        <p>Jika Anda tidak meminta reset password, abaikan email ini.</p>

        <p>Salam,<br><b>Tim LaporWarga</b></p>
    ";

    $mail->AltBody =
        "Halo, {$user['name']}.\n\n"
        . "Gunakan tautan berikut untuk reset password:\n"
        . $resetLink
        . "\n\nTautan berlaku selama 30 menit dan hanya dapat digunakan satu kali.";

    // Kirim email
    $mail->send();

    responseJSON(
        200,
        true,
        'Jika email terdaftar, tautan reset password akan dikirim.'
    );

} catch (Throwable $e) {
    error_log('Password reset request error: ' . $e->getMessage());

    // Hapus token yang belum terkirim jika terjadi kegagalan
    if (isset($tokenHash)) {
        try {
            $cleanup = $pdo->prepare(
                "DELETE FROM password_resets
                 WHERE token_hash = :token_hash
                 AND used_at IS NULL"
            );

            $cleanup->execute([
                ':token_hash' => $tokenHash
            ]);
        } catch (Throwable $cleanupError) {
            error_log(
                'Password reset cleanup error: '
                . $cleanupError->getMessage()
            );
        }
    }

    responseJSON(
        500,
        false,
        'Permintaan reset password belum dapat diproses. Silakan coba lagi.'
    );
}
