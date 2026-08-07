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
$withShell = 0; // U10-B8: تبنّي CM-00 — الشاشةُ تبذر محاورَها (ems_shell_axes/data-ems-ax)
$withUxShell = 0; // U11-U10: تبنّي قشرة المحتوى (ems-unified-page-shell — AS-07)
$withUxComp  = 0; // U11-U10: استعمال مكونات النواة (ems-kpi-card/EmsUI/الحالات)
foreach ($dirs as $d) {
    foreach (glob($ROOT . '/' . $d . '/*.php') as $f) {
        $src = (string) file_get_contents($f);
        if (strpos($src, 'insidebar') === false) { continue; } // شاشةٌ كاملةٌ وحدها تُعدّ
        $screens++;
        if (strpos($src, 'ems_screen_about') !== false || strpos($src, 'ما هذه الشاشة') !== false) { $withAbout++; }
        if (strpos($src, 'gov_columns') !== false || strpos($src, 'ems-gov-th') !== false) { $withGov++; }
        if (strpos($src, 'ems_shell_axes') !== false || strpos($src, 'data-ems-ax-') !== false) { $withShell++; }
        if (strpos($src, 'ems-unified-page-shell') !== false) { $withUxShell++; }
        if (strpos($src, 'ems-kpi-card') !== false || strpos($src, 'EmsUI.') !== false
            || strpos($src, 'ems-state-') !== false) { $withUxComp++; }
    }
}
/* DEF-006 (update0009): المقامُ يُعلَن ولا يُوحَّد قسرًا — هذا العدُّ يشمل كلَّ
   ملفٍّ حيٍّ في المجلداتِ التشغيلية (القانونيةُ الـ203 في NAV-09 + معالجاتٌ
   وشاشاتٌ مساندةٌ وما خارجَ الوثيقةِ كنواة التأجير) — فلا يُقارَن بأرقام NAV-09
   إلا بذكرِ المقامَين معًا. */
fwrite(STDOUT, "· الشاشات الحية (بمظلة السايدبار): {$screens}"
             . " — مقامٌ أوسعُ من قانونيات NAV-09 الـ203: يشمل المساندَ وما خارجَ الوثيقة\n");
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

/* ⑦ CM-00 الغلاف الحاكم (DEC-E · update0009): المحاور الخمسة تركيبًا لا قائمة */
$uni = file_get_contents(__DIR__ . '/../assets/js/ui-unification.js');
$cm00 = $uni !== false
    && strpos($uni, 'EmsScreenShell') !== false
    && strpos($uni, "'loading', 'data', 'empty', 'no-results', 'error'") !== false
    && strpos($uni, "'online', 'offline', 'syncing', 'sync-failed'") !== false;
if (!$cm00) { $fail++; }
fwrite(STDOUT, ($cm00 ? '✔' : '✘') . " ⑦ CM-00 حيٌّ بمحاوره الخمسة (اختبار التركيبة: tests/cm00_shell_test.html)\n");
fwrite(STDOUT, "⑧ تبنّي CM-00 (بذرُ المحاور من الخادم): {$withShell}/{$screens} (عدّادُ تبنٍّ يرتفع — القرار الحاكم ١: يُقاس أولًا)\n");
/* ⑨ U11-U10: تبنّي واجهة UXR-01 — عدّادان يرتفعان (والقشرة العالمية topbar/سايدبار/
   حامل الرسائل تشمل كلَّ الشاشات عبر inheader/insidebar فلا تُعدّ هنا؛ يُعدّ
   المتبنّي الصريح للقشرة والمكونات. لوحاتُ الأدوار الـ17 تركب المصيّرَ الواحد
   role_board.php فتُحتسب شاشةً واحدةً في المقام). البوابة: tools/uxr_visual_gate.php */
fwrite(STDOUT, "⑨ تبنّي واجهة UXR-01 — قشرةُ المحتوى (ems-unified-page-shell): {$withUxShell}/{$screens} · المكونات (EmsUI/KPI/الحالات): {$withUxComp}/{$screens}\n");
fwrite(STDOUT, "④ الترتيبُ بالدورة والوثيقة — يشهد به nav09_verify (حزامه المستقل)\n");
fwrite(STDOUT, "──────────────────────────────────────────────\n");
if ($fail === 0) { fwrite(STDOUT, "الحكم: ✔ البنيويُّ قائمٌ — والتبنّي عدّادٌ يرتفع\n"); exit(0); }
fwrite(STDOUT, "الحكم: ✘ {$fail} خرقًا بنيويًّا" . ($ENFORCE ? ' — الدمجُ ممنوع' : '') . "\n");
exit($ENFORCE ? 1 : 0);
