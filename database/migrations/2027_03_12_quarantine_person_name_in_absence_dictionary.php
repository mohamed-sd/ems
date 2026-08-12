<?php
/**
 * 2027_03_12 — اسمُ موظفٍ حقيقيٍّ جالسٌ في قاموسِ رموزِ الحضور
 * ═══════════════════════════════════════════════════════════════════════════
 * **المقيسُ**: `payroll_absence_types` فيه للشركة 4 **اثنا عشرَ** رمزًا حيًّا،
 * والأحدَ عشرَ المعلَنةَ (`1 · 0 · 10 · 11 · ST · S · M · A1 · A2 · EM · UP`)
 * أُنشئت كلُّها في لحظةٍ واحدةٍ (2026-08-01 14:40:57). والثانيَ عشرَ صفٌّ شاذٌّ:
 *
 *     #133 · code = 'PAYR' · label_ar = 'أنس الفاتح إبراهيم' · 2025-02-04
 *
 * و«أنس الفاتح إبراهيم» **موظفٌ حقيقيٌّ (#60)** — أي أن **اسمَ شخصٍ صار عنوانَ
 * رمزِ غيابٍ**. وهذا توقيعُ إدراجٍ **منزاحِ الأعمدة** (كالعطبِ المعروفِ في هذا
 * المستودع: `bind_param` منزاحٌ حرفين محا 14 نصَّ بلاغ). وأثرُه ليس نظريًّا:
 * كلُّ شاشةٍ تسرد رموزَ الحضورِ الحيّةَ تعرض **اسمَ موظفٍ خيارًا** يُختار.
 *
 * **والصفُّ معزولٌ تمامًا** — مقيسٌ: لا مفتاحَ أجنبيًّا يشير إلى الجدول أصلًا،
 * ومسحُ كلِّ أعمدةِ النصِّ القصيرةِ في القاعدةِ (بمقارنةٍ ثنائيةٍ) وجد «PAYR»
 * **في هذا الصفِّ وحدَه** — فلا واقعةَ حضورٍ ولا احتساب يستند إليه.
 *
 * ⇒ **يُعزَل بـ`active = 0` لا يُحذف**: القاموسُ بيانٌ مرجعيٌّ، وإخفاءُ الشاذِّ
 *   يكفي لإصلاحِ ما يُعرَض، وإبقاءُ الصفِّ يحفظ أثرَ العطبِ لمن يحقّق في جذرِه.
 *   ولا يُعاد استعمالُ الرمزِ: يُوسَم عنوانُه بأنه معزولٌ ليُقرأ سببُه من الصفِّ.
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once dirname(__DIR__, 2) . '/includes/env.php';

$db = @new mysqli(ems_env('DB_HOST'), ems_env('DB_MIGRATOR_USER'),
                  ems_env('DB_MIGRATOR_PASS'), ems_env('DB_NAME'));
if ($db->connect_errno) { fwrite(STDERR, 'اتصال: ' . $db->connect_error . "\n"); exit(1); }
$db->set_charset('utf8mb4');
$one = function ($sql) use ($db) { $r = $db->query($sql); return $r ? $r->fetch_row()[0] : null; };

/** الأحدَ عشرَ المعلَنةَ — مصدرُ الحقيقةِ لِما يجوز أن يكون حيًّا */
$DECLARED = array('1', '0', '10', '11', 'ST', 'S', 'M', 'A1', 'A2', 'EM', 'UP');
$inDecl = "'" . implode("','", $DECLARED) . "'";

