<?php
/**
 * 2027_11_06_injfrd66_w5_derived_views.php
 *   الموجةُ ⑤ — منظرانِ مشتقّانِ يُخرجان `SUP-03` و`SUP-14` من «غيرِ محسوم»
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **منظرانِ لا جدولان** — وهذا قرارُ نصٍّ لا ذوق:
 *   ① `SUP-14` نصُّه حرفيًّا «**منظرٌ مشتقٌّ من الحصص… لا شاشةٌ ولا إدخال**»،
 *      ومعيارُه «صفر إدخالٍ في المستهدفات». **وجدولٌ يُملأ يتقادم ويكذب**،
 *      ويفتح بابَ إدخالٍ في سطحٍ نصُّه يمنعه. فـ«صفرُ الإدخال» يصير **خاصيةً
 *      بنيويةً لا قاعدةً تُراقَب**.
 *   ② `SUP-03` «حالةُ التأهيل» **لا عمودَ لها في القاعدة**، ومكوّناتُها كلُّها
 *      في `suppliers`. فالحالةُ **تُحسَب من مكوّناتِها** — ومن خزّنها خزّن حكمًا
 *      يتقادم بأوَّلِ مستندٍ يُرفَع.
 *
 * ◆ **وثلاثةُ فخاخٍ مكتوبةٌ هنا لأنَّها كلَّفت جولةً كاملةً من قبل**:
 *   ① `SQL SECURITY INVOKER` **في جملةِ الإنشاء** — و`ALTER` بعدَها لا يغيّره.
 *      ومنظرٌ بحقوقِ منشئِه يُفشل الاستعادةَ على مضيفٍ لا يعرف `ems_migrator`.
 *   ② **حرفياتُ `CASE` تأخذ ترتيبَ اتصالِ العميلِ لا المخطَّط** — فمقارنةٌ
 *      واحدةٌ تنجح من سطرِ الأوامرِ وترسُب من PHP بـ«Illegal mix of collations».
 *      **وعطبٌ يظهر في بيئةٍ ويختفي في أخرى أخطرُ من عطبٍ ثابت.**
 *   ③ **الشهرُ الغائبُ ليس شهرَ صفر**: حصةٌ بنافذةٍ فارغةٍ (`effective_from`
 *      أو `effective_to` = NULL) تُنتج شهرًا قيمتُه NULL — **ولو مرَّ لعُدَّ
 *      «شهرَ صفرٍ» وهو ليس شهرًا أصلًا**. يُستبعد من عمودِ الأشهرِ **ويُعلَن**.
 *
 * ◆ **ولا حارسَ إنفاذٍ هنا**: `SUP-03` معيارُه «صفرُ عقدٍ لموردٍ غيرِ مؤهَّل»
 *   و**العشرون عقدًا كلُّها لموردين ناقصي التأهيل**. فمنظرٌ **يقيس** ولا يمنع —
 *   والمنعُ قرارُ مالكٍ بمهلةِ تصحيح، لا هجرةُ بيانات.
 *
 * التشغيل:  php database/migrations/2027_11_06_injfrd66_w5_derived_views.php
 * الرجوع :  php database/migrations/2027_11_06_injfrd66_w5_derived_views.php --revert
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

$REVERT = in_array('--revert', $argv, true);
$VIEWS  = array('v_supplier_qualification', 'v_supplier_targets_monthly');

/* ── الرجوع ─────────────────────────────────────────────────────────────── */
if ($REVERT) {
    echo "\n══ الرجوع — الموجةُ ⑤ ══\n\n";
    foreach ($VIEWS as $v) {
        /* منظرٌ يُسقَط بـ DROP VIEW لا DROP TABLE — والخلطُ يُسقط جدولًا حيًّا */
        $ok = $conn->query("DROP VIEW IF EXISTS `{$v}`");
        printf("  %s %s\n", $ok ? '✔' : '✘', $v);
    }
    echo "\n  ◆ ولا بياناتٍ تُستعاد: المنظرُ مشتقٌّ — إسقاطُه لا يُفقد صفًّا واحدًا.\n\n";
    exit(0);
}

echo "\n══ INJ-FRD-01 · الموجةُ ⑤ — منظرانِ مشتقّان ══\n\n";

