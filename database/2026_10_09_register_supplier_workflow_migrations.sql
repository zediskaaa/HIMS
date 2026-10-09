-- HIMS migration-history synchronization
-- Target: MySQL 8 / TiDB
--
-- IMPORTANT: Run this only after the corresponding Laravel migrations or
-- schema SQL have already created the required tables and columns. This file
-- records migration history; it does not create the supplier workflow schema.
-- Migration IDs are intentionally auto-generated because IDs 90-93 may already
-- be occupied in another HIMS database. Laravel identifies migrations by name.

INSERT INTO `migrations` (`migration`, `batch`)
SELECT `source`.`migration`, `source`.`batch`
FROM (
    SELECT '2026_10_06_000001_add_supplier_portal_workflow' AS `migration`, 1 AS `batch`
    UNION ALL
    SELECT '2026_10_07_000001_assign_supplier_user_identifiers', 1
    UNION ALL
    SELECT '2026_10_09_000001_add_company_profile_workflow_to_suppliers', 1
    UNION ALL
    SELECT '2026_10_09_000002_create_supplier_invitations_table', 1
) AS `source`
LEFT JOIN `migrations` AS `existing`
    ON `existing`.`migration` = `source`.`migration`
WHERE `existing`.`id` IS NULL;

-- Verification: all four rows should be returned exactly once.
SELECT `id`, `migration`, `batch`
FROM `migrations`
WHERE `migration` IN (
    '2026_10_06_000001_add_supplier_portal_workflow',
    '2026_10_07_000001_assign_supplier_user_identifiers',
    '2026_10_09_000001_add_company_profile_workflow_to_suppliers',
    '2026_10_09_000002_create_supplier_invitations_table'
)
ORDER BY `migration`;
