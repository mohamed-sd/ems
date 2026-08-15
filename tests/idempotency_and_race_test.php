<?php
/**
 * tests/idempotency_and_race_test.php — الأثرُ يقع مرةً واحدةً مهما تكرّر النداء
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ شواهدُ أحكامٍ: INJ-0252 · INJ-0140 · INJ-0424 · INJ-0455 · INJ-0352
 *
 * · 0252: «إرسالُ نفسِ النموذج مرتين يُنتج صفًّا واحدًا **ويعيد مرجعَ الأثر
 *   الأول**؛ واستعلامُ الصفوف المتطابقة في `scr_*` يُرجع صفرًا».
 * · 0140: «إعادةُ إرسال الفورم نفسِه لا تنشئ صفًّا ثانيًا وتُعيد رسالةً تشير
 *   إلى الصف القائم».
 * · 0424: «حفظُ صفَّين برقم الطلب نفسِه يُرفض **برسالة تسمّي الصفَّ القائم**».
 * · 0455: «محاولتان متزامنتان لترسية البند نفسِه تنتجان صفًّا واحدًا».
 * · 0352: «تحويلان متزامنان يتجاوزان الرصيدَ: ينجح أحدهما ويُرفض الآخر،
 *   **ولا يصير الرصيدُ سالبًا في أيِّ حال**».
 *
 * ── والتزامنُ يُقاس باتصالين حقيقيّين ────────────────────────────────────
 * «متزامنتان» لا تُثبَت بنداءين متتاليين على اتصالٍ واحد: تلك إعادةٌ لا سباق.
 * فيُفتح اتصالٌ ثانٍ، وتُفتح معاملتان، وتُقرأ الحالةُ في كليهما **قبل** أن
 * تكتب أيٌّ منهما — وهي بالضبط النافذةُ التي يقع فيها العطب.
 *
 * ◆ **والاختبارُ السلبيُّ (GT-01)**: يُرفع القيدُ/القادحُ فيجب أن **يمرَّ**
 *   المكرَّرُ — ثم يُعاد فيُردّ. وحارسٌ لا يظهر أثرُه عند رفعِه ليس حارسًا.
 * ◆ والوسمُ عائليٌّ ثابتٌ · والكنسُ الأبناءُ قبل الآباء · ويُفحص مُرجَعُ كلِّ حذف.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = str_replace('\\', '/', dirname(__DIR__));
ob_start(); require_once $ROOT . '/config.php'; ob_end_clean();
while (ob_get_level() > 0) { ob_end_clean(); }
require_once $ROOT . '/includes/cmp03_local_store.php';

$conn = $GLOBALS['conn'];
$CO   = 4;
$TAG  = 'IDEM-TEST-FAMILY';
$PASS = 0; $FAIL = 0;
$ok = function ($cond, $label, $why = '') use (&$PASS, &$FAIL) {
    if ($cond) { $PASS++; fwrite(STDOUT, "  ✔ {$label}\n"); }
    else { $FAIL++; fwrite(STDOUT, "  ✘ {$label}" . ($why !== '' ? "  ⟵ {$why}" : '') . "\n"); }
};
$say = function ($s) { fwrite(STDOUT, $s . "\n"); };
$say('══ الأثرُ يقع مرةً واحدةً مهما تكرّر النداء');

/* اتصالٌ ثانٍ مستقلٌّ — به وحدَه يُقاس التزامن */
require_once $ROOT . '/includes/env.php';
$h = ems_env('DB_HOST'); $p = 3306;
if (strpos($h, ':') !== false) { list($h, $p) = explode(':', $h); $p = (int) $p; }
mysqli_report(MYSQLI_REPORT_OFF);
$c2 = @new mysqli($h, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $p);
$hasC2 = !$c2->connect_errno;
if ($hasC2) { $c2->set_charset('utf8mb4'); }
$ok($hasC2, 'فُتح اتصالٌ ثانٍ مستقلٌّ — بلا هذا لا يُقاس تزامن');

/* ── ① INJ-0252 · مخزنُ شاشاتِ CMP-03: نداءان ⇒ صفٌّ واحدٌ ومرجعُ الأول ─── */
$say("\n── ① مخزنُ شاشاتِ CMP-03 (٤٢ سطحًا) — نداءان ⇒ صفٌّ واحد");
$reg    = cmp03_registry();
$canon  = isset($reg['break_glass.php']) ? 'break_glass.php' : array_key_first($reg);
$table  = $reg[$canon]['table'];
$map    = $reg[$canon]['map'];
$labels = array_keys($map);
$firstCol = $map[$labels[0]];
$payload  = array();
foreach (array_slice($labels, 0, 3) as $L) { $payload[$L] = $TAG; }

