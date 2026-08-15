<?php
/**
 * 2027_04_15_share_period_no_overlap.php
 * ═══════════════════════════════════════════════════════════════════════════
 * لا فترتان متراكبتان لحصةٍ واحدة — قادحًا لا فحصَ شاشة — ⇐ INJ-0045
 *
 * نصُّ القبول: «بعد نقل حصةٍ: `SUM(percent)` للأصل في يوم النقل وفي اليوم التالي
 * = ١٠٠ بالضبط؛ **وصفرُ فترتين متراكبتين لنفس (الأصل × الممول)**».
 *
 * ── الاصطلاحُ المحسوم ────────────────────────────────────────────────────
 * `valid_to` **شامل**: آخرُ يومٍ تسري فيه الحصة. وهو اصطلاحُ القراءةِ القائمِ
 * في كلِّ الشاشات (`valid_to IS NULL OR valid_to >= CURDATE()`)، فلا يُغيَّر —
 * ويُصحَّح الكاتبُ ليوافقه: الإغلاقُ **أمسِ** والفتحُ **اليومَ**.
 *
 * ── ولماذا قادحٌ لا شرطٌ في الشاشة ───────────────────────────────────────
 * `asset_ownership_shares` يكتب فيه أكثرُ من مسار (`asset_disposal.php` ·
 * `FinancingService::applyShares` · هجراتُ التسوية). وشرطٌ في مسارٍ واحدٍ
 * يتركُ البابَ مفتوحًا في الباقي — **وحارسٌ في طبقةٍ واحدةٍ ليس حارسًا** (CS-11).
 *
 * ◆ ويحكم على **الوارد** فقط: البيانةُ التاريخيةُ تُقاس وتُعلَن ولا تُدان.
 * ◆ و`information_schema.TRIGGERS` تحتاج امتيازًا — فالتحققُ **وظيفيّ**.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(dirname(__DIR__));
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$u = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_USER') : ems_env('DB_USER');
$p = ems_env('DB_MIGRATOR_PASS') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');
$conn = new mysqli($host, $u, $p, ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');

echo "══ لا فترتان متراكبتان لحصةٍ واحدة ══\n\n";

/* ── ① المقيسُ الموروثُ يُعلَن قبل أيِّ حارس ─────────────────────────────── */
$q = "SELECT COUNT(*) FROM asset_ownership_shares a
        JOIN asset_ownership_shares b
          ON b.company_id = a.company_id AND b.asset_kind = a.asset_kind
         AND b.asset_id = a.asset_id AND b.financier_entity_id = a.financier_entity_id
         AND b.share_id > a.share_id
         AND a.valid_from <= COALESCE(b.valid_to, '9999-12-31')
         AND b.valid_from <= COALESCE(a.valid_to, '9999-12-31')";
$r = $conn->query($q);
$legacy = ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
echo '  تراكبٌ موروثٌ في البيانةِ القائمة: ' . $legacy . "\n";

/* ── ② القادح: الوارد لا يتراكب ─────────────────────────────────────────── */
foreach (array('trg_share_no_overlap_ins', 'trg_share_no_overlap_upd') as $t) {
    $conn->query("DROP TRIGGER IF EXISTS `{$t}`");
}
$body = "
    DECLARE clash INT;
    SELECT COUNT(*) INTO clash
      FROM asset_ownership_shares x
     WHERE x.company_id = NEW.company_id
       AND x.asset_kind = NEW.asset_kind
       AND x.asset_id = NEW.asset_id
       AND x.financier_entity_id = NEW.financier_entity_id
       AND x.share_id <> COALESCE(NEW.share_id, 0)
       AND NEW.valid_from <= COALESCE(x.valid_to, '9999-12-31')
       AND x.valid_from  <= COALESCE(NEW.valid_to, '9999-12-31');
    IF clash > 0 THEN
        SIGNAL SQLSTATE '45000'
          SET MESSAGE_TEXT = 'SHR-409: فترتان متراكبتان لنفس (الاصل × الممول) — valid_to شامل فاغلق اليوم السابق';
    END IF;
";
$ok1 = $conn->query("CREATE TRIGGER trg_share_no_overlap_ins BEFORE INSERT ON asset_ownership_shares
FOR EACH ROW BEGIN {$body} END");
if (!$ok1) { exit("✘ تعذّر قادحُ الإدراج: {$conn->error}\n"); }
echo "  ✔ trg_share_no_overlap_ins\n";
$ok2 = $conn->query("CREATE TRIGGER trg_share_no_overlap_upd BEFORE UPDATE ON asset_ownership_shares
FOR EACH ROW BEGIN {$body} END");
if (!$ok2) { exit("✘ تعذّر قادحُ التحديث: {$conn->error}\n"); }
echo "  ✔ trg_share_no_overlap_upd\n";

/* ── ③ جسٌّ وظيفيّ ───────────────────────────────────────────────────────── */
echo "\n── جسٌّ وظيفيّ: أيُردُّ تراكبٌ متعمَّد؟\n";
$r = $conn->query("SELECT share_id, company_id, asset_id, asset_kind, financier_entity_id,
                          op_id, model_code, percent, valid_from, valid_to
                     FROM asset_ownership_shares ORDER BY share_id LIMIT 1");
$s = $r ? $r->fetch_assoc() : null;
if (!$s) {
    echo "  ⚠ لا حصصَ في القاعدة — يُعلَن التخطّي ولا يُدّعى نجاح\n";
} else {
    $esc = function ($v) use ($conn) { return $v === null ? 'NULL' : "'" . $conn->real_escape_string((string) $v) . "'"; };
    $ins = $conn->query("INSERT INTO asset_ownership_shares
            (company_id, asset_id, asset_kind, financier_entity_id, op_id, model_code, percent,
             valid_from, valid_to, doc_ref, recorded_percent, approved_percent, created_by)
          VALUES ({$s['company_id']}, {$s['asset_id']}, " . $esc($s['asset_kind']) . ",
                  {$s['financier_entity_id']}, " . ($s['op_id'] === null ? 'NULL' : (int) $s['op_id']) . ",
                  " . $esc($s['model_code']) . ", 1,
                  " . $esc($s['valid_from']) . ", " . $esc($s['valid_to']) . ",
                  'جسُّ القادح', 1, 1, 1)");
    if ($ins) {
        echo "  ✘ **مرّ** التراكبُ — القادحُ لا يعمل\n";
        $conn->query("DELETE FROM asset_ownership_shares WHERE doc_ref = 'جسُّ القادح'");
    } else {
        echo '  ✔ رُدّ: ' . mb_substr($conn->error, 0, 95) . "\n";
    }
}

echo "\n✔ تمّت\n";
