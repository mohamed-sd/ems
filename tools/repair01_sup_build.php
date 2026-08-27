<?php
/**
 * tools/repair01_sup_build.php — مولِّدُ شاشاتِ الدليلِ الناقصةِ بجداولِها ونماذجِها
 * ═══════════════════════════════════════════════════════════════════════════
 * **طلبُ المالك**: «إن وُجدت شاشةٌ غيرُ موجودةٍ فأنشئها **بنفسِ بياناتِ الجدولِ
 * الموجودةِ في الدليلِ المعماريّ**، واجعل لها فورم وجدولَ قاعدةِ بياناتٍ بنفسِ
 * بياناتِ جدولِ العرض» · و«**إن اكتشفتَ تكرارًا فلا تُنشئ له صفحةً جديدة**».
 *
 * ◆ **ولا يبني إلّا ما حكم عليه المُقرِّرُ بـ`BUILD`** — و`REUSE` تُعاد تسميتُها
 *   ولا تُبنى، **فالتكرارُ عطبٌ حاجبٌ أُغلق عند صفر**.
 *
 * ◆ **والقالبُ عُرفُ الشجرةِ لا اختراعٌ**: قشرةٌ ثمَّ `session_bootstrap` ثمَّ
 *   فحصُ `role_permissions` بـ`$MODULE_CODE` ثمَّ `ems_tenant_db()` ثمَّ
 *   `inheader`/`insidebar` — **كما في `Suppliers/supplier_advances.php` حرفًا**.
 *
 * ◆ **والعمودُ يُسمَّى بمعناه لا برقمِه**: قاموسٌ عربيٌّ ⇐ إنجليزيٌّ للكلماتِ
 *   المتكرّرة، **ونوعُه من اسمِه**: «تاريخ» ⇒ `DATE` · «مبلغ/نسبة» ⇒ `DECIMAL` ·
 *   `REFERENCE` ⇒ `INT` مفهرَس. ⛔ **وأعمدةٌ باسمِ `f01` تُخفي المعنى فتُمنَع.**
 *
 * ⛔ **ولا يكتب هذا المولِّدُ في القاعدةِ مباشرةً**: يُصدر **هجرةً** تُشغَّل
 *   بمستخدمِ الهجرةِ — فـ`ALTER`/`CREATE` ممنوعٌ على مستخدمِ التطبيق.
 *
 * التشغيل: php tools/repair01_sup_build.php [--only=3,4] [--apply]
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
define('EMS_CLI', true);
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/session_bootstrap.php';
require_once $ROOT . '/config.php';
while (ob_get_level()) { ob_end_clean(); }
$conn = $GLOBALS['conn']; $conn->set_charset('utf8mb4');
$APPLY = in_array('--apply', $argv, true);
$ONLY  = array();
foreach ($argv as $a) { if (strpos($a, '--only=') === 0) { $ONLY = array_map('intval', explode(',', substr($a, 7))); } }

$spec = json_decode((string) file_get_contents($ROOT . '/docs/REPAIR01_20260823/GUIDE_SPEC_02.json'), true);
$dec  = json_decode((string) file_get_contents($ROOT . '/docs/REPAIR01_20260823/SUP_DECISION.json'), true);
$verdict = array();
foreach ($dec as $d) { $verdict[$d['no']] = $d; }

/* ── قاموسُ الكلماتِ المتكرّرة: العمودُ يُسمَّى بمعناه ─────────────────────── */
$DICT = array(
  'رقم' => 'no', 'كود' => 'code', 'معرف' => 'id', 'اسم' => 'name', 'تاريخ' => 'date',
  'مورد' => 'supplier', 'موردين' => 'supplier', 'الموردين' => 'supplier', 'المورد' => 'supplier',
  'عقد' => 'contract', 'عقود' => 'contract', 'بند' => 'line', 'بنود' => 'line',
  'حالة' => 'status', 'نوع' => 'type', 'صفة' => 'role', 'دور' => 'role',
  'مبلغ' => 'amount', 'قيمة' => 'value', 'نسبة' => 'pct', 'عدد' => 'count',
  'وحدة' => 'unit', 'وحدات' => 'unit', 'معدة' => 'equipment', 'معدات' => 'equipment',
  'مستند' => 'doc', 'وثيقة' => 'doc', 'ملاحظات' => 'notes', 'ملاحظة' => 'note',
  'بداية' => 'start', 'نهاية' => 'end', 'انتهاء' => 'expiry', 'اصدار' => 'issue',
  'جهة' => 'party', 'بنك' => 'bank', 'حساب' => 'account', 'فرع' => 'branch',
  'مشروع' => 'project', 'موقع' => 'site', 'شهر' => 'month', 'سنة' => 'year',
  'فترة' => 'period', 'حصة' => 'quota', 'حصص' => 'quota', 'خانة' => 'slot', 'خانات' => 'slot',
  'تسليم' => 'handover', 'اعتماد' => 'approval', 'تقييم' => 'evaluation',
  'مخالفة' => 'violation', 'جزاء' => 'penalty', 'سلفة' => 'advance', 'خصم' => 'deduction',
  'دفع' => 'payment', 'صرف' => 'disburse', 'تسوية' => 'settlement', 'اقفال' => 'closure',
  'رصيد' => 'balance', 'التزام' => 'obligation', 'تغطية' => 'coverage', 'عجز' => 'deficit',
  'فائض' => 'surplus', 'جاهزية' => 'readiness', 'احلال' => 'replacement',
  'احتياط' => 'reserve', 'مستهدف' => 'target', 'اداء' => 'performance',
  'مرجع' => 'ref', 'قائمة' => 'list', 'ضمان' => 'guarantee', 'قاعدة' => 'rule',
  'ترحيل' => 'migration', 'خريطة' => 'map', 'تتبع' => 'trace', 'تقرير' => 'report',
  'مراجعة' => 'review', 'قبول' => 'accept', 'ملحق' => 'annex', 'تصفية' => 'liquidation',
  'اغلاق' => 'close', 'مصدر' => 'source', 'قدرة' => 'capacity', 'تكامل' => 'integration',
  'تفويض' => 'delegation', 'مفوض' => 'delegate', 'تاهيل' => 'qualification',
  'ائتمان' => 'credit', 'قانوني' => 'legal', 'تحقق' => 'verify', 'متحقق' => 'verifier',
  'لوحة' => 'plate', 'شاسي' => 'chassis', 'صنع' => 'make', 'ملكية' => 'ownership',
  'مالك' => 'owner', 'هاتف' => 'phone', 'استحقاق' => 'entitlement', 'مكون' => 'component',
  'عرض' => 'offer', 'عروض' => 'offer', 'تفاوض' => 'negotiation', 'ترشيح' => 'nomination',
  'مسؤولية' => 'responsibility', 'تكلفة' => 'cost', 'مصفوفة' => 'matrix',
  /* ⚠ **خمسةُ أسماءٍ سقطت إلى بديلٍ رقميٍّ** لأنَّ القاموسَ لم يغطِّ كلماتِها —
       **والعمودُ الرقميُّ يُخفي المعنى**. فأُكمل بما نقص لا بما تُخمَّن حاجتُه. */
  'احتياج' => 'need', 'مسؤوليات' => 'responsibility', 'مسؤولي' => 'responsibility',
  'نيابي' => 'onbehalf', 'نيابيه' => 'onbehalf', 'سلف' => 'advance', 'خصومات' => 'deduction',
  'خصوم' => 'deduction', 'قوائم' => 'list', 'قوائ' => 'list', 'مرجعيه' => 'ref', 'مرجعي' => 'ref',
  'قاموس' => 'dictionary', 'قواعد' => 'rule', 'قواع' => 'rule', 'استنتاج' => 'derivation',
  'توزيع' => 'allocation', 'تخصيص' => 'allocation', 'اعمار' => 'aging', 'نيابة' => 'onbehalf',
);
$nz = function ($v) { $v = str_replace(array('أ','إ','آ','ٱ'), 'ا', (string) $v); $v = str_replace('ة', 'ه', $v);
    $v = str_replace(array('ى'), 'ي', $v);
    $v = preg_replace('~[\x{064B}-\x{0655}\x{0670}\x{0640}]~u', '', $v);
    return preg_replace('~\s+~u', ' ', trim($v)); };
