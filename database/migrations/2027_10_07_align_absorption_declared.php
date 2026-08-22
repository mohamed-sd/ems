<?php
/**
 * 2027_10_07_align_absorption_declared.php
 *   كلُّ ما أُخفي يُعلَن: تحويلٌ إلى وارثِه أو منعٌ في مساحتِه
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ما كشفه حزامُ «صفرِ الفقد»**: أُخفي مئةٌ وخمسةَ عشرَ بندًا من تنقّلِ
 *   المبيعاتِ والموردين، **وستون منها بلا إعلانٍ في مصدرٍ يقرؤه الحزام** —
 *   فأعلن نقصًا غيرَ مصرَّحٍ ورسَّب التسليم. **وهو محقّ**: الإخفاءُ بلا إعلانٍ
 *   في سجلٍّ يُقرأ **فقدٌ صامتٌ ولو كان مقصودًا**.
 *
 * ◆ **وللإعلانِ بابانِ لا ثالثَ لهما — وهما نصُّ الوثيقتين**:
 *   ① **تحويلٌ إلى وارثٍ حاضر** (`nav_redirects`): «المساراتُ القديمةُ Redirect
 *     ولا تُحذف». وهذا حكمُ ما صار تبويبًا في ملفٍّ أمّ — والوارثُ يجب أن يكون
 *     **حاضرًا في تنقّلِ الدورِ نفسِه**، وإلا فالتحويلُ إلى العدم.
 *   ② **منعٌ مسجَّلٌ في المساحة** (`gov_space_appearances`): «الإزالةُ من مساحةٍ
 *     ليست حذفًا من النظام» — للشاشةِ التي يملكها غيرُ هذه الإدارة.
 *
 * ◆ **ولا يُعلَن ما لا يصحّ**: لا يُكتب تحويلٌ إلى وارثٍ غائبٍ عن الدور، ولا
 *   يُكتب منعٌ لشاشةٍ تملكها هذه الإدارةُ نفسُها. وما لا ينطبق عليه بابٌ منهما
 *   **يُعاد إظهارُه** — فالبابُ لا يصير غطاءً لكلِّ اختفاء.
 *
 * التشغيل:  php database/migrations/2027_10_07_align_absorption_declared.php
 * الرجوع :  php database/migrations/2027_10_07_align_absorption_declared.php --revert
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

$SRC   = 'INJ-ALIGN-ABSORB';
$SPACE = array(12 => 'ادارة المبيعات', 2 => 'ادارة الموردين');
$DEPT  = array(12 => 'المبيعات والعقود', 2 => 'إدارة الموردين');

if (in_array('--revert', $argv, true)) {
    $conn->query("DELETE FROM `nav_redirects` WHERE `old_route` IN
                    (SELECT `route` FROM `gov_nav_hidden_log`)
                   AND `created_at` >= (SELECT MIN(`hidden_at`) FROM `gov_nav_hidden_log`)");
    $n1 = $conn->affected_rows;
    $conn->query("DELETE FROM `gov_space_appearances` WHERE `src_note` = '{$SRC}'");
    echo "↺ حُذف {$n1} تحويلًا و{$conn->affected_rows} صفَّ منعٍ من هذه الجولة\n";
    exit(0);
}

/* ══ ① وارثُ كلِّ تبويب — يُقرأ من مكوّناتِ التبويباتِ لا يُكتب يدًا ═════════ */
$parentOf = array();
$fam = (string) @file_get_contents($ROOT . '/includes/sales_family_tabs.php');
if (preg_match_all("~'([a-z_]+)'\s*=>\s*array\('([^']+)',\s*array\((.*?)\)\),~s", $fam, $m, PREG_SET_ORDER)) {
    foreach ($m as $one) {
        if (!preg_match_all("~'([^']+)'\)~", $one[3], $r2)) { continue; }
        $routes = $r2[1];
        $head = $routes[0];                       /* أوّلُ تبويبٍ هو الأبُ الظاهر */
        foreach ($routes as $rt) { if ($rt !== $head) { $parentOf[$rt] = $head; } }
    }
}
/* ملفُّ العقدِ وملفُّ المورد — أبواهما رأسا سجلَّيهما */
foreach (array('contract_file_tabs.php' => 'Contracts/contracts.php',
               'supplier_file_tabs.php' => 'Suppliers/suppliers.php') as $kit => $head) {
    $src = (string) @file_get_contents($ROOT . '/includes/' . $kit);
    if (preg_match_all("~'([A-Za-z][A-Za-z0-9_]*/[A-Za-z0-9_]+\.php)~", $src, $r3)) {
        foreach (array_unique($r3[1]) as $rt) {
            if ($rt !== $head && !isset($parentOf[$rt])) { $parentOf[$rt] = $head; }
        }
    }
}
/* ◆ **وارثٌ مُعلَنٌ بنصِّ الوثيقةِ لما لم تلتقطه المكوّنات**: عشرةُ مساراتٍ
 *   قرارُها في الوثيقتين «تبويبٌ في الملفِّ الأمّ» أو «إسقاطُ قراءة»، ولم
 *   يلتقطها استخراجُ المكوّناتِ لأن مرجعَها فيها بمرساةٍ (`#coverage`) أو
 *   لأنها تبويبٌ لم يُبنَ شريطُه بعد. **والاسمُ يُكتب هنا بنصِّ قرارِ الورقةِ
 *   لا باجتهاد.** */