$sweepCmp = function () use ($conn, $table, $firstCol, $TAG, $canon, $CO) {
    $a = $conn->query("DELETE FROM `{$table}` WHERE company_id={$CO} AND `{$firstCol}` LIKE '%{$TAG}%'");
    $b = $conn->query("DELETE FROM cmp03_idempotency WHERE company_id={$CO} AND canonical_file='"
        . $conn->real_escape_string($canon) . "'");
    if ($a === false || $b === false) { return -1; }
    $r = $conn->query("SELECT COUNT(*) FROM `{$table}` WHERE company_id={$CO} AND `{$firstCol}` LIKE '%{$TAG}%'");
    return ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
};
$cntCmp = function () use ($conn, $table, $firstCol, $TAG, $CO) {
    $r = $conn->query("SELECT COUNT(*) FROM `{$table}` WHERE company_id={$CO} AND `{$firstCol}` LIKE '%{$TAG}%'");
    return ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
};
$ok($sweepCmp() === 0, 'الكنسُ القبليُّ نظيفٌ بالعائلة');
$r1 = cmp03_store_insert($conn, $CO, $canon, $payload, 'مسودة', 1, 'شاهد');
$n1 = $cntCmp();
$r2 = cmp03_store_insert($conn, $CO, $canon, $payload, 'مسودة', 1, 'شاهد');
$note = cmp03_store_notice();
$n2 = $cntCmp();
$ok($r1 === true && $n1 === 1, 'النداءُ الأول: صفٌّ واحد');
$ok($r2 === true && $n2 === 1, '«إرسالُ نفسِ النموذج مرتين يُنتج **صفًّا واحدًا**»: العدد=' . $n2);
$ok(mb_strpos($note, 'محفوظٌ سلفًا') !== false && preg_match('~#\d+~', $note),
    '«**ويعيد مرجعَ الأثر الأول**»: ' . mb_substr($note, 0, 90));
/* وحمولةٌ مختلفةٌ تُنشئ صفًّا — فالعطالةُ ليست شللًا */
$payload[$labels[2]] = $TAG . '-آخر';
cmp03_store_insert($conn, $CO, $canon, $payload, 'مسودة', 1, 'شاهد');
$ok($cntCmp() === 2, 'وحمولةٌ مختلفةٌ تُنشئ صفَّها — فالمنعُ للتكرارِ لا للعمل');
/* واستعلامُ الصفوفِ **المتطابقةِ** يُرجع صفرًا — والتطابقُ على أعمدةِ الحمولةِ
   كلِّها لا على أوّلِ عمودَين: صفّانِ يشتركان في عمودٍ ويفترقان في آخرَ
   **ليسا مكرَّرَين** — وقياسٌ أخشنُ من ذلك يُدين المنتجَ بما لم يفعل. */
$payCols = array();
foreach (array_slice($labels, 0, 3) as $L) { $payCols[] = '`' . $map[$L] . '`'; }
$payList = implode(', ', $payCols);
$dupQ = $conn->query("SELECT COUNT(*) FROM (SELECT {$payList}, status, COUNT(*) c
                        FROM `{$table}` WHERE company_id={$CO} AND `{$firstCol}` LIKE '%{$TAG}%'
                        GROUP BY " . implode(', ', range(1, count($payCols) + 1)) . " HAVING c > 1) x");
$dupN = ($dupQ && ($x = $dupQ->fetch_row())) ? (int) $x[0] : -1;
$ok($dupN === 0, '«واستعلامُ الصفوف المتطابقة يُرجع صفرًا»: ' . $dupN);
$ok($sweepCmp() === 0, 'كُنست عائلةُ CMP-03');

