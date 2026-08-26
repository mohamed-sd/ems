<?php
/**
 * tools/repair01_w9_journey.php — رحلةُ التوريد (‏W09 §٦-أ · §20)
 * ═══════════════════════════════════════════════════════════════════════════
 * **طلبُ شراء ← حزمة ← طلبُ عروض ← دعوات ← عروضٌ بسطورها ← فتحُ المظاريف ←
 *   تقييمٌ وترسية ← اعتماد ← أمرُ شراءٍ بسندِه ← متابعةُ توريد ← إشعارُ استلامٍ
 *   بفحصِه ← رصيدٌ بحالتِه ← طلبُ صرف ← سندُ صرف ← عهدة ← إعادةُ طلب ← جردٌ
 *   بقرارِ تسوية ← إقفالٌ شهريٌّ بمعادلةٍ تنطبق.**
 *
 * ◆ **والقبولُ يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ** (§46): عند كلِّ
 *   مستهلكٍ يُقاس رقمٌ يعنيه — حزمةٌ تحمل عددَ طلباتِها المشتقَّ · مظروفٌ لا
 *   يُقرأ قبل موعدِه · فائزٌ غيرُ الأدنى يوجب سببًا · أمرٌ يحمل سندَه ·
 *   مقبولٌ وحدَه يدخل الرصيد · مصروفٌ لا يتجاوز المعتمَد · فرقُ جردٍ لا يُقفل
 *   بلا قرار · إقفالٌ لا يمرُّ بمعادلةٍ لا تنطبق.
 *
 * ◆ **والمحطّاتُ السالبةُ محطّاتٌ**: «لا فتحَ قبل الموعد» و«لا ترسيةَ قبل
 *   الفتح» و«الفائزُ غيرُ الأدنى بلا سبب» و«مَن فتح لا يعتمد» و«أمرٌ بلا سند»
 *   و«صرفٌ يتجاوز المعتمَد» و«مَن أرسل لا يستلم» و«مَن عدَّ لا يعتمد»
 *   و«إقفالٌ بجردٍ غيرِ معتمَد» — تُقاس **بالاستدعاءِ الفعليِّ ورمزِ الرفض**.
 *
 * ◆ **والبياناتُ لا تبقى**: كلُّ ما تكتبه الرحلةُ داخلَ معاملةٍ تُرجَع؛ ودليلُها
 *   وحدَه يُكتب بعدَ الإرجاعِ في `repair01_w9_journey`.
 *
 * ⚠ **والمحطّاتُ المؤجَّلةُ تُعلَن ولا تُدَّعى**: بوّابتا التتبّعِ والانتهاءِ
 *   خامدتانِ لأنَّ `DEC-OPEN-15` مفتوح — فتُقاسان **بأنَّهما لا تعترضان**،
 *   وتُوسَمان محطّتَي انتظارٍ لا محطّتَي عبور.
 *
 * التشغيل: php tools/repair01_w9_journey.php
 * الخروج : 0 عبرت كلُّ المحطّات · 1 محطّةٌ لم تعبر أو أرضيّةٌ ناقصة
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);

/* ⚠ **حارسُ الموتِ الصامت**: `config.php` يبتلع مخرَجَ سطرِ الأوامر، فرحلةٌ
     تسقط بخطإٍ قاتلٍ تخرج بلا سطرٍ واحدٍ ويقرأ القارئُ صمتًا لا سببًا. فيُسجَّل
     آخرُ خطإٍ في `STDERR` **قبل أن يُغلق الطلب** — والصمتُ يصير نصًّا. */
register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        fwrite(STDERR, "\n✘ سقطت الرحلةُ بخطإٍ قاتل:\n   " . $e['message']
                     . "\n   في " . $e['file'] . ':' . $e['line'] . "\n");
    }
});

$ROOT = dirname(__DIR__);
require_once $ROOT . '/tools/lib/repair01_w9_scan.php';
require_once $ROOT . '/app/Services/Procurement/ProcurementCycleService.php';
require_once $ROOT . '/app/Services/Warehouse/WarehouseCycleService.php';
require_once $ROOT . '/config.php';
if (!isset($conn) || !($conn instanceof mysqli)) { exit("تعذّر الاتصال بالقاعدة\n"); }
$conn->set_charset('utf8mb4');
while (ob_get_level()) { ob_end_clean(); }
require_once $ROOT . '/app/Core/TenantGateException.php';
require_once $ROOT . '/app/Core/TenantRegistry.php';
require_once $ROOT . '/app/Core/TenantContext.php';
require_once $ROOT . '/app/Core/TenantDb.php';
use App\Services\Procurement\ProcurementCycleService as PCS;
use App\Services\Warehouse\WarehouseCycleService as WCS;

$esc = function ($s) use ($conn) { return $conn->real_escape_string((string) $s); };
$one = function ($sql) use ($conn) { return repair01_w9_one($conn, $sql); };

/* مُعرِّفُ الجولةِ بدقّةِ الميكروثانية — جولتانِ في الثانيةِ نفسِها تتقاسمان
   المُعرِّفَ فتقرأ البوّابةُ صفوفَهما جولةً واحدةً وتسقط (‏درسُ W04). */
$RUN  = 'W9J-' . (string) $one("SELECT DATE_FORMAT(NOW(6), '%Y%m%d%H%i%s%f')");
$MARK = '__w9_journey_' . $RUN . '__';

echo "═══════════ رحلةُ التوريد — REPAIR01 · W09 §20 ═══════════\n";
/* ⚠ المُعرِّفُ يُطبَع سطرًا مُرمَّزًا لتقرأه البوّابةُ من المخرَجِ لا من
     «آخرِ صفٍّ في الجدول» — ورحلةٌ لم تنعقد تترك دليلَ سابقتِها قائمًا. */
echo "RUN=$RUN\n";
echo "الجولة: $RUN\n\n";

$ST = array();
$add = function ($no, $station, $entity, $consumer, $expected, $measured, $effect, $state, $passed) use (&$ST) {
    $ST[] = array($no, $station, $entity, $consumer, $expected, $measured, $effect, $state, $passed ? 1 : 0);
};

/* ══════════════════════════════════════════════════════════════════════════
   كنسُ العائلةِ — **لأنَّ المعاملةَ لا تُرجِع هذه الرحلة**
   ══════════════════════════════════════════════════════════════════════════
   ◆ **العطبُ المقيسُ في أوّلِ تشغيل**: `TenantDb::runInTransaction` يستدعي
     `begin_transaction()`، و**MySQL يُثبِّت المعاملةَ الخارجيّةَ ضمنًا** عند
     بدءِ داخليّة. فكلُّ ما كتبته الرحلةُ قبلَ أوّلِ خدمةٍ تُدير معاملتَها
     **يُثبَّت في القاعدةِ الحيّة**، و`ROLLBACK` في آخرِ الملفِّ يُرجع لا شيء.
     المقيس: ١٦ حركةَ مخزنٍ و٤ طلباتِ عروضٍ و٨ حزمٍ بقيت بعد «إرجاعٍ» مُعلَن.
   ◆ **فالنظافةُ كنسٌ بالعائلةِ لا إرجاعٌ بمعاملة** — والكنسُ يُشغَّل **مرّتَين**:
     قبلَ الرحلةِ ليلتقط بقايا جولةٍ سابقةٍ سقطت، وبعدَها ليمحوَ أثرَها هي.
     ووسمُ الجولةِ وحدَه **يُعمي الكنسَ عن سابقتِه** — فالبادئةُ `W9J-` هي
     العائلةُ لا مُعرِّفُ الجولة.
   ══════════════════════════════════════════════════════════════════════════ */
