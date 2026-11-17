-- MySQL dump 10.13  Distrib 8.4.11, for Linux (aarch64)
--
-- Host: localhost    Database: vekec_lms
-- ------------------------------------------------------
-- Server version	8.4.11

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ai_billing`
--

DROP TABLE IF EXISTS `ai_billing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_billing` (
  `course_id` bigint unsigned NOT NULL,
  `revision` int unsigned NOT NULL DEFAULT '1',
  `bindings` json NOT NULL,
  `accepted_by` bigint unsigned NOT NULL,
  `accepted_at` datetime NOT NULL,
  PRIMARY KEY (`course_id`),
  KEY `accepted_by` (`accepted_by`),
  CONSTRAINT `ai_billing_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `ai_billing_ibfk_2` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_billing`
--

LOCK TABLES `ai_billing` WRITE;
/*!40000 ALTER TABLE `ai_billing` DISABLE KEYS */;
INSERT INTO `ai_billing` VALUES (1,3,'[{\"model\": \"gemini-3.5-flash-lite\", \"payer\": \"teacher\", \"currency\": \"USD\", \"provider\": \"gemini\", \"capability\": \"text\", \"credential_id\": \"1\", \"monthly_budget\": 1, \"billing_owner_id\": 2, \"provider_revision\": 3}]',2,'2026-11-03 11:07:33');
/*!40000 ALTER TABLE `ai_billing` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_credentials`
--

DROP TABLE IF EXISTS `ai_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_credentials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `owner_user_id` bigint unsigned NOT NULL,
  `provider` varchar(30) NOT NULL,
  `label` varchar(100) NOT NULL,
  `key_hint` varchar(12) NOT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `status` varchar(20) NOT NULL DEFAULT 'unverified',
  `ciphertext` text,
  `nonce` varchar(64) DEFAULT NULL,
  `tag` varchar(64) DEFAULT NULL,
  `key_version` varchar(30) NOT NULL,
  `last_verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` datetime DEFAULT NULL,
  `provider_revision` int unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `owner_user_id` (`owner_user_id`,`status`),
  CONSTRAINT `ai_credentials_ibfk_1` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_credentials`
--

