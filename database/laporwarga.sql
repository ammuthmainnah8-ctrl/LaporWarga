-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 25, 2026 at 07:15 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `laporwarga`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `category_id` bigint UNSIGNED NOT NULL,
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'Infrastruktur', 'Jalan rusak, lampu jalan mati, jembatan, drainase'),
(2, 'Kebersihan', 'Sampah menumpuk, saluran tersumbat'),
(3, 'Keamanan', 'Gangguan keamanan dan ketertiban lingkungan'),
(4, 'Pelayanan Publik', 'Keluhan terhadap layanan kantor atau petugas');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `complaint_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `category_id` bigint UNSIGNED NOT NULL,
  `ticket_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `incident_date` date NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `initial_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'baru',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL
) ;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`complaint_id`, `user_id`, `category_id`, `ticket_number`, `title`, `description`, `incident_date`, `location`, `initial_photo`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'LPR-20261117-0001', 'Jalan Rusak', 'Kondisi jalan mengalami kerusakan.', '2026-11-16', 'Jalan Melati', NULL, 'diproses', '2026-11-17 03:04:58', '2026-11-17 03:04:59'),
(2, 3, 2, 'LPR-20261117-0002', 'Sampah Menumpuk', 'Sampah belum diangkut selama seminggu.', '2026-11-15', 'Jalan Mawar', 'uploads/complaints/f2e697697bf63b50c2f2d9fc4b54e778.jpg', 'baru', '2026-11-17 03:04:58', NULL),
(3, 3, 1, 'LPR-20261124-0003', 'Pengujian Pengaduan Baru', 'Ini adalah pengujian pengiriman pengaduan beserta foto.', '2026-11-24', 'Jalan Melati', 'uploads/complaints/a1dbbd4c97480000e6b35306f1e90378.jpg', 'baru', '2026-11-24 10:41:25', NULL),
(4, 3, 1, 'LPR-20261125-0004', 'Pengujian Pengaduan Baru - Diedit', 'Pengaduan khusus untuk menguji fitur edit warga.', '2026-11-25', 'Jalan Melati', NULL, 'dibatalkan', '2026-11-24 23:54:11', '2026-11-24 23:56:46');

--
-- Triggers `complaints`
--
DELIMITER $$
CREATE TRIGGER `trg_complaints_arsip` BEFORE DELETE ON `complaints` FOR EACH ROW BEGIN
    IF OLD.status IN ('selesai', 'ditolak', 'dibatalkan') THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Pengaduan final tidak boleh dihapus.';
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_complaints_lindungi` BEFORE UPDATE ON `complaints` FOR EACH ROW BEGIN
    -- Status final tidak boleh diubah
    IF OLD.status IN ('selesai', 'ditolak', 'dibatalkan')
       AND NEW.status <> OLD.status THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Status final tidak boleh diubah';
    END IF;

    -- Identitas dan nomor tiket tidak boleh diubah
    IF NEW.user_id <> OLD.user_id
       OR NEW.ticket_number <> OLD.ticket_number
       OR NEW.created_at <> OLD.created_at THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Data identitas dan nomor tiket tidak boleh diubah';
    END IF;

    -- Isi pengaduan hanya boleh diubah saat status baru
    IF OLD.status <> 'baru' AND (
        NOT (NEW.category_id <=> OLD.category_id)
        OR NOT (NEW.title <=> OLD.title)
        OR NOT (NEW.description <=> OLD.description)
        OR NOT (NEW.incident_date <=> OLD.incident_date)
        OR NOT (NEW.location <=> OLD.location)
        OR NOT (NEW.initial_photo <=> OLD.initial_photo)
    ) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Isi pengaduan tidak boleh diubah setelah diproses';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `complaint_updates`
--

CREATE TABLE `complaint_updates` (
  `update_id` bigint UNSIGNED NOT NULL,
  `complaint_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `update_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `documentation_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `inspected_at` datetime DEFAULT NULL,
  `inspection_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_basis` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_photo_reason` text COLLATE utf8mb4_unicode_ci
) ;

--
-- Dumping data for table `complaint_updates`
--

INSERT INTO `complaint_updates` (`update_id`, `complaint_id`, `user_id`, `update_type`, `old_status`, `new_status`, `message`, `documentation_photo`, `created_at`, `inspected_at`, `inspection_location`, `rejection_basis`, `no_photo_reason`) VALUES
(1, 1, 1, 'status', 'baru', 'diproses', 'Pengaduan mulai ditangani.', NULL, '2026-11-17 03:04:59', NULL, NULL, NULL, NULL),
(2, 1, 2, 'edit', NULL, NULL, 'Pengujian fungsi pencatatan riwayat.', NULL, '2026-11-24 12:59:23', NULL, NULL, NULL, NULL),
(3, 4, 3, 'edit', NULL, NULL, 'Warga memperbarui data pengaduan.', NULL, '2026-11-24 23:56:46', NULL, NULL, NULL, NULL),
(4, 4, 3, 'pembatalan', 'baru', 'dibatalkan', 'Warga membatalkan pengaduan.', NULL, '2026-11-25 00:13:35', NULL, NULL, NULL, NULL),
(5, 2, 3, 'balasan', NULL, NULL, 'Terima kasih atas tanggapan petugas. Saya menunggu tindak lanjut pengaduan ini.', NULL, '2026-11-25 04:27:02', NULL, NULL, NULL, NULL),
(6, 2, 1, 'response', NULL, NULL, 'Petugas telah menerima balasan Anda. Pengaduan akan kami tindak lanjuti sesuai prosedur.', NULL, '2026-11-25 04:34:08', NULL, NULL, NULL, NULL);

