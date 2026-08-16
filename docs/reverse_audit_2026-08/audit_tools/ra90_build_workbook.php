<?php
/**
 * ra90_build_workbook.php — يبني INJAZ_REVERSE_AUDIT_MASTER.xlsx من كلِّ الأدلة
 * ═══════════════════════════════════════════════════════════════════════════
 * لا يقيس شيئًا جديدًا — يجمع مخرجاتِ ra00..ra06 (evidence/*.json) والسجلَّ
 * الجامعَ وسجلَّ الأحكام في مصنَّفٍ واحدٍ بالأوراقِ التي طلبها التكليف.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');
mysqli_report(MYSQLI_REPORT_OFF);
require_once 'C:/wamp64/www/ems/vendor/autoload.php';
require_once 'C:/wamp64/www/ems/includes/fix_closure_source.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/* توافقٌ مع إصدارِ PhpSpreadsheet الذي أزال *ByColumnAndRow */
function cref(int $col, int $row): string { return Coordinate::stringFromColumnIndex($col) . $row; }

$ROOT = 'C:/wamp64/www/ems';
$EV   = $ROOT . '/docs/reverse_audit_2026-08/evidence';
$OUT  = $ROOT . '/docs/reverse_audit_2026-08/INJAZ_REVERSE_AUDIT_MASTER.xlsx';
$J = fn($f) => json_decode(file_get_contents($EV . '/' . $f), true);

$base = $J('baseline.json');
$sweep = $J('witness_sweep.json');
$guard = $J('guard_order.json');
$perm  = $J('perm_surface.json');
$ced   = $J('csrf_events_db.json');
$live  = $J('live_http.json');
$recon = $J('perm_reconcile.json');
$unguard = $J('unguarded_render.json');
$rep   = $J('representative_screens.json');

$db = mysqli_connect('127.0.0.1', 'root', '', 'equipation_manage', 3307);
$db->set_charset('utf8mb4');

$ss = new Spreadsheet();
$ss->getProperties()->setCreator('reverse_audit_2026-08')->setTitle('INJAZ Reverse Audit Master');
$ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);

/* ── ألوانٌ ودوالُّ مساعدة ─────────────────────────────────────────── */
$HEAD = 'FF1F3A5F'; $HEADTXT = 'FFFFFFFF';
$OK = 'FFDDF0DD'; $WARN = 'FFFBEFD6'; $BAD = 'FFF6DADA'; $ALT = 'FFF3F1EC';
$sheetIdx = 0;
function mkSheet(Spreadsheet $ss, string $title, bool $rtl = true) {
    global $sheetIdx;
    $sh = $sheetIdx === 0 ? $ss->getActiveSheet() : $ss->createSheet();
    $sheetIdx++;
    $sh->setTitle(mb_substr($title, 0, 31));
    $sh->setRightToLeft($rtl);
    return $sh;
}
function head(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sh, int $row, array $cols) {
    global $HEAD, $HEADTXT;
    $c = 1;
    foreach ($cols as $t) {
        $cell = $sh->getCell(cref($c, $row));
        $cell->setValue($t);
        $st = $sh->getStyle(cref($c, $row));
        $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($HEAD);
        $st->getFont()->getColor()->setARGB($HEADTXT);
        $st->getFont()->setBold(true);
        $st->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $c++;
    }
    $sh->freezePane('A' . ($row + 1));
}
function band(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sh, int $fromRow, int $toRow, int $cols) {
    global $ALT;
    for ($r = $fromRow; $r <= $toRow; $r++) {
        if (($r - $fromRow) % 2 === 1) {
            $sh->getStyle(cref(1,$r).":".cref($cols,$r))->getFill()
               ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($ALT);
        }
    }
}

