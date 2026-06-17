-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: group_13
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Current Database: `group_13`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `group_13` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;

USE `group_13`;

--
-- Table structure for table `club_members`
--

DROP TABLE IF EXISTS `club_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `club_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `club_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('owner','member') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','pending','invited') DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_club_user` (`club_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `club_members`
--

LOCK TABLES `club_members` WRITE;
/*!40000 ALTER TABLE `club_members` DISABLE KEYS */;
INSERT INTO `club_members` VALUES (1,1,2,'owner','2026-04-20 18:10:55','active'),(43,2,2,'owner','2026-05-26 10:24:02','active');
/*!40000 ALTER TABLE `club_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clubs`
--

DROP TABLE IF EXISTS `clubs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clubs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `owner_id` int(11) NOT NULL,
  `is_public` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clubs`
--

LOCK TABLES `clubs` WRITE;
/*!40000 ALTER TABLE `clubs` DISABLE KEYS */;
INSERT INTO `clubs` VALUES (1,'GTR俱樂部','GTR最棒','/uploads/comments/img_69e6e510aa0027.51304278.jpg',2,0,'2026-04-20 18:10:55'),(2,'F1俱樂部','喜歡F1的人都可以進來喔','/uploads/comments/img_6a157b5a146ce9.32800354.jpg',2,0,'2026-05-26 10:24:02');
/*!40000 ALTER TABLE `clubs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comment_likes`
--

DROP TABLE IF EXISTS `comment_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comment_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_comment_like` (`comment_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `comment_likes_ibfk_1` FOREIGN KEY (`comment_id`) REFERENCES `form_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comment_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comment_likes`
--

LOCK TABLES `comment_likes` WRITE;
/*!40000 ALTER TABLE `comment_likes` DISABLE KEYS */;
INSERT INTO `comment_likes` VALUES (1,1,2,'2026-03-08 13:25:46'),(2,6,2,'2026-05-12 15:24:13'),(4,3,2,'2026-05-12 16:33:36'),(5,2,2,'2026-06-05 22:43:33');
/*!40000 ALTER TABLE `comment_likes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_bookmarks`
--

DROP TABLE IF EXISTS `form_bookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_bookmarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bookmark` (`form_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `form_bookmarks_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `form_bookmarks_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_bookmarks`
--

LOCK TABLES `form_bookmarks` WRITE;
/*!40000 ALTER TABLE `form_bookmarks` DISABLE KEYS */;
INSERT INTO `form_bookmarks` VALUES (2,2,2,'2026-03-09 06:27:18'),(3,2,1,'2026-04-04 14:56:12'),(7,1,2,'2026-05-12 15:24:20'),(9,15,2,'2026-05-14 15:08:29'),(10,9,2,'2026-05-23 18:56:53');
/*!40000 ALTER TABLE `form_bookmarks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_clubs`
--

DROP TABLE IF EXISTS `form_clubs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_clubs` (
  `form_id` int(11) NOT NULL,
  `club_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`form_id`,`club_id`),
  KEY `idx_form_clubs_club_id` (`club_id`),
  CONSTRAINT `form_clubs_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `form_clubs_ibfk_2` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_clubs`
--

LOCK TABLES `form_clubs` WRITE;
/*!40000 ALTER TABLE `form_clubs` DISABLE KEYS */;
INSERT INTO `form_clubs` VALUES (15,1,'2026-05-27 07:39:48');
/*!40000 ALTER TABLE `form_clubs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_comments`
--

DROP TABLE IF EXISTS `form_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `form_id` (`form_id`),
  KEY `user_id` (`user_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `form_comments_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `form_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `form_comments_ibfk_3` FOREIGN KEY (`parent_id`) REFERENCES `form_comments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_comments`
--

LOCK TABLES `form_comments` WRITE;
/*!40000 ALTER TABLE `form_comments` DISABLE KEYS */;
INSERT INTO `form_comments` VALUES (1,1,2,'<p>當然可愛</p>',NULL,NULL,'2026-03-08 12:34:27'),(2,1,2,'<p>對阿</p>',1,NULL,'2026-03-08 13:30:51'),(3,1,1,'<p>我也覺得不錯</p>',NULL,NULL,'2026-03-08 15:16:13'),(4,1,1,'<p>對阿</p>',1,NULL,'2026-03-08 15:16:19'),(5,2,2,'<p>當然是russel</p>',NULL,NULL,'2026-03-09 06:27:32'),(6,2,1,'<p>很棒</p>',NULL,NULL,'2026-04-04 14:56:20'),(11,1,2,'<p>我也覺得</p>',3,NULL,'2026-05-12 15:24:28'),(17,15,2,'<p>你好</p>',NULL,NULL,'2026-05-23 18:59:33'),(20,1,2,'<p>對</p>',NULL,NULL,'2026-06-05 20:17:10'),(30,2,2,'<p>很棒</p>',NULL,NULL,'2026-06-06 10:41:38'),(31,2,2,'<p>我也覺得很棒</p>',6,NULL,'2026-06-06 10:41:46'),(32,2,2,'<p>好</p>',NULL,NULL,'2026-06-06 10:48:13'),(33,2,2,'<p>很讚</p>',6,NULL,'2026-06-06 10:48:25'),(36,1,2,'<p>好</p>',NULL,NULL,'2026-06-07 12:51:40'),(37,1,2,'<p>沒錯</p>',4,NULL,'2026-06-07 12:51:48'),(38,1,2,'<p>好</p>',NULL,NULL,'2026-06-07 12:55:06');
/*!40000 ALTER TABLE `form_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_fields`
--

DROP TABLE IF EXISTS `form_fields`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_fields` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_id` int(11) NOT NULL,
  `field_type` enum('short_text','long_text','radio','checkbox','dropdown','date','time') NOT NULL,
  `label` varchar(255) NOT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `options` text DEFAULT NULL,
  `order_num` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `form_id` (`form_id`),
  CONSTRAINT `form_fields_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_fields`
--

LOCK TABLES `form_fields` WRITE;
/*!40000 ALTER TABLE `form_fields` DISABLE KEYS */;
INSERT INTO `form_fields` VALUES (7,2,'dropdown','是誰呢',1,'[\"舒馬克\",\"Senna\",\"漢寶\",\"汽車人\",\"其他\"]',0),(8,2,'short_text','說說你的看法吧!!!',0,NULL,1),(30,15,'radio','GTR 是好車嗎',1,'[\"是\",\"否\",\"不知道\"]',0),(31,15,'short_text','說說你的看法吧!!!',0,NULL,1),(35,9,'radio','咖哩飯要拌嗎',1,'[\"要\",\"不要\",\"都可以\"]',0),(36,9,'long_text','說說你的看法吧!!!',0,NULL,1),(60,1,'radio','你覺得初音可愛嗎',1,'[\"是\",\"否\",\"不知道\",\"超可愛\"]',0),(61,1,'short_text','說說你的看法吧',0,NULL,1),(62,19,'radio','123',0,'[\"選項 1\",\"選項 2\"]',0);
/*!40000 ALTER TABLE `form_fields` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_likes`
--

DROP TABLE IF EXISTS `form_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_likes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_like` (`form_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `form_likes_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `form_likes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_likes`
--

LOCK TABLES `form_likes` WRITE;
/*!40000 ALTER TABLE `form_likes` DISABLE KEYS */;
INSERT INTO `form_likes` VALUES (2,2,2,'2026-03-09 06:27:18'),(3,2,1,'2026-04-04 14:56:11'),(8,9,1,'2026-04-20 16:13:44'),(20,15,2,'2026-06-06 10:53:09'),(21,9,2,'2026-06-07 12:54:48'),(22,1,2,'2026-06-07 12:59:55');
/*!40000 ALTER TABLE `form_likes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_responses`
--

DROP TABLE IF EXISTS `form_responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `form_id` (`form_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `form_responses_ibfk_1` FOREIGN KEY (`form_id`) REFERENCES `forms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `form_responses_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_responses`
--

LOCK TABLES `form_responses` WRITE;
/*!40000 ALTER TABLE `form_responses` DISABLE KEYS */;
INSERT INTO `form_responses` VALUES (1,1,2,'2026-03-08 13:06:24'),(2,1,2,'2026-03-08 15:17:42'),(3,2,2,'2026-03-20 17:26:28'),(4,2,1,'2026-03-20 17:27:55'),(17,9,2,'2026-04-20 15:49:15'),(18,9,2,'2026-04-20 15:50:57'),(19,9,2,'2026-04-20 15:52:50'),(20,9,2,'2026-04-20 16:05:47'),(21,9,1,'2026-04-20 16:13:20');
/*!40000 ALTER TABLE `form_responses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `forms`
--

DROP TABLE IF EXISTS `forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `anonymous_responses` tinyint(1) DEFAULT 0,
  `show_on_index` tinyint(1) NOT NULL DEFAULT 1,
  `response_scope` enum('all_members','club_members') NOT NULL DEFAULT 'all_members',
  `club_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `target_group` (`target_group`),
  CONSTRAINT `forms_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `forms_ibfk_2` FOREIGN KEY (`target_group`) REFERENCES `groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `forms`
--

LOCK TABLES `forms` WRITE;
/*!40000 ALTER TABLE `forms` DISABLE KEYS */;
INSERT INTO `forms` VALUES (1,2,'初音可愛嗎','<p><strong>你覺得初音可愛嗎</strong></p>','/uploads/forms/img_69ad6b074f1f87.96832362.jpg',NULL,NULL,NULL,1,1,'2026-03-08 12:29:37',1,0,1,'all_members',NULL),(2,1,'F1 goat 是誰','<p>請問F1 goat 是誰?</p>','/uploads/forms/img_69ad923d80dba9.05451323.jpg',NULL,NULL,'2026-03-27 23:14:00',1,1,'2026-03-08 15:14:42',1,0,1,'all_members',NULL),(9,12,'咖哩飯要拌嗎','<p>咖哩飯要拌嗎</p>','/uploads/forms/img_6a2680e25e1f51.02936292.jpg',NULL,NULL,NULL,1,1,'2026-04-20 15:48:23',1,0,1,'all_members',NULL),(15,2,'GTR 是好車嗎','<p><br></p>','/uploads/forms/img_6a05daa2877689.52994864.webp',NULL,NULL,NULL,1,1,'2026-05-14 13:41:24',1,0,0,'all_members',1),(19,2,'123','<p><br></p>',NULL,NULL,NULL,NULL,1,1,'2026-06-08 09:34:42',1,0,1,'all_members',NULL);
/*!40000 ALTER TABLE `forms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `groups`
--

DROP TABLE IF EXISTS `groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `groups`
--

LOCK TABLES `groups` WRITE;
/*!40000 ALTER TABLE `groups` DISABLE KEYS */;
INSERT INTO `groups` VALUES (1,'一般','一般會員群組','2026-03-03 12:09:46'),(2,'管理','管理員群組','2026-03-03 12:09:46');
/*!40000 ALTER TABLE `groups` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=118 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (1,1,2,'你在幹麻',1,'2026-03-08 15:16:33'),(2,2,1,'我在吃飯阿',1,'2026-03-08 15:17:02'),(3,1,2,'那你覺得我要不要去考研究所，你會覺得很重要嗎',1,'2026-03-08 15:20:18'),(4,2,1,'可以阿我自己覺得很不錯阿',1,'2026-03-08 15:20:43'),(5,1,2,'是這樣嗎',1,'2026-03-09 06:07:48'),(6,2,1,'對阿',1,'2026-03-09 06:08:08'),(7,2,1,'[sticker:🥰]',1,'2026-03-09 06:17:33'),(8,2,1,'😀😂🥰',1,'2026-03-09 06:17:53'),(9,2,1,'[sticker:🥹]',1,'2026-03-09 06:18:01'),(10,2,1,'[sticker:🫶]',1,'2026-03-09 06:20:48'),(11,2,1,'[sticker:💯]',1,'2026-03-09 06:20:52'),(12,2,1,'😡😡😡😡😭😭😭😭',1,'2026-03-09 06:20:53'),(13,2,1,'[sticker:🐸]',1,'2026-03-09 06:21:21'),(14,2,1,'[sticker:💪]',1,'2026-03-09 06:21:28'),(15,2,1,'[sticker:💪]',1,'2026-03-09 06:21:29'),(16,2,1,'[sticker:💪]',1,'2026-03-09 06:21:29'),(17,2,1,'😬😬',1,'2026-03-09 06:21:32'),(18,2,1,'[sticker:🐸]',1,'2026-03-09 06:21:44'),(19,3,2,'【檢舉通知】\n類型：表單\n原因：騷擾內容\n目標 ID：2\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:1]',1,'2026-03-09 06:22:08'),(20,3,2,'【社團申請通知】\n用戶「member」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-20 19:25:42'),(21,3,1,'【社團申請通過】\n您申請加入社團「GTR俱樂部」已通過審核！',1,'2026-04-20 19:26:18'),(22,3,12,'【社團邀請】\n您被邀請加入社團「GTR俱樂部」，請前往社團頁面確認邀請。',1,'2026-04-21 07:31:40'),(23,3,12,'【社團邀請】\n您被邀請加入社團「GTR俱樂部」，請前往社團頁面確認邀請。',1,'2026-04-21 07:33:34'),(24,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 07:34:08'),(25,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 07:45:40'),(26,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 07:47:28'),(27,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 07:47:30'),(28,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 07:48:29'),(29,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 07:48:31'),(30,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 07:48:34'),(31,3,12,'【社團邀請】\n您被邀請加入社團「GTR俱樂部」，請前往社團頁面確認邀請。',1,'2026-04-21 07:49:52'),(32,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 07:50:19'),(33,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 07:50:23'),(34,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 07:50:25'),(35,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 07:51:40'),(36,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 07:52:12'),(37,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 07:54:04'),(38,3,12,'【社團申請通過】\n您申請加入社團「GTR俱樂部」已通過審核！',1,'2026-04-21 07:57:12'),(39,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 08:22:27'),(40,3,12,'【社團申請通過】\n您申請加入社團「GTR俱樂部」已通過審核！[CLUB:1]',1,'2026-04-21 08:22:47'),(41,3,12,'【社團通知】\n你已被移出社團「GTR俱樂部」。',1,'2026-04-21 08:25:00'),(42,3,12,'【社團邀請】\n您被邀請加入社團「GTR俱樂部」，請前往社團頁面確認邀請。',1,'2026-04-21 08:25:05'),(43,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團頁面審核。',1,'2026-04-21 08:30:37'),(44,3,12,'【社團申請未通過】\n您申請加入社團「GTR俱樂部」未通過審核。',1,'2026-04-21 08:33:49'),(45,3,12,'【社團邀請】\n您被邀請加入社團「GTR俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-04-21 08:33:57'),(46,3,12,'【社團邀請】\n您被邀請加入社團「GTR俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-04-21 08:36:49'),(47,3,2,'【社團申請通知】\n用戶「daniel」申請加入您的社團「GTR俱樂部」，請前往社團審核。[CLUB:1]',1,'2026-04-21 08:39:37'),(48,3,12,'【社團申請通過】\n您申請加入社團「GTR俱樂部」已通過審核！[CLUB:1]',1,'2026-04-21 08:39:58'),(49,3,2,'【社團申請通知】\n用戶「member」申請加入您的社團「GTR俱樂部」，請前往社團審核。[CLUB:1]',1,'2026-04-21 08:42:55'),(50,3,1,'【社團申請未通過】\n您申請加入社團「GTR俱樂部」未通過審核。[CLUBS_EXPLORE]',1,'2026-04-21 08:43:08'),(51,3,1,'【社團邀請】\n您被邀請加入社團「GTR俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-04-21 08:44:27'),(52,3,12,'【社團邀請】\n您被邀請加入社團「GTR俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-04-21 08:46:50'),(53,3,1,'【社團通知】\n你已被移出社團「GTR俱樂部」。[CLUBS_EXPLORE]',1,'2026-04-21 08:47:37'),(54,3,1,'【社團邀請】\n您被邀請加入社團「GTR俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-04-21 08:47:49'),(55,3,1,'【社團邀請已取消】\n您被邀請加入社團「GTR俱樂部」的邀請已被取消。',1,'2026-04-21 08:51:13'),(58,3,1,'【社團邀請】\n您被邀請加入社團「GTR俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-04-21 08:54:39'),(59,2,1,'嗨嗨',1,'2026-05-12 15:42:05'),(60,2,1,'😀',1,'2026-05-12 15:42:08'),(61,2,1,'😀',1,'2026-05-12 15:42:13'),(62,2,1,'嗨',1,'2026-05-12 15:42:18'),(63,2,1,'嗨嗨',1,'2026-05-12 15:42:23'),(64,2,1,'你好阿',1,'2026-05-12 15:42:26'),(65,2,1,'你好',1,'2026-05-14 12:47:01'),(66,3,2,'【檢舉通知】\n類型：表單\n原因：詐騙\n目標 ID：9\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:2]',1,'2026-05-14 12:52:10'),(67,2,1,'嗨',1,'2026-05-14 13:04:11'),(68,2,1,'哈囉你好阿',1,'2026-05-14 15:09:44'),(69,3,2,'【檢舉通知】\n類型：表單\n原因：詐騙\n目標 ID：9\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:3]',1,'2026-05-14 15:14:05'),(70,3,2,'【檢舉通知】\n類型：留言\n原因：騷擾內容\n目標 ID：5\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:4]',1,'2026-05-19 18:47:28'),(72,3,2,'【社團申請通知】\n用戶「member」申請加入您的社團「GTR俱樂部」，請前往社團審核。[CLUB:1]',1,'2026-05-19 19:13:59'),(73,3,1,'【社團申請通過】\n您申請加入社團「GTR俱樂部」已通過審核！[CLUB:1]',1,'2026-05-19 19:16:51'),(74,3,1,'【社團通知】\n你已被移出社團「GTR俱樂部」。[CLUBS_EXPLORE]',1,'2026-05-19 19:19:25'),(75,2,1,'你好',1,'2026-05-19 20:07:51'),(76,2,1,'你好',1,'2026-05-23 20:11:54'),(77,3,2,'【社團申請通知】\n用戶「member」申請加入您的社團「GTR俱樂部」，請前往社團審核。[CLUB:1]',1,'2026-05-27 15:21:17'),(78,3,1,'【社團申請通過】\n您申請加入社團「GTR俱樂部」已通過審核！[CLUB:1]',1,'2026-05-27 15:21:31'),(79,3,2,'【檢舉通知】\n類型：表單\n原因：騷擾內容\n目標 ID：2\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:5]',1,'2026-06-05 20:03:00'),(80,3,1,'【社團邀請】\n您被邀請加入社團「F1俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-06-05 20:10:35'),(81,3,1,'【社團邀請】\n您被邀請加入社團「F1俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-06-05 20:12:16'),(82,2,1,'嗨',1,'2026-06-05 20:15:39'),(83,3,2,'【檢舉通知】\n類型：表單\n原因：騷擾內容\n目標 ID：2\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:6]',1,'2026-06-05 20:20:33'),(84,3,1,'【社團邀請】\n您被邀請加入社團「F1俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-06-05 20:23:03'),(86,3,2,'【檢舉通知】\n類型：表單\n原因：騷擾內容\n目標 ID：9\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:7]',1,'2026-06-05 20:43:02'),(87,2,1,'哈囉',1,'2026-06-05 22:33:03'),(88,3,2,'【檢舉通知】\n類型：表單\n原因：騷擾內容\n目標 ID：2\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:8]',1,'2026-06-05 22:37:14'),(89,3,1,'【社團邀請】\n您被邀請加入社團「F1俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-06-05 22:39:48'),(90,3,2,'【檢舉通知】\n類型：表單\n原因：騷擾內容\n目標 ID：2\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:9]',1,'2026-06-05 22:48:54'),(91,3,1,'【社團邀請】\n您被邀請加入社團「F1俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-06-05 22:51:26'),(92,2,1,'嗨',1,'2026-06-06 08:57:28'),(93,3,2,'【檢舉通知】\n類型：表單\n原因：騷擾內容\n目標 ID：2\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:10]',1,'2026-06-06 09:01:45'),(94,3,1,'【社團邀請】\n您被邀請加入社團「F1俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-06-06 09:10:20'),(95,3,1,'【社團邀請】\n您被邀請加入社團「F1俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-06-06 10:54:59'),(96,3,2,'【檢舉通知】\n類型：表單\n原因：騷擾內容\n目標 ID：2\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:11]',1,'2026-06-06 11:38:56'),(97,3,2,'【檢舉通知】\n類型：表單\n原因：騷擾內容\n目標 ID：2\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:12]',1,'2026-06-06 11:41:16'),(98,3,2,'【檢舉通知】\n類型：表單\n原因：騷擾內容\n目標 ID：2\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:13]',1,'2026-06-06 11:47:17'),(99,3,2,'【檢舉通知】\n類型：表單\n原因：騷擾內容\n目標 ID：2\n\n請前往管理後台 › 檢舉管理 處理。[REPORT:14]',1,'2026-06-07 11:18:36'),(100,2,12,'你好',1,'2026-06-07 12:15:07'),(101,12,2,'嗨嗨',1,'2026-06-07 12:15:31'),(102,3,1,'【社團通知】\n你已被移出社團「GTR俱樂部」。[CLUBS_EXPLORE]',1,'2026-06-07 13:01:11'),(103,3,1,'【社團邀請】\n您被邀請加入社團「GTR俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-06-07 13:01:22'),(105,3,2,'【社團申請通知】\n用戶「member」申請加入您的社團「F1俱樂部」，請前往社團審核。[CLUB:2]',1,'2026-06-07 13:17:03'),(106,3,1,'【社團申請通過】\n您申請加入社團「F1俱樂部」已通過審核！[CLUB:2]',1,'2026-06-07 13:17:20'),(107,2,12,'你好',1,'2026-06-07 13:17:34'),(108,3,1,'【社團通知】\n你已被移出社團「F1俱樂部」。[CLUBS_EXPLORE]',1,'2026-06-07 13:19:36'),(109,1,2,'😡',1,'2026-06-07 13:22:28'),(110,1,2,'[sticker:🥰]',1,'2026-06-07 13:22:30'),(111,3,2,'【社團申請通知】\n用戶「member」申請加入您的社團「F1俱樂部」，請前往社團審核。[CLUB:2]',1,'2026-06-07 13:22:45'),(112,3,1,'【社團申請通過】\n您申請加入社團「F1俱樂部」已通過審核！[CLUB:2]',1,'2026-06-07 13:23:02'),(113,2,1,'好😭😭',1,'2026-06-07 13:23:16'),(114,2,1,'[sticker:💯]',1,'2026-06-07 13:23:19'),(115,3,1,'【社團通知】\n你已被移出社團「F1俱樂部」。[CLUBS_EXPLORE]',1,'2026-06-07 14:12:35'),(116,3,1,'【社團邀請】\n您被邀請加入社團「F1俱樂部」，請前往待處理社團確認邀請。[CLUBS_PENDING]',1,'2026-06-07 14:15:19'),(117,3,1,'【社團通知】\n你已被移出社團「F1俱樂部」。[CLUBS_EXPLORE]',0,'2026-06-07 14:15:46');
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reports`
--

DROP TABLE IF EXISTS `reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` enum('comment','form') NOT NULL,
  `target_id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reason` varchar(50) NOT NULL,
  `status` enum('pending','resolved') DEFAULT 'pending',
  `delete_token` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `reporter_id` (`reporter_id`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reports`
--

LOCK TABLES `reports` WRITE;
/*!40000 ALTER TABLE `reports` DISABLE KEYS */;
INSERT INTO `reports` VALUES (1,'form',2,2,'騷擾內容','resolved',NULL,'2026-03-09 06:22:08'),(2,'form',9,2,'詐騙','resolved',NULL,'2026-05-14 12:52:10'),(3,'form',9,2,'詐騙','resolved',NULL,'2026-05-14 15:14:05'),(4,'comment',5,2,'騷擾內容','resolved',NULL,'2026-05-19 18:47:28'),(5,'form',2,2,'騷擾內容','resolved',NULL,'2026-06-05 20:03:00'),(6,'form',2,2,'騷擾內容','resolved',NULL,'2026-06-05 20:20:33'),(7,'form',9,2,'騷擾內容','resolved',NULL,'2026-06-05 20:43:02'),(8,'form',2,2,'騷擾內容','resolved',NULL,'2026-06-05 22:37:14'),(9,'form',2,2,'騷擾內容','resolved',NULL,'2026-06-05 22:48:54'),(10,'form',2,2,'騷擾內容','resolved',NULL,'2026-06-06 09:01:45'),(11,'form',2,2,'騷擾內容','resolved',NULL,'2026-06-06 11:38:56'),(12,'form',2,2,'騷擾內容','resolved',NULL,'2026-06-06 11:41:16'),(13,'form',2,2,'騷擾內容','resolved',NULL,'2026-06-06 11:47:17'),(14,'form',2,2,'騷擾內容','resolved',NULL,'2026-06-07 11:18:36');
/*!40000 ALTER TABLE `reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `response_answers`
--

DROP TABLE IF EXISTS `response_answers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `response_answers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `response_id` int(11) NOT NULL,
  `field_id` int(11) NOT NULL,
  `answer` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `response_id` (`response_id`),
  KEY `field_id` (`field_id`),
  CONSTRAINT `response_answers_ibfk_1` FOREIGN KEY (`response_id`) REFERENCES `form_responses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `response_answers_ibfk_2` FOREIGN KEY (`field_id`) REFERENCES `form_fields` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `response_answers`
--

LOCK TABLES `response_answers` WRITE;
/*!40000 ALTER TABLE `response_answers` DISABLE KEYS */;
INSERT INTO `response_answers` VALUES (5,3,7,'Senna'),(6,3,8,''),(7,4,7,'舒馬克'),(8,4,8,'當然是舒馬克');
/*!40000 ALTER TABLE `response_answers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','member') DEFAULT 'member',
  `group_id` int(11) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `profile_bg` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `bio` text DEFAULT NULL,
  `profile_bg_ratio` tinyint(4) NOT NULL DEFAULT 7,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `group_id` (`group_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'member','$2y$10$YxweHdRrvjwkbHCuQD6ofuDHf/gDsfGWmenoMNNskzdeM52Wg6WwK','member@example.com','member',1,'/uploads/avatars/avatar_1_69ad9284db6b1.jpg','/uploads/profile_bg/bg_1_69ad92a7c1371.jpg','2026-03-03 12:09:46','我是member',6),(2,'admin','$2y$10$pxYiFhrb.E0T9XvYqa5po.PF/7r6DLdl/gLRaqllzMiiMwqaRXIWu','admin@example.com','admin',2,'/uploads/avatars/avatar_2_69ad68db93264.jpg','/uploads/profile_bg/bg_2_6a268c0c63d72.jpg','2026-03-03 12:09:46','搖曳露營好好看!',6),(3,'系統','','system@localhost','member',NULL,NULL,NULL,'2026-03-03 12:15:37',NULL,7),(12,'daniel','$2y$10$XGXN98ES0ihBV5rIK3B/SeJ10R04OTZBbev4RAXRbpqTPVeM.7/M6','daniel88516@yahoo.com','member',1,'/uploads/avatars/avatar_12_6a2518d5d83ce.jpg','/uploads/profile_bg/bg_12_6a25190fc5fac.jpg','2026-04-19 10:34:37',NULL,6),(13,'jjjghu','$2y$10$jSjTyymehuFrpMQswMqtye/.a.XMamaC4ePXt4ZD1kbsR5WuudjBO','chiuliyou@gmail.com','member',1,NULL,NULL,'2026-04-19 14:53:16',NULL,7);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'group_13'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-08 17:38:23