--
-- Triggers `complaint_updates`
--
DELIMITER $$
CREATE TRIGGER `trg_updates_tidak_dihapus` BEFORE DELETE ON `complaint_updates` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Riwayat penanganan tidak boleh dihapus.';
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_updates_tidak_diubah` BEFORE UPDATE ON `complaint_updates` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'Riwayat penanganan tidak boleh diubah.';
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `complaint_id` bigint UNSIGNED DEFAULT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `complaint_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 2, 1, 'Status diperbarui', 'Pengaduan LW-2026-001 sekarang berstatus Diproses.', 0, '2026-11-17 03:04:59'),
(2, 1, 2, 'Balasan Baru dari Warga', 'Warga telah mengirim balasan pada pengaduan nomor 2.', 1, '2026-11-25 04:27:02'),
(3, 3, 2, 'Tanggapan Baru', 'Petugas telah memberikan tanggapan pada pengaduan nomor 2.', 1, '2026-11-25 04:34:08');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`, `created_at`) VALUES
(2, 4, 'c2762f773eb85a13ec6e6d8d17264d317dc8a924c0ab86ef30f33bd369af3971', '2026-11-24 14:21:18', '2026-11-24 13:56:49', '2026-11-24 05:51:18'),
(3, 4, '5e29f0ca819bdbcf3704e54aed66c6142c93ef6275a49fa0f1c38194d872827e', '2026-11-24 14:49:31', '2026-11-24 14:26:51', '2026-11-24 06:19:31'),
(4, 4, 'e51eb7c88cbaa67cea0f4819034902d579b5877fccda94057fbb23b055710aca', '2020-01-01 00:00:00', NULL, '2026-11-24 06:35:49');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_sequence`
--

CREATE TABLE `ticket_sequence` (
  `id` tinyint UNSIGNED NOT NULL,
  `last_number` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_sequence`
--

INSERT INTO `ticket_sequence` (`id`, `last_number`) VALUES
(1, 4);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` bigint UNSIGNED NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `username`, `email`, `phone`, `password`, `role`, `created_at`) VALUES
(1, 'Petugas Satu', 'petugas1', 'petugas1@laporwarga.test', '081200000001', '$2y$10$mzyVy82W3D1JT/XbyYcpJOmd8mMlmBWmflmJxQUYJzYXwNwBVXV32', 'petugas', '2026-11-17 03:04:58'),
(2, 'Budi Santoso', 'budi', 'budi@laporwarga.test', '081200000002', '$2y$10$JIDJcBksE4FlAzZyCR6akudN1incwqsUMha9KOrvi4EWu9MlacDSq', 'warga', '2026-11-17 03:04:58'),
(3, 'Siti Aminah', 'siti', 'siti@laporwarga.test', '081200000003', '$2y$10$mzyVy82W3D1JT/XbyYcpJOmd8mMlmBWmflmJxQUYJzYXwNwBVXV32', 'warga', '2026-11-17 03:04:58'),
(4, 'Andi Pratama', 'andi_test', 'ammuthmainnah8@gmail.com', '081234567890', '$2y$10$E5lIWC/KgFMHX7wGSXe5D.8mBXm7273ocWZbHjiZxvD.upiyehcyK', 'warga', '2026-11-23 06:50:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`category_id`),
  ADD UNIQUE KEY `uk_categories_name` (`category_name`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`complaint_id`),
  ADD UNIQUE KEY `uk_complaints_ticket` (`ticket_number`),
  ADD KEY `fk_complaints_user` (`user_id`),
  ADD KEY `fk_complaints_category` (`category_id`);

--
-- Indexes for table `complaint_updates`
--
ALTER TABLE `complaint_updates`
  ADD PRIMARY KEY (`update_id`),
  ADD KEY `fk_updates_complaint` (`complaint_id`),
  ADD KEY `fk_updates_user` (`user_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `fk_notif_user` (`user_id`),
  ADD KEY `fk_notif_complaint` (`complaint_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_password_reset_token` (`token_hash`),
  ADD KEY `idx_password_reset_user` (`user_id`),
  ADD KEY `idx_password_reset_expiry` (`expires_at`);

--
-- Indexes for table `ticket_sequence`
--
ALTER TABLE `ticket_sequence`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uk_users_username` (`username`),
  ADD UNIQUE KEY `uk_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `category_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `complaint_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `complaint_updates`
--
ALTER TABLE `complaint_updates`
  MODIFY `update_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `fk_complaints_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`),
  ADD CONSTRAINT `fk_complaints_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `complaint_updates`
--
ALTER TABLE `complaint_updates`
  ADD CONSTRAINT `fk_updates_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`),
  ADD CONSTRAINT `fk_updates_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_complaint` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`complaint_id`),
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