$slug = function ($ar) use ($DICT, $nz) {
    /* معرِّفٌ إنجليزيٌّ في الاسمِ نفسِه (`رقم القيد · Qual_ID`) يُقدَّم */
    if (preg_match('~([A-Za-z][A-Za-z0-9_]{2,})~', $ar, $m)) { return strtolower($m[1]); }
    $out = array();
    foreach (preg_split('~[\s—·\-–\(\)/،,]+~u', $nz($ar)) as $w) {
        $w = preg_replace('~^(وال|بال|فال|كال|ال|و)~u', '', $w);
        if ($w === '') { continue; }
        if (isset($DICT[$w])) { $out[] = $DICT[$w]; continue; }
        $w2 = preg_replace('~(ات|ون|ين|ه|ي)$~u', '', $w);
        if (isset($DICT[$w2])) { $out[] = $DICT[$w2]; }
    }
    $out = array_values(array_unique(array_filter($out)));
    return $out ? implode('_', array_slice($out, 0, 3)) : '';
};
$sqlType = function ($ar, $type) use ($nz) {
    $a = $nz($ar);
    if ($type === 'REFERENCE') { return 'INT NULL'; }
    if (mb_strpos($a, 'تاريخ') !== false) { return 'DATE NULL'; }
    if (preg_match('~مبلغ|قيمه|تكلفه|رصيد|سعر~u', $a)) { return 'DECIMAL(18,2) NULL'; }
    if (preg_match('~نسبه|معدل~u', $a)) { return 'DECIMAL(9,4) NULL'; }
    if (preg_match('~عدد|كميه|ساعات|سنه~u', $a)) { return 'INT NULL'; }
    if (preg_match('~حاله|نوع|صفه|دور~u', $a)) { return "VARCHAR(60) NOT NULL DEFAULT ''"; }
    if (preg_match('~ملاحظ|وصف|سبب~u', $a)) { return 'TEXT NULL'; }
    return "VARCHAR(160) NOT NULL DEFAULT ''";
};

