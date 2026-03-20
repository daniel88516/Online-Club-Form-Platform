-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026-03-20 18:57:53
-- 伺服器版本： 10.4.32-MariaDB
-- PHP 版本： 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `group_13`
--
CREATE DATABASE IF NOT EXISTS `group_13` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `group_13`;

-- --------------------------------------------------------

--
-- 資料表結構 `comment_likes`
--

CREATE TABLE `comment_likes` (
  `id` int(11) NOT NULL,
  `comment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `comment_likes`
--

INSERT INTO `comment_likes` (`id`, `comment_id`, `user_id`, `created_at`) VALUES
(1, 1, 2, '2026-03-08 13:25:46');

-- --------------------------------------------------------

--
-- 資料表結構 `forms`
--

CREATE TABLE `forms` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `target_group` int(11) DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `allow_multiple` tinyint(1) DEFAULT 0,
  `is_published` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `show_stats` tinyint(1) DEFAULT 1,
  `anonymous_responses` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `forms`
--

INSERT INTO `forms` (`id`, `user_id`, `title`, `description`, `cover_image`, `target_group`, `start_date`, `end_date`, `allow_multiple`, `is_published`, `created_at`, `show_stats`, `anonymous_responses`) VALUES
(1, 2, '初音可愛嗎', '<p>你覺得初音可愛嗎</p>', '/uploads/forms/img_69ad6b074f1f87.96832362.jpg', NULL, '0000-00-00 00:00:00', '2026-03-25 20:27:00', 1, 1, '2026-03-08 12:29:37', 1, 0),
(2, 1, 'F1 goat 是誰', '<p>請問F1 goat 是誰?</p>', '/uploads/forms/img_69ad923d80dba9.05451323.jpg', NULL, NULL, '2026-03-27 23:14:00', 1, 1, '2026-03-08 15:14:42', 1, 0);

-- --------------------------------------------------------

--
-- 資料表結構 `form_bookmarks`
--

CREATE TABLE `form_bookmarks` (
  `id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `form_bookmarks`
--

INSERT INTO `form_bookmarks` (`id`, `form_id`, `user_id`, `created_at`) VALUES
(1, 1, 2, '2026-03-08 12:30:01'),
(2, 2, 2, '2026-03-09 06:27:18');

-- --------------------------------------------------------

--
-- 資料表結構 `form_comments`
--

CREATE TABLE `form_comments` (
  `id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `form_comments`
--

INSERT INTO `form_comments` (`id`, `form_id`, `user_id`, `content`, `parent_id`, `image_path`, `created_at`) VALUES
(1, 1, 2, '<p>當然可愛</p>', NULL, NULL, '2026-03-08 12:34:27'),
(2, 1, 2, '<p>對阿</p>', 1, NULL, '2026-03-08 13:30:51'),
(3, 1, 1, '<p>我也覺得不錯</p>', NULL, NULL, '2026-03-08 15:16:13'),
(4, 1, 1, '<p>對阿</p>', 1, NULL, '2026-03-08 15:16:19'),
(5, 2, 2, '<p>當然是russel</p>', NULL, NULL, '2026-03-09 06:27:32');

-- --------------------------------------------------------

--
-- 資料表結構 `form_fields`
--

CREATE TABLE `form_fields` (
  `id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `field_type` enum('short_text','long_text','radio','checkbox','dropdown','date','time') NOT NULL,
  `label` varchar(255) NOT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `options` text DEFAULT NULL,
  `order_num` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `form_fields`
--

INSERT INTO `form_fields` (`id`, `form_id`, `field_type`, `label`, `is_required`, `options`, `order_num`) VALUES
(1, 1, 'radio', '你覺得初音可愛嗎', 1, '[\"是\",\"否\",\"不知道\"]', 0),
(2, 1, 'short_text', '說說你的看法吧', 0, NULL, 1),
(7, 2, 'dropdown', '是誰呢', 1, '[\"舒馬克\",\"Senna\",\"漢寶\",\"汽車人\",\"其他\"]', 0),
(8, 2, 'short_text', '說說你的看法吧!!!', 0, NULL, 1);

-- --------------------------------------------------------

--
-- 資料表結構 `form_likes`
--

CREATE TABLE `form_likes` (
  `id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `form_likes`
--

INSERT INTO `form_likes` (`id`, `form_id`, `user_id`, `created_at`) VALUES
(1, 1, 2, '2026-03-08 12:30:02'),
(2, 2, 2, '2026-03-09 06:27:18');

-- --------------------------------------------------------

--
-- 資料表結構 `form_responses`
--

CREATE TABLE `form_responses` (
  `id` int(11) NOT NULL,
  `form_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `form_responses`
--

INSERT INTO `form_responses` (`id`, `form_id`, `user_id`, `submitted_at`) VALUES
(1, 1, 2, '2026-03-08 13:06:24'),
(2, 1, 2, '2026-03-08 15:17:42'),
(3, 2, 2, '2026-03-20 17:26:28'),
(4, 2, 1, '2026-03-20 17:27:55');

-- --------------------------------------------------------

--
-- 資料表結構 `groups`
--

CREATE TABLE `groups` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `groups`
--

INSERT INTO `groups` (`id`, `name`, `description`, `created_at`) VALUES
(1, '一般', '一般會員群組', '2026-03-03 12:09:46'),
(2, '管理', '管理員群組', '2026-03-03 12:09:46');

-- --------------------------------------------------------

--
-- 資料表結構 `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `receiver_id`, `content`, `is_read`, `created_at`) VALUES
(1, 1, 2, '你在幹麻', 1, '2026-03-08 15:16:33'),
(2, 2, 1, '我在吃飯阿', 1, '2026-03-08 15:17:02'),
(3, 1, 2, '那你覺得我要不要去考研究所，你會覺得很重要嗎', 1, '2026-03-08 15:20:18'),
(4, 2, 1, '可以阿我自己覺得很不錯阿', 1, '2026-03-08 15:20:43'),
(5, 1, 2, '是這樣嗎', 1, '2026-03-09 06:07:48'),
(6, 2, 1, '對阿', 1, '2026-03-09 06:08:08'),
(7, 2, 1, '[sticker:🥰]', 1, '2026-03-09 06:17:33'),
(8, 2, 1, '😀😂🥰', 1, '2026-03-09 06:17:53'),
(9, 2, 1, '[sticker:🥹]', 1, '2026-03-09 06:18:01'),
(10, 2, 1, '[sticker:🫶]', 1, '2026-03-09 06:20:48'),
(11, 2, 1, '[sticker:💯]', 1, '2026-03-09 06:20:52'),
(12, 2, 1, '😡😡😡😡😭😭😭😭', 1, '2026-03-09 06:20:53'),
(13, 2, 1, '[sticker:🐸]', 1, '2026-03-09 06:21:21'),
(14, 2, 1, '[sticker:💪]', 1, '2026-03-09 06:21:28'),
(15, 2, 1, '[sticker:💪]', 1, '2026-03-09 06:21:29'),
(16, 2, 1, '[sticker:💪]', 1, '2026-03-09 06:21:29'),
(17, 2, 1, '😬😬', 1, '2026-03-09 06:21:32'),
(18, 2, 1, '[sticker:🐸]', 1, '2026-03-09 06:21:44'),
(19, 3, 2, '【檢舉通知】\n類型：表單\n原因：騷擾內容\n目標 ID：2\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:1]', 1, '2026-03-09 06:22:08');

-- --------------------------------------------------------

--
-- 資料表結構 `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `type` enum('comment','form') NOT NULL,
  `target_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reason` varchar(50) NOT NULL,
  `status` enum('pending','resolved') DEFAULT 'pending',
  `delete_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `reports`
--

INSERT INTO `reports` (`id`, `type`, `target_id`, `reporter_id`, `reason`, `status`, `delete_token`, `created_at`) VALUES
(1, 'form', 2, 2, '騷擾內容', 'resolved', NULL, '2026-03-09 06:22:08');

-- --------------------------------------------------------

--
-- 資料表結構 `response_answers`
--

CREATE TABLE `response_answers` (
  `id` int(11) NOT NULL,
  `response_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `answer` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `response_answers`
--

INSERT INTO `response_answers` (`id`, `response_id`, `field_id`, `answer`) VALUES
(1, 1, 1, '是'),
(2, 1, 2, '當然可愛'),
(3, 2, 1, '是'),
(4, 2, 2, '沒錯'),
(5, 3, 7, 'Senna'),
(6, 3, 8, ''),
(7, 4, 7, '舒馬克'),
(8, 4, 8, '當然是舒馬克');

-- --------------------------------------------------------

--
-- 資料表結構 `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','member') DEFAULT 'member',
  `group_id` int(11) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `profile_bg` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- 傾印資料表的資料 `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `group_id`, `avatar`, `profile_bg`, `created_at`, `bio`) VALUES
(1, 'member', '$2y$10$YxweHdRrvjwkbHCuQD6ofuDHf/gDsfGWmenoMNNskzdeM52Wg6WwK', 'member@example.com', 'member', 1, '/uploads/avatars/avatar_1_69ad9284db6b1.jpg', '/uploads/profile_bg/bg_1_69ad92a7c1371.jpg', '2026-03-03 12:09:46', NULL),
(2, 'admin', '$2y$10$pxYiFhrb.E0T9XvYqa5po.PF/7r6DLdl/gLRaqllzMiiMwqaRXIWu', 'admin@example.com', 'admin', 2, '/uploads/avatars/avatar_2_69ad68db93264.jpg', '/uploads/profile_bg/bg_2_69ad68e24e096.jpg', '2026-03-03 12:09:46', '搖曳露營好好看'),
(3, '系統', '', 'system@localhost', 'member', NULL, NULL, NULL, '2026-03-03 12:15:37', NULL);

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_comment_like` (`comment_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- 資料表索引 `forms`
--
ALTER TABLE `forms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `target_group` (`target_group`);

--
-- 資料表索引 `form_bookmarks`
--
ALTER TABLE `form_bookmarks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bookmark` (`form_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- 資料表索引 `form_comments`
--
ALTER TABLE `form_comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `form_id` (`form_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- 資料表索引 `form_fields`
--
ALTER TABLE `form_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `form_id` (`form_id`);

--
-- 資料表索引 `form_likes`
--
ALTER TABLE `form_likes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_like` (`form_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- 資料表索引 `form_responses`
--
ALTER TABLE `form_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `form_id` (`form_id`),
  ADD KEY `user_id` (`user_id`);

--
-- 資料表索引 `groups`
--
ALTER TABLE `groups`
  ADD PRIMARY KEY (`id`);

--
-- 資料表索引 `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- 資料表索引 `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reporter_id` (`reporter_id`);

--
-- 資料表索引 `response_answers`
--
ALTER TABLE `response_answers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `response_id` (`response_id`),
  ADD KEY `field_id` (`field_id`);

--
-- 資料表索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `group_id` (`group_id`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `comment_likes`
--
ALTER TABLE `comment_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `forms`
--
ALTER TABLE `forms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `form_bookmarks`
--
ALTER TABLE `form_bookmarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `form_comments`
--
ALTER TABLE `form_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `form_fields`
--
ALTER TABLE `form_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `form_likes`
--
ALTER TABLE `form_likes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `form_responses`
--
ALTER TABLE `form_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `groups`
--
ALTER TABLE `groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `response_answers`
--
ALTER TABLE `response_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- 已傾印資料表的限制式
--

--
-- 資料表的限制式 `comment_likes`
--
ALTER TABLE `comment_likes`
  ADD CONSTRAINT `comment_likes_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `form_comments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `comment_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `forms`
--
ALTER TABLE `forms`
  ADD CONSTRAINT `forms_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forms_ibfk_2` FOREIGN KEY (`target_group`) REFERENCES `groups` (`id`) ON DELETE SET NULL;

--
-- 資料表的限制式 `form_bookmarks`
--
ALTER TABLE `form_bookmarks`
  ADD CONSTRAINT `form_bookmarks_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `form_bookmarks_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `form_comments`
--
ALTER TABLE `form_comments`
  ADD CONSTRAINT `form_comments_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `form_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `form_comments_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `form_comments` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `form_fields`
--
ALTER TABLE `form_fields`
  ADD CONSTRAINT `form_fields_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `form_likes`
--
ALTER TABLE `form_likes`
  ADD CONSTRAINT `form_likes_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `form_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `form_responses`
--
ALTER TABLE `form_responses`
  ADD CONSTRAINT `form_responses_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `form_responses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- 資料表的限制式 `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `response_answers`
--
ALTER TABLE `response_answers`
  ADD CONSTRAINT `response_answers_ibfk_1` FOREIGN KEY (`response_id`) REFERENCES `form_responses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `response_answers_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `form_fields` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