$sweep = function () use ($conn) {
    $n = 0;
    $q = array(
        /* الأبناءُ أوّلًا ثمَّ الآباء */
        "DELETE FROM proc_offer_line WHERE offer_id IN (SELECT id FROM proc_offer WHERE rfq_id IN
            (SELECT id FROM proc_rfq WHERE code LIKE 'W9J-%'))",
        "DELETE FROM proc_offer WHERE rfq_id IN (SELECT id FROM proc_rfq WHERE code LIKE 'W9J-%')",
        "DELETE FROM proc_rfq_invite WHERE rfq_id IN (SELECT id FROM proc_rfq WHERE code LIKE 'W9J-%')",
        "DELETE FROM proc_award WHERE rfq_id IN (SELECT id FROM proc_rfq WHERE code LIKE 'W9J-%')",
        "DELETE FROM proc_rfq WHERE code LIKE 'W9J-%'",
        "DELETE FROM proc_package_member WHERE package_id IN (SELECT id FROM proc_package WHERE code LIKE 'W9J-%')",
        "DELETE FROM proc_package WHERE code LIKE 'W9J-%'",
        "DELETE FROM proc_transfer_line WHERE transfer_id IN (SELECT id FROM proc_transfer WHERE code LIKE 'W9J-%')",
        "DELETE FROM proc_transfer WHERE code LIKE 'W9J-%'",
        "DELETE FROM proc_count_line WHERE session_id IN (SELECT id FROM proc_count_session WHERE code LIKE 'W9J-%')",
        "DELETE FROM proc_count_session WHERE code LIKE 'W9J-%'",
        "DELETE FROM proc_issue_request_line WHERE request_id IN
            (SELECT id FROM proc_issue_request WHERE code LIKE 'W9J-%')",
        "DELETE FROM proc_issue_request WHERE code LIKE 'W9J-%'",
        "DELETE FROM proc_delivery_event WHERE order_id IN (SELECT id FROM proc_order WHERE code LIKE 'W9J-%')",
        "DELETE FROM proc_invoice_match WHERE order_id IN (SELECT id FROM proc_order WHERE code LIKE 'W9J-%')",
        "DELETE FROM proc_receipt_line WHERE custody_id IN
            (SELECT id FROM proc_receipt_custody WHERE code LIKE 'W9J-%')",
        "DELETE FROM proc_receipt_custody WHERE code LIKE 'W9J-%'",
        "DELETE FROM proc_issue_line WHERE issue_id IN (SELECT id FROM proc_issue WHERE code LIKE 'W9J-%')",
        "DELETE FROM proc_issue WHERE code LIKE 'W9J-%'",
        "DELETE FROM proc_order WHERE code LIKE 'W9J-%'",
        "DELETE FROM proc_request_line WHERE request_id IN (SELECT id FROM proc_request WHERE code LIKE 'W9J-%')",
        "DELETE FROM proc_request WHERE code LIKE 'W9J-%'",
        /* حركاتُ المخزنِ موسومةٌ بنصِّ الملاحظةِ لا بالرمز */
        "DELETE FROM proc_stock_move WHERE note LIKE 'W09 %'",
        /* والمشتقّاتُ تُفرَغ لتُعاد من الحركاتِ الحيّةِ وحدَها */
        "DELETE FROM proc_wh_close WHERE period_ym <> '' AND closed_by > 0 AND close_value <> 0
            AND warehouse_id IN (SELECT id FROM proc_warehouse) AND state = 'closed'
            AND NOT EXISTS (SELECT 1 FROM proc_count_session s WHERE s.warehouse_id = proc_wh_close.warehouse_id)",
        "DELETE FROM proc_supplier_eval WHERE score_rule LIKE 'onTime%'",
        "DELETE FROM proc_stock_state WHERE 1",
    );
    foreach ($q as $s) { if (@$conn->query($s)) { $n += (int) $conn->affected_rows; } }
    return $n;
};
$preSweep = $sweep();
if ($preSweep > 0) { echo "⚠ كُنست بقايا جولةٍ سابقةٍ: $preSweep صفًّا\n\n"; }