/* ── ما يُبنى ─────────────────────────────────────────────────────────────── */
$build = array();
foreach ($spec as $g) {
    $no = (int) $g['no'];
    if ($ONLY && !in_array($no, $ONLY, true)) { continue; }
    if (!isset($verdict[$no]) || $verdict[$no]['verdict'] !== 'BUILD') { continue; }
    $build[] = $g;
}
printf("\n═══ مولِّدُ شاشاتِ الموردين ═══\n  يُبنى الآن: %d\n\n", count($build));

$mig = array(); $files = array();
foreach ($build as $g) {
    $base = $slug($g['title']);
    if ($base === '') { $base = 'sup_scr_' . $g['no']; }
    $file = 'sup_' . $base . '.php';
    $tbl  = 'sup_' . substr($base, 0, 40);
    /* ⛔ **ولا يتصادم اسمان**: رقمُ الشاشةِ يفصل ما تشابه */
    if (in_array($tbl, array_column($mig, 't'), true)) { $tbl .= '_' . $g['no']; $file = 'sup_' . $base . '_' . $g['no'] . '.php'; }

    /* ⚠ **فحصُ التكرارِ لم يشمل الأعمدةَ الثابتة**: حقلٌ اسمُه «معرّف …» يُترجَم
         إلى `id` فيصطدم بالمفتاح — **وجدولان من خمسةٍ وعشرين أخفقا صامتَين**
         (‏عدَّت الهجرةُ 23 من 25 ولم تقل أيَّهما). ⇒ **المحجوزةُ تُبذَر في
         `$seen` من أوّلِه**، فلا يُولَّد اسمٌ يصطدم بها. */
    $cols = array(); $formF = array(); $listF = array();
    $seen = array('id' => 1, 'company_id' => 1, 'created_at' => 1,
                  'created_by' => 1, 'updated_at' => 1);
    foreach ($g['fields'] as $i => $f) {
        if ($f['type'] === 'SYSTEM' && $i === 0) { continue; }          /* المفتاحُ id */
        if ($f['type'] === 'AUDIT') { continue; }                        /* أعمدةُ الأثرِ موحَّدة */
        $cn = $slug($f['name']);
        if ($cn === '') { $cn = 'c' . ($i + 1); }
        while (isset($seen[$cn])) { $cn .= '_' . ($i + 1); }
        $seen[$cn] = 1;
        $cols[] = array('col' => $cn, 'ar' => $f['name'], 'type' => $f['type'], 'sql' => $sqlType($f['name'], $f['type']));
        $listF[] = array($cn, $f['name']);
        if (in_array($f['type'], array('INPUT', 'REFERENCE', 'CONTROLLED'), true)) { $formF[] = array($cn, $f['name'], $f['type']); }
    }
    $mig[] = array('t' => $tbl, 'cols' => $cols, 'title' => $g['title'], 'grain' => $g['grain']);
    $files[] = array('file' => $file, 'tbl' => $tbl, 'g' => $g, 'list' => $listF, 'form' => $formF);
    printf("  %2d %-34s ⇒ %-30s %d عمودًا · %d في النموذج\n", $g['no'], mb_substr($g['title'], 0, 32),
        $file, count($cols), count($formF));
}