/* ── ٠ قياسٌ قبلَ البناء: نوافذُ الحصصِ الفارغة ──────────────────────────── */
$r = $conn->query("SELECT COUNT(*) t, SUM(effective_from IS NULL OR effective_to IS NULL) nul
                     FROM v_supplier_share_units");
if (!$r) { exit("  ✘ `v_supplier_share_units` غيرُ متاح: {$conn->error}\n"); }
$sh = $r->fetch_assoc();
printf("  ○ حصصُ المصدر: %d — منها %d بنافذةِ سريانٍ فارغة\n", $sh['t'], $sh['nul']);
if ((int) $sh['nul'] > 0) {
    echo "     ⚠ **تُستبعَد من عمودِ الأشهرِ ولا تُبتلع**: شهرٌ قيمتُه NULL ليس\n";
    echo "       «شهرَ صفر» — ولو مرَّ لأضاف صفًّا يُعَدُّ في مقامٍ لا ينتمي إليه.\n";
}

$mk = static function (string $name, string $sql) use ($conn): void {
    /* ◆ `SQL SECURITY INVOKER` **في جملةِ الإنشاءِ نفسِها** — لا بـ`ALTER` بعدَها */
    if (!$conn->query("CREATE OR REPLACE SQL SECURITY INVOKER VIEW `{$name}` AS {$sql}")) {
        exit("\n  ✘ {$name}: {$conn->error}\n");
    }
    $r = $conn->query("SELECT COUNT(*) FROM `{$name}`");
    if (!$r) { exit("\n  ✘ {$name} أُنشئ ولا يُقرأ: {$conn->error}\n"); }
    printf("  ✔ %-28s %s صفًّا\n", $name, number_format((int) $r->fetch_row()[0]));
};

/* ── ① SUP-03 — حالةُ التأهيلِ محسوبةً من مكوّناتِها ────────────────────── */
/* والترتيبُ مثبَّتٌ على كلِّ حرفيّةٍ نصّية: بغيرِه يرسُب المنظرُ من PHP وينجح
   من سطرِ الأوامر — وهو أخبثُ ما في البابِ لأنَّه لا يُرى حتى يُرى في الإنتاج. */
$mk('v_supplier_qualification',
    "SELECT s.company_id, s.id AS supplier_id, s.name AS supplier_name,
            s.supplier_code,
            (s.commercial_registration IS NOT NULL AND s.commercial_registration <> '') AS has_cr,
            (s.tax_number IS NOT NULL AND s.tax_number <> '')                           AS has_tax,
            (s.identity_number IS NOT NULL AND s.identity_number <> '')                 AS has_identity,
            s.identity_expiry_date,
            (s.identity_expiry_date IS NOT NULL AND s.identity_expiry_date < CURDATE()) AS identity_expired,
            (s.bank_account_no IS NOT NULL AND s.bank_account_no <> '')                 AS has_bank_account,
            (s.bank_iban IS NOT NULL AND s.bank_iban <> '')                             AS has_iban,
            (s.bank_verified_at IS NOT NULL)                                            AS bank_verified,
            (   (s.commercial_registration IS NULL OR s.commercial_registration = '')
              + (s.tax_number IS NULL OR s.tax_number = '')
              + (s.identity_number IS NULL OR s.identity_number = '')
              + (s.bank_account_no IS NULL OR s.bank_account_no = '')
              + (s.bank_verified_at IS NULL) )                                          AS missing_count,
            CONCAT_WS('، ',
              CASE WHEN s.commercial_registration IS NULL OR s.commercial_registration = ''
                   THEN CAST('سجلٌّ تجاريّ' AS CHAR) COLLATE utf8mb4_unicode_ci END,
              CASE WHEN s.tax_number IS NULL OR s.tax_number = ''
                   THEN CAST('رقمٌ ضريبيّ' AS CHAR) COLLATE utf8mb4_unicode_ci END,
              CASE WHEN s.identity_number IS NULL OR s.identity_number = ''
                   THEN CAST('هويّة' AS CHAR) COLLATE utf8mb4_unicode_ci END,
              CASE WHEN s.bank_account_no IS NULL OR s.bank_account_no = ''
                   THEN CAST('حسابٌ بنكيّ' AS CHAR) COLLATE utf8mb4_unicode_ci END,
              CASE WHEN s.bank_verified_at IS NULL
                   THEN CAST('توثيقُ الحساب' AS CHAR) COLLATE utf8mb4_unicode_ci END
            )                                                                           AS missing_list,
            CASE
              WHEN (s.commercial_registration IS NULL OR s.commercial_registration = '')
                OR (s.tax_number IS NULL OR s.tax_number = '')
                OR (s.identity_number IS NULL OR s.identity_number = '')
                OR (s.bank_account_no IS NULL OR s.bank_account_no = '')
                OR  s.bank_verified_at IS NULL
                   THEN CAST('ناقصُ التأهيل' AS CHAR) COLLATE utf8mb4_unicode_ci
              WHEN s.identity_expiry_date IS NOT NULL AND s.identity_expiry_date < CURDATE()
                   THEN CAST('هويّةٌ منتهية' AS CHAR) COLLATE utf8mb4_unicode_ci
              ELSE      CAST('مؤهَّل' AS CHAR) COLLATE utf8mb4_unicode_ci
            END                                                                         AS qualification_state,
            COALESCE(k.live_contracts, 0)                                               AS live_contracts
       FROM suppliers s
       LEFT JOIN (SELECT supplier_id, COUNT(*) AS live_contracts
                    FROM supplier_contracts WHERE is_deleted = 0
                   GROUP BY supplier_id) k ON k.supplier_id = s.id
      WHERE s.is_deleted = 0");

/* ── ② SUP-14 — المستهدفاتُ الشهريةُ بعمودِ أشهرٍ صريحٍ يشمل الصفر ───────── */
/* ◆ **ولماذا عمودُ أشهرٍ لا تجميع**: `GROUP BY` على الحصصِ **يُسقط الشهرَ الذي
     لا حصةَ فيه** — والشهرُ الغائبُ من التقريرِ يُقرأ «لا بيانات» بينما معناه
     «**مستهدَفٌ صفر**». والفرقُ بينهما قرارٌ تشغيليّ.
   ◆ **والنطاقُ نطاقُ الموردِ لا نطاقُ النظام**: عمودٌ عامٌّ من 2020 إلى 2027
     يخترع لموردٍ بدأ في 2026 ستّةً وستّينَ «شهرَ صفرٍ» لم يكن فيها متعاقدًا
     أصلًا — رقمٌ صادقُ الحسابِ كاذبُ المعنى. */
$mk('v_supplier_targets_monthly',
    "WITH RECURSIVE span AS (
        SELECT company_id, supplier_id,
               DATE_FORMAT(MIN(effective_from), '%Y-%m-01') AS m_first,
               DATE_FORMAT(MAX(effective_to),   '%Y-%m-01') AS m_last
          FROM v_supplier_share_units
         WHERE supplier_id IS NOT NULL
           AND effective_from IS NOT NULL AND effective_to IS NOT NULL
         GROUP BY company_id, supplier_id
     ), months AS (
        SELECT company_id, supplier_id, CAST(m_first AS DATE) AS mon, CAST(m_last AS DATE) AS m_last
          FROM span
        UNION ALL
        SELECT company_id, supplier_id, DATE_ADD(mon, INTERVAL 1 MONTH), m_last
          FROM months WHERE mon < m_last
     )
     SELECT m.company_id, m.supplier_id, s.name AS supplier_name,
            /* ◆ و`DATE_FORMAT` **يُخرج نصًّا بترتيبِ الاتصال** لا بترتيبِ المخطَّط —
                 فيلزمه ما يلزم حرفيّاتِ `CASE` سواءً بسواء. وحارسُ الترتيبِ رصده
                 في أوَّلِ تشغيل: عمودٌ واحدٌ من تسعةٍ كان يكفي لإسقاطِ المنظرِ من PHP. */
            CAST(DATE_FORMAT(m.mon, '%Y-%m') AS CHAR) COLLATE utf8mb4_unicode_ci AS target_month,
            m.mon AS month_start,
            COUNT(u.supplier_container_id) AS shares_active,
            ROUND(COALESCE(SUM(u.share_units * 30.0
                  / NULLIF(DATEDIFF(u.effective_to, u.effective_from), 0)), 0), 2) AS monthly_target,
            CASE WHEN COUNT(u.supplier_container_id) = 0
                 THEN CAST('شهرُ صفرٍ — لا حصةَ سارية' AS CHAR) COLLATE utf8mb4_unicode_ci
                 ELSE CAST('مستهدَفٌ من حصصٍ سارية' AS CHAR) COLLATE utf8mb4_unicode_ci
            END AS basis
       FROM months m
       LEFT JOIN suppliers s ON s.id = m.supplier_id
       LEFT JOIN v_supplier_share_units u
              ON u.supplier_id = m.supplier_id AND u.company_id = m.company_id
             AND u.effective_from IS NOT NULL AND u.effective_to IS NOT NULL
             AND u.effective_from <= LAST_DAY(m.mon) AND u.effective_to >= m.mon
      GROUP BY m.company_id, m.supplier_id, s.name, m.mon");

/* ── ③ الحُرّاسُ بعدَ البناء — وكلٌّ منها كلَّف جولةً من قبل ──────────────── */
echo "\n  ── حُرّاسُ ما بعدَ البناء\n";
$halt = 0;
foreach ($VIEWS as $v) {
    $r = $conn->query("SELECT SECURITY_TYPE FROM information_schema.VIEWS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$v}'");
    $st = $r ? (string) $r->fetch_row()[0] : '';
    if ($st !== 'INVOKER') {
        $halt++; printf("     ✘ %s حقوقُه «%s» لا INVOKER — يُفشل الاستعادة\n", $v, $st);
    } else { printf("     ✔ %-28s INVOKER\n", $v); }

    $r = $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$v}'
                          AND COLLATION_NAME IS NOT NULL
                          AND COLLATION_NAME <> 'utf8mb4_unicode_ci'");
    $bad = $r ? (int) $r->fetch_row()[0] : -1;
    if ($bad !== 0) {
        $halt++; printf("     ✘ %s فيه %d عمودًا بترتيبٍ غريب — يرسُب من PHP وينجح من CLI\n", $v, $bad);
    } else { printf("     ✔ %-28s ترتيبُ أعمدتِه النصّيةِ على المخطَّط\n", $v); }
}

/* شهرٌ بلا تاريخ = عطبٌ مرَّ، لا «شهرُ صفر» */
$r = $conn->query("SELECT COUNT(*) FROM v_supplier_targets_monthly
                    WHERE target_month IS NULL OR month_start IS NULL");
$nullMon = $r ? (int) $r->fetch_row()[0] : -1;
if ($nullMon !== 0) {
    $halt++; printf("     ✘ %d صفًّا بشهرٍ فارغ — نافذةُ حصةٍ فارغةٌ تسرَّبت للعمود\n", $nullMon);
} else { echo "     ✔ صفرُ صفٍّ بشهرٍ فارغ — والحصةُ بلا نافذةٍ استُبعدت لا ابتُلعت\n"; }

/* كلُّ موردٍ حيٍّ محكومٌ عليه — ولا «غيرُ مصنَّف» يمرُّ صامتًا */
$r = $conn->query("SELECT COUNT(*) FROM v_supplier_qualification
                    WHERE qualification_state IS NULL OR qualification_state = ''");
$unjudged = $r ? (int) $r->fetch_row()[0] : -1;
if ($unjudged !== 0) {
    $halt++; printf("     ✘ %d موردًا بلا حكمِ تأهيل\n", $unjudged);
} else { echo "     ✔ صفرُ موردٍ بلا حكمِ تأهيل\n"; }

if ($halt > 0) {
    echo "\n  ⛔ توقَّفت الهجرةُ عند {$halt} حارسًا — والمنظرُ الذي لا يجتاز حُرّاسَه\n";
    echo "     أسوأُ من غيابِه: يُقرأ ويُصدَّق.\n\n";
    foreach ($VIEWS as $v) { $conn->query("DROP VIEW IF EXISTS `{$v}`"); }
    exit(1);
}

/* ── ④ ما يقوله المنظرانِ الآن ──────────────────────────────────────────── */
echo "\n  ── حصيلةُ القياسِ الأولِ\n";
$r = $conn->query("SELECT qualification_state, COUNT(*) n, SUM(live_contracts) contracts
                     FROM v_supplier_qualification GROUP BY qualification_state");
while ($r && ($x = $r->fetch_assoc())) {
    printf("     %-16s %3d موردًا · %s عقدًا نافذًا\n", $x['qualification_state'], $x['n'], $x['contracts']);
}
$r = $conn->query("SELECT COUNT(*) rows_n, SUM(shares_active = 0) zeros,
                          COUNT(DISTINCT supplier_id) sup, ROUND(SUM(monthly_target), 2) tot
                     FROM v_supplier_targets_monthly");
$t = $r ? $r->fetch_assoc() : array();
printf("     %s شهرًا × %s موردًا — منها %s شهرَ صفرٍ · مجموعُ المستهدَف %s\n",
    $t['rows_n'], $t['sup'], $t['zeros'], number_format((float) $t['tot'], 2));

echo "\n  ◆ ولا حارسَ منعٍ هنا: المنظرُ **يقيس** ولا يمنع. ومنعُ التعاقدِ مع\n";
echo "    غيرِ المؤهَّلِ اليومَ يُخالف عقودَ الإدارةِ كلَّها — قرارُ مالكٍ بمهلة.\n\n";
exit(0);
