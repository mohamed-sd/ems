-- ═══════════════════════════════════════════════════════════════════════════
-- 2026_12_09_u12_sec_write_doors.sql
-- «لا كتابةَ بلا باب» — تصحيحُ ثلاثةَ عشرَ خرقًا في حزامِ الحوكمة
-- ───────────────────────────────────────────────────────────────────────────
-- كشفَ AC-GOV-02 (tools/sec_perm_checks.php) ثلاثةَ عشرَ دورًا يملك حقَّ كتابةٍ
-- على شاشةٍ لا بابَ لها في قوائمه. والخرقُ نوعان لا نوعٌ واحد، ولكلٍّ علاجُه
-- الصحيحُ بحسبِ ما تقوله الوثيقة — لا بحسبِ ما يُسكت الفاحص:
--
-- ① كتابةٌ حقيقيةٌ بلا باب ⇒ يُفتح البابُ (لا يُنزع الحق).
--    `Risk/risk_field.php` — «حقُّ المُبلِّغِ الميدانيِّ ثلاثةٌ: يرفع إشارةً ·
--    يسجّل دليلَ تنفيذِ ضابطٍ يملكه · يقدّم دليلَ إنجازِ إجراءٍ مسنَدٍ إليه»
--    (M-16 §14-4). فالأدوارُ التشغيليةُ 1 · 5 · 6 · 24 تكتب فيها بحقٍّ منصوصٍ،
--    وغيابُ البابِ عيبُ تنقّلٍ لا عيبُ صلاحية.
--
-- ② حقُّ كتابةٍ على شاشةٍ لا تكتب أصلًا ⇒ يُقوَّم الحقُّ إلى قراءة.
--    `Risk/risk_dept_fin.php` — «حقُّ الإدارة: قراءةٌ» نصًّا في رأس الملف، وصفرُ
--    مسارِ كتابةٍ في شيفرتها (25 سطرًا عرضًا نطاقيًّا).
--    `Risk/risk_incidents.php` — سجلُّ الوقائعِ تملكه إدارةُ المخاطر (28 · 29)،
--    والأدوارُ التشغيليةُ ترفع إشاراتِها عبر risk_field لا بالقيدِ المباشر
--    («يُنشئ إشارةً لا خطرًا، تدخل الفرزَ ولا تُسجَّل خطرًا مباشرةً»).
--    فحقُّ الإضافةِ هنا فائضُ منحٍ من جولةِ التسجيل، لا حقٌّ منصوص.
--
-- لا يُحذف صفٌّ واحد: العلاجُ فتحُ بابٍ أو خفضُ رايةٍ إلى صفر — وكلاهما يُعكَس
-- بعبارةٍ واحدة.
--
-- عكسُه: حذفُ صفوفِ nav_items الأربعةِ الجديدة · وإعادةُ can_add = 1 للأدوار
-- المذكورةِ في الجدولين.
-- ═══════════════════════════════════════════════════════════════════════════

-- ① بابُ «مخاطرُ الميدان» للأدوارِ التشغيليةِ التي تكتب فيه بحقٍّ منصوص
INSERT INTO nav_items (role_id, door, group_id, module_id, label_ar, route, icon, sort_order, permission_code, active)
SELECT x.role_id, 'RISK', x.group_id, mo.id, 'مخاطرُ الميدان',
       'Risk/risk_field.php', 'fa fa-triangle-exclamation', 95, 'Risk/risk_field.php', 1
  FROM (
        SELECT n.role_id, MIN(n.group_id) AS group_id
          FROM nav_items n
         WHERE n.active = 1 AND n.door = 'RISK' AND n.role_id IN (1, 5, 6, 24)
         GROUP BY n.role_id
       ) x
  CROSS JOIN modules mo
 WHERE mo.code = 'Risk/risk_field.php'
   AND NOT EXISTS (
        SELECT 1 FROM nav_items n2
         WHERE n2.role_id = x.role_id AND n2.active = 1
           AND n2.route LIKE 'Risk/risk_field.php%');

-- ② خفضُ حقِّ الإضافةِ على شاشةٍ عرضٍ نطاقيٍّ بلا مسارِ كتابة
UPDATE role_permissions rp
  JOIN modules mo ON mo.id = rp.module_id
   SET rp.can_add = 0
 WHERE mo.code = 'Risk/risk_dept_fin.php'
   AND rp.role_id IN (17, 18, 19, 20, 21, 22)
   AND rp.can_add = 1;

-- ③ خفضُ حقِّ الإضافةِ على سجلِّ الوقائعِ لغيرِ مالكِه — الإشارةُ طريقُهم
UPDATE role_permissions rp
  JOIN modules mo ON mo.id = rp.module_id
   SET rp.can_add = 0
 WHERE mo.code = 'Risk/risk_incidents.php'
   AND rp.role_id IN (1, 5, 6, 24)
   AND rp.can_add = 1;