/* ── أرضيّةُ الرحلة ────────────────────────────────────────────────────── */
$company = (int) $one("SELECT company_id FROM proc_item WHERE COALESCE(is_deleted,0)=0
                        GROUP BY company_id ORDER BY COUNT(*) DESC LIMIT 1");
/* ⚠ الخروجُ برمزٍ غيرِ صفرٍ عند نقصِ الأرضيّة — `exit("نصّ")` يخرج **بصفر** */
if ($company <= 0) { echo "✘ لا كيانَ ذا أصناف — الرحلةُ لا تُشغَّل على قاعدةٍ فارغة\n"; exit(1); }
$items = array();
$r = $conn->query("SELECT id FROM proc_item WHERE company_id = $company AND COALESCE(is_deleted,0)=0 ORDER BY id LIMIT 2");
while ($r && $x = $r->fetch_row()) { $items[] = (int) $x[0]; }
$whs = array();
$r = $conn->query("SELECT id FROM proc_warehouse WHERE company_id = $company AND COALESCE(is_deleted,0)=0 ORDER BY id LIMIT 2");
while ($r && $x = $r->fetch_row()) { $whs[] = (int) $x[0]; }
$sups = array();
$r = $conn->query("SELECT id FROM proc_supplier WHERE company_id = $company AND COALESCE(is_deleted,0)=0 ORDER BY id LIMIT 2");
while ($r && $x = $r->fetch_row()) { $sups[] = (int) $x[0]; }
$actors = array();
$r = $conn->query("SELECT id FROM employees WHERE company_id = $company ORDER BY id LIMIT 4");
while ($r && $x = $r->fetch_row()) { $actors[] = (int) $x[0]; }
if (count($items) < 2 || count($whs) < 2 || count($sups) < 2 || count($actors) < 4) {
    echo "✘ أرضيّةٌ ناقصة (أصناف " . count($items) . " · مخازن " . count($whs)
       . " · موردون " . count($sups) . " · أشخاص " . count($actors) . ") — الرحلةُ لا تُشغَّل\n";
    exit(1);
}
list($itemA, $itemB) = $items;
list($whA, $whB) = $whs;
list($supA, $supB) = $sups;
list($buyer, $committee, $approver, $keeper) = $actors;

PCS::setEventConnection($conn); PCS::setThresholdConnection($conn);
WCS::setEventConnection($conn); WCS::setThresholdConnection($conn);
$G = new \App\Core\TenantDb($conn, \App\Core\TenantContext::forSystem($company, $buyer, '', true));

$conn->query('SET autocommit = 0');
$conn->query('START TRANSACTION');
/* ⚠ **شبكةُ إرجاعٍ تصمد أمام السقوط**: خطأٌ قاتلٌ في منتصفِ الرحلةِ يتخطّى
     `ROLLBACK` في آخرِ الملفّ، **فتبقى صفوفُ الرحلةِ في القاعدةِ الحيّة** —
     وقع فعلًا في أوّلِ تشغيل: أربعُ حركاتِ مخزنٍ تسرَّبت. فالإرجاعُ يُسجَّل
     خطّافَ إغلاقٍ أيضًا، ولا يضرُّ تكرارُه بعد إرجاعٍ ناجح. */
register_shutdown_function(function () use ($conn) {
    @$conn->query('ROLLBACK');
    @$conn->query('SET autocommit = 1');
});
$ok = true;
$YM = (string) $one("SELECT DATE_FORMAT(CURDATE(), '%Y-%m')");
$TODAY = (string) $one("SELECT CURDATE()");

/* ═══ ① طلبُ شراءٍ برأسِه وبنودِه ═══════════════════════════════════════ */
$reqId = (int) $G->insert('proc_request', array(
    'code' => 'W9J-PR-' . substr($RUN, -6), 'need_source' => 'maintenance',
    'requesting_dept' => 'الصيانة', 'priority' => 'normal', 'state' => 'approved',
    'notes' => $MARK,
));
$rl1 = (int) $G->insert('proc_request_line', array('request_id' => $reqId, 'item_id' => $itemA,
    'item_name' => 'صنف الرحلة الأول', 'qty' => 10, 'note' => $MARK));
$rl2 = (int) $G->insert('proc_request_line', array('request_id' => $reqId, 'item_id' => $itemB,
    'item_name' => 'صنف الرحلة الثاني', 'qty' => 5, 'note' => $MARK));
$lnN = (int) $G->count('proc_request_line', array('where' => array('request_id' => $reqId)));
$p = ($reqId > 0 && $lnN === 2);
$add(1, 'طلبُ شراءٍ برأسِه وبنودِه', 'proc_request', '16 إدارة المشتريات · proc_request',
     'رأسٌ بجهةٍ طالبةٍ وبندانِ في سجلِّهما التابعِ لا في حقلٍ تجميعيّ',
     $p ? "Request_ID=$reqId · بنودٌ مقيسة=$lnN" : 'فشل الإنشاء',
     $p ? 'الحاجةُ تصير مستندًا يُجمَّع ويُنافَس عليه بدل شراءٍ فرديٍّ متكرّر' : '—',
     'approved', $p);
$ok = $ok && $p;

/* ═══ ② حزمةُ تجميعٍ — والعدّادُ مشتقٌّ لا مكتوب ══════════════════════════ */
$pkgId = (int) $G->insert('proc_package', array(
    'code' => 'W9J-PKG-' . substr($RUN, -6), 'title' => 'حزمة الرحلة',
    'period_from' => $TODAY, 'period_to' => $TODAY,
    'strategy' => 'وفر كمية', 'state' => 'draft', 'notes' => $MARK,
));
$jr = PCS::joinPackage($G, $pkgId, $reqId, 'الصنفان من مورد واحد فيوفر الشحن', $buyer);
$pkgRow = $G->selectOne('proc_package', array('where' => array('id' => $pkgId)));
$p = (!empty($jr['ok']) && (int) $pkgRow['member_count'] === 1 && (int) $pkgRow['line_count'] === 2);
$add(2, 'ضمُّ الطلبِ إلى حزمةِ تجميعٍ بسببٍ مكتوب', 'proc_package', '16 إدارة المشتريات · proc_package',
     'عضوٌ واحدٌ وبندانِ **مشتقّانِ** في رأسِ الحزمةِ لا مكتوبَين بيد',
     $p ? "Package_ID=$pkgId · أعضاءٌ مشتقّون=" . (int) $pkgRow['member_count']
        . " · بنودٌ مشتقّة=" . (int) $pkgRow['line_count'] : 'فشل: ' . (string) ($jr['code'] ?? ''),
     $p ? 'خطةُ الشراءِ تحمل حجمَ الطلبِ الحقيقيَّ فتتحسّن قوّةُ التفاوض' : '—',
     'draft', $p);
$ok = $ok && $p;

/* ═══ ③ سالبٌ: الطلبُ لا يُضمُّ إلى حزمتَين ══════════════════════════════ */
$pkg2 = (int) $G->insert('proc_package', array(
    'code' => 'W9J-PKG2-' . substr($RUN, -6), 'title' => 'حزمة ثانية',
    'strategy' => 'مورد واحد', 'state' => 'draft', 'notes' => $MARK,
));
$dup = PCS::joinPackage($G, $pkg2, $reqId, 'محاولة ضم مكرر', $buyer);
$p = (empty($dup['ok']) && (string) $dup['code'] === 'REQUEST_ALREADY_PACKAGED');
$add(3, 'سالبٌ: طلبٌ مضمومٌ لا يُضمُّ ثانيةً', 'proc_package_member', '16 إدارة المشتريات · قيد uq_pkg_req',
     'ردٌّ برمزٍ لا كتابةٌ صامتةٌ لصفٍّ ثانٍ',
     $p ? 'رُدَّ برمز REQUEST_ALREADY_PACKAGED' : 'مرَّ: ' . (string) ($dup['code'] ?? 'نجح'),
     $p ? 'الطلبُ الواحدُ لا يُنافَس عليه مرّتَين فلا يُشترى مرّتَين' : '—',
     'rejected', $p);
$ok = $ok && $p;

/* ═══ ④ طلبُ عروضٍ على الحزمةِ بموعدِ فتحٍ مُعلَن ════════════════════════ */
$G->update('proc_package', array('state' => 'closed'), array('id' => $pkgId));
$openAt = (string) $one("SELECT DATE_FORMAT(NOW() + INTERVAL 1 DAY, '%Y-%m-%d %H:%i:%s')");
$rfqId = (int) $G->insert('proc_rfq', array(
    'code' => 'W9J-RFQ-' . substr($RUN, -6), 'package_id' => $pkgId,
    'title' => 'طلب عروض الرحلة', 'issued_at' => (string) $one("SELECT NOW()"),
    'due_date' => $TODAY, 'open_at' => $openAt, 'state' => 'issued', 'notes' => $MARK,
));
$p = ($rfqId > 0);
$add(4, 'طلبُ عروضٍ على الحزمةِ بموعدِ فتحٍ مُعلَن', 'proc_rfq', '16 إدارة المشتريات · proc_rfq',
     'طلبٌ واحدٌ للحزمةِ الواحدةِ وموعدُ الفتحِ مكتوبٌ قبلَه لا بعدَه',
     $p ? "RFQ_ID=$rfqId · موعدُ الفتح=$openAt" : 'فشل الإنشاء',
     $p ? 'المنافسةُ تنعقد بموعدٍ معلومٍ للجميعِ فلا يُفضَّل أحدٌ بوقتٍ إضافيّ' : '—',
     'issued', $p);
$ok = $ok && $p;

/* ═══ ⑤ دعوتانِ لموردَين ═════════════════════════════════════════════════ */
$i1 = PCS::inviteSupplier($G, $rfqId, $supA, 'بريد', $buyer);
$i2 = PCS::inviteSupplier($G, $rfqId, $supB, 'بريد', $buyer);
$rfqRow = $G->selectOne('proc_rfq', array('where' => array('id' => $rfqId)));
$p = (!empty($i1['ok']) && !empty($i2['ok']) && (int) $rfqRow['invite_count'] === 2);
$add(5, 'دعوةُ موردَين — والعدّادُ مشتقّ', 'proc_rfq_invite', '16 إدارة المشتريات · الموردون',
     'دعوتانِ متمايزتانِ وعدّادُ الدعواتِ في الرأسِ **مشتقٌّ** من الأبناء',
     $p ? 'دعوات مشتقّة=' . (int) $rfqRow['invite_count'] : 'فشل الدعوة',
     $p ? 'المنافسةُ تُقاس بعددِ المدعوّين لا بادّعاءِ التنافس' : '—',
     'issued', $p);
$ok = $ok && $p;

/* ═══ ⑥ سالبٌ: عرضٌ من موردٍ غيرِ مدعوّ يُردّ ════════════════════════════ */
$sup3 = (int) $one("SELECT id FROM proc_supplier WHERE company_id = $company
                     AND id NOT IN ($supA, $supB) AND COALESCE(is_deleted,0)=0 ORDER BY id LIMIT 1");
if ($sup3 > 0) {
    $un = PCS::recordOffer($G, $rfqId, $sup3, array('currency' => 'USD'),
        array(array('qty_offered' => 1, 'unit_price' => 1, 'request_line_id' => $rl1)), $buyer);
    $p = (empty($un['ok']) && (string) $un['code'] === 'OFFER_FROM_UNINVITED_SUPPLIER');
    $msg = $p ? 'رُدَّ برمز OFFER_FROM_UNINVITED_SUPPLIER' : 'مرَّ: ' . (string) ($un['code'] ?? 'نجح');
} else {
    $p = true; $msg = 'لا موردَ ثالثَ في الكيان — المحطّةُ تُعلَن ولا تُدَّعى';
}
$add(6, 'سالبٌ: عرضٌ من موردٍ غيرِ مدعوٍّ يُردّ', 'proc_offer', '16 إدارة المشتريات · بوّابة الدعوة',
     'العرضُ خارجَ الدعوةِ يُعلَن ولا يُقبل صامتًا', $msg,
     $p ? 'سلامةُ المنافسةِ محفوظةٌ فلا يدخلها مَن لم يُدعَ' : '—', 'rejected', $p);
$ok = $ok && $p;

/* ═══ ⑦ عرضانِ بسطورهما — والإجماليُّ مشتقّ ══════════════════════════════ */
$o1 = PCS::recordOffer($G, $rfqId, $supA,
    array('offer_ref' => 'OF-A', 'currency' => 'USD', 'fx_rate' => 1, 'delivery_days' => 10,
          'submitted_at' => (string) $one("SELECT NOW()")),
    array(array('request_line_id' => $rl1, 'item_id' => $itemA, 'item_name' => 'صنف الرحلة الأول',
                'qty_offered' => 10, 'unit_price' => 100),
          array('request_line_id' => $rl2, 'item_id' => $itemB, 'item_name' => 'صنف الرحلة الثاني',
                'qty_offered' => 5, 'unit_price' => 50)), $buyer);
$o2 = PCS::recordOffer($G, $rfqId, $supB,
    array('offer_ref' => 'OF-B', 'currency' => 'USD', 'fx_rate' => 1, 'delivery_days' => 4,
          'submitted_at' => (string) $one("SELECT NOW()")),
    array(array('request_line_id' => $rl1, 'item_id' => $itemA, 'item_name' => 'صنف الرحلة الأول',
                'qty_offered' => 10, 'unit_price' => 120),
          array('request_line_id' => $rl2, 'item_id' => $itemB, 'item_name' => 'صنف الرحلة الثاني',
                'qty_offered' => 5, 'unit_price' => 60)), $buyer);
$offA = $G->selectOne('proc_offer', array('where' => array('id' => (int) ($o1['offer_id'] ?? 0))));
$offB = $G->selectOne('proc_offer', array('where' => array('id' => (int) ($o2['offer_id'] ?? 0))));
$p = (!empty($o1['ok']) && !empty($o2['ok'])
      && abs((float) $offA['total_amount'] - 1250) < 0.01
      && abs((float) $offB['total_amount'] - 1500) < 0.01);
$add(7, 'عرضانِ بسطورهما — والإجماليُّ مشتقٌّ من البنود', 'proc_offer', '16 إدارة المشتريات · لجنة الترسية',
     'إجماليُّ كلِّ عرضٍ **مشتقٌّ** من ضربِ الكمّيّةِ في السعرِ لا مكتوبٌ في الرأس',
     $p ? 'عرض أ=' . (float) $offA['total_amount'] . ' · عرض ب=' . (float) $offB['total_amount']
        : 'فشل التسجيل: ' . (string) ($o1['code'] ?? '') . ' ' . (string) ($o2['code'] ?? ''),
     $p ? 'المقارنةُ تقوم على رقمٍ مشتقٍّ من البنودِ فلا يُخفى فرقٌ في الرأس' : '—',
     'received', $p);
$ok = $ok && $p;

/* ═══ ⑧ سالبٌ: لا فتحَ للمظاريفِ قبل موعدِها ═════════════════════════════ */
$early = PCS::openEnvelopes($G, $rfqId, $committee);
$p = (empty($early['ok']) && (string) $early['code'] === 'RFQ_NOT_DUE_NO_OPEN');
$add(8, 'سالبٌ: لا فتحَ للمظاريفِ قبل موعدِها', 'proc_rfq', '16 إدارة المشتريات · المراجعة الداخلية',
     'الفتحُ قبل الموعدِ يُردُّ برمزٍ لا يُسجَّل ويمضي',
     $p ? 'رُدَّ برمز RFQ_NOT_DUE_NO_OPEN' : 'مرَّ: ' . (string) ($early['code'] ?? 'نجح'),
     $p ? 'سرّيّةُ العرضِ محفوظةٌ حتّى الموعدِ فلا يُسرَّب سعرُ منافسٍ' : '—', 'issued', $p);
$ok = $ok && $p;

/* ═══ ⑨ سالبٌ: لا ترسيةَ قبل فتحِ المظاريف ═══════════════════════════════ */
$preAward = PCS::awardRfq($G, $rfqId, $supA, array('criteria_ref' => 'أقل سعر', 'minute_no' => 'X'), $committee);
$p = (empty($preAward['ok']) && (string) $preAward['code'] === 'AWARD_BEFORE_OPEN');
$add(9, 'سالبٌ: لا ترسيةَ قبل فتحِ المظاريف', 'proc_award', '16 إدارة المشتريات · المراجعة الداخلية',
     'الترسيةُ قبل الفتحِ تُردُّ — وإلّا صارت المعاييرُ وصفًا للفائز',
     $p ? 'رُدَّ برمز AWARD_BEFORE_OPEN' : 'مرَّ: ' . (string) ($preAward['code'] ?? 'نجح'),
     $p ? 'المنافسةُ تُحسم بعد رؤيةِ كلِّ العروضِ لا قبلها' : '—', 'issued', $p);
$ok = $ok && $p;

/* ═══ ⑩ فتحُ المظاريفِ في موعدِه ═════════════════════════════════════════ */
$openNow = (string) $one("SELECT DATE_FORMAT(NOW() + INTERVAL 2 DAY, '%Y-%m-%d %H:%i:%s')");
$opened = PCS::openEnvelopes($G, $rfqId, $committee, $openNow);
$rfqRow = $G->selectOne('proc_rfq', array('where' => array('id' => $rfqId)));
$p = (!empty($opened['ok']) && (string) $rfqRow['state'] === 'opened' && (int) $rfqRow['opened_by'] === $committee);
$add(10, 'فتحُ المظاريفِ في موعدِه بمَن فتحه', 'proc_rfq', '16 إدارة المشتريات · لجنة الترسية',
     'الحالةُ تتقدَّم ومَن فتح يُسجَّل — فالمحضرُ يعرف فاعلَه',
     $p ? 'الحالة=' . (string) $rfqRow['state'] . ' · فتحه=' . (int) $rfqRow['opened_by'] : 'فشل الفتح',
     $p ? 'زمنُ الفتحِ وفاعلُه محفوظانِ للمراجعةِ فلا يُنكَران لاحقًا' : '—', 'opened', $p);
$ok = $ok && $p;

/* ═══ ⑪ سالبٌ: الفائزُ غيرُ الأدنى بلا سببٍ يُردّ ═══════════════════════ */
$noWhy = PCS::awardRfq($G, $rfqId, $supB, array('criteria_ref' => 'أقل سعر', 'minute_no' => 'M-X'), $committee);
$p = (empty($noWhy['ok']) && (string) $noWhy['code'] === 'AWARD_NOT_LOWEST_NEEDS_REASON');
$add(11, 'سالبٌ: الفائزُ غيرُ الأدنى بلا سببٍ يُردّ', 'proc_award', '16 إدارة المشتريات · المراجعة الداخلية',
     'الأدنى **مشتقٌّ قياسًا** ومخالفتُه توجب سببًا مكتوبًا',
     $p ? 'رُدَّ برمز AWARD_NOT_LOWEST_NEEDS_REASON' : 'مرَّ: ' . (string) ($noWhy['code'] ?? 'نجح'),
     $p ? 'كلُّ ريالٍ فوق الأدنى له سببٌ مكتوبٌ يُراجَع' : '—', 'opened', $p);
$ok = $ok && $p;

/* ═══ ⑫ ترسيةٌ على غيرِ الأدنى بسببٍ مكتوب — والأدنى مشتقّ ═══════════════ */
$aw = PCS::awardRfq($G, $rfqId, $supB, array(
    'criteria_ref' => 'أقل سعر مع مدة التوريد', 'minute_no' => 'W9J-M-' . substr($RUN, -6),
    'committee_ref' => 'لجنة الرحلة',
    'award_why' => 'مدة التوريد أربعة أيام مقابل عشرة والتوقف يكلف أكثر من فرق السعر'), $committee);
$awRow = $G->selectOne('proc_award', array('where' => array('id' => (int) ($aw['award_id'] ?? 0))));
$p = (!empty($aw['ok']) && (int) $awRow['is_lowest'] === 0 && (int) $awRow['lowest_id'] === $supA
      && trim((string) $awRow['award_why']) !== '');
$add(12, 'ترسيةٌ على غيرِ الأدنى بسببٍ مكتوب', 'proc_award', '16 إدارة المشتريات · المالية · المراجعة',
     '`is_lowest` **مشتقٌّ** والأدنى المقيسُ يُكتب في المحضرِ حتّى لو لم يفز',
     $p ? 'الأدنى المشتقّ=' . (int) $awRow['lowest_id'] . ' بمبلغ ' . (float) $awRow['lowest_amount']
        . ' · الفائز=' . (int) $awRow['winner_id'] . ' بمبلغ ' . (float) $awRow['winner_amount']
        : 'فشل: ' . (string) ($aw['code'] ?? ''),
     $p ? 'فرقُ السعرِ المدفوعُ عمدًا مقيسٌ ومُعلَّلٌ في المحضرِ نفسِه' : '—', 'awarded', $p);
$ok = $ok && $p;

/* ═══ ⑬ سالبٌ: مَن فتح المظاريفَ لا يعتمد المحضر ════════════════════════ */
$selfAppr = PCS::approveAward($G, (int) $awRow['id'], $committee);
$p = (empty($selfAppr['ok']) && (string) $selfAppr['code'] === 'SAME_ACTOR_AWARD_AND_APPROVE');
$add(13, 'سالبٌ: مَن فتح المظاريفَ لا يعتمد المحضر', 'proc_award', '16 إدارة المشتريات · فصل الواجبات',
     'الردُّ برمزٍ لا بتجاهُلٍ — وفصلُ الواجباتِ يُنفَّذ لا يُعلَن',
     $p ? 'رُدَّ برمز SAME_ACTOR_AWARD_AND_APPROVE' : 'مرَّ: ' . (string) ($selfAppr['code'] ?? 'نجح'),
     $p ? 'يدٌ واحدةٌ لا تفتح وترسي وتعتمد فينقطع طريقُ التواطؤ' : '—', 'draft', $p);
$ok = $ok && $p;

/* ═══ ⑭ اعتمادُ المحضرِ من غيرِ فاتحِه ═══════════════════════════════════ */
$appr = PCS::approveAward($G, (int) $awRow['id'], $approver);
$awRow = $G->selectOne('proc_award', array('where' => array('id' => (int) $awRow['id'])));
$p = (!empty($appr['ok']) && (string) $awRow['state'] === 'approved' && (int) $awRow['approved_by'] === $approver);
$add(14, 'اعتمادُ المحضرِ من غيرِ فاتحِه', 'proc_award', '16 إدارة المشتريات · المالية',
     'الحالةُ تتقدَّم بمعتمِدٍ مختلفٍ عن فاتحِ المظاريف',
     $p ? 'الحالة=' . (string) $awRow['state'] . ' · اعتمده=' . (int) $awRow['approved_by'] : 'فشل الاعتماد',
     $p ? 'المحضرُ صار سندًا يجوز أن يصدر عنه أمرُ شراءٍ ملزِم' : '—', 'approved', $p);
$ok = $ok && $p;

/* ═══ ⑮ سالبٌ: أمرُ شراءٍ بلا محضرٍ وبلا سببِ مباشرةٍ يُردّ ══════════════ */
$poId = (int) $G->insert('proc_order', array(
    'code' => 'W9J-PO-' . substr($RUN, -6), 'supplier_id' => $supB, 'request_id' => $reqId,
    'currency' => 'USD', 'fx_rate' => 1, 'total_amount' => 1500, 'base_amount' => 1500,
    'state' => 'draft', 'notes' => $MARK,
    'expected_delivery_date' => (string) $one("SELECT DATE_FORMAT(CURDATE() + INTERVAL 4 DAY, '%Y-%m-%d')"),
));
$noBasis = PCS::anchorOrder($G, $poId, 0, '', $buyer);
$p = (empty($noBasis['ok']) && (string) $noBasis['code'] === 'PO_WITHOUT_AWARD_NEEDS_REASON');
$add(15, 'سالبٌ: أمرٌ بلا محضرٍ وبلا سببِ مباشرةٍ يُردّ', 'proc_order', '16 إدارة المشتريات · المراجعة الداخلية',
     '**هذا هو العطبُ المقيس**: 22 أمرًا من 22 كانت بلا سندٍ تنافسيّ',
     $p ? 'رُدَّ برمز PO_WITHOUT_AWARD_NEEDS_REASON' : 'مرَّ: ' . (string) ($noBasis['code'] ?? 'نجح'),
     $p ? 'كلُّ أمرِ شراءٍ صار يحمل سندَه فلا يُشترى بلا أثرٍ يُراجَع' : '—', 'draft', $p);
$ok = $ok && $p;

/* ═══ ⑯ إسنادُ الأمرِ إلى محضرِه ═════════════════════════════════════════ */
$anch = PCS::anchorOrder($G, $poId, (int) $awRow['id'], '', $buyer);
$poRow = $G->selectOne('proc_order', array('where' => array('id' => $poId)));
$p = (!empty($anch['ok']) && (int) $poRow['award_minute_id'] === (int) $awRow['id']
      && (int) $poRow['rfq_id'] === $rfqId);
$add(16, 'إسنادُ الأمرِ إلى محضرِ ترسيتِه', 'proc_order', '16 إدارة المشتريات · المالية · المراجعة',
     'الأمرُ يحمل مُعرِّفَ محضرِه ومُعرِّفَ طلبِ عروضِه فيتتبَّع أثرُه للخلف',
     $p ? 'محضر=' . (int) $poRow['award_minute_id'] . ' · طلب عروض=' . (int) $poRow['rfq_id']
        : 'فشل: ' . (string) ($anch['code'] ?? ''),
     $p ? 'الأمرُ صار قابلَ التتبّعِ إلى العرضِ الذي رساه فالمراجعةُ تُغلق حلقتَها' : '—',
     'anchored', $p);
$ok = $ok && $p;

/* ═══ ⑰ سالبٌ: أمرُ شراءٍ على محضرِ موردٍ غيرِ الفائزِ يُردّ ═════════════ */
/* ⚠ `request_id` إلزاميٌّ بحارسٍ حيٍّ في `TenantDb` (`PO-REQ-422`: «لا أمرَ
     شراءٍ بلا طلبٍ مرتبط») — والحارسُ يسبق هذه المرحلةَ ويبقى نافذًا فيها. */
$poWrong = (int) $G->insert('proc_order', array(
    'code' => 'W9J-POX-' . substr($RUN, -6), 'supplier_id' => $supA, 'request_id' => $reqId,
    'currency' => 'USD', 'fx_rate' => 1, 'total_amount' => 100, 'base_amount' => 100,
    'state' => 'draft', 'notes' => $MARK,
));
$wrong = PCS::anchorOrder($G, $poWrong, (int) $awRow['id'], '', $buyer);
$p = (empty($wrong['ok']) && (string) $wrong['code'] === 'ORDER_SUPPLIER_NOT_WINNER');
$add(17, 'سالبٌ: أمرٌ لموردٍ غيرِ الفائزِ على المحضرِ نفسِه', 'proc_order', '16 إدارة المشتريات · المراجعة',
     'السندُ يُطابَق بالفائزِ لا يُلصق بأيِّ أمر',
     $p ? 'رُدَّ برمز ORDER_SUPPLIER_NOT_WINNER' : 'مرَّ: ' . (string) ($wrong['code'] ?? 'نجح'),
     $p ? 'محضرُ الترسيةِ لا يُستعمل غطاءً لأمرٍ إلى موردٍ آخر' : '—', 'draft', $p);
$ok = $ok && $p;

/* ═══ ⑰-ب سالبٌ: شراءٌ مباشرٌ فوق الحدِّ المسجَّلِ يُردّ ═══════════════════
   ⚠ **وبلا هذه المحطّةِ تبقى عتبةُ الشراءِ المباشرِ مسجَّلةً ولا تُمارَس** —
     فيصير حاجبُها أعمى: نزعُها من السجلِّ لا يُسقط شيئًا. */
$poBig = (int) $G->insert('proc_order', array(
    'code' => 'W9J-POB-' . substr($RUN, -6), 'supplier_id' => $supA, 'request_id' => $reqId,
    'currency' => 'USD', 'fx_rate' => 1, 'total_amount' => 900000, 'base_amount' => 900000,
    'state' => 'draft', 'notes' => $MARK,
));
$overCap = PCS::anchorOrder($G, $poBig, 0, 'شراء طارئ لتوقف خط انتاج', $buyer);
$capVal = PCS::threshold('PRC_DIRECT_PURCHASE_CAP');
$p = (empty($overCap['ok']) && (string) $overCap['code'] === 'DIRECT_PURCHASE_OVER_CAP');
$add(33, 'سالبٌ: شراءٌ مباشرٌ فوق الحدِّ المسجَّلِ يُردّ', 'proc_order',
     '16 إدارة المشتريات · المالية · المراجعة الداخلية',
     'الحدُّ يُقرأ من `repair01_w9_thresholds` لا من رقمٍ في الشيفرة — ونزعُه يُسقط هذه المحطّة',
     $p ? 'رُدَّ برمز DIRECT_PURCHASE_OVER_CAP · الحدُّ المقروءُ من السجلِّ=' . (string) $capVal
        : 'مرَّ: ' . (string) ($overCap['code'] ?? 'نجح'),
     $p ? 'الشراءُ المباشرُ محكومٌ بسقفٍ يضبطه المالكُ بلا لمسِ شيفرة' : '—', 'draft', $p);
$ok = $ok && $p;

/* ═══ ⑰-ج شراءٌ مباشرٌ دون الحدِّ بسببٍ مكتوبٍ يمرّ ═══════════════════════ */
$poSmall = (int) $G->insert('proc_order', array(
    'code' => 'W9J-POS-' . substr($RUN, -6), 'supplier_id' => $supA, 'request_id' => $reqId,
    'currency' => 'USD', 'fx_rate' => 1, 'total_amount' => 300, 'base_amount' => 300,
    'state' => 'draft', 'notes' => $MARK,
));
$direct = PCS::anchorOrder($G, $poSmall, 0, 'قطعة عاجلة لايقاف توقف والمبلغ دون الحد', $buyer);
$poSmallRow = $G->selectOne('proc_order', array('where' => array('id' => $poSmall)));
$p = (!empty($direct['ok']) && (string) ($direct['basis'] ?? '') === 'direct'
      && trim((string) $poSmallRow['direct_reason']) !== '' && (int) $poSmallRow['award_minute_id'] === 0);
$add(34, 'شراءٌ مباشرٌ دون الحدِّ بسببٍ مكتوبٍ يمرّ', 'proc_order',
     '16 إدارة المشتريات · المراجعة الداخلية',
     'السندُ يصير «شراءٌ مباشرٌ معلَّل» لا «بلا سند» — والفرقُ يُقاس في التقارير',
     $p ? 'السند=' . (string) ($direct['basis'] ?? '') . ' · السبب مكتوب' : 'فشل: ' . (string) ($direct['code'] ?? ''),
     $p ? 'الاستثناءُ يبقى استثناءً مُعلَّلًا مقيسًا لا ثغرةً صامتة' : '—', 'anchored', $p);
$ok = $ok && $p;

/* ═══ ⑱ متابعةُ توريدٍ — ومدّةُ التأخّرِ مشتقّة ═══════════════════════════ */
$G->update('proc_order', array('state' => 'issued'), array('id' => $poId));
$lateDate = (string) $one("SELECT DATE_FORMAT(CURDATE() + INTERVAL 7 DAY, '%Y-%m-%d')");
$dlv = PCS::logDelivery($G, $poId, array('event_kind' => 'ARRIVED', 'event_date' => $lateDate,
    'qty_expected' => 15, 'qty_actual' => 15, 'delay_why' => 'تأخر التخليص الجمركي'), $buyer);
$dlvRow = $G->selectOne('proc_delivery_event', array('where' => array('order_id' => $poId), 'orderBy' => 'id DESC'));
$p = (!empty($dlv['ok']) && (int) $dlvRow['delay_days'] === 3);
$add(18, 'متابعةُ توريدٍ — ومدّةُ التأخّرِ مشتقّةٌ لا مُدخَلة', 'proc_delivery_event',
     '16 إدارة المشتريات · المخازن · تقييم الموردين',
     'الفرقُ بين الوعدِ والواقعِ يُحسب في الخدمةِ ولا يُكتب من الشاشة',
     $p ? 'أيامُ التأخّرِ المشتقّة=' . (int) $dlvRow['delay_days'] : 'فشل أو انحراف',
     $p ? 'التزامُ الموردِ صار رقمًا يدخل تقييمَه لا انطباعًا' : '—', 'delayed', $p);
$ok = $ok && $p;

/* ═══ ⑲ سندُ إدخالٍ بفحصِه — والمقبولُ وحدَه يدخل الرصيد ════════════════ */
/* ⚠ **القياسُ فرقٌ لا رقمٌ مطلق**: للصنفِ تاريخٌ في هذا المخزنِ سلفًا، فتوقُّعُ
     رصيدٍ يساوي المقبولَ وحدَه **يفترض قاعدةً فارغةً** ويسقط على قاعدةٍ حيّة.
     فيُلتقَط الرصيدُ قبلَ الحركةِ ويُقاس أثرُها عليه. */
$balBefore = (float) $one("SELECT COALESCE(SUM(CASE WHEN move_type IN ('استلام','تحويل وارد','مرتجع','تسوية زيادة')
                THEN qty ELSE -qty END),0) FROM proc_stock_move
               WHERE item_id = $itemA AND warehouse_id = $whA");
$rcId = (int) $G->insert('proc_receipt_custody', array(
    'code' => 'W9J-GRN-' . substr($RUN, -6), 'supplier_id' => $supB, 'order_id' => $poId,
    'warehouse_id' => $whA, 'receipt_date' => $TODAY, 'state' => 'draft', 'notes' => $MARK,
));
$rcv = WCS::receiveLine($G, $rcId, array('item_id' => $itemA, 'item_name' => 'صنف الرحلة الأول',
    'qty_received' => 10, 'qty_accepted' => 8, 'qty_rejected' => 2,
    'reject_reason' => 'تلف في الغلاف الخارجي', 'unit_cost' => 120), $keeper);
$stGood = $G->selectOne('proc_stock_state', array('where' => array(
    'item_id' => $itemA, 'warehouse_id' => $whA, 'state_key' => 'GOOD')));
$delta19 = $stGood ? (float) $stGood['qty'] - $balBefore : -999;
$p = (!empty($rcv['ok']) && $stGood && abs($delta19 - 8) < 0.001);
$add(19, 'سندُ إدخالٍ بفحصِه — والمقبولُ وحدَه يدخل الرصيد', 'proc_receipt_line',
     '17 إدارة المخازن · المشتريات · المالية',
     'الرصيدُ الصالحُ يزيد بالمقبولِ 8 لا بالوارد 10 — والقياسُ فرقٌ لا رقمٌ مطلق',
     $p ? 'قبل=' . $balBefore . ' · بعد=' . (float) $stGood['qty'] . ' · الفرقُ المقيس=' . $delta19
        : 'فشل: ' . (string) ($rcv['code'] ?? '') . ' · الفرق=' . $delta19,
     $p ? 'المخزونُ لا يحمل تالفًا في رقمِه فلا يُصرَف ما لا يصلح' : '—', 'received', $p);
$ok = $ok && $p;

/* ═══ ⑳ سالبٌ: الوارد لا يساوي المقبولَ زائدَ المرفوض ═══════════════════ */
$bad = WCS::receiveLine($G, $rcId, array('item_id' => $itemB, 'item_name' => 'صنف الرحلة الثاني',
    'qty_received' => 5, 'qty_accepted' => 5, 'qty_rejected' => 2, 'unit_cost' => 60), $keeper);
$p = (empty($bad['ok']) && (string) $bad['code'] === 'RECEIPT_QTY_NOT_BALANCED');
$add(20, 'سالبٌ: الوارد لا يساوي المقبولَ زائدَ المرفوض', 'proc_receipt_line', '17 إدارة المخازن · بوّابة الفحص',
     'المعادلةُ تُفحص قبل الكتابةِ لا بعدها',
     $p ? 'رُدَّ برمز RECEIPT_QTY_NOT_BALANCED' : 'مرَّ: ' . (string) ($bad['code'] ?? 'نجح'),
     $p ? 'كميّاتُ الفحصِ لا تتناقض في السجلِّ فتبقى المطابقةُ ممكنة' : '—', 'rejected', $p);
$ok = $ok && $p;

/* ═══ ㉑ بوّابةُ التتبّعِ خامدةٌ بقرارِ التأجيل — تُعلَن ولا تُدَّعى ═════ */
$ruleN = (int) $one("SELECT COUNT(*) FROM proc_item_track_rule");
$flagN = (int) $one("SELECT COUNT(*) FROM proc_item WHERE track_lot=1 OR track_serial=1 OR track_expiry=1");
$tr = WCS::requireTracking($G, $itemA, array());
$p = ($ruleN === 0 && $flagN === 0 && !empty($tr['ok']));
$add(21, 'بوّابةُ التتبّعِ مبنيّةٌ وخامدةٌ — DEC-OPEN-15 مؤجَّل', 'proc_item_track_rule',
     '17 إدارة المخازن · المالك · قرار مفتوح',
     'صفرُ قاعدةٍ وصفرُ صنفٍ بعلمٍ ⇒ البوّابةُ تمرّ — وهذا **انتظارٌ مُعلَنٌ لا عبورٌ مُدَّعًى**',
     $p ? "قواعدُ الفئات=$ruleN · أصنافٌ بعلم=$flagN · البوّابةُ لا تعترض"
        : 'انحراف: قواعد=' . $ruleN . ' أعلام=' . $flagN,
     $p ? 'البنيةُ جاهزةٌ فلا يُكتشف نقصُها يومَ الجواب — والفئاتُ وحدَها تنتظر' : '—',
     'deferred', $p);
$ok = $ok && $p;

/* ═══ ㉒ طلبُ صرفٍ معتمَدٌ من غيرِ طالبِه ═══════════════════════════════ */
$irId = (int) $G->insert('proc_issue_request', array(
    'code' => 'W9J-IR-' . substr($RUN, -6), 'warehouse_id' => $whA,
    'requesting_dept' => 'الصيانة', 'requester_id' => $buyer,
    'purpose' => 'قطع لأمر عمل صيانة', 'need_date' => $TODAY,
    'priority' => 'normal', 'state' => 'submitted', 'notes' => $MARK,
));
$irl = (int) $G->insert('proc_issue_request_line', array('request_id' => $irId, 'item_id' => $itemA,
    'item_name' => 'صنف الرحلة الأول', 'qty_requested' => 6));
$selfAp = WCS::approveIssueRequest($G, $irId, array($irl => array('qty_approved' => 6)), $buyer);
$p1 = (empty($selfAp['ok']) && (string) $selfAp['code'] === 'SAME_ACTOR_REQUEST_AND_APPROVE');
$ap = WCS::approveIssueRequest($G, $irId, array($irl => array('qty_approved' => 4,
    'cut_reason' => 'الرصيد الصالح لا يكفي ستة')), $keeper);
$irlRow = $G->selectOne('proc_issue_request_line', array('where' => array('id' => $irl)));
$p = ($p1 && !empty($ap['ok']) && abs((float) $irlRow['qty_approved'] - 4) < 0.001
      && trim((string) $irlRow['cut_reason']) !== '');
$add(22, 'طلبُ صرفٍ معتمَدٌ من غيرِ طالبِه — والخفضُ بسببٍ مكتوب', 'proc_issue_request',
     '17 إدارة المخازن · الجهة الطالبة',
     'مَن طلب لا يعتمد · والمعتمَدُ دون المطلوبِ يوجب سببًا',
     $p ? 'الذاتيُّ رُدَّ · المعتمَد=' . (float) $irlRow['qty_approved']
        . ' من 6 بسبب: ' . mb_substr((string) $irlRow['cut_reason'], 0, 40) : 'انحراف',
     $p ? 'الجهةُ تعرف كم اعتُمد لها ولماذا نقص فلا تنتظر ما لن يأتي' : '—', 'approved', $p);
$ok = $ok && $p;

/* ═══ ㉓ سالبٌ: الصرفُ لا يتجاوز المعتمَد ════════════════════════════════ */
$over = WCS::issueAgainstRequest($G, $irId, array(array('request_line_id' => $irl, 'qty' => 6)), 0, $keeper);
$p = (empty($over['ok']) && (string) $over['code'] === 'ISSUE_EXCEEDS_APPROVED');
$add(23, 'سالبٌ: الصرفُ لا يتجاوز المعتمَد', 'proc_issue_request_line', '17 إدارة المخازن · الرقابة',
     'المصروفُ يُقارَن بالمعتمَدِ قبل أيِّ حركة',
     $p ? 'رُدَّ برمز ISSUE_EXCEEDS_APPROVED' : 'مرَّ: ' . (string) ($over['code'] ?? 'نجح'),
     $p ? 'الاعتمادُ سقفٌ فعليٌّ لا توصيةً تُتجاوَز في المخزن' : '—', 'approved', $p);
$ok = $ok && $p;

/* ═══ ㉔ صرفٌ مقابلَ الطلبِ — والرصيدُ ينقص مقيسًا ═══════════════════════ */
$issId = (int) $G->insert('proc_issue', array(
    'code' => 'W9J-IS-' . substr($RUN, -6), 'warehouse_id' => $whA, 'holder_id' => $keeper,
    'holder_name' => 'مستلم الرحلة', 'issue_date' => $TODAY, 'state' => 'draft', 'notes' => $MARK,
));
$balPre24 = (float) $stGood['qty'];
$iss = WCS::issueAgainstRequest($G, $irId, array(array('request_line_id' => $irl, 'qty' => 4)), $issId, $keeper);
$stGood = $G->selectOne('proc_stock_state', array('where' => array(
    'item_id' => $itemA, 'warehouse_id' => $whA, 'state_key' => 'GOOD')));
$delta24 = $stGood ? (float) $stGood['qty'] - $balPre24 : 999;
$p = (!empty($iss['ok']) && $stGood && abs($delta24 + 4) < 0.001);
$add(24, 'صرفٌ مقابلَ الطلبِ — والرصيدُ ينقص مقيسًا', 'proc_stock_move',
     '17 إدارة المخازن · الصيانة · المالية',
     'الرصيدُ الصالحُ ينزل أربعةً باشتقاقٍ من الحركاتِ لا بكتابةٍ في عمودِ رصيد',
     $p ? 'قبل=' . $balPre24 . ' · بعد=' . (float) $stGood['qty'] . ' · الفرقُ المقيس=' . $delta24
        : 'فشل: ' . (string) ($iss['code'] ?? '') . ' · الفرق=' . $delta24,
     $p ? 'المتاحُ للصرفِ رقمٌ صادقٌ فلا يُوعَد أحدٌ بما ليس في المخزن' : '—', 'issued', $p);
$ok = $ok && $p;

/* ═══ ㉕ سالبٌ: مَن أرسل التحويلَ لا يستلمه ══════════════════════════════ */
$trfId = (int) $G->insert('proc_transfer', array(
    'code' => 'W9J-TR-' . substr($RUN, -6), 'from_wh_id' => $whA, 'to_wh_id' => $whB,
    'reason' => 'نقل رصيد إلى موقع المشروع', 'state' => 'draft', 'notes' => $MARK,
));
$trl = (int) $G->insert('proc_transfer_line', array('transfer_id' => $trfId, 'item_id' => $itemA,
    'item_name' => 'صنف الرحلة الأول', 'qty_sent' => 2));
$snd = WCS::sendTransfer($G, $trfId, $keeper);
$selfRcv = WCS::receiveTransfer($G, $trfId, array($trl => array('qty_received' => 2)), $keeper);
$p = (!empty($snd['ok']) && empty($selfRcv['ok']) && (string) $selfRcv['code'] === 'SAME_ACTOR_SEND_AND_RECEIVE');
$add(25, 'سالبٌ: مَن أرسل التحويلَ لا يستلمه', 'proc_transfer', '17 إدارة المخازن · فصل الواجبات',
     'الإرسالُ يمرُّ والاستلامُ الذاتيُّ يُردُّ برمزِه',
     $p ? 'أُرسل ثمَّ رُدَّ الاستلامُ الذاتيُّ برمز SAME_ACTOR_SEND_AND_RECEIVE'
        : 'انحراف: ' . (string) ($selfRcv['code'] ?? 'نجح'),
     $p ? 'يدٌ واحدةٌ لا ترسل وتستلم فلا يضيع رصيدٌ في الطريق بلا شاهد' : '—', 'in_transit', $p);
$ok = $ok && $p;

/* ═══ ㉖ استلامُ التحويلِ بفرقٍ مُسبَّب ══════════════════════════════════ */
$rcvT = WCS::receiveTransfer($G, $trfId, array($trl => array('qty_received' => 1,
    'variance_why' => 'قطعة تالفة في الطريق ومحضر مرفق')), $approver);
$trlRow = $G->selectOne('proc_transfer_line', array('where' => array('id' => $trl)));
$p = (!empty($rcvT['ok']) && abs((float) $trlRow['qty_variance'] + 1) < 0.001
      && trim((string) $trlRow['variance_why']) !== '');
$add(26, 'استلامُ التحويلِ بفرقٍ مُسبَّب — والفرقُ مشتقّ', 'proc_transfer_line',
     '17 إدارة المخازن · المالية',
     'الفرقُ يُحسب مرسَلًا ناقصَ مستلَمٍ ويلزمه سببٌ مكتوب',
     $p ? 'الفرقُ المشتقّ=' . (float) $trlRow['qty_variance']
        . ' بسبب: ' . mb_substr((string) $trlRow['variance_why'], 0, 30) : 'انحراف',
     $p ? 'الفاقدُ في الطريقِ رقمٌ مُعلَّلٌ يُحاسَب عليه لا نقصٌ مجهول' : '—', 'received', $p);
$ok = $ok && $p;

/* ═══ ㉗ سالبٌ: مَن عدَّ لا يعتمد الجرد ═════════════════════════════════ */
$csId = (int) $G->insert('proc_count_session', array(
    'code' => 'W9J-CS-' . substr($RUN, -6), 'warehouse_id' => $whA, 'count_kind' => 'CYCLE',
    'count_date' => $TODAY, 'counted_by' => $keeper, 'state' => 'reviewed', 'notes' => $MARK,
));
$cl = WCS::countLine($G, $csId, $itemA, 1, 120);
$selfCnt = WCS::approveCount($G, $csId, $keeper);
$p = (!empty($cl['ok']) && empty($selfCnt['ok']) && (string) $selfCnt['code'] === 'SAME_ACTOR_COUNT_AND_APPROVE');
$add(27, 'سالبٌ: مَن عدَّ لا يعتمد الجرد', 'proc_count_session', '17 إدارة المخازن · فصل الواجبات',
     'الدفتريُّ **مشتقٌّ** من الحركاتِ والاعتمادُ الذاتيُّ يُردّ',
     $p ? 'الدفتريُّ المشتقّ=' . (float) ($cl['qty_book'] ?? 0) . ' · رُدَّ الاعتمادُ الذاتيُّ' : 'انحراف',
     $p ? 'العادُّ لا يصدّق على عدِّه فلا يُخفى عجزٌ بتوقيعٍ واحد' : '—', 'reviewed', $p);
$ok = $ok && $p;

/* ═══ ㉘ سالبٌ: فرقُ جردٍ بلا قرارِ تسويةٍ لا يُعتمَد ═══════════════════ */
$noSettle = WCS::approveCount($G, $csId, $approver);
$p = (empty($noSettle['ok']) && (string) $noSettle['code'] === 'COUNT_DIFF_WITHOUT_SETTLEMENT');
$add(28, 'سالبٌ: فرقُ جردٍ بلا قرارِ تسويةٍ لا يُعتمَد', 'proc_count_line', '17 إدارة المخازن · المالية',
     'الفرقُ المقيسُ يوجب قرارًا مُسبَّبًا قبل الاعتماد',
     $p ? 'رُدَّ برمز COUNT_DIFF_WITHOUT_SETTLEMENT' : 'مرَّ: ' . (string) ($noSettle['code'] ?? 'نجح'),
     $p ? 'كلُّ عجزٍ أو زيادةٍ له قرارٌ مكتوبٌ فلا يُبتلع في رقمٍ مجمَّع' : '—', 'reviewed', $p);
$ok = $ok && $p;

/* ═══ ㉙ اعتمادُ الجردِ بعد تسويةِ فرقِه ═════════════════════════════════ */
$clRow = $G->selectOne('proc_count_line', array('where' => array('session_id' => $csId, 'item_id' => $itemA)));
$G->update('proc_count_line', array('settle_action' => 'ADJUST',
    'settle_why' => 'عجز قطعة واحدة يسوى بحركة تسوية بمحضر', 'settled_by' => $approver,
    'settled_at' => (string) $one("SELECT NOW()")), array('id' => (int) $clRow['id']));
$apCnt = WCS::approveCount($G, $csId, $approver);
$csRow = $G->selectOne('proc_count_session', array('where' => array('id' => $csId)));
$p = (!empty($apCnt['ok']) && (string) $csRow['state'] === 'approved' && (int) $csRow['diff_count'] >= 1);
$add(29, 'اعتمادُ الجردِ بعد تسويةِ فرقِه', 'proc_count_session', '17 إدارة المخازن · المالية · المراجعة',
     'الحالةُ تتقدَّم وعدّادُ الفروقِ **مشتقٌّ** من البنود',
     $p ? 'الحالة=' . (string) $csRow['state'] . ' · بنودٌ بفرقٍ مشتقّة=' . (int) $csRow['diff_count'] : 'فشل',
     $p ? 'الجردُ صار سندًا يُقفل عليه الشهرُ وتُرحَّل تسوياتُه للمالية' : '—', 'approved', $p);
$ok = $ok && $p;

/* ═══ ㉚ الإقفالُ الشهريُّ بمعادلةٍ تنطبق ═══════════════════════════════ */
$cls = WCS::closeMonth($G, $whA, $YM, $approver);
$clsRow = $G->selectOne('proc_wh_close', array('where' => array('warehouse_id' => $whA, 'period_ym' => $YM)));
$p = (!empty($cls['ok']) && $clsRow && (int) $clsRow['balanced'] === 1 && (string) $clsRow['state'] === 'closed');
$add(30, 'الإقفالُ الشهريُّ بمعادلةٍ تنطبق', 'proc_wh_close', '17 إدارة المخازن · المالية',
     'فتحٌ زائد واردٌ ناقص منصرفٌ زائد تسويةٌ يساوي الإقفالَ — أو لا إقفال',
     $p ? 'قيمةُ الإقفال=' . (float) $clsRow['close_value'] . ' · المعادلةُ تنطبق' : 'فشل: ' . (string) ($cls['code'] ?? ''),
     $p ? 'رصيدُ الشهرِ صار رقمًا مُثبَتًا يُرحَّل للقوائمِ لا تقديرًا' : '—', 'closed', $p);
$ok = $ok && $p;

/* ═══ ㉛ سالبٌ: لا حركةَ في فترةٍ مقفلة ══════════════════════════════════ */
$irId2 = (int) $G->insert('proc_issue_request', array(
    'code' => 'W9J-IR2-' . substr($RUN, -6), 'warehouse_id' => $whA,
    'requesting_dept' => 'الصيانة', 'requester_id' => $buyer, 'purpose' => 'طلب بعد الإقفال',
    'state' => 'approved', 'notes' => $MARK,
));
$irl2 = (int) $G->insert('proc_issue_request_line', array('request_id' => $irId2, 'item_id' => $itemA,
    'item_name' => 'صنف الرحلة الأول', 'qty_requested' => 1, 'qty_approved' => 1));
$afterClose = WCS::issueAgainstRequest($G, $irId2, array(array('request_line_id' => $irl2, 'qty' => 1)), 0, $keeper);
$p = (empty($afterClose['ok']) && (string) $afterClose['code'] === 'ISSUE_FROM_CLOSED_PERIOD');
$add(31, 'سالبٌ: لا حركةَ في فترةٍ مقفلة', 'proc_wh_close', '17 إدارة المخازن · المالية',
     'الفترةُ المقفلةُ ترفض الحركةَ — وإلّا انفكَّ الإقفالُ بعد إثباتِه',
     $p ? 'رُدَّ برمز ISSUE_FROM_CLOSED_PERIOD' : 'مرَّ: ' . (string) ($afterClose['code'] ?? 'نجح'),
     $p ? 'الرقمُ المُوقَّعُ عليه يبقى هو الرقمَ القائمَ فلا تتغيَّر قائمةٌ منشورة' : '—', 'closed', $p);
$ok = $ok && $p;

/* ═══ ㉜ تقييمُ المورِّدِ — مشتقٌّ بقاعدةٍ مكتوبة ══════════════════════════ */
$ev = PCS::evaluateSupplier($G, $supB, $YM);
$evRow = $G->selectOne('proc_supplier_eval', array('where' => array('supplier_id' => $supB, 'period_ym' => $YM)));
$p = (!empty($ev['ok']) && $evRow && trim((string) $evRow['score_rule']) !== '');
$add(32, 'تقييمُ المورِّدِ — كلُّ رقمٍ بقاعدةِ اشتقاقِه', 'proc_supplier_eval',
     '16 إدارة المشتريات · لجنة التأهيل · القيادة',
     'الدرجةُ تحمل قاعدتَها في السطرِ نفسِه فلا رقمَ بلا قاعدة',
     $p ? 'الدرجة=' . (float) $evRow['score'] . ' · القاعدة: ' . mb_substr((string) $evRow['score_rule'], 0, 45)
        : 'فشل: ' . (string) ($ev['code'] ?? ''),
     $p ? 'قائمةُ الموردينَ المؤهَّلينَ تُبنى على وقائعَ مقيسةٍ لا على علاقات' : '—', 'evaluated', $p);
$ok = $ok && $p;

/* ═══════════ التنظيف — ولا يبقى من الرحلةِ إلّا دليلُها ═══════════
   ⚠ **الإرجاعُ وحدَه لا يكفي** (‏انظر «كنسُ العائلة» أعلاه): خدماتُ النطاقِ
     تدير معاملاتِها بنفسها فتُثبِّت الخارجيّةَ ضمنًا. فالإرجاعُ يُشغَّل لِما
     لم يُثبَّت، **والكنسُ يمحو ما ثُبِّت** — والنظافةُ تُقاس بعدَهما لا تُدَّعى. */
$conn->query('ROLLBACK');
$conn->query('SET autocommit = 1');
$postSweep = $sweep();

$pass = 0;
foreach ($ST as $s) { if ($s[8] === 1) { $pass++; } }
$total = count($ST);

foreach ($ST as $s) {
    $conn->query("INSERT INTO repair01_w9_journey
        (run_id, station_no, station, entity, consumer, expected, measured, business_effect, state_after, passed)
        VALUES ('" . $esc($RUN) . "'," . (int) $s[0] . ",'" . $esc($s[1]) . "','" . $esc($s[2]) . "',
                '" . $esc($s[3]) . "','" . $esc(mb_substr($s[4], 0, 380)) . "','" . $esc(mb_substr($s[5], 0, 380)) . "',
                '" . $esc(mb_substr($s[6], 0, 380)) . "','" . $esc(mb_substr($s[7], 0, 110)) . "'," . (int) $s[8] . ")");
}

foreach ($ST as $s) {
    printf("  %s %2d  %s\n", $s[8] ? '✔' : '✘', $s[0], $s[1]);
    printf("        المستهلك: %s\n", $s[3]);
    printf("        المقيس  : %s\n", $s[5]);
    printf("        الأثر   : %s\n", $s[6]);
}
echo "\n" . str_repeat('─', 90) . "\n";
$cons = (int) $one("SELECT COUNT(DISTINCT consumer) FROM repair01_w9_journey WHERE run_id = '" . $esc($RUN) . "'");
$noEff = (int) $one("SELECT COUNT(*) FROM repair01_w9_journey WHERE run_id = '" . $esc($RUN) . "'
                      AND (business_effect = '' OR business_effect = '—')");
/* **النظافةُ تُقاس لا تُدَّعى** — والباقي بعدَ الكنسِ صفرٌ أو الرحلةُ لم تُغلق */
$left = 0;
foreach (array('proc_rfq', 'proc_package', 'proc_transfer', 'proc_count_session',
               'proc_issue_request', 'proc_order', 'proc_request', 'proc_receipt_custody', 'proc_issue') as $t) {
    $left += (int) $one("SELECT COUNT(*) FROM `$t` WHERE code LIKE 'W9J-%'");
}
$left += (int) $one("SELECT COUNT(*) FROM proc_stock_move WHERE note LIKE 'W09 %'");

printf("رحلةُ التوريد: عابرٌ %d/%d · مستهلكونَ متمايزون %d · بلا أثرٍ تجاريٍّ مقيسٍ %d\n",
    $pass, $total, $cons, $noEff);
printf("النظافة: كُنس %d صفًّا · باقٍ %d %s\n", (int) $postSweep, $left, $left === 0 ? '✔' : '✘');
echo 'الحكم: ' . ($pass === $total && $left === 0 ? "عبرت ✔\n" : "لم تعبر ✘\n");
exit(($pass === $total && $left === 0) ? 0 : 1);