LOCK TABLES `ai_credentials` WRITE;
/*!40000 ALTER TABLE `ai_credentials` DISABLE KEYS */;
INSERT INTO `ai_credentials` VALUES (1,2,'gemini','projects/488955723448','olDg',1,'verified','pJBr20tbcNEUjYmex2MU8oO+mPAW2mbh3KDqLyPN+z9jbJ6pl71aICGsFwYO5EyfuiGXAMo=','l3YR8+71VctEByuv','+QMi2eYMWH7lVMRkgwFtow==','1','2026-11-03 11:02:02','2026-11-03 04:01:45',NULL,3);
/*!40000 ALTER TABLE `ai_credentials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_drafts`
--

DROP TABLE IF EXISTS `ai_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_drafts` (
  `actor_id` bigint unsigned NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  `operation_key` varchar(128) NOT NULL,
  `request_hash` char(64) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'processing',
  `response_json` json DEFAULT NULL,
  `error_code` varchar(80) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`actor_id`,`course_id`,`operation_key`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `ai_drafts_ibfk_1` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `ai_drafts_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_drafts`
--

LOCK TABLES `ai_drafts` WRITE;
/*!40000 ALTER TABLE `ai_drafts` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_drafts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_idempotency`
--

DROP TABLE IF EXISTS `ai_idempotency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_idempotency` (
  `actor_id` bigint unsigned NOT NULL,
  `route_hash` char(64) NOT NULL,
  `idem_key` varchar(128) NOT NULL,
  `body_hash` char(64) NOT NULL,
  `response_json` json DEFAULT NULL,
  `response_status` smallint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`actor_id`,`route_hash`,`idem_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_idempotency`
--

LOCK TABLES `ai_idempotency` WRITE;
/*!40000 ALTER TABLE `ai_idempotency` DISABLE KEYS */;
INSERT INTO `ai_idempotency` VALUES (2,'2ac80ac361b9d4e70bfa1fa151b599facff30c7a9abf1da44a02f7abe5a88263','c5f867ff-a772-4e69-af7c-cbc270e04c5a','a7975eea5d20f6cfb3709963e1e0647cf48f61fb17c4c72595903c9bee68da9d','{\"credential\": {\"label\": \"projects/488955723448\", \"status\": \"verified\", \"version\": 1, \"key_hint\": \"olDg\", \"provider\": \"gemini\", \"credential_id\": \"1\", \"provider_name\": \"Google Gemini\", \"last_verified_at\": \"2026-11-03T04:02:02Z\", \"provider_base_url\": \"https://generativelanguage.googleapis.com/v1beta\", \"provider_revision\": 3, \"provider_current_revision\": 3}, \"verified_capabilities\": [], \"billable_test_performed\": false}',200,'2026-11-03 04:02:02'),(2,'8ddab20a438dcf74d1c350834ebfcbe867c5bdab8654c1fbf097755f9953b3ba','e62be43f-ccdb-44d3-99de-be40ee594fbf','190dac79a0968584c774455d78f6c55973e0886e48f0102a9ab8ab8786fabf87','{\"label\": \"projects/488955723448\", \"status\": \"unverified\", \"version\": 1, \"key_hint\": \"olDg\", \"provider\": \"gemini\", \"credential_id\": \"1\", \"provider_name\": \"Google Gemini\", \"last_verified_at\": null, \"provider_base_url\": \"https://generativelanguage.googleapis.com/v1beta\", \"provider_revision\": 3, \"provider_current_revision\": 3}',201,'2026-11-03 04:01:45');
/*!40000 ALTER TABLE `ai_idempotency` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_providers`
--

DROP TABLE IF EXISTS `ai_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_providers` (
  `slug` varchar(30) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `name` varchar(100) NOT NULL,
  `adapter` varchar(30) NOT NULL,
  `base_url` varchar(1000) NOT NULL,
  `models` json NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `revision` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_providers`
--

LOCK TABLES `ai_providers` WRITE;
/*!40000 ALTER TABLE `ai_providers` DISABLE KEYS */;
INSERT INTO `ai_providers` VALUES ('gemini','Google Gemini','gemini','https://generativelanguage.googleapis.com/v1beta','{\"text\": [\"gemini-3.5-flash-lite\"], \"speech\": [], \"analytics\": [], \"assistant\": [], \"embedding\": []}',1,3,'2026-11-03 03:45:51','2026-11-03 03:46:16'),('openai','OpenAI','openai_compatible','https://api.openai.com/v1','{\"text\": [], \"speech\": [], \"analytics\": [], \"assistant\": [], \"embedding\": []}',0,1,'2026-10-26 04:58:51','2026-10-26 04:58:51');
/*!40000 ALTER TABLE `ai_providers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ai_usage_reservations`
--

DROP TABLE IF EXISTS `ai_usage_reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_usage_reservations` (
  `id` char(32) NOT NULL,
  `operation_key` varchar(128) NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  `actor_id` bigint unsigned NOT NULL,
  `billing_owner_id` bigint unsigned NOT NULL,
  `capability` varchar(30) NOT NULL,
  `binding_revision` int unsigned NOT NULL,
  `credential_id` bigint unsigned DEFAULT NULL,
  `credential_version` int unsigned DEFAULT NULL,
  `payer` varchar(20) NOT NULL,
  `provider` varchar(30) NOT NULL,
  `model` varchar(200) NOT NULL,
  `period` char(7) NOT NULL,
  `day_key` date NOT NULL,
  `reserved_micros` bigint unsigned NOT NULL,
  `actual_micros` bigint unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'reserved',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `provider_revision` int unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_id` (`course_id`,`operation_key`),
  KEY `course_id_2` (`course_id`,`capability`,`period`,`status`),
  KEY `course_id_3` (`course_id`,`actor_id`,`capability`,`day_key`),
  KEY `actor_id` (`actor_id`),
  KEY `billing_owner_id` (`billing_owner_id`),
  KEY `credential_id` (`credential_id`),
  CONSTRAINT `ai_usage_reservations_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `ai_usage_reservations_ibfk_2` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `ai_usage_reservations_ibfk_3` FOREIGN KEY (`billing_owner_id`) REFERENCES `users` (`id`),
  CONSTRAINT `ai_usage_reservations_ibfk_4` FOREIGN KEY (`credential_id`) REFERENCES `ai_credentials` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ai_usage_reservations`
--

LOCK TABLES `ai_usage_reservations` WRITE;
/*!40000 ALTER TABLE `ai_usage_reservations` DISABLE KEYS */;
/*!40000 ALTER TABLE `ai_usage_reservations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attempts`
--

DROP TABLE IF EXISTS `attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `lesson_id` bigint unsigned NOT NULL,
  `started_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `submitted_at` datetime DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `answers` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lesson_id` (`lesson_id`),
  KEY `user_id` (`user_id`,`lesson_id`),
  CONSTRAINT `attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `attempts_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attempts`
--

LOCK TABLES `attempts` WRITE;
/*!40000 ALTER TABLE `attempts` DISABLE KEYS */;
INSERT INTO `attempts` VALUES (1,4,8,'2026-10-09 03:13:41','2026-10-09 10:14:03',100.00,'{\"4\": 2}'),(2,4,6,'2026-10-09 03:46:32','2026-10-09 10:46:42',100.00,'{\"3\": 1}');
/*!40000 ALTER TABLE `attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `resource` varchar(200) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'course.saved','5','2026-09-07 06:53:37'),(2,1,'lesson.created','9','2026-09-07 06:57:05'),(3,1,'course.published','5','2026-09-07 06:57:58'),(4,1,'category.updated','5','2026-09-07 07:44:44'),(5,1,'category.updated','1','2026-09-07 07:44:56'),(6,1,'category.updated','8','2026-09-07 07:45:38'),(7,1,'category.updated','3','2026-09-07 07:45:42'),(8,1,'category.updated','2','2026-09-07 07:45:56'),(9,1,'category.created','9','2026-09-07 07:46:38'),(10,1,'category.created','10','2026-09-07 07:47:03'),(11,1,'category.created','11','2026-09-07 07:47:14'),(12,1,'category.created','12','2026-09-07 07:48:24'),(13,1,'category.created','13','2026-09-07 07:49:12'),(14,1,'category.created','14','2026-09-07 07:49:16'),(15,1,'category.created','15','2026-09-07 07:49:24'),(16,1,'category.created','16','2026-09-07 07:49:38'),(17,1,'category.created','17','2026-09-07 07:49:53'),(18,1,'category.created','18','2026-09-07 07:50:35'),(19,1,'category.created','19','2026-09-07 07:51:22'),(20,1,'category.created','20','2026-09-07 07:51:36'),(21,1,'course.saved','5','2026-09-07 07:51:51'),(22,1,'course.saved','4','2026-09-07 07:52:49'),(23,1,'course.saved','3','2026-09-07 07:53:11'),(24,1,'editor.image_uploaded','8c9df1881727899e038eab810db3d32008a26589.png','2026-09-07 15:31:14'),(25,1,'user.password_reset','2','2026-09-08 03:10:57'),(26,1,'user.updated','2','2026-09-08 03:10:57'),(27,1,'user.created','3','2026-09-08 03:14:41'),(28,1,'user.created','4','2026-09-08 03:17:03'),(29,1,'course.saved','5','2026-09-08 03:19:06'),(30,4,'course.enrolled','5','2026-09-08 03:20:10'),(31,4,'certificate.issued','34902b1553bfe441de7c7c08a7f320ab','2026-09-08 03:21:32'),(32,1,'certificate.design_saved','template','2026-09-08 04:22:47'),(33,1,'certificate.design_saved','template','2026-09-08 07:15:09'),(34,1,'certificate.signature_uploaded','1444187e33cf71f0faac2a4d0051d42f2f77e357.png','2026-09-08 07:15:09'),(35,1,'certificate.design_saved','template','2026-09-08 07:41:30'),(36,1,'certificate.emblem_uploaded','48d1004c5bbf0e87e3d8c03141c87b7f9261cabc.png','2026-09-08 07:41:30'),(37,1,'certificate.design_saved','template','2026-09-08 07:41:50'),(38,1,'settings.saved','system','2026-09-08 07:45:53'),(39,1,'banner.created','1','2026-10-09 02:08:19'),(40,1,'banner.updated','1','2026-10-09 02:15:19'),(41,1,'banner.created','2','2026-10-09 02:19:39'),(42,1,'user.password_reset','4','2026-10-09 02:39:59'),(43,1,'user.updated','4','2026-10-09 02:39:59'),(44,1,'user.password_reset','2','2026-10-09 03:11:04'),(45,1,'user.updated','2','2026-10-09 03:11:04'),(46,4,'course.enrolled','4','2026-10-09 03:13:12'),(47,4,'certificate.issued','f5c2fbadad270136947e48d216bba970','2026-10-09 03:14:28'),(48,1,'certificate.design_saved','template','2026-10-09 03:18:40'),(49,1,'certificate.design_saved','template','2026-10-09 03:19:30'),(50,1,'certificate.range_created','1','2026-10-09 03:39:31'),(51,NULL,'certificate.unused_range_adjusted','1:0101-0999->0001-9999:user_requested','2026-10-09 03:41:07'),(52,NULL,'certificate.range_activated','1:user_requested','2026-10-09 03:41:07'),(53,4,'course.enrolled','3','2026-10-09 03:46:23'),(54,4,'certificate.issued','853577bda2f160db6f113a539d3b6fcc','2026-10-09 03:46:52'),(55,2,'course.saved','1','2026-11-03 03:14:34'),(56,1,'ai.provider.created','gemini','2026-11-03 03:45:51'),(57,1,'ai.provider.updated','gemini','2026-11-03 03:46:05'),(58,1,'ai.provider.updated','gemini','2026-11-03 03:46:16'),(59,2,'ai.credential.created','1','2026-11-03 04:01:45'),(60,2,'ai.credential.verified','1','2026-11-03 04:02:02'),(61,2,'ai.billing.saved','1','2026-11-03 04:06:28'),(62,2,'ai.billing.saved','1','2026-11-03 04:06:34'),(63,2,'ai.billing.saved','1','2026-11-03 04:07:33'),(64,1,'ai.pricing.saved','text','2026-11-03 04:10:34');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `banners`
--

DROP TABLE IF EXISTS `banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `banners` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(240) NOT NULL,
  `image_path` varchar(80) NOT NULL,
  `link_url` varchar(2000) NOT NULL DEFAULT '',
  `sort_order` smallint unsigned NOT NULL DEFAULT '0',
  `active` tinyint unsigned NOT NULL DEFAULT '1',
  `width` int unsigned NOT NULL,
  `height` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `banner_display` (`active`,`sort_order`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `banners`
--

LOCK TABLES `banners` WRITE;
/*!40000 ALTER TABLE `banners` DISABLE KEYS */;
INSERT INTO `banners` VALUES (1,'ศูนย์การศึกษาอาชีพ','68a54b08ffe7a83289ddbdbbbc9db2d5662ad273.png','',0,1,1983,793,'2026-10-09 02:08:19'),(2,'credit bank','cffd19da10b3c632549cc685b5e1168ad6d9230e.png','',1,1,1983,793,'2026-10-09 02:19:39');
/*!40000 ALTER TABLE `banners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,'ช่างสถาปัตยกรรม','2026-09-07 07:14:29'),(2,'ช่างเชื่อมโลหะ','2026-09-07 07:14:29'),(3,'ช่างกลโรงงาน','2026-09-07 07:14:29'),(5,'ช่างก่อสร้าง','2026-09-07 07:14:29'),(8,'ช่างเทคนิคอุตสาหกรรม','2026-09-07 07:14:29'),(9,'ช่างยนต์','2026-09-07 07:46:38'),(10,'ช่างอิเล็กทรอนิกส์','2026-09-07 07:47:03'),(11,'ช่างเมคคาทรอนิกส์','2026-09-07 07:47:14'),(12,'ช่างไฟฟ้ากำลัง','2026-09-07 07:48:24'),(13,'การบัญชี','2026-09-07 07:49:12'),(14,'การตลาด','2026-09-07 07:49:16'),(15,'การจัดการดิจิทัล','2026-09-07 07:49:24'),(16,'คหกรรมศาสตร์','2026-09-07 07:49:38'),(17,'เทคโนโลยีสารสนเทศ','2026-09-07 07:49:53'),(18,'เทคโนโลยีธุรกิจดิจิทัล','2026-09-07 07:50:35'),(19,'เทคนิคพื้นฐาน','2026-09-07 07:51:22'),(20,'สามัญสัมพันธ์','2026-09-07 07:51:36');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificate_number_ranges`
--

DROP TABLE IF EXISTS `certificate_number_ranges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificate_number_ranges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `prefix` varchar(30) NOT NULL DEFAULT '',
  `year` smallint unsigned NOT NULL,
  `start_number` int unsigned NOT NULL,
  `end_number` int unsigned NOT NULL,
  `next_number` int unsigned NOT NULL,
  `digits` tinyint unsigned NOT NULL DEFAULT '4',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificate_number_ranges`
--

LOCK TABLES `certificate_number_ranges` WRITE;
/*!40000 ALTER TABLE `certificate_number_ranges` DISABLE KEYS */;
INSERT INTO `certificate_number_ranges` VALUES (1,'VEKEC',2569,1,9999,2,4,'2026-10-09 03:39:31');
/*!40000 ALTER TABLE `certificate_number_ranges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificate_numbering`
--

DROP TABLE IF EXISTS `certificate_numbering`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificate_numbering` (
  `id` tinyint unsigned NOT NULL,
  `range_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `range_id` (`range_id`),
  CONSTRAINT `certificate_numbering_ibfk_1` FOREIGN KEY (`range_id`) REFERENCES `certificate_number_ranges` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificate_numbering`
--

LOCK TABLES `certificate_numbering` WRITE;
/*!40000 ALTER TABLE `certificate_numbering` DISABLE KEYS */;
INSERT INTO `certificate_numbering` VALUES (1,1);
/*!40000 ALTER TABLE `certificate_numbering` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `certificates`
--

DROP TABLE IF EXISTS `certificates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `certificates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  `code` char(32) NOT NULL,
  `recipient_name` varchar(180) NOT NULL,
  `course_title` varchar(240) NOT NULL,
  `hours` smallint unsigned NOT NULL,
  `institution` varchar(240) NOT NULL,
  `signatory` varchar(180) NOT NULL,
  `issued_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `design_snapshot` json DEFAULT NULL,
  `registration_number` varchar(80) DEFAULT NULL,
  `number_range_id` bigint unsigned DEFAULT NULL,
  `serial_number` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  UNIQUE KEY `user_id` (`user_id`,`course_id`),
  UNIQUE KEY `certificate_registration_number` (`registration_number`),
  UNIQUE KEY `certificate_range_serial` (`number_range_id`,`serial_number`),
  KEY `course_id` (`course_id`),
  CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certificates`
--

LOCK TABLES `certificates` WRITE;
/*!40000 ALTER TABLE `certificates` DISABLE KEYS */;
INSERT INTO `certificates` VALUES (1,4,5,'34902b1553bfe441de7c7c08a7f320ab','ภัทรพล วิวัฒน์พงษ์','การสร้างเว็บไซต์',15,'วิทยาลัยเทคนิคกำแพงเพชร','ผู้อำนวยการวิทยาลัยเทคนิคกำแพงเพชร','2026-09-08 03:21:32',NULL,NULL,NULL,NULL),(2,4,4,'f5c2fbadad270136947e48d216bba970','ภัทรพล วิวัฒน์พงษ์','รู้เท่าทันดิจิทัลและการใช้อินเทอร์เน็ตอย่างปลอดภัย',2,'วิทยาลัยเทคนิคกำแพงเพชร','นายสถาพร โพธิ์หวี','2026-10-09 03:14:28','{\"intro\": \"ให้ไว้เพื่อแสดงว่า\", \"style\": \"classic\", \"accent\": \"#375a39\", \"heading\": \"เกียรติบัตร\", \"version\": 1, \"subtitle\": \"CERTIFICATE OF COMPLETION\", \"completion\": \"ได้ผ่านการเรียนรู้ผ่านระบบศูนย์การศึกาษาอาชีพ อาชีวศึกษาและการประเมินผลในหลักสูตร\", \"show_hours\": true, \"emblem_path\": \"48d1004c5bbf0e87e3d8c03141c87b7f9261cabc.png\", \"emblem_size\": 9.3, \"institution\": \"วิทยาลัยเทคนิคกำแพงเพชร\", \"director_name\": \"นายสถาพร โพธิ์หวี\", \"signature_path\": \"1444187e33cf71f0faac2a4d0051d42f2f77e357.png\", \"signature_width\": 30, \"director_position\": \"ผู้อำนวยการวิทยาลัยเทคนิคกำแพงเพชร\", \"signature_alignment\": \"center\"}',NULL,NULL,NULL),(3,4,3,'853577bda2f160db6f113a539d3b6fcc','ภัทรพล วิวัฒน์พงษ์','ทักษะการสื่อสารภาษาอังกฤษในงานบริการ',2,'วิทยาลัยเทคนิคกำแพงเพชร','นายสถาพร โพธิ์หวี','2026-10-09 03:46:52','{\"intro\": \"ให้ไว้เพื่อแสดงว่า\", \"style\": \"classic\", \"accent\": \"#375a39\", \"heading\": \"ประกาศนียบัตรฉบับนี้\", \"version\": 1, \"subtitle\": \"CERTIFICATE OF COMPLETION\", \"completion\": \"ได้ผ่านการเรียนรู้ผ่านระบบศูนย์การศึกาษาอาชีพ อาชีวศึกษาและการประเมินผลในหลักสูตร\", \"show_hours\": true, \"emblem_path\": \"48d1004c5bbf0e87e3d8c03141c87b7f9261cabc.png\", \"emblem_size\": 9.3, \"institution\": \"วิทยาลัยเทคนิคกำแพงเพชร\", \"director_name\": \"นายสถาพร โพธิ์หวี\", \"signature_path\": \"1444187e33cf71f0faac2a4d0051d42f2f77e357.png\", \"signature_width\": 30, \"director_position\": \"ผู้อำนวยการวิทยาลัยเทคนิคกำแพงเพชร\", \"signature_alignment\": \"center\"}','VEKEC 0001/2569',1,1);
/*!40000 ALTER TABLE `certificates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `courses`
--

DROP TABLE IF EXISTS `courses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `courses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `teacher_id` bigint unsigned NOT NULL,
  `title` varchar(240) NOT NULL,
  `code` varchar(40) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(100) NOT NULL,
  `level` enum('all','voc1','voc2','public') DEFAULT 'all',
  `hours` smallint unsigned NOT NULL DEFAULT '3',
  `status` enum('draft','published','archived') DEFAULT 'draft',
  `sequential` tinyint(1) NOT NULL DEFAULT '1',
  `color` varchar(20) NOT NULL DEFAULT 'teal',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `teacher_id` (`teacher_id`),
  KEY `status` (`status`,`category`),
  KEY `fk_courses_category` (`category`),
  CONSTRAINT `courses_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_courses_category` FOREIGN KEY (`category`) REFERENCES `categories` (`name`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `courses`
--

LOCK TABLES `courses` WRITE;
/*!40000 ALTER TABLE `courses` DISABLE KEYS */;
INSERT INTO `courses` VALUES (1,2,'พื้นฐานการเขียนเว็บไซต์ด้วย HTML และ CSS','DEMO-WEB','เริ่มต้นสร้างหน้าเว็บแรก เรียนรู้โครงสร้าง HTML การตกแต่งด้วย CSS และแนวคิดเว็บไซต์ที่รองรับทุกหน้าจอ (รายวิชาตัวอย่างสำหรับทดสอบระบบ)','เทคโนโลยีสารสนเทศ','all',3,'published',1,'teal','2026-09-07 04:39:14'),(2,2,'การวางแผนธุรกิจสำหรับผู้ประกอบการมือใหม่','DEMO-BIZ','เปลี่ยนไอเดียเป็นแผนธุรกิจ เรียนรู้ลูกค้าเป้าหมาย การตั้งราคา และการคำนวณต้นทุนเบื้องต้น (รายวิชาตัวอย่างสำหรับทดสอบระบบ)','ช่างเชื่อมโลหะ','all',3,'published',1,'orange','2026-09-07 04:39:14'),(3,2,'ทักษะการสื่อสารภาษาอังกฤษในงานบริการ','DEMO-ENG','ฝึกประโยคที่ใช้จริงในการต้อนรับลูกค้า การแนะนำบริการ และการสื่อสารอย่างสุภาพในที่ทำงาน (รายวิชาตัวอย่างสำหรับทดสอบระบบ)','สามัญสัมพันธ์','all',2,'published',1,'blue','2026-09-07 04:39:14'),(4,2,'รู้เท่าทันดิจิทัลและการใช้อินเทอร์เน็ตอย่างปลอดภัย','DEMO-DIG','เสริมทักษะจำเป็นในโลกออนไลน์ การดูแลข้อมูลส่วนตัว รู้จักข้อความหลอกลวง และการตรวจสอบแหล่งข้อมูล (รายวิชาตัวอย่างสำหรับทดสอบระบบ)','เทคโนโลยีธุรกิจดิจิทัล','all',2,'published',1,'purple','2026-09-07 04:39:14'),(5,1,'การสร้างเว็บไซต์','31900-0001','สร้างเว็บไซต์ด้วยโปรแกรมภาษาหรือโปรแกรมสำเร็จรูป ด้วยความรับผิดชอบ มีความคิดเชิงนวัตกรรมละเอียดรอบคอบและทำงานเป็นทีม','เทคโนโลยีสารสนเทศ','all',15,'published',1,'purple','2026-09-07 06:53:37');
/*!40000 ALTER TABLE `courses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `editor_image_uses`
--

DROP TABLE IF EXISTS `editor_image_uses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `editor_image_uses` (
  `path` varchar(80) NOT NULL,
  `lesson_id` bigint unsigned NOT NULL,
  `context` enum('body','question','explanation') NOT NULL,
  PRIMARY KEY (`path`,`lesson_id`,`context`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `editor_image_uses_ibfk_1` FOREIGN KEY (`path`) REFERENCES `editor_images` (`path`) ON DELETE CASCADE,
  CONSTRAINT `editor_image_uses_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `editor_image_uses`
--

LOCK TABLES `editor_image_uses` WRITE;
/*!40000 ALTER TABLE `editor_image_uses` DISABLE KEYS */;
/*!40000 ALTER TABLE `editor_image_uses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `editor_images`
--

DROP TABLE IF EXISTS `editor_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `editor_images` (
  `path` varchar(80) NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  `uploaded_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`path`),
  KEY `course_id` (`course_id`),
  KEY `uploaded_by` (`uploaded_by`),
  CONSTRAINT `editor_images_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `editor_images_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `editor_images`
--

LOCK TABLES `editor_images` WRITE;
/*!40000 ALTER TABLE `editor_images` DISABLE KEYS */;
INSERT INTO `editor_images` VALUES ('8c9df1881727899e038eab810db3d32008a26589.png',4,1,'2026-09-07 15:31:14');
/*!40000 ALTER TABLE `editor_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `enrollments`
--

DROP TABLE IF EXISTS `enrollments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `enrollments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `course_id` bigint unsigned NOT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `enrolled_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`course_id`),
  KEY `course_id` (`course_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`),
  CONSTRAINT `enrollments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `enrollments`
--

LOCK TABLES `enrollments` WRITE;
/*!40000 ALTER TABLE `enrollments` DISABLE KEYS */;
INSERT INTO `enrollments` VALUES (1,4,5,NULL,'2026-09-08 03:20:10'),(2,4,4,NULL,'2026-10-09 03:13:12'),(3,4,3,NULL,'2026-10-09 03:46:23');
/*!40000 ALTER TABLE `enrollments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lessons`
--

DROP TABLE IF EXISTS `lessons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lessons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `course_id` bigint unsigned NOT NULL,
  `title` varchar(240) NOT NULL,
  `kind` enum('article','video','assignment','quiz') NOT NULL,
  `body` mediumtext,
  `position` int unsigned NOT NULL,
  `media_path` varchar(255) DEFAULT NULL,
  `external_url` varchar(1000) DEFAULT NULL,
  `duration_seconds` int unsigned NOT NULL DEFAULT '0',
  `require_watch` tinyint(1) NOT NULL DEFAULT '0',
  `attachment_path` varchar(255) DEFAULT NULL,
  `pass_score` tinyint unsigned NOT NULL DEFAULT '60',
  `time_limit` smallint unsigned NOT NULL DEFAULT '30',
  `max_attempts` tinyint unsigned NOT NULL DEFAULT '3',
  `body_format` enum('text','html') NOT NULL DEFAULT 'text',
  PRIMARY KEY (`id`),
  UNIQUE KEY `course_id` (`course_id`,`position`),
  CONSTRAINT `lessons_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lessons`
--

LOCK TABLES `lessons` WRITE;
/*!40000 ALTER TABLE `lessons` DISABLE KEYS */;
INSERT INTO `lessons` VALUES (1,1,'โครงสร้างหน้าเว็บและการจัดรูปแบบ','article','HTML ใช้กำหนดโครงสร้างและความหมายของเนื้อหา เช่น หัวข้อ ย่อหน้า และลิงก์ ส่วน CSS ใช้กำหนดสี ขนาด และการจัดวาง\n\nโครงสร้างเริ่มต้นประกอบด้วย doctype, html, head และ body ภายใน head ควรระบุ charset=UTF-8 และ viewport เพื่อรองรับภาษาไทยและมือถือ\n\nใช้หัวข้อ h1 หนึ่งหัวข้อหลักต่อหน้าและเรียงระดับ h2, h3 ตามเนื้อหา ลิงก์ควรมีชื่อที่สื่อความหมาย รูปภาพควรมี alt\n\nกิจกรรม: ลองร่างหน้าแนะนำตัว ประกอบด้วยชื่อ ความสนใจ และทักษะที่อยากพัฒนา แล้วแยกสิ่งที่เป็นโครงสร้างกับรูปแบบการแสดงผล',1,NULL,NULL,0,0,NULL,60,30,3,'text'),(2,1,'แบบทดสอบทบทวนความเข้าใจ','quiz','เลือกคำตอบที่ถูกต้องที่สุด',2,NULL,NULL,0,0,NULL,60,30,3,'text'),(3,2,'รู้จักลูกค้าและคุณค่าของธุรกิจ','article','การเริ่มต้นธุรกิจควรระบุปัญหาของลูกค้าให้ชัดเจนก่อนเลือกสินค้า กลุ่มลูกค้าเป้าหมายควรเจาะจงพอที่จะสัมภาษณ์และทดสอบความต้องการได้\n\nคุณค่าที่เสนอคือลูกค้าจะได้รับประโยชน์อะไร เช่น ประหยัดเวลา เข้าถึงบริการง่ายขึ้น หรือใช้งานสะดวกขึ้น\n\nแยกต้นทุนคงที่ เช่น ค่าเช่า ออกจากต้นทุนผันแปร เช่น วัตถุดิบต่อชิ้น เพื่อประมาณกำไรขั้นต้น\n\nกิจกรรม: เลือกธุรกิจหนึ่งอย่าง เขียนลูกค้าเป้าหมาย ปัญหาที่แก้ให้ลูกค้า และต้นทุนสามรายการ',1,NULL,NULL,0,0,NULL,60,30,3,'text'),(4,2,'แบบทดสอบทบทวนความเข้าใจ','quiz','เลือกคำตอบที่ถูกต้องที่สุด',2,NULL,NULL,0,0,NULL,60,30,3,'text'),(5,3,'การทักทายและสอบถามความต้องการ','article','เริ่มสนทนาด้วยคำทักทายที่เหมาะสม เช่น Good morning. หรือ Welcome. จากนั้นสอบถามความต้องการด้วย How may I help you?\n\nเมื่อฟังไม่ชัด ให้ขอให้คู่สนทนาพูดอีกครั้งอย่างสุภาพ: Could you repeat that, please?\n\nการสรุปสิ่งที่ลูกค้าต้องการช่วยลดความเข้าใจผิด ใช้ Let me confirm ... แล้วทวนรายละเอียดสำคัญ\n\nกิจกรรม: เขียนบทสนทนาสั้น 4 ประโยค เริ่มจากการต้อนรับ สอบถามความต้องการ ทวนข้อมูล และกล่าวขอบคุณ',1,NULL,NULL,0,0,NULL,60,30,3,'text'),(6,3,'แบบทดสอบทบทวนความเข้าใจ','quiz','เลือกคำตอบที่ถูกต้องที่สุด',2,NULL,NULL,0,0,NULL,60,30,3,'text'),(7,4,'ปกป้องบัญชีและตรวจสอบข้อมูลออนไลน์','article','ใช้รหัสผ่านที่ยาวและไม่ซ้ำกันในแต่ละบริการ โปรแกรมจัดการรหัสผ่านช่วยสร้างและเก็บรหัสผ่านได้ เปิดใช้การยืนยันตัวตนหลายปัจจัยเมื่อบริการรองรับ\n\nก่อนกรอกข้อมูล ให้ตรวจสอบชื่อโดเมนอย่างรอบคอบ ข้อความเร่งให้โอนเงินหรือเปิดเผย OTP อาจเป็นข้อความหลอกลวง\n\nตรวจสอบข่าวจากแหล่งต้นทาง วันที่เผยแพร่ และหลักฐานประกอบก่อนส่งต่อ แยกความคิดเห็นออกจากข้อเท็จจริง\n\nกิจกรรม: เขียนรายการตรวจสอบ 3 ข้อที่คุณจะใช้ก่อนเปิดลิงก์จากผู้ส่งที่ไม่รู้จัก',1,NULL,NULL,0,0,NULL,60,30,3,'text'),(8,4,'แบบทดสอบทบทวนความเข้าใจ','quiz','เลือกคำตอบที่ถูกต้องที่สุด',2,NULL,NULL,0,0,NULL,60,30,3,'text'),(9,5,'การสร้างเว็บไซต์เบื้องต้น','video','ความรู้เบื้องต้นเกี่ยวกับการสร้างเว็บไซต์เบื้องต้น',1,NULL,'https://www.youtube.com/watch?v=jj_kb6ZiPS0&t=23s',4,1,NULL,60,30,3,'text');
/*!40000 ALTER TABLE `lessons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `progress`
--

DROP TABLE IF EXISTS `progress`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `progress` (
  `user_id` bigint unsigned NOT NULL,
  `lesson_id` bigint unsigned NOT NULL,
  `watched_seconds` decimal(10,2) NOT NULL DEFAULT '0.00',
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`,`lesson_id`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `progress_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `progress_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `progress`
--

LOCK TABLES `progress` WRITE;
/*!40000 ALTER TABLE `progress` DISABLE KEYS */;
INSERT INTO `progress` VALUES (4,5,0.00,'2026-10-09 10:46:28'),(4,6,0.00,'2026-10-09 10:46:42'),(4,7,0.00,'2026-10-09 10:13:23'),(4,8,0.00,'2026-10-09 10:14:03'),(4,9,3.86,'2026-09-08 10:21:20');
/*!40000 ALTER TABLE `progress` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `questions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `lesson_id` bigint unsigned NOT NULL,
  `prompt` text NOT NULL,
  `options` json NOT NULL,
  `answer` tinyint unsigned NOT NULL,
  `content_format` enum('text','html') NOT NULL DEFAULT 'text',
  `explanation` text,
  PRIMARY KEY (`id`),
  KEY `lesson_id` (`lesson_id`),
  CONSTRAINT `questions_ibfk_1` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `questions`
--

LOCK TABLES `questions` WRITE;
/*!40000 ALTER TABLE `questions` DISABLE KEYS */;
INSERT INTO `questions` VALUES (1,2,'ภาษาใดใช้กำหนดรูปแบบ สี และการจัดวางของหน้าเว็บ?','[\"HTML\", \"CSS\", \"SQL\", \"PHP\"]',1,'text',NULL),(2,4,'ข้อใดเป็นตัวอย่างต้นทุนผันแปรต่อชิ้น?','[\"ค่าเช่าร้านรายเดือน\", \"วัตถุดิบที่ใช้ผลิตสินค้าแต่ละชิ้น\", \"ค่าอุปกรณ์สำนักงานครั้งแรก\", \"ค่าป้ายร้าน\"]',1,'text',NULL),(3,6,'ประโยคใดเหมาะกับการขอให้ลูกค้าพูดซ้ำอย่างสุภาพ?','[\"Speak now.\", \"Could you repeat that, please?\", \"You are wrong.\", \"I do not care.\"]',1,'text',NULL),(4,8,'เมื่อมีคนโทรมาขอรหัส OTP ควรทำอย่างไร?','[\"บอกรหัสทันที\", \"ส่งรหัสทางข้อความ\", \"ไม่เปิดเผยรหัส และติดต่อหน่วยงานผ่านช่องทางทางการ\", \"ให้รหัสเฉพาะช่วงกลางวัน\"]',2,'text',NULL);
/*!40000 ALTER TABLE `questions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `rate_limits` (
  `bucket` char(64) NOT NULL,
  `hits` int unsigned NOT NULL DEFAULT '1',
  `expires_at` bigint unsigned NOT NULL,
  PRIMARY KEY (`bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limits`
--

LOCK TABLES `rate_limits` WRITE;
/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
INSERT INTO `rate_limits` VALUES ('28a24bac37b455d02c0e3b42606445b773f9146f23213016550ce00f814b3513',1,1788842101),('5eac5c2f85ce269baee3a8716fd93582f20f160ee82c306a54b072e9f570b7b8',1,1793682105),('6816c882cdda807ca7162be976292310f1e718298f6fab66322354f92e6e371c',1,1793679422),('90dbac27da615ea95024a3696179ea904246987d426c3f397cd55f1d10988115',3,1793678526);
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schema_migrations`
--

DROP TABLE IF EXISTS `schema_migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_migrations` (
  `version` varchar(100) NOT NULL,
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schema_migrations`
--

LOCK TABLES `schema_migrations` WRITE;
/*!40000 ALTER TABLE `schema_migrations` DISABLE KEYS */;
INSERT INTO `schema_migrations` VALUES ('001_managed_categories','2026-09-07 07:14:29'),('002_rich_text_images','2026-09-07 14:59:26'),('003_certificate_design','2026-09-08 04:11:50'),('004_home_banners','2026-10-09 01:54:45'),('005_certificate_numbers','2026-10-09 03:28:21'),('006_ai_byok','2026-10-26 04:58:51'),('007_admin_ai_providers','2026-10-26 04:58:51'),('008_ai_drafts','2026-11-03 03:25:10');
/*!40000 ALTER TABLE `schema_migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES ('ai_text_pricing','{\"revision\":1,\"rates\":{\"gemini\":{\"gemini-3.5-flash-lite\":{\"input\":0.3,\"output\":2.5}}}}'),('certificate_design','{\"version\":1,\"institution\":\"วิทยาลัยเทคนิคกำแพงเพชร\",\"heading\":\"ประกาศนียบัตรฉบับนี้\",\"subtitle\":\"CERTIFICATE OF COMPLETION\",\"intro\":\"ให้ไว้เพื่อแสดงว่า\",\"completion\":\"ได้ผ่านการเรียนรู้ผ่านระบบศูนย์การศึกาษาอาชีพ อาชีวศึกษาและการประเมินผลในหลักสูตร\",\"director_name\":\"นายสถาพร โพธิ์หวี\",\"director_position\":\"ผู้อำนวยการวิทยาลัยเทคนิคกำแพงเพชร\",\"emblem_size\":9.3,\"style\":\"classic\",\"accent\":\"#375a39\",\"signature_alignment\":\"center\",\"signature_width\":30,\"show_hours\":true,\"signature_path\":\"1444187e33cf71f0faac2a4d0051d42f2f77e357.png\",\"emblem_path\":\"48d1004c5bbf0e87e3d8c03141c87b7f9261cabc.png\"}'),('contact_email','vekec.kpt@gmail.com'),('institution','วิทยาลัยเทคนิคกำแพงเพชร'),('registration_open','1'),('signatory','นายสถาพร โพธิ์หวี');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `submissions`
--

DROP TABLE IF EXISTS `submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `submissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `lesson_id` bigint unsigned NOT NULL,
  `body` text NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `score` tinyint unsigned DEFAULT NULL,
  `feedback` text,
  `graded_by` bigint unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`lesson_id`),
  KEY `lesson_id` (`lesson_id`),
  KEY `graded_by` (`graded_by`),
  CONSTRAINT `submissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `submissions_ibfk_2` FOREIGN KEY (`lesson_id`) REFERENCES `lessons` (`id`),
  CONSTRAINT `submissions_ibfk_3` FOREIGN KEY (`graded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `submissions`
--

LOCK TABLES `submissions` WRITE;
/*!40000 ALTER TABLE `submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(180) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `role` enum('admin','teacher','student') NOT NULL DEFAULT 'student',
  `level` enum('voc1','voc2','public') NOT NULL DEFAULT 'public',
  `thaid_subject` char(64) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `thaid_subject` (`thaid_subject`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'ผู้ดูแลระบบ','admin@kpt.ac.th','$2y$10$RX4Yk6jkcU0O4uDzYdtzdOwmw4DY.fEZjVjhfkV1Kccexrb.UJySu','admin','public',NULL,1,'2026-09-07 04:39:14'),(2,'นายภีรภัท วิวัฒน์พงษ์','peerapat5004@gmail.com','$2y$10$EietLgjGL0STbFx3cZuvuuRLIQ.9K7ou4Qai0UlHKImPUqsLnk4Rq','teacher','public',NULL,1,'2026-09-07 04:39:14'),(3,'นางจิตรลดา วิวัฒน์พงษ์','tongjai@gmail.com','$2y$10$Z6ZCQ897Q6U3l/73aItg0evQGJU62dCQ4SW622VfOjub9I1bUHPUu','teacher','public',NULL,1,'2026-09-08 03:14:41'),(4,'ภัทรพล วิวัฒน์พงษ์','joemaster@gmail.com','$2y$10$u72FjETm5Ss98r3D8RLgWuEQubfujdd1iITpfLm9BX0l5/m11bGdO','student','public',NULL,1,'2026-09-08 03:17:03');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-16 20:55:17