if (!$APPLY) { echo "\n◆ عرضٌ فقط — أضِف `--apply`\n"; exit(0); }

/* ── ① الهجرة ─────────────────────────────────────────────────────────────── */
$m = "<?php\n/**\n * هجرةُ شاشاتِ إدارةِ الموردين المنصوصةِ في الدليلِ المعماريّ\n"
   . " * ═══════════════════════════════════════════════════════════════════════════\n"
   . " * **مولَّدةٌ من الدليل**: `php tools/repair01_sup_build.php --apply`\n"
   . " * ◆ ولكلِّ عمودٍ **تعليقٌ باسمِه العربيِّ المنصوص** — فالمعنى يسكن الجدولَ\n"
   . " *   لا وثيقةً تتفرّق عنه.\n"
   . " * ⛔ ولا يُنشأ جدولٌ موجود — `CREATE TABLE IF NOT EXISTS`.\n"
   . " */\nif (php_sapi_name() !== 'cli') { exit(\"CLI فقط\\n\"); }\n"
   . "error_reporting(E_ALL & ~E_DEPRECATED);\nmb_internal_encoding('UTF-8');\n"
   . "\$ROOT = dirname(dirname(__DIR__));\nrequire_once \$ROOT . '/includes/env.php';\n"
   . "\$host = ems_env('DB_HOST'); \$port = 3306;\n"
   . "if (strpos(\$host, ':') !== false) { list(\$host, \$port) = explode(':', \$host); \$port = (int) \$port; }\n"
   . "mysqli_report(MYSQLI_REPORT_OFF);\n"
   . "\$u = ems_env('DB_MIGRATOR_USER') ?: ems_env('DB_USER');\n"
   . "\$p = ems_env('DB_MIGRATOR_USER') ? ems_env('DB_MIGRATOR_PASS') : ems_env('DB_PASS');\n"
   . "\$conn = new mysqli(\$host, \$u, \$p, ems_env('DB_NAME'), \$port);\n"
   . "if (\$conn->connect_errno) { exit(\"تعذّر الاتصال: {\$conn->connect_error}\\n\"); }\n"
   . "\$conn->set_charset('utf8mb4');\n\$n = 0;\n";
foreach ($mig as $x) {
    $c = array("  `id` INT AUTO_INCREMENT PRIMARY KEY",
               "  `company_id` INT NOT NULL DEFAULT 0 COMMENT 'بوابة المستأجر'");
    foreach ($x['cols'] as $cl) {
        $c[] = '  `' . $cl['col'] . '` ' . $cl['sql'] . " COMMENT '" . str_replace("'", '', $cl['ar']) . "'";
    }
    $c[] = "  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP";
    $c[] = "  `created_by` INT NOT NULL DEFAULT 0";
    $c[] = "  `updated_at` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP";
    $c[] = "  KEY `ix_" . substr($x['t'], 0, 24) . "_co` (`company_id`)";
    $m .= "\n/* " . str_replace('*/', '', $x['title']) . " — حبّة: " . str_replace('*/', '', $x['grain']) . " */\n";
    $m .= "\$n += \$conn->query(\"CREATE TABLE IF NOT EXISTS `{$x['t']}` (\n" . implode(",\n", $c)
        . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci\") ? 1 : 0;\n";
}
$m .= "\nprintf(\"✔ جداولُ أُنشئت أو كانت قائمة: %d من " . count($mig) . "\\n\", \$n);\n";
$mp = $ROOT . '/database/migrations/2027_12_07_repair01_sup_guide_tables.php';
file_put_contents($mp, $m);
printf("\n✔ كُتبت الهجرة: %s\n", basename($mp));
printf("◆ شغّلها: php database/migrations/%s\n", basename($mp));
file_put_contents($ROOT . '/docs/REPAIR01_20260823/SUP_BUILD_PLAN.json',
    json_encode($files, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "✔ كُتبت خطّةُ الشاشات: SUP_BUILD_PLAN.json\n";
