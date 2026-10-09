-- =====================================================================
-- HIMS Consolidated Database Dump
-- Source: TiDB Cloud Serverless (Cluster: 10394803852350064775)
-- Database: hims
-- =====================================================================

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

CREATE DATABASE IF NOT EXISTS `hims` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `hims`;

--
-- Table structure for table `account_activation_challenges`
--

DROP TABLE IF EXISTS `account_activation_challenges`;
CREATE TABLE `account_activation_challenges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `channel` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `otp_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `resend_available_at` timestamp NULL DEFAULT NULL,
  `failed_attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `verified_at` timestamp NULL DEFAULT NULL,
  `consumed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `account_activation_challenges_user_id_foreign` (`user_id`),
  UNIQUE KEY `account_activation_challenges_user_id_unique` (`user_id`),
  CONSTRAINT `account_activation_challenges_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `ai_chat_conversations`
--

DROP TABLE IF EXISTS `ai_chat_conversations`;
CREATE TABLE `ai_chat_conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `ai_chat_conversations_user_id_foreign` (`user_id`),
  KEY `ai_chat_conversations_user_id_updated_at_index` (`user_id`,`updated_at`),
  CONSTRAINT `ai_chat_conversations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `ai_chat_messages`
--

DROP TABLE IF EXISTS `ai_chat_messages`;
CREATE TABLE `ai_chat_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint unsigned NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attachment_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_path` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_extension` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachment_size` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_error` tinyint(1) NOT NULL DEFAULT '0',
  `source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_hint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `ai_chat_messages_conversation_id_foreign` (`conversation_id`),
  KEY `ai_chat_messages_conversation_id_created_at_index` (`conversation_id`,`created_at`),
  CONSTRAINT `ai_chat_messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `ai_chat_conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `approval_chains`
--

DROP TABLE IF EXISTS `approval_chains`;
CREATE TABLE `approval_chains` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chain_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` bigint unsigned NOT NULL,
  `total_commitment_amount` decimal(14,2) NOT NULL DEFAULT '0',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `approval_chains_chain_type_index` (`chain_type`),
  KEY `approval_chains_target_id_index` (`target_id`),
  KEY `approval_chains_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `approval_steps`
--

DROP TABLE IF EXISTS `approval_steps`;
CREATE TABLE `approval_steps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `approval_chain_id` bigint unsigned NOT NULL,
  `step_number` smallint unsigned NOT NULL,
  `required_role` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `approver_user_id` bigint unsigned DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `threshold_min` decimal(14,2) NOT NULL DEFAULT '0',
  `threshold_max` decimal(14,2) DEFAULT NULL,
  `decision_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `digital_signature_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `approval_steps_approval_chain_id_foreign` (`approval_chain_id`),
  KEY `approval_steps_approver_user_id_foreign` (`approver_user_id`),
  KEY `approval_steps_approval_chain_id_step_number_index` (`approval_chain_id`,`step_number`),
  CONSTRAINT `approval_steps_approval_chain_id_foreign` FOREIGN KEY (`approval_chain_id`) REFERENCES `approval_chains` (`id`) ON DELETE CASCADE,
  CONSTRAINT `approval_steps_approver_user_id_foreign` FOREIGN KEY (`approver_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `event_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `actor_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_employee_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_role` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_category` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_reference` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `business_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `outcome` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `correlation_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `operating_system` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_city` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_region` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_country` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_country_code` char(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_source` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_latitude` decimal(8,4) DEFAULT NULL,
  `location_longitude` decimal(9,4) DEFAULT NULL,
  `location_accuracy_meters` int unsigned DEFAULT NULL,
  `occurred_at_utc` datetime(6) DEFAULT NULL,
  `display_timezone` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `audit_logs_user_id_foreign` (`user_id`),
  KEY `audit_logs_target_type_target_id_index` (`target_type`,`target_id`),
  KEY `audit_logs_user_id_created_at_index` (`user_id`,`created_at`),
  KEY `audit_logs_action_created_at_index` (`action`,`created_at`),
  KEY `audit_logs_action_index` (`action`),
  KEY `audit_logs_actor_name_index` (`actor_name`),
  KEY `audit_logs_actor_employee_id_index` (`actor_employee_id`),
  KEY `audit_logs_target_name_index` (`target_name`),
  KEY `audit_logs_ip_address_index` (`ip_address`),
  UNIQUE KEY `audit_logs_event_id_unique` (`event_id`),
  KEY `audit_logs_event_category_index` (`event_category`),
  KEY `audit_logs_module_index` (`module`),
  KEY `audit_logs_actor_role_index` (`actor_role`),
  KEY `audit_logs_outcome_index` (`outcome`),
  KEY `audit_logs_source_index` (`source`),
  KEY `audit_logs_correlation_id_index` (`correlation_id`),
  KEY `audit_logs_target_reference_index` (`target_reference`),
  KEY `audit_logs_occurred_at_utc_index` (`occurred_at_utc`),
  CONSTRAINT `audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=30001;

--
-- Dumping data for table `audit_logs`
--

/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES
(1,'45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','1','REC-MVUM8MG6TP','REC-MVUM8MG6TP','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/landing.blade.php) (Incident ID: REC-MVUM8MG6TP, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-MVUM8MG6TP\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:40.901506','Asia/Manila','2026-10-03 04:48:40','2026-10-03 04:48:40'),
(2,'e63f9c4a-5770-4b98-8047-608ec9b20667',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','2','REC-PG9AS8JHAD','REC-PG9AS8JHAD','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-PG9AS8JHAD, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-PG9AS8JHAD\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:41.336143','Asia/Manila','2026-10-03 04:48:41','2026-10-03 04:48:41'),
(3,'dc549918-0d5e-494c-abb2-da5d063f15f7',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','3','REC-XYZYCECY9C','REC-XYZYCECY9C','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-XYZYCECY9C, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-XYZYCECY9C\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:41.461638','Asia/Manila','2026-10-03 04:48:41','2026-10-03 04:48:41'),
(4,'3713ba48-6e56-4124-8b74-75e1c02d959b',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','4','REC-DGTERE2MFJ','REC-DGTERE2MFJ','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-DGTERE2MFJ, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-DGTERE2MFJ\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:41.563099','Asia/Manila','2026-10-03 04:48:41','2026-10-03 04:48:41'),
(5,'d58df706-5e29-4b48-9ee5-142ba8742fbc',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','5','REC-FHJQVKV6TM','REC-FHJQVKV6TM','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-FHJQVKV6TM, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-FHJQVKV6TM\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:41.833536','Asia/Manila','2026-10-03 04:48:41','2026-10-03 04:48:41'),
(6,'22c18dd8-9dcc-4abc-9927-90e3fc355583',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','6','REC-2BFNAE0PEZ','REC-2BFNAE0PEZ','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-2BFNAE0PEZ, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-2BFNAE0PEZ\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:42.158585','Asia/Manila','2026-10-03 04:48:42','2026-10-03 04:48:42'),
(7,'58176073-e216-4584-870c-9926196293f7',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','7','REC-8URKDXXJHI','REC-8URKDXXJHI','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-8URKDXXJHI, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-8URKDXXJHI\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:42.392590','Asia/Manila','2026-10-03 04:48:42','2026-10-03 04:48:42'),
(8,'dff5796c-cb03-41a2-9822-d9e390032877',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','8','REC-NPIUPQ6COX','REC-NPIUPQ6COX','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-NPIUPQ6COX, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-NPIUPQ6COX\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:43.014739','Asia/Manila','2026-10-03 04:48:43','2026-10-03 04:48:43'),
(9,'65435e95-3615-4a69-9947-49697a51581a',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','9','REC-UO0S75LCHU','REC-UO0S75LCHU','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-UO0S75LCHU, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-UO0S75LCHU\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:43.523816','Asia/Manila','2026-10-03 04:48:43','2026-10-03 04:48:43'),
(10,'0845f57b-4adf-4911-8db6-444a6ef6c738',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','10','REC-IKVZT35DTY','REC-IKVZT35DTY','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-IKVZT35DTY, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-IKVZT35DTY\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:43.910376','Asia/Manila','2026-10-03 04:48:43','2026-10-03 04:48:43'),
(11,'b43e5eeb-9846-467f-80d9-60ee4cc5bede',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','11','REC-RJUFNQWG3H','REC-RJUFNQWG3H','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-RJUFNQWG3H, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-RJUFNQWG3H\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:44.152752','Asia/Manila','2026-10-03 04:48:44','2026-10-03 04:48:44'),
(12,'9ade396f-9ca4-43ba-ad22-e139ce6880eb',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','12','REC-4QSZUQ5PHB','REC-4QSZUQ5PHB','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-4QSZUQ5PHB, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-4QSZUQ5PHB\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:45.350267','Asia/Manila','2026-10-03 04:48:45','2026-10-03 04:48:45'),
(13,'1f133774-bb77-4830-b64e-26e384fc4a5c',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','13','REC-EGSAOWIKKC','REC-EGSAOWIKKC','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-EGSAOWIKKC, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-EGSAOWIKKC\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:46.575866','Asia/Manila','2026-10-03 04:48:46','2026-10-03 04:48:46'),
(14,'15ad4ceb-185c-4b93-9564-5b11aacf3392',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','14','REC-WRSJENK5WI','REC-WRSJENK5WI','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-WRSJENK5WI, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-WRSJENK5WI\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:47.125669','Asia/Manila','2026-10-03 04:48:47','2026-10-03 04:48:47'),
(15,'8d1b7fe6-4187-4239-9506-5e008261b739',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','15','REC-LDNKG1BDN3','REC-LDNKG1BDN3','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-LDNKG1BDN3, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-LDNKG1BDN3\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:47.391293','Asia/Manila','2026-10-03 04:48:47','2026-10-03 04:48:47'),
(16,'b7d6a384-8d7f-4c69-bd11-be113cb40a14',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','16','REC-UBLNDETOF9','REC-UBLNDETOF9','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-UBLNDETOF9, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-UBLNDETOF9\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:47.709957','Asia/Manila','2026-10-03 04:48:47','2026-10-03 04:48:47'),
(17,'877b9984-1fbc-40b7-9569-6bf1f0bc4966',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','17','REC-PSU3CORUUU','REC-PSU3CORUUU','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-PSU3CORUUU, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-PSU3CORUUU\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:48.223102','Asia/Manila','2026-10-03 04:48:48','2026-10-03 04:48:48'),
(18,'cdf3393b-9e9b-414c-bb36-9524ea896725',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','18','REC-9PJW7CRVCK','REC-9PJW7CRVCK','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-9PJW7CRVCK, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-9PJW7CRVCK\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:48.471367','Asia/Manila','2026-10-03 04:48:48','2026-10-03 04:48:48'),
(19,'281db0f8-a061-4dc6-86ad-5416ccca548d',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','19','REC-FDLQP0O6G4','REC-FDLQP0O6G4','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-FDLQP0O6G4, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-FDLQP0O6G4\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:48.615029','Asia/Manila','2026-10-03 04:48:48','2026-10-03 04:48:48'),
(20,'5a5dd29f-4a69-4cec-9d66-646a669714da',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','20','REC-5LD48UZZ0L','REC-5LD48UZZ0L','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-5LD48UZZ0L, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-5LD48UZZ0L\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:49.020118','Asia/Manila','2026-10-03 04:48:49','2026-10-03 04:48:49'),
(21,'4bd2aec7-5925-4fd0-8fe8-05a7918b2c1c',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','21','REC-YOI6KXXJIK','REC-YOI6KXXJIK','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-YOI6KXXJIK, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-YOI6KXXJIK\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:49.340866','Asia/Manila','2026-10-03 04:48:49','2026-10-03 04:48:49'),
(22,'1733269f-ccd7-4df8-99eb-57c69e29efca',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','22','REC-MHXCT1IKGL','REC-MHXCT1IKGL','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-MHXCT1IKGL, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-MHXCT1IKGL\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:50.433628','Asia/Manila','2026-10-03 04:48:50','2026-10-03 04:48:50'),
(23,'06ed9c7c-148f-4c52-a656-728b503fb8d8',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','23','REC-TWY7TVYJCG','REC-TWY7TVYJCG','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-TWY7TVYJCG, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-TWY7TVYJCG\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:50.597619','Asia/Manila','2026-10-03 04:48:50','2026-10-03 04:48:50'),
(24,'a82536ba-58cd-425b-902c-12a456e76e99',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','24','REC-WETVWZSZMQ','REC-WETVWZSZMQ','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-WETVWZSZMQ, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-WETVWZSZMQ\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:50.837498','Asia/Manila','2026-10-03 04:48:50','2026-10-03 04:48:50'),
(25,'4dcbe442-53b9-40e8-a47f-05b72582ff96',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','25','REC-MLUI27XNDW','REC-MLUI27XNDW','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-MLUI27XNDW, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-MLUI27XNDW\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:51.400065','Asia/Manila','2026-10-03 04:48:51','2026-10-03 04:48:51'),
(26,'0ef05671-f6d9-47f8-bfb9-3ec0c1d51417',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','26','REC-NSL3GOC6MK','REC-NSL3GOC6MK','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-NSL3GOC6MK, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-NSL3GOC6MK\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:52.242924','Asia/Manila','2026-10-03 04:48:52','2026-10-03 04:48:52'),
(27,'cdb8a69b-9ed8-4ff2-910c-c136ab0bf773',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','27','REC-CXU20UQITR','REC-CXU20UQITR','Operation [http_request] in [system] failed: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) (Incident ID: REC-CXU20UQITR, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-CXU20UQITR\", \"exception_class\": \"ViewException\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:52.819702','Asia/Manila','2026-10-03 04:48:52','2026-10-03 04:48:52'),
(28,'f6f83a0e-b70e-440f-af0f-0559df26c899',NULL,'System',NULL,NULL,'system_operation_failed','System','System Recovery','App\\Models\\SystemRecoveryRecord','28','REC-J39DJCY7X9','REC-J39DJCY7X9','Operation [http_request] in [system] failed: Uncaught Illuminate\\Foundation\\ViteManifestNotFoundException: Vite manifest not found at: /app/public/build/manifest.json in /app/vendor/laravel/framework/src/Illuminate/Foundation/Vite.php:946\nStack trace:\n#0 /app/vendor/laravel/framework/src/Illuminate/Foundation/Vite.php(384): Illuminate\\Foundation\\Vite->manifest(\'build\')\n#1 /app/storage/framework/views/605dde6d58430a1239183f717c11de3d.php(14): Illuminate\\Foundation\\Vite->__invoke(Object(Illuminate\\Support\\Collection))\n#2 /app/vendo... (Incident ID: REC-J39DJCY7X9, Strategy: unhandled_exception_intercept).',NULL,'failure','system','45f4dcf6-8a20-4304-9f2c-92a3200be3a9',NULL,'{\"error_id\": \"REC-J39DJCY7X9\", \"exception_class\": \"FatalError\", \"is_retryable\": false, \"module\": \"system\", \"operation\": \"http_request\", \"strategy\": \"unhandled_exception_intercept\"}','136.158.31.184','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','Desktop',NULL,'Windows 10','Brave 154.0',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-10-03 04:48:53.670120','Asia/Manila','2026-10-03 04:48:53','2026-10-03 04:48:53');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;

--
-- Table structure for table `barcode_aliases`
--

DROP TABLE IF EXISTS `barcode_aliases`;
CREATE TABLE `barcode_aliases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbology` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal',
  `target_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` bigint unsigned NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `barcode_aliases_target_type_target_id_index` (`target_type`,`target_id`),
  UNIQUE KEY `barcode_aliases_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`) /*T![clustered_index] CLUSTERED */,
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`) /*T![clustered_index] CLUSTERED */,
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `chain_of_custody_logs`
--

DROP TABLE IF EXISTS `chain_of_custody_logs`;
CREATE TABLE `chain_of_custody_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `custody_number` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trackable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trackable_id` bigint unsigned NOT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `releasing_user_id` bigint unsigned DEFAULT NULL,
  `releasing_party_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receiving_user_id` bigint unsigned DEFAULT NULL,
  `receiving_party_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transferred_at` datetime NOT NULL,
  `origin_location` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_location` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_condition` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'credential_auth',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `chain_of_custody_logs_releasing_user_id_foreign` (`releasing_user_id`),
  KEY `chain_of_custody_logs_receiving_user_id_foreign` (`receiving_user_id`),
  KEY `chain_of_custody_logs_trackable_type_trackable_id_index` (`trackable_type`,`trackable_id`),
  KEY `chain_of_custody_logs_event_type_transferred_at_index` (`event_type`,`transferred_at`),
  UNIQUE KEY `chain_of_custody_logs_custody_number_unique` (`custody_number`),
  CONSTRAINT `chain_of_custody_logs_releasing_user_id_foreign` FOREIGN KEY (`releasing_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chain_of_custody_logs_receiving_user_id_foreign` FOREIGN KEY (`receiving_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cost_center_budgets`
--

DROP TABLE IF EXISTS `cost_center_budgets`;
CREATE TABLE `cost_center_budgets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cost_center_id` bigint unsigned NOT NULL,
  `fiscal_year` smallint unsigned NOT NULL,
  `allocated_budget` decimal(14,2) NOT NULL DEFAULT '0',
  `soft_encumbered` decimal(14,2) NOT NULL DEFAULT '0',
  `hard_encumbered` decimal(14,2) NOT NULL DEFAULT '0',
  `spent_amount` decimal(14,2) NOT NULL DEFAULT '0',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `cost_center_budgets_cost_center_id_foreign` (`cost_center_id`),
  UNIQUE KEY `cost_center_budgets_cost_center_id_fiscal_year_unique` (`cost_center_id`,`fiscal_year`),
  CONSTRAINT `cost_center_budgets_cost_center_id_foreign` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cost_centers`
--

DROP TABLE IF EXISTS `cost_centers`;
CREATE TABLE `cost_centers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `department` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `manager_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `cost_centers_manager_id_foreign` (`manager_id`),
  KEY `cost_centers_department_is_active_index` (`department`,`is_active`),
  UNIQUE KEY `cost_centers_code_unique` (`code`),
  CONSTRAINT `cost_centers_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cycle_count_docs`
--

DROP TABLE IF EXISTS `cycle_count_docs`;
CREATE TABLE `cycle_count_docs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `document_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `count_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ABC',
  `scheduled_date` date NOT NULL,
  `assigned_counter_id` bigint unsigned NOT NULL,
  `storage_location_id` bigint unsigned DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generated',
  `snapshot_timestamp` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `approved_by_id` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `cycle_count_docs_assigned_counter_id_foreign` (`assigned_counter_id`),
  KEY `cycle_count_docs_storage_location_id_foreign` (`storage_location_id`),
  KEY `cycle_count_docs_approved_by_id_foreign` (`approved_by_id`),
  KEY `cycle_count_docs_status_scheduled_date_index` (`status`,`scheduled_date`),
  UNIQUE KEY `cycle_count_docs_document_number_unique` (`document_number`),
  CONSTRAINT `cycle_count_docs_assigned_counter_id_foreign` FOREIGN KEY (`assigned_counter_id`) REFERENCES `users` (`id`),
  CONSTRAINT `cycle_count_docs_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cycle_count_docs_approved_by_id_foreign` FOREIGN KEY (`approved_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `cycle_count_lines`
--

DROP TABLE IF EXISTS `cycle_count_lines`;
CREATE TABLE `cycle_count_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cycle_count_doc_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `storage_location_id` bigint unsigned NOT NULL,
  `item_batch_id` bigint unsigned DEFAULT NULL,
  `book_quantity_snapshot` int NOT NULL,
  `counted_quantity_blind` int DEFAULT NULL,
  `variance_quantity` int NOT NULL DEFAULT '0',
  `variance_value` decimal(12,2) NOT NULL DEFAULT '0',
  `recount_required` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `inventory_adjustment_id` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `cycle_count_lines_cycle_count_doc_id_foreign` (`cycle_count_doc_id`),
  KEY `cycle_count_lines_item_id_foreign` (`item_id`),
  KEY `cycle_count_lines_storage_location_id_foreign` (`storage_location_id`),
  KEY `cycle_count_lines_item_batch_id_foreign` (`item_batch_id`),
  KEY `cycle_count_lines_inventory_adjustment_id_foreign` (`inventory_adjustment_id`),
  KEY `cycle_count_lines_item_id_status_index` (`item_id`,`status`),
  CONSTRAINT `cycle_count_lines_cycle_count_doc_id_foreign` FOREIGN KEY (`cycle_count_doc_id`) REFERENCES `cycle_count_docs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cycle_count_lines_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `cycle_count_lines_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`),
  CONSTRAINT `cycle_count_lines_item_batch_id_foreign` FOREIGN KEY (`item_batch_id`) REFERENCES `item_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cycle_count_lines_inventory_adjustment_id_foreign` FOREIGN KEY (`inventory_adjustment_id`) REFERENCES `inventory_adjustments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `demand_plans`
--

DROP TABLE IF EXISTS `demand_plans`;
CREATE TABLE `demand_plans` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `plan_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `analysis_days` smallint unsigned NOT NULL DEFAULT '90',
  `forecast_days` smallint unsigned NOT NULL DEFAULT '30',
  `lead_time_days` smallint unsigned NOT NULL DEFAULT '7',
  `current_stock` int NOT NULL DEFAULT '0',
  `historical_usage` int NOT NULL DEFAULT '0',
  `average_daily_usage` decimal(10,3) NOT NULL DEFAULT '0',
  `upcoming_need` int NOT NULL DEFAULT '0',
  `reorder_point` int NOT NULL DEFAULT '0',
  `safety_stock` int unsigned NOT NULL DEFAULT '0',
  `suggested_order_quantity` int unsigned NOT NULL DEFAULT '0',
  `days_of_cover` smallint unsigned DEFAULT NULL,
  `trend` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'insufficient_data',
  `trigger_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `generated_by` bigint unsigned DEFAULT NULL,
  `generated_at` timestamp NULL DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `demand_plans_item_id_foreign` (`item_id`),
  UNIQUE KEY `demand_plans_plan_number_unique` (`plan_number`),
  KEY `demand_plans_generated_by_foreign` (`generated_by`),
  KEY `demand_plans_item_id_generated_at_index` (`item_id`,`generated_at`),
  CONSTRAINT `demand_plans_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `demand_plans_generated_by_foreign` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `device_login_cooldowns`
--

DROP TABLE IF EXISTS `device_login_cooldowns`;
CREATE TABLE `device_login_cooldowns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `login_approval_request_id` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blocked_until` timestamp NOT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `device_login_cooldowns_user_id_foreign` (`user_id`),
  KEY `device_login_cooldowns_ip_address_index` (`ip_address`),
  KEY `device_login_cooldowns_device_fingerprint_index` (`device_fingerprint`),
  KEY `device_login_cooldowns_blocked_until_index` (`blocked_until`),
  CONSTRAINT `device_login_cooldowns_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `dpri_reference_prices`
--

DROP TABLE IF EXISTS `dpri_reference_prices`;
CREATE TABLE `dpri_reference_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pndf_code` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `drug_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dosage_form_strength` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_of_measure` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ceiling_price` decimal(12,4) NOT NULL,
  `edition_year` smallint unsigned NOT NULL DEFAULT '2026',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  UNIQUE KEY `dpri_code_year_unique` (`pndf_code`,`edition_year`),
  KEY `dpri_reference_prices_pndf_code_index` (`pndf_code`),
  KEY `dpri_reference_prices_edition_year_index` (`edition_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `employee_id_sequences`
--

DROP TABLE IF EXISTS `employee_id_sequences`;
CREATE TABLE `employee_id_sequences` (
  `id` tinyint unsigned NOT NULL,
  `next_value` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employee_id_sequences`
--

/*!40000 ALTER TABLE `employee_id_sequences` DISABLE KEYS */;
INSERT INTO `employee_id_sequences` VALUES
(1,1);
/*!40000 ALTER TABLE `employee_id_sequences` ENABLE KEYS */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `goods_receipt_notes`
--

DROP TABLE IF EXISTS `goods_receipt_notes`;
CREATE TABLE `goods_receipt_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `grn_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dr_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sales_invoice_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_order_id` bigint unsigned DEFAULT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `carrier_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `waybill_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `packing_slip_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sscc` varchar(18) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_cold_chain` tinyint(1) NOT NULL DEFAULT '0',
  `temp_logger_id` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transit_temp_min` decimal(5,2) DEFAULT NULL,
  `transit_temp_max` decimal(5,2) DEFAULT NULL,
  `temp_excursion` tinyint(1) NOT NULL DEFAULT '0',
  `received_by_id` bigint unsigned NOT NULL,
  `receipt_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `delivery_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'complete',
  `received_at` datetime NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `quarantine_location_id` bigint unsigned DEFAULT NULL,
  `receipt_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `packing_slip_key` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `waybill_key` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `goods_receipt_notes_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `goods_receipt_notes_supplier_id_foreign` (`supplier_id`),
  KEY `goods_receipt_notes_received_by_id_foreign` (`received_by_id`),
  KEY `goods_receipt_notes_supplier_id_receipt_status_index` (`supplier_id`,`receipt_status`),
  KEY `goods_receipt_notes_received_at_index` (`received_at`),
  UNIQUE KEY `goods_receipt_notes_grn_number_unique` (`grn_number`),
  KEY `goods_receipt_notes_dr_number_index` (`dr_number`),
  KEY `goods_receipt_notes_sales_invoice_number_index` (`sales_invoice_number`),
  KEY `goods_receipt_notes_quarantine_location_id_foreign` (`quarantine_location_id`),
  UNIQUE KEY `goods_receipt_notes_receipt_key_unique` (`receipt_key`),
  UNIQUE KEY `goods_receipt_notes_packing_slip_key_unique` (`packing_slip_key`),
  UNIQUE KEY `goods_receipt_notes_waybill_key_unique` (`waybill_key`),
  CONSTRAINT `goods_receipt_notes_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `goods_receipt_notes_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `goods_receipt_notes_received_by_id_foreign` FOREIGN KEY (`received_by_id`) REFERENCES `users` (`id`),
  CONSTRAINT `goods_receipt_notes_quarantine_location_id_foreign` FOREIGN KEY (`quarantine_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `grn_line_items`
--

DROP TABLE IF EXISTS `grn_line_items`;
CREATE TABLE `grn_line_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `goods_receipt_note_id` bigint unsigned NOT NULL,
  `po_line_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned NOT NULL,
  `item_batch_id` bigint unsigned DEFAULT NULL,
  `purchase_unit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conversion_factor` decimal(12,4) NOT NULL DEFAULT '1',
  `ordered_quantity` int NOT NULL DEFAULT '0',
  `shipped_quantity` int NOT NULL DEFAULT '0',
  `received_quantity` int NOT NULL DEFAULT '0',
  `received_base_quantity` int NOT NULL DEFAULT '0',
  `accepted_quantity` int NOT NULL DEFAULT '0',
  `rejected_quantity` int NOT NULL DEFAULT '0',
  `quarantined_quantity` int NOT NULL DEFAULT '0',
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0',
  `destination_location_id` bigint unsigned DEFAULT NULL,
  `batch_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lot_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `manufactured_date` date DEFAULT NULL,
  `serial_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'received',
  `item_condition` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'good',
  `discrepancy_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discrepancy_action` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discrepancy_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `staging_location_id` bigint unsigned DEFAULT NULL,
  `pending_put_away_quantity` int NOT NULL DEFAULT '0',
  `returned_quantity` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `grn_line_items_goods_receipt_note_id_foreign` (`goods_receipt_note_id`),
  KEY `grn_line_items_po_line_id_foreign` (`po_line_id`),
  KEY `grn_line_items_item_id_foreign` (`item_id`),
  KEY `grn_line_items_item_batch_id_foreign` (`item_batch_id`),
  KEY `grn_line_items_destination_location_id_foreign` (`destination_location_id`),
  KEY `grn_line_items_item_id_status_index` (`item_id`,`status`),
  KEY `grn_line_items_staging_location_id_foreign` (`staging_location_id`),
  CONSTRAINT `grn_line_items_goods_receipt_note_id_foreign` FOREIGN KEY (`goods_receipt_note_id`) REFERENCES `goods_receipt_notes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `grn_line_items_po_line_id_foreign` FOREIGN KEY (`po_line_id`) REFERENCES `po_line_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `grn_line_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `grn_line_items_item_batch_id_foreign` FOREIGN KEY (`item_batch_id`) REFERENCES `item_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `grn_line_items_destination_location_id_foreign` FOREIGN KEY (`destination_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `grn_line_items_staging_location_id_foreign` FOREIGN KEY (`staging_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `inspection_acceptance_reports`
--

DROP TABLE IF EXISTS `inspection_acceptance_reports`;
CREATE TABLE `inspection_acceptance_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `iar_number` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `goods_receipt_note_id` bigint unsigned NOT NULL,
  `purchase_order_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `invoice_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `iar_date` date NOT NULL,
  `inspection_date` date DEFAULT NULL,
  `inspected_by_id` bigint unsigned DEFAULT NULL,
  `inspection_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_order',
  `inspection_findings` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `acceptance_date` date DEFAULT NULL,
  `accepted_by_id` bigint unsigned DEFAULT NULL,
  `delivery_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'complete',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_inspection',
  `days_delayed` int unsigned NOT NULL DEFAULT '0',
  `liquidated_damages_amount` decimal(14,2) NOT NULL DEFAULT '0',
  `coa_transmittal_deadline_at` date DEFAULT NULL,
  `coa_transmitted_at` date DEFAULT NULL,
  `coa_received_by` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `inspection_acceptance_reports_goods_receipt_note_id_foreign` (`goods_receipt_note_id`),
  KEY `inspection_acceptance_reports_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `inspection_acceptance_reports_supplier_id_foreign` (`supplier_id`),
  KEY `inspection_acceptance_reports_inspected_by_id_foreign` (`inspected_by_id`),
  KEY `inspection_acceptance_reports_accepted_by_id_foreign` (`accepted_by_id`),
  KEY `inspection_acceptance_reports_purchase_order_id_status_index` (`purchase_order_id`,`status`),
  KEY `inspection_acceptance_reports_supplier_id_status_index` (`supplier_id`,`status`),
  KEY `inspection_acceptance_reports_iar_date_index` (`iar_date`),
  UNIQUE KEY `inspection_acceptance_reports_iar_number_unique` (`iar_number`),
  UNIQUE KEY `inspection_acceptance_reports_goods_receipt_note_id_unique` (`goods_receipt_note_id`),
  CONSTRAINT `inspection_acceptance_reports_goods_receipt_note_id_foreign` FOREIGN KEY (`goods_receipt_note_id`) REFERENCES `goods_receipt_notes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inspection_acceptance_reports_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inspection_acceptance_reports_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inspection_acceptance_reports_inspected_by_id_foreign` FOREIGN KEY (`inspected_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inspection_acceptance_reports_accepted_by_id_foreign` FOREIGN KEY (`accepted_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `inventory_adjustments`
--

DROP TABLE IF EXISTS `inventory_adjustments`;
CREATE TABLE `inventory_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `adjustment_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `storage_location_id` bigint unsigned NOT NULL,
  `item_batch_id` bigint unsigned DEFAULT NULL,
  `current_quantity` int NOT NULL,
  `adjustment_quantity` int NOT NULL,
  `resulting_quantity` int NOT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0',
  `total_variance_value` decimal(12,2) NOT NULL DEFAULT '0',
  `adjustment_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `explanation` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_approval',
  `requested_by_id` bigint unsigned NOT NULL,
  `approved_by_id` bigint unsigned DEFAULT NULL,
  `second_approved_by_id` bigint unsigned DEFAULT NULL,
  `posted_at` datetime DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `inventory_adjustments_item_id_foreign` (`item_id`),
  KEY `inventory_adjustments_storage_location_id_foreign` (`storage_location_id`),
  KEY `inventory_adjustments_item_batch_id_foreign` (`item_batch_id`),
  KEY `inventory_adjustments_requested_by_id_foreign` (`requested_by_id`),
  KEY `inventory_adjustments_approved_by_id_foreign` (`approved_by_id`),
  KEY `inventory_adjustments_second_approved_by_id_foreign` (`second_approved_by_id`),
  KEY `inventory_adjustments_item_id_status_index` (`item_id`,`status`),
  KEY `inventory_adjustments_status_index` (`status`),
  UNIQUE KEY `inventory_adjustments_adjustment_number_unique` (`adjustment_number`),
  CONSTRAINT `inventory_adjustments_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `inventory_adjustments_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`),
  CONSTRAINT `inventory_adjustments_item_batch_id_foreign` FOREIGN KEY (`item_batch_id`) REFERENCES `item_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_adjustments_requested_by_id_foreign` FOREIGN KEY (`requested_by_id`) REFERENCES `users` (`id`),
  CONSTRAINT `inventory_adjustments_approved_by_id_foreign` FOREIGN KEY (`approved_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_adjustments_second_approved_by_id_foreign` FOREIGN KEY (`second_approved_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `inventory_items`
--

DROP TABLE IF EXISTS `inventory_items`;
CREATE TABLE `inventory_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generic_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dosage_form_strength` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pndf_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `regulatory_category` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'GENERAL_RX',
  `lasa_group_code` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sku` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barcode_value` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gtin` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_batch_tracked` tinyint(1) NOT NULL DEFAULT '1',
  `is_serial_tracked` tinyint(1) NOT NULL DEFAULT '0',
  `is_expiry_tracked` tinyint(1) NOT NULL DEFAULT '0',
  `storage_classification` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temperature_classification` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `storage_temp_min` decimal(4,2) DEFAULT NULL,
  `storage_temp_max` decimal(4,2) DEFAULT NULL,
  `fda_cpr_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_consignment` tinyint(1) NOT NULL DEFAULT '0',
  `pick_face_minimum` int unsigned NOT NULL DEFAULT '0',
  `pick_face_maximum` int unsigned NOT NULL DEFAULT '0',
  `abc_class` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'B',
  `costing_method` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'moving_average',
  `quantity_on_hand` int NOT NULL DEFAULT '0',
  `reserved_quantity` int NOT NULL DEFAULT '0',
  `reorder_level` int NOT NULL DEFAULT '0',
  `safety_stock` int unsigned NOT NULL DEFAULT '0',
  `reorder_point` int unsigned NOT NULL DEFAULT '0',
  `economic_order_quantity` int unsigned NOT NULL DEFAULT '0',
  `lead_time_days` int unsigned NOT NULL DEFAULT '7',
  `annual_demand` int unsigned NOT NULL DEFAULT '0',
  `expiry_alert_days` int unsigned NOT NULL DEFAULT '30',
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0',
  `total_value` decimal(12,2) NOT NULL DEFAULT '0',
  `supplier_id` bigint unsigned DEFAULT NULL,
  `default_location_id` bigint unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `archived_at` timestamp NULL DEFAULT NULL,
  `archived_by` bigint unsigned DEFAULT NULL,
  `archive_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `inventory_items_supplier_id_foreign` (`supplier_id`),
  UNIQUE KEY `inventory_items_sku_unique` (`sku`),
  KEY `inventory_items_category_id_foreign` (`category_id`),
  KEY `inventory_items_default_location_id_foreign` (`default_location_id`),
  UNIQUE KEY `inventory_items_barcode_value_unique` (`barcode_value`),
  UNIQUE KEY `inventory_items_gtin_unique` (`gtin`),
  KEY `inventory_items_regulatory_category_status_index` (`regulatory_category`,`status`),
  KEY `inventory_items_lasa_group_code_index` (`lasa_group_code`),
  KEY `inventory_items_is_consignment_status_index` (`is_consignment`,`status`),
  KEY `inventory_items_pndf_code_index` (`pndf_code`),
  KEY `inventory_items_archived_by_foreign` (`archived_by`),
  KEY `inventory_items_status_archived_at_index` (`status`,`archived_at`),
  CONSTRAINT `inventory_items_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `item_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_default_location_id_foreign` FOREIGN KEY (`default_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_items_archived_by_foreign` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `inventory_serials`
--

DROP TABLE IF EXISTS `inventory_serials`;
CREATE TABLE `inventory_serials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `item_batch_id` bigint unsigned DEFAULT NULL,
  `storage_location_id` bigint unsigned DEFAULT NULL,
  `serial_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'quarantined',
  `source_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `inventory_serials_item_id_foreign` (`item_id`),
  KEY `inventory_serials_item_batch_id_foreign` (`item_batch_id`),
  KEY `inventory_serials_storage_location_id_foreign` (`storage_location_id`),
  KEY `inventory_serials_source_type_source_id_index` (`source_type`,`source_id`),
  UNIQUE KEY `inventory_serial_item_unique` (`item_id`,`serial_number`),
  KEY `inventory_serials_storage_location_id_status_index` (`storage_location_id`,`status`),
  CONSTRAINT `inventory_serials_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `inventory_serials_item_batch_id_foreign` FOREIGN KEY (`item_batch_id`) REFERENCES `item_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_serials_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `inventory_shrinkage_reports`
--

DROP TABLE IF EXISTS `inventory_shrinkage_reports`;
CREATE TABLE `inventory_shrinkage_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kpi_process_review_id` bigint unsigned NOT NULL,
  `cycle_count_doc_id` bigint unsigned DEFAULT NULL,
  `storage_location_id` bigint unsigned NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `ledger_book_quantity` decimal(12,2) NOT NULL DEFAULT '0',
  `physical_counted_quantity` decimal(12,2) NOT NULL DEFAULT '0',
  `shrinkage_quantity` decimal(12,2) NOT NULL DEFAULT '0',
  `shrinkage_rate_pct` decimal(8,2) NOT NULL DEFAULT '0',
  `unit_cost` decimal(12,4) NOT NULL DEFAULT '0',
  `total_loss_value` decimal(14,4) NOT NULL DEFAULT '0',
  `shrinkage_reason` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_admin_escalation` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `inventory_shrinkage_reports_kpi_process_review_id_foreign` (`kpi_process_review_id`),
  KEY `inventory_shrinkage_reports_cycle_count_doc_id_foreign` (`cycle_count_doc_id`),
  KEY `inventory_shrinkage_reports_storage_location_id_foreign` (`storage_location_id`),
  KEY `inventory_shrinkage_reports_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `isr_review_loc_idx` (`kpi_process_review_id`,`storage_location_id`),
  CONSTRAINT `inventory_shrinkage_reports_kpi_process_review_id_foreign` FOREIGN KEY (`kpi_process_review_id`) REFERENCES `kpi_process_reviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_shrinkage_reports_cycle_count_doc_id_foreign` FOREIGN KEY (`cycle_count_doc_id`) REFERENCES `cycle_count_docs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `inventory_shrinkage_reports_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `inventory_shrinkage_reports_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `iot_telemetry_logs`
--

DROP TABLE IF EXISTS `iot_telemetry_logs`;
CREATE TABLE `iot_telemetry_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sensor_id` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `storage_location_id` bigint unsigned NOT NULL,
  `temperature_celsius` decimal(4,2) NOT NULL,
  `relative_humidity_pct` decimal(4,2) DEFAULT NULL,
  `excursion_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `resulting_event` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `iot_telemetry_logs_storage_location_id_foreign` (`storage_location_id`),
  KEY `iot_telemetry_logs_storage_location_id_recorded_at_index` (`storage_location_id`,`recorded_at`),
  KEY `iot_telemetry_logs_sensor_id_recorded_at_index` (`sensor_id`,`recorded_at`),
  KEY `iot_telemetry_logs_excursion_status_recorded_at_index` (`excursion_status`,`recorded_at`),
  CONSTRAINT `iot_telemetry_logs_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `item_batches`
--

DROP TABLE IF EXISTS `item_batches`;
CREATE TABLE `item_batches` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `batch_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lot_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manufactured_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `received_at` date DEFAULT NULL,
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0',
  `initial_quantity` int NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `item_batches_item_id_foreign` (`item_id`),
  UNIQUE KEY `item_batches_item_id_batch_number_unique` (`item_id`,`batch_number`),
  KEY `item_batches_expiry_date_index` (`expiry_date`),
  CONSTRAINT `item_batches_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `item_categories`
--

DROP TABLE IF EXISTS `item_categories`;
CREATE TABLE `item_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `item_categories_parent_id_foreign` (`parent_id`),
  UNIQUE KEY `item_categories_code_unique` (`code`),
  CONSTRAINT `item_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `item_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `item_stock_levels`
--

DROP TABLE IF EXISTS `item_stock_levels`;
CREATE TABLE `item_stock_levels` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `storage_location_id` bigint unsigned NOT NULL,
  `item_batch_id` bigint unsigned DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `reserved_quantity` int NOT NULL DEFAULT '0',
  `quarantined_quantity` int NOT NULL DEFAULT '0',
  `blocked_quantity` int NOT NULL DEFAULT '0',
  `in_transit_quantity` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `item_stock_levels_item_id_foreign` (`item_id`),
  KEY `item_stock_levels_storage_location_id_foreign` (`storage_location_id`),
  KEY `item_stock_levels_item_batch_id_foreign` (`item_batch_id`),
  UNIQUE KEY `item_stock_levels_unique` (`item_id`,`storage_location_id`,`item_batch_id`),
  CONSTRAINT `item_stock_levels_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `item_stock_levels_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `item_stock_levels_item_batch_id_foreign` FOREIGN KEY (`item_batch_id`) REFERENCES `item_batches` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `item_unit_conversions`
--

DROP TABLE IF EXISTS `item_unit_conversions`;
CREATE TABLE `item_unit_conversions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `purchase_unit` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conversion_factor` decimal(12,4) NOT NULL DEFAULT '1',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `item_unit_conversions_item_id_foreign` (`item_id`),
  UNIQUE KEY `item_unit_conv_unique` (`item_id`,`purchase_unit`),
  CONSTRAINT `item_unit_conversions_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `kpi_process_reviews`
--

DROP TABLE IF EXISTS `kpi_process_reviews`;
CREATE TABLE `kpi_process_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `review_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `evaluator_id` bigint unsigned NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `approved_by_id` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qualitative_context` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `executive_summary` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metrics_summary` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `kpi_process_reviews_evaluator_id_foreign` (`evaluator_id`),
  KEY `kpi_process_reviews_approved_by_id_foreign` (`approved_by_id`),
  UNIQUE KEY `kpi_process_reviews_review_number_unique` (`review_number`),
  KEY `kpi_process_reviews_status_index` (`status`),
  CONSTRAINT `kpi_process_reviews_approved_by_id_foreign` FOREIGN KEY (`approved_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `kpi_process_reviews_evaluator_id_foreign` FOREIGN KEY (`evaluator_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `login_approval_requests`
--

DROP TABLE IF EXISTS `login_approval_requests`;
CREATE TABLE `login_approval_requests` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `guard` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web',
  `remember` tinyint(1) NOT NULL DEFAULT '0',
  `challenge_token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `claim_token_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `trust_device_on_approval` tinyint(1) NOT NULL DEFAULT '0',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_fingerprint` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `browser` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_summary` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_at` timestamp NOT NULL,
  `expires_at` timestamp NOT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `responded_by_user_id` bigint unsigned DEFAULT NULL,
  `email_otp_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_otp_expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `login_approval_requests_user_id_foreign` (`user_id`),
  KEY `login_approval_requests_responded_by_user_id_foreign` (`responded_by_user_id`),
  KEY `login_approval_requests_challenge_token_hash_index` (`challenge_token_hash`),
  KEY `login_approval_requests_claim_token_hash_index` (`claim_token_hash`),
  KEY `login_approval_requests_status_index` (`status`),
  KEY `login_approval_requests_device_fingerprint_index` (`device_fingerprint`),
  KEY `login_approval_requests_expires_at_index` (`expires_at`),
  CONSTRAINT `login_approval_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `login_approval_requests_responded_by_user_id_foreign` FOREIGN KEY (`responded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `logistics_documents`
--

DROP TABLE IF EXISTS `logistics_documents`;
CREATE TABLE `logistics_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tracking_number` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `purchase_order_id` bigint unsigned DEFAULT NULL,
  `goods_receipt_note_id` bigint unsigned DEFAULT NULL,
  `inspection_acceptance_report_id` bigint unsigned DEFAULT NULL,
  `material_requisition_id` bigint unsigned DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size_bytes` bigint unsigned DEFAULT NULL,
  `disk` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `sha256_checksum` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_at` date DEFAULT NULL,
  `received_at` date DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `retention_class` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'financial_10yr',
  `retention_until` date DEFAULT NULL,
  `uploaded_by_id` bigint unsigned NOT NULL,
  `verified_by_id` bigint unsigned DEFAULT NULL,
  `version_number` smallint unsigned NOT NULL DEFAULT '1',
  `replaces_document_id` bigint unsigned DEFAULT NULL,
  `superseded_by_id` bigint unsigned DEFAULT NULL,
  `revision_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `verification_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `logistics_documents_supplier_id_foreign` (`supplier_id`),
  KEY `logistics_documents_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `logistics_documents_goods_receipt_note_id_foreign` (`goods_receipt_note_id`),
  KEY `logistics_documents_inspection_acceptance_report_id_foreign` (`inspection_acceptance_report_id`),
  KEY `logistics_documents_material_requisition_id_foreign` (`material_requisition_id`),
  KEY `logistics_documents_uploaded_by_id_foreign` (`uploaded_by_id`),
  KEY `logistics_documents_verified_by_id_foreign` (`verified_by_id`),
  KEY `logistics_documents_replaces_document_id_foreign` (`replaces_document_id`),
  KEY `logistics_documents_superseded_by_id_foreign` (`superseded_by_id`),
  KEY `logistics_documents_document_type_status_index` (`document_type`,`status`),
  KEY `logistics_documents_purchase_order_id_document_type_index` (`purchase_order_id`,`document_type`),
  KEY `logistics_documents_supplier_id_document_type_index` (`supplier_id`,`document_type`),
  UNIQUE KEY `logistics_documents_tracking_number_unique` (`tracking_number`),
  CONSTRAINT `logistics_documents_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `logistics_documents_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `logistics_documents_goods_receipt_note_id_foreign` FOREIGN KEY (`goods_receipt_note_id`) REFERENCES `goods_receipt_notes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `logistics_documents_inspection_acceptance_report_id_foreign` FOREIGN KEY (`inspection_acceptance_report_id`) REFERENCES `inspection_acceptance_reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `logistics_documents_material_requisition_id_foreign` FOREIGN KEY (`material_requisition_id`) REFERENCES `material_requisitions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `logistics_documents_uploaded_by_id_foreign` FOREIGN KEY (`uploaded_by_id`) REFERENCES `users` (`id`),
  CONSTRAINT `logistics_documents_verified_by_id_foreign` FOREIGN KEY (`verified_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `logistics_documents_replaces_document_id_foreign` FOREIGN KEY (`replaces_document_id`) REFERENCES `logistics_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `logistics_documents_superseded_by_id_foreign` FOREIGN KEY (`superseded_by_id`) REFERENCES `logistics_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `material_requisition_lines`
--

DROP TABLE IF EXISTS `material_requisition_lines`;
CREATE TABLE `material_requisition_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `material_requisition_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `requested_quantity` int NOT NULL DEFAULT '0',
  `reserved_quantity` int NOT NULL DEFAULT '0',
  `issued_quantity` int NOT NULL DEFAULT '0',
  `allocation_strategy` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'FEFO',
  `item_batch_id` bigint unsigned DEFAULT NULL,
  `storage_location_id` bigint unsigned DEFAULT NULL,
  `line_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `material_requisition_lines_material_requisition_id_foreign` (`material_requisition_id`),
  KEY `material_requisition_lines_item_id_foreign` (`item_id`),
  KEY `material_requisition_lines_item_batch_id_foreign` (`item_batch_id`),
  KEY `material_requisition_lines_storage_location_id_foreign` (`storage_location_id`),
  KEY `material_requisition_lines_item_id_line_status_index` (`item_id`,`line_status`),
  CONSTRAINT `material_requisition_lines_material_requisition_id_foreign` FOREIGN KEY (`material_requisition_id`) REFERENCES `material_requisitions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `material_requisition_lines_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `material_requisition_lines_item_batch_id_foreign` FOREIGN KEY (`item_batch_id`) REFERENCES `item_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requisition_lines_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `material_requisitions`
--

DROP TABLE IF EXISTS `material_requisitions`;
CREATE TABLE `material_requisitions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `requisition_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `requesting_user_id` bigint unsigned NOT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cost_center_id` bigint unsigned DEFAULT NULL,
  `required_date` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `urgency` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'routine',
  `justification` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approved_by_id` bigint unsigned DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `issued_by_id` bigint unsigned DEFAULT NULL,
  `issued_at` datetime DEFAULT NULL,
  `acknowledged_by_id` bigint unsigned DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `material_requisitions_requesting_user_id_foreign` (`requesting_user_id`),
  KEY `material_requisitions_cost_center_id_foreign` (`cost_center_id`),
  KEY `material_requisitions_approved_by_id_foreign` (`approved_by_id`),
  KEY `material_requisitions_issued_by_id_foreign` (`issued_by_id`),
  KEY `material_requisitions_acknowledged_by_id_foreign` (`acknowledged_by_id`),
  KEY `material_requisitions_department_status_index` (`department`,`status`),
  KEY `material_requisitions_required_date_index` (`required_date`),
  UNIQUE KEY `material_requisitions_requisition_number_unique` (`requisition_number`),
  CONSTRAINT `material_requisitions_requesting_user_id_foreign` FOREIGN KEY (`requesting_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `material_requisitions_cost_center_id_foreign` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requisitions_approved_by_id_foreign` FOREIGN KEY (`approved_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requisitions_issued_by_id_foreign` FOREIGN KEY (`issued_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `material_requisitions_acknowledged_by_id_foreign` FOREIGN KEY (`acknowledged_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=96589;

--
-- Dumping data for table `migrations`
--

/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES
(1,'0001_01_01_000000_create_users_table',1),
(2,'0001_01_01_000001_create_cache_table',1),
(3,'0001_01_01_000002_create_jobs_table',1),
(4,'2026_07_16_033557_create_suppliers_table',1),
(5,'2026_07_17_000001_create_inventory_items_table',1),
(6,'2026_07_17_000002_create_storage_locations_table',1),
(7,'2026_07_17_000003_create_stock_movements_table',1),
(8,'2026_07_18_000001_create_purchase_orders_table',1),
(9,'2026_07_18_000002_create_procurement_requests_table',1),
(10,'2026_07_18_000003_create_supplier_quotes_table',1),
(11,'2026_07_18_000004_create_demand_plans_table',1),
(12,'2026_07_18_000005_add_missing_inventory_value_columns',1),
(13,'2026_08_03_073638_create_personal_access_tokens_table',1),
(14,'2026_08_06_100001_create_item_categories_table',1),
(15,'2026_08_06_100002_create_item_batches_table',1),
(16,'2026_08_06_100003_reshape_inventory_items_for_batch_tracking',1),
(17,'2026_08_06_100004_create_item_stock_levels_table',1),
(18,'2026_08_06_100005_extend_stock_movements_for_batches',1),
(19,'2026_08_06_100006_extend_storage_locations_hierarchy',1),
(20,'2026_08_06_100007_create_stock_alerts_table',1),
(21,'2026_08_08_100001_add_role_and_status_to_users_table',1),
(22,'2026_08_08_100002_extend_demand_plans_for_forecasting',1),
(23,'2026_08_27_000001_add_name_parts_to_users_table',1),
(24,'2026_08_27_000002_create_employee_id_sequences_table',1),
(25,'2026_08_27_000003_create_audit_logs_table',1),
(26,'2026_09_02_000001_add_is_protected_to_users_table',1),
(27,'2026_09_05_000002_add_autocomplete_indexes_to_audit_logs_table',1),
(28,'2026_09_06_000001_add_mfa_enabled_to_users_table',1),
(29,'2026_09_06_000002_add_password_changed_at_to_users_table',1),
(30,'2026_09_06_000003_create_password_histories_table',1),
(31,'2026_09_07_000001_add_login_lockout_fields_to_users_table',1),
(32,'2026_09_07_000002_add_last_failed_login_at_to_users_table',1),
(33,'2026_09_07_000003_add_authenticator_mfa_fields_to_users_table',1),
(34,'2026_09_10_000001_build_supplier_management_foundation',1),
(35,'2026_09_10_000002_create_procurement_and_sourcing_tables',1),
(36,'2026_09_10_000003_make_legacy_procurement_columns_nullable',1),
(37,'2026_09_10_000004_add_procurement_method_to_purchase_requests_table',1),
(38,'2026_09_10_100001_extend_item_stock_levels_and_inventory_items',1),
(39,'2026_09_10_100002_create_goods_receipts_and_inspections_tables',1),
(40,'2026_09_10_100003_create_material_requisitions_tables',1),
(41,'2026_09_10_100004_create_stock_transfers_and_adjustments_tables',1),
(42,'2026_09_10_100005_create_cycle_counts_tables',1),
(43,'2026_09_10_100006_create_smart_warehousing_foundation',1),
(44,'2026_09_10_200001_create_smart_warehousing_advanced_compliance',1),
(45,'2026_09_11_000001_create_document_tracking_and_logistics_records',1),
(46,'2026_09_11_000002_add_created_by_user_id_to_purchase_orders',1),
(47,'2026_09_12_000001_create_evidence_based_process_review_tables',1),
(48,'2026_09_12_000002_add_pndf_code_to_inventory_items',1),
(49,'2026_09_12_000003_extend_audit_logs_with_event_context',1),
(50,'2026_09_12_000004_add_session_timeout_reminder_to_users_table',1),
(51,'2026_09_12_000005_add_device_and_location_context_to_audit_logs',1),
(52,'2026_09_12_000006_add_browser_location_to_audit_logs',1),
(53,'2026_09_12_000007_create_system_recovery_records_table',1),
(54,'2026_09_12_000008_create_notifications_table',1),
(55,'2026_09_12_000009_add_avatar_path_to_users_table',1),
(56,'2026_09_13_000001_create_ai_chat_tables',1),
(57,'2026_09_14_000001_add_logo_path_to_suppliers_table',1),
(58,'2026_09_15_000001_align_purchase_order_status_default_with_enum',1),
(59,'2026_09_15_000002_backfill_legacy_purchase_order_status_values',1),
(60,'2026_09_16_000001_normalise_inventory_item_status_vocabulary',1),
(61,'2026_09_17_000001_add_traceability_columns_to_system_recovery_records_table',1),
(62,'2026_09_17_000002_create_system_recovery_attempts_table',1),
(63,'2026_09_17_000003_map_legacy_recovery_statuses',1),
(64,'2026_09_17_000004_align_recovery_status_default_with_enum',1),
(65,'2026_09_20_000001_create_privacy_requests_table',1),
(66,'2026_09_20_000002_create_security_incidents_table',1),
(67,'2026_09_20_100001_create_item_unit_conversions_and_extend_procurement_quantities',1),
(68,'2026_09_21_120000_add_archive_columns_to_master_records_tables',1),
(69,'2026_09_23_000001_add_sms_mfa_to_users_table',1),
(70,'2026_09_24_000001_add_fulfillment_fields_to_privacy_requests_table',1),
(71,'2026_09_24_000002_add_operational_read_indexes',1),
(72,'2026_09_24_100001_add_discrepancy_and_destination_to_grn_lines',1),
(73,'2026_09_24_100002_unify_receiving_inventory_state',1),
(74,'2026_09_26_000001_add_in_transit_purpose_to_storage_locations',1),
(75,'2026_09_26_000002_remove_default_shipment_destination',1),
(76,'2026_09_26_000003_remove_generic_procurement_uom_defaults',1),
(77,'2026_09_26_100001_create_device_security_and_approval_tables',1),
(78,'2026_09_27_000001_add_owner_decision_to_login_approval_requests',1),
(79,'2026_09_27_000002_encrypt_sms_mfa_phone',1),
(80,'2026_09_27_000003_prepare_sensitive_field_encryption',1),
(81,'2026_09_27_000004_encrypt_sensitive_fields',1),
(82,'2026_09_27_100001_create_user_consents_table',1),
(83,'2026_09_27_100002_add_pickup_locations_to_shipments',1),
(84,'2026_09_28_000001_create_scheduled_reports_tables',1),
(85,'2026_09_29_000001_scope_password_history_fingerprints_to_users',1),
(86,'2026_09_30_000001_protect_process_review_history_from_user_deletion',1),
(87,'2026_10_01_000001_create_account_activation_challenges',1),
(88,'2026_10_02_000001_create_user_avatars_table',1),
(89,'2026_10_02_000002_add_activation_cancellation_to_users_table',1),
(90,'2026_10_06_000001_add_supplier_portal_workflow',1),
(91,'2026_10_07_000001_assign_supplier_user_identifiers',1),
(92,'2026_10_09_000001_add_company_profile_workflow_to_suppliers',1),
(93,'2026_10_09_000002_create_supplier_invitations_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`),
  KEY `notifications_recipient_unread_index` (`notifiable_type`,`notifiable_id`,`read_at`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `password_histories`
--

DROP TABLE IF EXISTS `password_histories`;
CREATE TABLE `password_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_fingerprint` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `used_at` timestamp NOT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `password_histories_user_id_foreign` (`user_id`),
  KEY `password_histories_used_at_index` (`used_at`),
  UNIQUE KEY `password_histories_user_password_fingerprint_unique` (`user_id`,`password_fingerprint`),
  CONSTRAINT `password_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`) /*T![clustered_index] CLUSTERED */
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `pdea_dangerous_drugs_register`
--

DROP TABLE IF EXISTS `pdea_dangerous_drugs_register`;
CREATE TABLE `pdea_dangerous_drugs_register` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `register_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `item_batch_id` bigint unsigned DEFAULT NULL,
  `movement_id` bigint unsigned DEFAULT NULL,
  `storage_location_id` bigint unsigned DEFAULT NULL,
  `pdea_spf_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `physician_s2_license` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prescriber_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `patient_encounter_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int unsigned NOT NULL,
  `running_balance` int unsigned NOT NULL,
  `custodian_id` bigint unsigned NOT NULL,
  `witness_pharmacist_id` bigint unsigned NOT NULL,
  `witness_authenticated_at` datetime NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `pdea_dangerous_drugs_register_item_id_foreign` (`item_id`),
  KEY `pdea_dangerous_drugs_register_item_batch_id_foreign` (`item_batch_id`),
  KEY `pdea_dangerous_drugs_register_movement_id_foreign` (`movement_id`),
  KEY `pdea_dangerous_drugs_register_storage_location_id_foreign` (`storage_location_id`),
  KEY `pdea_dangerous_drugs_register_custodian_id_foreign` (`custodian_id`),
  KEY `pdea_dangerous_drugs_register_witness_pharmacist_id_foreign` (`witness_pharmacist_id`),
  KEY `pdea_dangerous_drugs_register_item_id_recorded_at_index` (`item_id`,`recorded_at`),
  KEY `pdea_dangerous_drugs_register_pdea_spf_number_index` (`pdea_spf_number`),
  KEY `pdea_dangerous_drugs_register_physician_s2_license_index` (`physician_s2_license`),
  UNIQUE KEY `pdea_dangerous_drugs_register_register_number_unique` (`register_number`),
  CONSTRAINT `pdea_dangerous_drugs_register_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `pdea_dangerous_drugs_register_item_batch_id_foreign` FOREIGN KEY (`item_batch_id`) REFERENCES `item_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pdea_dangerous_drugs_register_movement_id_foreign` FOREIGN KEY (`movement_id`) REFERENCES `stock_movements` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pdea_dangerous_drugs_register_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pdea_dangerous_drugs_register_custodian_id_foreign` FOREIGN KEY (`custodian_id`) REFERENCES `users` (`id`),
  CONSTRAINT `pdea_dangerous_drugs_register_witness_pharmacist_id_foreign` FOREIGN KEY (`witness_pharmacist_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `po_line_items`
--

DROP TABLE IF EXISTS `po_line_items`;
CREATE TABLE `po_line_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint unsigned NOT NULL,
  `pr_line_id` bigint unsigned DEFAULT NULL,
  `quote_line_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned NOT NULL,
  `line_number` int unsigned NOT NULL,
  `purchase_unit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conversion_factor` decimal(12,4) NOT NULL DEFAULT '1',
  `ordered_quantity` int NOT NULL DEFAULT '1',
  `received_quantity` int NOT NULL DEFAULT '0',
  `invoiced_quantity` int NOT NULL DEFAULT '0',
  `unit_price` decimal(12,2) NOT NULL DEFAULT '0',
  `total_line_amount` decimal(14,2) NOT NULL DEFAULT '0',
  `line_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `accepted_quantity` int NOT NULL DEFAULT '0',
  `rejected_quantity` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `po_line_items_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `po_line_items_pr_line_id_foreign` (`pr_line_id`),
  KEY `po_line_items_quote_line_id_foreign` (`quote_line_id`),
  KEY `po_line_items_item_id_foreign` (`item_id`),
  UNIQUE KEY `po_line_items_purchase_order_id_line_number_unique` (`purchase_order_id`,`line_number`),
  CONSTRAINT `po_line_items_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `po_line_items_pr_line_id_foreign` FOREIGN KEY (`pr_line_id`) REFERENCES `pr_line_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `po_line_items_quote_line_id_foreign` FOREIGN KEY (`quote_line_id`) REFERENCES `quote_line_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `po_line_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `po_revisions`
--

DROP TABLE IF EXISTS `po_revisions`;
CREATE TABLE `po_revisions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint unsigned NOT NULL,
  `revision_number` smallint unsigned NOT NULL,
  `change_order_code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `justification` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `delta_amount` decimal(14,2) NOT NULL DEFAULT '0',
  `variance_percentage` decimal(5,2) NOT NULL DEFAULT '0',
  `original_snapshot` json NOT NULL,
  `proposed_snapshot` json NOT NULL,
  `requires_doa_reapproval` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_by` bigint unsigned NOT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `po_revisions_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `po_revisions_created_by_foreign` (`created_by`),
  KEY `po_revisions_approved_by_foreign` (`approved_by`),
  CONSTRAINT `po_revisions_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `po_revisions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `po_revisions_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `pr_line_items`
--

DROP TABLE IF EXISTS `pr_line_items`;
CREATE TABLE `pr_line_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_request_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `line_number` int unsigned NOT NULL,
  `gl_account_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `uom` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estimated_unit_price` decimal(12,2) NOT NULL DEFAULT '0',
  `estimated_total_price` decimal(12,2) NOT NULL DEFAULT '0',
  `need_by_date` date DEFAULT NULL,
  `is_contracted_catalog` tinyint(1) NOT NULL DEFAULT '0',
  `contract_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `pr_line_items_purchase_request_id_foreign` (`purchase_request_id`),
  KEY `pr_line_items_item_id_foreign` (`item_id`),
  KEY `pr_line_items_contract_id_foreign` (`contract_id`),
  UNIQUE KEY `pr_line_items_purchase_request_id_line_number_unique` (`purchase_request_id`,`line_number`),
  CONSTRAINT `pr_line_items_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pr_line_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `pr_line_items_contract_id_foreign` FOREIGN KEY (`contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `privacy_requests`
--

DROP TABLE IF EXISTS `privacy_requests`;
CREATE TABLE `privacy_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ticket_number` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `requestor_name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `requestor_email` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `request_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `details` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `handled_by_user_id` bigint unsigned DEFAULT NULL,
  `handled_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approved_by_user_id` bigint unsigned DEFAULT NULL,
  `target_completion_date` timestamp NULL DEFAULT NULL,
  `processing_started_at` timestamp NULL DEFAULT NULL,
  `fulfilled_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `export_payload` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_size_bytes` bigint unsigned DEFAULT NULL,
  `package_manifest` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `package_expires_at` timestamp NULL DEFAULT NULL,
  `download_count` int unsigned NOT NULL DEFAULT '0',
  `last_downloaded_at` timestamp NULL DEFAULT NULL,
  `exclusions_summary` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `privacy_requests_user_id_foreign` (`user_id`),
  KEY `privacy_requests_handled_by_user_id_foreign` (`handled_by_user_id`),
  KEY `privacy_requests_status_index` (`status`),
  KEY `privacy_requests_request_type_index` (`request_type`),
  KEY `privacy_requests_created_at_index` (`created_at`),
  UNIQUE KEY `privacy_requests_ticket_number_unique` (`ticket_number`),
  KEY `privacy_requests_approved_by_user_id_foreign` (`approved_by_user_id`),
  CONSTRAINT `privacy_requests_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `privacy_requests_handled_by_user_id_foreign` FOREIGN KEY (`handled_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `privacy_requests_approved_by_user_id_foreign` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `process_recommendations`
--

DROP TABLE IF EXISTS `process_recommendations`;
CREATE TABLE `process_recommendations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kpi_process_review_id` bigint unsigned NOT NULL,
  `category` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` bigint unsigned DEFAULT NULL,
  `target_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `problem_detected` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `evidence_metrics` json DEFAULT NULL,
  `root_cause_analysis` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recommended_action` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `expected_operational_benefit` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `priority` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `implemented_by_id` bigint unsigned DEFAULT NULL,
  `implemented_at` timestamp NULL DEFAULT NULL,
  `implementation_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `process_recommendations_kpi_process_review_id_foreign` (`kpi_process_review_id`),
  KEY `process_recommendations_implemented_by_id_foreign` (`implemented_by_id`),
  KEY `process_recommendations_category_index` (`category`),
  KEY `process_recommendations_priority_index` (`priority`),
  KEY `process_recommendations_status_index` (`status`),
  CONSTRAINT `process_recommendations_kpi_process_review_id_foreign` FOREIGN KEY (`kpi_process_review_id`) REFERENCES `kpi_process_reviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `process_recommendations_implemented_by_id_foreign` FOREIGN KEY (`implemented_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `procurement_audit_logs`
--

DROP TABLE IF EXISTS `procurement_audit_logs`;
CREATE TABLE `procurement_audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `entity_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint unsigned NOT NULL,
  `action_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `procurement_audit_logs_user_id_foreign` (`user_id`),
  KEY `procurement_audit_logs_entity_name_entity_id_index` (`entity_name`,`entity_id`),
  KEY `procurement_audit_logs_action_type_created_at_index` (`action_type`,`created_at`),
  CONSTRAINT `procurement_audit_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `procurement_categories`
--

DROP TABLE IF EXISTS `procurement_categories`;
CREATE TABLE `procurement_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `category_manager_id` bigint unsigned DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `procurement_categories_parent_id_foreign` (`parent_id`),
  KEY `procurement_categories_category_manager_id_foreign` (`category_manager_id`),
  KEY `procurement_categories_is_active_code_index` (`is_active`,`code`),
  UNIQUE KEY `procurement_categories_code_unique` (`code`),
  CONSTRAINT `procurement_categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `procurement_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_categories_category_manager_id_foreign` FOREIGN KEY (`category_manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `procurement_requests`
--

DROP TABLE IF EXISTS `procurement_requests`;
CREATE TABLE `procurement_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_id` bigint unsigned NOT NULL,
  `requested_quantity` int NOT NULL DEFAULT '0',
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `supplier_id` bigint unsigned DEFAULT NULL,
  `approved_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `approval_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `evaluation_score` decimal(5,2) DEFAULT NULL,
  `evaluation_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `procurement_requests_item_id_foreign` (`item_id`),
  KEY `procurement_requests_supplier_id_foreign` (`supplier_id`),
  UNIQUE KEY `procurement_requests_request_number_unique` (`request_number`),
  CONSTRAINT `procurement_requests_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `procurement_requests_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `procurement_savings_logs`
--

DROP TABLE IF EXISTS `procurement_savings_logs`;
CREATE TABLE `procurement_savings_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kpi_process_review_id` bigint unsigned NOT NULL,
  `purchase_order_id` bigint unsigned NOT NULL,
  `purchase_order_line_id` bigint unsigned DEFAULT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `pndf_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uom` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity_procured` decimal(12,2) NOT NULL DEFAULT '0',
  `actual_unit_price` decimal(12,4) NOT NULL DEFAULT '0',
  `dpri_ceiling_price` decimal(12,4) NOT NULL DEFAULT '0',
  `variance_amount` decimal(14,4) NOT NULL DEFAULT '0',
  `savings_percentage` decimal(8,2) NOT NULL DEFAULT '0',
  `is_above_ceiling` tinyint(1) NOT NULL DEFAULT '0',
  `justification` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `procurement_savings_logs_kpi_process_review_id_foreign` (`kpi_process_review_id`),
  KEY `procurement_savings_logs_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `procurement_savings_logs_purchase_order_line_id_foreign` (`purchase_order_line_id`),
  KEY `procurement_savings_logs_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `psl_review_item_idx` (`kpi_process_review_id`,`inventory_item_id`),
  KEY `procurement_savings_logs_pndf_code_index` (`pndf_code`),
  CONSTRAINT `procurement_savings_logs_kpi_process_review_id_foreign` FOREIGN KEY (`kpi_process_review_id`) REFERENCES `kpi_process_reviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `procurement_savings_logs_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `procurement_savings_logs_purchase_order_line_id_foreign` FOREIGN KEY (`purchase_order_line_id`) REFERENCES `po_line_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `procurement_savings_logs_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `purchase_request_id` bigint unsigned DEFAULT NULL,
  `sourcing_rfq_id` bigint unsigned DEFAULT NULL,
  `cost_center_id` bigint unsigned DEFAULT NULL,
  `po_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `purchase_unit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conversion_factor` decimal(12,4) NOT NULL DEFAULT '1',
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0',
  `total_amount` decimal(12,2) NOT NULL DEFAULT '0',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `exchange_rate` decimal(10,4) NOT NULL DEFAULT '1',
  `total_encumbered_amount` decimal(14,2) NOT NULL DEFAULT '0',
  `payment_terms` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `incoterms` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PO-REV1',
  `revision_number` smallint unsigned NOT NULL DEFAULT '1',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `cxml_payload` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_date` date DEFAULT NULL,
  `mode_of_procurement` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public_bidding',
  `penalty_clause_rate` decimal(6,4) NOT NULL DEFAULT '0.001',
  `fund_cluster` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '01 Regular Agency Fund',
  `conforme_date` date DEFAULT NULL,
  `conforme_signed_by` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `entity_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Philippine General Hospital',
  `ors_burs_number` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `received_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `purchase_orders_supplier_id_foreign` (`supplier_id`),
  KEY `purchase_orders_item_id_foreign` (`item_id`),
  UNIQUE KEY `purchase_orders_po_number_unique` (`po_number`),
  KEY `purchase_orders_purchase_request_id_foreign` (`purchase_request_id`),
  KEY `purchase_orders_sourcing_rfq_id_foreign` (`sourcing_rfq_id`),
  KEY `purchase_orders_cost_center_id_foreign` (`cost_center_id`),
  KEY `purchase_orders_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `purchase_orders_requested_at_idx` (`requested_at`),
  CONSTRAINT `purchase_orders_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_orders_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_orders_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_sourcing_rfq_id_foreign` FOREIGN KEY (`sourcing_rfq_id`) REFERENCES `sourcing_rfqs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_cost_center_id_foreign` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `purchase_orders_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `purchase_requests`
--

DROP TABLE IF EXISTS `purchase_requests`;
CREATE TABLE `purchase_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pr_number` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requester_id` bigint unsigned NOT NULL,
  `cost_center_id` bigint unsigned NOT NULL,
  `procurement_category_id` bigint unsigned DEFAULT NULL,
  `procurement_method` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_estimated_amount` decimal(14,2) NOT NULL DEFAULT '0',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `priority` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `is_emergency` tinyint(1) NOT NULL DEFAULT '0',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `purchase_requests_requester_id_foreign` (`requester_id`),
  KEY `purchase_requests_cost_center_id_foreign` (`cost_center_id`),
  KEY `purchase_requests_procurement_category_id_foreign` (`procurement_category_id`),
  KEY `purchase_requests_cost_center_id_status_index` (`cost_center_id`,`status`),
  UNIQUE KEY `purchase_requests_pr_number_unique` (`pr_number`),
  KEY `purchase_requests_status_index` (`status`),
  CONSTRAINT `purchase_requests_requester_id_foreign` FOREIGN KEY (`requester_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_requests_cost_center_id_foreign` FOREIGN KEY (`cost_center_id`) REFERENCES `cost_centers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `purchase_requests_procurement_category_id_foreign` FOREIGN KEY (`procurement_category_id`) REFERENCES `procurement_categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `quality_inspections`
--

DROP TABLE IF EXISTS `quality_inspections`;
CREATE TABLE `quality_inspections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `grn_line_item_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned NOT NULL,
  `item_batch_id` bigint unsigned DEFAULT NULL,
  `inspected_by_id` bigint unsigned DEFAULT NULL,
  `inspection_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_sample',
  `sample_quantity` int NOT NULL DEFAULT '0',
  `accepted_quantity` int NOT NULL DEFAULT '0',
  `rejected_quantity` int NOT NULL DEFAULT '0',
  `inspection_date` datetime NOT NULL,
  `findings` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rejection_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `quality_inspections_grn_line_item_id_foreign` (`grn_line_item_id`),
  KEY `quality_inspections_item_id_foreign` (`item_id`),
  KEY `quality_inspections_item_batch_id_foreign` (`item_batch_id`),
  KEY `quality_inspections_inspected_by_id_foreign` (`inspected_by_id`),
  KEY `quality_inspections_item_id_inspection_status_index` (`item_id`,`inspection_status`),
  CONSTRAINT `quality_inspections_grn_line_item_id_foreign` FOREIGN KEY (`grn_line_item_id`) REFERENCES `grn_line_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quality_inspections_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `quality_inspections_item_batch_id_foreign` FOREIGN KEY (`item_batch_id`) REFERENCES `item_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `quality_inspections_inspected_by_id_foreign` FOREIGN KEY (`inspected_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `quote_line_items`
--

DROP TABLE IF EXISTS `quote_line_items`;
CREATE TABLE `quote_line_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_quote_id` bigint unsigned NOT NULL,
  `rfq_line_item_id` bigint unsigned DEFAULT NULL,
  `offered_unit_price` decimal(12,2) NOT NULL DEFAULT '0',
  `offered_quantity` int NOT NULL DEFAULT '1',
  `lead_time_days` smallint unsigned NOT NULL DEFAULT '7',
  `shipping_cost` decimal(12,2) NOT NULL DEFAULT '0',
  `tariffs_cost` decimal(12,2) NOT NULL DEFAULT '0',
  `handling_cost` decimal(12,2) NOT NULL DEFAULT '0',
  `discount_amount` decimal(12,2) NOT NULL DEFAULT '0',
  `landed_cost` decimal(14,2) NOT NULL DEFAULT '0',
  `technical_compliance` tinyint(1) NOT NULL DEFAULT '1',
  `technical_score` decimal(5,2) NOT NULL DEFAULT '100',
  `is_awarded` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `quote_line_items_supplier_quote_id_foreign` (`supplier_quote_id`),
  KEY `quote_line_items_rfq_line_item_id_foreign` (`rfq_line_item_id`),
  CONSTRAINT `quote_line_items_supplier_quote_id_foreign` FOREIGN KEY (`supplier_quote_id`) REFERENCES `supplier_quotes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quote_line_items_rfq_line_item_id_foreign` FOREIGN KEY (`rfq_line_item_id`) REFERENCES `rfq_line_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `rfq_line_items`
--

DROP TABLE IF EXISTS `rfq_line_items`;
CREATE TABLE `rfq_line_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sourcing_rfq_id` bigint unsigned NOT NULL,
  `pr_line_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned NOT NULL,
  `line_number` int unsigned NOT NULL,
  `target_quantity` int NOT NULL DEFAULT '1',
  `uom` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technical_specifications` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `max_budget_unit_price` decimal(12,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `rfq_line_items_sourcing_rfq_id_foreign` (`sourcing_rfq_id`),
  KEY `rfq_line_items_pr_line_id_foreign` (`pr_line_id`),
  KEY `rfq_line_items_item_id_foreign` (`item_id`),
  UNIQUE KEY `rfq_line_items_sourcing_rfq_id_line_number_unique` (`sourcing_rfq_id`,`line_number`),
  CONSTRAINT `rfq_line_items_sourcing_rfq_id_foreign` FOREIGN KEY (`sourcing_rfq_id`) REFERENCES `sourcing_rfqs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rfq_line_items_pr_line_id_foreign` FOREIGN KEY (`pr_line_id`) REFERENCES `pr_line_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `rfq_line_items_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `rfq_supplier_invitations`
--

DROP TABLE IF EXISTS `rfq_supplier_invitations`;
CREATE TABLE `rfq_supplier_invitations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sourcing_rfq_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `portal_token` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'invited',
  `invited_at` timestamp NOT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `rfq_supplier_invitations_sourcing_rfq_id_foreign` (`sourcing_rfq_id`),
  KEY `rfq_supplier_invitations_supplier_id_foreign` (`supplier_id`),
  UNIQUE KEY `rfq_supplier_invitations_sourcing_rfq_id_supplier_id_unique` (`sourcing_rfq_id`,`supplier_id`),
  UNIQUE KEY `rfq_supplier_invitations_portal_token_unique` (`portal_token`),
  CONSTRAINT `rfq_supplier_invitations_sourcing_rfq_id_foreign` FOREIGN KEY (`sourcing_rfq_id`) REFERENCES `sourcing_rfqs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rfq_supplier_invitations_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `scheduled_report_executions`
--

DROP TABLE IF EXISTS `scheduled_report_executions`;
CREATE TABLE `scheduled_report_executions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scheduled_report_id` bigint unsigned DEFAULT NULL,
  `requested_by_user_id` bigint unsigned DEFAULT NULL,
  `recipient_user_id` bigint unsigned DEFAULT NULL,
  `scheduled_for` timestamp NOT NULL,
  `report_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `report_format` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `filters` json DEFAULT NULL,
  `recipient_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `mail_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `record_count` int unsigned DEFAULT NULL,
  `failure_stage` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_summary` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `mail_sent_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `scheduled_report_executions_scheduled_report_id_foreign` (`scheduled_report_id`),
  KEY `scheduled_report_executions_requested_by_user_id_foreign` (`requested_by_user_id`),
  KEY `scheduled_report_executions_recipient_user_id_foreign` (`recipient_user_id`),
  UNIQUE KEY `scheduled_report_occurrence_unique` (`scheduled_report_id`,`scheduled_for`),
  KEY `scheduled_report_history_index` (`scheduled_report_id`,`created_at`),
  KEY `scheduled_report_executions_status_index` (`status`),
  CONSTRAINT `scheduled_report_executions_scheduled_report_id_foreign` FOREIGN KEY (`scheduled_report_id`) REFERENCES `scheduled_reports` (`id`) ON DELETE SET NULL,
  CONSTRAINT `scheduled_report_executions_requested_by_user_id_foreign` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `scheduled_report_executions_recipient_user_id_foreign` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `scheduled_reports`
--

DROP TABLE IF EXISTS `scheduled_reports`;
CREATE TABLE `scheduled_reports` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `report_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `report_format` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `frequency` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `day_of_week` tinyint unsigned DEFAULT NULL,
  `day_of_month` tinyint unsigned DEFAULT NULL,
  `run_at` time NOT NULL,
  `filters` json DEFAULT NULL,
  `recipient_user_id` bigint unsigned NOT NULL,
  `created_by_user_id` bigint unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `last_run_at` timestamp NULL DEFAULT NULL,
  `next_run_at` timestamp NULL DEFAULT NULL,
  `last_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `scheduled_reports_recipient_user_id_foreign` (`recipient_user_id`),
  KEY `scheduled_reports_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `scheduled_reports_next_run_at_index` (`next_run_at`),
  CONSTRAINT `scheduled_reports_recipient_user_id_foreign` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `scheduled_reports_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `security_incidents`
--

DROP TABLE IF EXISTS `security_incidents`;
CREATE TABLE `security_incidents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `incident_number` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'detected',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `affected_system_or_data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_suspected_breach` tinyint(1) NOT NULL DEFAULT '0',
  `breach_assessment` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `containment_actions` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remediation_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `detected_at` timestamp NOT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `reported_by_user_id` bigint unsigned DEFAULT NULL,
  `assigned_to_user_id` bigint unsigned DEFAULT NULL,
  `resolved_by_user_id` bigint unsigned DEFAULT NULL,
  `metadata` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `security_incidents_reported_by_user_id_foreign` (`reported_by_user_id`),
  KEY `security_incidents_assigned_to_user_id_foreign` (`assigned_to_user_id`),
  KEY `security_incidents_resolved_by_user_id_foreign` (`resolved_by_user_id`),
  KEY `security_incidents_status_index` (`status`),
  KEY `security_incidents_severity_index` (`severity`),
  KEY `security_incidents_is_suspected_breach_index` (`is_suspected_breach`),
  KEY `security_incidents_detected_at_index` (`detected_at`),
  UNIQUE KEY `security_incidents_incident_number_unique` (`incident_number`),
  CONSTRAINT `security_incidents_reported_by_user_id_foreign` FOREIGN KEY (`reported_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_incidents_assigned_to_user_id_foreign` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `security_incidents_resolved_by_user_id_foreign` FOREIGN KEY (`resolved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `shipments`
--

DROP TABLE IF EXISTS `shipments`;
CREATE TABLE `shipments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `shipment_number` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `purchase_order_id` bigint unsigned DEFAULT NULL,
  `supplier_id` bigint unsigned DEFAULT NULL,
  `pickup_location_type` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_location_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_storage_location_id` bigint unsigned DEFAULT NULL,
  `carrier_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tracking_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `waybill_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vehicle_plate_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `driver_contact` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sscc` varchar(18) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `origin_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_contact_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_contact_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `destination_facility` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_storage_location_id` bigint unsigned DEFAULT NULL,
  `dispatch_date` date DEFAULT NULL,
  `estimated_delivery_date` date DEFAULT NULL,
  `actual_delivery_date` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_transit',
  `is_cold_chain` tinyint(1) NOT NULL DEFAULT '0',
  `temp_min` decimal(5,2) DEFAULT NULL,
  `temp_max` decimal(5,2) DEFAULT NULL,
  `temp_logger_serial` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temp_excursion` tinyint(1) NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `shipments_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `shipments_supplier_id_foreign` (`supplier_id`),
  KEY `shipments_purchase_order_id_status_index` (`purchase_order_id`,`status`),
  KEY `shipments_supplier_id_status_index` (`supplier_id`,`status`),
  KEY `shipments_tracking_number_index` (`tracking_number`),
  UNIQUE KEY `shipments_shipment_number_unique` (`shipment_number`),
  KEY `shipments_pickup_storage_location_id_foreign` (`pickup_storage_location_id`),
  KEY `shipments_destination_storage_location_id_foreign` (`destination_storage_location_id`),
  KEY `shipments_pickup_location_type_index` (`pickup_location_type`),
  KEY `shipments_pickup_location_name_index` (`pickup_location_name`),
  CONSTRAINT `shipments_pickup_storage_location_id_foreign` FOREIGN KEY (`pickup_storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_destination_storage_location_id_foreign` FOREIGN KEY (`destination_storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shipments_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `sourcing_evaluations`
--

DROP TABLE IF EXISTS `sourcing_evaluations`;
CREATE TABLE `sourcing_evaluations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sourcing_rfq_id` bigint unsigned NOT NULL,
  `supplier_quote_id` bigint unsigned NOT NULL,
  `evaluator_user_id` bigint unsigned NOT NULL,
  `commercial_score` decimal(5,2) NOT NULL DEFAULT '0',
  `technical_score` decimal(5,2) NOT NULL DEFAULT '0',
  `quality_score` decimal(5,2) NOT NULL DEFAULT '0',
  `lead_time_score` decimal(5,2) NOT NULL DEFAULT '0',
  `composite_score` decimal(5,2) NOT NULL DEFAULT '0',
  `normalized_landed_cost` decimal(14,2) NOT NULL DEFAULT '0',
  `conflict_of_interest_declared` tinyint(1) NOT NULL DEFAULT '0',
  `justification_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `completed_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `sourcing_evaluations_sourcing_rfq_id_foreign` (`sourcing_rfq_id`),
  KEY `sourcing_evaluations_supplier_quote_id_foreign` (`supplier_quote_id`),
  KEY `sourcing_evaluations_evaluator_user_id_foreign` (`evaluator_user_id`),
  KEY `sourcing_evaluations_sourcing_rfq_id_composite_score_index` (`sourcing_rfq_id`,`composite_score`),
  CONSTRAINT `sourcing_evaluations_sourcing_rfq_id_foreign` FOREIGN KEY (`sourcing_rfq_id`) REFERENCES `sourcing_rfqs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sourcing_evaluations_supplier_quote_id_foreign` FOREIGN KEY (`supplier_quote_id`) REFERENCES `supplier_quotes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sourcing_evaluations_evaluator_user_id_foreign` FOREIGN KEY (`evaluator_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `sourcing_rfqs`
--

DROP TABLE IF EXISTS `sourcing_rfqs`;
CREATE TABLE `sourcing_rfqs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rfq_number` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_request_id` bigint unsigned DEFAULT NULL,
  `created_by_user_id` bigint unsigned NOT NULL,
  `procurement_method` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'request_for_quotation',
  `bidding_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sealed',
  `submission_deadline` timestamp NOT NULL,
  `status` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `terms_conditions` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `weight_price` decimal(4,2) NOT NULL DEFAULT '0.4',
  `weight_technical` decimal(4,2) NOT NULL DEFAULT '0.3',
  `weight_quality` decimal(4,2) NOT NULL DEFAULT '0.15',
  `weight_lead_time` decimal(4,2) NOT NULL DEFAULT '0.15',
  `published_at` timestamp NULL DEFAULT NULL,
  `unsealed_at` timestamp NULL DEFAULT NULL,
  `unsealed_by_user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `sourcing_rfqs_purchase_request_id_foreign` (`purchase_request_id`),
  KEY `sourcing_rfqs_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `sourcing_rfqs_unsealed_by_user_id_foreign` (`unsealed_by_user_id`),
  UNIQUE KEY `sourcing_rfqs_rfq_number_unique` (`rfq_number`),
  KEY `sourcing_rfqs_status_index` (`status`),
  CONSTRAINT `sourcing_rfqs_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sourcing_rfqs_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `sourcing_rfqs_unsealed_by_user_id_foreign` FOREIGN KEY (`unsealed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `stock_alerts`
--

DROP TABLE IF EXISTS `stock_alerts`;
CREATE TABLE `stock_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `item_batch_id` bigint unsigned DEFAULT NULL,
  `storage_location_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'warning',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `threshold_value` int DEFAULT NULL,
  `current_value` int DEFAULT NULL,
  `acknowledged_by` bigint unsigned DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `stock_alerts_item_id_foreign` (`item_id`),
  KEY `stock_alerts_item_batch_id_foreign` (`item_batch_id`),
  KEY `stock_alerts_storage_location_id_foreign` (`storage_location_id`),
  KEY `stock_alerts_acknowledged_by_foreign` (`acknowledged_by`),
  KEY `stock_alerts_item_id_type_status_index` (`item_id`,`type`,`status`),
  CONSTRAINT `stock_alerts_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_alerts_item_batch_id_foreign` FOREIGN KEY (`item_batch_id`) REFERENCES `item_batches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_alerts_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_alerts_acknowledged_by_foreign` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `stock_movements`
--

DROP TABLE IF EXISTS `stock_movements`;
CREATE TABLE `stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `item_id` bigint unsigned NOT NULL,
  `item_batch_id` bigint unsigned DEFAULT NULL,
  `movement_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `unit_cost` decimal(12,2) NOT NULL DEFAULT '0',
  `from_location_id` bigint unsigned DEFAULT NULL,
  `to_location_id` bigint unsigned DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moved_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reference_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `idempotency_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_order_id` bigint unsigned DEFAULT NULL,
  `goods_receipt_note_id` bigint unsigned DEFAULT NULL,
  `purchase_unit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `purchase_quantity` int DEFAULT NULL,
  `base_unit` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `serial_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `stock_movements_item_id_foreign` (`item_id`),
  KEY `stock_movements_from_location_id_foreign` (`from_location_id`),
  KEY `stock_movements_to_location_id_foreign` (`to_location_id`),
  KEY `stock_movements_item_batch_id_foreign` (`item_batch_id`),
  KEY `stock_movements_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  KEY `stock_movements_user_id_foreign` (`user_id`),
  KEY `stock_movements_hash_index` (`hash`),
  KEY `stock_movements_item_date_idx` (`item_id`,`moved_at`),
  KEY `stock_movements_date_id_idx` (`moved_at`,`id`),
  KEY `stock_movements_purchase_order_id_foreign` (`purchase_order_id`),
  KEY `stock_movements_goods_receipt_note_id_foreign` (`goods_receipt_note_id`),
  UNIQUE KEY `stock_movements_idempotency_key_unique` (`idempotency_key`),
  CONSTRAINT `stock_movements_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_movements_from_location_id_foreign` FOREIGN KEY (`from_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_to_location_id_foreign` FOREIGN KEY (`to_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_item_batch_id_foreign` FOREIGN KEY (`item_batch_id`) REFERENCES `item_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_purchase_order_id_foreign` FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_movements_goods_receipt_note_id_foreign` FOREIGN KEY (`goods_receipt_note_id`) REFERENCES `goods_receipt_notes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `stock_transfer_lines`
--

DROP TABLE IF EXISTS `stock_transfer_lines`;
CREATE TABLE `stock_transfer_lines` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `stock_transfer_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `item_batch_id` bigint unsigned DEFAULT NULL,
  `dispatched_quantity` int NOT NULL DEFAULT '0',
  `received_quantity` int NOT NULL DEFAULT '0',
  `damaged_quantity` int NOT NULL DEFAULT '0',
  `lost_quantity` int NOT NULL DEFAULT '0',
  `line_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `stock_transfer_lines_stock_transfer_id_foreign` (`stock_transfer_id`),
  KEY `stock_transfer_lines_item_id_foreign` (`item_id`),
  KEY `stock_transfer_lines_item_batch_id_foreign` (`item_batch_id`),
  KEY `stock_transfer_lines_item_id_line_status_index` (`item_id`,`line_status`),
  CONSTRAINT `stock_transfer_lines_stock_transfer_id_foreign` FOREIGN KEY (`stock_transfer_id`) REFERENCES `stock_transfers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_transfer_lines_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `stock_transfer_lines_item_batch_id_foreign` FOREIGN KEY (`item_batch_id`) REFERENCES `item_batches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `stock_transfers`
--

DROP TABLE IF EXISTS `stock_transfers`;
CREATE TABLE `stock_transfers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `transfer_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source_location_id` bigint unsigned NOT NULL,
  `destination_location_id` bigint unsigned NOT NULL,
  `in_transit_location_id` bigint unsigned DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `dispatched_by_id` bigint unsigned DEFAULT NULL,
  `dispatched_at` datetime DEFAULT NULL,
  `received_by_id` bigint unsigned DEFAULT NULL,
  `received_at` datetime DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discrepancy_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `stock_transfers_source_location_id_foreign` (`source_location_id`),
  KEY `stock_transfers_destination_location_id_foreign` (`destination_location_id`),
  KEY `stock_transfers_in_transit_location_id_foreign` (`in_transit_location_id`),
  KEY `stock_transfers_dispatched_by_id_foreign` (`dispatched_by_id`),
  KEY `stock_transfers_received_by_id_foreign` (`received_by_id`),
  KEY `stock_transfers_source_location_id_status_index` (`source_location_id`,`status`),
  KEY `stock_transfers_destination_location_id_status_index` (`destination_location_id`,`status`),
  UNIQUE KEY `stock_transfers_transfer_number_unique` (`transfer_number`),
  CONSTRAINT `stock_transfers_source_location_id_foreign` FOREIGN KEY (`source_location_id`) REFERENCES `storage_locations` (`id`),
  CONSTRAINT `stock_transfers_destination_location_id_foreign` FOREIGN KEY (`destination_location_id`) REFERENCES `storage_locations` (`id`),
  CONSTRAINT `stock_transfers_in_transit_location_id_foreign` FOREIGN KEY (`in_transit_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_transfers_dispatched_by_id_foreign` FOREIGN KEY (`dispatched_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stock_transfers_received_by_id_foreign` FOREIGN KEY (`received_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `storage_location_category_rules`
--

DROP TABLE IF EXISTS `storage_location_category_rules`;
CREATE TABLE `storage_location_category_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `storage_location_id` bigint unsigned NOT NULL,
  `item_category_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `storage_location_category_rules_storage_location_id_foreign` (`storage_location_id`),
  KEY `storage_location_category_rules_item_category_id_foreign` (`item_category_id`),
  UNIQUE KEY `location_category_rule_unique` (`storage_location_id`,`item_category_id`),
  CONSTRAINT `storage_location_category_rules_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `storage_location_category_rules_item_category_id_foreign` FOREIGN KEY (`item_category_id`) REFERENCES `item_categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `storage_locations`
--

DROP TABLE IF EXISTS `storage_locations`;
CREATE TABLE `storage_locations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barcode_value` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` bigint unsigned DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bin',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aisle` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rack` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shelf` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bin` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `storage_classification` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `temperature_classification` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capacity` int DEFAULT NULL,
  `max_weight_kg` decimal(8,2) DEFAULT NULL,
  `capacity_unit` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'units',
  `is_receiving_staging` tinyint(1) NOT NULL DEFAULT '0',
  `is_quarantine` tinyint(1) NOT NULL DEFAULT '0',
  `is_pick_face` tinyint(1) NOT NULL DEFAULT '0',
  `is_reserve` tinyint(1) NOT NULL DEFAULT '0',
  `is_dispatch_staging` tinyint(1) NOT NULL DEFAULT '0',
  `is_in_transit` tinyint(1) NOT NULL DEFAULT '0',
  `is_returns_area` tinyint(1) NOT NULL DEFAULT '0',
  `is_damaged_stock` tinyint(1) NOT NULL DEFAULT '0',
  `is_narcotics_vault` tinyint(1) NOT NULL DEFAULT '0',
  `is_hazardous_containment` tinyint(1) NOT NULL DEFAULT '0',
  `is_frozen_for_count` tinyint(1) NOT NULL DEFAULT '0',
  `excursion_hold` tinyint(1) NOT NULL DEFAULT '0',
  `sort_sequence` int unsigned NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  UNIQUE KEY `storage_locations_code_unique` (`code`),
  KEY `storage_locations_parent_id_foreign` (`parent_id`),
  KEY `storage_locations_type_status_index` (`type`,`status`),
  KEY `storage_locations_parent_id_sort_sequence_index` (`parent_id`,`sort_sequence`),
  UNIQUE KEY `storage_locations_barcode_value_unique` (`barcode_value`),
  KEY `storage_locations_is_narcotics_vault_status_index` (`is_narcotics_vault`,`status`),
  KEY `storage_locations_excursion_hold_status_index` (`excursion_hold`,`status`),
  KEY `storage_locations_is_in_transit_status_index` (`is_in_transit`,`status`),
  CONSTRAINT `storage_locations_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `supplier_accreditations`
--

DROP TABLE IF EXISTS `supplier_accreditations`;
CREATE TABLE `supplier_accreditations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `cycle_number` int unsigned NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `submitted_by` bigint unsigned DEFAULT NULL,
  `submitted_at` timestamp NOT NULL,
  `decided_by` bigint unsigned DEFAULT NULL,
  `decided_at` timestamp NULL DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `decision_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `supplier_accreditations_supplier_id_foreign` (`supplier_id`),
  KEY `supplier_accreditations_submitted_by_foreign` (`submitted_by`),
  KEY `supplier_accreditations_decided_by_foreign` (`decided_by`),
  UNIQUE KEY `supplier_accreditations_supplier_id_cycle_number_unique` (`supplier_id`,`cycle_number`),
  KEY `supplier_accreditations_supplier_id_status_index` (`supplier_id`,`status`),
  CONSTRAINT `supplier_accreditations_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `supplier_accreditations_submitted_by_foreign` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_accreditations_decided_by_foreign` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `supplier_compliance_alerts`
--

DROP TABLE IF EXISTS `supplier_compliance_alerts`;
CREATE TABLE `supplier_compliance_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `supplier_document_id` bigint unsigned DEFAULT NULL,
  `supplier_contract_id` bigint unsigned DEFAULT NULL,
  `source_key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `due_date` date NOT NULL,
  `message` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_detected_at` timestamp NOT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `supplier_compliance_alerts_supplier_id_foreign` (`supplier_id`),
  KEY `supplier_compliance_alerts_supplier_document_id_foreign` (`supplier_document_id`),
  KEY `supplier_compliance_alerts_supplier_contract_id_foreign` (`supplier_contract_id`),
  KEY `supplier_compliance_alerts_status_index` (`supplier_id`,`status`),
  KEY `supplier_compliance_alerts_due_index` (`status`,`due_date`),
  UNIQUE KEY `supplier_compliance_alerts_source_key_unique` (`source_key`),
  CONSTRAINT `supplier_compliance_alerts_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `supplier_compliance_alerts_supplier_document_id_foreign` FOREIGN KEY (`supplier_document_id`) REFERENCES `supplier_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_compliance_alerts_supplier_contract_id_foreign` FOREIGN KEY (`supplier_contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `supplier_contacts`
--

DROP TABLE IF EXISTS `supplier_contacts`;
CREATE TABLE `supplier_contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'primary',
  `position` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mobile` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `supplier_contacts_supplier_id_foreign` (`supplier_id`),
  KEY `supplier_contacts_supplier_id_is_active_index` (`supplier_id`,`is_active`),
  CONSTRAINT `supplier_contacts_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `supplier_contracts`
--

DROP TABLE IF EXISTS `supplier_contracts`;
CREATE TABLE `supplier_contracts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `contract_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `starts_at` date NOT NULL,
  `ends_at` date DEFAULT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `payment_terms` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_terms` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsible_user_id` bigint unsigned DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `supplier_contracts_supplier_id_foreign` (`supplier_id`),
  KEY `supplier_contracts_responsible_user_id_foreign` (`responsible_user_id`),
  UNIQUE KEY `supplier_contracts_supplier_id_contract_number_unique` (`supplier_id`,`contract_number`),
  KEY `supplier_contracts_supplier_id_ends_at_index` (`supplier_id`,`ends_at`),
  CONSTRAINT `supplier_contracts_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `supplier_contracts_responsible_user_id_foreign` FOREIGN KEY (`responsible_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `supplier_documents`
--

DROP TABLE IF EXISTS `supplier_documents`;
CREATE TABLE `supplier_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `document_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `issued_at` date DEFAULT NULL,
  `expires_at` date DEFAULT NULL,
  `issuing_authority` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disk` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local',
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` bigint unsigned NOT NULL,
  `verification_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `required_for_accreditation` tinyint(1) NOT NULL DEFAULT '0',
  `blocks_procurement_when_invalid` tinyint(1) NOT NULL DEFAULT '0',
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `review_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `superseded_by_id` bigint unsigned DEFAULT NULL,
  `is_current` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `supplier_documents_supplier_id_foreign` (`supplier_id`),
  KEY `supplier_documents_uploaded_by_foreign` (`uploaded_by`),
  KEY `supplier_documents_verified_by_foreign` (`verified_by`),
  KEY `supplier_documents_superseded_by_id_foreign` (`superseded_by_id`),
  KEY `supplier_documents_verification_index` (`supplier_id`,`verification_status`),
  KEY `supplier_documents_expiry_index` (`supplier_id`,`expires_at`),
  KEY `supplier_documents_current_index` (`supplier_id`,`is_current`),
  CONSTRAINT `supplier_documents_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `supplier_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_documents_verified_by_foreign` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_documents_superseded_by_id_foreign` FOREIGN KEY (`superseded_by_id`) REFERENCES `supplier_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `supplier_prices`
--

DROP TABLE IF EXISTS `supplier_prices`;
CREATE TABLE `supplier_prices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_product_id` bigint unsigned NOT NULL,
  `supplier_contract_id` bigint unsigned DEFAULT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `minimum_order_quantity` int unsigned NOT NULL DEFAULT '1',
  `effective_from` date NOT NULL,
  `effective_until` date DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `supplier_prices_supplier_product_id_foreign` (`supplier_product_id`),
  KEY `supplier_prices_supplier_contract_id_foreign` (`supplier_contract_id`),
  KEY `supplier_prices_created_by_foreign` (`created_by`),
  KEY `supplier_prices_effective_index` (`supplier_product_id`,`effective_from`),
  CONSTRAINT `supplier_prices_supplier_product_id_foreign` FOREIGN KEY (`supplier_product_id`) REFERENCES `supplier_products` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `supplier_prices_supplier_contract_id_foreign` FOREIGN KEY (`supplier_contract_id`) REFERENCES `supplier_contracts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_prices_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `supplier_products`
--

DROP TABLE IF EXISTS `supplier_products`;
CREATE TABLE `supplier_products` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `item_id` bigint unsigned NOT NULL,
  `supplier_sku` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `supplier_product_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `manufacturer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `brand` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pack_size` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `minimum_order_quantity` int unsigned DEFAULT NULL,
  `lead_time_days` smallint unsigned DEFAULT NULL,
  `is_preferred` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `supplier_products_supplier_id_foreign` (`supplier_id`),
  KEY `supplier_products_item_id_foreign` (`item_id`),
  UNIQUE KEY `supplier_products_supplier_id_item_id_unique` (`supplier_id`,`item_id`),
  KEY `supplier_products_item_id_is_active_index` (`item_id`,`is_active`),
  CONSTRAINT `supplier_products_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `supplier_products_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `supplier_quotes`
--

DROP TABLE IF EXISTS `supplier_quotes`;
CREATE TABLE `supplier_quotes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sourcing_rfq_id` bigint unsigned DEFAULT NULL,
  `quote_number` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `procurement_request_id` bigint unsigned DEFAULT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `quoted_price` decimal(12,2) NOT NULL DEFAULT '0',
  `total_bid_amount` decimal(14,2) NOT NULL DEFAULT '0',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PHP',
  `exchange_rate` decimal(10,4) NOT NULL DEFAULT '1',
  `incoterms` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_terms` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `validity_end_date` date DEFAULT NULL,
  `is_sealed` tinyint(1) NOT NULL DEFAULT '1',
  `unsealed_at` timestamp NULL DEFAULT NULL,
  `is_awarded` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'submitted',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `supplier_quotes_procurement_request_id_foreign` (`procurement_request_id`),
  KEY `supplier_quotes_supplier_id_foreign` (`supplier_id`),
  KEY `supplier_quotes_sourcing_rfq_id_foreign` (`sourcing_rfq_id`),
  CONSTRAINT `supplier_quotes_procurement_request_id_foreign` FOREIGN KEY (`procurement_request_id`) REFERENCES `procurement_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_quotes_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `supplier_quotes_sourcing_rfq_id_foreign` FOREIGN KEY (`sourcing_rfq_id`) REFERENCES `sourcing_rfqs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `supplier_scorecards`
--

DROP TABLE IF EXISTS `supplier_scorecards`;
CREATE TABLE `supplier_scorecards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kpi_process_review_id` bigint unsigned NOT NULL,
  `supplier_id` bigint unsigned NOT NULL,
  `delivery_score` decimal(5,2) NOT NULL DEFAULT '0',
  `quality_score` decimal(5,2) NOT NULL DEFAULT '0',
  `fill_rate_score` decimal(5,2) NOT NULL DEFAULT '0',
  `total_score` decimal(5,2) NOT NULL DEFAULT '0',
  `total_pos_count` int unsigned NOT NULL DEFAULT '0',
  `completed_pos_count` int unsigned NOT NULL DEFAULT '0',
  `late_deliveries_count` int unsigned NOT NULL DEFAULT '0',
  `avg_lead_time_days` decimal(8,2) NOT NULL DEFAULT '0',
  `promised_lead_time_days` decimal(8,2) NOT NULL DEFAULT '0',
  `non_conformance_count` int unsigned NOT NULL DEFAULT '0',
  `temperature_excursions_count` int unsigned NOT NULL DEFAULT '0',
  `has_valid_lto` tinyint(1) NOT NULL DEFAULT '1',
  `has_valid_cpr` tinyint(1) NOT NULL DEFAULT '1',
  `recommendation` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'retain',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `supplier_scorecards_kpi_process_review_id_foreign` (`kpi_process_review_id`),
  KEY `supplier_scorecards_supplier_id_foreign` (`supplier_id`),
  KEY `sc_review_supp_idx` (`kpi_process_review_id`,`supplier_id`),
  CONSTRAINT `supplier_scorecards_kpi_process_review_id_foreign` FOREIGN KEY (`kpi_process_review_id`) REFERENCES `kpi_process_reviews` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_scorecards_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `trade_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `business_structure` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provides_regulated_health_products` tinyint(1) NOT NULL DEFAULT '0',
  `logo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_person` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `identity_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `archived_at` timestamp NULL DEFAULT NULL,
  `archived_by` bigint unsigned DEFAULT NULL,
  `archive_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `accreditation_status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `accreditation_expires_at` date DEFAULT NULL,
  `standard_lead_time_days` smallint unsigned DEFAULT NULL,
  `payment_terms` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `reviewed_by` bigint unsigned DEFAULT NULL,
  `approved_by` bigint unsigned DEFAULT NULL,
  `last_reviewed_at` timestamp NULL DEFAULT NULL,
  `suspension_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `suppliers_created_by_foreign` (`created_by`),
  KEY `suppliers_reviewed_by_foreign` (`reviewed_by`),
  KEY `suppliers_approved_by_foreign` (`approved_by`),
  KEY `suppliers_procurement_eligibility_index` (`status`,`accreditation_status`),
  UNIQUE KEY `suppliers_identity_key_unique` (`identity_key`),
  KEY `suppliers_accreditation_status_index` (`accreditation_status`),
  KEY `suppliers_accreditation_expires_at_index` (`accreditation_expires_at`),
  KEY `suppliers_archived_by_foreign` (`archived_by`),
  KEY `suppliers_status_archived_at_index` (`status`,`archived_at`),
  CONSTRAINT `suppliers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `suppliers_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `suppliers_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `suppliers_archived_by_foreign` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `surgical_consignment_bill_onlys`
--

DROP TABLE IF EXISTS `surgical_consignment_bill_onlys`;
CREATE TABLE `surgical_consignment_bill_onlys` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `inventory_item_id` bigint unsigned NOT NULL,
  `inventory_serial_id` bigint unsigned DEFAULT NULL,
  `item_batch_id` bigint unsigned DEFAULT NULL,
  `storage_location_id` bigint unsigned DEFAULT NULL,
  `patient_encounter_id` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `operating_suite` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `surgeon_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `implanted_quantity` int unsigned NOT NULL DEFAULT '1',
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending_po',
  `purchase_request_id` bigint unsigned DEFAULT NULL,
  `recorded_by_id` bigint unsigned NOT NULL,
  `implanted_at` datetime NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `surgical_consignment_bill_onlys_inventory_item_id_foreign` (`inventory_item_id`),
  KEY `surgical_consignment_bill_onlys_inventory_serial_id_foreign` (`inventory_serial_id`),
  KEY `surgical_consignment_bill_onlys_item_batch_id_foreign` (`item_batch_id`),
  KEY `surgical_consignment_bill_onlys_storage_location_id_foreign` (`storage_location_id`),
  KEY `surgical_consignment_bill_onlys_purchase_request_id_foreign` (`purchase_request_id`),
  KEY `surgical_consignment_bill_onlys_recorded_by_id_foreign` (`recorded_by_id`),
  KEY `surgical_consignment_bill_onlys_patient_encounter_id_index` (`patient_encounter_id`),
  KEY `surgical_consignment_bill_onlys_status_implanted_at_index` (`status`,`implanted_at`),
  UNIQUE KEY `surgical_consignment_bill_onlys_request_number_unique` (`request_number`),
  CONSTRAINT `surgical_consignment_bill_onlys_inventory_item_id_foreign` FOREIGN KEY (`inventory_item_id`) REFERENCES `inventory_items` (`id`),
  CONSTRAINT `surgical_consignment_bill_onlys_inventory_serial_id_foreign` FOREIGN KEY (`inventory_serial_id`) REFERENCES `inventory_serials` (`id`) ON DELETE SET NULL,
  CONSTRAINT `surgical_consignment_bill_onlys_item_batch_id_foreign` FOREIGN KEY (`item_batch_id`) REFERENCES `item_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `surgical_consignment_bill_onlys_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `surgical_consignment_bill_onlys_purchase_request_id_foreign` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `surgical_consignment_bill_onlys_recorded_by_id_foreign` FOREIGN KEY (`recorded_by_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `system_recovery_attempts`
--

DROP TABLE IF EXISTS `system_recovery_attempts`;
CREATE TABLE `system_recovery_attempts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `system_recovery_record_id` bigint unsigned NOT NULL,
  `attempt_number` int unsigned NOT NULL,
  `outcome` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `handler` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `message` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_user_id` bigint unsigned DEFAULT NULL,
  `actor_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `system_recovery_attempts_system_recovery_record_id_foreign` (`system_recovery_record_id`),
  KEY `system_recovery_attempts_actor_user_id_foreign` (`actor_user_id`),
  UNIQUE KEY `recovery_attempt_incident_number_unique` (`system_recovery_record_id`,`attempt_number`),
  KEY `recovery_attempt_incident_created_index` (`system_recovery_record_id`,`created_at`),
  CONSTRAINT `system_recovery_attempts_system_recovery_record_id_foreign` FOREIGN KEY (`system_recovery_record_id`) REFERENCES `system_recovery_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `system_recovery_attempts_actor_user_id_foreign` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `system_recovery_records`
--

DROP TABLE IF EXISTS `system_recovery_records`;
CREATE TABLE `system_recovery_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `error_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `user_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `module` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `failure_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'application',
  `operation` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `error_summary` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `affected_resource` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `exception_class` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `technical_details` json DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'failed',
  `strategy_applied` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_retryable` tinyint(1) NOT NULL DEFAULT '0',
  `retry_handler` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retry_payload` json DEFAULT NULL,
  `retry_count` int unsigned NOT NULL DEFAULT '0',
  `last_retried_at` timestamp NULL DEFAULT NULL,
  `last_attempt_outcome` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_attempt_error` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved_by_user_id` bigint unsigned DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `system_recovery_records_user_id_foreign` (`user_id`),
  KEY `system_recovery_records_resolved_by_user_id_foreign` (`resolved_by_user_id`),
  KEY `system_recovery_records_module_status_index` (`module`,`status`),
  KEY `system_recovery_records_operation_status_index` (`operation`,`status`),
  KEY `system_recovery_records_created_at_index` (`created_at`),
  UNIQUE KEY `system_recovery_records_error_id_unique` (`error_id`),
  KEY `system_recovery_records_module_index` (`module`),
  KEY `system_recovery_records_operation_index` (`operation`),
  KEY `system_recovery_records_status_index` (`status`),
  KEY `system_recovery_records_is_retryable_index` (`is_retryable`),
  KEY `system_recovery_records_failure_type_index` (`failure_type`),
  KEY `system_recovery_records_reference_id_index` (`reference_id`),
  CONSTRAINT `system_recovery_records_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `system_recovery_records_resolved_by_user_id_foreign` FOREIGN KEY (`resolved_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=30001;

--
-- Dumping data for table `system_recovery_records`
--

/*!40000 ALTER TABLE `system_recovery_records` DISABLE KEYS */;
INSERT INTO `system_recovery_records` VALUES
(1,'REC-MVUM8MG6TP',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/landing.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-MVUM8MG6TP\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/landing.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:38','2026-10-03 04:48:38'),
(2,'REC-PG9AS8JHAD',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-PG9AS8JHAD\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:41','2026-10-03 04:48:41'),
(3,'REC-XYZYCECY9C',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-XYZYCECY9C\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:41','2026-10-03 04:48:41'),
(4,'REC-DGTERE2MFJ',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-DGTERE2MFJ\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:41','2026-10-03 04:48:41'),
(5,'REC-FHJQVKV6TM',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-FHJQVKV6TM\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:41','2026-10-03 04:48:41'),
(6,'REC-2BFNAE0PEZ',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-2BFNAE0PEZ\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:42','2026-10-03 04:48:42'),
(7,'REC-8URKDXXJHI',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-8URKDXXJHI\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:42','2026-10-03 04:48:42'),
(8,'REC-NPIUPQ6COX',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-NPIUPQ6COX\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:42','2026-10-03 04:48:42'),
(9,'REC-UO0S75LCHU',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-UO0S75LCHU\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:43','2026-10-03 04:48:43'),
(10,'REC-IKVZT35DTY',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-IKVZT35DTY\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:43','2026-10-03 04:48:43'),
(11,'REC-RJUFNQWG3H',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-RJUFNQWG3H\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:44','2026-10-03 04:48:44'),
(12,'REC-4QSZUQ5PHB',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-4QSZUQ5PHB\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:44','2026-10-03 04:48:44'),
(13,'REC-EGSAOWIKKC',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-EGSAOWIKKC\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:46','2026-10-03 04:48:46'),
(14,'REC-WRSJENK5WI',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-WRSJENK5WI\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:46','2026-10-03 04:48:46'),
(15,'REC-LDNKG1BDN3',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-LDNKG1BDN3\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:47','2026-10-03 04:48:47'),
(16,'REC-UBLNDETOF9',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-UBLNDETOF9\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:47','2026-10-03 04:48:47'),
(17,'REC-PSU3CORUUU',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-PSU3CORUUU\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:48','2026-10-03 04:48:48'),
(18,'REC-9PJW7CRVCK',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-9PJW7CRVCK\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:48','2026-10-03 04:48:48'),
(19,'REC-FDLQP0O6G4',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-FDLQP0O6G4\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:48','2026-10-03 04:48:48'),
(20,'REC-5LD48UZZ0L',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-5LD48UZZ0L\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:48','2026-10-03 04:48:48'),
(21,'REC-YOI6KXXJIK',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-YOI6KXXJIK\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:49','2026-10-03 04:48:49'),
(22,'REC-MHXCT1IKGL',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-MHXCT1IKGL\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:50','2026-10-03 04:48:50'),
(23,'REC-TWY7TVYJCG',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-TWY7TVYJCG\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:50','2026-10-03 04:48:50'),
(24,'REC-WETVWZSZMQ',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-WETVWZSZMQ\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:50','2026-10-03 04:48:50'),
(25,'REC-MLUI27XNDW',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-MLUI27XNDW\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:51','2026-10-03 04:48:51'),
(26,'REC-NSL3GOC6MK',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-NSL3GOC6MK\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:52','2026-10-03 04:48:52'),
(27,'REC-CXU20UQITR',NULL,'System / Automated','system','application','http_request','Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)',NULL,NULL,'Illuminate\\View\\ViewException','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-CXU20UQITR\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php)\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:52','2026-10-03 04:48:52'),
(28,'REC-J39DJCY7X9',NULL,'System / Automated','system','application','http_request','Uncaught Illuminate\\Foundation\\ViteManifestNotFoundException: Vite manifest not found at: /app/public/build/manifest.json in /app/vendor/laravel/framework/src/Illuminate/Foundation/Vite.php:946\nStack trace:\n#0 /app/vendor/laravel/framework/src/Illuminate/Foundation/Vite.php(384): Illuminate\\Foundation\\Vite->manifest(\'build\')\n#1 /app/storage/framework/views/605dde6d58430a1239183f717c11de3d.php(14): Illuminate\\Foundation\\Vite->__invoke(Object(Illuminate\\Support\\Collection))\n#2 /app/vendo...',NULL,NULL,'Symfony\\Component\\ErrorHandler\\Error\\FatalError','{\"code\": 0, \"context\": {\"path\": \"/\"}, \"error_id\": \"REC-J39DJCY7X9\", \"file\": \"vendor/laravel/framework/src/Illuminate/Foundation/Vite.php\", \"line\": 946, \"message\": \"Uncaught Illuminate\\\\Foundation\\\\ViteManifestNotFoundException: Vite manifest not found at: /app/public/build/manifest.json in /app/vendor/laravel/framework/src/Illuminate/Foundation/Vite.php:946\\nStack trace:\\n#0 /app/vendor/laravel/framework/src/Illuminate/Foundation/Vite.php(384): Illuminate\\\\Foundation\\\\Vite->manifest(\'build\')\\n#1 /app/storage/framework/views/605dde6d58430a1239183f717c11de3d.php(14): Illuminate\\\\Foundation\\\\Vite->__invoke(Object(Illuminate\\\\Support\\\\Collection))\\n#2 /app/vendor/laravel/framework/src/Illuminate/Filesystem/Filesystem.php(123): require(\'/app/storage/fr...\')\\n#3 /app/vendor/laravel/framework/src/Illuminate/Filesystem/Filesystem.php(124): Illuminate\\\\Filesystem\\\\Filesystem::{closure:Illuminate\\\\Filesystem\\\\Filesystem::getRequire():120}()\\n#4 /app/vendor/laravel/framework/src/Illuminate/View/Engines/PhpEngine.php(57): Illuminate\\\\Filesystem\\\\Filesystem->getRequire(\'/app/storage/fr...\', Array)\\n#5 /app/vendor/laravel/framework/src/Illuminate/View/Engines/CompilerEngine.php(76): Illuminate\\\\View\\\\Engines\\\\PhpEngine->evaluatePath(\'/app/storage/fr...\', Array)\\n#6 /app/vendor/laravel/framework/src/Illuminate/View/View.php(208): Illuminate\\\\View\\\\Engines\\\\CompilerEngine->get(\'/app/resources/...\', Array)\\n#7 /app/vendor/laravel/framework/src/Illuminate/View/View.php(191): Illuminate\\\\View\\\\View->getContents()\\n#8 /app/vendor/laravel/framework/src/Illuminate/View/View.php(160): Illuminate\\\\View\\\\View->renderContents()\\n#9 /app/vendor/laravel/framework/src/Illuminate/Http/Response.php(78): Illuminate\\\\View\\\\View->render()\\n#10 /app/vendor/laravel/framework/src/Illuminate/Http/Response.php(34): Illuminate\\\\Http\\\\Response->setContent(Object(Illuminate\\\\View\\\\View))\\n#11 /app/vendor/laravel/framework/src/Illuminate/Routing/ResponseFactory.php(61): Illuminate\\\\Http\\\\Response->__construct(Object(Illuminate\\\\View\\\\View), 500, Array)\\n#12 /app/vendor/laravel/framework/src/Illuminate/Routing/ResponseFactory.php(91): Illuminate\\\\Routing\\\\ResponseFactory->make(Object(Illuminate\\\\View\\\\View), 500, Array)\\n#13 /app/bootstrap/app.php(178): Illuminate\\\\Routing\\\\ResponseFactory->view(\'errors.500\', Array, 500)\\n#14 /app/vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php(717): {closure:{closure:/app/bootstrap/app.php:91}:142}(Object(Illuminate\\\\View\\\\ViewException), Object(Illuminate\\\\Http\\\\Request))\\n#15 /app/vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php(618): Illuminate\\\\Foundation\\\\Exceptions\\\\Handler->renderViaCallbacks(Object(Illuminate\\\\Http\\\\Request), Object(Illuminate\\\\View\\\\ViewException))\\n#16 /app/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php(225): Illuminate\\\\Foundation\\\\Exceptions\\\\Handler->render(Object(Illuminate\\\\Http\\\\Request), Object(Illuminate\\\\View\\\\ViewException))\\n#17 /app/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php(202): Illuminate\\\\Foundation\\\\Bootstrap\\\\HandleExceptions->renderHttpResponse(Object(Illuminate\\\\View\\\\ViewException))\\n#18 /app/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php(262): Illuminate\\\\Foundation\\\\Bootstrap\\\\HandleExceptions->handleException(Object(Illuminate\\\\View\\\\ViewException))\\n#19 [internal function]: Illuminate\\\\Foundation\\\\Bootstrap\\\\HandleExceptions->{closure:Illuminate\\\\Foundation\\\\Bootstrap\\\\HandleExceptions::forwardsTo():261}(Object(Illuminate\\\\View\\\\ViewException))\\n#20 {main}\\n\\nNext Illuminate\\\\View\\\\ViewException: Vite manifest not found at: /app/public/build/manifest.json (View: /app/resources/views/errors/500.blade.php) in /app/vendor/laravel/framework/src/Illuminate/Foundation/Vite.php:946\\nStack trace:\\n#0 /app/vendor/laravel/framework/src/Illuminate/View/Engines/PhpEngine.php(59): Illuminate\\\\View\\\\Engines\\\\CompilerEngine->handleViewException(Object(Illuminate\\\\Foundation\\\\ViteManifestNotFoundException), 0)\\n#1 /app/vendor/laravel/framework/src/Illuminate/View/Engines/CompilerEngine.php(76): Illuminate\\\\View\\\\Engines\\\\PhpEngine->evaluatePath(\'/app/storage/fr...\', Array)\\n#2 /app/vendor/laravel/framework/src/Illuminate/View/View.php(208): Illuminate\\\\View\\\\Engines\\\\CompilerEngine->get(\'/app/resources/...\', Array)\\n#3 /app/vendor/laravel/framework/src/Illuminate/View/View.php(191): Illuminate\\\\View\\\\View->getContents()\\n#4 /app/vendor/laravel/framework/src/Illuminate/View/View.php(160): Illuminate\\\\View\\\\View->renderContents()\\n#5 /app/vendor/laravel/framework/src/Illuminate/Http/Response.php(78): Illuminate\\\\View\\\\View->render()\\n#6 /app/vendor/laravel/framework/src/Illuminate/Http/Response.php(34): Illuminate\\\\Http\\\\Response->setContent(Object(Illuminate\\\\View\\\\View))\\n#7 /app/vendor/laravel/framework/src/Illuminate/Routing/ResponseFactory.php(61): Illuminate\\\\Http\\\\Response->__construct(Object(Illuminate\\\\View\\\\View), 500, Array)\\n#8 /app/vendor/laravel/framework/src/Illuminate/Routing/ResponseFactory.php(91): Illuminate\\\\Routing\\\\ResponseFactory->make(Object(Illuminate\\\\View\\\\View), 500, Array)\\n#9 /app/bootstrap/app.php(178): Illuminate\\\\Routing\\\\ResponseFactory->view(\'errors.500\', Array, 500)\\n#10 /app/vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php(717): {closure:{closure:/app/bootstrap/app.php:91}:142}(Object(Illuminate\\\\View\\\\ViewException), Object(Illuminate\\\\Http\\\\Request))\\n#11 /app/vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/Handler.php(618): Illuminate\\\\Foundation\\\\Exceptions\\\\Handler->renderViaCallbacks(Object(Illuminate\\\\Http\\\\Request), Object(Illuminate\\\\View\\\\ViewException))\\n#12 /app/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php(225): Illuminate\\\\Foundation\\\\Exceptions\\\\Handler->render(Object(Illuminate\\\\Http\\\\Request), Object(Illuminate\\\\View\\\\ViewException))\\n#13 /app/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php(202): Illuminate\\\\Foundation\\\\Bootstrap\\\\HandleExceptions->renderHttpResponse(Object(Illuminate\\\\View\\\\ViewException))\\n#14 /app/vendor/laravel/framework/src/Illuminate/Foundation/Bootstrap/HandleExceptions.php(262): Illuminate\\\\Foundation\\\\Bootstrap\\\\HandleExceptions->handleException(Object(Illuminate\\\\View\\\\ViewException))\\n#15 [internal function]: Illuminate\\\\Foundation\\\\Bootstrap\\\\HandleExceptions->{closure:Illuminate\\\\Foundation\\\\Bootstrap\\\\HandleExceptions::forwardsTo():261}(Object(Illuminate\\\\View\\\\ViewException))\\n#16 {main}\\n  thrown\", \"method\": \"GET\", \"url\": \"https://supplydjnrmhs.hostforgeplatforms.com\"}','not_recoverable','unhandled_exception_intercept',0,NULL,NULL,0,NULL,NULL,NULL,NULL,NULL,NULL,'172.71.87.141','2026-10-03 04:48:53','2026-10-03 04:48:53');
/*!40000 ALTER TABLE `system_recovery_records` ENABLE KEYS */;

--
-- Table structure for table `trusted_devices`
--

DROP TABLE IF EXISTS `trusted_devices`;
CREATE TABLE `trusted_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_uuid` varchar(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent_summary` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_trusted_at` timestamp NOT NULL,
  `last_used_at` timestamp NOT NULL,
  `expires_at` timestamp NOT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `trusted_devices_user_id_foreign` (`user_id`),
  KEY `trusted_devices_token_hash_index` (`token_hash`),
  KEY `trusted_devices_device_uuid_index` (`device_uuid`),
  KEY `trusted_devices_expires_at_index` (`expires_at`),
  KEY `trusted_devices_revoked_at_index` (`revoked_at`),
  CONSTRAINT `trusted_devices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `user_active_sessions`
--

DROP TABLE IF EXISTS `user_active_sessions`;
CREATE TABLE `user_active_sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `session_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web',
  `device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trusted_device_id` bigint unsigned DEFAULT NULL,
  `last_activity_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `user_active_sessions_user_id_foreign` (`user_id`),
  KEY `user_active_sessions_trusted_device_id_foreign` (`trusted_device_id`),
  UNIQUE KEY `user_active_sessions_user_id_unique` (`user_id`),
  KEY `user_active_sessions_session_id_index` (`session_id`),
  CONSTRAINT `user_active_sessions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_active_sessions_trusted_device_id_foreign` FOREIGN KEY (`trusted_device_id`) REFERENCES `trusted_devices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `user_avatars`
--

DROP TABLE IF EXISTS `user_avatars`;
CREATE TABLE `user_avatars` (
  `user_id` bigint unsigned NOT NULL,
  `mime_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `content` mediumblob NOT NULL,
  PRIMARY KEY (`user_id`) /*T![clustered_index] CLUSTERED */,
  KEY `user_avatars_user_id_foreign` (`user_id`),
  CONSTRAINT `user_avatars_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `user_consents`
--

DROP TABLE IF EXISTS `user_consents`;
CREATE TABLE `user_consents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `consent_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `policy_version` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'consented',
  `is_mandatory` tinyint(1) NOT NULL DEFAULT '0',
  `consented_at` timestamp NULL DEFAULT NULL,
  `withdrawn_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `user_consents_user_id_foreign` (`user_id`),
  KEY `user_consents_user_id_consent_type_status_index` (`user_id`,`consent_type`,`status`),
  KEY `user_consents_consent_type_policy_version_index` (`consent_type`,`policy_version`),
  KEY `user_consents_created_at_index` (`created_at`),
  CONSTRAINT `user_consents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'viewer',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `activation_cancellation_reason` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activation_cancellation_details` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activation_cancelled_at` timestamp NULL DEFAULT NULL,
  `activation_cancelled_by` bigint unsigned DEFAULT NULL,
  `activation_cancellation_notice_sent_at` timestamp NULL DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `archived_by` bigint unsigned DEFAULT NULL,
  `archive_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_protected` tinyint(1) NOT NULL DEFAULT '0',
  `mfa_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `session_timeout_reminder_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `authenticator_secret` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `authenticator_enabled_at` timestamp NULL DEFAULT NULL,
  `employee_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_blind_index` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` tinyint unsigned NOT NULL DEFAULT '0',
  `last_failed_login_at` timestamp NULL DEFAULT NULL,
  `login_retry_at` timestamp NULL DEFAULT NULL,
  `login_locked_until` timestamp NULL DEFAULT NULL,
  `login_lockout_count` int unsigned NOT NULL DEFAULT '0',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_changed_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `surname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `middle_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sms_mfa_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `sms_mfa_phone` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_role_status_index` (`role`,`status`),
  UNIQUE KEY `users_employee_id_unique` (`employee_id`),
  KEY `users_login_locked_until_index` (`login_locked_until`),
  KEY `users_archived_by_foreign` (`archived_by`),
  KEY `users_status_archived_at_index` (`status`,`archived_at`),
  KEY `users_phone_blind_index_index` (`phone_blind_index`),
  UNIQUE KEY `users_phone_blind_index_unique` (`phone_blind_index`),
  KEY `users_activation_cancelled_by_foreign` (`activation_cancelled_by`),
  CONSTRAINT `users_archived_by_foreign` FOREIGN KEY (`archived_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `users_activation_cancelled_by_foreign` FOREIGN KEY (`activation_cancelled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `warehouse_exceptions`
--

DROP TABLE IF EXISTS `warehouse_exceptions`;
CREATE TABLE `warehouse_exceptions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `exception_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_task_id` bigint unsigned DEFAULT NULL,
  `exception_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `storage_location_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `quantity` int unsigned DEFAULT NULL,
  `details` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `raised_by_id` bigint unsigned DEFAULT NULL,
  `assigned_to_id` bigint unsigned DEFAULT NULL,
  `resolution` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved_by_id` bigint unsigned DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `warehouse_exceptions_warehouse_task_id_foreign` (`warehouse_task_id`),
  KEY `warehouse_exceptions_storage_location_id_foreign` (`storage_location_id`),
  KEY `warehouse_exceptions_item_id_foreign` (`item_id`),
  KEY `warehouse_exceptions_raised_by_id_foreign` (`raised_by_id`),
  KEY `warehouse_exceptions_assigned_to_id_foreign` (`assigned_to_id`),
  KEY `warehouse_exceptions_resolved_by_id_foreign` (`resolved_by_id`),
  KEY `warehouse_exceptions_status_priority_created_at_index` (`status`,`priority`,`created_at`),
  UNIQUE KEY `warehouse_exceptions_exception_number_unique` (`exception_number`),
  CONSTRAINT `warehouse_exceptions_warehouse_task_id_foreign` FOREIGN KEY (`warehouse_task_id`) REFERENCES `warehouse_tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_exceptions_storage_location_id_foreign` FOREIGN KEY (`storage_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_exceptions_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_exceptions_raised_by_id_foreign` FOREIGN KEY (`raised_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_exceptions_assigned_to_id_foreign` FOREIGN KEY (`assigned_to_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_exceptions_resolved_by_id_foreign` FOREIGN KEY (`resolved_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `warehouse_label_prints`
--

DROP TABLE IF EXISTS `warehouse_label_prints`;
CREATE TABLE `warehouse_label_prints` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `label_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` bigint unsigned NOT NULL,
  `template` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` json NOT NULL,
  `copies` int unsigned NOT NULL DEFAULT '1',
  `printed_by_id` bigint unsigned DEFAULT NULL,
  `printed_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `warehouse_label_prints_printed_by_id_foreign` (`printed_by_id`),
  KEY `warehouse_label_prints_target_type_target_id_index` (`target_type`,`target_id`),
  UNIQUE KEY `warehouse_label_prints_label_number_unique` (`label_number`),
  CONSTRAINT `warehouse_label_prints_printed_by_id_foreign` FOREIGN KEY (`printed_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `warehouse_scan_events`
--

DROP TABLE IF EXISTS `warehouse_scan_events`;
CREATE TABLE `warehouse_scan_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `scan_identifier` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `warehouse_task_id` bigint unsigned DEFAULT NULL,
  `sequence_number` int unsigned DEFAULT NULL,
  `raw_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `symbology` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `resolved_type` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `resolved_id` bigint unsigned DEFAULT NULL,
  `outcome` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `scanned_by_id` bigint unsigned DEFAULT NULL,
  `idempotency_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `warehouse_scan_events_warehouse_task_id_foreign` (`warehouse_task_id`),
  KEY `warehouse_scan_events_scanned_by_id_foreign` (`scanned_by_id`),
  KEY `warehouse_scan_events_warehouse_task_id_sequence_number_index` (`warehouse_task_id`,`sequence_number`),
  KEY `warehouse_scan_events_outcome_created_at_index` (`outcome`,`created_at`),
  UNIQUE KEY `warehouse_scan_events_scan_identifier_unique` (`scan_identifier`),
  UNIQUE KEY `warehouse_scan_events_idempotency_key_unique` (`idempotency_key`),
  CONSTRAINT `warehouse_scan_events_warehouse_task_id_foreign` FOREIGN KEY (`warehouse_task_id`) REFERENCES `warehouse_tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_scan_events_scanned_by_id_foreign` FOREIGN KEY (`scanned_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `warehouse_task_events`
--

DROP TABLE IF EXISTS `warehouse_task_events`;
CREATE TABLE `warehouse_task_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `warehouse_task_id` bigint unsigned NOT NULL,
  `event_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `from_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `warehouse_task_events_warehouse_task_id_foreign` (`warehouse_task_id`),
  KEY `warehouse_task_events_actor_id_foreign` (`actor_id`),
  KEY `warehouse_task_events_warehouse_task_id_created_at_index` (`warehouse_task_id`,`created_at`),
  CONSTRAINT `warehouse_task_events_warehouse_task_id_foreign` FOREIGN KEY (`warehouse_task_id`) REFERENCES `warehouse_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `warehouse_task_events_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Table structure for table `warehouse_tasks`
--

DROP TABLE IF EXISTS `warehouse_tasks`;
CREATE TABLE `warehouse_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `task_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `task_type` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ready',
  `priority` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal',
  `source_location_id` bigint unsigned DEFAULT NULL,
  `destination_location_id` bigint unsigned DEFAULT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `item_batch_id` bigint unsigned DEFAULT NULL,
  `requested_quantity` int unsigned NOT NULL DEFAULT '0',
  `completed_quantity` int unsigned NOT NULL DEFAULT '0',
  `assigned_to_id` bigint unsigned DEFAULT NULL,
  `created_by_id` bigint unsigned DEFAULT NULL,
  `reference_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` bigint unsigned DEFAULT NULL,
  `idempotency_key` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `recommendation_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `override_reason` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  KEY `warehouse_tasks_source_location_id_foreign` (`source_location_id`),
  KEY `warehouse_tasks_destination_location_id_foreign` (`destination_location_id`),
  KEY `warehouse_tasks_item_id_foreign` (`item_id`),
  KEY `warehouse_tasks_item_batch_id_foreign` (`item_batch_id`),
  KEY `warehouse_tasks_assigned_to_id_foreign` (`assigned_to_id`),
  KEY `warehouse_tasks_created_by_id_foreign` (`created_by_id`),
  KEY `warehouse_tasks_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  KEY `warehouse_tasks_status_priority_due_at_index` (`status`,`priority`,`due_at`),
  KEY `warehouse_tasks_assigned_to_id_status_index` (`assigned_to_id`,`status`),
  KEY `warehouse_tasks_task_type_status_index` (`task_type`,`status`),
  UNIQUE KEY `warehouse_tasks_task_number_unique` (`task_number`),
  UNIQUE KEY `warehouse_tasks_idempotency_key_unique` (`idempotency_key`),
  CONSTRAINT `warehouse_tasks_source_location_id_foreign` FOREIGN KEY (`source_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_tasks_destination_location_id_foreign` FOREIGN KEY (`destination_location_id`) REFERENCES `storage_locations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_tasks_item_id_foreign` FOREIGN KEY (`item_id`) REFERENCES `inventory_items` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_tasks_item_batch_id_foreign` FOREIGN KEY (`item_batch_id`) REFERENCES `item_batches` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_tasks_assigned_to_id_foreign` FOREIGN KEY (`assigned_to_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `warehouse_tasks_created_by_id_foreign` FOREIGN KEY (`created_by_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Supplier portal workflow schema
--

ALTER TABLE `users`
    ADD COLUMN `supplier_id` BIGINT UNSIGNED NULL AFTER `id`,
    ADD CONSTRAINT `users_supplier_id_foreign`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
        ON DELETE RESTRICT,
    ADD INDEX `users_supplier_id_role_status_index`
        (`supplier_id`, `role`, `status`);

CREATE TABLE `purchase_order_acknowledgements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `purchase_order_id` BIGINT UNSIGNED NOT NULL,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `responded_by` BIGINT UNSIGNED NOT NULL,
    `response` VARCHAR(30) NOT NULL,
    `exception_type` VARCHAR(50) NULL,
    `message` TEXT NULL,
    `responded_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    INDEX `purchase_order_acknowledgements_supplier_id_responded_at_index`
        (`supplier_id`, `responded_at`),
    CONSTRAINT `purchase_order_acknowledgements_purchase_order_id_foreign`
        FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `purchase_order_acknowledgements_supplier_id_foreign`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
        ON DELETE RESTRICT,
    CONSTRAINT `purchase_order_acknowledgements_responded_by_foreign`
        FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT
);

CREATE TABLE `shipment_line_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `shipment_id` BIGINT UNSIGNED NOT NULL,
    `po_line_id` BIGINT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `lot_number` VARCHAR(100) NULL,
    `serial_number` VARCHAR(100) NULL,
    `expiry_date` DATE NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `shipment_line_items_shipment_id_po_line_id_unique`
        (`shipment_id`, `po_line_id`),
    CONSTRAINT `shipment_line_items_shipment_id_foreign`
        FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `shipment_line_items_po_line_id_foreign`
        FOREIGN KEY (`po_line_id`) REFERENCES `po_line_items` (`id`)
        ON DELETE RESTRICT
);

CREATE TABLE `supplier_discrepancies` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `grn_line_item_id` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'open',
    `supplier_response_type` VARCHAR(50) NULL,
    `supplier_response` TEXT NULL,
    `responded_by` BIGINT UNSIGNED NULL,
    `responded_at` TIMESTAMP NULL,
    `resolution` TEXT NULL,
    `resolved_by` BIGINT UNSIGNED NULL,
    `resolved_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `supplier_discrepancies_grn_line_item_id_unique`
        (`grn_line_item_id`),
    INDEX `supplier_discrepancies_supplier_id_status_index`
        (`supplier_id`, `status`),
    CONSTRAINT `supplier_discrepancies_supplier_id_foreign`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
        ON DELETE RESTRICT,
    CONSTRAINT `supplier_discrepancies_grn_line_item_id_foreign`
        FOREIGN KEY (`grn_line_item_id`) REFERENCES `grn_line_items` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `supplier_discrepancies_responded_by_foreign`
        FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `supplier_discrepancies_resolved_by_foreign`
        FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL
);

CREATE TABLE `supplier_invoices` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_id` BIGINT UNSIGNED NOT NULL,
    `purchase_order_id` BIGINT UNSIGNED NOT NULL,
    `invoice_number` VARCHAR(80) NOT NULL,
    `invoice_date` DATE NOT NULL,
    `total_amount` DECIMAL(14,2) NOT NULL,
    `status` VARCHAR(30) NOT NULL DEFAULT 'submitted',
    `match_notes` TEXT NULL,
    `submitted_by` BIGINT UNSIGNED NOT NULL,
    `submitted_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `supplier_invoices_supplier_id_invoice_number_unique`
        (`supplier_id`, `invoice_number`),
    INDEX `supplier_invoices_supplier_id_status_index`
        (`supplier_id`, `status`),
    CONSTRAINT `supplier_invoices_supplier_id_foreign`
        FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`)
        ON DELETE RESTRICT,
    CONSTRAINT `supplier_invoices_purchase_order_id_foreign`
        FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders` (`id`)
        ON DELETE RESTRICT,
    CONSTRAINT `supplier_invoices_submitted_by_foreign`
        FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`)
        ON DELETE RESTRICT
);

CREATE TABLE `supplier_invoice_lines` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `supplier_invoice_id` BIGINT UNSIGNED NOT NULL,
    `po_line_id` BIGINT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `unit_price` DECIMAL(12,2) NOT NULL,
    `line_total` DECIMAL(14,2) NOT NULL,
    `match_status` VARCHAR(30) NOT NULL DEFAULT 'pending',
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `supplier_invoice_lines_supplier_invoice_id_po_line_id_unique`
        (`supplier_invoice_id`, `po_line_id`),
    CONSTRAINT `supplier_invoice_lines_supplier_invoice_id_foreign`
        FOREIGN KEY (`supplier_invoice_id`) REFERENCES `supplier_invoices` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `supplier_invoice_lines_po_line_id_foreign`
        FOREIGN KEY (`po_line_id`) REFERENCES `po_line_items` (`id`)
        ON DELETE RESTRICT
);

ALTER TABLE `supplier_products`
    ADD COLUMN `gtin` VARCHAR(14) NULL AFTER `supplier_sku`,
    ADD COLUMN `approval_status` VARCHAR(30) NOT NULL DEFAULT 'approved'
        AFTER `is_active`,
    ADD COLUMN `vmi_enabled` TINYINT(1) NOT NULL DEFAULT 0
        AFTER `approval_status`,
    ADD COLUMN `vmi_min` INT UNSIGNED NULL AFTER `vmi_enabled`,
    ADD COLUMN `vmi_max` INT UNSIGNED NULL AFTER `vmi_min`,
    ADD INDEX `supplier_products_supplier_id_approval_status_index`
        (`supplier_id`, `approval_status`);

--
-- Supplier company profile workflow schema
--

ALTER TABLE `suppliers`
    ADD COLUMN `company_profile_status` VARCHAR(30) NOT NULL DEFAULT 'draft'
        AFTER `accreditation_status`,
    ADD COLUMN `company_profile_draft` LONGTEXT NULL
        AFTER `company_profile_status`,
    ADD COLUMN `company_profile_feedback` TEXT NULL
        AFTER `company_profile_draft`,
    ADD COLUMN `company_profile_submitted_at` TIMESTAMP NULL
        AFTER `company_profile_feedback`,
    ADD COLUMN `company_profile_reviewed_at` TIMESTAMP NULL
        AFTER `company_profile_submitted_at`,
    ADD COLUMN `company_profile_reviewed_by` BIGINT UNSIGNED NULL
        AFTER `company_profile_reviewed_at`,
    ADD INDEX `suppliers_company_profile_status_index`
        (`company_profile_status`),
    ADD CONSTRAINT `suppliers_company_profile_reviewed_by_foreign`
        FOREIGN KEY (`company_profile_reviewed_by`) REFERENCES `users` (`id`)
        ON DELETE SET NULL;

UPDATE `suppliers`
SET `company_profile_status` = CASE `accreditation_status`
    WHEN 'approved' THEN 'approved'
    WHEN 'pending_review' THEN 'pending_review'
    WHEN 'rejected' THEN 'rejected'
    ELSE `company_profile_status`
END;

--
-- Invitation-only supplier onboarding schema
--

DROP TABLE IF EXISTS `supplier_invitations`;
CREATE TABLE `supplier_invitations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `supplier_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `invited_by` bigint unsigned DEFAULT NULL,
  `revoked_by` bigint unsigned DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `delivery_status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `delivery_attempts` smallint unsigned NOT NULL DEFAULT '0',
  `invited_at` timestamp NOT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivery_failed_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) /*T![clustered_index] CLUSTERED */,
  UNIQUE KEY `supplier_invitations_user_id_unique` (`user_id`),
  UNIQUE KEY `supplier_invitations_token_hash_unique` (`token_hash`),
  KEY `supplier_invitations_supplier_id_status_index` (`supplier_id`,`status`),
  KEY `supplier_invitations_status_expires_at_index` (`status`,`expires_at`),
  KEY `supplier_invitations_invited_by_foreign` (`invited_by`),
  KEY `supplier_invitations_revoked_by_foreign` (`revoked_by`),
  CONSTRAINT `supplier_invitations_supplier_id_foreign` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `supplier_invitations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_invitations_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_invitations_revoked_by_foreign` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Restore global state
-- =====================================================================

+
-- Remove lifecycle rows retired with SupplyChainTurnaroundDemoSeeder.
DELETE FROM `inspection_acceptance_reports`
WHERE `iar_number` LIKE 'IAR-REV-2026-Q3-%' OR `iar_number` LIKE 'IAR-REV-2026-Q4-%';
DELETE FROM `grn_line_items`
WHERE `goods_receipt_note_id` IN (
  SELECT `id` FROM `goods_receipt_notes`
  WHERE `grn_number` LIKE 'GRN-REV-2026-Q3-%' OR `grn_number` LIKE 'GRN-REV-2026-Q4-%'
);
DELETE FROM `goods_receipt_notes`
WHERE `grn_number` LIKE 'GRN-REV-2026-Q3-%' OR `grn_number` LIKE 'GRN-REV-2026-Q4-%';
DELETE FROM `po_line_items`
WHERE `purchase_order_id` IN (
  SELECT `id` FROM `purchase_orders`
  WHERE `po_number` LIKE 'PO-REV-2026-Q3-%' OR `po_number` LIKE 'PO-REV-2026-Q4-%'
);
DELETE FROM `purchase_orders`
WHERE `po_number` LIKE 'PO-REV-2026-Q3-%' OR `po_number` LIKE 'PO-REV-2026-Q4-%';
DELETE FROM `rfq_line_items`
WHERE `sourcing_rfq_id` IN (
  SELECT `id` FROM `sourcing_rfqs`
  WHERE `rfq_number` LIKE 'RFQ-REV-2026-Q3-%' OR `rfq_number` LIKE 'RFQ-REV-2026-Q4-%'
);
DELETE FROM `sourcing_rfqs`
WHERE `rfq_number` LIKE 'RFQ-REV-2026-Q3-%' OR `rfq_number` LIKE 'RFQ-REV-2026-Q4-%';
DELETE FROM `pr_line_items`
WHERE `purchase_request_id` IN (
  SELECT `id` FROM `purchase_requests`
  WHERE `pr_number` LIKE 'PR-REV-2026-Q3-%' OR `pr_number` LIKE 'PR-REV-2026-Q4-%'
);
DELETE FROM `purchase_requests`
WHERE `pr_number` LIKE 'PR-REV-2026-Q3-%' OR `pr_number` LIKE 'PR-REV-2026-Q4-%';

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