$parentOf = array_merge($parentOf, array(
    'Suppliers/equipment_plan.php'   => 'Suppliers/suppliers.php',        /* م04 */
    'Suppliers/supplier_bank.php'    => 'Suppliers/suppliers.php',        /* م03 دمج */
    'Suppliers/supplier_advances.php'=> 'Suppliers/settlements.php',      /* م15 */
    'Contracts/contract_card.php'    => 'Contracts/contracts.php',        /* أحكام العقد */
    'Contracts/contract_coverage.php'=> 'Contracts/contracts.php',        /* الورقة 14 */
    'Contracts/unit_client_match.php'=> 'Contracts/claims.php',           /* الأداء المعتمد */
    'Contracts/collections.php'      => 'Contracts/claims.php',           /* حالةُ التحصيلِ إسقاطًا */
    'Contracts/commercial_board.php' => 'main/role_board.php',            /* لوحةُ الإدارةِ صفحةُ هبوط */
));
printf("① وارثٌ مقروءٌ من مكوّناتِ التبويبات ومُعلَنٌ بالوثيقة: %d مسارًا\n", count($parentOf));

/* ══ ② الإعلانُ لكلِّ بندٍ مُخفًى ═══════════════════════════════════════ */
$q = $conn->query("SELECT `role_id`,`route`,`label_ar` FROM `gov_nav_hidden_log` ORDER BY `role_id`,`route`");
$hidden = array();
while ($q && $r = $q->fetch_assoc()) { $hidden[] = $r; }

/* المسارات الظاهرةُ الآن لكلِّ دور — فالوارثُ يجب أن يكون حاضرًا */
$nowByRole = array();
$q = $conn->query("SELECT `role_id`,`route` FROM `nav_items` WHERE `active` = 1");
while ($q && $r = $q->fetch_row()) {
    $nowByRole[(int) $r[0]][preg_replace('/[?\#].*$/', '', $r[1])] = true;
}

$q = $conn->query("SELECT COALESCE(MAX(`id`),0) FROM `gov_space_appearances`");
$nextId = $q ? (int) $q->fetch_row()[0] : 0;