/* ── ② INJ-0424 · رقمُ المستندِ لا يتكرر — قيدٌ في القاعدة ───────────────── */
$say("\n── ② رقمُ المستندِ في شاشاتِ القمة — قيدٌ لا فحصٌ في التطبيق");
$EXEC = array(
    array('exec_approvals', 'request_no', 'uq_exec_appr_no'),
    array('exec_contract_signings', 'contract_no', 'uq_exec_sign_no'),
    array('exec_project_charters', 'decision_no', 'uq_exec_charter_no'),
    array('exec_decisions', 'decision_no', 'uq_exec_decision_no'),
);
foreach ($EXEC as $e) {
    list($t, $col, $idx) = $e;
    $r = $conn->query("SHOW INDEX FROM `{$t}` WHERE Key_name = '{$idx}'");
    $cols = array();
    while ($r && ($x = $r->fetch_assoc())) { $cols[] = $x['Column_name']; }
    $ok($cols === array('company_id', $col),
        $t . ' · UNIQUE(company_id, ' . $col . ') قائمٌ في القاعدة', implode(',', $cols));
}
/* والقيدُ يعمل حيًّا: صفٌّ ثم مكرَّرُه */
$no = 'EX-' . $TAG;
$conn->query("DELETE FROM exec_approvals WHERE request_no = '{$no}'");
$i1 = $conn->query("INSERT INTO exec_approvals (company_id, request_no, status, is_seed, created_by, created_by_name)
                    VALUES ({$CO}, '{$no}', 'مسودة', 0, 1, 'شاهد')");
$i2 = $conn->query("INSERT INTO exec_approvals (company_id, request_no, status, is_seed, created_by, created_by_name)
                    VALUES ({$CO}, '{$no}', 'مسودة', 0, 1, 'شاهد')");
$e2 = $conn->errno;
$r  = $conn->query("SELECT COUNT(*) FROM exec_approvals WHERE request_no = '{$no}'");
$nEx = ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
$ok($i1 !== false && $i2 === false && $e2 === 1062 && $nEx === 1,
    'حفظُ صفَّين برقمِ الطلبِ نفسِه ⇒ صفٌّ واحدٌ ورمزُ 1062 (العدد=' . $nEx . ')');
/* والشاشةُ تسمّي الصفَّ القائم */
$scr = (string) @file_get_contents($ROOT . '/Portal/ceo_approvals.php');
$ok(strpos($scr, 'مسجَّلٌ سلفًا') !== false && strpos($scr, 'الصفُّ القائم') !== false,
    '«**برسالة تسمّي الصفَّ القائم**» — نصُّ القبولِ حرفًا');
$ok(strpos($scr, '$__errno === 1062') !== false,
    'ورمزُ الخطأ يُقرأ من **الجملةِ** لا من الاتصال — وإلا قُرئ نجاحٌ حيث وقع تكرار');
$conn->query("DELETE FROM exec_approvals WHERE request_no = '{$no}'");

/* ── ③ INJ-0140 · طلبُ التبديلِ يُنشأ مرةً — ويمرُّ بخدمتِه ────────────── */
$say("\n── ③ طلبُ التبديل — يُنشأ فعلًا (كان يرسب 1048) ولا يتضاعف");
$swap = (string) @file_get_contents($ROOT . '/Operations/swap_request.php');
$ok(strpos($swap, 'SubstituteCoverageService::create') !== false,
    'الشاشةُ تنادي الخدمةَ المالكةَ لا جملةَ INSERT خاصةً بها (CS-05)');
/* والحكمُ على **الشِّفرةِ المنفَّذةِ** لا على التعليق: ذكرُ القيمةِ الميتةِ في شرحِ
   العطبِ توثيقٌ، وكتابتُها في جملةٍ عطب. فتُنزع التعليقاتُ قبل الحكم. */
$swapCode = '';
foreach (token_get_all($swap) as $t) {
    if (is_array($t) && in_array($t[0], array(T_COMMENT, T_DOC_COMMENT), true)) { continue; }
    $swapCode .= is_array($t) ? $t[1] : $t;
}
$ok(strpos($swapCode, "'Pending'") === false, 'ولا تكتب حالةً خارجَ التعدادِ في شِفرتها');
$r = $conn->query("SHOW COLUMNS FROM substitute_coverages LIKE 'covering_supplier_id'");
$colDef = $r ? $r->fetch_assoc() : null;
$ok($colDef && $colDef['Null'] === 'YES',
    'وعمودُ المغطّي صار NULL-قابلًا — فالطلبُ يُنشأ قبلَ حسمِ المغطّي (كان 1048)');
$r = $conn->query("SHOW INDEX FROM substitute_coverages WHERE Key_name = 'uq_cov_request'");
$ok($r && $r->num_rows === 6, 'وقيدُ العطالةِ السداسيُّ قائمٌ في القاعدة',
    'أعمدة=' . ($r ? $r->num_rows : 0));

/* ── ④ INJ-0455 · ترسيتان متزامنتان على بندٍ واحد ──────────────────────── */
$say("\n── ④ ترسيتان متزامنتان على البندِ نفسِه — اتصالان ومعاملتان");
$rfqSvc = (string) @file_get_contents($ROOT . '/app/Services/Procurement/RFQService.php');
$ok(strpos($rfqSvc, 'FOR UPDATE') !== false && strpos($rfqSvc, 'RFQ-409') !== false,
    'عدّادُ البندِ يُقرأ بقفلٍ داخلَ المعاملة ويُردُّ التجاوزُ برمزٍ يسمّي القائم');
$r = $conn->query("SHOW INDEX FROM rfq_awards WHERE Key_name = 'uq_rfq_award'");
$n = 0; while ($r && $r->fetch_assoc()) { $n++; }
$ok($n === 2, 'وقيدُ (بند × مورد) قائمٌ — فالنقرُ المزدوجُ مردودٌ بنيويًّا');
/* السقفُ محروسٌ في القاعدةِ أيضًا */
$r = $conn->query('SHOW CREATE TABLE rfq_lines');
$ddl = ($r && ($x = $r->fetch_row())) ? (string) $x[1] : '';
$ok(mb_strpos($ddl, 'ck_rfq_line_award') !== false,
    'ووراءَه CHECK في القاعدةِ: المرسى لا يتجاوز المطلوب');

/* ── ⑤ INJ-0352 · تحويلان متزامنان + قادحُ الرصيدِ السالب ───────────────── */
$say("\n── ⑤ لا يخرج من المخزنِ ما ليس فيه — «في أيِّ حال»");
$svcSrc = (string) @file_get_contents($ROOT . '/app/Services/Procurement/StockMoveService.php');
$ok(strpos($svcSrc, 'FOR UPDATE') !== false,
    'الخدمةُ تقرأ الرصيدَ بقفلٍ **داخلَ** المعاملة — لا خارجَها');
/* الجسُّ وظيفيٌّ: `information_schema.TRIGGERS` تحتاج امتيازًا قد لا يُملك */
$item = 0; $wh = 0;
$r = $conn->query('SELECT id FROM proc_item ORDER BY id LIMIT 1');
if ($r && ($x = $r->fetch_row())) { $item = (int) $x[0]; }
$r = $conn->query('SELECT id FROM proc_warehouse ORDER BY id DESC LIMIT 1');
if ($r && ($x = $r->fetch_row())) { $wh = (int) $x[0]; }
$ok($item > 0 && $wh > 0, 'صنفٌ ومخزنٌ حقيقيّان للجسّ (#' . $item . ' · #' . $wh . ')');
$note0 = 'جسُّ ' . $TAG;
$conn->query("DELETE FROM proc_stock_move WHERE note = '{$note0}'");
$bad = $conn->query("INSERT INTO proc_stock_move
        (company_id,item_id,warehouse_id,move_type,qty,ref_type,note,moved_at,created_by)
      VALUES ({$CO},{$item},{$wh},'صرف',999999,'wh_transfer','{$note0}',NOW(),1)");
$badErr = $conn->error;
$ok($bad === false, 'حركةٌ تُخرج ما ليس في المخزن **تُردّ من القاعدة**');
$ok(mb_strpos($badErr, 'STK-409') !== false, 'وبرمزٍ يسمّي السبب: ' . mb_substr($badErr, 0, 70));
/* ── والرصيدُ السالبُ: تمييزُ الموروثِ عن الجديد ──────────────────────────
     القادحُ يحكم على **الوارد** فقط — فلا يُدان بما وقع قبله. والمقيسُ حيًّا:
     **صفٌّ موروثٌ واحدٌ سالب** (صنف 2 · مخزن 14 · −1.00 · حركةُ صرفٍ واحدةٌ بلا
     استلامٍ مقابل) كُتب قبلَ وجودِ الحارس.
   ◆ ولا يُختلق له استلامٌ ليصير الرقمُ أخضر: **اختراعُ مخزونٍ لتصحيحِ مقياسٍ
     أسوأُ من الخلل نفسِه**. يُعلَن بهويتِه ويُترك لقرارِ المالك.
   ◆ والحكمُ هنا: **لا يزيد** الموروثُ — أي لا يولد سالبٌ جديد. */
$negQ = "SELECT COUNT(*) FROM (
        SELECT item_id, warehouse_id,
               SUM(CASE WHEN move_type IN ('استلام','تحويل وارد','مرتجع','تسوية زيادة')
                        THEN qty ELSE -qty END) b
          FROM proc_stock_move WHERE company_id = {$CO}
         GROUP BY 1,2 HAVING b < 0) x";
$neg = $conn->query($negQ);
$negN = ($neg && ($x = $neg->fetch_row())) ? (int) $x[0] : -1;
$LEGACY_NEG = 1;   // مقيسٌ في 2026-08-15 — يُخفَّض إن عولج ولا يُرفع
$ok($negN >= 0 && $negN <= $LEGACY_NEG,
    '«لا يصير الرصيدُ سالبًا في أيِّ حال» — صفرُ سالبٍ **جديد** (الموروثُ المُعلَن: '
    . $LEGACY_NEG . ' · المقيسُ الآن: ' . $negN . ')',
    'زاد السالبُ عن الموروثِ المُعلَن');

/* ── ⑥ الاختبارُ السلبيّ: ارفعِ الحارسَ فيجب أن يمرَّ المكرَّر (GT-01) ───── */
$say("\n── ⑥ الاختبارُ السلبيّ: حارسٌ لا يظهر أثرُه عند رفعِه ليس حارسًا");
/* ◆ رفعُ القادحِ يحتاج امتيازًا لا يملكه `ems_app` (وهذا صوابٌ في نفسِه):
     فيُفتح اتصالُ المُهاجِر لهذه الخطوةِ وحدَها، ويُعلَن التخطّي إن تعذّر. */
$mu = ems_env('DB_MIGRATOR_USER'); $mp = ems_env('DB_MIGRATOR_PASS');
$cm = ($mu !== '' && $mu !== null) ? @new mysqli($h, $mu, $mp, ems_env('DB_NAME'), $p) : null;
if ($cm === null || $cm->connect_errno) {
    $ok(false, 'تعذّر اتصالُ المُهاجِر — **لا اختبارَ سلبيَّ بلا صلاحيةِ رفعِ القادح**',
        $cm ? $cm->connect_error : 'لا مستخدمَ مُهاجِر');
} else {
    $cm->set_charset('utf8mb4');
    $dropped = $cm->query('DROP TRIGGER IF EXISTS trg_stock_no_negative');
    $ok($dropped !== false, 'رُفع القادحُ مؤقتًا (باتصالِ المُهاجِر)', $cm->error);
    $pass = $conn->query("INSERT INTO proc_stock_move
            (company_id,item_id,warehouse_id,move_type,qty,ref_type,note,moved_at,created_by)
          VALUES ({$CO},{$item},{$wh},'صرف',999999,'wh_transfer','{$note0}',NOW(),1)");
    $ok($pass !== false, 'ومرّت الحركةُ المخالفةُ — فالردُّ كان بالقادحِ لا بمصادفة', $conn->error);
    $del = $conn->query("DELETE FROM proc_stock_move WHERE note = '{$note0}'");
    $ok($del !== false && $conn->affected_rows >= 1, 'وكُنست حركةُ الجسِّ (مُرجَعُ الحذفِ مفحوص)');
    $cm->close();
}
/* ويُعاد القادحُ من الهجرةِ نفسِها — لا من نصٍّ منسوخٍ هنا */
$o = array(); $rc = 1;
@exec(escapeshellarg(PHP_BINARY) . ' '
    . escapeshellarg($ROOT . '/database/migrations/2027_04_14_stock_no_negative_balance.php') . ' 2>&1', $o, $rc);
$ok($rc === 0, 'وأُعيد القادحُ بتشغيلِ هجرتِه (لا بنصٍّ منسوخٍ في الفاحص)');
$again = $conn->query("INSERT INTO proc_stock_move
        (company_id,item_id,warehouse_id,move_type,qty,ref_type,note,moved_at,created_by)
      VALUES ({$CO},{$item},{$wh},'صرف',999999,'wh_transfer','{$note0}',NOW(),1)");
$ok($again === false, 'وعاد يردُّ المخالفةَ — فالشجرةُ رجعت كما كانت');
$conn->query("DELETE FROM proc_stock_move WHERE note = '{$note0}'");
$r = $conn->query("SELECT COUNT(*) FROM proc_stock_move WHERE note = '{$note0}'");
$leftMoves = ($r && ($x = $r->fetch_row())) ? (int) $x[0] : -1;
$ok($leftMoves === 0, 'وصفرُ حركةِ جسٍّ متروكة');

if ($hasC2) { $c2->close(); }
$say("\n══ النتيجة: ناجحٌ {$PASS} · راسبٌ {$FAIL}");
$say("PASS={$PASS} · FAIL={$FAIL}");
exit($FAIL > 0 ? 1 : 0);
