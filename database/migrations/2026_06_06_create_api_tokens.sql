-- ═══════════════════════════════════════════════════════════════════════════
-- EMS · جدول توكنات الـ API (مصادقة Bearer للتطبيق الجوّال)
-- @date 2026-06-06
--
-- يُخزَّن هنا تجزئة (sha256) للتوكن لا التوكن نفسه. عند كل طلب يُرسل التطبيق
-- التوكن الخام في ترويسة Authorization: Bearer <token>، ويتحقق الخادم بمطابقة
-- تجزئته مع token_hash مع التأكد أن revoked = 0 وأن expires_at لم يمضِ.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `api_tokens` (
    `id`         INT(11)      NOT NULL AUTO_INCREMENT,
    `user_id`    INT(11)      NOT NULL,
    `token_hash` CHAR(64)     NOT NULL COMMENT 'sha256 hex للتوكن الخام',
    `device`     VARCHAR(150) DEFAULT NULL COMMENT 'وصف اختياري للجهاز/التطبيق',
    `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_used_at` DATETIME   DEFAULT NULL,
    `expires_at` DATETIME     DEFAULT NULL,
    `revoked`    TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_token_hash` (`token_hash`),
    KEY `idx_user` (`user_id`),
    KEY `idx_active` (`revoked`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