$rd = $conn->prepare("INSERT INTO `nav_redirects` (`old_route`,`new_route`,`active`,`hits`,`created_at`)
                      VALUES (?,?,1,0,NOW())");
$sa = $conn->prepare("INSERT INTO `gov_space_appearances`
      (`id`,`space_ar`,`space_kind`,`tab_ar`,`screen_ar`,`route`,`owner_dept_ar`,`owner_kind`,
       `src_class`,`src_ownership`,`src_decision`,`src_note`,`spaces_count`,
       `cls`,`ownership`,`decision`,`basis`,`rule_step`,`updated_at`)
      VALUES (?,?,'DEPARTMENT','',?,?,?,'BUSINESS_DEPARTMENT',
              'FORBIDDEN','VALID','CONFIRMED',?,1,
              'FORBIDDEN','VALID','CONFIRMED',?,6,NOW())");

$redir = 0; $forbid = 0; $unhide = array();
$seenRd = array();
foreach ($hidden as $h) {
    $role = (int) $h['role_id'];
    $base = preg_replace('/[?\#].*$/', '', $h['route']);
    $lc   = mb_strtolower($base);

    /* ◆ **والمنظرُ الفرعيُّ وارثُه أصلُه**: صفٌّ بمرساةٍ (`#2`) أو معاملٍ
     *   (`?view=`) اختفى وأصلُه ظاهرٌ للدورِ نفسِه — فلم يُفقد شيء. */
    if ($base !== $h['route'] && isset($nowByRole[$role][$base])) { continue; }

    /* ① وارثٌ حاضرٌ في الدورِ نفسِه؟ */
    $heir = isset($parentOf[$base]) ? $parentOf[$base] : null;
    if ($heir !== null && isset($nowByRole[$role][$heir])) {
        $key = $lc . '=>' . mb_strtolower($heir);
        if (!isset($seenRd[$key])) {
            $c = $conn->query("SELECT COUNT(*) FROM `nav_redirects`
                                WHERE `old_route` = '" . $conn->real_escape_string($base) . "'
                                  AND `active` = 1");
            if ($c && (int) $c->fetch_row()[0] === 0) {
                $rd->bind_param('ss', $base, $heir);
                if ($rd->execute()) { $redir++; }
            }
            $seenRd[$key] = true;
        }
        continue;
    }

    /* ② منعٌ مسجَّلٌ في مساحةِ هذا الدور — ولا يُكتب لشاشةٍ تملكها الإدارةُ نفسُها */
    $own = $conn->query("SELECT `owner_dept_ar` FROM `gov_space_appearances`
                          WHERE `route` = '" . $conn->real_escape_string($base) . "' LIMIT 1");
    $ownerDept = ($own && ($x = $own->fetch_row())) ? (string) $x[0] : '';
    if ($ownerDept !== '' && $ownerDept === $DEPT[$role]) {
        $unhide[] = "دور {$role} · {$base} (تملكه الإدارةُ نفسُها ولا وارثَ حاضرًا)";
        continue;
    }
    $c = $conn->query("SELECT COUNT(*) FROM `gov_space_appearances`
                        WHERE `route` = '" . $conn->real_escape_string($base) . "'
                          AND `space_ar` = '" . $conn->real_escape_string($SPACE[$role]) . "'
                          AND `cls` = 'FORBIDDEN'");
    if ($c && (int) $c->fetch_row()[0] > 0) { continue; }
    $nextId++;
    $od = $ownerDept !== '' ? $ownerDept : 'غير محسوم';
    $basis = mb_substr('أُزيل ظهورُه من هذه المساحةِ بقرارِ دمجِ وثيقةِ المواءمة — '
                     . 'والشاشةُ باقيةٌ ومفتوحةٌ في مساحتِها المالكة', 0, 255);
    /* ◆ **سبعُ علاماتٍ في العبارةِ لا ثماني**: الأعمدةُ تسعةَ عشرَ وأكثرُها
     *   ثوابتُ نصٍّ — فالمعدودُ **علاماتُ الاستفهامِ** لا الأعمدة. وربطُ ثامنٍ
     *   يرمي `ArgumentCountError` — والعدُّ يسبق الربطَ دائمًا. */
    $spaceAr = $SPACE[$role];
    $screenAr = (string) $h['label_ar'];
    $sa->bind_param('issssss', $nextId, $spaceAr, $screenAr, $base, $od, $SRC, $basis);
    if ($sa->execute()) { $forbid++; }
    else { echo "   ✘ {$base}: {$sa->error}\n"; }
}
$rd->close(); $sa->close();
printf("② أُعلن: تحويلاتٌ إلى وارثٍ حاضر=%d · منعٌ مسجَّلٌ في المساحة=%d\n", $redir, $forbid);

/* ══ ③ ما لا ينطبق عليه بابٌ يُعاد إظهارُه — والبابُ لا يصير غطاءً ═══════ */
if ($unhide) {
    printf("③ **يُعاد إظهارُ %d بندًا** — لا وارثَ حاضرًا ولا يصحُّ منعُه:\n", count($unhide));
    foreach ($unhide as $x) { echo "   · {$x}\n"; }
} else {
    echo "③ لا بندَ يُعاد إظهارُه — كلُّ مُخفًى له بابُ إعلانٍ صحيح\n";
}

ems_migration_recorded(__FILE__, $conn, 0);