/* ── ① الشاذُّ يُحدَّد بالمعلَنِ لا بالاسمِ ────────────────────────────────── */
$rows = array();
$r = $db->query("SELECT id, code, label_ar, active FROM payroll_absence_types
                  WHERE company_id = 4 AND code IS NOT NULL AND active = 1
                    AND code NOT IN ({$inDecl}) ORDER BY id");
while ($r && ($x = $r->fetch_assoc())) { $rows[] = $x; }
echo '── ① رموزٌ حيةٌ خارجَ المعلَن: ' . count($rows) . "\n";
foreach ($rows as $x) { echo '     #' . $x['id'] . " «{$x['code']}» — {$x['label_ar']}\n"; }
if (!$rows) { echo "\n✅ القاموسُ نظيفٌ — لا عمل.\n"; exit(0); }

/* ── ② لكلِّ شاذٍّ: يُثبَت عزلُه قبل إخفائه ───────────────────────────────── */
$done = 0;
foreach ($rows as $x) {
    $id   = (int) $x['id'];
    $code = (string) $x['code'];
    $safeCode = $db->real_escape_string($code);

    /* أهو اسمُ شخصٍ حقيقيّ؟ يُعلَن ولا يُشترط — العلّةُ أنه خارجَ المعلَن */
    $emp = (int) $one("SELECT COUNT(*) FROM employees
                        WHERE name = '" . $db->real_escape_string((string) $x['label_ar']) . "'");
    echo "── ② #{$id}: عنوانُه يطابق اسمَ موظفٍ حقيقيٍّ؟ " . ($emp > 0 ? "نعم ({$emp})" : 'لا') . "\n";

    /* أيُّ استعمالٍ للرمزِ في القاعدة؟ يُمسح مسحًا ثنائيًّا على الأعمدة القصيرة */
    $uses = 0;
    $cr = $db->query("SELECT TABLE_NAME t, COLUMN_NAME c FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE IN ('varchar','char')
                         AND CHARACTER_MAXIMUM_LENGTH <= 32 AND TABLE_NAME NOT LIKE 'v\\_%'
                         AND TABLE_NAME <> 'payroll_absence_types'");
    while ($cr && ($cx = $cr->fetch_assoc())) {
        $q = $db->query("SELECT COUNT(*) FROM `{$cx['t']}` WHERE BINARY `{$cx['c']}` = '{$safeCode}'");
        if ($q) { $uses += (int) $q->fetch_row()[0]; }
    }
    echo "     مواضعُ استعمالِ الرمزِ خارجَ القاموس: {$uses}\n";
    if ($uses > 0) {
        fwrite(STDERR, "     الرمزُ مستعملٌ — لا يُعزَل بلا قرارٍ خاصّ. تُخطّى.\n");
        continue;
    }

    $ok = $db->query("UPDATE payroll_absence_types
                         SET active = 0,
                             label_ar = CONCAT('[معزول — قيدٌ منزاحُ الأعمدة] ', label_ar)
                       WHERE id = {$id} AND active = 1");
    if ($ok === false) { fwrite(STDERR, '     عزلٌ فشل: ' . $db->error . "\n"); continue; }
    $done++;
    echo "     ✔ عُزل (active = 0) ووُسم عنوانُه بسببه\n";
}

/* ── ③ الشاهد ─────────────────────────────────────────────────────────────── */
$live = (int) $one("SELECT COUNT(*) FROM payroll_absence_types
                     WHERE company_id = 4 AND code IS NOT NULL AND active = 1");
$stray = (int) $one("SELECT COUNT(*) FROM payroll_absence_types
                      WHERE company_id = 4 AND code IS NOT NULL AND active = 1
                        AND code NOT IN ({$inDecl})");
echo "── ③ رموزٌ حيةٌ الآن: {$live} · وخارجَ المعلَن: {$stray}\n";
if ($stray !== 0) { fwrite(STDERR, "بقي شاذٌّ حيٌّ — لم يكتمل\n"); exit(1); }
if ($live !== 11) { fwrite(STDERR, "الحيُّ {$live} لا 11 — راجِع القاموس\n"); exit(1); }

echo "\n✅ عُزل {$done} رمزًا شاذًّا · والقاموسُ أحدَ عشرَ رمزًا كما هو معلَن.\n";
exit(0);
