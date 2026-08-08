-- ═══════════════════════════════════════════════════════════════════════════
-- 2026_12_08_u12_guard_denials_nav_repoint.sql
-- «المحاولاتُ الممنوعة» تشير إلى شاشتِها لا إلى سجلِّ التدقيقِ العام
-- ───────────────────────────────────────────────────────────────────────────
-- المخالفةُ الأخيرةُ في فاحصِ المطابقة (tools/nav09_verify.php): عنصرُ التنقّل
-- «المحاولات الممنوعة» لدور الحوكمة (15) يشير إلى `admin/audit_log.php` بينما
-- الوثيقةُ تجعل وجهتَه `Governance/guard_denials.php` — الشاشةَ التي وُلدت في
-- هذه الحقبة لمراجعةِ المنعِ (M-14 · مراجعةُ الحجب).
--
-- والفرقُ حقيقيٌّ لا تسمويّ: سجلُّ التدقيقِ العامُّ يعرض كلَّ الوقائع، وشاشةُ
-- المحاولاتِ الممنوعةِ تعرض ما رفضه الحارسُ وتصنّفه أربعةَ أصناف. فالإشارةُ
-- للأولى تُغرق مراجعَ المنعِ في ضجيجٍ ليس عملَه.
--
-- عكسُه: إعادةُ route/module_id/permission_code إلى admin/audit_log.php.
-- ═══════════════════════════════════════════════════════════════════════════

UPDATE nav_items ni
  JOIN modules mo ON mo.code = 'Governance/guard_denials.php'
   SET ni.route = 'Governance/guard_denials.php',
       ni.module_id = mo.id,
       ni.permission_code = 'Governance/guard_denials.php'
 WHERE ni.active = 1
   AND ni.label_ar = 'المحاولات الممنوعة'
   AND ni.route = 'admin/audit_log.php';
