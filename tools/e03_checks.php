<?php
/**
 * tools/e03_checks.php — حزام محرّك الشاشات (E-03) · مقياسُ تبنٍّ لا وصف
 * ───────────────────────────────────────────────────────────────────────────
 * «الحالة القائمة مقيسةً لا موصوفة — الأرقام قبل الرأي» (NAV-029):
 *  ① سؤال الشاشة (UX-01 · AC-E03-01): عدُّ الشاشات الحاملة سطرَ «ما هذه
 *     الشاشة؟» (ems_screen_about) من الحية — تبنٍّ يُقاس ويرتفع.
 *  ② طقم الحالات السبعة (AC-E03-04): وجودُ المصيّر ومرادفاته وكتله الأربع
 *     في أصول الواجهة — فحصٌ بنيويٌّ حاكم.
 *  ③ الأعمدة السبعة الحاكمة (AC-E03-03): تبنّي gov_columns في الشاشات.
 *  ④ الترتيب بالدورة (AC-E03-02): يشهد به حزام nav09_verify (لا يُكرَّر هنا).
 *  ⑤ زر الإبلاغ من كل شاشة (UX-08): report_button في القالب المشترك.
 * الحاكمُ ②و⑤ (بنيويان)؛ والعدّادات تبليغيةٌ ترتفع دفعةً دفعة.
 */
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
$ENFORCE = in_array('--enforce', $argv, true);
$fail = 0;

fwrite(STDOUT, "════ فحوص E-03 — محرّك الشاشات ════\n");

/* عدّ الشاشات الحية (المجلدات التشغيلية) */
$dirs = array('Approvals','Contracts','Employees','Equipments','Finance','FinRequests','Financing',
              'Fleet','Governance','Maintenance','Movement','Operations','Portal','Procurement',
              'Settings','Suppliers','Tickets','Timesheet','Transport','Warehouse','Workforce','main');
$screens = 0; $withAbout = 0; $withGov = 0; $withKit = 0;
foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = (string) file_get_contents($f);
        if (strpos($src, 'insidebar') === false) { continue; } // شاشةٌ كاملةٌ وحدها تُعدّ
        $screens++;
        if (strpos($src, 'ems_screen_about') !== false || strpos($src, 'ما هذه الشاشة') !== false) { $withAbout++; }
        if (strpos($src, 'gov_columns') !== false || strpos($src, 'ems-gov-th') !== false) { $withGov++; }
    }
}
fwrite(STDOUT, "· الشاشات الحية (بمظلة السايدبار): {$screens}\n");
fwrite(STDOUT, "① سؤالُ الشاشة مكتوبٌ: {$withAbout}/{$screens} (تبنٍّ يُقاس — AC-E03-01)\n");
fwrite(STDOUT, "③ الأعمدةُ السبعُ الحاكمة (gov_columns): {$withGov}/{$screens} (تبليغ — وcmp03_gov_check يفصّل)\n");

/* ② الطقم السباعي — بنيوي حاكم */
$ui = (string) @file_get_contents($ROOT . '/assets/js/ui-unification.js');
$css = (string) @file_get_contents($ROOT . '/assets/css/ems.main.all.style.css');
$kitOk = (strpos($ui, 'mapStatusToken') !== false)
      && (strpos($ui, 'reversed') !== false)
      && (strpos($css, 'locked') !== false);
if (!$kitOk) { $fail++; }
fwrite(STDOUT, ($kitOk ? '✔' : '✘') . " ② طقمُ الحالات السبعة قائمٌ بنيويًّا (المصيّر + الكتل)\n");

/* ⑤ زرُّ الإبلاغ من كل شاشة — في القالب المشترك (يصل السياق آليًّا) */
$rb = is_file($ROOT . '/includes/report_button.php');
$rbWired = false;
foreach (array('inheader.php', 'insidebar.php', 'includes/page_header.php') as $t) {
    $s = (string) @file_get_contents($ROOT . '/' . $t);
    if (strpos($s, 'report_button') !== false || strpos($s, 'أبلغ عن مشكلة') !== false) { $rbWired = true; break; }
}
$ok5 = $rb && $rbWired;
if (!$ok5) { $fail++; }
fwrite(STDOUT, ($ok5 ? '✔' : '✘') . " ⑤ زرُّ الإبلاغ بسياقه في القالب المشترك (UX-08)\n");

/* ⑥ الحالات الخمس المركزية (موجة ٤): تحميل · فارغة · خطأ · نجاح · دون اتصال */
$fsOk = (strpos($ui, 'bootFiveStates') !== false)
     && (strpos($ui, 'emsFsOffline') !== false)
     && (strpos($ui, 'emsFsLoading') !== false)
     && (strpos($ui, 'ajaxError') !== false)
     && is_file($ROOT . '/assets/i18n/datatables/ar.json'); // «فارغة» المعرَّبة
if (!$fsOk) { $fail++; }
fwrite(STDOUT, ($fsOk ? '✔' : '✘') . " ⑥ الحالاتُ الخمسُ مركزيةٌ في طبقة التوحيد (تحميل·فارغة·خطأ·نجاح·اتصال)\n");

fwrite(STDOUT, "④ الترتيبُ بالدورة والوثيقة — يشهد به nav09_verify (حزامه المستقل)\n");
fwrite(STDOUT, "──────────────────────────────────────────────\n");
if ($fail === 0) { fwrite(STDOUT, "الحكم: ✔ البنيويُّ قائمٌ — والتبنّي عدّادٌ يرتفع\n"); exit(0); }
fwrite(STDOUT, "الحكم: ✘ {$fail} خرقًا بنيويًّا" . ($ENFORCE ? ' — الدمجُ ممنوع' : '') . "\n");
exit($ENFORCE ? 1 : 0);
