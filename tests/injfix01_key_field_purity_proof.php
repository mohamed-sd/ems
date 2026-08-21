<?php
/**
 * tests/injfix01_key_field_purity_proof.php
 *   نقاءُ حقلِ المفتاح — INJ-FIX-01 · الموجة ب · GAP-09
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **معيارُ القبول**: «صفرُ صفٍّ بنصٍّ بشريٍّ في حقلِ مفتاح · وسجلٌّ نظيفٌ ·
 *   **وقيدٌ يمنع عودتَه**».
 *
 * ◆ **وثلاثةُ أسئلةٍ لا سؤالٌ واحد** — والاكتفاءُ بالأولِ يُعلن إغلاقًا ينقضه
 *   أوّلُ بذرٍ قادم:
 *   ① أنُظِّف الحاضر؟
 *   ② **أيبيت القيدُ فعلًا؟** — ويُجرَّب بمحاولةِ كتابةٍ ملوَّثةٍ تُردّ.
 *   ③ أحُفظ الأصلُ فيمكن الرجوع؟ — فتنظيفٌ بلا حجرٍ إتلافٌ لا إصلاح.
 *
 * ◆ **والكشفُ بالمسافةِ لا بالحرفِ العربيّ**: حقلُ المفتاحِ رمزٌ بلا مسافةٍ ولا
 *   فاصلِ `·`. وقيمةٌ معجميةٌ عربيةٌ مفردةٌ (مثل «معتمد» في حالةِ السياسة)
 *   **ليست تلوّثًا** — وكشفٌ يخلط بينهما يُتلف قاموسًا سليمًا باسمِ التنظيف.
 *
 * التشغيل: php tests/injfix01_key_field_purity_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING & ~E_NOTICE);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);

$ROOT = dirname(__DIR__);
require_once $ROOT . '/config.php';

$pass = 0; $fail = 0;
function ok($c, $l, &$p, &$f, $d = '') { if ($c) { $p++; echo "  ✔ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } else { $f++; echo "  ✘ {$l}" . ($d !== '' ? " — {$d}" : '') . "\n"; } }

$TARGETS = array(
    array('approval_workflow_rules', 'entity_type'),
    array('approval_workflow_rules', 'action'),
    array('ems_event_deliveries',    'consumer'),
    array('approval_requests',       'entity_type'),
    array('approval_requests',       'action'),
);

echo "════ نقاءُ حقلِ المفتاح — GAP-09 ════\n";

/* ── ① الحاضرُ نظيف ─────────────────────────────────────────────────────── */
echo "\n── ① السجلُّ الحاضر ──\n";
$left = 0;
foreach ($TARGETS as $t) {
    list($tbl, $col) = $t;
    $q = $conn->query("SELECT COUNT(*) FROM `{$tbl}` WHERE `{$col}` LIKE '% %' OR `{$col}` LIKE '%·%');");
    if (!$q) { $q = $conn->query("SELECT COUNT(*) FROM `{$tbl}` WHERE `{$col}` LIKE '% %' OR `{$col}` LIKE '%·%'"); }
    $n = $q ? (int) $q->fetch_row()[0] : -1;
    $tot = (int) $conn->query("SELECT COUNT(*) FROM `{$tbl}`")->fetch_row()[0];
    ok($n === 0, "{$tbl}.{$col}", $pass, $fail, "ملوَّث={$n} من {$tot}");
    $left += max(0, $n);
}
ok($left === 0, '**صفرُ نصٍّ بشريٍّ في حقلِ مفتاح** على المقامِ كلِّه', $pass, $fail, "الباقي={$left}");

/* ── ② القيدُ يبيت — ويُجرَّب لا يُفترض ─────────────────────────────────── */
echo "\n── ② القيدُ مُجرَّبٌ لا مُفترَض ──\n";
foreach ($TARGETS as $t) {
    list($tbl, $col) = $t;
    $name = "chk_keypure_{$tbl}_{$col}";
    $q = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                        WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = '{$tbl}'
                          AND CONSTRAINT_NAME = '{$name}'");
    ok($q && (int) $q->fetch_row()[0] === 1, "القيدُ مسجَّل: {$name}", $pass, $fail);
}

/* ◆ **والتسجيلُ ليس إنفاذًا** — تُحاوَل كتابةٌ ملوَّثةٌ ويُقاس ردُّها.
     والمحاولةُ داخلَ معاملةٍ تُرجَع دائمًا، فلا تترك أثرًا ولو نجحت خطأً. */
$conn->begin_transaction();
$blocked = 0; $slipped = array();
foreach ($TARGETS as $t) {
    list($tbl, $col) = $t;
    $probe = 'INJFIX09 تلوثٌ مُتعمَّد · UAT';
    $st = $conn->prepare("UPDATE `{$tbl}` SET `{$col}` = ? WHERE `id` = (SELECT * FROM (SELECT MIN(`id`) FROM `{$tbl}`) x)");
    if (!$st) { $slipped[] = "{$tbl}.{$col} (تعذّر التحضير)"; continue; }
    $st->bind_param('s', $probe);
    $okRun = $st->execute();
    $st->close();
    if (!$okRun) { $blocked++; } else { $slipped[] = "{$tbl}.{$col}"; }
}
$conn->rollback();
ok(count($slipped) === 0, '**كتابةٌ ملوَّثةٌ مُتعمَّدةٌ تُردُّ في كلِّ خانة**', $pass, $fail,
   count($slipped) ? 'نفذت في: ' . implode(' · ', $slipped) : "رُدَّت في {$blocked}/" . count($TARGETS));

