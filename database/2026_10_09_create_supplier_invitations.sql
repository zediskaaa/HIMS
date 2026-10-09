-- HIMS incremental schema update
-- Feature: Invitation-only supplier registration and onboarding
-- Target: MySQL 8 / TiDB
-- Prerequisite: migration 2026_10_09_000001_add_company_profile_workflow_to_suppliers
-- Run this once against the existing HIMS database selected by your SQL client.

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
  `delivery_attempts` smallint unsigned NOT NULL DEFAULT 0,
  `invited_at` timestamp NOT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `delivery_failed_at` timestamp NULL DEFAULT NULL,
  `opened_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `supplier_invitations_user_id_unique` (`user_id`),
  UNIQUE KEY `supplier_invitations_token_hash_unique` (`token_hash`),
  KEY `supplier_invitations_supplier_id_status_index` (`supplier_id`, `status`),
  KEY `supplier_invitations_status_expires_at_index` (`status`, `expires_at`),
  KEY `supplier_invitations_invited_by_foreign` (`invited_by`),
  KEY `supplier_invitations_revoked_by_foreign` (`revoked_by`),
  CONSTRAINT `supplier_invitations_supplier_id_foreign`
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `supplier_invitations_user_id_foreign`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `supplier_invitations_invited_by_foreign`
    FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `supplier_invitations_revoked_by_foreign`
    FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keep Laravel's migration history synchronized when this SQL file is run
-- manually instead of through `php artisan migrate`.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT
  '2026_10_09_000002_create_supplier_invitations_table',
  `migration_batches`.`next_batch`
FROM (
  SELECT COALESCE(MAX(`batch`), 0) + 1 AS `next_batch`
  FROM `migrations`
) AS `migration_batches`
WHERE NOT EXISTS (
  SELECT 1
  FROM `migrations`
  WHERE `migration` = '2026_10_09_000002_create_supplier_invitations_table'
);

-- Verification result should return one row with the new table name.
SELECT `TABLE_NAME`
FROM `information_schema`.`TABLES`
WHERE `TABLE_SCHEMA` = DATABASE()
  AND `TABLE_NAME` = 'supplier_invitations';
