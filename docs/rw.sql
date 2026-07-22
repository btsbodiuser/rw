-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 21, 2026 at 03:43 PM
-- Server version: 9.1.0
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rw`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_types`
--

DROP TABLE IF EXISTS `activity_types`;
CREATE TABLE IF NOT EXISTS `activity_types` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_mn` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_types`
--

INSERT INTO `activity_types` (`id`, `name`, `name_mn`, `slug`, `icon`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'Trail Running', 'Трейл гүйлт', 'trail-running', '🏔️', 1, 1, '2026-07-19 04:23:28'),
(2, 'Road Running', 'Замын гүйлт', 'road-running', '🏃', 2, 1, '2026-07-19 04:23:28'),
(3, 'Hiking', 'Уулын аялал', 'hiking', '⛰️', 3, 1, '2026-07-19 04:23:28'),
(4, 'Cross Training', 'Дасгал хийх', 'cross-training', '💪', 4, 1, '2026-07-19 04:23:28'),
(5, 'Walking', 'Алхалт', 'walking', '🚶', 5, 1, '2026-07-19 04:23:28'),
(6, 'Gym', 'Фитнесс', 'gym', '🏋️', 6, 1, '2026-07-19 04:23:28');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
CREATE TABLE IF NOT EXISTS `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('super_admin','admin','pos_cashier') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `remember_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `username`, `password`, `name`, `role`, `remember_token`, `created_at`) VALUES
(1, 'admin', '$2y$10$FOuMPVdHdErJDgCrDKDrgOdZ.MQX/TnsOpbgo96pRyEX84cwlz8m2', 'Admin', 'super_admin', '0862a5acf8930c50cc9d44510118c434b11eee83c84627cf66f15ac398bac719', '2026-04-28 00:29:03');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int DEFAULT NULL,
  `actor_type` enum('customer','admin','system') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'system',
  `actor_id` int DEFAULT NULL,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_entity` (`entity_type`,`entity_id`),
  KEY `idx_actor` (`actor_type`,`actor_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `action`, `entity_type`, `entity_id`, `actor_type`, `actor_id`, `ip_address`, `details`, `created_at`) VALUES
(1, 'order_created', 'order', 1, 'customer', 1, '::1', '{\"total\": 629250, \"items_count\": 2, \"order_number\": \"RW64039781\"}', '2026-07-21 07:15:30'),
(2, 'order_status_changed', 'order', 1, 'admin', 1, '::1', '{\"new_status\": \"confirmed\", \"old_status\": \"pending\", \"order_number\": \"RW64039781\"}', '2026-07-21 07:26:53'),
(3, 'order_payment_changed', 'order', 1, 'admin', 1, '::1', '{\"order_number\": \"RW64039781\", \"new_payment_status\": \"paid\", \"old_payment_status\": \"pending\"}', '2026-07-21 07:26:55'),
(4, 'order_payment_changed', 'order', 1, 'admin', 1, '::1', '{\"order_number\": \"RW64039781\", \"new_payment_status\": \"paid\", \"old_payment_status\": \"paid\"}', '2026-07-21 07:26:56'),
(5, 'order_created', 'order', 2, 'customer', 1, '::1', '{\"total\": 57000, \"items_count\": 1, \"order_number\": \"RW04718868\"}', '2026-07-21 07:27:29'),
(6, 'order_status_changed', 'order', 1, 'admin', 1, '::1', '{\"new_status\": \"confirmed\", \"old_status\": \"confirmed\", \"order_number\": \"RW64039781\"}', '2026-07-21 07:28:07'),
(7, 'order_payment_changed', 'order', 1, 'admin', 1, '::1', '{\"order_number\": \"RW64039781\", \"new_payment_status\": \"paid\", \"old_payment_status\": \"paid\"}', '2026-07-21 07:28:11'),
(8, 'order_fulfillment_changed', 'order', 2, 'admin', 1, '::1', '{\"address\": \"123\", \"district_id\": 6, \"order_number\": \"RW04718868\", \"new_fulfillment\": \"delivery\", \"old_fulfillment\": \"delivery\"}', '2026-07-21 07:28:18'),
(9, 'order_status_changed', 'order', 2, 'admin', 1, '::1', '{\"new_status\": \"confirmed\", \"old_status\": \"pending\", \"order_number\": \"RW04718868\"}', '2026-07-21 07:28:30'),
(10, 'order_payment_changed', 'order', 2, 'admin', 1, '::1', '{\"order_number\": \"RW04718868\", \"new_payment_status\": \"paid\", \"old_payment_status\": \"pending\"}', '2026-07-21 07:28:32'),
(11, 'order_created', 'order', 3, 'customer', 3, '::1', '{\"total\": 57000, \"items_count\": 1, \"order_number\": \"RW62840787\"}', '2026-07-21 07:38:43'),
(12, 'order_created', 'order', 4, 'customer', 1, '::1', '{\"total\": 182000, \"items_count\": 1, \"order_number\": \"RW51864998\"}', '2026-07-21 08:07:19'),
(13, 'order_status_changed', 'order', 4, 'admin', 1, '::1', '{\"new_status\": \"confirmed\", \"old_status\": \"pending\", \"order_number\": \"RW51864998\"}', '2026-07-21 08:08:10'),
(14, 'order_payment_changed', 'order', 4, 'admin', 1, '::1', '{\"order_number\": \"RW51864998\", \"new_payment_status\": \"paid\", \"old_payment_status\": \"pending\"}', '2026-07-21 08:08:20'),
(15, 'order_created', 'order', 5, 'customer', 1, '::1', '{\"total\": 206000, \"items_count\": 1, \"order_number\": \"RW57260292\"}', '2026-07-21 08:08:37'),
(16, 'order_created', 'order', 6, 'customer', 6, '::1', '{\"total\": 50000, \"items_count\": 1, \"order_number\": \"RW09459827\"}', '2026-07-21 08:28:12'),
(17, 'order_cancelled', 'order', 0, 'system', NULL, '::1', '{\"order_number\": \"RW09459827\"}', '2026-07-21 08:28:15'),
(18, 'order_created', 'order', 7, 'customer', 1, '::1', '{\"total\": 206000, \"items_count\": 1, \"order_number\": \"RW10958294\"}', '2026-07-21 08:44:20'),
(19, 'customer_updated', 'customer', 1, 'admin', 1, '::1', '{\"name\": \"Battseren\", \"email\": \"\", \"phone\": \"60104889\"}', '2026-07-21 10:27:16'),
(20, 'order_created', 'order', 8, 'customer', 9, '::1', '{\"total\": 199000, \"items_count\": 1, \"order_number\": \"RW30067753\"}', '2026-07-21 10:28:10'),
(21, 'customer_updated', 'customer', 9, 'admin', 1, '::1', '{\"name\": \"Батцэцэг\", \"email\": \"\", \"phone\": \"88064889\"}', '2026-07-21 10:29:28'),
(22, 'register', 'customer', 10, 'customer', 10, '::1', '{\"method\": \"email\"}', '2026-07-21 10:30:22'),
(23, 'order_created', 'order', 9, 'customer', 11, '::1', '{\"total\": 249000, \"items_count\": 1, \"order_number\": \"RW90784082\"}', '2026-07-21 10:41:46'),
(24, 'order_status_changed', 'order', 9, 'admin', 1, '::1', '{\"new_status\": \"confirmed\", \"old_status\": \"pending\", \"order_number\": \"RW90784082\"}', '2026-07-21 10:41:54'),
(25, 'order_status_changed', 'order', 8, 'admin', 1, '::1', '{\"new_status\": \"confirmed\", \"old_status\": \"pending\", \"order_number\": \"RW30067753\"}', '2026-07-21 10:41:59'),
(26, 'order_payment_changed', 'order', 8, 'admin', 1, '::1', '{\"order_number\": \"RW30067753\", \"new_payment_status\": \"paid\", \"old_payment_status\": \"pending\"}', '2026-07-21 10:42:01'),
(27, 'order_payment_changed', 'order', 9, 'admin', 1, '::1', '{\"order_number\": \"RW90784082\", \"new_payment_status\": \"paid\", \"old_payment_status\": \"pending\"}', '2026-07-21 10:42:06');

-- --------------------------------------------------------

--
-- Table structure for table `bank_statement_imports`
--