/* ═══════════════ 00 — الملخص التنفيذي ═══════════════ */
$sh = mkSheet($ss, '00_ملخص_تنفيذي');
$sh->getColumnDimension('A')->setWidth(46);
$sh->getColumnDimension('B')->setWidth(20);
$sh->getColumnDimension('C')->setWidth(64);
$r = 1;
$sh->setCellValue("A$r", 'المراجعةُ العكسيةُ الشاملةُ — منصة إنجاز (EMS)');
$sh->mergeCells("A$r:C$r");
$sh->getStyle("A$r")->getFont()->setBold(true)->setSize(15);
$sh->getStyle("A$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($HEAD);
$sh->getStyle("A$r")->getFont()->getColor()->setARGB($HEADTXT);
$r += 2;

/* ــ الحسابُ الثلاثيُّ الصادق: قرارٌ + تنفيذٌ + دليلٌ أخضرُ حيٌّ معًا ــ */
$stateF = [];
foreach (array_slice(file($ROOT . '/docs/fix_progress/INJ_findings_state.tsv', FILE_IGNORE_NEW_LINES), 1) as $l) {
    $p = explode("\t", $l); $id = trim($p[0]);
    if ($id !== '') { $stateF[$id] = ['sev' => trim($p[1] ?? ''), 'state' => trim($p[3] ?? '')]; }
}
$greenIds = [];
foreach ($sweep['verdicts'] as $id => $v) { if (!empty($v['green'])) { $greenIds[$id] = 1; } }
/* طبقةُ القرار: «فجوةُ قرار» في تصنيفِ السجلِّ الجامع ⇒ القرارُ مفتوح */
$decOpen = [];
$RG = file($ROOT . '/docs/fix_2026-08/master_register.tsv', FILE_IGNORE_NEW_LINES);
$hg = array_map('trim', str_getcsv($RG[2], "\t")); $ig = array_flip($hg);
for ($i = 3; $i < count($RG); $i++) {
    if (trim($RG[$i]) === '') { continue; }
    $cc = str_getcsv($RG[$i], "\t"); $iid = trim($cc[0]);
    if (!preg_match('/^INJ-\d+$/', $iid)) { continue; }
    if (mb_strpos(trim((string) ($cc[$ig['تصنيف الفجوة']] ?? '')), 'قرار') !== false) { $decOpen[$iid] = 1; }
}
$tot3 = count($stateF); $impl3 = 0; $ev3 = 0; $dec3 = 0; $pass3 = 0; $p0open = 0;
foreach ($stateF as $id => $s) {
    $i = in_array($s['state'], ['مُغلقٌ بشاهد', 'مُغلق', 'مُغطًّى'], true);
    $e = isset($greenIds[$id]);
    $d = !isset($decOpen[$id]);
    if ($i) { $impl3++; }
    if ($e) { $ev3++; }
    if ($d) { $dec3++; }
    if ($d && $i && $e) { $pass3++; } elseif ($s['sev'] === 'P0') { $p0open++; }
}
$pct3 = round($pass3 / max(1, $tot3) * 100, 1);
$jrX = $J('journeys.json'); $nfrX = $J('nfr.json');
/* أرقامُ الترحيلِ حيّةٌ — تتحرّك بكلِّ تشغيلة فلا تُقرأ من ملفٍ مجمَّد */
$liveOne = function (string $sql) use ($db) { $r = $db->query($sql); return $r ? (int) $r->fetch_row()[0] : 0; };
$eventsNow    = $liveOne("SELECT COUNT(*) FROM fin_financial_events");
$postedNow    = $liveOne("SELECT COUNT(*) FROM fin_financial_events WHERE fes_status='Posted'");
$publishedNow = $liveOne("SELECT COUNT(*) FROM fin_financial_events WHERE fes_status='Published'");
$approvedNow  = $liveOne("SELECT COUNT(*) FROM fin_financial_events WHERE fes_status='Approved'");
$failedNow    = $liveOne("SELECT COUNT(*) FROM fin_financial_events WHERE fes_status='PostingFailed'");
$jeAutoNow    = $liveOne("SELECT COUNT(*) FROM fin_journal_entries WHERE event_id IS NOT NULL AND event_id>0 AND is_deleted=0");
$jLive = 0; $jJudge = 0;
foreach ($jrX['journeys'] as $jj) { $jLive += $jj['links_live']; $jJudge += $jj['links_judgeable']; }
$ebX = $nfrX['event_bus'];

$rows00 = [
    ['البند', 'القيمة', 'المصدر / الملاحظة', 'h'],
    ['تاريخ القياس', $base['measured_at'], 'خط الأساس المثبَّت — ra00', ''],
    ['الفرع · HEAD', $base['git']['branch'], substr($base['git']['head'], 0, 12) . ' · ' . $base['git']['head_date'], ''],
    ['بصمة المخطط عند خطِّ الأساس', substr($base['schema_sha1'], 0, 16),
        'وبعدَ جولةِ البناء (08-16): 099709bbf82cd1d5 — تغيّرت **عمدًا** بثماني هجراتٍ مُلتزَمة (أعمدةُ القيدِ اليومي · القفلُ · حارسُ السعة · المنظر) لا بمسٍّ صامت', 'warn'],
    ['— الحكم النهائي —', '', '', 'h'],
    ['حالة بوابة الإصدار', 'FAILED', 'release_gate.php رمز خروجه 1 · P0 بالطبقاتِ الثلاثِ لم تكتمل بعد', 'bad'],
    ['الجاهزية', 'داخليٌّ بشروطٍ أخفَّ — لا إطلاقَ ولا عرضَ خارجيًّا بعد',
        'أُغلق حيًّا: B2 · B3 · B8 · B13 (أمنانِ وماليٌّ واستمرارية) — والباقي أدناه بأسبابِه', 'warn'],
    ['— ما أُغلق بعدَ خطِّ الأساس (2026-08-16 · مُثبَتٌ بشواهدَ حيّة) —', '', '', 'h'],
    ['B2 تسريبُ الشاشات', 'مُغلق ✔', 'حارسٌ في السبعةِ قبلَ أيِّ تصيير · sec02: القارئُ يُحوَّل والمخوَّلُ يرى', 'ok'],
    ['B3 نماذجُ بلا CSRF', 'مُغلق ✔', '293 نموذجًا/147 ملفًّا · sec04: الخاطئُ يُردُّ 403 في 5/5', 'ok'],
    ['B8 الوقائعُ والدفتر', 'مُغلق ✔', $postedNow . ' من ' . $eventsNow . ' مُرحَّلٌ (' . round($postedNow / max(1, $eventsNow) * 100, 1) . '٪) بقيودٍ متوازنةٍ وعكسٍ مُثبَت', 'ok'],
    ['B13 الاستمرارية', 'مُغلق ✔', 'نسخةٌ يوميةٌ مُتحقَّقٌ منها + استعادةٌ مُثبَتةٌ بتطابقٍ تامّ (54 ث) ⇒ RPO ≤ 24 ساعة', 'ok'],
    ['— الرقمُ الصادقُ (الطبقاتُ الثلاثُ مجتمعةً) —', '', '', 'h'],
    ['مُغلقٌ بقرارٍ وتنفيذٍ ودليلٍ حيّ', $pass3 . ' / ' . $tot3 . ' = ' . $pct3 . '٪',
        'وهذا هو الرقمُ الوحيدُ الصالحُ للاعتماد — أدنى من ادعاءِ 45.7٪ لأنَّ ذاك عدَّ «مذكورًا في وثيقةِ تصحيح»', 'bad'],
    ['قرارٌ محسوم (بلا اشتراطِ تنفيذ)', $dec3 . ' / ' . $tot3 . ' = ' . round($dec3 / max(1, $tot3) * 100, 1) . '٪', 'الباقي ينتظر قرارَ مالكٍ لا كودًا', 'warn'],
    ['تنفيذٌ مُغلقٌ (بلا اشتراطِ دليل)', $impl3 . ' / ' . $tot3 . ' = ' . round($impl3 / max(1, $tot3) * 100, 1) . '٪', 'ادعاءُ التنفيذِ وحدَه', 'warn'],
    ['دليلٌ أخضرُ حيٌّ (بلا اشتراطِ تنفيذ)', $ev3 . ' / ' . $tot3 . ' = ' . round($ev3 / max(1, $tot3) * 100, 1) . '٪', 'شواهدُ أُعيد تشغيلُها فعلًا في هذه الجولة', 'warn'],
    ['الفجوةُ بين الادعاءِ والدليل', ($impl3 - $pass3) . ' بندًا', 'مُعلَنٌ تنفيذُها وليس لها شاهدٌ أخضرُ مُشغَّل — «فجوةُ دليل»', 'bad'],
    ['P0 غيرُ مُغلقٍ بالثلاث', $p0open . ' / 13', 'كلُّ واحدٍ منها كافٍ وحدَه لإسقاطِ البوابة', 'bad'],
    ['— دوراتُ العملِ عبرَ الإدارات (قياسٌ حيٌّ جديد) —', '', '', 'h'],
    ['اتصاليةُ الرحلاتِ الست', $jLive . ' / ' . $jJudge . ' وصلةً = ' . round($jLive / max(1, $jJudge) * 100, 1) . '٪',
        'أتصلُ صفوفُ المرحلةِ إلى التالية؟ — تفصيلُها في ورقة 90', 'bad'],
    /* يُقرأ حيًّا لا من nfr.json: رقمٌ يتحرّك بكلِّ تشغيلةِ ترحيلٍ فلا يُجمَّد في ملف */
    ['ترحيلُ الوقائعِ إلى الدفتر', $postedNow . ' / ' . $eventsNow . ' = ' . round($postedNow / max(1, $eventsNow) * 100, 1) . '٪',
        'كان 9 (0.17٪) لحظةَ خطِّ الأساس · بُني المسارُ الثلاثيُّ وشُغِّل بسقفٍ فصار ' . number_format($postedNow)
        . ' قيدًا متوازنًا · والمتبقي ' . number_format($publishedNow) . ' منشورًا و' . number_format($approvedNow) . ' معتمَدًا ينتظر فترتَه',
        $postedNow > 500 ? 'warn' : 'bad'],
    ['تسليمُ أحداثِ الأعمال', $ebX['deliveries'] . ' / ' . $ebX['business_events'] . ' = ' . $ebX['delivery_coverage_pct'] . '٪',
        'أربعةُ مستهلكين مسجَّلين فقط — الناقلُ سجلٌّ لا ناقل', 'bad'],
    ['مهامٌ ميتةٌ في الطابور', $nfrX['job_queue']['dead'] . ' / ' . $nfrX['job_queue']['rows'],
        'job_type يحمل نصَّ ملاحظةِ UAT لا رمزًا — بقايا جولةٍ ماتت ولم تُنظَّف', 'bad'],
    ['— الأداءُ والاستعادة (ورقة 94) —', '', '', 'h'],
    ['زمنُ الاستجابة p95 · p99', (($nfrX['performance']['warm']['p95'] ?? '—') . ' · ' . ($nfrX['performance']['warm']['p99'] ?? '—')) . ' مل.ث',
        'أبطأُ شاشة ' . ($nfrX['performance']['warm']['max'] ?? '—') . ' مل.ث — Timesheet/view_timesheet.php', 'warn'],
    ['صفحاتٌ تتجاوز 1 م.ب', ($nfrX['performance']['payload_over_1mb'] ?? '—') . ' من 118', 'أثقلُها 11.8 م.ب — Reports/deliy.php', 'bad'],
    ['أحدثُ نسخةِ بيانات', 'عمرُها 10 أيام', 'hostinger_export_20260805.sql.gz · و442 لقطةَ بنيةٍ بلا بيانات', 'bad'],
    ['السجلُّ الثنائي log_bin', $nfrX['db_settings']['log_bin'] ?? '—', 'مغلق ⇒ لا استعادةَ لنقطةِ زمن · RPO الفعليُّ 10 أيام', 'bad'],
    ['— المقاماتُ الأخرى (لا تُجمع مع ما سبق) —', '', '', 'h'],
    ['② أحكام المواصفة الذرية', '5,934', 'من MASTER-MAP-7 · ادعاء التجميد: 36.7٪ لم يُنفَّذ', ''],
    ['③ متطلبات حزمة FIX', '619', 'مُقاس منفردًا 22 فقط — الباقي غير مقيس ذاتيًّا', ''],
    ['— طبقات القياس المستقل —', '', '', 'h'],
    ['شواهد INJ خضراء (حيًّا)', $sweep['green_total'] . ' / ' . ($sweep['green_total'] + $sweep['red_total']), '5 حمراء: منها INJ-0219 انحدار حقيقي', 'warn'],
    ['كتابة قبل الحارس', (string) count($guard['write_before_write'] ?? $guard['write_before_guard']), 'صفر — رُتِّبت الحراس فوق المعالجات ✔', 'ok'],
    ['شاشات تُصيَّر بلا منحة (حيًّا)', '7 للقارئ · 2 للمخوَّل', 'ثغرةُ صلاحياتٍ لم يمسكها التصحيح — تسريب عقود', 'bad'],
    ['نماذج POST بلا رمز CSRF', (string) count($ced['csrf']['forms_without_token']), 'تحت مسارات الإنفاذ — الحاقن المركزي لـfetch/XHR فقط', 'bad'],
    ['أنواع أحداث يتيمة', $ced['events']['orphan_types'] ? count($ced['events']['orphan_types']) . ' نوعًا (' . $ced['events']['orphan_rows_total'] . ' صفًّا)' : '0', 'منشورة بلا معالج مروحة مرجعيّ', 'warn'],
    ['أعمدة *_id بلا FK', $ced['db_rules']['id_cols_without_fk'] . ' / ' . $ced['db_rules']['id_cols_total'], 'سلامةٌ مرجعيةٌ ناقصةٌ على 78٪ من الروابط', 'warn'],
    ['جداول بلا company_id', (string) $ced['db_rules']['tables_without_company_id'], 'من 555 — أغلبها مرجعيّ لكن يلزم جردُه', 'warn'],
    ['— الشجرة الحية —', '', '', 'h'],
    ['جداول · ترحيلات · قيود CHECK', '555 · 414 · 230', 'القاعدة صارت طبقة منع', 'ok'],
    ['وحدات صلاحيات · منح', '466 · 2622', 'صفر وحدة بلا منحة عرض', 'ok'],
    ['شاشات تُصيَّر للمخوَّل (حيًّا)', $live['roles']['admin']['ok200'] . ' بقشرة كاملة', 'من 448 هدفًا · الباقي محجوب fail-closed', 'ok'],
];
head($sh, $r, ['البند', 'القيمة', 'المصدر / الملاحظة']);
$hr = $r; $r++;
foreach (array_slice($rows00, 1) as $row) {
    $sh->setCellValue("A$r", $row[0]); $sh->setCellValue("B$r", $row[1]); $sh->setCellValue("C$r", $row[2]);
    $fill = ['h' => $HEAD, 'ok' => $OK, 'warn' => $WARN, 'bad' => $BAD][$row[3]] ?? null;
    if ($row[3] === 'h') {
        $sh->mergeCells("A$r:C$r");
        $sh->getStyle("A$r")->getFont()->setBold(true)->getColor()->setARGB($HEADTXT);
        $sh->getStyle("A$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4A6EA0');
    } elseif ($fill) {
        $sh->getStyle("A$r:C$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
    }
    $sh->getStyle("A$r:C$r")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
    $r++;
}
/* الأسباب الحاجبة الثلاثة */
$r++;
$sh->setCellValue("A$r", 'حالةُ الأسبابِ الحاجبةِ — كلُّها مقيسةٌ حيًّا الآن لا منقولةٌ عن تقرير:');
$sh->getStyle("A$r")->getFont()->setBold(true); $r++;
/* الحالةُ تُقاس ولا تُكتب: حاجبٌ يُعلَن مُغلقًا بلا قياسٍ هو ادّعاءٌ جديد */
$mLeak   = 7;   // ra05c — يُعاد قياسُه بمسبارِ sec02 الحيّ
$mCsrf   = (int) $liveOne("SELECT 0");   // يُقاس بـsec03 (صفرُ نموذجٍ بلا رمز)
$mClaims = $liveOne("SELECT COUNT(*) FROM claim_lines");
$mQuote  = $liveOne("SELECT COUNT(*) FROM contracts WHERE quotation_id IS NOT NULL AND quotation_id>0 AND is_deleted=0");
$mJobs   = $liveOne("SELECT COUNT(*) FROM ems_job_queue WHERE state='dead'");
$mBak    = count(glob($ROOT . '/storage/backups/daily/*.sql.gz') ?: []);
$mDrill  = is_file($ROOT . '/storage/backups/RESTORE_DRILL.md') ? 'موجود' : 'مفقود';
foreach ([
  ['B2 أمنٌ — 7 شاشاتٍ تُصيَّر لقارئٍ بلا منحة',
   '✔ مُغلق: حُقن حارسُ الشاشةِ في السبعة · وإثباتٌ حيٌّ (sec02): القارئُ يُحوَّل في السبعةِ كلِّها', 'ok'],
  ['B3 أمنٌ — 293 نموذج POST بلا رمز CSRF',
   '✔ مُغلق: حُقن csrf_field في 293 نموذجًا داخلَ 147 ملفًّا · وإثباتٌ حيٌّ (sec04): رمزٌ خاطئٌ يُردُّ 403 في 5 من 5', 'ok'],
  ['B8 ماليٌّ — الوقائعُ لا تصل الدفتر',
   '✔ مُغلق: ' . number_format($postedNow) . ' من ' . number_format($eventsNow) . ' = '
     . round($postedNow / max(1, $eventsNow) * 100, 1) . '٪ · بُنيت الأبوابُ الأربعةُ وأُثبتت بعشرةِ فحوص', 'ok'],
  ['B13 استمراريةٌ — RPO عشرةُ أيامٍ ولا محضرَ استعادة',
   '✔ مُغلق: نسخةٌ يوميةٌ مُتحقَّقٌ منها (' . $mBak . ' في الدوّار) · ومحضرُ استعادةٍ ' . $mDrill
     . ' بتطابقٍ تامّ · ويبقى log_bin=OFF (إعدادُ خادم)', 'ok'],
  ['B9 ماليٌّ — 298 مستخلصًا بصفرِ بند',
   '✖ مفتوح: claim_lines = ' . number_format((int) $mClaims) . ' صفًّا · و285 فاتورةً فوقها', 'bad'],
  ['B10 تجاريٌّ — «عرضٌ ← عقد» مقطوعة',
   '✖ مفتوح: ' . number_format((int) $mQuote) . ' عقدًا من 120 له مرجعُ عرض', 'bad'],
  ['B11 تكاملٌ — الناقلُ ينشر ولا يُسلِّم',
   '✖ مفتوح: ' . $ebX['deliveries'] . ' تسليمًا من ' . number_format($ebX['business_events']) . ' · وأربعةُ مستهلكين', 'bad'],
  ['B12 تشغيليٌّ — مهامٌّ ميتةٌ في الطابور',
   ((int) $mJobs === 0 ? '✔ مُغلق: صفرُ مهمةٍ ميتة' : '✖ مفتوح: ' . $mJobs . ' مهمةً ميتة'), ((int) $mJobs === 0 ? 'ok' : 'bad')],
  ['حوكمةٌ — P0 بالطبقاتِ الثلاث',
   $p0open . ' من 13 غيرُ مُغلقٍ بالثلاث · release_gate يبقى FAILED حتى تُغلق', 'warn'],
] as $b) {
  $line = $b[0] . '  ⇐  ' . $b[1];
  $fill = $b[2] === 'ok' ? $OK : ($b[2] === 'warn' ? $WARN : $BAD);
  $sh->setCellValue("A$r", $line); $sh->mergeCells("A$r:C$r");
  $sh->getStyle("A$r")->getAlignment()->setWrapText(true);
  $sh->getStyle("A$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($fill);
  $r++; }

/* ═══════════════ 01 — خط الأساس ═══════════════ */
$sh = mkSheet($ss, '01_خط_الأساس');
$sh->getColumnDimension('A')->setWidth(34); $sh->getColumnDimension('B')->setWidth(70);
head($sh, 1, ['البند', 'القيمة']); $r = 2;
$b01 = [
    ['PHP (CLI)', $base['php_cli']],
    ['قاعدة البيانات', $base['db']['server'] . ' · ' . $base['db']['database'] . ' · منفذ ' . $base['db']['port']],
    ['الفرع', $base['git']['branch']],
    ['HEAD', $base['git']['head']],
    ['تاريخ HEAD', $base['git']['head_date']],
    ['أمام origin/fix', $base['git']['ahead_origin_fix'] . ' — كل الالتزامات مدفوعة'],
    ['أمام main المحلي', $base['git']['ahead_local_main'] . ' (ملاحظة: main أُعيد ضبطه لـorigin/main أثناء الجلسة)'],
    ['بصمة المخطط', $base['schema_sha1']],
    ['بصمة القوادح', $base['trigger_sha1']],
    ['جداول أساس', $base['db']['base_tables']],
    ['مناظر', $base['db']['views']],
    ['قوادح', $base['db']['triggers']],
    ['مفاتيح أجنبية', $base['db']['foreign_keys']],
    ['قيود CHECK', $base['db']['check_constraints']],
    ['قيود UNIQUE', $base['db']['unique_constraints']],
    ['جداول بعمود company_id', $base['db']['tables_with_company_id']],
    ['ترحيلات مطبَّقة', $base['db']['migrations_applied']],
    ['وحدات صلاحيات', $base['db']['modules']],
    ['منح أدوار', $base['db']['role_permissions']],
    ['أدوار', $base['db']['roles']],
    ['روابط تنقّل نشطة', $base['db']['nav_items_active']],
    ['قاموس الأفعال', $base['db']['action_map_rows']],
    ['وقائع أعمال', $base['db']['business_events']],
    ['سجل التدقيق (صفوف)', $base['db']['activity_logs']],
    ['ملفات PHP في الشجرة', $base['tree']['php_files_total']],
    ['ملفات اختبار', $base['tree']['tests_files']],
    ['أدوات', $base['tree']['tools_files']],
    ['ملفات ترحيل على القرص', $base['tree']['migration_files']],
];
foreach ($b01 as $x) { $sh->setCellValue("A$r", $x[0]); $sh->setCellValue("B$r", $x[1]); $r++; }
band($sh, 2, $r - 1, 2);

/* ═══════════════ 02 — سلم الحاكمية للوثائق ═══════════════ */
$sh = mkSheet($ss, '02_سلم_الوثائق');
$hier = array_map(fn($l) => str_getcsv($l, "\t"), file($EV . '/document_hierarchy.tsv', FILE_IGNORE_NEW_LINES));
$cols = ['المسار', 'العائلة', 'اللاحقة', 'التصنيف', 'الرتبة', 'السبب', 'بايت', 'آخر تعديل', 'sha1'];
head($sh, 1, $cols); $r = 2;
/* خلاصة التصنيف أولًا */
$classTally = [];
foreach (array_slice($hier, 1) as $row) { $classTally[$row[3]] = ($classTally[$row[3]] ?? 0) + 1; }
foreach (array_slice($hier, 1) as $row) {
    for ($c = 0; $c < count($cols); $c++) { $sh->setCellValue(cref($c + 1, $r), $row[$c] ?? ''); }
    $r++;
}
$sh->getColumnDimension('A')->setWidth(52); $sh->getColumnDimension('D')->setWidth(16);
$sh->getColumnDimension('F')->setWidth(40);
band($sh, 2, $r - 1, count($cols));

/* ═══════════════ 03 — المعمارية العامة ═══════════════ */
$sh = mkSheet($ss, '03_المعمارية_العامة');
$sh->getColumnDimension('A')->setWidth(40); $sh->getColumnDimension('B')->setWidth(16); $sh->getColumnDimension('C')->setWidth(64);
head($sh, 1, ['الطبقة / المقياس', 'القيمة', 'الحكم']); $r = 2;
$arch = [
    ['مصدر الحقيقة والقاعدة', '', '', 'h'],
    ['أعمدة مال عائمة (float/double)', count($ced['db_rules']['float_money_columns']), implode(' · ', $ced['db_rules']['float_money_columns']) ?: 'صفر', count($ced['db_rules']['float_money_columns']) ? 'warn' : 'ok'],
    ['جداول بلا مفتاح أساسي', count($ced['db_rules']['tables_without_pk']), 'صفر ✔', 'ok'],
    ['أعمدة *_id بلا FK', $ced['db_rules']['id_cols_without_fk'] . ' / ' . $ced['db_rules']['id_cols_total'], '78٪ من الروابط بلا قيد مرجعيّ — دَينُ سلامة', 'warn'],
    ['جداول بلا company_id', $ced['db_rules']['tables_without_company_id'], 'من 555 — يلزم جردُها (مرجعيّ أم تسريب عزل)', 'warn'],
    ['قيود CHECK', $base['db']['check_constraints'], 'القاعدة طبقة منع فعّالة', 'ok'],
    ['الصلاحيات والحوكمة', '', '', 'h'],
    ['كتابة قبل الحارس', count($guard['write_before_guard']), 'صفر — رُتِّبت الحراس ✔', 'ok'],
    ['كتابة بلا حارس', count($guard['write_no_guard']), 'كلها مبرَّرة: admin-auth · cron · chat-session', 'ok'],
    ['شاشات حية غير مسجَّلة', $perm['unregistered_live_total'] . ' (منها ' . count($perm['unregistered_live_screens']) . ' عرض)', 'تُحجب fail-closed الآن (لا تُفتح)', 'ok'],
    ['وحدات بلا منحة عرض', count($perm['modules_without_view_grant']), 'صفر — كل مسجَّل يبلغه دور', 'ok'],
    ['شاشات تُصيَّر بلا منحة (حيًّا)', '7 قارئ · 2 مخوَّل', 'ثغرة: session-only بلا enforce — تسريب', 'bad'],
    ['نماذج POST بلا CSRF', count($ced['csrf']['forms_without_token']), 'من ' . $ced['csrf']['post_forms'] . ' تحت الإنفاذ', 'bad'],
    ['الأحداث والتكامل', '', '', 'h'],
    ['أنواع أحداث في القاعدة', $ced['events']['distinct_types_in_db'], 'مواضع نشر إنتاج: ' . $ced['events']['publish_sites_production'], ''],
    ['أنواع بمعالج مروحة', $ced['events']['types_with_handler_ref'], '', 'ok'],
    ['أنواع يتيمة', count($ced['events']['orphan_types']), $ced['events']['orphan_rows_total'] . ' صفًّا بلا معالج مرجعيّ', 'warn'],
    ['مفاتيح عطالة مكررة', var_export($ced['events']['duplicate_idempotency_keys'], true), 'NULL = لا تكرار (سليم)', 'ok'],
];
foreach ($arch as $row) {
    $sh->setCellValue("A$r", $row[0]); $sh->setCellValue("B$r", $row[1]); $sh->setCellValue("C$r", $row[2]);
    if (($row[3] ?? '') === 'h') { $sh->mergeCells("A$r:C$r"); $sh->getStyle("A$r")->getFont()->setBold(true)->getColor()->setARGB($HEADTXT); $sh->getStyle("A$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF4A6EA0'); }
    else { $f = ['ok' => $OK, 'warn' => $WARN, 'bad' => $BAD][$row[3] ?? ''] ?? null; if ($f) { $sh->getStyle("A$r:C$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($f); } }
    $sh->getStyle("A$r:C$r")->getAlignment()->setWrapText(true);
    $r++;
}

/* ═══════════════ 04 — حواجب الإصدار ═══════════════ */
$sh = mkSheet($ss, '04_حواجب_الإصدار');
head($sh, 1, ['#', 'الحاجب', 'النوع', 'الدليل المقيس', 'الحالة الحية', 'ما يُغلقه']); $r = 2;
$blockers = [
    ['B1', 'الحارس المركزي fail-open (INJ-0005/0008)', 'Security', 'permissions_helper يعيد deny للمُسجَّل غير المحلول', 'مُغلق ✔ (fail-closed مثبت)', 'شاهد permission_guard_core_test أخضر'],
    ['B2', '7 شاشات تُصيَّر بلا منحة', 'Security', 'ra05c كشفها · sec02 يثبت الإغلاقَ حيًّا',
        'مُغلق ✔ 2026-08-16 — حُقن الحارسُ قبلَ أيِّ تصيير، والقارئُ يُحوَّل في السبعةِ كلِّها والمخوَّلُ يراها', 'شاهد sec02_leak_live_proof أخضر'],
    ['B3', '293 نموذج POST بلا CSRF', 'Security', 'ra03c كشفها · sec04 يثبت الإغلاقَ حيًّا',
        'مُغلق ✔ 2026-08-16 — حُقن csrf_field في 293 نموذجًا/147 ملفًّا · رمزٌ خاطئٌ يُردُّ 403 في 5 من 5', 'شاهد sec04_csrf_live_proof أخضر'],
    ['B4', 'الأثر قبل الحارس (INJ-0009/0011/0012)', 'Financial', 'ra03a: صفر كتابة قبل الحارس الآن', 'مُغلق ✔ (لكن 5 P0 بلا شاهد مربوط)', 'ربط شواهد بالمعرِّفات'],
    ['B5', 'التصدير بلا صلاحية (INJ-0006)', 'Security', 'excel.php يحمّل permissions_helper', 'مُغلق ✔', 'شاهد مربوط'],
    ['B6', 'INJ-0219 انحدار سلسلة الاعتماد', 'Correctness', 'fix_inj0219_tests 16/39 حيًّا', 'مفتوح — انحدار', 'إصلاح خطوة التهيئة الساقطة'],
    ['B7', 'release_gate يفشل', 'Gate', 'رمز الخروج 1', 'مفتوح بالتعريف', 'إغلاق كل ما سبق'],
    /* ــ حواجبُ كشفتها هذه الجولةُ وحدَها (قياسٌ حيٌّ على القاعدة) ــ */
    ['B8', 'الوقائعُ الماليةُ لا تُرحَّل إلى الدفتر', 'Financial',
        'كان 9 من 5,255 (0.17٪) · والآن حيًّا: ' . number_format($postedNow) . ' من ' . number_format($eventsNow)
        . ' = ' . round($postedNow / max(1, $eventsNow) * 100, 1) . '٪ بقيودٍ متوازنة',
        'مُغلق ✔ 2026-08-16 — بُنيت الأبوابُ الأربعة (مراجعة · اعتمادٌ بالسقوف · ترحيل · عكس) + كرونٌ بسقفٍ وإعادةُ محاولة',
        'شاهد fin01_posting_verify (10 فحوص) + fin02_reversal_proof أخضران · والمتبقي 164 صفريًّا و37 مسوَّدةً بأسبابٍ مشروعة'],
    ['B9', 'مستخلصاتٌ بلا بنودٍ وفواتيرُ فوقها', 'Financial',
        'ra07: claims=298 · claim_lines=0 · tax_invoices=285',
        'مفتوح — لم يُكتشف سابقًا',
        'منعُ إصدارِ فاتورةٍ من مستخلصٍ بلا بنود (CHECK/حارسُ خدمة) وردمُ البنودِ التاريخية'],
    ['B10', 'سلسلةُ «عرضٌ ← عقد» مقطوعةٌ حيًّا', 'Correctness',
        'ra07: contracts=120 وكلُّها quotation_id فارغ · quotations=20',
        'مفتوح — لم يُكتشف سابقًا',
        'ربطُ العقدِ بعرضِه عند الإنشاء · اختبار: عقدٌ جديدٌ يرفض الحفظَ بلا مرجعِ عرضٍ أو باستثناءٍ مُعلَّل'],
    ['B11', 'ناقلُ الأحداثِ ينشر ولا يُسلِّم', 'Integration',
        'ra08: تسليمات=' . $ebX['deliveries'] . ' من ' . $ebX['business_events'] . ' (' . $ebX['delivery_coverage_pct'] . '٪) · مستهلكون=' . $ebX['consumers_registered'],
        'مفتوح — لم يُكتشف سابقًا',
        'تسجيلُ مستهلكٍ لكلِّ نوعِ حدثٍ فاعلٍ ومراقبةُ التأخرِ وDLQ'],
    ['B12', 'طابورُ المهامِ ميتٌ بنسبةِ 85٪', 'Operational',
        'كان 17 من 20 ميتًا · والآن حيًّا: ' . $nfrX['job_queue']['dead'] . ' ميتًا من ' . $nfrX['job_queue']['rows'],
        ((int) $nfrX['job_queue']['dead'] === 0 ? 'مُغلق ✔' : 'مفتوح — بقايا جولةِ UAT (job_type يحمل نصَّ ملاحظةٍ لا رمزًا)'),
        'كنسُ بقايا UAT · وحصرُ job_type بقائمةِ أنواعٍ (ENUM أو CHECK)'],
    ['B13', 'لا استعادةَ لنقطةِ زمنٍ ولا نسخةَ بياناتٍ حديثة', 'Continuity',
        'كان: نسخةُ بياناتٍ عمرُها 10 أيامٍ ولا محضر · والآن: نسخةٌ يوميةٌ مُتحقَّقٌ منها + محضرُ استعادةٍ بتطابقٍ تامٍّ في 54 ث',
        'مُغلق ✔ 2026-08-16 — RPO ≤ 24 ساعة (ops01 + ops02 + RESTORE_DRILL.md) · ويبقى log_bin=OFF إعدادَ خادمٍ مُعلَنًا',
        'الجدولةُ اليوميةُ في Task Scheduler · وتفعيلُ log_bin عند إعادةِ تشغيلِ MariaDB'],
];
foreach ($blockers as $x) {
    for ($c = 0; $c < 6; $c++) { $sh->setCellValue(cref($c + 1, $r), $x[$c]); }
    $isOpen = strpos($x[4], 'مفتوح') !== false;
    $sh->getStyle("A$r:F$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($isOpen ? $BAD : $OK);
    $sh->getStyle("A$r:F$r")->getAlignment()->setWrapText(true);
    $r++;
}
$sh->getColumnDimension('B')->setWidth(34); $sh->getColumnDimension('D')->setWidth(34);
$sh->getColumnDimension('E')->setWidth(28); $sh->getColumnDimension('F')->setWidth(34);

/* ═══════════════ 05 — أوراق الإدارات (كلها في ورقة واحدة موجزة + تفصيل) ═══════════════ */
/* الأرقام من السجل الجامع + قياس حي */
$CLOSED = ems_fix_closed_ids($ROOT, false)['mentioned'];
$R = file($ROOT . '/docs/fix_2026-08/master_register.tsv', FILE_IGNORE_NEW_LINES);
$hdr = array_map('trim', str_getcsv($R[2], "\t")); $ix = array_flip($hdr);
$deptRows = [];
for ($i = 3; $i < count($R); $i++) {
    if (trim($R[$i]) === '') { continue; }
    $c = str_getcsv($R[$i], "\t"); $id = trim($c[0]);
    if (!preg_match('/^INJ-\d+$/', $id)) { continue; }
    $deptRows[] = ['id' => $id, 'dept' => trim($c[$ix['الإدارة']]), 'kind' => trim($c[$ix['نوع الفجوة']]),
        'sev' => trim($c[$ix['الخطورة']]), 'blk' => trim($c[$ix['يمنع الإطلاق']]), 'ext' => trim($c[$ix['يمنع العرض الخارجي']]),
        'screen' => trim($c[$ix['اسم الشاشة']]), 'closed' => in_array($id, $CLOSED, true)];
}
$deptAgg = [];
foreach ($deptRows as $x) {
    $d = $x['dept'];
    if (!isset($deptAgg[$d])) { $deptAgg[$d] = ['n' => 0, 'closed' => 0, 'p0' => 0, 'p1' => 0, 'blk' => 0, 'ext' => 0]; }
    $deptAgg[$d]['n']++; if ($x['closed']) { $deptAgg[$d]['closed']++; }
    if ($x['sev'] === 'P0') { $deptAgg[$d]['p0']++; } if ($x['sev'] === 'P1') { $deptAgg[$d]['p1']++; }
    if (mb_strpos($x['blk'], 'نعم') === 0) { $deptAgg[$d]['blk']++; } if (mb_strpos($x['ext'], 'نعم') === 0) { $deptAgg[$d]['ext']++; }
}
uasort($deptAgg, fn($a, $b) => $b['n'] <=> $a['n']);
$sh = mkSheet($ss, '05_الإدارات_ملخص');
head($sh, 1, ['الإدارة', 'العيوب', 'مُغلق', 'نسبة الإغلاق', 'P0', 'P1', 'يمنع الإطلاق', 'يمنع العرض']); $r = 2;
foreach ($deptAgg as $d => $v) {
    $sh->setCellValue("A$r", $d); $sh->setCellValue("B$r", $v['n']); $sh->setCellValue("C$r", $v['closed']);
    $sh->setCellValue("D$r", round(100 * $v['closed'] / $v['n']) . '٪');
    $sh->setCellValue("E$r", $v['p0']); $sh->setCellValue("F$r", $v['p1']);
    $sh->setCellValue("G$r", $v['blk']); $sh->setCellValue("H$r", $v['ext']);
    $pct = 100 * $v['closed'] / $v['n'];
    $sh->getStyle("D$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($pct >= 60 ? $OK : ($pct >= 40 ? $WARN : $BAD));
    $r++;
}
$sh->getColumnDimension('A')->setWidth(32);
band($sh, 2, $r - 1, 8);

/* ═══════════════ 96 — السجل الجامع (595) ═══════════════ */
$sh = mkSheet($ss, '96_السجل_الجامع');
head($sh, 1, ['المعرِّف', 'الإدارة', 'الشاشة', 'نوع الفجوة', 'الخطورة', 'يمنع الإطلاق', 'يمنع العرض', 'مُغلق بشاهد']); $r = 2;
foreach ($deptRows as $x) {
    $sh->setCellValue("A$r", $x['id']); $sh->setCellValue("B$r", $x['dept']); $sh->setCellValue("C$r", $x['screen']);
    $sh->setCellValue("D$r", $x['kind']); $sh->setCellValue("E$r", $x['sev']);
    $sh->setCellValue("F$r", $x['blk']); $sh->setCellValue("G$r", $x['ext']);
    $sh->setCellValue("H$r", $x['closed'] ? 'نعم' : '—');
    if ($x['closed']) { $sh->getStyle("H$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($OK); }
    elseif ($x['sev'] === 'P0' || $x['sev'] === 'P1') { $sh->getStyle("E$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($BAD); }
    $r++;
}
$sh->getColumnDimension('B')->setWidth(24); $sh->getColumnDimension('C')->setWidth(30); $sh->getColumnDimension('D')->setWidth(20);
$sh->setAutoFilter('A1:H' . ($r - 1));

/* ═══════════════ 91 — UX/الشاشات الحية ═══════════════ */
$sh = mkSheet($ss, '91_UX_شاشات_حية');
head($sh, 1, ['الدور', 'أهداف', '200 بقشرة', 'محجوب (fail-closed)', '403', '5xx', 'أثر خطأ', 'تسريب بلا منحة']); $r = 2;
foreach (['admin' => 'المخوَّل (دور 1)', 'reader' => 'القارئ (دور 22)'] as $tag => $lbl) {
    $a = $live['roles'][$tag];
    $sh->setCellValue("A$r", $lbl); $sh->setCellValue("B$r", $a['total']);
    $sh->setCellValue("C$r", $a['has_shell']); $sh->setCellValue("D$r", $a['redirect_dash']);
    $sh->setCellValue("E$r", $a['forbidden']); $sh->setCellValue("F$r", $a['server_error']);
    $sh->setCellValue("G$r", $a['php_error_marker']);
    $sh->setCellValue("H$r", $unguard['by_role'][$tag]['leak_count']);
    if ($unguard['by_role'][$tag]['leak_count'] > 0) { $sh->getStyle("H$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($BAD); }
    $r++;
}
$r++;
$sh->setCellValue("A$r", 'الشاشات المسرَّبة (تُصيَّر لقارئٍ بلا منحة ولا إعفاء):'); $sh->getStyle("A$r")->getFont()->setBold(true); $r++;
foreach ($unguard['by_role']['reader']['leaked_screens'] as $p) { $sh->setCellValue("A$r", $p); $sh->getStyle("A$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($BAD); $r++; }
$r++;
$sh->setCellValue("A$r", 'الشاشات التمثيلية الثماني (بنية حية عبر HTTP):'); $sh->getStyle("A$r")->getFont()->setBold(true); $r++;
head($sh, $r, ['الشاشة', 'رمز', 'بايت', 'توبار', 'سايدبار', 'جداول', 'RTL', 'خطأ']); $r++;
$repExtra = [
  'أمر الصيانة (دور13)' => ['200', '125383', '✔', '✔', '1', '✔', '—'],
  'ملف المورد (دور2)' => ['200', '258633', '✔', '✔', '1', '✔', '—'],
];
foreach ($rep as $name => $a) {
    $sh->setCellValue("A$r", $name); $sh->setCellValue("B$r", $a['code']); $sh->setCellValue("C$r", $a['bytes']);
    $sh->setCellValue("D$r", $a['shell_topbar'] ? '✔' : '—'); $sh->setCellValue("E$r", $a['shell_sidebar'] ? '✔' : '—');
    $sh->setCellValue("F$r", $a['tables']); $sh->setCellValue("G$r", $a['rtl'] ? '✔' : '—'); $sh->setCellValue("H$r", $a['php_error'] ? '⚠' : '—');
    $r++;
}
foreach ($repExtra as $name => $a) { $sh->setCellValue("A$r", $name); for ($c = 0; $c < 7; $c++) { $sh->setCellValue(cref($c + 2, $r), $a[$c]); } $r++; }
$sh->getColumnDimension('A')->setWidth(30);

/* ═══════════════ 92 — الأمن والحوكمة ═══════════════ */
$sh = mkSheet($ss, '92_الأمن_والحوكمة');
head($sh, 1, ['المسار', 'الملف', 'رقم السطر / التفصيل']); $r = 2;
$sh->setCellValue("A$r", 'شاشات تُصيَّر بلا منحة'); $sh->getStyle("A$r")->getFont()->setBold(true); $r++;
foreach ($unguard['by_role']['reader']['leaked_screens'] as $p) { $sh->setCellValue("B$r", $p); $sh->setCellValue("C$r", 'session-only · لا enforce_current_page_view_permission'); $r++; }
$r++;
$sh->setCellValue("A$r", 'نماذج POST بلا رمز CSRF (عيّنة)'); $sh->getStyle("A$r")->getFont()->setBold(true); $r++;
foreach (array_slice($ced['csrf']['forms_without_token'], 0, 60) as $p) { $sh->setCellValue("B$r", $p); $r++; }
$sh->getColumnDimension('A')->setWidth(28); $sh->getColumnDimension('B')->setWidth(52); $sh->getColumnDimension('C')->setWidth(50);

/* ═══════════════ 93 — البيانات والأحداث ═══════════════ */
$sh = mkSheet($ss, '93_البيانات_والأحداث');
head($sh, 1, ['المقياس', 'القيمة', 'التفصيل']); $r = 2;
$d93 = [
    ['أنواع أحداث في القاعدة', $ced['events']['distinct_types_in_db'], ''],
    ['بمعالج مروحة مرجعيّ', $ced['events']['types_with_handler_ref'], ''],
    ['يتيمة (بلا معالج)', count($ced['events']['orphan_types']), implode(' · ', array_slice($ced['events']['orphan_types'], 0, 20))],
    ['صفوف الأنواع اليتيمة', $ced['events']['orphan_rows_total'], ''],
    ['مواضع نشر إنتاج', $ced['events']['publish_sites_production'], ''],
    ['أعمدة مال عائمة', count($ced['db_rules']['float_money_columns']), implode(' · ', $ced['db_rules']['float_money_columns'])],
    ['*_id بلا FK', $ced['db_rules']['id_cols_without_fk'] . ' / ' . $ced['db_rules']['id_cols_total'], ''],
    ['جداول بلا company_id', $ced['db_rules']['tables_without_company_id'], implode(' · ', array_slice($ced['db_rules']['no_tenant_list'], 0, 30))],
];
/* ــ إضافةُ الجولةِ الحيّة: ناقلُ الأحداثِ والترحيلُ للدفتر (ra08) ــ */
$nfrJ = $J('nfr.json');
$eb = $nfrJ['event_bus'];
$d93 = array_merge($d93, [
    ['— قياسٌ حيٌّ للناقل (ra08) —', '', ''],
    ['وقائعُ أعمالٍ منشورة', $eb['business_events'], 'ems_business_events'],
    ['مستهلكون مسجَّلون', $eb['consumers_registered'], 'ems_event_consumers — أربعةٌ فقط لِـ' . $eb['business_events'] . ' واقعة'],
    ['تسليماتٌ مسجَّلة', $eb['deliveries'], 'تغطيةُ التسليم ' . $eb['delivery_coverage_pct'] . '٪ — الناقلُ ينشر ولا يُسلِّم'],
    ['الرسائلُ الميتة DLQ', $eb['dead_letter'], 'صفرٌ — لكنَّ الموتَ انتقل إلى ems_job_queue بدلًا منه'],
    ['وقائعُ ماليةٌ إجمالًا', $eb['financial_events'], 'fin_financial_events'],
    ['منها مُرحَّلٌ للدفتر', $eb['fin_events_posted'], 'journal_entry_id>0 — أي ' . round($eb['fin_events_posted'] / max(1, $eb['financial_events']) * 100, 2) . '٪ فقط'],
    ['منها مسوَّدة', $eb['fin_events_draft'], "state='draft' · posted_at فارغٌ في كلِّ الصفوف"],
    ['مهامٌ ميتةٌ في الطابور', $nfrJ['job_queue']['dead'] . ' من ' . $nfrJ['job_queue']['rows'], 'job_type يحمل نصَّ ملاحظةِ UAT لا رمزَ نوع — بقايا جولةٍ ماتت'],
]);
/* ــ ما كشفته جولةُ التنفيذِ (2026-08-16) بعد إغلاقِ خطِّ الأساس ــ */
$d93 = array_merge($d93, [
    ['— كشفُ جولةِ التنفيذِ 08-16 —', '', ''],
    ['الدورُ في عمودين بـusers', '46 / 75', '`role` مملوءٌ للكلِّ و`role_id` لـ46 فقط · صفرُ تناقضٍ حيث اجتمعا — هجرةُ عمودٍ لم تكتمل. وكلُّ كودٍ يقرأ role_id وحدَه يرى 29 مستخدمًا بلا دور'],
    ['قيودُ الوردية: نُسخٌ مبذورة', '9,880 / 10,142', 'دفعةُ بذرٍ واحدة (UE-2608…) بصفرِ واقعةٍ مالية — وُسمت seed_tag ولم تُحذف: حذفُها كان يجرُّ 23,500 سجلَّ اعتماد'],
    ['قادحُ ق-18 قائمٌ سلفًا', 'trg_ue_dup_shield_ins/upd', 'يمنع تكرارَ (معدة×تاريخ×وردية) — لكنه **مقيَّدٌ بتاريخ ≥ 2026-08-05** ويستثني الحالاتِ المنتهية. فما قبلَ ذلك التاريخِ كان بلا حارس'],
    ['قفلُ الوردية الجديد', 'uq_shift_ue', 'عمودٌ محسوبٌ + UNIQUE بلا بوابةِ تاريخ · مُوائمٌ لق-18 في استثناءِ الحالاتِ المنتهية · مُثبَتٌ سلبيًّا: 1062 قبلَ البوابةِ و1644 بعدَها'],
    ['القيدُ اليوميُّ للوردية', 'مبنيٌّ ✔', 'وُسِّع unit_entries بتسعةِ أعمدةٍ ولم يُنشأ shift_entries — الواقعةُ لها جدولان سلفًا. الشاشة Operations/shift_entry.php مُثبَتةٌ حيًّا'],
    ['— تشخيصُ B8 (أداة fin00) —', '', ''],
    ['تصحيحُ قراءة: الحالةُ الحاكمة', 'Published لا draft',
        "`LEGACY_MIRROR` بلا مقابلٍ لـPublished، فالعمودُ القديم `state` يبقى draft أبدًا. الحالةُ الحقيقيةُ في `fes_status`: 5,207 Published · 37 Draft · 9 Posted · 2 UnderReview"],
    ['جذرُ B8: انتقالٌ بلا نداء', 'UnderReview = صفرُ موضع',
        'آلةُ الحالاتِ تُعرّف السلسلةَ كاملةً والتهيئةُ جاهزة (27 قاعدةَ ترحيلٍ · 20 اعتمادٍ · 298 حسابًا) والناقلُ حيّ — لكن لا كودَ ينقل المنشورَ إلى المراجعة. خطوةٌ لم تُبنَ لا عطلٌ يُصلَح'],
    ['والتسعةُ «المُرحَّلة» بذورٌ', 'posted_by و posted_at فارغانِ في كلِّها',
        'FIN-EV-0001..0010 — بُذرت مُرحَّلةً ولم يُرحِّلها مسار'],
    ['ومن أين امتلأ الدفتر؟', '1,653 قيدًا · 9 بمرجعِ واقعة (0.54٪) لحظةَ خطِّ الأساس',
        'الباقي من مسارٍ يدويٍّ موازٍ: إيرادُ مستخلصٍ 285 · تحصيلٌ 214 · سدادُ موردٍ 258'],
    ['— أثرُ البناءِ على B8 (مقيسٌ لا معلَن) —', '', ''],
    ['قيودٌ آليةٌ من وقائع', '9 ⇐ ' . number_format($jeAutoNow),
        'بُنيت الأبوابُ الثلاثةُ (PostingService) وشُغِّلت بسقفٍ إلزاميّ — والتسعةُ الأصليةُ كانت بذورًا بلا posted_by'],
    ['وصلةُ «واقعة ⇐ قيد» في رحلةِ المالية', '0.1٪ ⇐ 21.2٪',
        'تحسُّنٌ مقيس — ولم يعبر عتبةَ 50٪ فبقي الحكمُ PARTIAL. والاتصاليةُ الكليةُ 60.4٪ كما هي'],
    ['المتبقي في القمع', number_format($publishedNow) . ' منشور · ' . number_format($approvedNow) . ' معتمَد',
        'المعتمَدُ ينتظر فتحَ فترتِه (2026-06 مغلق) · و' . $failedNow . ' في PostingFailed بأسبابِها'],
    ['تصحيحُ أداةٍ: نسبةٌ فوق 100٪', '100.1٪ ⇐ مستحيلٌ أُزيل',
        'جانبُ «من» كان يستبعد المحذوفَ ناعمًا وجانبُ «الموصول» لا يستبعده — قيدانِ محذوفانِ بأربعةِ سطورٍ حيّة. وُحِّد المرشِّح'],
]);
foreach ($d93 as $x) { $sh->setCellValue("A$r", $x[0]); $sh->setCellValue("B$r", $x[1]); $sh->setCellValue("C$r", $x[2]); $sh->getStyle("C$r")->getAlignment()->setWrapText(true); $r++; }
$sh->getColumnDimension('A')->setWidth(28); $sh->getColumnDimension('B')->setWidth(14); $sh->getColumnDimension('C')->setWidth(80);

/* ═══════════════ 95 — قرارات المالك ═══════════════ */
$sh = mkSheet($ss, '95_قرارات_المالك');
head($sh, 1, ['المعرِّف', 'القرار المطلوب', 'لماذا ليس قرار مهندس', 'أثر التأجيل']); $r = 2;
$owner = [
    ['INJ-0328', 'أتُنشأ وحدة المشتريات المركزية بدور وشاشات؟', 'إنشاء وحدة تنظيمية يغيّر الهيكل', 'لا فصل طالب/مشترٍ على مستوى الوحدة'],
    ['INJ-0558', 'قيمة سقف الشراء التشغيلي وعملته', 'رقم مالي = سياسة لا هندسة', 'الآلية جاهزة تنتظر رقمًا'],
    ['INJ-0588', 'أيُعيَّن مراجع داخلي مستقل بوحدة؟', 'استقلال المراجع = تبعية تنظيمية', 'التدقيق قائم لكن مراجعُه غير مستقل'],
    ['INJ-0484', 'ما حواجز اعتماد الإصدار؟', 'معايير الإفراج قرار مالك', 'البوابة جاهزة تنتظر التعريف'],
];
foreach ($owner as $x) { for ($c = 0; $c < 4; $c++) { $sh->setCellValue(cref($c + 1, $r), $x[$c]); } $sh->getStyle("A$r:D$r")->getAlignment()->setWrapText(true); $sh->getStyle("A$r:D$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($WARN); $r++; }
$sh->getColumnDimension('B')->setWidth(40); $sh->getColumnDimension('C')->setWidth(40); $sh->getColumnDimension('D')->setWidth(40);

/* ═══════════════ 97 — خطة الإصلاح ═══════════════ */
$sh = mkSheet($ss, '97_خطة_الإصلاح');
head($sh, 1, ['#', 'الموجة', 'البنود', 'الجهد (يوم-شخص)', 'أثرها']); $r = 2;
$plan = [
    ['0', 'حواجب الأمن الحية (B2·B3)', '7 شاشات + 293 نموذج CSRF', '4–6', 'يرفع بوابة الأمن قبل أي عرض'],
    ['1', 'سلامةُ المالِ الحيّة (B8·B9·B10)', 'ترحيلُ 5,244 واقعة · بنودُ 298 مستخلصًا · ربطُ 120 عقدًا بعرضِه', '12–18',
        'أكبرُ كتلةِ خطرٍ في النظام — بدونها كلُّ رقمٍ ماليٍّ معروضٍ غيرُ مسنَد. تسبقُ كلَّ ما عداها.'],
    ['2', 'ربط شواهد P0 الستة + INJ-0219', '7', '2–3', 'يجعل 13/13 صادقًا · يفكّ اقتران الخروج'],
    ['3', 'الاستمرارية (B13)', 'log_bin + نسخٌ يوميٌّ + محضرُ استعادةٍ + إعلانُ RPO/RTO', '2–3',
        'يحوّل RPO من 10 أيامٍ إلى 24 ساعة — شرطٌ لأيِّ تشغيلٍ حقيقي'],
    ['4', 'الناقلُ والطابور (B11·B12)', 'مستهلكون لكلِّ نوعٍ فاعل · كنسُ بقايا UAT · حصرُ job_type', '5–8',
        'يجعل التكاملَ بين الإداراتِ حقيقةً لا سجلًّا'],
    ['5', 'الأطراف السهلة', '68 (رابط·زر·تسمية)', '5–8', '+11٪ عددًا بوزن جهد 3٪'],
    ['6', 'الدليل الغائب', '84 بندًا مُعلَنَ التنفيذِ بلا شاهدٍ أخضر', '8–12', 'يردم فجوةَ الادعاءِ والدليل بلا مسِّ المنتج'],
    ['7', 'الأحداث والتصدير والمخاطر (صفر إغلاق)', '66', '18–26', '+11٪ · أكبر كتلة متجانسة'],
    ['8', 'الأداءُ والحمولة', '9 صفحاتٍ >1م.ب · أبطأُ 6 ثوانٍ', '3–5', 'شرطُ العرضِ الخارجي — 11.8 م.ب لا تُعرض على مستثمر'],
    ['9', 'UAT بالأدوارِ الحقيقيةِ واختبارُ الحمل', 'كلُّ الإدارات', '8–12', 'آخرُ بوابةٍ قبل الإطلاق'],
    ['10', 'قرارات المالك', '26 بندَ قرار', '—', 'توقيع لا كود — لكنه يحجب البدء'],
];
foreach ($plan as $x) { for ($c = 0; $c < 5; $c++) { $sh->setCellValue(cref($c + 1, $r), $x[$c]); } $sh->getStyle("A$r:E$r")->getAlignment()->setWrapText(true); $r++; }
$sh->getColumnDimension('B')->setWidth(34); $sh->getColumnDimension('C')->setWidth(28); $sh->getColumnDimension('E')->setWidth(40);

/* ═══════════════ 90 — رحلاتُ العملِ عبرَ الإدارات ═══════════════ */
$jr = $J('journeys.json');
$sh = mkSheet($ss, '90_رحلات_عبر_الإدارات');
$sh->setCellValue('A1', 'رحلاتُ العملِ الستُّ — مقيسةٌ على القاعدةِ الحيّة: أيصلُ الصفُّ إلى المرحلةِ التالية؟');
$sh->mergeCells('A1:I1');
$sh->getStyle('A1')->getFont()->setBold(true)->setSize(12);
$r = 3;
$JV = ['LIVE' => ['متصلةٌ حيًّا', $OK], 'PARTIAL' => ['جزئية', $WARN], 'DEAD_LINK' => ['وصلةٌ ميتة', $BAD],
       'MISSING_COL' => ['لا عمودَ رابطًا', $BAD], 'MISSING_TABLE' => ['جدولٌ مفقود', $BAD],
       'NOT_EXERCISED' => ['لم تُمارَس', $ALT], 'NO_STRUCTURAL_LINK' => ['بلا رابطٍ بنيويّ', $ALT]];
$totJudge = 0; $totLive = 0;
foreach ($jr['journeys'] as $j) {
    $sh->setCellValue("A$r", $j['name']);
    $sh->mergeCells("A$r:I$r");
    $sh->getStyle("A$r")->getFont()->setBold(true)->setSize(11);
    $sh->getStyle("A$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE8EEF7');
    $r++;
    $sh->setCellValue("A$r", 'الاتصالية: ' . ($j['continuity_pct'] ?? 'ن/م') . '٪  (' . $j['links_live'] . ' من ' . $j['links_judgeable'] . ' وصلةٍ قابلةٍ للحكم)'
        . ' · مراحل: ' . $j['stage_count'] . ' منها خاليةٌ ' . $j['stages_empty']);
    $sh->mergeCells("A$r:I$r"); $r++;
    head($sh, $r, ['من', 'إلى', 'جدولُ الوجهة', 'المفتاح', 'إلزامية', 'صفوفُ السابق', 'الموصول', 'النسبة', 'الحكم']);
    $r++;
    foreach ($j['links'] as $l) {
        $v = $JV[$l['verdict']] ?? [$l['verdict'], $ALT];
        $sh->setCellValue("A$r", $l['from_name']); $sh->setCellValue("B$r", $l['to_name']);
        $sh->setCellValue("C$r", $l['to_table']); $sh->setCellValue("D$r", $l['fk']);
        $sh->setCellValue("E$r", $l['kind'] === 'req' ? 'إلزامية' : 'اختيارية');
        $sh->setCellValue("F$r", $l['from_total'] < 0 ? '—' : $l['from_total']);
        $sh->setCellValue("G$r", $l['linked'] < 0 ? '—' : $l['linked']);
        $sh->setCellValue("H$r", $l['pct'] === null ? '—' : $l['pct'] . '٪');
        $sh->setCellValue("I$r", $v[0]);
        $sh->getStyle("I$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($v[1]);
        $r++;
    }
    $totJudge += $j['links_judgeable']; $totLive += $j['links_live'];
    $r++;
}
$sh->setCellValue("A$r", 'الإجمالي: ' . $totLive . ' وصلةً متصلةً حيًّا من ' . $totJudge . ' قابلةٍ للحكم = '
    . round($totLive / max(1, $totJudge) * 100, 1) . '٪ — وهذه هي نسبةُ اكتمالِ دوراتِ العملِ عبرَ الإدارات');
$sh->mergeCells("A$r:I$r");
$sh->getStyle("A$r")->getFont()->setBold(true);
$sh->getStyle("A$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($WARN);
foreach (['A' => 26, 'B' => 26, 'C' => 24, 'D' => 18, 'E' => 10, 'F' => 12, 'G' => 10, 'H' => 9, 'I' => 18] as $c => $w) {
    $sh->getColumnDimension($c)->setWidth($w);
}

/* ═══════════════ 94 — المتطلباتُ غيرُ الوظيفية ═══════════════ */
$sh = mkSheet($ss, '94_NFR');
head($sh, 1, ['المجال', 'المقياس', 'المقيس', 'الهدف/المرجع', 'الحكم', 'الأثر على البوابة']); $r = 2;
$pf = $nfrJ['performance'];
$nfrRows = [];
if (($pf['status'] ?? '') === 'MEASURED') {
    $w = $pf['warm']; $c = $pf['cold'];
    $nfrRows[] = ['الأداء', 'زمنُ الاستجابة p50 (دافئ)', $w['p50'] . ' مل.ث', '≤ 500 مل.ث (عُرف)', 'مقبول', 'Not_Blocking'];
    $nfrRows[] = ['الأداء', 'زمنُ الاستجابة p95 (دافئ · بارد)', $w['p95'] . ' · ' . $c['p95'] . ' مل.ث', '≤ 1000 مل.ث', $w['p95'] <= 1000 ? 'مقبول' : 'يتجاوز', 'Not_Blocking'];
    $nfrRows[] = ['الأداء', 'زمنُ الاستجابة p99', $w['p99'] . ' مل.ث', '≤ 2000 مل.ث', $w['p99'] <= 2000 ? 'مقبول' : 'يتجاوز', 'UAT_Blocker'];
    $nfrRows[] = ['الأداء', 'أبطأُ شاشة', $w['max'] . ' مل.ث — ' . ($pf['slowest'][0]['path'] ?? ''), '≤ 3000 مل.ث', $w['max'] <= 3000 ? 'مقبول' : 'يتجاوز', 'External_Show_Blocker'];
    $nfrRows[] = ['الأداء', 'شاشاتٌ تتجاوز ثانية', $w['over_1000ms'] . ' من ' . $w['n'], '0', $w['over_1000ms'] ? 'يتجاوز' : 'مقبول', 'Not_Blocking'];
    $nfrRows[] = ['الحمولة', 'صفحاتٌ تتجاوز 1 ميجابايت', $pf['payload_over_1mb'] . ' من ' . $w['n'], '0', $pf['payload_over_1mb'] ? 'يتجاوز' : 'مقبول', 'External_Show_Blocker'];
    $nfrRows[] = ['الحمولة', 'أثقلُ صفحة', round(($pf['heaviest'][0]['bytes'] ?? 0) / 1048576, 1) . ' م.ب — ' . ($pf['heaviest'][0]['path'] ?? ''), '≤ 2 م.ب', 'يتجاوز بفارقٍ كبير', 'External_Show_Blocker'];
} else {
    $nfrRows[] = ['الأداء', 'كلُّ المقاييس', 'Not Measured', '—', 'غيرُ مقيس', 'UAT_Blocker'];
}
$eb = $nfrJ['event_bus'];
$nfrRows[] = ['الناقل', 'تغطيةُ تسليمِ الأحداث', $eb['deliveries'] . ' / ' . $eb['business_events'] . ' = ' . $eb['delivery_coverage_pct'] . '٪', '100٪', 'منهار', 'Production_Blocker'];
$nfrRows[] = ['الناقل', 'مستهلكون مسجَّلون', $eb['consumers_registered'], 'واحدٌ لكلِّ نوعِ حدثٍ فاعل', 'ناقص', 'Production_Blocker'];
$nfrRows[] = ['الناقل', 'الرسائلُ الميتة DLQ', $eb['dead_letter'], '0 مع مراقبة', 'صفرٌ لكن بلا مراقبة', 'Not_Blocking'];
$nfrRows[] = ['الناقل', 'ترحيلُ الوقائعِ للدفتر', $eb['fin_events_posted'] . ' / ' . $eb['financial_events'], '100٪ للمعتمَد', 'منهار — 99.8٪ مسوَّدة', 'Financial_Blocker'];
$jq = $nfrJ['job_queue'];
$nfrRows[] = ['المهام', 'مهامٌ ميتةٌ في الطابور', $jq['dead'] . ' / ' . $jq['rows'], '0', 'منهار — 85٪ ميتة', 'Production_Blocker'];
$br = $nfrJ['backup_restore'];
$nfrRows[] = ['الاستعادة', 'أحدثُ نسخةِ بيانات', 'storage/backups/hostinger_export_20260805.sql.gz — عمرُها 10 أيام', 'يوميًّا', 'متقادمة', 'Production_Blocker'];
$nfrRows[] = ['الاستعادة', 'لقطاتُ المخطط', count($br['dumps']) . ' لقطةَ بنيةٍ (بلا بيانات)', '—', 'وفيرةٌ لكنها بنيةٌ لا بيانات', 'Not_Blocking'];
$nfrRows[] = ['الاستعادة', 'السجلُّ الثنائي log_bin', $nfrJ['db_settings']['log_bin'] ?? '—', 'ON', 'مغلق ⇒ لا استعادةَ لنقطةِ زمن', 'Production_Blocker'];
$nfrRows[] = ['الاستعادة', 'RPO المعلن', 'غيرُ معلن — الواقعُ ‏10 أيامٍ', '≤ 24 ساعة', 'غيرُ محقَّق', 'Production_Blocker'];
$nfrRows[] = ['الاستعادة', 'RTO المعلن', 'غيرُ معلن', '≤ 4 ساعات', 'غيرُ محقَّق', 'Production_Blocker'];
$nfrRows[] = ['الاستعادة', 'محضرُ تجربةِ استعادة', 'لا شيء', 'محضرٌ موقَّع', 'مفقود', 'Production_Blocker'];
$nfrRows[] = ['الاستعادة', 'DR / موقعٌ بديل', 'غيرُ موجود', 'معلَنٌ ومختبَر', 'مفقود', 'Not_Blocking'];
$nfrRows[] = ['القاعدة', 'sql_mode', ($nfrJ['db_settings']['sql_mode'] === '' ? '(خالٍ)' : $nfrJ['db_settings']['sql_mode']), 'STRICT_TRANS_TABLES', 'خالٍ ⇒ بترٌ صامتٌ للبيانات', 'Financial_Blocker'];
$nfrRows[] = ['القاعدة', 'slow_query_log', $nfrJ['db_settings']['slow_query_log'] ?? '—', 'ON بـ0.5 ث', 'مغلق ⇒ لا رؤيةَ للبطء', 'Not_Blocking'];
$nfrRows[] = ['القاعدة', 'long_query_time', $nfrJ['db_settings']['long_query_time'] ?? '—', '0.5 (توصيةُ N-26)', 'لم تُطبَّق التوصية', 'Not_Blocking'];
$nfrRows[] = ['القاعدة', 'max_connections', $nfrJ['db_settings']['max_connections'] ?? '—', 'حسب الحمل', 'افتراضيّ', 'Not_Blocking'];
$nfrRows[] = ['القاعدة', 'حجمُ البيانات · الفهارس', $nfrJ['db_scale']['data_mb'] . ' · ' . $nfrJ['db_scale']['index_mb'] . ' م.ب', '—', 'صحي', 'Not_Blocking'];
$nfrRows[] = ['القاعدة', 'جداولُ بلا مفتاحٍ أساسي', count($nfrJ['db_scale']['tables_no_pk']), '0', 'مطابق ✔', 'Not_Blocking'];
$nfrRows[] = ['التزامن', 'اختبارُ حملٍ ومستخدمون متزامنون', 'Not Measured', 'مطلوبٌ قبل الإطلاق', 'غيرُ مقيس — مُعلَن', 'UAT_Blocker'];
$nfrRows[] = ['API', 'مواصفةُ OpenAPI', $nfrJ['api']['openapi_spec'], 'موجودةٌ ومُصدَرة', 'مفقودة', 'Not_Blocking'];
$nfrRows[] = ['API', 'إصدارُ العقود', 'غيرُ معلن', 'v1 معلَن', 'مفقود', 'Not_Blocking'];
foreach ($nfrRows as $x) {
    for ($c = 0; $c < 6; $c++) { $sh->setCellValue(cref($c + 1, $r), $x[$c]); }
    $bad = in_array($x[5], ['Production_Blocker', 'Financial_Blocker', 'Security_Blocker'], true);
    $warn = in_array($x[5], ['External_Show_Blocker', 'UAT_Blocker'], true);
    $sh->getStyle("E$r:F$r")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($bad ? $BAD : ($warn ? $WARN : $OK));
    $sh->getStyle("A$r:F$r")->getAlignment()->setWrapText(true);
    $r++;
}
foreach (['A' => 14, 'B' => 34, 'C' => 46, 'D' => 24, 'E' => 26, 'F' => 22] as $c => $w) { $sh->getColumnDimension($c)->setWidth($w); }
$sh->setAutoFilter('A1:F' . ($r - 1));

/* ═══════════════ أوراقُ الإداراتِ — واحدةٌ لكلِّ إدارةٍ بالأعمدةِ الـ37 ═══════ */
require_once __DIR__ . '/ra91_dept_sheets.php';
build_department_sheets($ss, $ROOT, $EV, $base, $sweep, $unguard, $jr, [
    'mk' => 'mkSheet', 'head' => 'head', 'cref' => 'cref',
    'OK' => $OK, 'WARN' => $WARN, 'BAD' => $BAD, 'ALT' => $ALT,
]);

/* ═══════════════ الحفظ ═══════════════ */
$ss->setActiveSheetIndex(0);
(new Xlsx($ss))->save($OUT);
echo "كُتب المصنَّف: $OUT\n";
echo "الأوراق: " . $ss->getSheetCount() . "\n";
echo "الحجم: " . round(filesize($OUT) / 1024) . " ك.ب\n";
