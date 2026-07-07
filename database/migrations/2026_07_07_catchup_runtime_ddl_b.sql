-- ═══════════════════════════════════════════════════════════════════════════
-- 2026_07_07_catchup_runtime_ddl_b.sql — تكملة ترحيل اللحاق (المرحلة 0 · ADR-03)
--
-- عبارة واحدة أفلتت من الجرد الأول (نمط CREATE INDEX لا ALTER/CREATE TABLE):
-- فهرس عزل الشركة على equipments — من Equipments/equipments.php:27
-- وEquipments/equipments_drivers.php:64.
--
-- ملف منفصل لأن ملف اللحاق الأول مُطبَّق، وتعديل ملفٍ مُطبَّق ممنوع (checksum).
-- ═══════════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;

-- idx_equipments_company_id ON equipments (company_id)
SET @ddl = (SELECT IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='equipments' AND COLUMN_NAME='company_id')
    AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='equipments' AND INDEX_NAME='idx_equipments_company_id'),
    'CREATE INDEX idx_equipments_company_id ON equipments (company_id)', 'SELECT 1'));
PREPARE mig FROM @ddl; EXECUTE mig; DEALLOCATE PREPARE mig;