DROP TABLE IF EXISTS `bank_statement_imports`;
CREATE TABLE IF NOT EXISTS `bank_statement_imports` (
  `id` int NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'khan_bank',
  `account_number` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `period_from` date DEFAULT NULL,
  `period_to` date DEFAULT NULL,
  `total_transactions` int NOT NULL DEFAULT '0',
  `qpay_transactions` int NOT NULL DEFAULT '0',
  `matched_transactions` int NOT NULL DEFAULT '0',
  `unmatched_transactions` int NOT NULL DEFAULT '0',
  `transfer_transactions` int DEFAULT '0',
  `transfer_matched` int DEFAULT '0',
  `transfer_unmatched` int DEFAULT '0',
  `bonum_transactions` int NOT NULL DEFAULT '0',
  `imported_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_transactions`
--

DROP TABLE IF EXISTS `bank_transactions`;
CREATE TABLE IF NOT EXISTS `bank_transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `import_id` int NOT NULL,
  `transaction_date` datetime NOT NULL,
  `credit_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `debit_amount` decimal(14,2) NOT NULL DEFAULT '0.00',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `counter_account` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_qpay` tinyint(1) NOT NULL DEFAULT '0',
  `is_transfer` tinyint(1) DEFAULT '0',
  `is_bonum` tinyint(1) NOT NULL DEFAULT '0',
  `qpay_ref` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qpay_charge` decimal(10,2) DEFAULT NULL,
  `order_id` int DEFAULT NULL,
  `cargo_payment_id` int DEFAULT NULL,
  `match_status` enum('matched','unmatched','amount_mismatch','no_order','ignored') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unmatched',
  `match_method` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_total` decimal(12,2) DEFAULT NULL,
  `expected_amount` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `import_id` (`import_id`),
  KEY `order_number` (`order_number`),
  KEY `order_id` (`order_id`),
  KEY `idx_cargo_payment_id` (`cargo_payment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

DROP TABLE IF EXISTS `blog_posts`;
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_mn` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slug` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `excerpt` text COLLATE utf8mb4_unicode_ci,
  `excerpt_mn` text COLLATE utf8mb4_unicode_ci,
  `body_mn` longtext COLLATE utf8mb4_unicode_ci,
  `body` longtext COLLATE utf8mb4_unicode_ci,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_published` tinyint DEFAULT '0',
  `published_at` timestamp NULL DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `title`, `title_mn`, `slug`, `excerpt`, `excerpt_mn`, `body_mn`, `body`, `image`, `is_published`, `published_at`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'What are stability shoes, and why do runners need them?', 'What are stability shoes, and why do runners need them?', 'what-are-stability-shoes-and-why-do-runners-need-them', NULL, 'You\'re deep into a marathon, lungs burning, legs heavy. When you hit that wall, the last thing you want is less support where it matters most.', '<p>You\'re deep into a marathon, lungs burning, legs heavy. When you hit that wall, the last thing you want is less support where it matters most.</p><p>Support shoes are one of the most useful tools a runner can have, whether you’re new to the sport or a professional. Yet they’re also one of the most misunderstood.</p><p>This guide breaks down why runners need support shoes, how stability works and how to choose the right pair for your goals.</p><h2><strong>What are stability shoes, and why are they important?</strong></h2><p>Stability shoes are designed for runners whose feet roll excessively inward (known as overpronation). These shoes often use firmer foam on the inner side or other guiding elements to help steer the foot forward toward a more neutral alignment.</p><p>Neutral shoes, by contrast, are lighter and more flexible for runners who don\'t need corrective support. Racing shoes are the lightest of all, prioritizing speed with minimal cushioning and support. They’re the ideal option for short, fast efforts.</p><p>Pronation, the natural rolling motion as the foot hits the ground, is your body’s built-in shock absorption and propulsion. It softens impact, then springs you forward. But when your muscles tire or your mechanics can’t maintain that pattern, alignment can falter. That’s when form breaks down and injury risk climbs.</p><p>Support shoes help maintain that efficient rolling motion, especially when fatigue hits.</p><h2><strong>Why every runner can benefit from stability shoes</strong></h2><p>Even neutral runners can benefit from stability in certain situations.</p><p>Here’s where support becomes especially valuable:</p><p><strong>- New runners:</strong> Early on, your muscles and tendons are still adapting. Added support helps them handle the increased load of regular running. <strong>- Runners returning from injury:</strong> Stability shoes offer structure as you rebuild strength and confidence. <strong>- Long-distance runners:</strong> The longer the run, or the more <a href=\"https://www.on.com/en-ch/stories/choosing-the-best-trail-running-shoes\" rel=\"noopener noreferrer\" target=\"_blank\" style=\"color: rgb(0, 0, 0);\"><strong>difficult the terrain</strong></a>, the more fatigue sets in. Support shoes help maintain alignment late into a session. <strong>- Easy or recovery runs:</strong> When you’re going for an easier session between harder efforts, support shoes can ease strain and help <a href=\"https://www.on.com/en-ch/stories/performance-running-the-runners-health-check-how-to-remain-injury-free-in-the-long-run\" rel=\"noopener noreferrer\" target=\"_blank\" style=\"color: rgb(0, 0, 0);\"><strong>prevent future injury</strong></a>.</p>', NULL, '6a3c93e9017bb_1782354921.jpg', 1, '2026-06-25 02:30:00', 10, '2026-06-25 02:35:59', '2026-06-25 03:17:42'),
(2, 'Running goodnes', 'Гүйлтийн ач тус', 'guyltiyn-ach-tus', NULL, 'Гүйлт нь бие махбодын эрүүл мэнд, сэтгэл зүйн тогтвортой байдал, оюуны болон сэтгэн бодох чадварт олон талын эерэг нөлөөллийг үзүүлдэг бидэнд хамгийн тустай бөгөөд энгийн дасгал хөдөлгөөнүүдийн нэг юм.', '<p>Гүйлт нь бие махбодын эрүүл мэнд, сэтгэл зүйн тогтвортой байдал, оюуны болон сэтгэн бодох чадварт олон талын эерэг нөлөөллийг үзүүлдэг бидэнд хамгийн тустай бөгөөд энгийн дасгал хөдөлгөөнүүдийн нэг юм. Гүйгчид болон спортоор тогтмол хичээллэдэг хүмүүс дасгалын үед болон дасгалын дараа яг юу мэдэрдэг бол. Тэд бие, сэтгэлээрээ яагаад дасгал хөдөлгөөн хийхэд шунан дурлаад байна вэ?</p><p>Мэдээж эрүүл мэндэд үзүүлэх эерэг нөлөө, цагийг үр дүнтэй өнгөрөөх хүсэл, өөртөө тавьсан зорилгодоо хүрэх гээд олон хүчин зүйлс нөлөөлж байгаа нь эргэлзээгүй. Үүнээс гадна гүйж эсвэл өндөр ачаалалтай дасгал хөдөлгөөн хийх үед тухайн хүмүүс “Runner\'s high” буюу “Гүйгчийн баяр хөөр”-ийг мэдэрдэг гэнэ. Гүйж эхлэх үед амьсгал түргэсэж, зүрхний цохилт нэмэгдэж, цусны эргэлт эрчимждэг энэ мэт үргэлжилсээр аажмаар өндөр хэмжээнд хүрч, жигдрэх үед хүний бие “эндорфин” буюу “аз жаргалын даавар” ялгаруулах бөгөөд энэ үед тухайн хүн баяр хөөрийн байдал руу (euphoric state) шилжинэ. Яг л хайрлаж дурлах, сайхан мэдээ сонсох, аз тохиох, аялах зугаалах эсвэл согтууруулах ундаа хэрэглэсэн үед баяр баясал, аз жаргал, эрч хүчээр дүүрэн байх үед үүсдэг мэдрэмжүүдтэй гүйлтээс мэдэрнэ гэсэн үг.</p><p>Түүнчлэн гүйсний дараа хүний биед “Эндоканнабиноид” гэх каннибастай (мансууруулах бодис) адил төстэй бүтэцтэй нэгдэл үүсдэг ба үүний нөлөөллөөр тухайн хүний сэтгэл санаа хөөрөлд ордог байх магадлалтай талаар зарим судлаачид дурдсан байдаг. Тиймээс гүйснээр түр хугацаанд сэтгэл гутралыг шинж тэмдгүүдийг (сэтгэлээр унах, гуниглах, нойргүйдэх, анхаарал сулрах, өөртөө итгэлгүй байх гэх мэт) бууруулж, тайвшралыг мэдрэх боломжтой гэж үздэг.</p><p>Энэхүү сэтгэл ханамжтай байдал нь олон хүний гүйлтийн спортоор хичээллэх бас нэгэн шалтгаан байж болох юм. Үүнээс гадна бидний мэдвэл зохих гүйлтийн зарим ач тусыг дурдъя.</p><ul><li>Зүрхний үйл ажиллагааг сайжруулж, цусны эргэлтийг идэвхжүүлснээр зүрх судасны өвчний эрсдэлийг бууруулна, зүрхний ачаалал даах чадварыг нэмэгдүүлнэ.</li><li>Гүйх үед биеийн ачаалал авснаар биед хуримтлагдсан илчлэгийг ихээр зарцуулдаг тул биеийн жингээ зохицуулахад болон хасахад тусалдаг. Мөн гүйх үед бие ядардаг хэдий ч стрессийн түвшин буурч, нойрны чанар сайжирдаг. Олон олон гүйгчид илүү хурдан унтаж, илүү амархан сэрдэг тухайгаа хуваалцсан байдаг.</li><li>Тогтмол гүйдэг хүмүүсийн дунд Альцгеймер зэрэг танин мэдэхүйн өвчин эмгэгүүд тохиолдох магадлал бага байдаг, түүнчлэн гүйлт нь санах ой болон суралцах чадварыг нэмэгдүүлдэг гэж үздэг.</li><li>Гүйлтийг дадал хэвшил болгосноор хүний биеийн ерөнхий дархлаа тогтолцоо сайжирдаг. Улмаар хөнгөн халдварт өвчин, ханиад зэргээс урьдчилан сэргийлж чадна. Үүгээр ч зогсохгүй гүйгчдийн ерөнхий эрүүл мэнд сайн байдаг тул дасгал хөдөлгөөн хийдэггүй хүмүүстэй харьцуулахад аливаа өвчин эмгэг тусах магадлал бага, урт наслах боломж өндөр гэж олон эх сурвалжууд дурдсан байна.</li></ul><p>Илүү өргөн хүрээгээр харвал хүмүүс гүйх хоббитой болсноор ижил төрлийн сонирхолтой нийгмийн бүлэгт багтаж, гүйлтийн клубүүдийн гишүүн болж хүрээллээ тэлж цагийн хамтдаа үр бүтээлтэй өнгөрөөх нөхцөлийг бий болгох боломжтой. Хүмүүстэй туршлагаа солилцож, эерэг харилцаатай байснаар нь бие биедээ урам зориг өгч, тууштай байхад тусалдаг.</p><p>Эцэст дүгнэж хэлэхэд гүйлт нь бидний бие махбод төдийгүй сэтгэл зүй, оюуны чадавх, нийгмийн харилцаанд хүртэл эерэг нөлөөг үзүүлэх боломжтой. Тогтмол гүйж хэвшсэнээр таны өдөр тутмын амьдралын хэв маягаас эхлээд, амьдралын чанарт ихээхэн эерэг өөрчлөлтүүдийг авчирна. Хэрэв та анх гүйж эхлэх гэж байгаа эсвэл урьд өмнө нь дасгал хөдөлгөөн хийж байсан туршлагагүй бол биеийн байлдаа эмчид үзүүлж, мэргэжлийн багш дасгалжуулагчаас зөвлөгөө аваарай. Танд аз жаргал, эрүүл энхийг хүсье.</p>', NULL, '6a5ef67914ae0_1784608377.jpg', 1, '2026-07-21 04:32:00', 10, '2026-07-21 04:33:14', '2026-07-21 04:33:14');

-- --------------------------------------------------------

--
-- Table structure for table `cargo_batches`
--

DROP TABLE IF EXISTS `cargo_batches`;
CREATE TABLE IF NOT EXISTS `cargo_batches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `due_date` datetime NOT NULL,
  `status` enum('open','closed','shipping','receiving','arrived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `cargo_rate_per_kg` decimal(10,2) NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `sms_sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cargo_batches`
--

INSERT INTO `cargo_batches` (`id`, `name`, `due_date`, `status`, `cargo_rate_per_kg`, `notes`, `sms_sent_at`, `created_at`, `updated_at`) VALUES
(7, '2026-07-01', '2026-07-31 16:40:00', 'open', 5000.00, '', NULL, '2026-07-19 08:40:25', '2026-07-19 08:40:25');

-- --------------------------------------------------------

--
-- Table structure for table `cargo_payments`
--

DROP TABLE IF EXISTS `cargo_payments`;
CREATE TABLE IF NOT EXISTS `cargo_payments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `payment_method` enum('qpay','bonum','storepay') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `invoice_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','paid','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_order` (`order_id`),
  KEY `idx_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_mn` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `slug`, `name`, `name_mn`, `icon`, `image`, `is_active`, `sort_order`, `created_at`) VALUES
(4, 'outlet', 'Outlet', 'Аутлет', '', 'uploads/categories/6a05548f68b74_1778734223.jpeg', 1, 40, '2026-04-30 04:48:27'),
(16, 'shoes', 'Shoes', 'Пүүз', '', 'uploads/categories/6a3ca3d68a709_1782358998.jpg', 1, 10, '2026-05-03 15:14:22'),
(18, 'apparel', 'Apparel', 'Хувцас', '', 'uploads/categories/6a3ca2a99efa3_1782358697.jpg', 1, 20, '2026-05-12 11:05:28'),
(19, 'accessories', 'Accessories', 'Дагалдах', '', 'uploads/categories/6a3ca2ae9e7a0_1782358702.jpg', 1, 30, '2026-05-13 12:55:27');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('new','read','spam') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contact_messages_status` (`status`),
  KEY `idx_contact_messages_ip_created` (`ip_address`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `phone`, `message`, `ip_address`, `user_agent`, `status`, `created_at`) VALUES
(15, 'Battseren', 'btsbodi@gmail.com', '88024889', 'sain bna uu tanai bguullagatia hamtran ajiiah huseltei bna', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0', 'read', '2026-07-21 15:22:53');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

DROP TABLE IF EXISTS `customers`;
CREATE TABLE IF NOT EXISTS `customers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facebook_id` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  KEY `idx_google_id` (`google_id`),
  KEY `idx_facebook_id` (`facebook_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `phone`, `password`, `name`, `email`, `google_id`, `facebook_id`, `avatar`, `created_at`) VALUES
(1, '60104889', '$2y$10$rWn8/dxlaAMAEZnHrE2z6Oq58rddesaZtFMUKtrcSw6VioDIAdLyK', 'Battseren', '', '', '', '', '2026-07-21 07:15:19'),
(4, '70002821', '$2y$10$gKEdO3YaqUAPb9kQcdBGWeMhPkjyElil8HCnBxABwYVaPwP/ehvMC', 'QA QPay Inline', NULL, NULL, NULL, NULL, '2026-07-21 08:25:25'),
(9, '88064889', '$2y$10$ORd/Vx4vBeJ8P7vq1.U1YurwfrKDx6pMpqUpHcRQr9qh9vbj/xUT6', 'Батцэцэг', '', '', '', '', '2026-07-21 10:27:48'),
(10, '60204889', '$2y$10$qeSqvJvWwn3cb.aU15Hu0.42clF7FZJHScbYX9G1ZM339MBIUHBsC', 'Battseren', 'btsbodi@gmail.com', NULL, NULL, NULL, '2026-07-21 10:30:22'),
(11, '88024889', '$2y$10$AHTypIQ7ZnkjZK5ZcJxZv.kxQBazQQaKrZCpZvajzIfg16wFT/s6W', 'God', NULL, NULL, NULL, NULL, '2026-07-21 10:40:33');

-- --------------------------------------------------------

--
-- Table structure for table `customer_addresses`
--

DROP TABLE IF EXISTS `customer_addresses`;
CREATE TABLE IF NOT EXISTS `customer_addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `label` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `district_id` int DEFAULT NULL,
  `khoroo_id` int DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `is_default` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `district_id` (`district_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_addresses`
--

INSERT INTO `customer_addresses` (`id`, `customer_id`, `label`, `district_id`, `khoroo_id`, `address`, `detail_address`, `is_default`, `created_at`) VALUES
(1, 1, '', 6, 130, '123', '456', 1, '2026-07-21 07:27:29');

-- --------------------------------------------------------

--
-- Table structure for table `customer_sessions`
--

DROP TABLE IF EXISTS `customer_sessions`;
CREATE TABLE IF NOT EXISTS `customer_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `customer_id` (`customer_id`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_sessions`
--

INSERT INTO `customer_sessions` (`id`, `customer_id`, `token`, `expires_at`, `created_at`) VALUES
(1, 1, '9df829c3bf6d375a97dee33a4b979f8b4c2ecfd236efdbaecc0357b12428e593', '2026-07-28 15:15:19', '2026-07-21 07:15:19'),
(4, 4, 'f8890426cc9a805d4281d127c6a9c1c7c32b22c6ec859093b83ce4b17edee918', '2026-07-28 16:25:25', '2026-07-21 08:25:25'),
(10, 9, 'aa8f388b83463bc6d2fa9b45e278033ae5f093c726937947f96dbbc90ad4c716', '2026-07-28 18:27:48', '2026-07-21 10:27:48'),
(11, 9, 'f83282ff9d9b5b76d25c702ff722845331ca415465798172b7d28f632a135fa7', '2026-07-28 18:28:59', '2026-07-21 10:28:59'),
(12, 10, '0820489e41f854fba530cacb5ff119572fdb565f409f38a821d5be16b887f5eb', '2026-07-28 18:30:22', '2026-07-21 10:30:22'),
(13, 10, '0cf721ec8034ce26d9a26f1720591db8dddb6c93603a88c401457a54fea82eac', '2026-07-28 18:30:41', '2026-07-21 10:30:41'),
(14, 11, '2fd72058803339957408a15eda383cf688143426b5429020664728b41c83c957', '2026-07-28 18:40:33', '2026-07-21 10:40:33'),
(15, 11, '82304623113ea172d9b3b69dff21b2740d627f4dd24c625c7dae12772ae09dc5', '2026-07-28 18:41:40', '2026-07-21 10:41:40');

-- --------------------------------------------------------

--
-- Table structure for table `deliveries`
--

DROP TABLE IF EXISTS `deliveries`;
CREATE TABLE IF NOT EXISTS `deliveries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `batch_id` int DEFAULT NULL,
  `order_id` int NOT NULL,
  `driver_id` int NOT NULL,
  `status` enum('assigned','picked_up','delivered','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'assigned',
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `picked_up_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `idx_driver` (`driver_id`),
  KEY `idx_status` (`status`),
  KEY `idx_batch` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_batches`
--

DROP TABLE IF EXISTS `delivery_batches`;
CREATE TABLE IF NOT EXISTS `delivery_batches` (
  `id` int NOT NULL AUTO_INCREMENT,
  `driver_id` int NOT NULL,
  `status` enum('assigned','in_progress','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'assigned',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_driver` (`driver_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `delivery_drivers`
--

DROP TABLE IF EXISTS `delivery_drivers`;
CREATE TABLE IF NOT EXISTS `delivery_drivers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `access_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_access_token` (`access_token`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `delivery_drivers`
--

INSERT INTO `delivery_drivers` (`id`, `name`, `phone`, `is_active`, `access_token`, `created_at`) VALUES
(1, 'delco', '99119911', 1, 'delco', '2026-04-30 05:11:59');

-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

DROP TABLE IF EXISTS `districts`;
CREATE TABLE IF NOT EXISTS `districts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_mn` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `districts`
--

INSERT INTO `districts` (`id`, `name`, `name_mn`, `is_active`, `sort_order`) VALUES
(1, 'Bayangol', 'Баянгол', 1, 1),
(2, 'Bayanzurkh', 'Баянзүрх', 1, 2),
(3, 'Chingeltei', 'Чингэлтэй', 1, 3),
(4, 'Sukhbaatar', 'Сүхбаатар', 1, 4),
(5, 'Khan-Uul', 'Хан-Уул', 1, 5),
(6, 'Songinokhairkhan', 'Сонгинохайрхан', 1, 6),
(7, 'Nalaikh', 'Налайх', 1, 7),
(8, 'Baganuur', 'Багануур', 1, 8),
(9, 'Bagakhangai', 'Багахангай', 1, 9),
(10, 'Arkhangai', 'Архангай аймаг', 1, 10),
(11, 'Bayan-Olgii', 'Баян-Өлгий аймаг', 1, 11),
(12, 'Bayankhongor', 'Баянхонгор аймаг', 1, 12),
(13, 'Bulgan', 'Булган аймаг', 1, 13),
(14, 'Govi-Altai', 'Говь-Алтай аймаг', 1, 14),
(15, 'Govisumber', 'Говьсүмбэр аймаг', 1, 15),
(16, 'Darkhan-Uul', 'Дархан-Уул аймаг', 1, 16),
(17, 'Dornogobi', 'Дорноговь аймаг', 1, 17),
(18, 'Dornod', 'Дорнод аймаг', 1, 18),
(19, 'Dundgobi', 'Дундговь аймаг', 1, 19),
(20, 'Zavkhan', 'Завхан аймаг', 1, 20),
(21, 'Orkhon', 'Орхон аймаг', 1, 21),
(22, 'Ovorkhangai', 'Өвөрхангай аймаг', 1, 22),
(23, 'Omnogobi', 'Өмнөговь аймаг', 1, 23),
(24, 'Sukhbaatar-aimag', 'Сүхбаатар аймаг', 1, 24),
(25, 'Selenge', 'Сэлэнгэ аймаг', 1, 25),
(26, 'Tuv', 'Төв аймаг', 1, 26),
(27, 'Uvs', 'Увс аймаг', 1, 27),
(28, 'Khovd', 'Ховд аймаг', 1, 28),
(29, 'Khovsgol', 'Хөвсгөл аймаг', 1, 29),
(30, 'Khentii', 'Хэнтий аймаг', 1, 30);

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

DROP TABLE IF EXISTS `faqs`;
CREATE TABLE IF NOT EXISTS `faqs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `question` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `answer` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_faq_active_sort` (`is_active`,`sort_order`),
  KEY `idx_faq_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `category`, `question`, `answer`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Захиалга', 'Хэрхэн захиалга өгөх вэ?', 'Та хүссэн бараагаа сонгоод сагсанд нэмнэ. Дараа нь \"Сагс\" руу орж, хаяг болон холбоо барих мэдээллээ оруулаад захиалгаа баталгаажуулна. \r\nЗахиалга төлбөр 100% төлөгдсөнөөр албан ёсоор баталгаажна.', 10, 1, '2026-05-01 01:55:23', '2026-06-08 03:42:20'),
(2, 'Захиалга', 'Захиалгаа цуцлах боломжтой юу?', 'Тийм, бараа хүргэгдээгүй бол захиалгаа цуцлах боломжтой. \r\nЗахиалга цуцлах хүсэлт гаргах бол захиалгын дугаараа дэлгүүрийн вэб хуудасны чат хэсгээр илгээнэ үү. \r\n\r\nХэрэв төлбөр төлсөн бол 3-5 хоногийн дотор буцааж олгоно.', 20, 1, '2026-05-01 01:55:23', '2026-06-08 03:41:14'),
(3, 'Захиалга', 'Урьдчилсан захиалга гэж юу вэ?', 'Урьдчилсан захиалга гэдэг нь Солонгосын том дэлгүүрүүдээс (E-mart, Costco, Lotte Mart, Olive Young) шууд захиалах бараа юм. Эдгээр бараа манай агуулахад байхгүй бөгөөд таны захиалгын дагуу Солонгосоос авчирдаг. Хүргэлтийн хугацаа 14-21 өдөр.', 30, 1, '2026-05-01 01:55:23', '2026-05-01 01:55:23'),
(4, 'Төлбөр', 'Ямар төлбөрийн хэлбэрүүдтэй вэ?', 'Бид QPay, бэлэн мөнгө болон картаар төлбөр хүлээн авдаг. Онлайнаар захиалахдаа QPay-ээр урьдчилж төлөх эсвэл бараа хүлээн авахдаа төлбөрөө төлөх боломжтой.', 40, 1, '2026-05-01 01:55:23', '2026-05-01 01:55:23'),
(6, 'Хүргэлт', 'Хүргэлт хэдэн өдөр үргэлжилдэг вэ?', 'Бэлэн бараа 1-2 өдөрт, урьдчилсан захиалга 14-21 өдөрт хүргэгддэг. Хүргэлтийн хугацаа танай хаягаас хамаарна.', 60, 1, '2026-05-01 01:55:23', '2026-05-01 01:55:23'),
(7, 'Хүргэлт', 'Хүргэлтийн төлбөр хэд вэ?', 'Хүргэлтийн үнэ: Улаанбаатар хотын бүх бүсэд 6,000₮. Овор хэмжээ ихтэй барааны хүргэлтийн төлбөр 8,000₮ байна.', 70, 1, '2026-05-01 01:55:23', '2026-06-08 03:46:54'),
(8, 'Хүргэлт', 'Хүргэлтийн хүрээ хаана байдаг вэ?', 'Бид зөвхөн Улаанбаатар хотын төвийн 6 дүүрэгт хүргэлт үйлчилгээ үзүүлдэг.', 80, 1, '2026-05-01 01:55:23', '2026-05-01 01:55:23'),
(9, 'Бараа бүтээгдэхүүн', 'Бүх бараа баталгаатай ирдэг үү?', 'Тийм, бидний бүх бараа дэлхийн алдартай дэлгүүрүүд болох E-mart, Costco, Lotte Mart, Olive Young-аас шууд авчирдаг. Энэ нь чанар баталгаа юм.', 90, 1, '2026-05-01 01:55:23', '2026-06-25 03:19:11'),
(10, 'Бараа бүтээгдэхүүн', 'Бараа дуусах үед хэрхэн мэдэх вэ?', 'Агуулахад байгаа бараанууд дуусах шахсан үед \"Бага үлдсэн\" гэсэн тэмдэг гарна. Урьдчилсан захиалгын барааны тоо хязгаартай бол боломжит үлдэгдэл тоо автоматаар харагдана.', 100, 1, '2026-05-01 01:55:23', '2026-06-08 03:38:08'),
(11, 'Данс', 'Данс үүсгэх шаардлагатай юу?', 'Үгүй, данс үүсгэхгүйгээр захиалга өгч болно. Гэхдээ данс үүсгэвэл захиалгынхаа түүхийг харах, хүргэлтийн мэдээлэл хадгалах зэрэг олон давуу талтай.', 110, 1, '2026-05-01 01:55:23', '2026-05-01 01:55:23'),
(12, 'Данс', 'Хэрхэн данс үүсгэх вэ?', 'Баруун дээд булангаас \"Нэвтрэх\" товч дарж, \"Бүртгүүлэх\" сонголтыг сонгоно. Утасны дугаар болон нууц үг оруулаад бүртгэлээ үүсгэнэ.', 120, 1, '2026-05-01 01:55:23', '2026-05-01 01:55:23');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_arrivals`
--

DROP TABLE IF EXISTS `inventory_arrivals`;
CREATE TABLE IF NOT EXISTS `inventory_arrivals` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cargo_batch_id` int DEFAULT NULL,
  `arrival_date` date NOT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_batch` (`cargo_batch_id`),
  KEY `idx_date` (`arrival_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_arrival_items`
--

DROP TABLE IF EXISTS `inventory_arrival_items`;
CREATE TABLE IF NOT EXISTS `inventory_arrival_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `arrival_id` int NOT NULL,
  `product_id` int NOT NULL,
  `variant_id` int DEFAULT NULL,
  `quantity_received` int NOT NULL DEFAULT '1',
  `quantity_matched` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_arrival` (`arrival_id`),
  KEY `idx_product_variant` (`product_id`,`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `khoroos`
--

DROP TABLE IF EXISTS `khoroos`;
CREATE TABLE IF NOT EXISTS `khoroos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `district_id` int NOT NULL,
  `number` int NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `district_id` (`district_id`)
) ENGINE=InnoDB AUTO_INCREMENT=535 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `khoroos`
--

INSERT INTO `khoroos` (`id`, `district_id`, `number`, `name`) VALUES
(1, 1, 1, NULL),
(2, 1, 2, NULL),
(3, 1, 3, NULL),
(4, 1, 4, NULL),
(5, 1, 5, NULL),
(6, 1, 6, NULL),
(7, 1, 7, NULL),
(8, 1, 8, NULL),
(9, 1, 9, NULL),
(10, 1, 10, NULL),
(11, 1, 11, NULL),
(12, 1, 12, NULL),
(13, 1, 13, NULL),
(14, 1, 14, NULL),
(15, 1, 15, NULL),
(16, 1, 16, NULL),
(17, 1, 17, NULL),
(18, 1, 18, NULL),
(19, 1, 19, NULL),
(20, 1, 20, NULL),
(21, 1, 21, NULL),
(22, 1, 22, NULL),
(23, 1, 23, NULL),
(24, 2, 1, NULL),
(25, 2, 2, NULL),
(26, 2, 3, NULL),
(27, 2, 4, NULL),
(28, 2, 5, NULL),
(29, 2, 6, NULL),
(30, 2, 7, NULL),
(31, 2, 8, NULL),
(32, 2, 9, NULL),
(33, 2, 10, NULL),
(34, 2, 11, NULL),
(35, 2, 12, NULL),
(36, 2, 13, NULL),
(37, 2, 14, NULL),
(38, 2, 15, NULL),
(39, 2, 16, NULL),
(40, 2, 17, NULL),
(41, 2, 18, NULL),
(42, 2, 19, NULL),
(43, 2, 20, NULL),
(44, 2, 21, NULL),
(45, 2, 22, NULL),
(46, 2, 23, NULL),
(47, 2, 24, NULL),
(48, 2, 25, NULL),
(49, 2, 26, NULL),
(50, 2, 27, NULL),
(51, 2, 28, NULL),
(52, 3, 1, NULL),
(53, 3, 2, NULL),
(54, 3, 3, NULL),
(55, 3, 4, NULL),
(56, 3, 5, NULL),
(57, 3, 6, NULL),
(58, 3, 7, NULL),
(59, 3, 8, NULL),
(60, 3, 9, NULL),
(61, 3, 10, NULL),
(62, 3, 11, NULL),
(63, 3, 12, NULL),
(64, 3, 13, NULL),
(65, 3, 14, NULL),
(66, 3, 15, NULL),
(67, 3, 16, NULL),
(68, 3, 17, NULL),
(69, 3, 18, NULL),
(70, 3, 19, NULL),
(71, 4, 1, NULL),
(72, 4, 2, NULL),
(73, 4, 3, NULL),
(74, 4, 4, NULL),
(75, 4, 5, NULL),
(76, 4, 6, NULL),
(77, 4, 7, NULL),
(78, 4, 8, NULL),
(79, 4, 9, NULL),
(80, 4, 10, NULL),
(81, 4, 11, NULL),
(82, 4, 12, NULL),
(83, 4, 13, NULL),
(84, 4, 14, NULL),
(85, 4, 15, NULL),
(86, 4, 16, NULL),
(87, 4, 17, NULL),
(88, 4, 18, NULL),
(89, 4, 19, NULL),
(90, 4, 20, NULL),
(91, 5, 1, NULL),
(92, 5, 2, NULL),
(93, 5, 3, NULL),
(94, 5, 4, NULL),
(95, 5, 5, NULL),
(96, 5, 6, NULL),
(97, 5, 7, NULL),
(98, 5, 8, NULL),
(99, 5, 9, NULL),
(100, 5, 10, NULL),
(101, 5, 11, NULL),
(102, 5, 12, NULL),
(103, 5, 13, NULL),
(104, 5, 14, NULL),
(105, 5, 15, NULL),
(106, 5, 16, NULL),
(107, 5, 17, NULL),
(108, 5, 18, NULL),
(109, 5, 19, NULL),
(110, 5, 20, NULL),
(111, 5, 21, NULL),
(112, 5, 22, NULL),
(113, 6, 1, NULL),
(114, 6, 2, NULL),
(115, 6, 3, NULL),
(116, 6, 4, NULL),
(117, 6, 5, NULL),
(118, 6, 6, NULL),
(119, 6, 7, NULL),
(120, 6, 8, NULL),
(121, 6, 9, NULL),
(122, 6, 10, NULL),
(123, 6, 11, NULL),
(124, 6, 12, NULL),
(125, 6, 13, NULL),
(126, 6, 14, NULL),
(127, 6, 15, NULL),
(128, 6, 16, NULL),
(129, 6, 17, NULL),
(130, 6, 18, NULL),
(131, 6, 19, NULL),
(132, 6, 20, NULL),
(133, 6, 21, NULL),
(134, 6, 22, NULL),
(135, 6, 23, NULL),
(136, 6, 24, NULL),
(137, 6, 25, NULL),
(138, 6, 26, NULL),
(139, 6, 27, NULL),
(140, 6, 28, NULL),
(141, 6, 29, NULL),
(142, 6, 30, NULL),
(143, 6, 31, NULL),
(144, 6, 32, NULL),
(145, 1, 24, NULL),
(146, 1, 25, NULL),
(147, 1, 26, NULL),
(148, 1, 27, NULL),
(149, 1, 28, NULL),
(150, 1, 29, NULL),
(151, 1, 30, NULL),
(152, 1, 31, NULL),
(153, 1, 32, NULL),
(154, 1, 33, NULL),
(155, 1, 34, NULL),
(156, 2, 29, NULL),
(157, 2, 30, NULL),
(158, 2, 31, NULL),
(159, 2, 32, NULL),
(160, 2, 33, NULL),
(161, 2, 34, NULL),
(162, 2, 35, NULL),
(163, 2, 36, NULL),
(164, 2, 37, NULL),
(165, 2, 38, NULL),
(166, 2, 39, NULL),
(167, 2, 40, NULL),
(168, 2, 41, NULL),
(169, 2, 42, NULL),
(170, 2, 43, NULL),
(171, 3, 20, NULL),
(172, 3, 21, NULL),
(174, 3, 22, NULL),
(175, 3, 23, NULL),
(176, 3, 24, NULL),
(177, 5, 23, NULL),
(178, 5, 24, NULL),
(179, 5, 25, NULL),
(180, 6, 33, NULL),
(181, 6, 34, NULL),
(182, 6, 35, NULL),
(183, 6, 36, NULL),
(184, 6, 37, NULL),
(185, 6, 38, NULL),
(186, 6, 39, NULL),
(187, 6, 40, NULL),
(188, 6, 41, NULL),
(189, 6, 42, NULL),
(190, 6, 43, NULL),
(191, 7, 1, NULL),
(192, 7, 2, NULL),
(193, 7, 3, NULL),
(194, 7, 4, NULL),
(195, 7, 5, NULL),
(196, 7, 6, NULL),
(197, 7, 7, NULL),
(198, 8, 1, NULL),
(199, 8, 2, NULL),
(200, 8, 3, NULL),
(201, 8, 4, NULL),
(202, 8, 5, NULL),
(203, 9, 1, NULL),
(204, 9, 2, NULL),
(205, 10, 1, 'Эрдэнэбулган'),
(206, 10, 2, 'Батцэнгэл'),
(207, 10, 3, 'Булган'),
(208, 10, 4, 'Жаргалант'),
(209, 10, 5, 'Ихтамир'),
(210, 10, 6, 'Өгийнуур'),
(211, 10, 7, 'Өлзийт'),
(212, 10, 8, 'Өндөр-Улаан'),
(213, 10, 9, 'Тариат'),
(214, 10, 10, 'Төвшрүүлэх'),
(215, 10, 11, 'Хайрхан'),
(216, 10, 12, 'Хангай'),
(217, 10, 13, 'Хашаат'),
(218, 10, 14, 'Хотонт'),
(219, 10, 15, 'Цахир'),
(220, 10, 16, 'Цэнхэр'),
(221, 10, 17, 'Цэцэрлэг'),
(222, 10, 18, 'Чулуут'),
(223, 10, 19, 'Эрдэнэмандал'),
(224, 11, 1, 'Өлгий'),
(225, 11, 2, 'Алтай'),
(226, 11, 3, 'Алтанцөгц'),
(227, 11, 4, 'Баяннуур'),
(228, 11, 5, 'Бугат'),
(229, 11, 6, 'Булган'),
(230, 11, 7, 'Буянт'),
(231, 11, 8, 'Дэлүүн'),
(232, 11, 9, 'Ногооннуур'),
(233, 11, 10, 'Сагсай'),
(234, 11, 11, 'Толбо'),
(235, 11, 12, 'Улаанхус'),
(236, 11, 13, 'Цэнгэл'),
(237, 12, 1, 'Баянхонгор'),
(238, 12, 2, 'Баацагаан'),
(239, 12, 3, 'Баян-Өндөр'),
(240, 12, 4, 'Баянбулаг'),
(241, 12, 5, 'Баянговь'),
(242, 12, 6, 'Баянлиг'),
(243, 12, 7, 'Баянцагаан'),
(244, 12, 8, 'Баян-Овоо'),
(245, 12, 9, 'Богд'),
(246, 12, 10, 'Бөмбөгөр'),
(247, 12, 11, 'Бууцагаан'),
(248, 12, 12, 'Галуут'),
(249, 12, 13, 'Гурванбулаг'),
(250, 12, 14, 'Жаргалант'),
(251, 12, 15, 'Жинст'),
(252, 12, 16, 'Заг'),
(253, 12, 17, 'Өлзийт'),
(254, 12, 18, 'Хүрээмарал'),
(255, 12, 19, 'Шинэжинст'),
(256, 12, 20, 'Эрдэнэцогт'),
(257, 13, 1, 'Булган'),
(258, 13, 2, 'Баян-Агт'),
(259, 13, 3, 'Баяннуур'),
(260, 13, 4, 'Бугат'),
(261, 13, 5, 'Бүрэгхангай'),
(262, 13, 6, 'Гурванбулаг'),
(263, 13, 7, 'Дашинчилэн'),
(264, 13, 8, 'Могод'),
(265, 13, 9, 'Орхон'),
(266, 13, 10, 'Рашаант'),
(267, 13, 11, 'Сайхан'),
(268, 13, 12, 'Сэлэнгэ'),
(269, 13, 13, 'Тэшиг'),
(270, 13, 14, 'Хангал'),
(271, 13, 15, 'Хишиг-Өндөр'),
(272, 13, 16, 'Хутаг-Өндөр'),
(273, 14, 1, 'Есөнбулаг'),
(274, 14, 2, 'Алтай'),
(275, 14, 3, 'Баян-Уул'),
(276, 14, 4, 'Бигэр'),
(277, 14, 5, 'Бугат'),
(278, 14, 6, 'Дарив'),
(279, 14, 7, 'Дэлгэр'),
(280, 14, 8, 'Жаргалан'),
(281, 14, 9, 'Тайшир'),
(282, 14, 10, 'Тонхил'),
(283, 14, 11, 'Төгрөг'),
(284, 14, 12, 'Халиун'),
(285, 14, 13, 'Хөхморьт'),
(286, 14, 14, 'Цогт'),
(287, 14, 15, 'Цээл'),
(288, 14, 16, 'Чандмань'),
(289, 14, 17, 'Шарга'),
(290, 14, 18, 'Эрдэнэ'),
(291, 15, 1, 'Сүмбэр'),
(292, 15, 2, 'Баянтал'),
(293, 15, 3, 'Шивээговь'),
(294, 16, 1, 'Дархан'),
(295, 16, 2, 'Хонгор'),
(296, 16, 3, 'Орхон'),
(297, 16, 4, 'Шарынгол'),
(298, 17, 1, 'Сайншанд'),
(299, 17, 2, 'Айраг'),
(300, 17, 3, 'Алтанширээ'),
(301, 17, 4, 'Даланжаргалан'),
(302, 17, 5, 'Дэлгэрэх'),
(303, 17, 6, 'Замын-Үүд'),
(304, 17, 7, 'Иххэт'),
(305, 17, 8, 'Мандах'),
(306, 17, 9, 'Өргөн'),
(307, 17, 10, 'Сайхандулаан'),
(308, 17, 11, 'Улаанбадрах'),
(309, 17, 12, 'Хатанбулаг'),
(310, 17, 13, 'Хөвсгөл'),
(311, 17, 14, 'Эрдэнэ'),
(312, 18, 1, 'Хэрлэн'),
(313, 18, 2, 'Баян-Уул'),
(314, 18, 3, 'Баяндун'),
(315, 18, 4, 'Баянтүмэн'),
(316, 18, 5, 'Булган'),
(317, 18, 6, 'Гурванзагал'),
(318, 18, 7, 'Дашбалбар'),
(319, 18, 8, 'Матад'),
(320, 18, 9, 'Сэргэлэн'),
(321, 18, 10, 'Халхгол'),
(322, 18, 11, 'Хөлөнбуйр'),
(323, 18, 12, 'Цагаан-Овоо'),
(324, 18, 13, 'Чойбалсан'),
(325, 18, 14, 'Чулуунхороот'),
(326, 19, 1, 'Мандалговь'),
(327, 19, 2, 'Адаацаг'),
(328, 19, 3, 'Баянжаргалан'),
(329, 19, 4, 'Говь-Угтаал'),
(330, 19, 5, 'Гурвансайхан'),
(331, 19, 6, 'Дэлгэрхангай'),
(332, 19, 7, 'Дэлгэрцогт'),
(333, 19, 8, 'Дэрэн'),
(334, 19, 9, 'Луус'),
(335, 19, 10, 'Өлзийт'),
(336, 19, 11, 'Өндөршил'),
(337, 19, 12, 'Сайнцагаан'),
(338, 19, 13, 'Сайхан-Овоо'),
(339, 19, 14, 'Хулд'),
(340, 19, 15, 'Цагаандэлгэр'),
(341, 20, 1, 'Улиастай'),
(342, 20, 2, 'Алдархаан'),
(343, 20, 3, 'Асгат'),
(344, 20, 4, 'Баянтэс'),
(345, 20, 5, 'Баянхайрхан'),
(346, 20, 6, 'Дөрвөлжин'),
(347, 20, 7, 'Завханмандал'),
(348, 20, 8, 'Идэр'),
(349, 20, 9, 'Их-Уул'),
(350, 20, 10, 'Нөмрөг'),
(351, 20, 11, 'Отгон'),
(352, 20, 12, 'Сантмаргац'),
(353, 20, 13, 'Сонгино'),
(354, 20, 14, 'Тосонцэнгэл'),
(355, 20, 15, 'Түдэвтэй'),
(356, 20, 16, 'Тэлмэн'),
(357, 20, 17, 'Тэс'),
(358, 20, 18, 'Ургамал'),
(359, 20, 19, 'Цагаанхайрхан'),
(360, 20, 20, 'Цагаанчулуут'),
(361, 20, 21, 'Цэцэн-Уул'),
(362, 20, 22, 'Шилүүстэй'),
(363, 20, 23, 'Эрдэнэхайрхан'),
(364, 20, 24, 'Яруу'),
(365, 21, 1, 'Баян-Өндөр'),
(366, 21, 2, 'Жаргалант'),
(367, 22, 1, 'Арвайхээр'),
(368, 22, 2, 'Баруунбаян-Улаан'),
(369, 22, 3, 'Бат-Өлзий'),
(370, 22, 4, 'Баян-Өндөр'),
(371, 22, 5, 'Баянгол'),
(372, 22, 6, 'Богд'),
(373, 22, 7, 'Бүрд'),
(374, 22, 8, 'Гучин-Ус'),
(375, 22, 9, 'Есөнзүйл'),
(376, 22, 10, 'Зүүнбаян-Улаан'),
(377, 22, 11, 'Нарийнтээл'),
(378, 22, 12, 'Өлзийт'),
(379, 22, 13, 'Сант'),
(380, 22, 14, 'Тарагт'),
(381, 22, 15, 'Төгрөг'),
(382, 22, 16, 'Уянга'),
(383, 22, 17, 'Хайрхандулаан'),
(384, 22, 18, 'Хархорин'),
(385, 22, 19, 'Хужирт'),
(386, 23, 1, 'Даланзадгад'),
(387, 23, 2, 'Баян-Овоо'),
(388, 23, 3, 'Баяндалай'),
(389, 23, 4, 'Булган'),
(390, 23, 5, 'Гурвантэс'),
(391, 23, 6, 'Мандал-Овоо'),
(392, 23, 7, 'Манлай'),
(393, 23, 8, 'Номгон'),
(394, 23, 9, 'Ноён'),
(395, 23, 10, 'Сэврэй'),
(396, 23, 11, 'Ханбогд'),
(397, 23, 12, 'Ханхонгор'),
(398, 23, 13, 'Хүрмэн'),
(399, 23, 14, 'Цогт-Овоо'),
(400, 23, 15, 'Цогтцэций'),
(401, 24, 1, 'Баруун-Урт'),
(402, 24, 2, 'Асгат'),
(403, 24, 3, 'Баяндэлгэр'),
(404, 24, 4, 'Дарьганга'),
(405, 24, 5, 'Мөнххаан'),
(406, 24, 6, 'Наран'),
(407, 24, 7, 'Онгон'),
(408, 24, 8, 'Сүхбаатар'),
(409, 24, 9, 'Түвшинширээ'),
(410, 24, 10, 'Түмэнцогт'),
(411, 24, 11, 'Уулбаян'),
(412, 24, 12, 'Халзан'),
(413, 24, 13, 'Эрдэнэцагаан'),
(414, 25, 1, 'Сүхбаатар'),
(415, 25, 2, 'Алтанбулаг'),
(416, 25, 3, 'Баруунбүрэн'),
(417, 25, 4, 'Баянгол'),
(418, 25, 5, 'Ерөө'),
(419, 25, 6, 'Жавхлант'),
(420, 25, 7, 'Зүүнбүрэн'),
(421, 25, 8, 'Мандал'),
(422, 25, 9, 'Орхон'),
(423, 25, 10, 'Орхонтуул'),
(424, 25, 11, 'Сайхан'),
(425, 25, 12, 'Сант'),
(426, 25, 13, 'Түшиг'),
(427, 25, 14, 'Хүдэр'),
(428, 25, 15, 'Хушаат'),
(429, 25, 16, 'Цагааннуур'),
(430, 25, 17, 'Шаамар'),
(431, 26, 1, 'Зуунмод'),
(432, 26, 2, 'Алтанбулаг'),
(433, 26, 3, 'Аргалант'),
(434, 26, 4, 'Архуст'),
(435, 26, 5, 'Батсүмбэр'),
(436, 26, 6, 'Баян'),
(437, 26, 7, 'Баян-Өнжүүл'),
(438, 26, 8, 'Баяндэлгэр'),
(439, 26, 9, 'Баянжаргалан'),
(440, 26, 10, 'Баянхангай'),
(441, 26, 11, 'Баянцагаан'),
(442, 26, 12, 'Баянцогт'),
(443, 26, 13, 'Баянчандмань'),
(444, 26, 14, 'Борнуур'),
(445, 26, 15, 'Бүрэн'),
(446, 26, 16, 'Дэлгэрхаан'),
(447, 26, 17, 'Жаргалант'),
(448, 26, 18, 'Заамар'),
(449, 26, 19, 'Лүн'),
(450, 26, 20, 'Мөнгөнморьт'),
(451, 26, 21, 'Өндөрширээт'),
(452, 26, 22, 'Сэргэлэн'),
(453, 26, 23, 'Угтаалцайдам'),
(454, 26, 24, 'Цээл'),
(455, 26, 25, 'Эрдэнэ'),
(456, 26, 26, 'Эрдэнэсант'),
(457, 26, 27, 'Баянбулаг'),
(458, 27, 1, 'Улаангом'),
(459, 27, 2, 'Баруунтуруун'),
(460, 27, 3, 'Бөхмөрөн'),
(461, 27, 4, 'Давст'),
(462, 27, 5, 'Завхан'),
(463, 27, 6, 'Зүүнговь'),
(464, 27, 7, 'Зүүнхангай'),
(465, 27, 8, 'Малчин'),
(466, 27, 9, 'Наранбулаг'),
(467, 27, 10, 'Өлгий'),
(468, 27, 11, 'Өмнөговь'),
(469, 27, 12, 'Өндөрхангай'),
(470, 27, 13, 'Сагил'),
(471, 27, 14, 'Тариалан'),
(472, 27, 15, 'Тэс'),
(473, 27, 16, 'Түргэн'),
(474, 27, 17, 'Ховд'),
(475, 27, 18, 'Хяргас'),
(476, 27, 19, 'Цагаанхайрхан'),
(477, 28, 1, 'Жаргалант'),
(478, 28, 2, 'Алтай'),
(479, 28, 3, 'Булган'),
(480, 28, 4, 'Буянт'),
(481, 28, 5, 'Дарви'),
(482, 28, 6, 'Дөргөн'),
(483, 28, 7, 'Дуут'),
(484, 28, 8, 'Зэрэг'),
(485, 28, 9, 'Манхан'),
(486, 28, 10, 'Мөнххайрхан'),
(487, 28, 11, 'Мөст'),
(488, 28, 12, 'Мянгад'),
(489, 28, 13, 'Үенч'),
(490, 28, 14, 'Ховд'),
(491, 28, 15, 'Цэцэг'),
(492, 28, 16, 'Чандмань'),
(493, 28, 17, 'Эрдэнэбүрэн'),
(494, 29, 1, 'Мөрөн'),
(495, 29, 2, 'Алаг-Эрдэнэ'),
(496, 29, 3, 'Арбулаг'),
(497, 29, 4, 'Баянзүрх'),
(498, 29, 5, 'Бүрэнтогтох'),
(499, 29, 6, 'Галт'),
(500, 29, 7, 'Жаргалант'),
(501, 29, 8, 'Их-Уул'),
(502, 29, 9, 'Рашаант'),
(503, 29, 10, 'Рэнчинлхүмбэ'),
(504, 29, 11, 'Тариалан'),
(505, 29, 12, 'Тосонцэнгэл'),
(506, 29, 13, 'Төмөрбулаг'),
(507, 29, 14, 'Түнэл'),
(508, 29, 15, 'Улаан-Уул'),
(509, 29, 16, 'Ханх'),
(510, 29, 17, 'Цагааннуур'),
(511, 29, 18, 'Цагаан-Уул'),
(512, 29, 19, 'Цагаан-Үүр'),
(513, 29, 20, 'Цэцэрлэг'),
(514, 29, 21, 'Чандмань-Өндөр'),
(515, 29, 22, 'Шинэ-Идэр'),
(516, 29, 23, 'Эрдэнэбулган'),
(517, 30, 1, 'Хэрлэн'),
(518, 30, 2, 'Батноров'),
(519, 30, 3, 'Батширээт'),
(520, 30, 4, 'Биндэр'),
(521, 30, 5, 'Бор-Өндөр'),
(522, 30, 6, 'Галшар'),
(523, 30, 7, 'Дадал'),
(524, 30, 8, 'Дарханы'),
(525, 30, 9, 'Дэлгэрхаан'),
(526, 30, 10, 'Жаргалтхаан'),
(527, 30, 11, 'Мөрөн'),
(528, 30, 12, 'Норовлин'),
(529, 30, 13, 'Өмнөдэлгэр'),
(530, 30, 14, 'Цэнхэрмандал'),
(531, 30, 15, 'Баян-Адарга'),
(532, 30, 16, 'Баянмөнх'),
(533, 30, 17, 'Баян-Овоо'),
(534, 7, 8, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `media`
--

DROP TABLE IF EXISTS `media`;
CREATE TABLE IF NOT EXISTS `media` (
  `id` int NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int UNSIGNED NOT NULL DEFAULT '0',
  `alt_text` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=810 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `media`
--

INSERT INTO `media` (`id`, `filename`, `original_name`, `mime_type`, `file_size`, `alt_text`, `created_at`) VALUES
(1, '69f00ee091a31_1777340128.png', 'icon-512.png', 'image/png', 231128, '', '2026-04-28 01:35:28'),
(2, '69f00ee093d34_1777340128.png', 'favicon-32x32.png', 'image/png', 2439, '', '2026-04-28 01:35:28'),
(3, '69f2e0aae513f_1777524906.jpg', '681293368_1418986996912420_5473529895134358306_n.jpg', 'image/jpeg', 116231, '', '2026-04-30 04:55:06'),
(4, '69f2e0ace966d_1777524908.jpg', '684275949_1418987000245753_893473564226844081_n.jpg', 'image/jpeg', 110846, '', '2026-04-30 04:55:09'),
(5, '69f41661d49df_1777604193.jpg', '682919375_1418987070245746_5625806151642165614_n.jpg', 'image/jpeg', 116840, '', '2026-05-01 02:56:33'),
(6, '69f41968869c4_1777604968.jpg', '685224961_1420788713398915_1300571417083480718_n.jpg', 'image/jpeg', 84296, '', '2026-05-01 03:09:28'),
(7, '69f4196977721_1777604969.jpg', '685860796_1420788026732317_3640502908619362032_n.jpg', 'image/jpeg', 84110, '', '2026-05-01 03:09:29'),
(8, '69f41969b140c_1777604969.jpg', '686445769_1420788003398986_7765453250696468115_n.jpg', 'image/jpeg', 31612, '', '2026-05-01 03:09:29'),
(9, '69f4196a92362_1777604970.jpg', '686912409_1420788620065591_3170978704792140554_n.jpg', 'image/jpeg', 111819, '', '2026-05-01 03:09:30'),
(10, '69f4197ebe7ca_1777604990.jpg', '681175020_1420788623398924_8612086293066419739_n (1).jpg', 'image/jpeg', 86056, '', '2026-05-01 03:09:50'),
(11, '69f41d2bcca15_1777605931.jpg', '683050124_1420111236799996_5198915822476915076_n.jpg', 'image/jpeg', 73646, '', '2026-05-01 03:25:31'),
(12, '69f41d2c55661_1777605932.jpg', '684211918_1420111326799987_1818005831218915844_n.jpg', 'image/jpeg', 71687, '', '2026-05-01 03:25:32'),
(13, '69f41d2d079b8_1777605933.jpg', '684885300_1420111233466663_884043981988737389_n.jpg', 'image/jpeg', 75632, '', '2026-05-01 03:25:33'),
(14, '69f41d2e3ea8d_1777605934.jpg', '685526915_1420110636800056_1629999919923181132_n.jpg', 'image/jpeg', 62045, '', '2026-05-01 03:25:34'),
(15, '69f41d2eaf733_1777605934.jpg', '686304804_1420111113466675_5077104384086491222_n.jpg', 'image/jpeg', 55645, '', '2026-05-01 03:25:34'),
(16, '69f41d2f00588_1777605935.jpg', '686501944_1420111123466674_4367117950952733915_n.jpg', 'image/jpeg', 48194, '', '2026-05-01 03:25:35'),
(17, '69f41e18a3396_1777606168.jpg', '684904038_1419091923568594_2335682332983999484_n.jpg', 'image/jpeg', 99145, '', '2026-05-01 03:29:28'),
(18, '69f42314ba54c_1777607444.jpg', '672674535_1411894874288299_7377889541138578992_n.jpg', 'image/jpeg', 105088, '', '2026-05-01 03:50:44'),
(19, '69f423157bae8_1777607445.jpg', '673546896_1411894864288300_3732935152169141884_n.jpg', 'image/jpeg', 71406, '', '2026-05-01 03:50:45'),
(20, '69f423165f0b6_1777607446.jpg', '674002997_1411895040954949_1047848366905274596_n.jpg', 'image/jpeg', 78316, '', '2026-05-01 03:50:46'),
(21, '69f42316dfd66_1777607446.jpg', '676602314_1411894947621625_5972187253052950311_n.jpg', 'image/jpeg', 54975, '', '2026-05-01 03:50:46'),
(22, '69f42317d224c_1777607447.jpg', '677761706_1411894977621622_1711452331532660067_n.jpg', 'image/jpeg', 95196, '', '2026-05-01 03:50:47'),
(23, '69f423185c0e2_1777607448.webp', 'before_after.webp', 'image/webp', 44800, '', '2026-05-01 03:50:48'),
(24, '69f432160e624_1777611286.jpg', '666042582_1508706980765237_5993114841445369723_n.jpg', 'image/jpeg', 74161, '', '2026-05-01 04:54:46'),
(25, '69f4327aac4f8_1777611386.jpg', 'EX_544494b7-89cb-4dbd-97c3-c4a9ee9a5b6f.jpg', 'image/jpeg', 33904, '', '2026-05-01 04:56:26'),
(26, '69f434d8a4bca_1777611992.jpg', '1000020786.jpg', 'image/jpeg', 151319, '', '2026-05-01 05:06:32'),
(27, '69f43502d27d5_1777612034.jpg', '1000020786.jpg', 'image/jpeg', 151319, '', '2026-05-01 05:07:14'),
(28, '69f436d8d172f_1777612504.jpeg', 'att.NIRCCO5fs0H6i5tbdQaTlEOD9byWq_MQwnWreiREw2U.jpeg', 'image/jpeg', 118956, '', '2026-05-01 05:15:05'),
(29, '69f436d909c58_1777612505.jpeg', 'att.4V38e4eFYKxw3Gvdt4XVpBxuwuZvCaAvoYmSP-PBuOs.jpeg', 'image/jpeg', 116838, '', '2026-05-01 05:15:05'),
(30, '69f436d931bf7_1777612505.jpeg', 'att.A8U_88WrzAjNICpFT-pQUB-8IztxuhTXmIsUWnphMkU.jpeg', 'image/jpeg', 124070, '', '2026-05-01 05:15:05'),
(31, '69f436d95806a_1777612505.jpeg', 'att.9gMsBhDSXSLkxHKKZq0udbjJoRAWb1Gyu95eo0TK7xg.jpeg', 'image/jpeg', 170346, '', '2026-05-01 05:15:05'),
(32, '69f43918c5430_1777613080.jpeg', 'att.L2M_WWodE-GCutEa-Gnp1NhFCDcYw-HrSa_6r67Fk8g.jpeg', 'image/jpeg', 120718, '', '2026-05-01 05:24:40'),
(33, '69f43918ec544_1777613080.jpeg', 'att.cFaIJnWg0YMaxW-S6QnxArWthP4rlBErKl5iimjYUHo.jpeg', 'image/jpeg', 132130, '', '2026-05-01 05:24:41'),
(34, '69f439191dd93_1777613081.jpeg', 'att.BFBf0q-qxgYHng9smW0Yb1MbEfsKIkj41FxmSNT01Cw.jpeg', 'image/jpeg', 123750, '', '2026-05-01 05:24:41'),
(35, '69f4391942347_1777613081.jpeg', 'att.hpGxl4VLPMMSBsCtc8kONOmlahRKlEVfZavVdYVvBy4.jpeg', 'image/jpeg', 128226, '', '2026-05-01 05:24:41'),
(36, '69f439fe93a07_1777613310.jpeg', 'att.HFJLHvlkrRMbMwXgkmIKw5sJyRnYD3GhjcELhn3H7TI.jpeg', 'image/jpeg', 110369, '', '2026-05-01 05:28:30'),
(37, '69f439febe0ee_1777613310.jpeg', 'att.PLT_DqjYkfFx9-0-nmid2NsdLsd0UQkdX7cZ3L-Bo9E.jpeg', 'image/jpeg', 111033, '', '2026-05-01 05:28:30'),
(38, '69f439fee6db5_1777613310.jpeg', 'att.IARjoWr33X5PVFryxFM9v3TxPOn1JLt1Y1cPqPzeZik.jpeg', 'image/jpeg', 95471, '', '2026-05-01 05:28:31'),
(39, '69f439ff15794_1777613311.jpeg', 'att.anCko87UMZjzx-us83CIUwovffpzX0GjBocVb-ndTIg.jpeg', 'image/jpeg', 124467, '', '2026-05-01 05:28:31'),
(40, '69f43cd87c6c2_1777614040.jpeg', 'att.0ROiXaoirD2kK71GGlUxIN36tajT0-kCuI2Lsys8rG4.jpeg', 'image/jpeg', 151705, '', '2026-05-01 05:40:40'),
(41, '69f43cd8b0b30_1777614040.jpeg', 'att.IvEWvYZzCUtM8ualexhHNyretogKM28M3_S2XAWO1mQ.jpeg', 'image/jpeg', 132900, '', '2026-05-01 05:40:40'),
(42, '69f43cd8d885e_1777614040.jpeg', 'att.TKZE1PJCPf_o5PrugvFvls5rJgNVkQCpn1VDWSqzuvs.jpeg', 'image/jpeg', 133796, '', '2026-05-01 05:40:41'),
(43, '69f43cd908b65_1777614041.jpeg', 'att.LWNLCgKM9tfPYnFuYuWLj_f4WWtT2kCEo10ZvcLJet8.jpeg', 'image/jpeg', 138879, '', '2026-05-01 05:40:41'),
(44, '69f43cd9315ac_1777614041.jpeg', 'att.Q7HjRtf_gBaW96jXZ6YGuG4U0hEwBU9h6Bttnqmf4X8.jpeg', 'image/jpeg', 167272, '', '2026-05-01 05:40:41'),
(45, '69f43f11ea188_1777614609.jpeg', 'att.ym1Srr5ZY7rulUeUIHu4oQqrn6M-nLhG3xPB_ExunEw.jpeg', 'image/jpeg', 123725, '', '2026-05-01 05:50:10'),
(46, '69f43f121f053_1777614610.jpeg', 'att.o0AwhIxSAF1a5itv8Aul9CvKy-e3k79SfP5eWqK018w.jpeg', 'image/jpeg', 136906, '', '2026-05-01 05:50:10'),
(47, '69f43f124409e_1777614610.jpeg', 'att.lkWiR55fcZBlEFb-UIVbXiVap9T5uQY5QrmMXLNpdfs.jpeg', 'image/jpeg', 154449, '', '2026-05-01 05:50:10'),
(48, '69f43f126a20b_1777614610.jpeg', 'att.2DTzVVOsNoYJ0UYknikjc7LZJf0CFHAzzc3C6zudAV0.jpeg', 'image/jpeg', 131288, '', '2026-05-01 05:50:10'),
(49, '69f4400066e5a_1777614848.jpeg', 'att.ym1Srr5ZY7rulUeUIHu4oQqrn6M-nLhG3xPB_ExunEw.jpeg', 'image/jpeg', 123725, '', '2026-05-01 05:54:08'),
(50, '69f4400089472_1777614848.jpeg', 'att.o0AwhIxSAF1a5itv8Aul9CvKy-e3k79SfP5eWqK018w.jpeg', 'image/jpeg', 136906, '', '2026-05-01 05:54:08'),
(51, '69f44000b2333_1777614848.jpeg', 'att.lkWiR55fcZBlEFb-UIVbXiVap9T5uQY5QrmMXLNpdfs.jpeg', 'image/jpeg', 154449, '', '2026-05-01 05:54:08'),
(52, '69f448460082e_1777616966.jpeg', 'att.oNzFJ2t1d5XovDb1agdcbnShs3B1zm0nceotXB3jz98.jpeg', 'image/jpeg', 135662, '', '2026-05-01 06:29:26'),
(53, '69f448460e433_1777616966.jpeg', 'att.X2_hGqFOyBsh9lSyU3pA2FhRUUO0ji6oe53x3nbZIzc.jpeg', 'image/jpeg', 130154, '', '2026-05-01 06:29:26'),
(54, '69f448461b687_1777616966.jpeg', 'att.iWfhfQpyfAPfJcKerFP12BMlhxgsaAU7wp3CU6pmrK4.jpeg', 'image/jpeg', 107629, '', '2026-05-01 06:29:26'),
(55, '69f4484628163_1777616966.jpeg', 'att.swgvJV7-7UZME-3L-OBqBSbqtCfNIVLcmLhLlc6gVA8.jpeg', 'image/jpeg', 129525, '', '2026-05-01 06:29:26'),
(56, '69f4484635048_1777616966.jpeg', 'att.4FS1qi28EBec8hmu0CtqUc206bW0AwBITOHURf21ovw.jpeg', 'image/jpeg', 135385, '', '2026-05-01 06:29:26'),
(57, '69f4488209650_1777617026.jpeg', 'att.gTjMT6rBjbVw5hmZRUlTohpcZEC1DsSrHR8vyBwusuM.jpeg', 'image/jpeg', 157983, '', '2026-05-01 06:30:26'),
(58, '69f4488217240_1777617026.jpeg', 'att.a4V5fmPEednkYNy1dgBDwDCqN4vFYJdNsvqG8PGLzJI.jpeg', 'image/jpeg', 151752, '', '2026-05-01 06:30:26'),
(59, '69f448822499d_1777617026.jpeg', 'att.UShg8tAbqwu12bZL_Prv18vK21ZCynxPEEZYazJxTpM.jpeg', 'image/jpeg', 132801, '', '2026-05-01 06:30:26'),
(60, '69f4488231a66_1777617026.jpeg', 'att.VNHnI3lAc7LyJs5FrI2gurGaaaKRcpcHNr-mifEuFVU.jpeg', 'image/jpeg', 147383, '', '2026-05-01 06:30:26'),
(61, '69f448823f469_1777617026.jpeg', 'att.xFfrmECpAZ9rjZAvA_oLMmUMXmF9hj2-oi12HuUFtqc.jpeg', 'image/jpeg', 208103, '', '2026-05-01 06:30:26'),
(62, '69f448824d499_1777617026.jpeg', 'att.ZjF4k9s3DaDngFSt0kwMexp19AviX4Fx98EWkTTGJdQ.jpeg', 'image/jpeg', 114977, '', '2026-05-01 06:30:26'),
(63, '69f448825a3bc_1777617026.jpeg', 'att.9W7Rp_3KUk-eO7FzbZEpaBUjaT733HfSoi1CCfOsSV8.jpeg', 'image/jpeg', 117205, '', '2026-05-01 06:30:26'),
(64, '69f4488267d81_1777617026.jpeg', 'att.MzMnqwpR-w3CkjG-_M4FkTUjpho3rFPaNry78SiQ0YQ.jpeg', 'image/jpeg', 119299, '', '2026-05-01 06:30:26'),
(65, '69f4488278097_1777617026.jpeg', 'att.XOEhOBT5IBRtCTNFBF6CVhkPafru-aOyD1CENjAezz0.jpeg', 'image/jpeg', 123553, '', '2026-05-01 06:30:26'),
(66, '69f448828a0d2_1777617026.jpeg', 'att.i2HlixexNgQalQKyGH_jY787jiqOQtzcl7_AMphk77I.jpeg', 'image/jpeg', 114580, '', '2026-05-01 06:30:26'),
(67, '69f44916dede3_1777617174.jpeg', 'att.oNzFJ2t1d5XovDb1agdcbnShs3B1zm0nceotXB3jz98.jpeg', 'image/jpeg', 135662, '', '2026-05-01 06:32:54'),
(68, '69f44916efd40_1777617174.jpeg', 'att.oNzFJ2t1d5XovDb1agdcbnShs3B1zm0nceotXB3jz98.jpeg', 'image/jpeg', 135662, '', '2026-05-01 06:32:55'),
(69, '69f4491709760_1777617175.jpeg', 'att.X2_hGqFOyBsh9lSyU3pA2FhRUUO0ji6oe53x3nbZIzc.jpeg', 'image/jpeg', 130154, '', '2026-05-01 06:32:55'),
(70, '69f4491717d26_1777617175.jpeg', 'att.iWfhfQpyfAPfJcKerFP12BMlhxgsaAU7wp3CU6pmrK4.jpeg', 'image/jpeg', 107629, '', '2026-05-01 06:32:55'),
(71, '69f4491726776_1777617175.jpeg', 'att.swgvJV7-7UZME-3L-OBqBSbqtCfNIVLcmLhLlc6gVA8.jpeg', 'image/jpeg', 129525, '', '2026-05-01 06:32:55'),
(72, '69f44b8e8b304_1777617806.jpeg', 'att.SnYCxPsRupb6DuezxU4CaCm92ZPU1yx0yn9dDGMdtks.jpeg', 'image/jpeg', 160172, '', '2026-05-01 06:43:26'),
(73, '69f44b8ebc5a5_1777617806.jpeg', 'att.v7ctZkriQ1uIx_uzuxlSDu23cIOdA-wHrmgPySa_6CU.jpeg', 'image/jpeg', 171933, '', '2026-05-01 06:43:26'),
(74, '69f44b8eeaf79_1777617806.jpeg', 'att.PoDx7Bq5EOTBGJSp0JWYlZjBemKJCiKpmkgkz8rvdoM.jpeg', 'image/jpeg', 146201, '', '2026-05-01 06:43:27'),
(75, '69f44b8f1b851_1777617807.jpeg', 'att.s0teKCDIPjOGsSvEJIyyvXgMLKdz2QAz_DCw8Bhwzcs.jpeg', 'image/jpeg', 156805, '', '2026-05-01 06:43:27'),
(76, '69f44b8f3e36d_1777617807.jpeg', 'att.5ASj3_KwYFIYuBwbLlRSJxbWInxbxoq8N8Ww2Xf6R8k.jpeg', 'image/jpeg', 113553, '', '2026-05-01 06:43:27'),
(77, '69f44b8f619a7_1777617807.jpeg', 'att.C_kPCo61VqNd0FE-jHG0H8wMsczQzSTDybOLGmdRHVA.jpeg', 'image/jpeg', 113557, '', '2026-05-01 06:43:27'),
(78, '69f44b8f91ad3_1777617807.jpeg', 'att.4xZhL20QYjt1xNIyUU8Ji7gpyHb0rh4ytHghsaqkOq4.jpeg', 'image/jpeg', 109607, '', '2026-05-01 06:43:27'),
(79, '69f44b8fbb222_1777617807.jpeg', 'att.ZaVrWeK7QUKT6E-jiQ2ls3McmWtrXCCkSqnTJ7VkfcY.jpeg', 'image/jpeg', 120635, '', '2026-05-01 06:43:27'),
(80, '69f44b8fe173d_1777617807.jpeg', 'att.dBeMXLZlyxm8pJsUqTtbJ3tDWadSvGP5SoUAqzlussg.jpeg', 'image/jpeg', 91601, '', '2026-05-01 06:43:28'),
(81, '69f44b900fda6_1777617808.jpeg', 'att.a5yjgZ_lh3FjH8AK2YFoPpIZM1FIh5pvVyRchcK-kCE.jpeg', 'image/jpeg', 186621, '', '2026-05-01 06:43:28'),
(82, '69f44b9034a88_1777617808.jpeg', 'att.1fzvLUZz4V6Ww4I-K3Wv4DQ_GGI4miikqONXqYI6G5I.jpeg', 'image/jpeg', 158574, '', '2026-05-01 06:43:28'),
(83, '69f44b9057509_1777617808.jpeg', 'att.Zqti1rthhXEFeP0MWwd7pitIl6MkPKxcBgrmQI4Avdg.jpeg', 'image/jpeg', 157120, '', '2026-05-01 06:43:28'),
(84, '69f44b9083b0c_1777617808.jpeg', 'att.2UOByn5CVs1uuwQrC0EaNwZzGnxY1sETRMNbhP5jTWE.jpeg', 'image/jpeg', 139739, '', '2026-05-01 06:43:28'),
(85, '69f44b90b36e9_1777617808.jpeg', 'att.32W6XJAv6h-P0uOLiukNYoNpCS4G-r_wmIQ_-VYZBDo.jpeg', 'image/jpeg', 164228, '', '2026-05-01 06:43:28'),
(86, '69f44b90d83ac_1777617808.jpeg', 'att.2edjJDK0w2Gni4Bznba-j6OpxLCjBxSg5HspT20EMbc.jpeg', 'image/jpeg', 177385, '', '2026-05-01 06:43:29'),
(87, '69f44efed5b32_1777618686.jpeg', 'att.KcnsFkqGg0ievRlrOvFQwEYxyBAojnKxyBv-92Xoslw.jpeg', 'image/jpeg', 116779, '', '2026-05-01 06:58:07'),
(88, '69f44eff0580e_1777618687.jpeg', 'att.FzQ5Yo7TkmEaKuKzQG8jabJYTKb4nK8BMtDN1buj8Q8.jpeg', 'image/jpeg', 111753, '', '2026-05-01 06:58:07'),
(89, '69f44eff2cc82_1777618687.jpeg', 'att.NvAtD60QsTF6E6qsiqu39nd_Sh7s-VYgKh_mGxz0xTw.jpeg', 'image/jpeg', 97388, '', '2026-05-01 06:58:07'),
(90, '69f4557cc4f68_1777620348.jpeg', 'att.TYyfzGxs7EMH92XzOnYOLrtSzWGvocSlYsoRIp1VWt0.jpeg', 'image/jpeg', 119791, '', '2026-05-01 07:25:48'),
(91, '69f4557cf3754_1777620348.jpeg', 'att.1GBLwboKpqlEyId-z16FoIn3dot8HowW6jLj9iXTzFA.jpeg', 'image/jpeg', 124441, '', '2026-05-01 07:25:49'),
(92, '69f4557d29ff7_1777620349.jpeg', 'att.fDA7ARjXj0a--Tk6DkkIwA4lpm9MI1RWtfmS70iGuEQ.jpeg', 'image/jpeg', 131020, '', '2026-05-01 07:25:49'),
(93, '69f457b5eb016_1777620917.jpeg', 'IMG_3186.jpeg', 'image/jpeg', 66270, '', '2026-05-01 07:35:17'),
(94, '69f457b5eb273_1777620917.jpeg', 'IMG_3187.jpeg', 'image/jpeg', 85407, '', '2026-05-01 07:35:18'),
(95, '69f457b6019b4_1777620918.jpeg', 'IMG_3188.jpeg', 'image/jpeg', 55590, '', '2026-05-01 07:35:18'),
(96, '69f46257a4aef_1777623639.jpg', '674540622_1413445534133233_4260530379043403250_n.jpg', 'image/jpeg', 20927, '', '2026-05-01 08:20:39'),
(97, '69f46258553f5_1777623640.jpg', '675885986_1413447010799752_4477030746447023354_n.jpg', 'image/jpeg', 61297, '', '2026-05-01 08:20:40'),
(98, '69f46259d2481_1777623641.jpg', '677939616_1413446994133087_9103667101524248657_n.jpg', 'image/jpeg', 115285, '', '2026-05-01 08:20:41'),
(99, '69f4625a884f0_1777623642.jpg', '678187965_1413447070799746_2744377078195370978_n.jpg', 'image/jpeg', 64954, '', '2026-05-01 08:20:42'),
(100, '69f4625b3330b_1777623643.jpg', '678514409_1413445497466570_2639208986758343341_n.jpg', 'image/jpeg', 31210, '', '2026-05-01 08:20:43'),
(101, '69f483ca0678c_1777632202.jpeg', 'att._utTBqwR1q6-2tD0829T4VirrZ5pnH2vCKbtijtnmnM.jpeg', 'image/jpeg', 141895, '', '2026-05-01 10:43:22'),
(102, '69f483ca2b4cb_1777632202.jpeg', 'att.z4sDrFGhPLEIaQUHfJeWx88ftxaKc9LTZRk6cLTsvdQ.jpeg', 'image/jpeg', 126306, '', '2026-05-01 10:43:22'),
(103, '69f483ca4fd53_1777632202.jpeg', 'att.Eum7it4ro0wQ5dlJX6wKSIf5hvNSjlv2JaQic3twTI0.jpeg', 'image/jpeg', 161095, '', '2026-05-01 10:43:22'),
(104, '69f484aa2cada_1777632426.jpeg', 'att.7KNnBKd9lt9QnZmV6fQrAQQNWpXJO6_OpxwFNqQpUao.jpeg', 'image/jpeg', 111640, '', '2026-05-01 10:47:06'),
(105, '69f484aa51754_1777632426.jpeg', 'att.b9kyKfob7v3LRQaJjg_LpESopV41H6HDRx8D6jVQ0EI.jpeg', 'image/jpeg', 118950, '', '2026-05-01 10:47:06'),
(106, '69f484aa7e859_1777632426.jpeg', 'att.uu1qq9EClUiwGB3cuKbpN2cuhuubuh_hzEStw-mfMNA.jpeg', 'image/jpeg', 109674, '', '2026-05-01 10:47:06'),
(107, '69f486f03ba06_1777633008.jpeg', 'att.4ndiOhFvaGbs9lGGNmHpFGLxpck7v9Pwr0ylrGipTtc.jpeg', 'image/jpeg', 128888, '', '2026-05-01 10:56:48'),
(108, '69f486f05ffcc_1777633008.jpeg', 'att.iylVjrNe9Hbj6eOEcLVSU2swBkZ_LZifZPxIrC7MfVY.jpeg', 'image/jpeg', 133862, '', '2026-05-01 10:56:48'),
(109, '69f486f084b8b_1777633008.jpeg', 'att.-hOS6LD8f7c4i86mcFq_uJxk-9_mmOKEjEdgn4tPrS8.jpeg', 'image/jpeg', 125291, '', '2026-05-01 10:56:48'),
(110, '69f489633804a_1777633635.jpeg', 'att.h9HmGdAOY37M1CKnA5LJvQTHr5GyAay2OYuwI2s0OvU.jpeg', 'image/jpeg', 121658, '', '2026-05-01 11:07:15'),
(111, '69f489635c04e_1777633635.jpeg', 'att.AluSbQZeHrGFegSMQF_4W0SNB8I-hc4vhC0QihMp5o0.jpeg', 'image/jpeg', 119867, '', '2026-05-01 11:07:15'),
(112, '69f489638cc1d_1777633635.jpeg', 'att.rHciA4swysI-baebseWhR3QXYs6gDRqewJhon14mK1o.jpeg', 'image/jpeg', 127778, '', '2026-05-01 11:07:15'),
(113, '69f48963b6f8d_1777633635.jpeg', 'att.KqGg0_xICKDb63ojqTgIe-X6xcnPRZ3MR_4qh8_4xWQ.jpeg', 'image/jpeg', 121275, '', '2026-05-01 11:07:15'),
(114, '69f48a16e63cc_1777633814.jpeg', 'att.W7ksxIwuE8B9kKhZcay7GDpqfj6-bVv869PMNFQRX8c.jpeg', 'image/jpeg', 121829, '', '2026-05-01 11:10:15'),
(115, '69f48b8cdf46e_1777634188.jpeg', 'att.P3-iI8JojD4NZdoI6kO1r9zOeqzW1rGA5bM6l0QAqAE.jpeg', 'image/jpeg', 126416, '', '2026-05-01 11:16:29'),
(116, '69f48b8d1807f_1777634189.jpeg', 'att.Iw-Oddau4YE4T2JxTjZbC4TN46Woo94nDDdJC8Vzbds.jpeg', 'image/jpeg', 107191, '', '2026-05-01 11:16:29'),
(117, '69f48b8d3c888_1777634189.jpeg', 'att.iJJp7zDLfmJ_hmmwU7kXxSBP4rAVTn-NBVofIb-5whg.jpeg', 'image/jpeg', 118847, '', '2026-05-01 11:16:29'),
(118, '69f48b8d6072b_1777634189.jpeg', 'att.EsUbHNxSDgFXme3pGBPAFzEUm5kg2f-ZOjSXSP0pPbc.jpeg', 'image/jpeg', 121527, '', '2026-05-01 11:16:29'),
(119, '69f57281057f1_1777693313.jpg', '671805142_1411624100982043_1123572420405638274_n.jpg', 'image/jpeg', 186987, '', '2026-05-02 03:41:53'),
(120, '69f5ee03ea12c_1777724931.png', 'IMG_3367.png', 'image/png', 140045, '', '2026-05-02 12:28:52'),
(121, '69f5ee0419123_1777724932.jpeg', 'att.SCRHMdckODgPatBGSOf_UTYUnc53nT5PJj8czyruKCE.jpeg', 'image/jpeg', 128556, '', '2026-05-02 12:28:52'),
(122, '69f5ee0427682_1777724932.jpeg', 'att.S6yINhlSGHwLZuMXiXv8zcKBmA54s4gRexhtTqCemTQ.jpeg', 'image/jpeg', 138421, '', '2026-05-02 12:28:52'),
(123, '69f5ee0435739_1777724932.jpeg', 'att.eKIpWK12jDd4gJXwDdSZT0Zf6sS4fGydfS7V8en8z6M.jpeg', 'image/jpeg', 149234, '', '2026-05-02 12:28:52'),
(124, '69f5ee0443870_1777724932.jpeg', 'att.ckBRx-fGrK9B2FM1xWs3a1E-FsGmSb4_bTgh8sDmwjQ.jpeg', 'image/jpeg', 135097, '', '2026-05-02 12:28:52'),
(125, '69f5ee04523b0_1777724932.jpeg', 'att.L7hfG9IwUezUCzjD720o3yvikKmqlE6-kNhix_IkB48.jpeg', 'image/jpeg', 125633, '', '2026-05-02 12:28:52'),
(126, '69f5ee045fd25_1777724932.jpeg', 'att.s-hpNh_l-UX3wKeWeZ31EQ89Dj1kY3tcNqAfsO7sTRI.jpeg', 'image/jpeg', 136856, '', '2026-05-02 12:28:52'),
(127, '69f5ee04713d3_1777724932.jpeg', 'att.eO1RKJBATqY-rCfme1K71cZwjiLE_mma4jX1B8wzROU.jpeg', 'image/jpeg', 122746, '', '2026-05-02 12:28:52'),
(128, '69f5ee04806be_1777724932.jpeg', 'att.7mPTcmMNOAOMq9zh1uUQEAY_Br9LaZ39EgGTbhpUZcQ.jpeg', 'image/jpeg', 111572, '', '2026-05-02 12:28:52'),
(129, '69f5ee049052c_1777724932.jpeg', 'att.KlHDTpNsDLoRHxx3oZkzq66rUVX0B1-szSadm6ss3x8.jpeg', 'image/jpeg', 112468, '', '2026-05-02 12:28:52'),
(130, '69f5ee049f6c7_1777724932.jpeg', 'att.iy6n-03StIF_yTEhJtHlnO4guPJvLYBsya_HI9Er_HM.jpeg', 'image/jpeg', 138255, '', '2026-05-02 12:28:52'),
(131, '69f5ee04afd35_1777724932.jpeg', 'att.2FFf-vUK3Ew71z97m24Il_2V2-8TaCLLcTurKV5CRfo.jpeg', 'image/jpeg', 122700, '', '2026-05-02 12:28:52'),
(132, '69f5ee04bfb04_1777724932.jpeg', 'att.sY7R0dswOdugvKDFS_JTf0mim8-Pp9l1Fuo369t47bQ.jpeg', 'image/jpeg', 141340, '', '2026-05-02 12:28:52'),
(133, '69f5ee04cebce_1777724932.jpeg', 'att.1MXyQELlxqQM6zHJutsR3PeeCTadrSgF5yDu1yAs2yY.jpeg', 'image/jpeg', 135871, '', '2026-05-02 12:28:52'),
(134, '69f5ee04dc7e9_1777724932.jpeg', 'att.E9uqOfuCPoWD5B5EbYOyhAUz8B2m83reDAreNSmA0mI.jpeg', 'image/jpeg', 129943, '', '2026-05-02 12:28:52'),
(135, '69f5ee04ea391_1777724932.jpeg', 'att.vXmeZnZ2DAZwJFbOSI6j6u3GO9NQE53_zARFTnR6jv4.jpeg', 'image/jpeg', 137643, '', '2026-05-02 12:28:53'),
(136, '69f5ee05040db_1777724933.jpeg', 'att.X-K26b8x8yatc9zefTGcugdpB76pjTlovuhnMctU7fM.jpeg', 'image/jpeg', 156072, '', '2026-05-02 12:28:53'),
(137, '69f5f05da384b_1777725533.jpeg', 'att.Y0DH-HLW9WVSI1FzbkeBW9vw11UwzEs24_Oo8wD86xs.jpeg', 'image/jpeg', 106582, '', '2026-05-02 12:38:53'),
(138, '69f5f05db7b36_1777725533.jpeg', 'att.0tiza3qmlDX8HcFotHz4qVBp2aE3mNIrzQU_CHskW7M.jpeg', 'image/jpeg', 123388, '', '2026-05-02 12:38:53'),
(139, '69f5f05dc5e9d_1777725533.jpeg', 'att.OmDVJ925eUzm2n9gu6gkLvHfMZeO3Z99PqELj-9pPHo.jpeg', 'image/jpeg', 120664, '', '2026-05-02 12:38:53'),
(140, '69f5f05dd36c8_1777725533.jpeg', 'att.Ci4TiIc9QuLv4TeE3rfT0UxSkdLpKK4z-9bg_HhCUAU.jpeg', 'image/jpeg', 152283, '', '2026-05-02 12:38:53'),
(141, '69f5f05de0e7a_1777725533.jpeg', 'att.2hpIwh1-zhiXjayo4wNY2Cyld_RJDyTJBu3upEDyBj0.jpeg', 'image/jpeg', 141055, '', '2026-05-02 12:38:53'),
(142, '69f5f05dee856_1777725533.jpeg', 'att.mFFh2025ytYlg0jCdnENnXWeXlWGwkwwh9lBmE9cSqM.jpeg', 'image/jpeg', 118893, '', '2026-05-02 12:38:54'),
(143, '69f5f05e07964_1777725534.jpeg', 'att.uiz7zTzsuo27OAy3NKayweai-JUQxT6pArz2AzyhHeU.jpeg', 'image/jpeg', 110126, '', '2026-05-02 12:38:54'),
(144, '69f5f05e148a2_1777725534.jpeg', 'att.cZiswPbwmPhwyVxTkhfagnqlMHp8rOB5ip5FmN5l9N0.jpeg', 'image/jpeg', 118173, '', '2026-05-02 12:38:54'),
(145, '69f5f05e22515_1777725534.jpeg', 'att.EPVEnboQOL3Js_JmAYVU5QCUg0-BTY5rCl7C-yQWKaA.jpeg', 'image/jpeg', 100280, '', '2026-05-02 12:38:54'),
(146, '69f5f05e2f36e_1777725534.jpeg', 'att.aITyGnU6a6PsPYnwwFqpIML0GjBwuerFYhlTu_zwk5A.jpeg', 'image/jpeg', 118880, '', '2026-05-02 12:38:54'),
(147, '69f5f05e3ca4a_1777725534.jpeg', 'att.DcoTEnzMOI0oTVea4QftScvgdEbQTAXihjd_SchWGJM.jpeg', 'image/jpeg', 136230, '', '2026-05-02 12:38:54'),
(148, '69f5f05e49e64_1777725534.jpeg', 'att.kKyg2Uae_vDVvhtS0RTet-xzgx3lY8k3RbTm0LLX-zE.jpeg', 'image/jpeg', 119357, '', '2026-05-02 12:38:54'),
(149, '69f5f05e5744f_1777725534.jpeg', 'att.Vf67Yg7Sxf3zX_ZK95goS4GArVr6Bh6xZBVX5AHSybw.jpeg', 'image/jpeg', 109130, '', '2026-05-02 12:38:54'),
(150, '69f5f05e64e96_1777725534.jpeg', 'att.1d_Y5CUP0RihzIlHxyIdUDEnWqtTsfu4iMGcHTNuETk.jpeg', 'image/jpeg', 120992, '', '2026-05-02 12:38:54'),
(151, '69f5f05e77cee_1777725534.jpeg', 'att.eoLlVEhr5oOx3sDCJxE8jJPoI2uhvcAlmzmosy15jlI.jpeg', 'image/jpeg', 118594, '', '2026-05-02 12:38:54'),
(152, '69f5f05e8aea2_1777725534.jpeg', 'att.kF4EuWrep09O6Q97ptAK45jT6fbzPhwrLvSASburGCQ.jpeg', 'image/jpeg', 117367, '', '2026-05-02 12:38:54'),
(153, '69f5f05e9da7d_1777725534.jpeg', 'att.TC-TwqBEFqFQZbOmahrUCSNvcz6aIA04rf-QnAyDL0c.jpeg', 'image/jpeg', 105070, '', '2026-05-02 12:38:54'),
(154, '69f5f05eac5f8_1777725534.jpeg', 'att.glBorjXvzGAdVX8Jge3hrQG6hkoXevktRn8ACvJ18Zk.jpeg', 'image/jpeg', 140802, '', '2026-05-02 12:38:54'),
(155, '69f5f05eb9ca3_1777725534.jpeg', 'att.v0trzgp1A3qpz69g60IOdf-rtUBZfzt2lpvqBPX71w4.jpeg', 'image/jpeg', 124202, '', '2026-05-02 12:38:54'),
(156, '69f5f05ec7297_1777725534.jpeg', 'att.oqqQmGsWMnmPh0gOmdMSQk1LQJBEXKnCKOLL7xcqBrw.jpeg', 'image/jpeg', 124804, '', '2026-05-02 12:38:54'),
(157, '69f5f2716a039_1777726065.jpeg', 'att.kF4EuWrep09O6Q97ptAK45jT6fbzPhwrLvSASburGCQ.jpeg', 'image/jpeg', 117367, '', '2026-05-02 12:47:45'),
(158, '69f5f2717dde3_1777726065.jpeg', 'att.Me1mOvNbNspgXbOjtUgkPnLPiL-hv0olTFsRDDRxa0k.jpeg', 'image/jpeg', 134479, '', '2026-05-02 12:47:45'),
(159, '69f5f27191004_1777726065.jpeg', 'att.GJkkp2ggxFxcMlb91g-B64veThHlVsTkYKEWfXtwTzU.jpeg', 'image/jpeg', 132411, '', '2026-05-02 12:47:45'),
(160, '69f5f2719ec87_1777726065.jpeg', 'att.SlyUYvQ4lU7_z-xdJv0InrzsQF5SN28J2FyvxJAVEF8.jpeg', 'image/jpeg', 116886, '', '2026-05-02 12:47:45'),
(161, '69f5f271abe8b_1777726065.jpeg', 'att.rgK8fnJKZ0C5EVaoQHJzDiHCF5zHy5sIJKAeVsy1odQ.jpeg', 'image/jpeg', 105413, '', '2026-05-02 12:47:45'),
(162, '69f5f271b8b0b_1777726065.jpeg', 'att.3YTH0wSGEH6z54txNvGhyoFgdcYwtbLphKJk_EMfCeY.jpeg', 'image/jpeg', 131978, '', '2026-05-02 12:47:45'),
(163, '69f5f271c5f3a_1777726065.jpeg', 'att.zgXiTRaHlQDK-EMFf34OGnvTN9z4ejCeDKSLSKjgvoQ.jpeg', 'image/jpeg', 128757, '', '2026-05-02 12:47:45'),
(164, '69f5f271d3c07_1777726065.jpeg', 'att.8t6CToZWrtuektf0CfNqIF6ZTMWOx99NjYpM67QVpi4.jpeg', 'image/jpeg', 134340, '', '2026-05-02 12:47:45'),
(165, '69f5f271e0e2c_1777726065.jpeg', 'att.OKukld6WNIfGR54bMRWo4HR0MxLslBKrqdpiwIEUYAw.jpeg', 'image/jpeg', 133815, '', '2026-05-02 12:47:45'),
(166, '69f5f271ede1f_1777726065.jpeg', 'att.mbvODiYWFMHfySYsmPXRCSSf7w7zM3Qt67TP0SbX3xA.jpeg', 'image/jpeg', 138694, '', '2026-05-02 12:47:46'),
(170, '69f627c796117_1777739719.jpeg', 'IMG_6527.jpeg', 'image/jpeg', 151271, '', '2026-05-02 16:35:19'),
(178, '69f62f921b104_1777741714.jpeg', '159,000.jpeg', 'image/jpeg', 122199, '', '2026-05-02 17:08:34'),
(179, '69f6fbe5f1bf0_1777794021.jpg', 'Unknown-2.jpg', 'image/jpeg', 126558, '', '2026-05-03 07:40:22'),
(180, '69f6fbfa53451_1777794042.jpg', 'Unknown-3.jpg', 'image/jpeg', 115264, '', '2026-05-03 07:40:42'),
(181, '69f6fbfb8cea7_1777794043.jpg', 'Unknown-4.jpg', 'image/jpeg', 114679, '', '2026-05-03 07:40:43'),
(182, '69f6fbfc533cd_1777794044.jpg', 'Unknown-5.jpg', 'image/jpeg', 103914, '', '2026-05-03 07:40:44'),
(183, '69f6fc067b178_1777794054.jpg', 'Unknown-6.jpg', 'image/jpeg', 102734, '', '2026-05-03 07:40:54'),
(184, '69f6ff7abd61f_1777794938.jpg', 'Unknown-7.jpg', 'image/jpeg', 91821, '', '2026-05-03 07:55:38'),
(185, '69f70417093e8_1777796119.png', 'e618e8fe0a4364d1271d0dcbd90a931e.png', 'image/png', 106228, '', '2026-05-03 08:15:19'),
(186, '69f70429a67a2_1777796137.jpg', 'IMG_2457 2.jpg', 'image/jpeg', 57933, '', '2026-05-03 08:15:37'),
(187, '69f706255e187_1777796645.jpg', 'Unknown-8.jpg', 'image/jpeg', 121472, '', '2026-05-03 08:24:05'),
(188, '69f7065e99e05_1777796702.jpg', 'Unknown-9.jpg', 'image/jpeg', 113026, '', '2026-05-03 08:25:02'),
(189, '69f7066025e46_1777796704.jpg', 'Unknown-10.jpg', 'image/jpeg', 109266, '', '2026-05-03 08:25:04'),
(190, '69f706683d3bd_1777796712.jpg', 'Unknown-11.jpg', 'image/jpeg', 98648, '', '2026-05-03 08:25:12'),
(191, '69f706690f3bc_1777796713.jpg', 'Unknown-12.jpg', 'image/jpeg', 117701, '', '2026-05-03 08:25:13'),
(192, '69f7066b50017_1777796715.jpg', 'Unknown.jpg', 'image/jpeg', 117602, '', '2026-05-03 08:25:15'),
(193, '69f7086d7560e_1777797229.jpg', 'Unknown-13.jpg', 'image/jpeg', 110120, '', '2026-05-03 08:33:49'),
(194, '69f7088a855e3_1777797258.jpg', 'Unknown-14.jpg', 'image/jpeg', 112813, '', '2026-05-03 08:34:18'),
(195, '69f7088b79a5d_1777797259.jpg', 'Unknown-15.jpg', 'image/jpeg', 112721, '', '2026-05-03 08:34:19'),
(196, '69f7088d18fb9_1777797261.jpg', 'Unknown-16.jpg', 'image/jpeg', 100638, '', '2026-05-03 08:34:21'),
(197, '69f7088d8eca4_1777797261.jpg', 'Unknown-17.jpg', 'image/jpeg', 108208, '', '2026-05-03 08:34:21'),
(198, '69f7092ff182e_1777797423.jpg', 'Unknown-22.jpg', 'image/jpeg', 112259, '', '2026-05-03 08:37:04'),
(199, '69f7098cb1a01_1777797516.jpg', 'Unknown-14.jpg', 'image/jpeg', 112813, '', '2026-05-03 08:38:36'),
(200, '69f7098cbde5e_1777797516.jpg', 'Unknown-15.jpg', 'image/jpeg', 112721, '', '2026-05-03 08:38:36'),
(201, '69f7098ccab36_1777797516.jpg', 'Unknown-16.jpg', 'image/jpeg', 100638, '', '2026-05-03 08:38:36'),
(202, '69f7098cd8335_1777797516.jpg', 'Unknown-17.jpg', 'image/jpeg', 108208, '', '2026-05-03 08:38:36'),
(203, '69f7098ce4c67_1777797516.jpg', 'Unknown-19.jpg', 'image/jpeg', 116286, '', '2026-05-03 08:38:36'),
(204, '69f7098cf0ecf_1777797516.jpg', 'Unknown-20.jpg', 'image/jpeg', 91900, '', '2026-05-03 08:38:37'),
(205, '69f7098d09cb2_1777797517.jpg', 'Unknown-21.jpg', 'image/jpeg', 105410, '', '2026-05-03 08:38:37'),
(206, '69f7098d16790_1777797517.jpg', 'Unknown-22.jpg', 'image/jpeg', 112259, '', '2026-05-03 08:38:37'),
(207, '69f70a433ab24_1777797699.jpg', 'Unknown-20.jpg', 'image/jpeg', 91900, '', '2026-05-03 08:41:39'),
(208, '69f70a43de802_1777797699.jpg', 'Unknown-21.jpg', 'image/jpeg', 105410, '', '2026-05-03 08:41:39'),
(209, '69f70a4482a58_1777797700.jpg', 'Unknown-22.jpg', 'image/jpeg', 112259, '', '2026-05-03 08:41:40'),
(210, '69f70e6462fd2_1777798756.jpg', 'Unknown-24.jpg', 'image/jpeg', 122080, '', '2026-05-03 08:59:16'),
(211, '69f70e647c2dd_1777798756.jpg', 'Unknown-25.jpg', 'image/jpeg', 109049, '', '2026-05-03 08:59:16'),
(212, '69f70e649050f_1777798756.jpg', 'Unknown-26.jpg', 'image/jpeg', 95386, '', '2026-05-03 08:59:16'),
(213, '69f70e64a1194_1777798756.jpg', 'Unknown-27.jpg', 'image/jpeg', 115317, '', '2026-05-03 08:59:16'),
(214, '69f70e94c6ac4_1777798804.jpg', 'Unknown-28.jpg', 'image/jpeg', 112852, '', '2026-05-03 09:00:04'),
(215, '69f70e94d9ea8_1777798804.jpg', 'Unknown-29.jpg', 'image/jpeg', 109197, '', '2026-05-03 09:00:04'),
(216, '69f70e94ecb21_1777798804.jpg', 'Unknown-30.jpg', 'image/jpeg', 105994, '', '2026-05-03 09:00:05'),
(217, '69f70e950a510_1777798805.jpg', 'Unknown-31.jpg', 'image/jpeg', 114558, '', '2026-05-03 09:00:05'),
(218, '69f70e951c4e5_1777798805.jpg', 'Unknown-32.jpg', 'image/jpeg', 104026, '', '2026-05-03 09:00:05'),
(219, '69f70edd1daff_1777798877.jpg', 'Unknown-24.jpg', 'image/jpeg', 122080, '', '2026-05-03 09:01:17'),
(220, '69f7101265585_1777799186.jpg', 'Unknown-33.jpg', 'image/jpeg', 99719, '', '2026-05-03 09:06:26'),
(221, '69f710127b3c9_1777799186.jpg', 'Unknown-34.jpg', 'image/jpeg', 98127, '', '2026-05-03 09:06:26'),
(222, '69f710128e65e_1777799186.jpg', 'Unknown-35.jpg', 'image/jpeg', 89190, '', '2026-05-03 09:06:26'),
(223, '69f710129f633_1777799186.jpg', 'Unknown-36.jpg', 'image/jpeg', 106592, '', '2026-05-03 09:06:26'),
(224, '69f71012b07b1_1777799186.jpg', 'Unknown-37.jpg', 'image/jpeg', 102984, '', '2026-05-03 09:06:26'),
(225, '69f71012c0fcd_1777799186.jpg', 'Unknown-38.jpg', 'image/jpeg', 116447, '', '2026-05-03 09:06:26'),
(226, '69f7102e5dfc1_1777799214.jpg', 'Unknown-33.jpg', 'image/jpeg', 99719, '', '2026-05-03 09:06:54'),
(227, '69f71043890b0_1777799235.jpg', 'Unknown-34.jpg', 'image/jpeg', 98127, '', '2026-05-03 09:07:15'),
(228, '69f71044a8d55_1777799236.jpg', 'Unknown-35.jpg', 'image/jpeg', 89190, '', '2026-05-03 09:07:16'),
(229, '69f7104572acc_1777799237.jpg', 'Unknown-36.jpg', 'image/jpeg', 106592, '', '2026-05-03 09:07:17'),
(230, '69f71045f12de_1777799237.jpg', 'Unknown-37.jpg', 'image/jpeg', 102984, '', '2026-05-03 09:07:18'),
(231, '69f710467dfb1_1777799238.jpg', 'Unknown-38.jpg', 'image/jpeg', 116447, '', '2026-05-03 09:07:18'),
(232, '69f7129af06e8_1777799834.jpg', 'Unknown-39.jpg', 'image/jpeg', 108266, '', '2026-05-03 09:17:15'),
(233, '69f712aaec5ca_1777799850.jpg', 'Unknown-40.jpg', 'image/jpeg', 110112, '', '2026-05-03 09:17:31'),
(234, '69f712ac2951d_1777799852.jpg', 'Unknown-41.jpg', 'image/jpeg', 112730, '', '2026-05-03 09:17:32'),
(235, '69f712accdf20_1777799852.jpg', 'Unknown-42.jpg', 'image/jpeg', 116956, '', '2026-05-03 09:17:32'),
(236, '69f712ad67398_1777799853.jpg', 'Unknown-43.jpg', 'image/jpeg', 117091, '', '2026-05-03 09:17:33'),
(237, '69f712ae087b4_1777799854.jpg', 'Unknown-44.jpg', 'image/jpeg', 116007, '', '2026-05-03 09:17:34'),
(238, '69f71528055af_1777800488.jpg', 'Unknown-45.jpg', 'image/jpeg', 117433, '', '2026-05-03 09:28:08'),
(239, '69f7152814409_1777800488.jpg', 'Unknown-46.jpg', 'image/jpeg', 107131, '', '2026-05-03 09:28:08'),
(240, '69f7152821144_1777800488.jpg', 'Unknown-47.jpg', 'image/jpeg', 93368, '', '2026-05-03 09:28:08'),
(241, '69f715282df15_1777800488.jpg', 'Unknown-48.jpg', 'image/jpeg', 123708, '', '2026-05-03 09:28:08'),
(242, '69f7171e13a29_1777800990.jpg', 'Unknown-49.jpg', 'image/jpeg', 116216, '', '2026-05-03 09:36:30'),
(243, '69f7173228d36_1777801010.jpg', 'Unknown-54.jpg', 'image/jpeg', 107814, '', '2026-05-03 09:36:50'),
(244, '69f717dd68792_1777801181.jpg', 'Unknown-55.jpg', 'image/jpeg', 108121, '', '2026-05-03 09:39:41'),
(245, '69f717dd7f73f_1777801181.jpg', 'Unknown-56.jpg', 'image/jpeg', 111947, '', '2026-05-03 09:39:41'),
(246, '69f717dd91b7f_1777801181.jpg', 'Unknown-57.jpg', 'image/jpeg', 96149, '', '2026-05-03 09:39:41'),
(247, '69f717dda2a6c_1777801181.jpg', 'Unknown-58.jpg', 'image/jpeg', 98815, '', '2026-05-03 09:39:41'),
(248, '69f717ef7eec9_1777801199.jpg', 'Unknown-50.jpg', 'image/jpeg', 106357, '', '2026-05-03 09:39:59'),
(249, '69f717ef90454_1777801199.jpg', 'Unknown-51.jpg', 'image/jpeg', 113302, '', '2026-05-03 09:39:59'),
(250, '69f717efa0775_1777801199.jpg', 'Unknown-52.jpg', 'image/jpeg', 98862, '', '2026-05-03 09:39:59'),
(251, '69f717efafdf9_1777801199.jpg', 'Unknown-53.jpg', 'image/jpeg', 102756, '', '2026-05-03 09:39:59'),
(252, '69f71bb5eb8a1_1777802165.jpg', 'att.Fo1vqKu2at7lqOIoZbOIkkvODueyRfM5pkgZ-nRRIHs.JPG', 'image/jpeg', 131574, '', '2026-05-03 09:56:06'),
(253, '69f71bb7428e9_1777802167.jpg', 'att.jw5iJm1I-_17WFPk0cbxQvso3Ujiwa69RazAPSR9aJw.JPG', 'image/jpeg', 102699, '', '2026-05-03 09:56:07'),
(254, '69f71bb87f1db_1777802168.jpg', 'att.v6-AKvDWEFVKlsigRSN88Ze_QlyiouiKwsTZevb592Y.JPG', 'image/jpeg', 124873, '', '2026-05-03 09:56:08'),
(255, '69f71bb9cd641_1777802169.jpg', 'att.zZI75h06RW0-E5Ke9XCt_ajDRC5pb5Pjn4OpL8DN-iY.JPG', 'image/jpeg', 114720, '', '2026-05-03 09:56:09'),
(256, '69f76efc4ac12_1777823484.jpg', 'new-balance-breeze-sd2202-black.jpg', 'image/jpeg', 45890, '', '2026-05-03 15:51:24'),
(257, '69f76f6ce0459_1777823596.jpg', '800x.jpg', 'image/jpeg', 31183, '', '2026-05-03 15:53:16'),
(258, '69f877cf991ab_1777891279.jpeg', 'att.6MRg4EGjoF_QmuW1ahboXV0aHQW1V2N-kLxNSqN8qtQ.jpeg', 'image/jpeg', 131273, '', '2026-05-04 10:41:19'),
(259, '69f877cfc1fd7_1777891279.jpeg', 'att.zpQi34eztRbAv7h2sMGJZdIQW4s2RVBGCXtTBdiI6ZE.jpeg', 'image/jpeg', 128743, '', '2026-05-04 10:41:19'),
(260, '69f877cfe7ab8_1777891279.jpeg', 'att.w6Cm_73PXofTm_zj0d3UYCD0NOM91txFon5BUuoWqjM.jpeg', 'image/jpeg', 137287, '', '2026-05-04 10:41:20'),
(261, '69f877d01698d_1777891280.jpeg', 'att.c9I5rPY4V_0qqS6qbR1QPVms1TuqRbp8e3wQ5Yl5X54.jpeg', 'image/jpeg', 101669, '', '2026-05-04 10:41:20'),
(262, '69f992be84729_1777963710.png', '3-39684_nike-logo-free-pictures-nike-air-max-logo.png', 'image/png', 41139, '', '2026-05-05 06:48:30'),
(263, '69f9937957eb1_1777963897.jpg', '625d06937efd51b47b430d2b499ba1ee.jpg', 'image/jpeg', 24642, '', '2026-05-05 06:51:37'),
(264, '69f993795df7d_1777963897.jpg', '631_dyson_logo.jpg', 'image/jpeg', 29150, '', '2026-05-05 06:51:37'),
(265, '69f993795e1cc_1777963897.jpg', 'jordan-logo-brand-symbol-design-clothes-sportwear-illustration-free-vector.jpg', 'image/jpeg', 39676, '', '2026-05-05 06:51:37'),
(266, '69f993c73a2cb_1777963975.webp', 'nike-basketball-z8yaalrzuxtolw53.webp', 'image/webp', 4976, '', '2026-05-05 06:52:55'),
(267, '69f993c73a549_1777963975.jpg', 'new-balance-breeze-sd2202-black.jpg', 'image/jpeg', 45890, '', '2026-05-05 06:52:55'),
(268, '69f993c73a70f_1777963975.jpg', 'b06b6b0b0376cde5221695ae6d0a1fd4.jpg', 'image/jpeg', 175454, '', '2026-05-05 06:52:55'),
(269, '69f9952b6ec3c_1777964331.jpg', '625d06937efd51b47b430d2b499ba1ee.jpg', 'image/jpeg', 24642, '', '2026-05-05 06:58:51'),
(270, '69f9958c42bc2_1777964428.png', '69f00ee091a31_1777340128.png', 'image/png', 231128, '', '2026-05-05 07:00:28'),
(271, '69f9958c42f00_1777964428.jpg', '625d06937efd51b47b430d2b499ba1ee.jpg', 'image/jpeg', 24642, '', '2026-05-05 07:00:28'),
(272, '69f9967f70bbc_1777964671.webp', '53d29d5c6bb3f7a80617ada8.webp', 'image/webp', 10598, '', '2026-05-05 07:04:31'),
(273, '69f99cbaa3633_1777966266.jpg', 'logo-of-shoe-icon-school-boot-isolated-sport-shoes-silhouette-design-for-male-vector.jpg', 'image/jpeg', 33922, '', '2026-05-05 07:31:06'),
(274, '69fa8b4b08dda_1778027339.png', 'icon-192.png', 'image/png', 47421, '', '2026-05-06 00:28:59'),
(276, '69fb5cdb9a9e8_1778080987.webp', '259512731664414.webp', 'image/webp', 65376, '', '2026-05-06 15:23:07'),
(277, '69fb5cdcbb47f_1778080988.webp', '259512732123166.webp', 'image/webp', 56338, '', '2026-05-06 15:23:08'),
(278, '69fb5eddc7405_1778081501.jpg', '11.jpg', 'image/jpeg', 234067, '', '2026-05-06 15:31:41'),
(279, '69fb5fa529316_1778081701.jpg', '12.jpg', 'image/jpeg', 178699, '', '2026-05-06 15:35:01'),
(280, '69fb61b8cceac_1778082232.webp', 'Gummy-Candy-30-mm-Large-Jelly-Filled-Gummies-Strawberry-Bulk-10-Pcs-Individually-Wrapped-1-18-Inches-Big_55b1e112-dc65-420b-99e1-045db6cb2220.4bf075818d1a10b00ef99401d3164758.jpeg.webp', 'image/webp', 75996, '', '2026-05-06 15:43:52'),
(281, '69fb61b9baa9d_1778082233.webp', '4e7a178a-e01f-4485-a482-db578f1d6624.ec7a386b4d4c946b54aaf46d61d9c1ab.jpeg.webp', 'image/webp', 90240, '', '2026-05-06 15:43:53'),
(282, '69fb61ba646c0_1778082234.webp', '19dac771-908a-49bf-ad30-80df5518833b.7bf3a1c0919b154fa2d17eae3f2e24a2.jpeg.webp', 'image/webp', 70272, '', '2026-05-06 15:43:54'),
(283, '69fc0b7c8987c_1778125692.jpeg', 'IMG_2639.jpeg', 'image/jpeg', 253730, '', '2026-05-07 03:48:12'),
(284, '69fc0b7eebd48_1778125694.jpeg', 'IMG_2640.jpeg', 'image/jpeg', 164627, '', '2026-05-07 03:48:14'),
(285, '69fc3b358837b_1778137909.jpeg', 'IMG_2567.jpeg', 'image/jpeg', 196375, '', '2026-05-07 07:11:49'),
(286, '69fc3ee2b5b52_1778138850.jpeg', 'IMG_2607.jpeg', 'image/jpeg', 250174, '', '2026-05-07 07:27:30'),
(287, '69fc4ab89f10b_1778141880.jpeg', 'IMG_2567.jpeg', 'image/jpeg', 196375, '', '2026-05-07 08:18:00'),
(288, '69fd71d008c91_1778217424.jpeg', 'IMG_2689.jpeg', 'image/jpeg', 137482, '', '2026-05-08 05:17:04'),
(289, '69fd71d40ba4c_1778217428.jpeg', 'IMG_2687.jpeg', 'image/jpeg', 129820, '', '2026-05-08 05:17:08'),
(290, '69fd71d563483_1778217429.jpeg', 'IMG_2688.jpeg', 'image/jpeg', 128725, '', '2026-05-08 05:17:09'),
(291, '69fd71d66d27b_1778217430.jpeg', 'IMG_2689.jpeg', 'image/jpeg', 137482, '', '2026-05-08 05:17:10'),
(292, '69fda434b72ec_1778230324.jpeg', 'IMG_2705.jpeg', 'image/jpeg', 221710, '', '2026-05-08 08:52:04'),
(293, '69fda6d7de17d_1778230999.jpeg', 'IMG_2598.jpeg', 'image/jpeg', 226640, '', '2026-05-08 09:03:19'),
(294, '6a0295d9b0b5d_1778554329.jpeg', 'IMG_2805.jpeg', 'image/jpeg', 272809, '', '2026-05-12 02:52:09'),
(295, '6a029d635ae2a_1778556259.jpeg', 'IMG_2830.jpeg', 'image/jpeg', 392149, '', '2026-05-12 03:24:19'),
(296, '6a02aa1072f2d_1778559504.jpg', '8bfd91b0-c655-4c9d-b46f-354f111c9123.jpg', 'image/jpeg', 53448, '', '2026-05-12 04:18:24'),
(297, '6a02aa10f21d9_1778559504.jpg', '31f8b7a0-e5b9-4661-994a-ba62953e07d7.jpg', 'image/jpeg', 56429, '', '2026-05-12 04:18:25'),
(298, '6a02b0387aa5c_1778561080.jpeg', 'IMG_2850.jpeg', 'image/jpeg', 237904, '', '2026-05-12 04:44:40'),
(299, '6a02b041bd9bc_1778561089.jpeg', 'IMG_2851.jpeg', 'image/jpeg', 256880, '', '2026-05-12 04:44:49'),
(300, '6a02b044ae6d0_1778561092.jpeg', 'IMG_2852.jpeg', 'image/jpeg', 315645, '', '2026-05-12 04:44:52'),
(301, '6a02c62c1547f_1778566700.jpeg', 'IMG_2773.jpeg', 'image/jpeg', 251319, '', '2026-05-12 06:18:20'),
(302, '6a02c9ee15f91_1778567662.jpeg', 'att.t0yIafq90AfrjFrCzuNgZr_5M6K5HYM12nLMp-gAJaI.jpeg', 'image/jpeg', 163607, '', '2026-05-12 06:34:22'),
(303, '6a0308bf9b1dc_1778583743.jpeg', 'IMG_6718.jpeg', 'image/jpeg', 392393, '', '2026-05-12 11:02:23'),
(304, '6a030a1b871e0_1778584091.jpeg', 'IMG_6811.jpeg', 'image/jpeg', 284218, '', '2026-05-12 11:08:11'),
(305, '6a030cfaea8fa_1778584826.png', 'IMG_6814.PNG', 'image/png', 570093, '', '2026-05-12 11:20:27'),
(306, '6a030d34aaedb_1778584884.png', 'IMG_6812.PNG', 'image/png', 438905, '', '2026-05-12 11:21:25'),
(307, '6a030f4f078a3_1778585423.jpeg', 'IMG_6815.jpeg', 'image/jpeg', 42594, '', '2026-05-12 11:30:23'),
(308, '6a0310fb8b7b6_1778585851.jpeg', 'IMG_6817.jpeg', 'image/jpeg', 171726, '', '2026-05-12 11:37:31'),
(309, '6a031101e044f_1778585857.jpeg', 'IMG_6818.jpeg', 'image/jpeg', 147284, '', '2026-05-12 11:37:37'),
(310, '6a031102822eb_1778585858.jpeg', 'IMG_6819.jpeg', 'image/jpeg', 91003, '', '2026-05-12 11:37:38'),
(311, '6a031102f3c35_1778585858.jpeg', 'IMG_6817.jpeg', 'image/jpeg', 171726, '', '2026-05-12 11:37:38'),
(312, '6a03136a90fe5_1778586474.jpeg', 'IMG_6822.jpeg', 'image/jpeg', 150832, '', '2026-05-12 11:47:54'),
(313, '6a031378a5c5b_1778586488.png', 'IMG_6821.png', 'image/png', 983507, '', '2026-05-12 11:48:08'),
(314, '6a03137a9e4fd_1778586490.webp', 'IMG_6823.webp', 'image/webp', 144862, '', '2026-05-12 11:48:10'),
(315, '6a03137bc5da2_1778586491.jpeg', 'IMG_6822.jpeg', 'image/jpeg', 150832, '', '2026-05-12 11:48:11'),
(316, '6a031387a4265_1778586503.jpeg', 'IMG_6822.jpeg', 'image/jpeg', 150832, '', '2026-05-12 11:48:23'),
(317, '6a0480000a3df_1778679808.jpeg', 'dyson bp04..jpeg', 'image/jpeg', 61976, '', '2026-05-13 13:43:28'),
(318, '6a04804f9494c_1778679887.jpg', 'BP03-PDP-PWC-1-2.jpg', 'image/jpeg', 25341, '', '2026-05-13 13:44:47'),
(319, '6a04804f94f4b_1778679887.jpg', 'BP03-PDP-PWC-1-3.jpg', 'image/jpeg', 30266, '', '2026-05-13 13:44:47'),
(320, '6a04804f951f2_1778679887.jpeg', 'BP04-ALT_With_Copy_08.png.jpeg', 'image/jpeg', 45411, '', '2026-05-13 13:44:47'),
(321, '6a0481f2ad236_1778680306.jpeg', 'Image 1.jpeg', 'image/jpeg', 98183, '', '2026-05-13 13:51:46'),
(322, '6a0481f2c1811_1778680306.jpeg', 'Image 2.jpeg', 'image/jpeg', 62085, '', '2026-05-13 13:51:46'),
(323, '6a0481f2cdf19_1778680306.jpeg', 'Image 3.jpeg', 'image/jpeg', 67522, '', '2026-05-13 13:51:46'),
(324, '6a0481f2dc656_1778680306.jpeg', 'Image 4.jpeg', 'image/jpeg', 61305, '', '2026-05-13 13:51:46'),
(325, '6a0481f2eabf1_1778680306.jpeg', 'Image 5.jpeg', 'image/jpeg', 51528, '', '2026-05-13 13:51:47'),
(326, '6a0481f300dee_1778680307.jpeg', 'Image 6.jpeg', 'image/jpeg', 45371, '', '2026-05-13 13:51:47'),
(327, '6a0481f30beec_1778680307.jpeg', 'Image 7.jpeg', 'image/jpeg', 85697, '', '2026-05-13 13:51:47'),
(328, '6a0481f321593_1778680307.jpeg', 'Image 8.jpeg', 'image/jpeg', 65027, '', '2026-05-13 13:51:47'),
(329, '6a0481f32fed6_1778680307.jpeg', 'Image 9.jpeg', 'image/jpeg', 52846, '', '2026-05-13 13:51:47'),
(330, '6a0481f33bdee_1778680307.jpeg', 'Image 10.jpeg', 'image/jpeg', 54720, '', '2026-05-13 13:51:47'),
(331, '6a04870c3e32e_1778681612.jpeg', 'Image 11.jpeg', 'image/jpeg', 59664, '', '2026-05-13 14:13:32'),
(332, '6a048784ea251_1778681732.jpeg', 'Image 12.jpeg', 'image/jpeg', 9517, '', '2026-05-13 14:15:32'),
(333, '6a0489c07a3f8_1778682304.jpeg', 'Image 14.jpeg', 'image/jpeg', 50617, '', '2026-05-13 14:25:04'),
(334, '6a0489cd86926_1778682317.jpeg', 'Image 15.jpeg', 'image/jpeg', 98007, '', '2026-05-13 14:25:17'),
(335, '6a0489d191623_1778682321.jpeg', 'Image 16.jpeg', 'image/jpeg', 59432, '', '2026-05-13 14:25:21'),
(336, '6a0489d3b46f7_1778682323.jpeg', 'Image 17.jpeg', 'image/jpeg', 59566, '', '2026-05-13 14:25:23'),
(337, '6a0489d66ba5a_1778682326.jpeg', 'Image 18.jpeg', 'image/jpeg', 99774, '', '2026-05-13 14:25:26'),
(338, '6a0489d7846a2_1778682327.jpeg', 'Image 19.jpeg', 'image/jpeg', 34906, '', '2026-05-13 14:25:27'),
(339, '6a048a3d0724a_1778682429.jpeg', 'Image 20.jpeg', 'image/jpeg', 35978, '', '2026-05-13 14:27:09'),
(340, '6a048ae0b5b06_1778682592.jpeg', 'Image 21.jpeg', 'image/jpeg', 3715, '', '2026-05-13 14:29:52'),
(341, '6a048b856eb28_1778682757.jpeg', 'KR-PH04-WHSGLD_Primary_1_withIcon.png.jpeg', 'image/jpeg', 57511, '', '2026-05-13 14:32:37'),
(342, '6a048c0dceb06_1778682893.jpeg', 'Image 22.jpeg', 'image/jpeg', 55822, '', '2026-05-13 14:34:53'),
(343, '6a048d6438b3f_1778683236.jpeg', 'Image 23.jpeg', 'image/jpeg', 46401, '', '2026-05-13 14:40:36'),
(344, '6a048d686b400_1778683240.jpeg', 'Image 24.jpeg', 'image/jpeg', 80754, '', '2026-05-13 14:40:40'),
(345, '6a048d6bde6ab_1778683243.jpeg', 'Image 25.jpeg', 'image/jpeg', 61616, '', '2026-05-13 14:40:43'),
(346, '6a048d6fc9c9a_1778683247.jpeg', 'Image 26.jpeg', 'image/jpeg', 83147, '', '2026-05-13 14:40:47'),
(347, '6a048d785020a_1778683256.jpeg', 'Image 27.jpeg', 'image/jpeg', 75940, '', '2026-05-13 14:40:56'),
(348, '6a048d8250dfa_1778683266.jpeg', 'Image 28.jpeg', 'image/jpeg', 51205, '', '2026-05-13 14:41:06'),
(349, '6a048d8543b62_1778683269.jpeg', 'Image 29.jpeg', 'image/jpeg', 65260, '', '2026-05-13 14:41:09'),
(350, '6a048e9da275f_1778683549.jpeg', 'Image 30.jpeg', 'image/jpeg', 65435, '', '2026-05-13 14:45:49'),
(351, '6a048e9e2f10b_1778683550.jpeg', 'Image 31.jpeg', 'image/jpeg', 75612, '', '2026-05-13 14:45:50'),
(352, '6a048e9f0cfda_1778683551.jpeg', 'Image 32.jpeg', 'image/jpeg', 54360, '', '2026-05-13 14:45:51'),
(353, '6a048e9f7db3c_1778683551.jpeg', 'Image 33.jpeg', 'image/jpeg', 68737, '', '2026-05-13 14:45:51'),
(354, '6a048f6c1a92c_1778683756.jpeg', 'KR-438E_NKSGLD_0_Primary_withIcon.png.jpeg', 'image/jpeg', 43186, '', '2026-05-13 14:49:16'),
(355, '6a048fcfd7d4d_1778683855.jpeg', 'Image 35.jpeg', 'image/jpeg', 27540, '', '2026-05-13 14:50:55'),
(356, '6a048fd0bef7a_1778683856.jpeg', 'Image 36.jpeg', 'image/jpeg', 95338, '', '2026-05-13 14:50:56'),
(357, '6a048fd1760d6_1778683857.jpeg', 'Image 37.jpeg', 'image/jpeg', 63147, '', '2026-05-13 14:50:57'),
(358, '6a048fd1c46b3_1778683857.jpeg', 'Image 38.jpeg', 'image/jpeg', 52480, '', '2026-05-13 14:50:57'),
(359, '6a048fd249272_1778683858.jpeg', 'Image 39.jpeg', 'image/jpeg', 75586, '', '2026-05-13 14:50:58'),
(360, '6a048fd287d39_1778683858.jpeg', 'Image 40.jpeg', 'image/jpeg', 20621, '', '2026-05-13 14:50:58'),
(361, '6a048fd2c8c82_1778683858.jpeg', 'Image 41.jpeg', 'image/jpeg', 30975, '', '2026-05-13 14:50:58'),
(362, '6a0491858d976_1778684293.jpeg', 'Image 46.jpeg', 'image/jpeg', 25309, '', '2026-05-13 14:58:13'),
(363, '6a04918858f0b_1778684296.jpeg', 'Image 47.jpeg', 'image/jpeg', 44008, '', '2026-05-13 14:58:16'),
(364, '6a04918a2c9a6_1778684298.jpeg', 'Image 45.jpeg', 'image/jpeg', 32367, '', '2026-05-13 14:58:18'),
(365, '6a04918d63bf9_1778684301.jpeg', 'Image 44.jpeg', 'image/jpeg', 31008, '', '2026-05-13 14:58:21'),
(366, '6a04919309cce_1778684307.jpeg', 'Image 43.jpeg', 'image/jpeg', 69647, '', '2026-05-13 14:58:27'),
(367, '6a0493982bb85_1778684824.jpeg', 'Image 49.jpeg', 'image/jpeg', 23267, '', '2026-05-13 15:07:04'),
(368, '6a049516716ba_1778685206.jpeg', 'Image 51.jpeg', 'image/jpeg', 53545, '', '2026-05-13 15:13:26'),
(369, '6a04951b42d23_1778685211.jpeg', 'Image 50.jpeg', 'image/jpeg', 108634, '', '2026-05-13 15:13:31'),
(370, '6a049529bc882_1778685225.jpeg', 'Image 53.jpeg', 'image/jpeg', 68626, '', '2026-05-13 15:13:45'),
(371, '6a04952f83080_1778685231.jpeg', 'Image 54.jpeg', 'image/jpeg', 58224, '', '2026-05-13 15:13:51'),
(372, '6a049531a3fb9_1778685233.jpeg', 'Image 55.jpeg', 'image/jpeg', 76358, '', '2026-05-13 15:13:53'),
(373, '6a049532f26c5_1778685234.jpeg', 'Image 56.jpeg', 'image/jpeg', 34173, '', '2026-05-13 15:13:55'),
(374, '6a0495383ce5f_1778685240.jpeg', 'Image 57.jpeg', 'image/jpeg', 167741, '', '2026-05-13 15:14:00'),
(375, '6a0496da7053a_1778685658.jpeg', 'Image 58.jpeg', 'image/jpeg', 66327, '', '2026-05-13 15:20:58'),
(376, '6a04981b3ff03_1778685979.jpeg', 'Image 59.jpeg', 'image/jpeg', 30212, '', '2026-05-13 15:26:19'),
(377, '6a04982702a58_1778685991.jpeg', 'Image 60.jpeg', 'image/jpeg', 141254, '', '2026-05-13 15:26:31'),
(378, '6a04982798bdb_1778685991.jpeg', 'Image 61.jpeg', 'image/jpeg', 11341, '', '2026-05-13 15:26:31'),
(379, '6a04982992ef0_1778685993.jpeg', 'Image 62.jpeg', 'image/jpeg', 60622, '', '2026-05-13 15:26:33'),
(380, '6a04982a91e2b_1778685994.jpeg', 'Image 63.jpeg', 'image/jpeg', 46504, '', '2026-05-13 15:26:34'),
(381, '6a04982be95ad_1778685995.jpeg', 'Image 64.jpeg', 'image/jpeg', 67898, '', '2026-05-13 15:26:35'),
(382, '6a04982c80537_1778685996.jpeg', 'Image 65.jpeg', 'image/jpeg', 46797, '', '2026-05-13 15:26:36'),
(383, '6a04982e1b363_1778685998.jpeg', 'Image 66.jpeg', 'image/jpeg', 60799, '', '2026-05-13 15:26:38'),
(384, '6a049b1220089_1778686738.jpeg', 'Image 68.jpeg', 'image/jpeg', 116513, '', '2026-05-13 15:38:58'),
(385, '6a052c7038ef6_1778723952.jpeg', 'IMG_6840.jpeg', 'image/jpeg', 68043, '', '2026-05-14 01:59:12'),
(386, '6a052c7b72375_1778723963.jpeg', 'IMG_6842.jpeg', 'image/jpeg', 102276, '', '2026-05-14 01:59:23'),
(387, '6a052c7d0ef26_1778723965.jpeg', 'IMG_6841.jpeg', 'image/jpeg', 88915, '', '2026-05-14 01:59:25'),
(388, '6a052cf827850_1778724088.jpeg', 'IMG_6736.jpeg', 'image/jpeg', 234134, '', '2026-05-14 02:01:28'),
(389, '6a052d1614abb_1778724118.jpeg', 'IMG_6843.jpeg', 'image/jpeg', 256370, '', '2026-05-14 02:01:58'),
(390, '6a052facf1ce0_1778724780.jpeg', 'IMG_6850.jpeg', 'image/jpeg', 93014, '', '2026-05-14 02:13:00'),
(391, '6a052fcb59822_1778724811.jpeg', 'IMG_6850.jpeg', 'image/jpeg', 93014, '', '2026-05-14 02:13:31'),
(392, '6a052fcde2fda_1778724813.jpeg', 'IMG_6849.jpeg', 'image/jpeg', 251977, '', '2026-05-14 02:13:33'),
(393, '6a052fced99e7_1778724814.jpeg', 'IMG_6848.jpeg', 'image/jpeg', 94389, '', '2026-05-14 02:13:34'),
(394, '6a052fd026604_1778724816.jpeg', 'IMG_6847.jpeg', 'image/jpeg', 162348, '', '2026-05-14 02:13:36'),
(395, '6a052fd0c84df_1778724816.jpeg', 'IMG_6846.jpeg', 'image/jpeg', 83535, '', '2026-05-14 02:13:36'),
(396, '6a052fd213a91_1778724818.jpeg', 'IMG_6851.jpeg', 'image/jpeg', 222959, '', '2026-05-14 02:13:38'),
(397, '6a05519ac312a_1778733466.jpg', 'Unknown-63.jpg', 'image/jpeg', 108750, '', '2026-05-14 04:37:46'),
(398, '6a05519daf09d_1778733469.jpg', 'Unknown-62.jpg', 'image/jpeg', 99339, '', '2026-05-14 04:37:49'),
(399, '6a0551a118656_1778733473.jpg', 'Unknown-64.jpg', 'image/jpeg', 124960, '', '2026-05-14 04:37:53'),
(400, '6a0553624deb7_1778733922.jpg', 'Unknown-65.jpg', 'image/jpeg', 94966, '', '2026-05-14 04:45:22'),
(401, '6a05536277b2f_1778733922.jpg', 'Unknown-64.jpg', 'image/jpeg', 124960, '', '2026-05-14 04:45:22'),
(402, '6a0553724698e_1778733938.jpg', 'Unknown-64.jpg', 'image/jpeg', 124960, '', '2026-05-14 04:45:38'),
(403, '6a05537320e71_1778733939.jpg', 'Unknown-65.jpg', 'image/jpeg', 94966, '', '2026-05-14 04:45:39'),
(404, '6a06b949afcec_1778825545.jpeg', 'IMG_3035.jpeg', 'image/jpeg', 155009, '', '2026-05-15 06:12:25'),
(405, '6a06b94f66325_1778825551.jpeg', 'IMG_3036.jpeg', 'image/jpeg', 275568, '', '2026-05-15 06:12:31'),
(406, '6a06b95060471_1778825552.jpeg', 'IMG_3038.jpeg', 'image/jpeg', 226744, '', '2026-05-15 06:12:32'),
(407, '6a06b9516fa0e_1778825553.jpeg', 'IMG_3039.jpeg', 'image/jpeg', 184191, '', '2026-05-15 06:12:33'),
(408, '6a0c6bbf42098_1779198911.jpeg', 'Image 1.jpeg', 'image/jpeg', 23876, '', '2026-05-19 13:55:11'),
(409, '6a0c6bdd3f824_1779198941.jpeg', 'Image 2.jpeg', 'image/jpeg', 19598, '', '2026-05-19 13:55:41'),
(410, '6a0c6cb983bc1_1779199161.jpeg', 'Image 3.jpeg', 'image/jpeg', 21457, '', '2026-05-19 13:59:21'),
(411, '6a0c6cb9ae774_1779199161.jpeg', 'Image 4.jpeg', 'image/jpeg', 16710, '', '2026-05-19 13:59:21'),
(412, '6a0c6cb9d9a22_1779199161.jpeg', 'Image 5.jpeg', 'image/jpeg', 21456, '', '2026-05-19 13:59:22'),
(413, '6a0c6cba0e13b_1779199162.jpeg', 'Image 6.jpeg', 'image/jpeg', 17721, '', '2026-05-19 13:59:22'),
(414, '6a0c6d05362bc_1779199237.jpeg', 'Image 7.jpeg', 'image/jpeg', 58540, '', '2026-05-19 14:00:37'),
(415, '6a0e186ed7fdc_1779308654.jpeg', 'IMG_3292.jpeg', 'image/jpeg', 264253, '', '2026-05-20 20:24:14'),
(416, '6a0e1a4650012_1779309126.jpeg', 'IMG_3292.jpeg', 'image/jpeg', 264253, '', '2026-05-20 20:32:06'),
(417, '6a0e1c827ef01_1779309698.jpeg', 'IMG_3291.jpeg', 'image/jpeg', 337685, '', '2026-05-20 20:41:38'),
(418, '6a0e9de663260_1779342822.jpeg', 'IMG_6962.jpeg', 'image/jpeg', 66426, '', '2026-05-21 05:53:42'),
(419, '6a0e9df18fd3f_1779342833.jpeg', 'IMG_6963.jpeg', 'image/jpeg', 111496, '', '2026-05-21 05:53:53'),
(420, '6a0e9df285926_1779342834.jpeg', 'IMG_6964.jpeg', 'image/jpeg', 55706, '', '2026-05-21 05:53:54'),
(421, '6a0eea06544d3_1779362310.jpeg', 'Image.jpeg', 'image/jpeg', 40980, '', '2026-05-21 11:18:30'),
(422, '6a0eea1625fc4_1779362326.jpeg', 'Image 2.jpeg', 'image/jpeg', 41789, '', '2026-05-21 11:18:46'),
(423, '6a0eea176cb93_1779362327.jpeg', 'Image 3.jpeg', 'image/jpeg', 45049, '', '2026-05-21 11:18:47');
INSERT INTO `media` (`id`, `filename`, `original_name`, `mime_type`, `file_size`, `alt_text`, `created_at`) VALUES
(424, '6a0eea1887b53_1779362328.jpeg', 'Image 4.jpeg', 'image/jpeg', 55984, '', '2026-05-21 11:18:48'),
(425, '6a0eea195bb9c_1779362329.jpeg', 'Image 5.jpeg', 'image/jpeg', 39226, '', '2026-05-21 11:18:49'),
(426, '6a0eea1a104ed_1779362330.jpeg', 'Image 6.jpeg', 'image/jpeg', 42726, '', '2026-05-21 11:18:50'),
(427, '6a0eef30a5eee_1779363632.jpeg', 'Image 7.jpeg', 'image/jpeg', 62662, '', '2026-05-21 11:40:32'),
(428, '6a0eef3eefd46_1779363646.jpeg', 'Image 8.jpeg', 'image/jpeg', 61101, '', '2026-05-21 11:40:47'),
(429, '6a0eef4110dd9_1779363649.jpeg', 'Image 9.jpeg', 'image/jpeg', 61378, '', '2026-05-21 11:40:49'),
(430, '6a0eef42b9df1_1779363650.jpeg', 'Image 10.jpeg', 'image/jpeg', 92725, '', '2026-05-21 11:40:50'),
(431, '6a0eef4476a45_1779363652.jpeg', 'Image 11.jpeg', 'image/jpeg', 59807, '', '2026-05-21 11:40:52'),
(432, '6a0ef28a0896d_1779364490.jpeg', 'Image 12.jpeg', 'image/jpeg', 58121, '', '2026-05-21 11:54:50'),
(433, '6a0ef28a7286a_1779364490.jpeg', 'Image 13.jpeg', 'image/jpeg', 57666, '', '2026-05-21 11:54:50'),
(434, '6a0ef28b4e661_1779364491.jpeg', 'Image 14.jpeg', 'image/jpeg', 67514, '', '2026-05-21 11:54:51'),
(435, '6a0ef28bbfe87_1779364491.jpeg', 'Image 15.jpeg', 'image/jpeg', 58341, '', '2026-05-21 11:54:51'),
(436, '6a0ef28c78eea_1779364492.jpeg', 'Image 16.jpeg', 'image/jpeg', 76792, '', '2026-05-21 11:54:52'),
(437, '6a0ef28d2cc97_1779364493.jpeg', 'Image 17.jpeg', 'image/jpeg', 61052, '', '2026-05-21 11:54:53'),
(438, '6a0ef45ea0cc3_1779364958.jpeg', 'Image 18.jpeg', 'image/jpeg', 73304, '', '2026-05-21 12:02:38'),
(439, '6a0ef45fd3a25_1779364959.jpeg', 'Image 19.jpeg', 'image/jpeg', 76480, '', '2026-05-21 12:02:39'),
(440, '6a0ef4611f235_1779364961.jpeg', 'Image 20.jpeg', 'image/jpeg', 83016, '', '2026-05-21 12:02:41'),
(441, '6a0ef46631501_1779364966.jpeg', 'Image 21.jpeg', 'image/jpeg', 228796, '', '2026-05-21 12:02:46'),
(442, '6a0ef469ef186_1779364969.jpeg', 'Image 22.jpeg', 'image/jpeg', 182999, '', '2026-05-21 12:02:50'),
(443, '6a0ef46dd5955_1779364973.jpeg', 'Image 23.jpeg', 'image/jpeg', 207127, '', '2026-05-21 12:02:53'),
(444, '6a0ef47000a4d_1779364976.jpeg', 'Image 24.jpeg', 'image/jpeg', 119564, '', '2026-05-21 12:02:56'),
(445, '6a0ef83456135_1779365940.jpeg', 'Image 37.jpeg', 'image/jpeg', 59743, '', '2026-05-21 12:19:00'),
(446, '6a0ef858e1fe7_1779365976.jpeg', 'Image 32.jpeg', 'image/jpeg', 67806, '', '2026-05-21 12:19:36'),
(447, '6a0ef85a01c8c_1779365978.jpeg', 'Image 28.jpeg', 'image/jpeg', 63088, '', '2026-05-21 12:19:38'),
(448, '6a0ef85b06786_1779365979.jpeg', 'Image 33.jpeg', 'image/jpeg', 88017, '', '2026-05-21 12:19:39'),
(449, '6a0ef85c16e6e_1779365980.jpeg', 'Image 34.jpeg', 'image/jpeg', 81434, '', '2026-05-21 12:19:40'),
(450, '6a0ef85de8f2b_1779365981.jpeg', 'Image 35.jpeg', 'image/jpeg', 163922, '', '2026-05-21 12:19:42'),
(451, '6a0ef85fbbe53_1779365983.jpeg', 'Image 36.jpeg', 'image/jpeg', 155196, '', '2026-05-21 12:19:43'),
(452, '6a0efb5fbda2c_1779366751.jpeg', 'Image 38.jpeg', 'image/jpeg', 107524, '', '2026-05-21 12:32:31'),
(453, '6a0efb60a5f24_1779366752.jpeg', 'Image 39.jpeg', 'image/jpeg', 107071, '', '2026-05-21 12:32:32'),
(454, '6a0efb61e63f3_1779366753.jpeg', 'Image 40.jpeg', 'image/jpeg', 169481, '', '2026-05-21 12:32:33'),
(455, '6a0efb62c332b_1779366754.jpeg', 'Image 41.jpeg', 'image/jpeg', 146400, '', '2026-05-21 12:32:34'),
(456, '6a0efb6347a19_1779366755.jpeg', 'Image 42.jpeg', 'image/jpeg', 97360, '', '2026-05-21 12:32:35'),
(457, '6a0efeaed7362_1779367598.jpeg', 'Image 43.jpeg', 'image/jpeg', 43881, '', '2026-05-21 12:46:38'),
(458, '6a0efeaf6d9ac_1779367599.jpeg', 'Image 44.jpeg', 'image/jpeg', 47140, '', '2026-05-21 12:46:39'),
(459, '6a0efeb010e18_1779367600.jpeg', 'Image 45.jpeg', 'image/jpeg', 27303, '', '2026-05-21 12:46:40'),
(460, '6a0efeb099131_1779367600.jpeg', 'Image 48.jpeg', 'image/jpeg', 45250, '', '2026-05-21 12:46:40'),
(461, '6a0efeb0f0970_1779367600.jpeg', 'Image 46.jpeg', 'image/jpeg', 19532, '', '2026-05-21 12:46:41'),
(462, '6a0efeb174f99_1779367601.jpeg', 'Image 47.jpeg', 'image/jpeg', 29244, '', '2026-05-21 12:46:41'),
(463, '6a0efeb388338_1779367603.jpeg', 'Image 49.jpeg', 'image/jpeg', 145294, '', '2026-05-21 12:46:43'),
(464, '6a0efeb59ba44_1779367605.jpeg', 'Image 50.jpeg', 'image/jpeg', 142853, '', '2026-05-21 12:46:45'),
(465, '6a0f095910ab8_1779370329.jpeg', 'Image 53.jpeg', 'image/jpeg', 45617, '', '2026-05-21 13:32:09'),
(466, '6a0f095990430_1779370329.jpeg', 'Image 54.jpeg', 'image/jpeg', 49111, '', '2026-05-21 13:32:09'),
(467, '6a0f095bc3d3f_1779370331.jpeg', 'Image 55.jpeg', 'image/jpeg', 152828, '', '2026-05-21 13:32:11'),
(468, '6a0f095e3b453_1779370334.jpeg', 'Image 56.jpeg', 'image/jpeg', 144520, '', '2026-05-21 13:32:14'),
(469, '6a105afb39e47_1779456763.jpeg', 'Image.jpeg', 'image/jpeg', 62362, '', '2026-05-22 13:32:43'),
(470, '6a105afc7f098_1779456764.jpeg', 'Image 3.jpeg', 'image/jpeg', 90233, '', '2026-05-22 13:32:44'),
(471, '6a105afd8a32f_1779456765.jpeg', 'Image 1.jpeg', 'image/jpeg', 109458, '', '2026-05-22 13:32:45'),
(472, '6a105afe3f6bc_1779456766.jpeg', 'Image 4.jpeg', 'image/jpeg', 107932, '', '2026-05-22 13:32:46'),
(473, '6a105afee3c41_1779456766.jpeg', 'Image 5.jpeg', 'image/jpeg', 61454, '', '2026-05-22 13:32:46'),
(474, '6a1061d04264d_1779458512.jpeg', 'Image 7.jpeg', 'image/jpeg', 37670, '', '2026-05-22 14:01:52'),
(475, '6a1061d1b39e0_1779458513.jpeg', 'Image 8.jpeg', 'image/jpeg', 39281, '', '2026-05-22 14:01:53'),
(476, '6a1061d343f39_1779458515.jpeg', 'Image 9.jpeg', 'image/jpeg', 42825, '', '2026-05-22 14:01:55'),
(477, '6a1061d5298ac_1779458517.jpeg', 'Image 10.jpeg', 'image/jpeg', 69704, '', '2026-05-22 14:01:57'),
(478, '6a1061d9963b3_1779458521.jpeg', 'Image 11.jpeg', 'image/jpeg', 104374, '', '2026-05-22 14:02:01'),
(479, '6a1061db93572_1779458523.jpeg', 'Image 12.jpeg', 'image/jpeg', 111003, '', '2026-05-22 14:02:03'),
(480, '6a1061dc76104_1779458524.jpeg', 'Image 13.jpeg', 'image/jpeg', 45640, '', '2026-05-22 14:02:04'),
(481, '6a1063aced489_1779458988.jpeg', 'Image 14.jpeg', 'image/jpeg', 73007, '', '2026-05-22 14:09:49'),
(482, '6a1063ad92d93_1779458989.jpeg', 'Image 15.jpeg', 'image/jpeg', 101878, '', '2026-05-22 14:09:49'),
(483, '6a1063af45949_1779458991.jpeg', 'Image 16.jpeg', 'image/jpeg', 73033, '', '2026-05-22 14:09:51'),
(484, '6a1063b139082_1779458993.jpeg', 'Image 17.jpeg', 'image/jpeg', 331215, '', '2026-05-22 14:09:53'),
(485, '6a1063b4595e3_1779458996.jpeg', 'Image 18.jpeg', 'image/jpeg', 197511, '', '2026-05-22 14:09:56'),
(486, '6a1063b5d857a_1779458997.jpeg', 'Image 19.jpeg', 'image/jpeg', 69353, '', '2026-05-22 14:09:57'),
(487, '6a1063b6ab343_1779458998.jpeg', 'Image 20.jpeg', 'image/jpeg', 52658, '', '2026-05-22 14:09:58'),
(488, '6a1063b88c673_1779459000.jpeg', 'Image 21.jpeg', 'image/jpeg', 105047, '', '2026-05-22 14:10:00'),
(489, '6a107222973b6_1779462690.jpeg', 'Image 22.jpeg', 'image/jpeg', 67097, '', '2026-05-22 15:11:30'),
(490, '6a10722429d2e_1779462692.jpeg', 'Image 23.jpeg', 'image/jpeg', 63943, '', '2026-05-22 15:11:32'),
(491, '6a107225691e5_1779462693.jpeg', 'Image 24.jpeg', 'image/jpeg', 78334, '', '2026-05-22 15:11:33'),
(492, '6a107227a9566_1779462695.jpeg', 'Image 25.jpeg', 'image/jpeg', 123927, '', '2026-05-22 15:11:35'),
(493, '6a10722895b49_1779462696.jpeg', 'Image 26.jpeg', 'image/jpeg', 67163, '', '2026-05-22 15:11:36'),
(494, '6a10722a8ad16_1779462698.jpeg', 'Image 27.jpeg', 'image/jpeg', 164235, '', '2026-05-22 15:11:38'),
(495, '6a10722d365fa_1779462701.jpeg', 'Image 28.jpeg', 'image/jpeg', 212459, '', '2026-05-22 15:11:41'),
(496, '6a10722d83323_1779462701.jpeg', 'Image 29.jpeg', 'image/jpeg', 31054, '', '2026-05-22 15:11:41'),
(497, '6a10722e22e02_1779462702.jpeg', 'Image 30.jpeg', 'image/jpeg', 56969, '', '2026-05-22 15:11:42'),
(498, '6a11220e42edd_1779507726.jpg', '104dd38c-81da-425a-a3f5-bfc88f58374c.jpg', 'image/jpeg', 132478, '', '2026-05-23 03:42:06'),
(499, '6a11220f30982_1779507727.jpg', 'c44898e9-3262-4569-a7c4-b5596774ec9e.jpg', 'image/jpeg', 99649, '', '2026-05-23 03:42:07'),
(500, '6a11220fe84f7_1779507727.jpg', '884b1958-d08a-47a5-9c52-c9d857877030.jpg', 'image/jpeg', 100447, '', '2026-05-23 03:42:08'),
(501, '6a112a6bb2eb0_1779509867.jpeg', 'IMG_3409.jpeg', 'image/jpeg', 46229, '', '2026-05-23 04:17:47'),
(502, '6a112a6d1e9ab_1779509869.jpeg', 'IMG_3410.jpeg', 'image/jpeg', 65882, '', '2026-05-23 04:17:49'),
(503, '6a112a6dacd0c_1779509869.jpeg', 'IMG_3411.jpeg', 'image/jpeg', 54785, '', '2026-05-23 04:17:49'),
(504, '6a11379bcee8c_1779513243.jpeg', 'IMG_3413.jpeg', 'image/jpeg', 38358, '', '2026-05-23 05:14:03'),
(505, '6a11379c635b8_1779513244.jpeg', 'IMG_3414.jpeg', 'image/jpeg', 49011, '', '2026-05-23 05:14:04'),
(506, '6a11379cec13f_1779513244.jpeg', 'IMG_3415.jpeg', 'image/jpeg', 28855, '', '2026-05-23 05:14:05'),
(507, '6a113be7469cb_1779514343.jpeg', 'IMG_3419.jpeg', 'image/jpeg', 46968, '', '2026-05-23 05:32:23'),
(508, '6a113befc8ff8_1779514351.jpeg', 'IMG_3420.jpeg', 'image/jpeg', 75511, '', '2026-05-23 05:32:31'),
(509, '6a113bf187702_1779514353.jpeg', 'IMG_3421.jpeg', 'image/jpeg', 40285, '', '2026-05-23 05:32:33'),
(510, '6a128f8dbff9b_1779601293.jpeg', 'IMG_3441.jpeg', 'image/jpeg', 56377, '', '2026-05-24 05:41:33'),
(511, '6a128f8e1bcea_1779601294.jpeg', 'IMG_3442.jpeg', 'image/jpeg', 35925, '', '2026-05-24 05:41:34'),
(512, '6a128f9ba78e8_1779601307.jpeg', 'IMG_3440.jpeg', 'image/jpeg', 50841, '', '2026-05-24 05:41:47'),
(513, '6a12f523e20a8_1779627299.jpeg', 'Image.jpeg', 'image/jpeg', 43675, '', '2026-05-24 12:54:59'),
(514, '6a12f524a2988_1779627300.jpeg', 'Image 1.jpeg', 'image/jpeg', 46993, '', '2026-05-24 12:55:00'),
(515, '6a12f52594b36_1779627301.jpeg', 'Image 2.jpeg', 'image/jpeg', 75926, '', '2026-05-24 12:55:01'),
(516, '6a12f52660170_1779627302.jpeg', 'Image 3.jpeg', 'image/jpeg', 142819, '', '2026-05-24 12:55:02'),
(517, '6a12f52976301_1779627305.jpeg', 'Image 5.jpeg', 'image/jpeg', 119811, '', '2026-05-24 12:55:05'),
(518, '6a12f529c5942_1779627305.jpeg', 'Image 6.jpeg', 'image/jpeg', 21799, '', '2026-05-24 12:55:05'),
(519, '6a12f76219931_1779627874.jpeg', 'Image 7.jpeg', 'image/jpeg', 42315, '', '2026-05-24 13:04:34'),
(520, '6a12f76284f09_1779627874.jpeg', 'Image 9.jpeg', 'image/jpeg', 40482, '', '2026-05-24 13:04:34'),
(521, '6a12f7636112f_1779627875.jpeg', 'Image 10.jpeg', 'image/jpeg', 100664, '', '2026-05-24 13:04:35'),
(522, '6a12f7639866c_1779627875.jpeg', 'Image 8.jpeg', 'image/jpeg', 40783, '', '2026-05-24 13:04:35'),
(523, '6a12f8db25451_1779628251.jpeg', 'Image 11.jpeg', 'image/jpeg', 61083, '', '2026-05-24 13:10:51'),
(524, '6a12f8dc8bc9c_1779628252.jpeg', 'Image 12.jpeg', 'image/jpeg', 65591, '', '2026-05-24 13:10:52'),
(525, '6a12ff3f92553_1779629887.jpeg', 'Image 13.jpeg', 'image/jpeg', 46745, '', '2026-05-24 13:38:07'),
(526, '6a12ff40147e1_1779629888.jpeg', 'Image 14.jpeg', 'image/jpeg', 52331, '', '2026-05-24 13:38:08'),
(527, '6a12ff40da0d0_1779629888.jpeg', 'Image 15.jpeg', 'image/jpeg', 53917, '', '2026-05-24 13:38:08'),
(528, '6a12ff41b9a28_1779629889.jpeg', 'Image 16.jpeg', 'image/jpeg', 60726, '', '2026-05-24 13:38:09'),
(529, '6a12ff423a09e_1779629890.jpeg', 'Image 17.jpeg', 'image/jpeg', 51656, '', '2026-05-24 13:38:10'),
(530, '6a13969af262c_1779668634.mp4', 'Video-768.mp4', 'video/mp4', 3855839, '', '2026-05-25 00:23:54'),
(531, '6a15925f17822_1779798623.jpeg', 'IMG_3606.jpeg', 'image/jpeg', 158880, '', '2026-05-26 12:30:23'),
(532, '6a15956d84f98_1779799405.jpeg', 'IMG_3612.jpeg', 'image/jpeg', 457023, '', '2026-05-26 12:43:25'),
(533, '6a159579b4f09_1779799417.jpeg', 'IMG_3610.jpeg', 'image/jpeg', 97410, '', '2026-05-26 12:43:37'),
(534, '6a15957b71b49_1779799419.jpeg', 'IMG_3611.jpeg', 'image/jpeg', 216676, '', '2026-05-26 12:43:39'),
(535, '6a15957c1d71b_1779799420.jpeg', 'IMG_3608.jpeg', 'image/jpeg', 79062, '', '2026-05-26 12:43:40'),
(536, '6a15957c5c78b_1779799420.jpeg', 'IMG_3609.jpeg', 'image/jpeg', 59554, '', '2026-05-26 12:43:40'),
(537, '6a15b0a3551d0_1779806371.jpeg', 'IMG_3615.jpeg', 'image/jpeg', 100270, '', '2026-05-26 14:39:31'),
(538, '6a15b0a773268_1779806375.jpeg', 'IMG_3616.jpeg', 'image/jpeg', 55666, '', '2026-05-26 14:39:35'),
(539, '6a15b596cc83f_1779807638.jpeg', 'IMG_3583.jpeg', 'image/jpeg', 359187, '', '2026-05-26 15:00:38'),
(540, '6a15b7d6758ef_1779808214.jpeg', 'IMG_3585.jpeg', 'image/jpeg', 311438, '', '2026-05-26 15:10:14'),
(541, '6a15b7fcb4f11_1779808252.jpeg', 'IMG_3618.jpeg', 'image/jpeg', 70445, '', '2026-05-26 15:10:52'),
(542, '6a15bd3e17d2a_1779809598.jpeg', 'IMG_3624.jpeg', 'image/jpeg', 330382, '', '2026-05-26 15:33:18'),
(543, '6a15bd455919e_1779809605.jpeg', 'IMG_3625.jpeg', 'image/jpeg', 233904, '', '2026-05-26 15:33:25'),
(544, '6a15bd4690b04_1779809606.jpeg', 'IMG_3626.jpeg', 'image/jpeg', 221120, '', '2026-05-26 15:33:26'),
(545, '6a15bd47a3b62_1779809607.jpeg', 'IMG_3627.jpeg', 'image/jpeg', 220112, '', '2026-05-26 15:33:27'),
(546, '6a15bd48b8bf5_1779809608.jpeg', 'IMG_3628.jpeg', 'image/jpeg', 212047, '', '2026-05-26 15:33:28'),
(547, '6a16af2cafb88_1779871532.jpeg', 'IMG_2495.jpeg', 'image/jpeg', 202266, '', '2026-05-27 08:45:32'),
(548, '6a16e773787c3_1779885939.jpeg', 'IMG_3686.jpeg', 'image/jpeg', 71248, '', '2026-05-27 12:45:39'),
(549, '6a179e3be32a3_1779932731.jpeg', 'IMG_3690.jpeg', 'image/jpeg', 69165, '', '2026-05-28 01:45:31'),
(550, '6a179e3f64cec_1779932735.jpeg', 'IMG_3691.jpeg', 'image/jpeg', 130116, '', '2026-05-28 01:45:35'),
(551, '6a179e3fafd20_1779932735.jpeg', 'IMG_3692.jpeg', 'image/jpeg', 61688, '', '2026-05-28 01:45:35'),
(552, '6a17b7f828bee_1779939320.jpeg', 'IMG_3709.jpeg', 'image/jpeg', 33936, '', '2026-05-28 03:35:20'),
(553, '6a17b7fb41b5b_1779939323.jpeg', 'IMG_3711.jpeg', 'image/jpeg', 43076, '', '2026-05-28 03:35:23'),
(554, '6a17b80c9aa9f_1779939340.jpeg', 'IMG_3711.jpeg', 'image/jpeg', 43076, '', '2026-05-28 03:35:40'),
(555, '6a17b81229c7b_1779939346.jpeg', 'IMG_3710.jpeg', 'image/jpeg', 46147, '', '2026-05-28 03:35:46'),
(556, '6a17c4f6e82e9_1779942646.jpeg', 'IMG_3726.jpeg', 'image/jpeg', 118559, '', '2026-05-28 04:30:47'),
(557, '6a17c4fa201e2_1779942650.jpeg', 'IMG_3727.jpeg', 'image/jpeg', 112904, '', '2026-05-28 04:30:50'),
(558, '6a17c7caec688_1779943370.jpeg', 'IMG_3716.jpeg', 'image/jpeg', 147114, '', '2026-05-28 04:42:51'),
(559, '6a17c834744ef_1779943476.jpeg', 'IMG_3730.jpeg', 'image/jpeg', 53096, '', '2026-05-28 04:44:36'),
(560, '6a17c83609699_1779943478.jpeg', 'IMG_3731.jpeg', 'image/jpeg', 80352, '', '2026-05-28 04:44:38'),
(561, '6a17de445bcb6_1779949124.jpg', 'image.jpg', 'image/jpeg', 159748, '', '2026-05-28 06:18:44'),
(562, '6a17de5cedfa7_1779949148.jpeg', 'IMG_3744.jpeg', 'image/jpeg', 158154, '', '2026-05-28 06:19:09'),
(563, '6a17e61ebfb3c_1779951134.jpeg', 'IMG_3752.jpeg', 'image/jpeg', 207715, '', '2026-05-28 06:52:14'),
(564, '6a1ab8e45d8e8_1780136164.jpeg', 'IMG_3699.jpeg', 'image/jpeg', 24798, '', '2026-05-30 10:16:04'),
(565, '6a1ab8e5f1556_1780136165.png', 'IMG_3697.png', 'image/png', 122938, '', '2026-05-30 10:16:06'),
(566, '6a1ab8ecc8b35_1780136172.png', 'IMG_3696.png', 'image/png', 192385, '', '2026-05-30 10:16:12'),
(567, '6a1ab8f17e22c_1780136177.png', 'IMG_3695.png', 'image/png', 191228, '', '2026-05-30 10:16:17'),
(568, '6a1ab8f521476_1780136181.jpeg', 'IMG_3694.jpeg', 'image/jpeg', 135125, '', '2026-05-30 10:16:21'),
(569, '6a1ab8f59d288_1780136181.jpeg', 'IMG_3692.jpeg', 'image/jpeg', 39747, '', '2026-05-30 10:16:21'),
(570, '6a1ab8f7d9fce_1780136183.jpeg', 'IMG_3785.jpeg', 'image/jpeg', 152591, '', '2026-05-30 10:16:23'),
(571, '6a1d900ea00d4_1780322318.jpeg', 'IMG_3863.jpeg', 'image/jpeg', 163528, '', '2026-06-01 13:58:38'),
(572, '6a1d9024c8736_1780322340.jpeg', 'IMG_3864.jpeg', 'image/jpeg', 130961, '', '2026-06-01 13:59:01'),
(573, '6a1d9422cb26e_1780323362.jpeg', 'IMG_3878.jpeg', 'image/jpeg', 68348, '', '2026-06-01 14:16:02'),
(574, '6a1d97d1b3495_1780324305.jpeg', 'IMG_3880.jpeg', 'image/jpeg', 185077, '', '2026-06-01 14:31:45'),
(575, '6a1d97d3e412f_1780324307.jpeg', 'IMG_3881.jpeg', 'image/jpeg', 119399, '', '2026-06-01 14:31:47'),
(576, '6a1d996aeb222_1780324714.jpeg', 'IMG_3883.jpeg', 'image/jpeg', 89791, '', '2026-06-01 14:38:35'),
(577, '6a1d996c8f1ab_1780324716.jpeg', 'IMG_3884.jpeg', 'image/jpeg', 104600, '', '2026-06-01 14:38:36'),
(578, '6a1d9d2daa0aa_1780325677.jpeg', 'IMG_3887.jpeg', 'image/jpeg', 80176, '', '2026-06-01 14:54:37'),
(579, '6a1d9f8d07fad_1780326285.jpeg', 'IMG_3867.jpeg', 'image/jpeg', 173312, '', '2026-06-01 15:04:45'),
(580, '6a1d9fb2eccfa_1780326322.jpeg', 'IMG_3891.jpeg', 'image/jpeg', 181397, '', '2026-06-01 15:05:23'),
(581, '6a1da343afc19_1780327235.jpeg', 'IMG_3870.jpeg', 'image/jpeg', 183522, '', '2026-06-01 15:20:35'),
(582, '6a1da35be279b_1780327259.jpeg', 'IMG_3892.jpeg', 'image/jpeg', 179422, '', '2026-06-01 15:20:59'),
(583, '6a1e5b8d38f8b_1780374413.jpeg', 'IMG_3903.jpeg', 'image/jpeg', 122754, '', '2026-06-02 04:26:53'),
(584, '6a20f676078f0_1780545142.png', 'AA2888A4-16D1-4658-BB6B-B1C9460EC3BE.png', 'image/png', 876553, '', '2026-06-04 03:52:24'),
(585, '6a20f6b767a43_1780545207.jpeg', 'IMG_7033.jpeg', 'image/jpeg', 38268, '', '2026-06-04 03:53:27'),
(586, '6a20f6b7ddf9b_1780545207.jpeg', 'IMG_7035.jpeg', 'image/jpeg', 35196, '', '2026-06-04 03:53:27'),
(587, '6a20f6b85b54e_1780545208.jpeg', 'IMG_7036.jpeg', 'image/jpeg', 30585, '', '2026-06-04 03:53:28'),
(588, '6a20f6b8a2900_1780545208.jpeg', 'IMG_7034.jpeg', 'image/jpeg', 30256, '', '2026-06-04 03:53:28'),
(589, '6a20f6c7c19c1_1780545223.png', 'IMG_7039.png', 'image/png', 247439, '', '2026-06-04 03:53:44'),
(590, '6a20f6c9518a6_1780545225.jpeg', 'IMG_7038.jpeg', 'image/jpeg', 54360, '', '2026-06-04 03:53:45'),
(591, '6a22285b7434a_1780623451.jpeg', 'motiva.jpeg', 'image/jpeg', 31650, '', '2026-06-05 01:37:31'),
(592, '6a2247f9c4dd4_1780631545.jpeg', 'jordan.jpeg', 'image/jpeg', 40542, '', '2026-06-05 03:52:25'),
(593, '6a2249c3ccab0_1780632003.jpg', 'Unknown-66.jpg', 'image/jpeg', 77288, '', '2026-06-05 04:00:03'),
(594, '6a224a0bf042f_1780632075.jpg', 'Unknown-66.jpg', 'image/jpeg', 77288, '', '2026-06-05 04:01:16'),
(595, '6a2257e4da5af_1780635620.jpeg', 'acg.jpeg', 'image/jpeg', 36241, '', '2026-06-05 05:00:20'),
(596, '6a2257ed46008_1780635629.jpeg', 'acg1.jpeg', 'image/jpeg', 48048, '', '2026-06-05 05:00:29'),
(597, '6a2257f2ce644_1780635634.jpeg', 'acg2.jpeg', 'image/jpeg', 35583, '', '2026-06-05 05:00:34'),
(598, '6a2268e9ba903_1780639977.jpeg', 'free.jpeg', 'image/jpeg', 44683, '', '2026-06-05 06:12:57'),
(599, '6a2268fdb3500_1780639997.jpeg', 'free1.jpeg', 'image/jpeg', 77010, '', '2026-06-05 06:13:17'),
(600, '6a22690967074_1780640009.jpeg', 'free2.jpeg', 'image/jpeg', 35737, '', '2026-06-05 06:13:29'),
(601, '6a22801b2b06b_1780645915.jpeg', 'force.jpeg', 'image/jpeg', 39124, '', '2026-06-05 07:51:55'),
(602, '6a2299f5bfa13_1780652533.jpeg', 'puma1.jpeg', 'image/jpeg', 48271, '', '2026-06-05 09:42:13'),
(603, '6a229a066887f_1780652550.jpeg', 'puma.jpeg', 'image/jpeg', 46940, '', '2026-06-05 09:42:30'),
(604, '6a22ab40eae32_1780656960.jpeg', 'pajama.jpeg', 'image/jpeg', 126381, '', '2026-06-05 10:56:01'),
(605, '6a22abbd4a145_1780657085.jpeg', 'pajama1.jpeg', 'image/jpeg', 108555, '', '2026-06-05 10:58:05'),
(606, '6a22becda0eb8_1780661965.jpeg', 'cuckoo.jpeg', 'image/jpeg', 48208, '', '2026-06-05 12:19:25'),
(607, '6a22bedf39cd7_1780661983.jpeg', 'cuckoo1.jpeg', 'image/jpeg', 62892, '', '2026-06-05 12:19:43'),
(608, '6a22bedfa79a4_1780661983.jpeg', 'cuckoo2.jpeg', 'image/jpeg', 45595, '', '2026-06-05 12:19:43'),
(609, '6a22bee22d311_1780661986.jpeg', 'cuckoo3.jpeg', 'image/jpeg', 58798, '', '2026-06-05 12:19:46'),
(610, '6a22bee3dd836_1780661987.jpeg', 'cuckoo4.jpeg', 'image/jpeg', 80810, '', '2026-06-05 12:19:47'),
(611, '6a25242e24c30_1780818990.jpeg', 'IMG_4141.jpeg', 'image/jpeg', 113723, '', '2026-06-07 07:56:30'),
(612, '6a2526137b10c_1780819475.jpeg', 'IMG_4143.jpeg', 'image/jpeg', 120024, '', '2026-06-07 08:04:35'),
(613, '6a264bdac5bfc_1780894682.jpeg', 'IMG_4183.jpeg', 'image/jpeg', 58450, '', '2026-06-08 04:58:02'),
(614, '6a2652406efe6_1780896320.jpeg', 'p6000.jpeg', 'image/jpeg', 53779, '', '2026-06-08 05:25:20'),
(615, '6a26524621bbc_1780896326.jpeg', 'p600.jpeg', 'image/jpeg', 85911, '', '2026-06-08 05:25:26'),
(616, '6a2655f45b804_1780897268.jpeg', 'd2.jpeg', 'image/jpeg', 48579, '', '2026-06-08 05:41:08'),
(617, '6a265600d7cd7_1780897280.jpeg', 'd4.jpeg', 'image/jpeg', 39009, '', '2026-06-08 05:41:20'),
(618, '6a26560482de4_1780897284.jpeg', 'V12-HEPA-Gold-Primary-800x1200.png.jpeg', 'image/jpeg', 30146, '', '2026-06-08 05:41:24'),
(619, '6a26560a079d4_1780897290.jpeg', 'd3.jpeg', 'image/jpeg', 55233, '', '2026-06-08 05:41:30'),
(620, '6a2679ea0f027_1780906474.jpeg', 'IMG_4218.jpeg', 'image/jpeg', 124435, '', '2026-06-08 08:14:34'),
(621, '6a2679eae832c_1780906474.jpeg', 'IMG_4219.jpeg', 'image/jpeg', 105096, '', '2026-06-08 08:14:34'),
(622, '6a2679eb9ca4a_1780906475.jpeg', 'IMG_4220.jpeg', 'image/jpeg', 71441, '', '2026-06-08 08:14:35'),
(623, '6a2679ebe73d3_1780906475.jpeg', 'IMG_4221.jpeg', 'image/jpeg', 60460, '', '2026-06-08 08:14:35'),
(624, '6a275fa19936f_1780965281.jpeg', 'IMG_4250.jpeg', 'image/jpeg', 132377, '', '2026-06-09 00:34:41'),
(625, '6a27a26502e60_1780982373.jpeg', 'IMG_4280.jpeg', 'image/jpeg', 43800, '', '2026-06-09 05:19:33'),
(626, '6a27a27b1ea40_1780982395.jpeg', 'IMG_4281.jpeg', 'image/jpeg', 230579, '', '2026-06-09 05:19:55'),
(627, '6a27a27f22eca_1780982399.jpeg', 'IMG_4283.jpeg', 'image/jpeg', 210928, '', '2026-06-09 05:19:59'),
(628, '6a27a28117d46_1780982401.jpeg', 'IMG_4285.jpeg', 'image/jpeg', 134939, '', '2026-06-09 05:20:01'),
(629, '6a2850604d5da_1781026912.jpeg', 'att.VjCvpM7TWCvcbuTWftHzrrtqzXY0lbTkwuCnZLPaBSM.jpeg', 'image/jpeg', 156291, '', '2026-06-09 17:41:52'),
(630, '6a28e0b8acc06_1781063864.jpeg', 'IMG_4370.jpeg', 'image/jpeg', 151652, '', '2026-06-10 03:57:44'),
(631, '6a28e0bc96316_1781063868.jpeg', 'IMG_4372.jpeg', 'image/jpeg', 127270, '', '2026-06-10 03:57:48'),
(632, '6a28e0ce41ceb_1781063886.png', 'IMG_4364.png', 'image/png', 391056, '', '2026-06-10 03:58:06'),
(633, '6a28e76927a11_1781065577.jpeg', 'IMG_4379.jpeg', 'image/jpeg', 150128, '', '2026-06-10 04:26:17'),
(634, '6a28e79bb3a5e_1781065627.jpeg', 'IMG_4381.jpeg', 'image/jpeg', 140435, '', '2026-06-10 04:27:07'),
(635, '6a28e8752c443_1781065845.jpeg', 'IMG_4380.jpeg', 'image/jpeg', 176172, '', '2026-06-10 04:30:45'),
(636, '6a28eb43a51e2_1781066563.jpeg', 'IMG_4380.jpeg', 'image/jpeg', 176172, '', '2026-06-10 04:42:43'),
(637, '6a28eb649f7f2_1781066596.jpeg', 'IMG_4384.jpeg', 'image/jpeg', 176110, '', '2026-06-10 04:43:16'),
(638, '6a28f457dc8bc_1781068887.jpeg', 'IMG_4385.jpeg', 'image/jpeg', 163094, '', '2026-06-10 05:21:28'),
(639, '6a28f46c2680f_1781068908.jpeg', 'IMG_4386.jpeg', 'image/jpeg', 161240, '', '2026-06-10 05:21:48'),
(640, '6a2917be56377_1781077950.jpeg', 'IMG_4402.jpeg', 'image/jpeg', 63957, '', '2026-06-10 07:52:30'),
(641, '6a2917ca0e539_1781077962.jpeg', 'IMG_4403.jpeg', 'image/jpeg', 138500, '', '2026-06-10 07:52:42'),
(642, '6a2917cc5dded_1781077964.jpeg', 'IMG_4404.jpeg', 'image/jpeg', 66343, '', '2026-06-10 07:52:44'),
(643, '6a293d16a6848_1781087510.jpeg', 'IMG_4410.jpeg', 'image/jpeg', 152676, '', '2026-06-10 10:31:50'),
(644, '6a294008eb630_1781088264.jpeg', 'IMG_4413.jpeg', 'image/jpeg', 52125, '', '2026-06-10 10:44:25'),
(645, '6a29400bc0cb0_1781088267.jpeg', 'IMG_4411.jpeg', 'image/jpeg', 70378, '', '2026-06-10 10:44:27'),
(646, '6a2be1f253d2c_1781260786.jpeg', 'IMG_4267.jpeg', 'image/jpeg', 155227, '', '2026-06-12 10:39:46'),
(647, '6a2be256857fd_1781260886.jpeg', 'IMG_4505.jpeg', 'image/jpeg', 178621, '', '2026-06-12 10:41:26'),
(648, '6a2ed1e5e8b7f_1781453285.jpeg', 'IMG_4578.jpeg', 'image/jpeg', 109208, '', '2026-06-14 16:08:05'),
(649, '6a2ed1e9d6b12_1781453289.jpeg', 'IMG_4579.jpeg', 'image/jpeg', 161274, '', '2026-06-14 16:08:09'),
(650, '6a30c2ef749aa_1781580527.jpeg', 'IMG_4678.jpeg', 'image/jpeg', 192553, '', '2026-06-16 03:28:47'),
(651, '6a30c3ee17d5e_1781580782.jpeg', 'IMG_4698.jpeg', 'image/jpeg', 182143, '', '2026-06-16 03:33:02'),
(652, '6a312991c458d_1781606801.jpeg', 'IMG_4620.jpeg', 'image/jpeg', 135271, '', '2026-06-16 10:46:42'),
(653, '6a3129eb864f1_1781606891.jpeg', 'IMG_4621.jpeg', 'image/jpeg', 136609, '', '2026-06-16 10:48:11'),
(654, '6a312a24a71fa_1781606948.jpeg', 'IMG_4725.jpeg', 'image/jpeg', 100540, '', '2026-06-16 10:49:08'),
(655, '6a312a28e4a0d_1781606952.jpeg', 'IMG_4726.jpeg', 'image/jpeg', 131474, '', '2026-06-16 10:49:12'),
(656, '6a313579c47c8_1781609849.jpeg', 'att.L28Z8IQnkgcfUM5Qw4rc1dkHh7qqevjpil0QPASxXNc.jpeg', 'image/jpeg', 34546, '', '2026-06-16 11:37:29'),
(657, '6a31357b1f0b8_1781609851.jpeg', 'att.doBfShTzcYZj2q_4torHEXLD6aEAt4UgKfekTP5FeW8.jpeg', 'image/jpeg', 33298, '', '2026-06-16 11:37:31'),
(658, '6a31357e75535_1781609854.jpeg', 'att.rVkNHYkCfhcVijdn3b2d4bfkYPQlwB0Hv_PT2zrUisY.jpeg', 'image/jpeg', 34754, '', '2026-06-16 11:37:34'),
(659, '6a31358006292_1781609856.jpeg', 'att.QstL5Md_1wEy5z0LDvdq7wzN1wugH4KlY1hSg_Rp9Ho.jpeg', 'image/jpeg', 29942, '', '2026-06-16 11:37:36'),
(660, '6a31358212632_1781609858.jpeg', 'att.rVkNHYkCfhcVijdn3b2d4bfkYPQlwB0Hv_PT2zrUisY.jpeg', 'image/jpeg', 34754, '', '2026-06-16 11:37:38'),
(661, '6a31447182849_1781613681.jpeg', 'IMG_4755.jpeg', 'image/jpeg', 38223, '', '2026-06-16 12:41:21'),
(662, '6a3144797db2a_1781613689.jpeg', 'IMG_4756.jpeg', 'image/jpeg', 32783, '', '2026-06-16 12:41:29'),
(663, '6a3144805c2cb_1781613696.jpeg', 'IMG_4757.jpeg', 'image/jpeg', 54744, '', '2026-06-16 12:41:36'),
(664, '6a314496c653e_1781613718.jpeg', 'IMG_4758.jpeg', 'image/jpeg', 143014, '', '2026-06-16 12:41:58'),
(665, '6a3144a67e16f_1781613734.jpeg', 'IMG_4759.jpeg', 'image/jpeg', 152479, '', '2026-06-16 12:42:14'),
(666, '6a3147e5b7cab_1781614565.jpeg', 'att._mW0YVjUX4qICHjnWTW-enVI-LCHQOSGPxjGOMf7zU0.jpeg', 'image/jpeg', 74224, '', '2026-06-16 12:56:05'),
(667, '6a3147e668e8f_1781614566.jpeg', 'att.30Qi93Hwsi-hyPAfS4STqokXoou5MrsDAXTd1urKBGM.jpeg', 'image/jpeg', 37157, '', '2026-06-16 12:56:06'),
(668, '6a315b2caa22a_1781619500.jpeg', 'att.gxAHtFb0FPYqlp6ku-0u6XuUfolH5SJlsGQ4movulJA.jpeg', 'image/jpeg', 27983, '', '2026-06-16 14:18:20'),
(669, '6a315b533edca_1781619539.jpeg', 'IMG_4789.jpeg', 'image/jpeg', 44481, '', '2026-06-16 14:18:59'),
(670, '6a315b61c5cb7_1781619553.jpeg', 'IMG_4791.jpeg', 'image/jpeg', 71867, '', '2026-06-16 14:19:13'),
(671, '6a315da088905_1781620128.jpeg', 'att.XpLKWP1VHB7_UW3vnykhRAwxW0OLXIXmFVA2hvUQN1g.jpeg', 'image/jpeg', 60330, '', '2026-06-16 14:28:48'),
(672, '6a315da470209_1781620132.jpeg', 'att.XpLKWP1VHB7_UW3vnykhRAwxW0OLXIXmFVA2hvUQN1g.jpeg', 'image/jpeg', 60330, '', '2026-06-16 14:28:52'),
(673, '6a315da76de64_1781620135.jpeg', 'att.ZuMr9kS-H-0nNlAeq2Fmn5EA5kxWkvYprlFKo3tYD38.jpeg', 'image/jpeg', 47940, '', '2026-06-16 14:28:55'),
(674, '6a315db258786_1781620146.jpeg', 'att.1PsufHAJuig0-B6Lb8qYK9LoaGjP1dLobfsGCzQutxU.jpeg', 'image/jpeg', 96384, '', '2026-06-16 14:29:06'),
(675, '6a316a7bbde38_1781623419.jpeg', 'att.y2ws53oyOb9HyxWgsQVO0FejaMCBAPCzV98-w8142OA.jpeg', 'image/jpeg', 43603, '', '2026-06-16 15:23:39'),
(676, '6a316a8615683_1781623430.jpeg', 'att.b8D-BfBLPloQmqz7dT6hvCHEiqXZPKEjnTj5vZiRV1Y.jpeg', 'image/jpeg', 103679, '', '2026-06-16 15:23:50'),
(677, '6a316a8939e69_1781623433.jpeg', 'att.pTvkj1iyZLgkdZ_4NQE49zsbHQKILYKV2CsIRlCAlfY.jpeg', 'image/jpeg', 105469, '', '2026-06-16 15:23:53'),
(678, '6a316c7a4b82b_1781623930.jpeg', 'att.vNj5VSFyf0Fi4CWeBam-s-4rGlQl9H4o5Zv35AHc5qE.jpeg', 'image/jpeg', 62819, '', '2026-06-16 15:32:10'),
(679, '6a316c7fe7d5b_1781623935.jpeg', 'att.uugrgmri6j9P-SBzQSEJ8BYz91-ifkKZq7LfEbF5iv4.jpeg', 'image/jpeg', 60000, '', '2026-06-16 15:32:16'),
(680, '6a316c85efac3_1781623941.jpeg', 'att.zEMPq9OXdXl33RA-lhAZDWQ_PyIHE6IKZTRt70sPtqA.jpeg', 'image/jpeg', 60829, '', '2026-06-16 15:32:22'),
(681, '6a31755d9e2e2_1781626205.jpeg', 'IMG_4827.jpeg', 'image/jpeg', 137732, '', '2026-06-16 16:10:05'),
(682, '6a317560d5e79_1781626208.jpeg', 'IMG_4828.jpeg', 'image/jpeg', 61727, '', '2026-06-16 16:10:08'),
(683, '6a317564de127_1781626212.jpeg', 'IMG_4829.jpeg', 'image/jpeg', 75163, '', '2026-06-16 16:10:12'),
(684, '6a31756984bc1_1781626217.jpeg', 'IMG_4830.jpeg', 'image/jpeg', 60900, '', '2026-06-16 16:10:17'),
(685, '6a3176155f217_1781626389.jpeg', 'IMG_4813.jpeg', 'image/jpeg', 89915, '', '2026-06-16 16:13:09'),
(686, '6a317635e2e1f_1781626421.jpeg', 'IMG_4816.jpeg', 'image/jpeg', 179882, '', '2026-06-16 16:13:41'),
(687, '6a31767bdddef_1781626491.jpeg', 'IMG_4817.jpeg', 'image/jpeg', 178462, '', '2026-06-16 16:14:51'),
(688, '6a33558c80d61_1781749132.jpeg', 'IMG_4931.jpeg', 'image/jpeg', 94218, '', '2026-06-18 02:18:52'),
(689, '6a33558e8dceb_1781749134.jpeg', 'IMG_4932.jpeg', 'image/jpeg', 105255, '', '2026-06-18 02:18:54'),
(690, '6a3355914e973_1781749137.jpeg', 'IMG_4933.jpeg', 'image/jpeg', 84729, '', '2026-06-18 02:18:57'),
(691, '6a33559276c81_1781749138.jpeg', 'IMG_4934.jpeg', 'image/jpeg', 59143, '', '2026-06-18 02:18:58'),
(692, '6a3355e9d0f4e_1781749225.jpeg', 'IMG_4936.jpeg', 'image/jpeg', 80741, '', '2026-06-18 02:20:25'),
(693, '6a336a0096f3d_1781754368.jpeg', 'IMG_4946.jpeg', 'image/jpeg', 65675, '', '2026-06-18 03:46:08'),
(694, '6a336a524a702_1781754450.jpeg', 'IMG_4948.jpeg', 'image/jpeg', 68541, '', '2026-06-18 03:47:30'),
(695, '6a336a5832d4b_1781754456.jpeg', 'IMG_4949.jpeg', 'image/jpeg', 106787, '', '2026-06-18 03:47:36'),
(696, '6a336c6c79000_1781754988.jpg', 'image.jpg', 'image/jpeg', 124086, '', '2026-06-18 03:56:28'),
(697, '6a336d520447f_1781755218.jpeg', 'IMG_7227.jpeg', 'image/jpeg', 113227, '', '2026-06-18 04:00:18'),
(698, '6a339e387de27_1781767736.jpeg', 'IMG_4974.jpeg', 'image/jpeg', 196361, '', '2026-06-18 07:28:56'),
(699, '6a339e50903bf_1781767760.jpeg', 'IMG_4988.jpeg', 'image/jpeg', 179541, '', '2026-06-18 07:29:20'),
(700, '6a33db448079e_1781783364.jpeg', 'IMG_4972.jpeg', 'image/jpeg', 244676, '', '2026-06-18 11:49:24'),
(701, '6a33db713fff3_1781783409.jpeg', 'IMG_5007.jpeg', 'image/jpeg', 203749, '', '2026-06-18 11:50:09'),
(702, '6a33e76847511_1781786472.jpeg', 'IMG_5015.jpeg', 'image/jpeg', 49897, '', '2026-06-18 12:41:12'),
(703, '6a33e769b39e7_1781786473.jpeg', 'IMG_5016.jpeg', 'image/jpeg', 59963, '', '2026-06-18 12:41:13'),
(704, '6a33e76be85e6_1781786475.jpeg', 'IMG_5017.jpeg', 'image/jpeg', 120222, '', '2026-06-18 12:41:15'),
(705, '6a33e76fce3bb_1781786479.jpeg', 'IMG_5018.jpeg', 'image/jpeg', 70934, '', '2026-06-18 12:41:19'),
(706, '6a33e773d4b3d_1781786483.jpeg', 'IMG_5019.jpeg', 'image/jpeg', 132892, '', '2026-06-18 12:41:23'),
(707, '6a33ea2ec4107_1781787182.jpeg', 'IMG_5021.jpeg', 'image/jpeg', 84357, '', '2026-06-18 12:53:02'),
(708, '6a33ea37e496b_1781787191.jpeg', 'IMG_5022.jpeg', 'image/jpeg', 130804, '', '2026-06-18 12:53:11'),
(709, '6a33ea43c4e2a_1781787203.jpeg', 'IMG_5023.jpeg', 'image/jpeg', 168439, '', '2026-06-18 12:53:23'),
(710, '6a33ea51b922e_1781787217.jpeg', 'IMG_5024.jpeg', 'image/jpeg', 254187, '', '2026-06-18 12:53:37'),
(711, '6a3483bbc166c_1781826491.jpeg', 'IMG_5038.jpeg', 'image/jpeg', 74307, '', '2026-06-18 23:48:11'),
(712, '6a3483bd1f3de_1781826493.jpeg', 'IMG_5039.jpeg', 'image/jpeg', 78102, '', '2026-06-18 23:48:13'),
(713, '6a34a8b12451a_1781835953.jpeg', 'IMG_5045.jpeg', 'image/jpeg', 144173, '', '2026-06-19 02:25:53'),
(714, '6a34a8b4a7d95_1781835956.jpeg', 'IMG_5046.jpeg', 'image/jpeg', 85777, '', '2026-06-19 02:25:56'),
(715, '6a34a902356e6_1781836034.jpeg', 'IMG_4653.jpeg', 'image/jpeg', 175494, '', '2026-06-19 02:27:14'),
(716, '6a34a92949a91_1781836073.jpeg', 'IMG_5047.jpeg', 'image/jpeg', 163496, '', '2026-06-19 02:27:53'),
(717, '6a34aa22b96e2_1781836322.jpeg', 'IMG_5049.jpeg', 'image/jpeg', 128272, '', '2026-06-19 02:32:02'),
(718, '6a34aa270be4c_1781836327.jpeg', 'IMG_5050.jpeg', 'image/jpeg', 90466, '', '2026-06-19 02:32:07'),
(719, '6a34aa304c102_1781836336.jpeg', 'IMG_5051.jpeg', 'image/jpeg', 97095, '', '2026-06-19 02:32:16'),
(720, '6a34aa36732cf_1781836342.jpeg', 'IMG_5052.jpeg', 'image/jpeg', 119292, '', '2026-06-19 02:32:22'),
(721, '6a352d0cb95b8_1781869836.jpeg', 'IMG_5078.jpeg', 'image/jpeg', 103572, '', '2026-06-19 11:50:36'),
(722, '6a352d1316b1d_1781869843.jpeg', 'IMG_5079.jpeg', 'image/jpeg', 97938, '', '2026-06-19 11:50:43'),
(723, '6a352d14e9f3a_1781869844.jpeg', 'IMG_5077.jpeg', 'image/jpeg', 54651, '', '2026-06-19 11:50:45'),
(724, '6a353b8e48044_1781873550.jpeg', 'IMG_7227.jpeg', 'image/jpeg', 113227, '', '2026-06-19 12:52:30'),
(725, '6a353c8c55fca_1781873804.jpeg', 'IMG_7272.jpeg', 'image/jpeg', 116556, '', '2026-06-19 12:56:44'),
(726, '6a353d3564b0b_1781873973.jpeg', 'Image 1.jpeg', 'image/jpeg', 42727, '', '2026-06-19 12:59:33'),
(727, '6a353d3771dbe_1781873975.jpeg', 'Image 2.jpeg', 'image/jpeg', 49241, '', '2026-06-19 12:59:35'),
(728, '6a353d3a052b9_1781873978.jpeg', 'Image 3.jpeg', 'image/jpeg', 81419, '', '2026-06-19 12:59:38'),
(729, '6a353d3c43c51_1781873980.jpeg', 'Image 4.jpeg', 'image/jpeg', 38081, '', '2026-06-19 12:59:40'),
(730, '6a353d6bda919_1781874027.jpeg', 'Image 5.jpeg', 'image/jpeg', 124144, '', '2026-06-19 13:00:27'),
(731, '6a353d7873664_1781874040.jpeg', 'Image 6.jpeg', 'image/jpeg', 183705, '', '2026-06-19 13:00:40'),
(732, '6a354050b7ff8_1781874768.jpeg', 'Image 7.jpeg', 'image/jpeg', 47568, '', '2026-06-19 13:12:48'),
(733, '6a3543dd04398_1781875677.jpeg', 'Image 8.jpeg', 'image/jpeg', 49181, '', '2026-06-19 13:27:57'),
(734, '6a3543ee55b52_1781875694.jpeg', 'Image 9.jpeg', 'image/jpeg', 48617, '', '2026-06-19 13:28:14'),
(735, '6a3543f10478d_1781875697.jpeg', 'Image 10.jpeg', 'image/jpeg', 67847, '', '2026-06-19 13:28:17'),
(736, '6a3543f23e40c_1781875698.jpeg', 'Image 11.jpeg', 'image/jpeg', 44945, '', '2026-06-19 13:28:18'),
(737, '6a3543f73a412_1781875703.jpeg', 'Image 12.jpeg', 'image/jpeg', 43015, '', '2026-06-19 13:28:23'),
(738, '6a3543fc294b0_1781875708.jpeg', 'Image 13.jpeg', 'image/jpeg', 143049, '', '2026-06-19 13:28:28'),
(739, '6a354400a2c9b_1781875712.jpeg', 'Image 14.jpeg', 'image/jpeg', 152313, '', '2026-06-19 13:28:32'),
(740, '6a35440323965_1781875715.jpeg', 'Image 15.jpeg', 'image/jpeg', 52684, '', '2026-06-19 13:28:35'),
(741, '6a35463820bbe_1781876280.jpeg', 'IMG_7273.jpeg', 'image/jpeg', 129884, '', '2026-06-19 13:38:00'),
(742, '6a354b91de873_1781877649.jpeg', 'Image 16.jpeg', 'image/jpeg', 53697, '', '2026-06-19 14:00:49'),
(743, '6a354b97cb18c_1781877655.jpeg', 'Image 17.jpeg', 'image/jpeg', 58032, '', '2026-06-19 14:00:55'),
(744, '6a354b9aab86e_1781877658.jpeg', 'Image 18.jpeg', 'image/jpeg', 57441, '', '2026-06-19 14:00:58'),
(745, '6a354b9d72729_1781877661.jpeg', 'Image 19.jpeg', 'image/jpeg', 81446, '', '2026-06-19 14:01:01'),
(746, '6a354b9e2e44f_1781877662.jpeg', 'Image 20.jpeg', 'image/jpeg', 47951, '', '2026-06-19 14:01:02'),
(747, '6a354ba4d7ae7_1781877668.jpeg', 'Image 21.jpeg', 'image/jpeg', 184684, '', '2026-06-19 14:01:08'),
(748, '6a354bae16bdb_1781877678.jpeg', 'Image 22.jpeg', 'image/jpeg', 174151, '', '2026-06-19 14:01:18'),
(749, '6a354bb1d31ac_1781877681.jpeg', 'Image 23.jpeg', 'image/jpeg', 87705, '', '2026-06-19 14:01:21'),
(750, '6a3553357a661_1781879605.jpeg', 'Image 24.jpeg', 'image/jpeg', 35464, '', '2026-06-19 14:33:25'),
(751, '6a3553365529f_1781879606.jpeg', 'Image 25.jpeg', 'image/jpeg', 34607, '', '2026-06-19 14:33:26'),
(752, '6a3553377f178_1781879607.jpeg', 'Image 26.jpeg', 'image/jpeg', 40516, '', '2026-06-19 14:33:27'),
(753, '6a3553391e99f_1781879609.jpeg', 'Image 27.jpeg', 'image/jpeg', 34741, '', '2026-06-19 14:33:29'),
(754, '6a35533b8eb7d_1781879611.jpeg', 'Image 28.jpeg', 'image/jpeg', 38188, '', '2026-06-19 14:33:31'),
(755, '6a3554058ab74_1781879813.jpeg', 'IMG_7240.jpeg', 'image/jpeg', 128995, '', '2026-06-19 14:36:53'),
(756, '6a35542bcf704_1781879851.jpg', 'Unknown-67.jpg', 'image/jpeg', 126553, '', '2026-06-19 14:37:31'),
(757, '6a3556eb550fd_1781880555.jpg', 'Unknown-68.jpg', 'image/jpeg', 125761, '', '2026-06-19 14:49:15'),
(758, '6a3556fbc3b20_1781880571.jpeg', 'Image 29.jpeg', 'image/jpeg', 83897, '', '2026-06-19 14:49:31'),
(759, '6a3556fcd7b07_1781880572.jpeg', 'Image 30.jpeg', 'image/jpeg', 87851, '', '2026-06-19 14:49:32'),
(760, '6a355700e5b61_1781880576.jpeg', 'Image 31.jpeg', 'image/jpeg', 99418, '', '2026-06-19 14:49:36'),
(761, '6a35570310652_1781880579.jpeg', 'Image 32.jpeg', 'image/jpeg', 174908, '', '2026-06-19 14:49:39'),
(762, '6a355703d8e71_1781880579.jpeg', 'Image 33.jpeg', 'image/jpeg', 77146, '', '2026-06-19 14:49:39'),
(763, '6a35570688293_1781880582.jpeg', 'Image 34.jpeg', 'image/jpeg', 63401, '', '2026-06-19 14:49:42'),
(764, '6a35570da146c_1781880589.jpeg', 'Image 35.jpeg', 'image/jpeg', 236859, '', '2026-06-19 14:49:49'),
(765, '6a3557138a05f_1781880595.jpeg', 'Image 36.jpeg', 'image/jpeg', 337960, '', '2026-06-19 14:49:55'),
(766, '6a35571b39bfc_1781880603.jpeg', 'Image 37.jpeg', 'image/jpeg', 338292, '', '2026-06-19 14:50:03'),
(767, '6a3559fa96742_1781881338.jpeg', 'Image 38.jpeg', 'image/jpeg', 48540, '', '2026-06-19 15:02:18'),
(768, '6a3559fc62ca8_1781881340.jpeg', 'Image 39.jpeg', 'image/jpeg', 49077, '', '2026-06-19 15:02:20'),
(769, '6a3559ff6c9dc_1781881343.jpeg', 'Image 40.jpeg', 'image/jpeg', 80544, '', '2026-06-19 15:02:23'),
(770, '6a355a00915a9_1781881344.jpeg', 'Image 41.jpeg', 'image/jpeg', 50608, '', '2026-06-19 15:02:24'),
(771, '6a355a020edc4_1781881346.jpeg', 'Image 42.jpeg', 'image/jpeg', 46236, '', '2026-06-19 15:02:26'),
(772, '6a355a096326a_1781881353.jpeg', 'Image 43.jpeg', 'image/jpeg', 205085, '', '2026-06-19 15:02:33'),
(773, '6a355a14e6ecc_1781881364.jpeg', 'Image 44.jpeg', 'image/jpeg', 172742, '', '2026-06-19 15:02:44'),
(774, '6a355a2097481_1781881376.jpeg', 'Image 45.jpeg', 'image/jpeg', 212312, '', '2026-06-19 15:02:56'),
(775, '6a355a97cf1ae_1781881495.jpg', 'Unknown-69.jpg', 'image/jpeg', 124607, '', '2026-06-19 15:04:55'),
(776, '6a355cb56467b_1781882037.jpeg', 'Image 46.jpeg', 'image/jpeg', 34778, '', '2026-06-19 15:13:57'),
(777, '6a355cb5d1957_1781882037.jpeg', 'Image 47.jpeg', 'image/jpeg', 35375, '', '2026-06-19 15:13:57'),
(778, '6a355cb654644_1781882038.jpeg', 'Image 48.jpeg', 'image/jpeg', 35609, '', '2026-06-19 15:13:58'),
(779, '6a355cb70e48f_1781882039.jpeg', 'Image 49.jpeg', 'image/jpeg', 33372, '', '2026-06-19 15:13:59'),
(780, '6a355cb837ed6_1781882040.jpeg', 'Image 50.jpeg', 'image/jpeg', 101698, '', '2026-06-19 15:14:00'),
(781, '6a355cbb401f8_1781882043.jpeg', 'Image 51.jpeg', 'image/jpeg', 92611, '', '2026-06-19 15:14:03'),
(782, '6a355cbbe239a_1781882043.jpeg', 'Image 52.jpeg', 'image/jpeg', 35206, '', '2026-06-19 15:14:03'),
(783, '6a355cde02dc8_1781882078.jpg', 'Unknown-70.jpg', 'image/jpeg', 124808, '', '2026-06-19 15:14:38'),
(784, '6a355f6ce855a_1781882732.jpg', 'Unknown-71.jpg', 'image/jpeg', 98585, '', '2026-06-19 15:25:32'),
(785, '6a355f77901e8_1781882743.jpeg', 'Image 53.jpeg', 'image/jpeg', 41898, '', '2026-06-19 15:25:43'),
(786, '6a355f780da6b_1781882744.jpeg', 'Image 54.jpeg', 'image/jpeg', 42855, '', '2026-06-19 15:25:44'),
(787, '6a355f79a3555_1781882745.jpeg', 'Image 55.jpeg', 'image/jpeg', 60559, '', '2026-06-19 15:25:45'),
(788, '6a355f7ab79a8_1781882746.jpeg', 'Image 56.jpeg', 'image/jpeg', 88998, '', '2026-06-19 15:25:46'),
(789, '6a355f7cee0a7_1781882748.jpeg', 'Image 57.jpeg', 'image/jpeg', 117568, '', '2026-06-19 15:25:48'),
(790, '6a3560989fc41_1781883032.jpg', 'Unknown-71.jpg', 'image/jpeg', 98585, '', '2026-06-19 15:30:32'),
(791, '6a3560a9905bd_1781883049.jpeg', 'Image 58.jpeg', 'image/jpeg', 31861, '', '2026-06-19 15:30:49'),
(792, '6a3560aa61326_1781883050.jpeg', 'Image 59.jpeg', 'image/jpeg', 35068, '', '2026-06-19 15:30:50'),
(793, '6a3560ab4d0f7_1781883051.jpeg', 'Image 60.jpeg', 'image/jpeg', 38194, '', '2026-06-19 15:30:51'),
(794, '6a3560ad0e2f3_1781883053.jpeg', 'Image 61.jpeg', 'image/jpeg', 44390, '', '2026-06-19 15:30:53'),
(795, '6a3560ae380d2_1781883054.jpeg', 'Image 62.jpeg', 'image/jpeg', 50689, '', '2026-06-19 15:30:54'),
(796, '6a3560af80162_1781883055.jpeg', 'Image 63.jpeg', 'image/jpeg', 37741, '', '2026-06-19 15:30:55'),
(797, '6a3560b5372e9_1781883061.jpeg', 'Image 64.jpeg', 'image/jpeg', 139823, '', '2026-06-19 15:31:01'),
(798, '6a3560bd21599_1781883069.jpeg', 'Image 65.jpeg', 'image/jpeg', 129977, '', '2026-06-19 15:31:09'),
(799, '6a3560d88f693_1781883096.jpg', 'Unknown-72.jpg', 'image/jpeg', 112984, '', '2026-06-19 15:31:36'),
(800, '6a3c79660ca95_1782348134.jpg', '667878829_1564594172337819_4964707579985046393_n.jpg', 'image/jpeg', 76400, '', '2026-06-25 00:42:14'),
(801, '6a3c796634a09_1782348134.jpg', '667878829_1564594172337819_4964707579985046393_n.jpg', 'image/jpeg', 76400, '', '2026-06-25 00:42:14'),
(802, '6a3c93e9017bb_1782354921.jpg', 'Screenshot 2026-06-25 103514.jpg', 'image/jpeg', 78543, '', '2026-06-25 02:35:21'),
(803, '6a3c9723124d2_1782355747.jpg', 'slider-5.jpg', 'image/jpeg', 8249, '', '2026-06-25 02:49:07'),
(804, '6a3c97afd8c66_1782355887.jpg', 'slider-38.jpg', 'image/jpeg', 7997, '', '2026-06-25 02:51:28'),
(805, '6a3ca4cfcf875_1782359247.jpg', '671301016_1564593469004556_7569029310194144655_n.jpg', 'image/jpeg', 32227, '', '2026-06-25 03:47:27'),
(806, '6a3ca4e7d3d03_1782359271.jpg', 'slider-45.jpg', 'image/jpeg', 7493, '', '2026-06-25 03:47:52'),
(807, '6a3ca4f4c2e31_1782359284.jpg', 'slider-5.jpg', 'image/jpeg', 8249, '', '2026-06-25 03:48:05'),
(808, '6a3ca5471e786_1782359367.jpg', 'Screenshot 2026-06-25 114920.jpg', 'image/jpeg', 71528, '', '2026-06-25 03:49:27'),
(809, '6a5ef67914ae0_1784608377.jpg', '3jzzoc_MPA07013_x974.jpg', 'image/jpeg', 178387, '', '2026-07-21 04:32:57');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `name`, `applied_at`) VALUES
(1, '001_full_schema_sync', '2026-04-28 00:29:16'),
(2, '002_otp_extra_fields', '2026-04-28 00:29:16'),
(3, '003_delivery_module', '2026-04-28 00:29:16'),
(4, '004_admin_remember_token', '2026-04-28 00:29:16'),
(5, '005_security_hardening', '2026-04-28 00:29:16'),
(6, '006_vat_support', '2026-04-28 00:29:16'),
(7, '007_store_only_seed', '2026-04-28 00:29:16'),
(8, '008_product_show_in_store', '2026-04-28 00:29:16'),
(9, '009_payment_reconciliation', '2026-04-28 00:29:16'),
(10, '010_product_entry_permissions', '2026-04-28 00:29:16'),
(11, '011_order_items_line_total', '2026-04-28 00:29:16'),
(12, '012_products_order_status', '2026-04-28 00:29:16'),
(13, '013_product_batch_link', '2026-04-28 00:29:16'),
(14, '014_preorder_assign_latest_batch', '2026-04-28 00:29:16'),
(15, '015_split_payment', '2026-04-28 00:29:16'),
(16, '016_backfill_payment_columns', '2026-04-28 00:29:16'),
(17, '017_product_hide_cargo_fee', '2026-04-28 00:29:16'),
(18, '018_order_cargo_fee_paid', '2026-04-28 00:29:16'),
(19, '019_order_items_cargo_fee_paid', '2026-04-28 00:29:16'),
(20, '020_order_items_hide_cargo_fee', '2026-04-28 00:29:16'),
(21, '021_preorder_visible_cargo_fee_paid', '2026-04-28 00:29:16'),
(22, '022_resync_cargo_fee_paid', '2026-04-28 00:29:16'),
(23, '023_payment_transfer_nomin', '2026-04-28 00:29:16'),
(24, '024_cargo_batch_sms_sent_at', '2026-04-28 00:29:16'),
(25, '025_add_districts_aimags', '2026-04-28 00:29:16'),
(26, '026_aimag_soums', '2026-04-28 00:29:17'),
(27, '027_delivery_batches', '2026-04-28 00:29:17'),
(28, '028_driver_access_token', '2026-04-28 00:29:17'),
(29, '029_ready_for_delivery', '2026-04-28 00:29:17'),
(30, '030_bank_payment_settings', '2026-04-28 00:29:17'),
(31, '031_bank_transfer_reconciliation', '2026-04-28 00:29:18'),
(32, '032_product_variants', '2026-04-28 00:29:18'),
(33, '033_social_login', '2026-04-28 00:29:18'),
(34, '034_item_delivery_status_and_order_note', '2026-04-28 00:29:19'),
(35, '035_register_without_otp_setting', '2026-05-01 01:55:23'),
(36, '036_faqs_table', '2026-05-01 01:55:23'),
(37, '037_home_category_settings', '2026-05-03 23:26:55'),
(38, '038_email_authentication', '2026-05-06 00:27:42'),
(39, '039_order_number_prefix_setting', '2026-05-06 00:27:42'),
(40, '040_reconciliation_time_window', '2026-05-06 00:27:42'),
(41, '041_otp_phone_nullable', '2026-05-22 14:28:43'),
(42, '042_bonum_payment_settings', '2026-05-22 14:28:43'),
(43, '043_bonum_checksum_and_invoice_col', '2026-05-22 14:28:44'),
(44, '044_delivery_fee_enabled_setting', '2026-05-22 14:28:44'),
(45, '045_storepay_payment_gateway', '2026-05-22 14:28:44'),
(46, '046_nullable_preorder_stock', '2026-05-22 14:28:44'),
(47, '047_product_price_tiers', '2026-05-22 14:28:44'),
(48, '048_bonum_reconciliation', '2026-05-25 00:18:52'),
(49, '049_orders_confirmed_at', '2026-06-10 03:46:07'),
(50, '050_stock_movements_ledger', '2026-06-10 03:46:07'),
(51, '051_inventory_arrivals', '2026-06-21 03:25:55'),
(52, '052_sms_queue', '2026-06-21 03:25:55'),
(53, '053_sms_templates', '2026-06-21 03:25:55'),
(54, '054_cargo_payments', '2026-06-21 03:25:55'),
(55, '055_bank_tx_cargo_payment', '2026-06-25 02:03:18'),
(56, '056_testimonials', '2026-06-25 02:03:18'),
(57, '057_blog_posts', '2026-06-25 02:03:18'),
(58, '058_blog_posts_body', '2026-06-25 02:35:53'),
(59, '059_sliders', '2026-06-25 02:46:49'),
(60, '060_gender_activity', '2026-07-19 04:23:28'),
(61, '061_contact_messages', '2026-07-21 15:05:34');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int DEFAULT NULL,
  `order_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_type` enum('pos','online') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online',
  `fulfillment` enum('pickup','delivery') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'delivery',
  `ready_for_delivery` tinyint(1) NOT NULL DEFAULT '0',
  `status` enum('pending','confirmed','cargo_shipping','cargo_arrived','ready_pickup','delivering','partially_delivered','delivered','picked_up','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `customer_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `district_id` int DEFAULT NULL,
  `khoroo_id` int DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `detail_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `subtotal` decimal(12,2) NOT NULL DEFAULT '0.00',
  `delivery_fee` decimal(10,2) DEFAULT '0.00',
  `cargo_fee` decimal(10,2) DEFAULT '0.00',
  `cargo_fee_paid` tinyint(1) NOT NULL DEFAULT '0',
  `total` decimal(12,2) NOT NULL DEFAULT '0.00',
  `vat_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `vat_included` tinyint(1) NOT NULL DEFAULT '0',
  `cargo_batch_id` int DEFAULT NULL,
  `payment_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'cash',
  `payment_cash` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_card` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_transfer` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_transfer_nomin` decimal(12,2) NOT NULL DEFAULT '0.00',
  `payment_status` enum('pending','paid','refunded') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `qpay_invoice_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bonum_invoice_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `storepay_invoice_id` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `order_note` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `district_id` (`district_id`),
  KEY `khoroo_id` (`khoroo_id`),
  KEY `cargo_batch_id` (`cargo_batch_id`),
  KEY `fk_orders_customer` (`customer_id`),
  KEY `idx_ready_delivery` (`ready_for_delivery`),
  KEY `idx_orders_confirmed_at` (`confirmed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `order_number`, `order_type`, `fulfillment`, `ready_for_delivery`, `status`, `customer_name`, `customer_phone`, `district_id`, `khoroo_id`, `address`, `detail_address`, `subtotal`, `delivery_fee`, `cargo_fee`, `cargo_fee_paid`, `total`, `vat_amount`, `vat_included`, `cargo_batch_id`, `payment_method`, `payment_cash`, `payment_card`, `payment_transfer`, `payment_transfer_nomin`, `payment_status`, `qpay_invoice_id`, `bonum_invoice_id`, `storepay_invoice_id`, `notes`, `order_note`, `confirmed_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'RW64039781', 'online', 'pickup', 0, 'confirmed', 'Battseren', '88024889', NULL, NULL, NULL, NULL, 629250.00, 0.00, 0.00, 1, 629250.00, 0.00, 0, NULL, 'transfer', 0.00, 0.00, 0.00, 0.00, 'paid', NULL, NULL, NULL, '', NULL, '2026-07-21 07:26:53', '2026-07-21 07:15:30', '2026-07-21 07:26:55'),
(2, 1, 'RW04718868', 'online', 'delivery', 0, 'confirmed', 'Battseren', '88024889', 6, 130, '123', '456', 50000.00, 7000.00, 0.00, 1, 57000.00, 0.00, 0, NULL, 'bonum', 0.00, 0.00, 0.00, 0.00, 'paid', NULL, '63d0873e2bc0d7a9b399334ceeb956fb8c220531d93b014787de007f184567c7', NULL, '', NULL, '2026-07-21 07:28:30', '2026-07-21 07:27:29', '2026-07-21 07:28:32'),
(4, 1, 'RW51864998', 'online', 'delivery', 0, 'confirmed', 'Battseren', '88024889', 6, 130, '123', '456', 175000.00, 7000.00, 0.00, 1, 182000.00, 0.00, 0, NULL, 'qpay', 0.00, 0.00, 0.00, 0.00, 'paid', '427f1c0a-ed19-4037-ad13-7f36284857be', NULL, NULL, 'qqqq', NULL, '2026-07-21 08:08:10', '2026-07-21 08:07:19', '2026-07-21 08:08:20'),
(5, 1, 'RW57260292', 'online', 'delivery', 0, 'cancelled', 'Battseren', '88024889', 6, 130, '123', '456', 199000.00, 7000.00, 0.00, 0, 206000.00, 0.00, 0, NULL, 'qpay', 0.00, 0.00, 0.00, 0.00, 'pending', 'ef533f30-4a10-4e35-82cf-ab30e50f3c43', NULL, NULL, 'sss', NULL, NULL, '2026-07-21 08:08:37', '2026-07-21 08:44:20'),
(7, 1, 'RW10958294', 'online', 'delivery', 0, 'cancelled', 'Battseren', '88024889', 6, 130, '123', '456', 199000.00, 7000.00, 0.00, 0, 206000.00, 0.00, 0, NULL, 'qpay', 0.00, 0.00, 0.00, 0.00, 'pending', 'ae462ef3-9b3f-4ed5-b978-3fb23efb38ec', NULL, NULL, '', NULL, NULL, '2026-07-21 08:44:20', '2026-07-21 10:19:53'),
(8, 9, 'RW30067753', 'online', 'pickup', 0, 'confirmed', 'Батцэрэн', '88024889', NULL, NULL, NULL, NULL, 199000.00, 0.00, 0.00, 1, 199000.00, 0.00, 0, NULL, 'transfer', 0.00, 0.00, 0.00, 0.00, 'paid', NULL, NULL, NULL, '', NULL, '2026-07-21 10:41:59', '2026-07-21 10:28:10', '2026-07-21 10:42:01'),
(9, 11, 'RW90784082', 'online', 'pickup', 0, 'confirmed', 'God', '88024889', NULL, NULL, NULL, NULL, 249000.00, 0.00, 0.00, 1, 249000.00, 0.00, 0, NULL, 'transfer', 0.00, 0.00, 0.00, 0.00, 'paid', NULL, NULL, NULL, '', NULL, '2026-07-21 10:41:54', '2026-07-21 10:41:46', '2026-07-21 10:42:06');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` int NOT NULL,
  `product_id` int NOT NULL,
  `variant_id` int DEFAULT NULL,
  `variant_label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_price` decimal(12,2) NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `line_total` decimal(12,2) DEFAULT NULL,
  `weight_kg` decimal(8,3) DEFAULT NULL,
  `cargo_fee` decimal(10,2) DEFAULT '0.00',
  `cargo_batch_id` int DEFAULT NULL,
  `cargo_status` enum('pending','shipping','arrived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arrival_item_id` int DEFAULT NULL,
  `cargo_fee_paid` tinyint(1) NOT NULL DEFAULT '0',
  `hide_cargo_fee` tinyint(1) NOT NULL DEFAULT '0',
  `delivery_status` enum('pending','delivered') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  KEY `idx_oi_cargo_batch` (`cargo_batch_id`),
  KEY `idx_arrival_item` (`arrival_item_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `variant_id`, `variant_label`, `product_name`, `product_price`, `quantity`, `line_total`, `weight_kg`, `cargo_fee`, `cargo_batch_id`, `cargo_status`, `arrival_item_id`, `cargo_fee_paid`, `hide_cargo_fee`, `delivery_status`) VALUES
(1, 1, 193, 564, '40', 'NIKE KOBE 3 PROTRO EP', 428250.00, 1, NULL, 2.000, 0.00, NULL, NULL, NULL, 0, 1, 'pending'),
(2, 1, 180, 508, 'L', 'Nike biker', 67000.00, 3, NULL, NULL, 0.00, NULL, NULL, NULL, 0, 1, 'pending'),
(3, 2, 170, 483, 'Хар', 'Badblood biker S size', 50000.00, 1, NULL, NULL, 0.00, NULL, NULL, NULL, 0, 1, 'pending'),
(5, 4, 172, NULL, NULL, 'Melamate gummy', 35000.00, 5, NULL, NULL, 0.00, NULL, NULL, NULL, 0, 1, 'pending'),
(6, 5, 171, 485, 'L', 'Badblood', 199000.00, 1, NULL, NULL, 0.00, NULL, NULL, NULL, 0, 1, 'pending'),
(8, 7, 171, 485, 'L', 'Badblood', 199000.00, 1, NULL, NULL, 0.00, NULL, NULL, NULL, 0, 1, 'pending'),
(9, 8, 173, 487, 'M', 'Badblood', 199000.00, 1, NULL, NULL, 0.00, NULL, NULL, NULL, 0, 1, 'pending'),
(10, 9, 175, 503, '43', 'Nike p6000', 249000.00, 1, NULL, 1.000, 0.00, NULL, NULL, NULL, 0, 1, 'pending');

-- --------------------------------------------------------

--
-- Table structure for table `otp_codes`
--

DROP TABLE IF EXISTS `otp_codes`;
CREATE TABLE IF NOT EXISTS `otp_codes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identifier` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` enum('phone','email') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'phone',
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT '0',
  `verify_attempts` int NOT NULL DEFAULT '0',
  `otp_token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp_token_expires` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_phone_code` (`phone`,`code`),
  KEY `idx_identifier_type` (`identifier`,`type`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_mn` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` int NOT NULL,
  `gender` enum('men','women','unisex','kids') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unisex',
  `shop_id` int NOT NULL,
  `type` enum('ready','preorder') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'ready',
  `price` decimal(12,2) NOT NULL,
  `original_price` decimal(12,2) DEFAULT NULL,
  `weight_kg` decimal(8,3) DEFAULT NULL,
  `barcode` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `main_image_id` int DEFAULT NULL,
  `image_ids` json DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description_mn` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `stock` int DEFAULT NULL,
  `has_variants` tinyint(1) NOT NULL DEFAULT '0',
  `preorder_date` date DEFAULT NULL,
  `order_status` enum('open','closed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'open',
  `cargo_batch_id` int DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT '0.0',
  `reviews` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1',
  `show_in_store` tinyint(1) NOT NULL DEFAULT '1',
  `created_by_customer_id` int DEFAULT NULL,
  `hide_cargo_fee` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `category_id` (`category_id`),
  KEY `shop_id` (`shop_id`),
  KEY `idx_products_barcode` (`barcode`),
  KEY `fk_products_main_image` (`main_image_id`),
  KEY `idx_products_cargo_batch` (`cargo_batch_id`),
  KEY `idx_products_gender` (`gender`)
) ENGINE=InnoDB AUTO_INCREMENT=197 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `name_mn`, `slug`, `category_id`, `gender`, `shop_id`, `type`, `price`, `original_price`, `weight_kg`, `barcode`, `image`, `main_image_id`, `image_ids`, `description`, `description_mn`, `stock`, `has_variants`, `preorder_date`, `order_status`, `cargo_batch_id`, `rating`, `reviews`, `is_active`, `show_in_store`, `created_by_customer_id`, `hide_cargo_fee`, `created_at`, `updated_at`) VALUES
(144, 'Puma Rice 21', 'Puma Rice 21', 'puma-rice-21', 16, 'unisex', 8, 'ready', 94000.00, 159000.00, NULL, NULL, 'uploads/media/6a2299f5bfa13_1780652533.jpeg', 602, '[603]', '', '', 52, 1, NULL, 'open', NULL, 5.0, 99, 1, 1, NULL, 0, '2026-06-05 09:59:23', '2026-07-19 11:45:25'),
(149, 'nike p6000', 'Nike p6000', 'nike-p6000', 16, 'unisex', 8, 'ready', 280000.00, 350000.00, NULL, NULL, 'uploads/media/6a2652406efe6_1780896320.jpeg', 614, '[615]', '', '', 6, 1, NULL, 'open', NULL, 5.0, 0, 1, 1, NULL, 0, '2026-06-08 05:26:16', '2026-07-19 09:01:45'),
(165, 'Zara gutal', 'Zara гутал', 'zara-gutal', 16, 'unisex', 8, 'ready', 79000.00, 199000.00, NULL, NULL, 'uploads/media/6a313579c47c8_1781609849.jpeg', 656, '[659, 660, 657]', '', '', 10, 1, NULL, 'open', NULL, 0.0, 0, 1, 1, NULL, 0, '2026-06-16 11:39:03', '2026-07-19 09:00:59'),
(166, 'Badblood bucket leather', 'Badblood', 'badblood-bucket-leather', 18, 'unisex', 8, 'ready', 50000.00, NULL, NULL, NULL, 'uploads/media/6a31447182849_1781613681.jpeg', 661, '[662, 663, 664]', '', '', 4, 0, NULL, 'open', NULL, 0.0, 0, 1, 1, NULL, 1, '2026-06-16 12:42:31', '2026-07-21 14:40:19'),
(167, 'Badblood cup', 'Badblood малгай', 'badblood-cup', 18, 'unisex', 8, 'ready', 60000.00, NULL, NULL, NULL, 'uploads/media/6a3147e5b7cab_1781614565.jpeg', 666, '[667]', '', '', 8, 0, NULL, 'open', NULL, 0.0, 0, 1, 1, NULL, 1, '2026-06-16 13:00:50', '2026-07-19 09:00:37'),
(168, 'Badblood', 'Badblood', 'badblood', 18, 'unisex', 8, 'ready', 89000.00, 150000.00, NULL, NULL, 'uploads/media/6a315b2caa22a_1781619500.jpeg', 668, '[670]', '', '', 7, 1, NULL, 'open', NULL, 0.0, 0, 1, 1, NULL, 1, '2026-06-16 14:19:35', '2026-07-19 09:00:29'),
(169, 'Badblood jeans', 'Badblood', 'badblood-jeans', 18, 'unisex', 8, 'ready', 80000.00, NULL, NULL, NULL, 'uploads/media/6a315da088905_1781620128.jpeg', 671, '[672, 673, 674]', '', '', 6, 1, NULL, 'open', NULL, 0.0, 0, 1, 1, NULL, 1, '2026-06-16 14:31:13', '2026-07-19 09:00:23'),
(170, 'Badblood biker S size', 'Badblood', 'badblood-biker-s-size', 18, 'unisex', 8, 'ready', 50000.00, NULL, NULL, NULL, 'uploads/media/6a316a7bbde38_1781623419.jpeg', 675, '[676, 677]', '', '', 6, 1, NULL, 'open', NULL, 0.0, 0, 1, 1, NULL, 1, '2026-06-16 15:24:17', '2026-07-21 07:27:29'),
(171, 'Badblood', 'Badblood', 'badblood-1784451612', 18, 'unisex', 8, 'ready', 199000.00, NULL, NULL, NULL, 'uploads/media/6a316c7a4b82b_1781623930.jpeg', 678, '[679, 680]', '', '', 6, 1, NULL, 'open', NULL, 0.0, 0, 1, 1, NULL, 1, '2026-06-16 15:33:36', '2026-07-21 10:19:53'),
(172, 'Melamate gummy', 'Melamate gummy', 'melamate-gummy', 19, 'unisex', 8, 'ready', 35000.00, NULL, NULL, NULL, 'uploads/media/6a31755d9e2e2_1781626205.jpeg', 681, '[682, 683, 684]', '✨ Нойргүйдэлтэй тэмцэх хамгийн амттай арга 💗Guzeelzgene online shop 🍓\r\n\r\nMelaMate Gummy 💤🌃🌠 \r\nХайрцагтаа 20н ширхэг 20н өдөрийн ХЭРЭГЛЭЭ\r\nУнтахын өмнө 1 ширхэг л хангалттай!\r\n\r\n🌙 1mg мелатонин\r\n🌿 Ургамлын гаралтай\r\n💤 Нойр хурдан хүргэнэ\r\n💗 Амттай gummy хэлбэр\r\n\r\nОрой бүр утас ширтээд унтаж чадахгүй байна уу?\r\nЭнэ жижигхэн жэлли таны нойрыг зөөлөн дэмжинэ ✨', '✨ Нойргүйдэлтэй тэмцэх хамгийн амттай арга 💗Guzeelzgene online shop 🍓\r\n\r\nMelaMate Gummy 💤🌃🌠 \r\nХайрцагтаа 20н ширхэг 20н өдөрийн ХЭРЭГЛЭЭ\r\nУнтахын өмнө 1 ширхэг л хангалттай!\r\n\r\n🌙 1mg мелатонин\r\n🌿 Ургамлын гаралтай\r\n💤 Нойр хурдан хүргэнэ\r\n💗 Амттай gummy хэлбэр\r\n\r\nОрой бүр утас ширтээд унтаж чадахгүй байна уу?\r\nЭнэ жижигхэн жэлли таны нойрыг зөөлөн дэмжинэ ✨', 5, 0, NULL, 'open', NULL, 0.0, 0, 1, 1, NULL, 1, '2026-06-16 16:12:26', '2026-07-21 08:07:19'),
(173, 'Badblood', 'Badblood', 'badblood-1784451434', 18, 'women', 9, 'ready', 199000.00, NULL, NULL, NULL, 'uploads/media/6a3176155f217_1781626389.jpeg', 685, '[686]', '', '', 6, 1, NULL, 'open', NULL, 0.0, 0, 1, 1, NULL, 1, '2026-06-16 16:15:07', '2026-07-21 10:28:10'),
(175, 'Nike p6000', 'Nike P6000', 'nike-p6000-1784451382', 16, 'unisex', 10, 'ready', 249000.00, 347500.00, 1.000, NULL, 'uploads/media/6a336a0096f3d_1781754368.jpeg', 693, '[694, 695]', '', '', 29, 1, NULL, 'open', NULL, 5.0, 100, 1, 1, NULL, 1, '2026-06-18 03:50:52', '2026-07-21 10:41:46'),
(180, 'Nike biker', 'Nike biker', 'nike-biker', 4, 'men', 8, 'ready', 67000.00, 212500.00, NULL, NULL, 'uploads/media/6a3483bbc166c_1781826491.jpeg', 711, '[712]', '', '', 122, 1, NULL, 'open', 7, 0.0, 0, 1, 1, NULL, 1, '2026-06-18 23:49:54', '2026-07-21 07:15:30'),
(193, 'NIKE KOBE 3 PROTRO EP', 'NIKE KOBE 3 PROTRO EP', 'nike-kobe-3-protro-ep', 16, 'men', 10, 'ready', 428250.00, 611250.00, 2.000, NULL, 'uploads/media/6a355cb56467b_1781882037.jpeg', 776, '[783, 777, 778, 779, 780, 781, 782]', '', 'Kobe 3 Protro EP нь Коби Брайнтын домогт загварыг орчин үеийн технологитой хослуулан шинэчилсэн сагсан бөмбөгийн гутал юм.\r\nХөнгөн жинтэй бүтэц, өндөр мэдрэмжтэй зөөлөвч нь хурдан хөдөлгөөн, огцом чиглэл өөрчлөх үед илүү сайн хариу үйлдэл үзүүлнэ. EP хувилбарын бат бөх ул нь гадаа талбайд тоглоход тохиромжтой бөгөөд найдвартай барьцалдалт өгдөг.\r\nОнцлог: Хөнгөн жин • Хурдан хариу үйлдэл • Маш сайн барьцалдалт • Бат бөх EP ул • Коби Брайнтын сонгодог загвар. 🏀👟🐍', 18, 1, NULL, 'open', NULL, 5.0, 68, 1, 1, NULL, 1, '2026-06-19 15:19:21', '2026-07-21 07:15:30'),
(194, 'NIKE BOOK 1 MP EP', 'BOOK 1 MP EP', 'nike-book-1-mp-ep', 16, 'unisex', 10, 'ready', 305750.00, 436800.00, 2.000, NULL, 'uploads/media/6a355f77901e8_1781882743.jpeg', 785, '[784, 786, 787, 788, 789]', '', 'Nike Book 1 MP EP нь NBA-ийн од тоглогч Девин Бүүкерийн нэрийн загвар бөгөөд сонгодог загвар, орчин үеийн тоглолтын гүйцэтгэлийг хослуулсан сагсан бөмбөгийн гутал юм.\r\nХөнгөн бүтэц, Zoom Air зөөлөвч нь хурдан хөдөлгөөн хийхэд дэмжлэг үзүүлж, тоглолтын турш тав тухтай мэдрэмж өгнө. EP хувилбар нь илүү бат бөх ултай тул гадаа болон заалны талбайд аль алинд нь тохиромжтой.\r\nОнцлог: Хөнгөн жин • Zoom Air зөөлөвч • Найдвартай барьцалдалт • Бат бөх EP ул • Девин Бүүкерийн нэрийн загвар. 🏀👟\r\nТохиромжтой: Хамгаалагч болон довтлогч байрлалын тоглогчид, хурдан хөдөлгөөн их хийдэг сагсан бөмбөгчдөд.', 25, 0, NULL, 'open', NULL, 4.6, 34, 1, 1, NULL, 1, '2026-06-19 15:27:22', '2026-07-21 06:35:11'),
(195, 'Nike A\'ONE EP', 'Nike A\'ONE EP', 'nike-a-one-ep', 16, 'unisex', 10, 'ready', 253250.00, 361800.00, 2.000, NULL, 'uploads/media/6a3560a9905bd_1781883049.jpeg', 791, '[799, 792, 793, 794, 795, 796, 797, 798]', '', 'Nike A\'One EP нь WNBA-ийн супер од A\'ja Wilson-ийн нэрийн анхны загварын сагсан бөмбөгийн гутал юм.\r\nХөнгөн жинтэй бүтэц, өндөр мэдрэмжтэй зөөлөвч нь хурдан хөдөлгөөн, үсрэлт болон газардах үед тав тухтай мэдрэмж төрүүлнэ. EP хувилбар нь илүү бат бөх ултай тул гадаа болон заалны талбайд тоглоход тохиромжтой.\r\nОнцлог: Хөнгөн жин • Зөөлөн ул • Найдвартай барьцалдалт • Бат бөх EP ул • A\'ja Wilson-ийн нэрийн анхны загвар. 🏀👟\r\nТохиромжтой: Хурд, авхаалж самбаа шаардсан бүх байрлалын тоглогчдод.', 27, 1, NULL, 'open', NULL, 4.9, 56, 1, 1, NULL, 1, '2026-06-19 15:34:56', '2026-07-19 09:45:20'),
(196, 'Energy Gel', 'Energy Gel', 'energy-gel', 4, 'unisex', 8, 'ready', 35000.00, 45000.00, NULL, NULL, 'uploads/media/6a1e5b8d38f8b_1780374413.jpeg', 583, NULL, '', '', 100, 0, NULL, 'open', NULL, 5.0, 12, 1, 1, NULL, 0, '2026-07-21 13:08:52', '2026-07-21 13:09:21');

-- --------------------------------------------------------

--
-- Table structure for table `product_activity_types`
--

DROP TABLE IF EXISTS `product_activity_types`;
CREATE TABLE IF NOT EXISTS `product_activity_types` (
  `product_id` int NOT NULL,
  `activity_type_id` int NOT NULL,
  PRIMARY KEY (`product_id`,`activity_type_id`),
  KEY `activity_type_id` (`activity_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_activity_types`
--

INSERT INTO `product_activity_types` (`product_id`, `activity_type_id`) VALUES
(195, 1),
(195, 2),
(173, 3),
(193, 4),
(194, 4),
(173, 5),
(193, 5),
(194, 5),
(194, 6);

-- --------------------------------------------------------

--
-- Table structure for table `product_colors`
--

DROP TABLE IF EXISTS `product_colors`;
CREATE TABLE IF NOT EXISTS `product_colors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_mn` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `hex_code` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_colors`
--

INSERT INTO `product_colors` (`id`, `name`, `name_mn`, `hex_code`, `sort_order`, `created_at`) VALUES
(1, 'Black', 'Хар', '#000000', 1, '2026-04-28 00:29:18'),
(2, 'White', 'Цагаан', '#FFFFFF', 2, '2026-04-28 00:29:18'),
(3, 'Red', 'Улаан', '#EF4444', 3, '2026-04-28 00:29:18'),
(4, 'Blue', 'Цэнхэр', '#3B82F6', 4, '2026-04-28 00:29:18'),
(5, 'Green', 'Ногоон', '#22C55E', 5, '2026-04-28 00:29:18'),
(6, 'Yellow', 'Шар', '#EAB308', 6, '2026-04-28 00:29:18'),
(7, 'Pink', 'Ягаан', '#EC4899', 7, '2026-04-28 00:29:18'),
(8, 'Purple', 'Нил ягаан', '#8B5CF6', 8, '2026-04-28 00:29:18'),
(9, 'Orange', 'Улбар шар', '#F97316', 9, '2026-04-28 00:29:18'),
(10, 'Brown', 'Бор', '#92400E', 10, '2026-04-28 00:29:18'),
(11, 'Gray', 'Саарал', '#6B7280', 11, '2026-04-28 00:29:18'),
(12, 'Beige', 'Цайвар шар', '#D2B48C', 12, '2026-04-28 00:29:18'),
(13, 'Navy', 'Хар хөх', '#1E3A5F', 13, '2026-04-28 00:29:18'),
(14, 'Jasper plum', '', '#a56fa0', 14, '2026-05-01 03:16:10'),
(15, 'Amber silk', '', '#f77a26', 15, '2026-05-01 03:16:33'),
(16, 'хар цагаан', '', '#fafafa', 16, '2026-05-01 07:26:52');

-- --------------------------------------------------------

--
-- Table structure for table `product_color_images`
--

DROP TABLE IF EXISTS `product_color_images`;
CREATE TABLE IF NOT EXISTS `product_color_images` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `color_id` int NOT NULL,
  `media_id` int NOT NULL,
  `sort_order` int DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_pci_product_color` (`product_id`,`color_id`),
  KEY `color_id` (`color_id`),
  KEY `media_id` (`media_id`)
) ENGINE=InnoDB AUTO_INCREMENT=382 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_color_images`
--

INSERT INTO `product_color_images` (`id`, `product_id`, `color_id`, `media_id`, `sort_order`) VALUES
(368, 170, 1, 677, 0),
(369, 170, 4, 676, 0),
(370, 168, 1, 670, 0),
(371, 168, 10, 669, 0),
(380, 144, 1, 602, 0),
(381, 144, 2, 603, 0);

-- --------------------------------------------------------

--
-- Table structure for table `product_entry_permissions`
--

DROP TABLE IF EXISTS `product_entry_permissions`;
CREATE TABLE IF NOT EXISTS `product_entry_permissions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_id` int NOT NULL,
  `granted_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_customer` (`customer_id`),
  KEY `granted_by` (`granted_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_price_tiers`
--

DROP TABLE IF EXISTS `product_price_tiers`;
CREATE TABLE IF NOT EXISTS `product_price_tiers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `min_qty` int NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_product_min_qty` (`product_id`,`min_qty`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_price_tiers`
--

INSERT INTO `product_price_tiers` (`id`, `product_id`, `min_qty`, `unit_price`, `created_at`) VALUES
(5, 196, 5, 32000.00, '2026-07-21 13:09:21'),
(6, 196, 10, 29000.00, '2026-07-21 13:09:21');

-- --------------------------------------------------------

--
-- Table structure for table `product_sizes`
--

DROP TABLE IF EXISTS `product_sizes`;
CREATE TABLE IF NOT EXISTS `product_sizes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_sizes`
--

INSERT INTO `product_sizes` (`id`, `name`, `sort_order`, `created_at`) VALUES
(2, 'S', 2, '2026-04-28 00:29:18'),
(3, 'M', 3, '2026-04-28 00:29:18'),
(4, 'L', 4, '2026-04-28 00:29:18'),
(5, 'XL', 5, '2026-04-28 00:29:18'),
(6, '2XL', 6, '2026-04-28 00:29:18'),
(7, '3XL', 7, '2026-04-28 00:29:18'),
(8, '34', 10, '2026-04-28 00:29:18'),
(9, '35', 11, '2026-04-28 00:29:18'),
(10, '36', 12, '2026-04-28 00:29:18'),
(11, '37', 13, '2026-04-28 00:29:18'),
(12, '38', 14, '2026-04-28 00:29:18'),
(13, '39', 15, '2026-04-28 00:29:18'),
(14, '40', 16, '2026-04-28 00:29:18'),
(15, '41', 17, '2026-04-28 00:29:18'),
(16, '42', 18, '2026-04-28 00:29:18'),
(17, '43', 19, '2026-04-28 00:29:18'),
(18, '44', 20, '2026-04-28 00:29:18'),
(19, '45', 21, '2026-04-28 00:29:18'),
(20, '225', 22, '2026-05-01 05:03:05'),
(21, '230', 23, '2026-05-01 05:03:15'),
(22, '235', 24, '2026-05-01 05:07:28'),
(23, '240', 25, '2026-05-01 05:07:38'),
(24, '245', 26, '2026-05-01 05:07:47'),
(25, '250', 27, '2026-05-01 05:07:54'),
(26, '255', 28, '2026-05-01 05:08:02'),
(27, '265', 29, '2026-05-01 05:30:12'),
(28, '270', 30, '2026-05-01 05:30:21'),
(30, '275', 32, '2026-05-01 06:37:28'),
(31, '280', 33, '2026-05-01 06:37:32'),
(32, '285', 34, '2026-05-01 06:37:37'),
(33, '290', 35, '2026-05-01 06:57:19'),
(37, '230', 39, '2026-05-01 07:00:21'),
(38, '240', 40, '2026-05-01 07:00:24'),
(39, '250', 41, '2026-05-01 07:00:28'),
(40, '260', 42, '2026-05-01 07:00:32'),
(41, '270', 43, '2026-05-01 07:00:36'),
(42, '280', 44, '2026-05-01 07:00:39'),
(43, '220', 45, '2026-05-01 07:27:32'),
(44, '250', 46, '2026-05-01 07:28:48'),
(45, '260', 47, '2026-05-01 07:29:54'),
(51, '1TB', 53, '2026-05-19 14:11:26'),
(52, '2TB', 54, '2026-05-19 14:11:59'),
(54, '250', 56, '2026-05-21 11:48:30'),
(55, '235', 57, '2026-05-22 14:50:10'),
(56, '240', 58, '2026-05-22 14:50:45'),
(58, '40', 60, '2026-06-19 13:03:23'),
(60, '41', 62, '2026-06-19 13:03:33'),
(62, '42', 64, '2026-06-19 13:04:12'),
(63, '43', 65, '2026-06-19 13:04:18'),
(64, '44', 66, '2026-06-19 13:04:22'),
(65, '44.5', 67, '2026-06-19 13:04:29');

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

DROP TABLE IF EXISTS `product_variants`;
CREATE TABLE IF NOT EXISTS `product_variants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `color_id` int DEFAULT NULL,
  `size_id` int DEFAULT NULL,
  `sku` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `price_override` decimal(12,2) DEFAULT NULL,
  `stock` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_color_size` (`product_id`,`color_id`,`size_id`),
  KEY `idx_variant_product` (`product_id`),
  KEY `color_id` (`color_id`),
  KEY `size_id` (`size_id`)
) ENGINE=InnoDB AUTO_INCREMENT=580 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`id`, `product_id`, `color_id`, `size_id`, `sku`, `price_override`, `stock`, `is_active`, `created_at`, `updated_at`) VALUES
(462, 144, 1, 11, NULL, NULL, 13, 1, '2026-06-05 09:59:23', '2026-06-05 22:03:28'),
(463, 144, 1, 22, NULL, NULL, 33, 1, '2026-06-05 09:59:23', '2026-06-05 09:59:23'),
(464, 144, 1, 23, NULL, NULL, 3, 1, '2026-06-05 09:59:23', '2026-06-12 22:17:46'),
(465, 144, 1, 24, NULL, NULL, 1, 1, '2026-06-05 09:59:23', '2026-06-17 10:20:43'),
(466, 144, 2, 11, NULL, NULL, 1, 1, '2026-06-05 09:59:23', '2026-06-05 09:59:23'),
(467, 144, 2, 22, NULL, NULL, 1, 1, '2026-06-05 09:59:23', '2026-06-05 09:59:23'),
(471, 149, NULL, 21, NULL, NULL, 6, 1, '2026-06-08 05:26:16', '2026-07-19 03:13:07'),
(477, 165, NULL, 10, NULL, NULL, 3, 1, '2026-06-16 11:39:03', '2026-06-16 11:39:03'),
(478, 165, NULL, 11, NULL, NULL, 1, 1, '2026-06-16 11:39:03', '2026-06-17 12:55:40'),
(479, 165, NULL, 12, NULL, NULL, 6, 1, '2026-06-16 11:39:03', '2026-06-16 11:39:03'),
(480, 168, 1, NULL, NULL, NULL, 3, 1, '2026-06-16 14:20:17', '2026-06-16 14:20:17'),
(481, 168, 10, NULL, NULL, NULL, 4, 1, '2026-06-16 14:20:17', '2026-06-16 14:20:17'),
(482, 169, NULL, 3, NULL, NULL, 6, 1, '2026-06-16 14:31:13', '2026-06-19 05:46:06'),
(483, 170, 1, NULL, NULL, NULL, 1, 1, '2026-06-16 15:25:09', '2026-07-21 07:27:29'),
(484, 170, 4, NULL, NULL, NULL, 5, 1, '2026-06-16 15:25:09', '2026-06-20 02:50:35'),
(485, 171, NULL, 4, NULL, NULL, 4, 1, '2026-06-16 15:33:36', '2026-07-21 10:19:53'),
(486, 171, NULL, 5, NULL, NULL, 2, 1, '2026-06-16 15:33:36', '2026-06-16 15:33:36'),
(487, 173, NULL, 3, NULL, NULL, 1, 1, '2026-06-16 16:15:07', '2026-07-21 10:28:10'),
(488, 173, NULL, 4, NULL, NULL, 4, 1, '2026-06-16 16:15:07', '2026-06-16 16:15:07'),
(489, 173, NULL, 5, NULL, NULL, 1, 1, '2026-06-16 16:15:07', '2026-06-21 03:25:46'),
(500, 175, NULL, 14, NULL, NULL, 8, 1, '2026-06-18 03:50:52', '2026-07-19 08:56:22'),
(501, 175, NULL, 15, NULL, NULL, 5, 1, '2026-06-18 03:50:52', '2026-07-19 08:56:22'),
(502, 175, NULL, 16, NULL, NULL, 5, 1, '2026-06-18 03:50:52', '2026-07-19 08:56:22'),
(503, 175, NULL, 17, NULL, NULL, 2, 1, '2026-06-18 03:50:52', '2026-07-21 10:41:46'),
(504, 175, NULL, 32, NULL, NULL, 2, 1, '2026-06-18 03:50:52', '2026-07-19 08:56:22'),
(505, 175, NULL, 33, NULL, NULL, 7, 1, '2026-06-18 03:50:52', '2026-07-19 08:56:22'),
(506, 180, NULL, 2, NULL, NULL, 60, 1, '2026-06-18 23:49:54', '2026-07-19 08:58:15'),
(507, 180, NULL, 3, NULL, NULL, 20, 1, '2026-06-18 23:49:54', '2026-07-19 08:58:15'),
(508, 180, NULL, 4, NULL, NULL, 27, 1, '2026-06-18 23:49:54', '2026-07-21 07:15:30'),
(509, 180, NULL, 5, NULL, NULL, 15, 1, '2026-06-18 23:49:54', '2026-07-19 08:58:15'),
(564, 193, NULL, 58, NULL, NULL, 9, 1, '2026-06-19 15:19:21', '2026-07-21 07:15:30'),
(566, 193, NULL, 60, NULL, NULL, 9, 1, '2026-06-19 15:19:21', '2026-07-19 08:50:48'),
(567, 195, NULL, 10, NULL, NULL, 5, 1, '2026-06-19 15:34:56', '2026-07-19 08:49:30'),
(572, 195, NULL, 58, NULL, NULL, 4, 1, '2026-06-19 15:34:56', '2026-07-19 08:49:30'),
(574, 195, NULL, 60, NULL, NULL, 5, 1, '2026-06-19 15:34:56', '2026-07-19 08:49:30'),
(576, 195, NULL, 62, NULL, NULL, 4, 1, '2026-06-19 15:34:56', '2026-07-19 08:49:30'),
(577, 195, NULL, 63, NULL, NULL, 4, 1, '2026-06-19 15:34:56', '2026-07-19 08:49:30'),
(578, 195, NULL, 64, NULL, NULL, 2, 1, '2026-06-19 15:34:56', '2026-07-19 08:49:30'),
(579, 195, NULL, 65, NULL, NULL, 3, 1, '2026-06-19 15:34:56', '2026-07-19 08:49:30');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE IF NOT EXISTS `settings` (
  `setting_key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `setting_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('text','number','boolean') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'text',
  `is_public` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `label`, `type`, `is_public`) VALUES
('address', 'Цэцэрлэгт хүрээлэнгийн хойно, Олонлог сургуулийн баруун талд 361р байр.', 'Store Address', 'text', 1),
('bank_account_name', 'Runners World LLC', 'Дансны нэр', 'text', 1),
('bank_account_number', 'MN480005005005005005', 'Дансны дугаар', 'text', 1),
('bank_name', 'ХААН БАНК', 'Банкны нэр', 'text', 1),
('bonum_checksum_key', '36d74597464c615d3271fc7a59da3345eecf9eb333a973bcfadecfdf72271891', 'Bonum Checksum Key', 'text', 0),
('bonum_merchant_id', 'l_khureltsetseg@yahoo.com', 'Bonum Merchant ID', 'text', 0),
('bonum_secret_key', '1fc53f9389f489ff6e04617bd6338a710e1e7c579cb572aec421f560f363119c0e0039e4b765e53c5339c1e6c7727985fae74f95efc1ed6eb84e1505560f6d8491010062c5ef9ee46cd20867e08b762f', 'Bonum Secret Key', 'text', 0),
('bonum_terminal_id', '17172409', 'Bonum Terminal ID', 'text', 0),
('business_hours', 'Өдөр бүр 09:00-20:00', 'Ажиллах цаг', 'text', 1),
('cargo_rate_per_kg', '5000', 'Cargo Rate per KG', 'number', 1),
('currency', '₮', 'Currency Symbol', 'text', 1),
('delivery_fee', '7000', 'Delivery Fee', 'number', 1),
('delivery_fee_enabled', '1', 'Хүргэлтийн төлбөрийг тооцох', 'boolean', 1),
('email', 'info@runnersworld.mn', 'Contact Email', 'text', 1),
('email_template_body_register', 'Таны баталгаажуулах код: <strong>{otp}</strong><br><br>Энэхүү код 10 минутын дараа дуусна.', 'Имэйл баталгаажуулах бие', 'text', 0),
('email_template_body_reset', 'Таны нууц үг сэргээх код: <strong>{otp}</strong><br><br>Энэхүү код 10 минутын дараа дуусна.', 'Нууц үг сэргээх бие', 'text', 0),
('email_template_subject_register', 'Runners World имэйл баталгаажуулах код', 'Имэйл баталгаажуулах сэдэв', 'text', 0),
('email_template_subject_reset', 'Runners World нууц үг сэргээх код', 'Нууц үг сэргээх сэдэв', 'text', 0),
('facebook_app_id', '', 'Facebook App ID', 'text', 1),
('facebook_url', 'https://www.facebook.com/profile.php?id=100063617541830', 'Facebook хаяг (URL)', 'text', 1),
('feature1_desc', 'Дэлхийн шилдэг брэндүүд', 'Feature 1 Description', 'text', 1),
('feature1_title', 'Чанартай бараа', 'Feature 1 Title', 'text', 1),
('feature2_desc', 'Хялбар захиалж Шуурхай хүргэнэ', 'Feature 2 Description', 'text', 1),
('feature2_title', 'Шуурхай хүргэлт', 'Feature 2 Title', 'text', 1),
('feature3_desc', '', 'Feature 3 Description', 'text', 1),
('feature3_title', 'Хямд үнэ', 'Feature 3 Title', 'text', 1),
('free_delivery_threshold', '500000', 'Free Delivery Threshold', 'number', 1),
('google_client_id', '', 'Google Client ID', 'text', 1),
('hero_btn1_text', 'Бүх бараа', 'Hero Button 1 Text', 'text', 1),
('hero_btn2_text', 'Урьдчилсан захиалга', 'Hero Button 2 Text', 'text', 1),
('hero_description', 'Таны гарт ШУУРХАЙ хүргэнэ.', 'Hero Description', 'text', 1),
('hero_subtitle', 'Хамгийн хямд үнээр', 'Hero Subtitle', 'text', 1),
('hero_title', 'Дэлхийн шилдэг брэндүүд', 'Hero Title', 'text', 1),
('home_categories_enabled', '1', 'Ангиллын хэсгийг харуулах', 'boolean', 1),
('home_category_discount_enabled', '1', 'Хямдрал картыг харуулах', 'boolean', 1),
('home_category_discount_image', '69f992be84729_1777963710.png', 'Хямдрал картны зураг', 'text', 1),
('home_category_new_enabled', '1', 'Шинэ бараа картыг харуулах', 'boolean', 1),
('home_category_new_image', '69f993c73a70f_1777963975.jpg', 'Шинэ бараа картны зураг', 'text', 1),
('home_category_preorder_enabled', '1', 'Урьдчилсан захиалга картыг харуулах', 'boolean', 1),
('home_category_preorder_image', '69f993c73a549_1777963975.jpg', 'Урьдчилсан захиалга картны зураг', 'text', 1),
('home_category_ready_enabled', '1', 'Бэлэн бараа картыг харуулах', 'boolean', 1),
('home_category_ready_image', '69f993c73a2cb_1777963975.webp', 'Бэлэн бараа картны зураг', 'text', 1),
('instagram_url', 'https://www.facebook.com/profile.php?id=100063617541830', 'Instagram хаяг (URL)', 'text', 1),
('login_email_enabled', '1', 'Имэйлээр нэвтрэх', 'boolean', 1),
('login_facebook_enabled', '0', 'Facebook нэвтрэх', 'boolean', 1),
('login_google_enabled', '0', 'Google нэвтрэх', 'boolean', 1),
('login_phone_otp_enabled', '1', 'Утас + OTP нэвтрэх', 'boolean', 1),
('login_phone_password_enabled', '1', 'Утас + Нууц үг нэвтрэх', 'boolean', 1),
('login_register_without_otp_enabled', '0', 'SMS-гүй бүртгэл (Утас + Нууц үг)', 'boolean', 1),
('map_embed_url', '', 'Google Maps Embed URL', 'text', 1),
('messagepro_api_key', 'b464b0889f84fe3d0ed72cb1d416227f', 'MessagePro API Key', 'text', 0),
('messagepro_from_number', '72228880', 'MessagePro From Number', 'text', 0),
('order_number_prefix', 'RW', 'Захиалгын дугаарын угтвар', 'text', 1),
('payment_bonum_enabled', '1', 'Bonum төлбөр идэвхтэй', 'boolean', 1),
('payment_qpay_enabled', '1', 'QPay төлбөр идэвхтэй', 'boolean', 1),
('payment_storepay_enabled', '0', 'StorePay төлбөр идэвхтэй', 'boolean', 1),
('payment_transfer_enabled', '1', 'Шилжүүлэг төлбөр идэвхтэй', 'boolean', 1),
('phone', '99904944', 'Contact Phone', 'text', 1),
('qpay_invoice_code', 'NOOMKO_SHOP_INVOICE', 'QPay Invoice Code', 'text', 0),
('qpay_password', 'LFuX4rgW', 'QPay Password', 'text', 0),
('qpay_username', 'NOOMKO_SHOP', 'QPay Username', 'text', 0),
('reconciliation_time_window_minutes', '10', 'Тохирол: цагийн цонх (минут)', 'number', 0),
('register_email_enabled', '1', 'Имэйлээр бүртгүүлэх', 'boolean', 1),
('shops_description', '', 'Shops Section Description', 'text', 1),
('shops_title', 'Дэлхийн шилдэг брэндүүдийг', 'Shops Section Title', 'text', 1),
('site_favicon', '6a3c796634a09_1782348134.jpg', 'Site Favicon', 'text', 1),
('site_logo', '6a3c79660ca95_1782348134.jpg', 'Site Logo', 'text', 1),
('site_name', 'RUNNER\'S WORLD', 'Site Name', 'text', 1),
('site_name_mn', 'RUNNER\'S WORLD', 'Site Name (Mongolian)', 'text', 1),
('site_primary_color', '#91c9fd', 'Primary Color', 'text', 1),
('site_slogan', 'Гүйлтийн дэлгүүр', 'Site Slogan', 'text', 1),
('site_url', 'https://runnersworld.mn', 'Сайтын URL', 'text', 0),
('smtp_encryption', 'tls', 'SMTP Encryption (tls/ssl)', 'text', 0),
('smtp_from_email', 'kmallmnshop@gmail.com', 'SMTP From Email', 'text', 0),
('smtp_from_name', 'Kmall.MN', 'SMTP From Name', 'text', 0),
('smtp_host', 'smtp.gmail.com', 'SMTP Host', 'text', 0),
('smtp_password', 'fpknemuinwmznjvk', 'SMTP Password', 'text', 0),
('smtp_port', '587', 'SMTP Port', 'number', 0),
('smtp_username', 'kmallmnshop@gmail.com', 'SMTP Username', 'text', 0),
('storepay_app_password', '', 'StorePay App Password', 'text', 0),
('storepay_app_username', '', 'StorePay App Username', 'text', 0),
('storepay_password', '', 'StorePay System Password', 'text', 0),
('storepay_store_id', '', 'StorePay Store ID', 'text', 0),
('storepay_username', '', 'StorePay System Username', 'text', 0),
('tiktok_url', '', 'TikTok хаяг (URL)', 'text', 1),
('top_bar_text', '', 'Top Bar Text', 'text', 1),
('top_bar_text_short', 'Тавтай үйлчлүүлээрй', 'Top Bar Text (Short)', 'text', 1);

-- --------------------------------------------------------

--
-- Table structure for table `shops`
--

DROP TABLE IF EXISTS `shops`;
CREATE TABLE IF NOT EXISTS `shops` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name_mn` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description_mn` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `color` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#3B82F6',
  `logo` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shops`
--

INSERT INTO `shops` (`id`, `slug`, `name`, `name_mn`, `description`, `description_mn`, `color`, `logo`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(8, 'outlet', 'Outlet', 'Outlet', 'dscsdffdghj', 'Дэлхийн Брэнд дэлгүүрүүд.   \r\n\r\nБид Солонгосын албан ёсны OUTLET дэлгүүрүүдээс бараагаа шууд татан авдаг.\r\nИймээс зуучлалын нэмэлт зардалгүй, зах зээлийн үнээс хямд санал болгодог.\r\n\r\nМанай бүх бүтээгдэхүүн:\r\n\r\n* 100% оригинал\r\n* Албан ёсны дэлгүүрээс татан авсан\r\n* Чанарын баталгаатай', '#669c35', 'uploads/shops/69f9b97dc43e5_1777973629.jpg', 1, 100, '2026-05-03 16:20:05', '2026-06-24 22:31:24'),
(9, 'hoka', 'Hoka', 'Hoka', 'Fly Human Fly', 'Fly Human Fly', '#e32400', '', 1, 10, '2026-05-06 15:13:48', '2026-06-24 22:31:55'),
(10, 'nike', 'Nike', 'Nike', '', '', '#96d35f', 'uploads/shops/6a0302288f2d9_1778582056.png', 1, 20, '2026-05-12 10:32:21', '2026-06-24 22:32:03');

-- --------------------------------------------------------

--
-- Table structure for table `shop_categories`
--

DROP TABLE IF EXISTS `shop_categories`;
CREATE TABLE IF NOT EXISTS `shop_categories` (
  `shop_id` int NOT NULL,
  `category_id` int NOT NULL,
  PRIMARY KEY (`shop_id`,`category_id`),
  KEY `category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shop_categories`
--

INSERT INTO `shop_categories` (`shop_id`, `category_id`) VALUES
(8, 4),
(9, 4),
(10, 4),
(8, 16),
(9, 16),
(10, 16),
(8, 18),
(9, 18),
(10, 18),
(8, 19),
(9, 19),
(10, 19);

-- --------------------------------------------------------

--
-- Table structure for table `sliders`
--

DROP TABLE IF EXISTS `sliders`;
CREATE TABLE IF NOT EXISTS `sliders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `title_mn` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtitle_mn` varchar(400) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `btn_text` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `btn_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `text_dark` tinyint(1) NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sliders`
--

INSERT INTO `sliders` (`id`, `title_mn`, `subtitle_mn`, `btn_text`, `btn_url`, `image`, `text_dark`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'Дэлхийн брэнд', 'бүтээгдэхүүнүүдийг таны сонголтонд', 'Дэлгүүр хэсэх', 'shop.php', '6a3ca4f4c2e31_1782359284.jpg', 1, 1, 10, '2026-06-25 02:49:20'),
(2, 'Hoka', 'Fly human Fly', 'Брэнд үзэх', 'shop.php?shop=hoka', '6a3ca5471e786_1782359367.jpg', 1, 1, 20, '2026-06-25 02:51:32');

-- --------------------------------------------------------

--
-- Table structure for table `sms_queue`
--

DROP TABLE IF EXISTS `sms_queue`;
CREATE TABLE IF NOT EXISTS `sms_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'cargo_arrived, batch_notify, etc.',
  `order_id` int DEFAULT NULL,
  `status` enum('pending','sent','failed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_text` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`status`,`created_at`),
  KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_templates`
--

DROP TABLE IF EXISTS `sms_templates`;
CREATE TABLE IF NOT EXISTS `sms_templates` (
  `id` int NOT NULL AUTO_INCREMENT,
  `key` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `variables` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'comma-separated available placeholders',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sms_templates`
--

INSERT INTO `sms_templates` (`id`, `key`, `name`, `body`, `variables`, `updated_at`, `updated_by`) VALUES
(1, 'cargo_arrived', 'Ачаа ирсэн мэдэгдэл', 'Tanii zahialsan baraa Solongosoos irlee. Hayag: BGD 26-r horoo Narnii horoolol 9-r bair, 72228880. Tue-Fri 10:00-19:00, Sat 11:00-19:00.', NULL, '2026-06-21 03:25:55', NULL),
(2, 'batch_notify', 'Батч SMS мэдэгдэл', 'Tanii zahialsan baraa Solongosoos irlee. Hayag: BGD 26-r horoo Narnii horoolol 9-r bair, 72228880. Tue-Fri 10:00-19:00, Sat 11:00-19:00.', NULL, '2026-06-21 03:25:55', NULL),
(3, 'otp', 'Баталгаажуулах код (OTP)', 'Guzeelzgene баталгаажуулах код: {code}', '{code}', '2026-06-21 03:25:55', NULL),
(4, 'order_ready_pickup', 'Очиж авахад бэлэн', 'Сайн байна уу! Таны {order_number} захиалга очиж авахад бэлэн боллоо. Runners World', '{order_number}', '2026-07-19 03:57:54', 1),
(5, 'order_delivering', 'Хүргэлтэнд гарсан', 'Сайн байна уу! Таны {order_number} захиалга хүргэгдэж байна. Runners World', '{order_number}', '2026-07-19 03:57:45', 1),
(6, 'order_general', 'Ерөнхий захиалга мэдэгдэл', 'Сайн байна уу! Таны {order_number} захиалгатай холбоотой мэдэгдэл. Runners World', '{order_number}', '2026-07-19 03:57:49', 1);

-- --------------------------------------------------------

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `variant_id` int DEFAULT NULL,
  `delta` int NOT NULL,
  `balance_after` int DEFAULT NULL,
  `reason` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_id` int DEFAULT NULL,
  `actor_type` enum('customer','admin','system') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'system',
  `actor_id` int DEFAULT NULL,
  `note` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sm_product_time` (`product_id`,`created_at`),
  KEY `idx_sm_variant_time` (`variant_id`,`created_at`),
  KEY `idx_sm_order` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stock_movements`
--

INSERT INTO `stock_movements` (`id`, `product_id`, `variant_id`, `delta`, `balance_after`, `reason`, `order_id`, `actor_type`, `actor_id`, `note`, `created_at`) VALUES
(1, 193, 564, -1, 9, 'order_sale', 1, 'customer', 1, 'Order RW64039781', '2026-07-21 07:15:30'),
(2, 180, 508, -3, 27, 'order_sale', 1, 'customer', 1, 'Order RW64039781', '2026-07-21 07:15:30'),
(3, 170, 483, -1, 1, 'order_sale', 2, 'customer', 1, 'Order RW04718868', '2026-07-21 07:27:29'),
(4, 166, NULL, -1, 5, 'order_sale', NULL, 'customer', 3, 'Order RW62840787', '2026-07-21 07:38:43'),
(5, 172, NULL, -5, 5, 'order_sale', 4, 'customer', 1, 'Order RW51864998', '2026-07-21 08:07:19'),
(6, 171, 485, -1, 3, 'order_sale', 5, 'customer', 1, 'Order RW57260292', '2026-07-21 08:08:37'),
(7, 166, NULL, -1, 4, 'order_sale', NULL, 'customer', 6, 'Order RW09459827', '2026-07-21 08:28:12'),
(8, 171, 485, 1, 4, 'order_cancel', 5, 'system', NULL, 'Auto-cancel: RW57260292', '2026-07-21 08:44:20'),
(9, 171, 485, -1, 3, 'order_sale', 7, 'customer', 1, 'Order RW10958294', '2026-07-21 08:44:20'),
(10, 171, 485, 1, 4, 'order_cancel', 7, 'system', NULL, 'Auto-cancel: RW10958294', '2026-07-21 10:19:53'),
(11, 173, 487, -1, 1, 'order_sale', 8, 'customer', 9, 'Order RW30067753', '2026-07-21 10:28:10'),
(12, 175, 503, -1, 2, 'order_sale', 9, 'customer', 11, 'Order RW90784082', '2026-07-21 10:41:46');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

DROP TABLE IF EXISTS `testimonials`;
CREATE TABLE IF NOT EXISTS `testimonials` (
  `id` int NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rating` tinyint DEFAULT '5',
  `title` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint DEFAULT '1',
  `sort_order` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cargo_payments`
--
ALTER TABLE `cargo_payments`
  ADD CONSTRAINT `fk_cp_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_addresses`
--
ALTER TABLE `customer_addresses`
  ADD CONSTRAINT `customer_addresses_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_addresses_ibfk_2` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customer_sessions`
--
ALTER TABLE `customer_sessions`
  ADD CONSTRAINT `customer_sessions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deliveries`
--
ALTER TABLE `deliveries`
  ADD CONSTRAINT `deliveries_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deliveries_ibfk_2` FOREIGN KEY (`driver_id`) REFERENCES `delivery_drivers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery_batches`
--
ALTER TABLE `delivery_batches`
  ADD CONSTRAINT `delivery_batches_ibfk_1` FOREIGN KEY (`driver_id`) REFERENCES `delivery_drivers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `khoroos`
--
ALTER TABLE `khoroos`
  ADD CONSTRAINT `khoroos_ibfk_1` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`district_id`) REFERENCES `districts` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`khoroo_id`) REFERENCES `khoroos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`cargo_batch_id`) REFERENCES `cargo_batches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_main_image` FOREIGN KEY (`main_image_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`);

--
-- Constraints for table `product_activity_types`
--
ALTER TABLE `product_activity_types`
  ADD CONSTRAINT `product_activity_types_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_activity_types_ibfk_2` FOREIGN KEY (`activity_type_id`) REFERENCES `activity_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_color_images`
--
ALTER TABLE `product_color_images`
  ADD CONSTRAINT `product_color_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_color_images_ibfk_2` FOREIGN KEY (`color_id`) REFERENCES `product_colors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_color_images_ibfk_3` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_entry_permissions`
--
ALTER TABLE `product_entry_permissions`
  ADD CONSTRAINT `product_entry_permissions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_entry_permissions_ibfk_2` FOREIGN KEY (`granted_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_price_tiers`
--
ALTER TABLE `product_price_tiers`
  ADD CONSTRAINT `fk_price_tiers_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_variants_ibfk_2` FOREIGN KEY (`color_id`) REFERENCES `product_colors` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_variants_ibfk_3` FOREIGN KEY (`size_id`) REFERENCES `product_sizes` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `shop_categories`
--
ALTER TABLE `shop_categories`
  ADD CONSTRAINT `shop_categories_ibfk_1` FOREIGN KEY (`shop_id`) REFERENCES `shops` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `shop_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD CONSTRAINT `fk_sm_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sm_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sm_variant` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