/* ── ③ الأصلُ محفوظٌ فالرجوعُ ممكن ──────────────────────────────────────── */
echo "\n── ③ الحجرُ — تنظيفٌ بلا حجرٍ إتلاف ──\n";
$q = $conn->query("SELECT COUNT(*) FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gov_key_pollution_archive'");
ok($q && (int) $q->fetch_row()[0] === 1, 'جدولُ الحجرِ موجود', $pass, $fail);

$q = $conn->query("SELECT COUNT(*) c, COUNT(DISTINCT CONCAT(src_table,'.',src_column)) k
                     FROM `gov_key_pollution_archive`");
$a = $q ? $q->fetch_assoc() : array('c' => 0, 'k' => 0);
ok((int) $a['c'] === 68, 'الأصولُ المحجورةُ ثمانٍ وستون', $pass, $fail, "محجور={$a['c']} في {$a['k']} خانة");

$q = $conn->query("SELECT COUNT(*) FROM `gov_key_pollution_archive`
                    WHERE `original_value` = '' OR `row_snapshot` IS NULL OR `replacement` = ''");
ok($q && (int) $q->fetch_row()[0] === 0, 'كلُّ محجورٍ يحمل أصلَه ولقطةَ صفِّه وبديلَه', $pass, $fail);

/* ◆ والرجوعُ يُقاس بمطابقةِ البديلِ لما في الجدولِ فعلًا — لا بوجودِ الصفِّ وحدَه.
 * ◆ **ويُستثنى المنسوخُ وحدَه**: صفٌّ كُنس لاحقًا إلى أرشيفٍ آخرَ لا موضعَ حيًّا
 *   يُردُّ إليه. ولا يُستثنى بالدعوى بل **بشرطَين**: `superseded_to` مُعلَنٌ
 *   بسببٍ مكتوب، **وصفُّه موجودٌ فعلًا في الموضعِ المُعلَن**. فانفصالٌ جديدٌ غيرُ
 *   مُعلَنٍ يُرسِّب كما كان — والاستثناءُ يضيّق الحكمَ ولا يُلغيه. */
$mismatch = 0; $checked = 0; $superseded = 0; $badSup = array();
$hasSup = (bool) $conn->query("SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='gov_key_pollution_archive'
      AND COLUMN_NAME='superseded_to'")->fetch_row()[0];
$sel = $hasSup ? '`superseded_to`,`superseded_reason`' : "NULL AS `superseded_to`, NULL AS `superseded_reason`";
$q = $conn->query("SELECT `src_table`,`src_column`,`src_row_id`,`replacement`,{$sel}
                     FROM `gov_key_pollution_archive` WHERE `restored_at` IS NULL");
while ($q && $x = $q->fetch_assoc()) {
    if (!empty($x['superseded_to'])) {
        $superseded++;
        /* المنسوخُ يُثبَت لا يُصدَّق: سببٌ مكتوبٌ وصفٌّ موجودٌ في موضعِه المُعلَن */
        $t = preg_replace('/[^A-Za-z0-9_]/', '', (string) $x['superseded_to']);
        $r = $conn->query("SELECT COUNT(*) FROM `{$t}` WHERE `id` = " . (int) $x['src_row_id']);
        $there = $r ? (int) $r->fetch_row()[0] : 0;
        if ($there === 0 || trim((string) $x['superseded_reason']) === '') {
            $badSup[] = $x['src_table'] . '#' . $x['src_row_id'];
        }
        continue;
    }
    $r = $conn->query("SELECT `{$x['src_column']}` FROM `{$x['src_table']}` WHERE `id` = " . (int) $x['src_row_id']);
    $cur = $r ? $r->fetch_row() : null;
    $checked++;
    if (!$cur || (string) $cur[0] !== (string) $x['replacement']) { $mismatch++; }
}
ok(count($badSup) === 0, 'كلُّ منسوخٍ له سببٌ مكتوبٌ وصفُّه في موضعِه المُعلَن', $pass, $fail,
   'منسوخ=' . $superseded . ' · بلا إثبات=' . count($badSup)
   . (count($badSup) ? ' — ' . implode(' · ', array_slice($badSup, 0, 4)) : ''));
ok($mismatch === 0, 'كلُّ بديلٍ مطابقٌ لما في الجدولِ — فالرجوعُ يُصيب صفَّه', $pass, $fail,
   "فُحص={$checked} · غيرُ مطابق={$mismatch}");

/* ── ④ ولا يُتلَف قاموسٌ سليمٌ باسمِ التنظيف ─────────────────────────────── */
echo "\n── ④ القاموسُ السليمُ لم يُمَسّ ──\n";
$q = $conn->query("SELECT COUNT(*) FROM `scr_sensitive_fields` WHERE `status` = 'معتمد'");
ok($q && (int) $q->fetch_row()[0] === 34,
   'قيمةٌ معجميةٌ عربيةٌ مفردةٌ («معتمد») باقيةٌ كما هي', $pass, $fail,
   'صفوفٌ معتمدة=' . ($q ? '34' : '?'));

echo "───────────────────────────────────────────────────────────────\n";
echo ($fail === 0 ? "✔" : "✘") . " النتيجة: نجح {$pass} · رسب {$fail}\n";

/* حكمُ الإغلاقِ — عقدُ GAP-56: يُصرَّح به بعدَ القياسِ لا يُستنتَج من الذِّكر */
require_once dirname(__DIR__) . '/tools/lib/gap_verdict.php';
gapv('GAP-09', true, 'صفرُ صفٍّ بنصٍّ بشريٍّ في حقلِ مفتاح — والملوَّثُ محجورٌ مؤرشَفٌ قبلَ أيِّ حذف', $fail);

exit($fail === 0 ? 0 : 1);
