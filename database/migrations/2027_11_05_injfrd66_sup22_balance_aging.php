<?php
/**
 * 2027_11_05_injfrd66_sup22_balance_aging.php
 *   SUP-22 — أعمارُ الأرصدةِ والالتزامات: **منظرٌ مشتقٌّ لا جدولُ إدخال**
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **المتطلب**: «الرصيدُ القائمُ لكلِّ موردٍ × عملةٍ بشرائحِ التقادمِ
 *   والالتزاماتِ القادمة». **ومعيارُه**: «كلُّ رصيدٍ له شريحةُ عمرٍ وإجراءٌ
 *   مقترح». **وموضعُه**: «قدرةٌ مفقودة — **منظرٌ** في التسويات».
 *
 * ◆ **ولذلك VIEW لا TABLE**: جدولٌ يُملأ يتقادمُ ويكذب، ويفتح بابَ إدخالٍ
 *   في سطحٍ نصُّه «منظر». والمنظرُ يُحسب لحظةَ القراءةِ من مصدرِه —
 *   فلا يحتاج مزامنةً ولا وظيفةَ كنسٍ ولا يخالف مصدرَه أبدًا.
 *   ⇐ و«صفرُ إدخال» يصير **خاصيةً بنيويةً** لا قاعدةً تُراقَب.
 *
 * ◆ **والمصدرُ مقيسٌ قبلَ البناء**: 252 تسويةَ موردٍ غيرَ مدفوعةٍ
 *   (`approved` و`payment_requested` · `paid_at IS NULL`) بـ4,983,621.35
 *   في 40 موردًا. فالمنظرُ يقوم على بياناتٍ حيّةٍ لا على فراغ.
 *
 * ◆ **وحرفياتُ `CASE` تُثبَّت على ترتيبِ المخطَّط** (`COLLATE utf8mb4_unicode_ci`):
 *   نصٌّ مكتوبٌ في الاستعلامِ يأخذ ترتيبَ **اتصالِ العميل**، فيصير عمودُ
 *   المنظرِ `general_ci` والمخطَّطُ `unicode_ci`. وأيُّ مقارنةٍ بعدَها تُلقي
 *   «Illegal mix of collations» — **من عميلٍ دونَ عميل**: نجحت من سطرِ
 *   الأوامرِ ورسبت من PHP. وعطبٌ يظهر في بيئةٍ ويختفي في أخرى أخطرُ من
 *   عطبٍ ثابت.
 *
 * ◆ **ولا DEFINER**: يمنع استعادةَ الدمبِ على مضيفٍ آخر (قاعدةُ المستودع).
 *
 * ◆ **والمسوَّداتُ تُستبعد**: 215 مسوَّدةً تحمل `period_to` بعدَ 2030 (وأقصاها
 *   2091) — تواريخُ غيرُ واقعيةٍ تُفسد كلَّ شريحةِ عمرٍ لو دخلت. والرصيدُ
 *   **المستحقُّ** ما اعتُمد، لا ما زال مسوَّدة.
 *
 * التشغيل:  php database/migrations/2027_11_05_injfrd66_sup22_balance_aging.php
 * الرجوع :  php database/migrations/2027_11_05_injfrd66_sup22_balance_aging.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
require_once __DIR__ . '/_ledger.php';

$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$VIEW = 'v_supplier_balance_aging';

if (in_array('--revert', $argv, true)) {
    $conn->query("DROP VIEW IF EXISTS `{$VIEW}`");
    echo "↺ أُسقط المنظر {$VIEW}\n";
    exit(0);
}

/* ── ① قياسُ المصدرِ قبلَ البناء — لا يُبنى منظرٌ على فراغ ──────────────── */
$q = $conn->query("SELECT COUNT(*) n, COUNT(DISTINCT party_ref) sup, SUM(net_amount) amt
                     FROM `settlements`
                    WHERE is_deleted = 0 AND party_type = 'supplier'
                      AND state IN ('approved','payment_requested') AND paid_at IS NULL");
$src = $q ? $q->fetch_assoc() : null;
if (!$src || (int) $src['n'] === 0) {
    fwrite(STDERR, "✘ صفرُ تسويةٍ مستحقّةٍ — لا يُبنى منظرُ أعمارٍ على فراغ\n");
    exit(1);
}
printf("① المصدر: %d تسويةً مستحقّةً · %d موردًا · %s\n",
    (int) $src['n'], (int) $src['sup'], number_format((float) $src['amt'], 2));

/* ── ② المنظر ─────────────────────────────────────────────────────────── */
$conn->query("DROP VIEW IF EXISTS `{$VIEW}`");

/* شرائحُ التقادمِ من `period_to` — تاريخُ استحقاقِ الفترةِ لا تاريخُ الإنشاء */
/* `SQL SECURITY INVOKER` **في جملةِ الإنشاءِ نفسِها**: منظرٌ بحقوقِ منشئِه
   يفشل عندَ الاستعادةِ على مضيفٍ لا يعرف ذلك المستخدمَ — وهي عثرةٌ مقيَّدةٌ
   في قاعدةِ المستودع. وتصحيحُه بعدَ الإنشاءِ لا يعمل: ALTER لا يغيّر النوعَ
   ما لم يُصرَّح به. */
$sql = "CREATE SQL SECURITY INVOKER VIEW `{$VIEW}` AS
SELECT
    s.company_id                                   AS company_id,
    s.party_ref                                    AS supplier_id,
    MAX(su.name)                                   AS supplier_name,
    MAX(su.supplier_code)                          AS supplier_code,
    s.currency                                     AS currency,

    COUNT(*)                                       AS open_settlements,
    SUM(s.net_amount)                              AS outstanding_amount,
    MIN(s.period_to)                               AS oldest_period_to,
    MAX(s.period_to)                               AS newest_period_to,
    MAX(DATEDIFF(CURDATE(), s.period_to))          AS oldest_age_days,

    SUM(CASE WHEN DATEDIFF(CURDATE(), s.period_to) <=  30 THEN s.net_amount ELSE 0 END) AS bucket_0_30,
    SUM(CASE WHEN DATEDIFF(CURDATE(), s.period_to) BETWEEN  31 AND  60 THEN s.net_amount ELSE 0 END) AS bucket_31_60,
    SUM(CASE WHEN DATEDIFF(CURDATE(), s.period_to) BETWEEN  61 AND  90 THEN s.net_amount ELSE 0 END) AS bucket_61_90,
    SUM(CASE WHEN DATEDIFF(CURDATE(), s.period_to) BETWEEN  91 AND 180 THEN s.net_amount ELSE 0 END) AS bucket_91_180,
    SUM(CASE WHEN DATEDIFF(CURDATE(), s.period_to) >  180 THEN s.net_amount ELSE 0 END) AS bucket_over_180,

    SUM(CASE WHEN s.state = 'payment_requested' THEN s.net_amount ELSE 0 END) AS requested_amount,
    SUM(CASE WHEN s.state = 'approved'          THEN s.net_amount ELSE 0 END) AS approved_amount,
    SUM(CASE WHEN s.open_objections > 0 THEN 1 ELSE 0 END)                    AS with_objections,

    /* شريحةُ العمرِ — لكلِّ رصيدٍ شريحةٌ مسمّاةٌ لا رقمٌ مجرَّد */
    CASE
        WHEN MAX(DATEDIFF(CURDATE(), s.period_to)) >  180 THEN 'أكثر من ١٨٠ يومًا'
        WHEN MAX(DATEDIFF(CURDATE(), s.period_to)) >   90 THEN '٩١–١٨٠ يومًا'
        WHEN MAX(DATEDIFF(CURDATE(), s.period_to)) >   60 THEN '٦١–٩٠ يومًا'
        WHEN MAX(DATEDIFF(CURDATE(), s.period_to)) >   30 THEN '٣١–٦٠ يومًا'
        ELSE 'حتى ٣٠ يومًا'
    END COLLATE utf8mb4_unicode_ci                 AS age_bucket,

    /* الإجراءُ المقترح — «كلُّ رصيدٍ له شريحةُ عمرٍ **وإجراءٌ مقترح**» */
    CASE
        WHEN SUM(CASE WHEN s.open_objections > 0 THEN 1 ELSE 0 END) > 0
             THEN 'حسمُ الاعتراضاتِ المفتوحةِ قبلَ الصرف'
        WHEN SUM(CASE WHEN s.state = 'payment_requested' THEN 1 ELSE 0 END) > 0
             THEN 'متابعةُ طلبِ الدفعِ لدى المالية'
        WHEN MAX(DATEDIFF(CURDATE(), s.period_to)) > 180
             THEN 'تصعيدٌ — رصيدٌ تجاوز ١٨٠ يومًا'
        WHEN MAX(DATEDIFF(CURDATE(), s.period_to)) >  90
             THEN 'إصدارُ طلبِ دفعٍ عاجل'
        ELSE 'ضمنَ المهلةِ — لا إجراءَ عاجل'
    END COLLATE utf8mb4_unicode_ci                 AS suggested_action,

    MIN(s.prepared_at)                             AS first_prepared_at,
    MAX(s.approved_at)                             AS last_approved_at
FROM `settlements` s
LEFT JOIN `suppliers` su ON su.id = s.party_ref AND su.is_deleted = 0
WHERE s.is_deleted = 0
  AND s.party_type = 'supplier'
  AND s.state IN ('approved','payment_requested')
  AND s.paid_at IS NULL
GROUP BY s.company_id, s.party_ref, s.currency";

if (!$conn->query($sql)) {
    fwrite(STDERR, "✘ تعذّر إنشاءُ المنظر: {$conn->error}\n");
    exit(1);
}
echo "② أُنشئ المنظر {$VIEW}\n";

/* ── ③ لا DEFINER — يمنع استعادةَ الدمبِ على مضيفٍ آخر ─────────────────── */
$q = $conn->query("SELECT DEFINER, SECURITY_TYPE FROM information_schema.VIEWS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$VIEW}'");
$v = $q ? $q->fetch_assoc() : null;
if ($v && strtoupper((string) $v['SECURITY_TYPE']) !== 'INVOKER') {
    fwrite(STDERR, "✘ المنظرُ بحقوقِ منشئِه — يفشل عندَ الاستعادةِ على مضيفٍ آخر. أُوقفت الهجرة
");
    exit(1);
}
printf("③ DEFINER=%s · SECURITY=%s — ويُنزع عند التصدير بقاعدةِ المستودع\n",
    $v['DEFINER'] ?? '—', $v['SECURITY_TYPE'] ?? '—');

/* ── ④ القياسُ بعدَ البناء ─────────────────────────────────────────────── */
$q = $conn->query("SELECT COUNT(*) rows_n, COUNT(DISTINCT supplier_id) sup,
                          SUM(outstanding_amount) amt,
                          SUM(age_bucket IS NULL OR age_bucket = '') no_bucket,
                          SUM(suggested_action IS NULL OR suggested_action = '') no_action
                     FROM `{$VIEW}`");
$r = $q ? $q->fetch_assoc() : null;
printf("④ المنظر: %d صفًّا · %d موردًا · %s · بلا شريحة %d · بلا إجراء %d\n",
    (int) $r['rows_n'], (int) $r['sup'], number_format((float) $r['amt'], 2),
    (int) $r['no_bucket'], (int) $r['no_action']);

$cols = $conn->query("SELECT COUNT(*) c FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$VIEW}'");
printf("   وحقولُه %d (والمرجعُ يعدُّ اثنَين وعشرين)\n", (int) $cols->fetch_assoc()['c']);

ems_migration_recorded(__FILE__, $conn, 0);
echo "✔ اكتمل — و«صفرُ إدخال» خاصيةٌ بنيويةٌ لا قاعدةٌ تُراقَب\n";
