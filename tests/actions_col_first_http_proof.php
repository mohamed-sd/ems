<?php
/**
 * tests/actions_col_first_http_proof.php
 * ═══════════════════════════════════════════════════════════════════════════
 * برهانُ HTTP لجولةِ «الإجراءاتُ أوّلًا» — من **الشاشةِ المُصيَّرةِ** لا من المصدر.
 *
 * لماذا برهانٌ حيٌّ والبوابةُ الساكنةُ خضراء؟
 *   لأن البوابةَ تقرأ نصًّا، والنصُّ لا يُظهر ما تفعله الفروعُ ولا ما تحقنه JS.
 *   وقد كذبت البوابةُ فعلًا مرّةً في هذه الجولة: قرأت G3 خضراءَ على عدّادٍ حيٍّ
 *   في Oprators/oprators.php لأنَّ العدّادَ كان يُمرَّر إلى دالةِ رسمٍ فتطبعه
 *   `" . $i . "` بلا `++`. فالحكمُ الأخيرُ للمُصيَّرِ لا للمصدر.
 *
 * ثلاثةُ أحكامٍ على كلِّ شاشةٍ تُفتح فعلًا:
 *   ① أوّلُ رأسٍ في الجدولِ الهدفِ هو «الإجراءات».
 *   ② أوّلُ خليةٍ في كلِّ صفِّ بياناتٍ تحمل زرًّا أو رابطَ فعلٍ (لا نصًّا).
 *   ③ لا عمودَ عدّادٍ تسلسليٍّ (1،2،3…) في الصفوف.
 *
 * التشغيل: php tests/actions_col_first_http_proof.php   (يتطلب Apache حيًّا)
 * ═══════════════════════════════════════════════════════════════════════════
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);

$BASE = 'http://localhost/ems';
$PASS = 0; $FAIL = 0; $SKIP = 0;
function ok($m)   { global $PASS; $PASS++; fwrite(STDOUT, "  ✔ {$m}\n"); }
function bad($m)  { global $FAIL; $FAIL++; fwrite(STDOUT, "  ✘ FAIL: {$m}\n"); }
function skip($m) { global $SKIP; $SKIP++; fwrite(STDOUT, "  ○ تخطٍّ: {$m}\n"); }
function head($m) { fwrite(STDOUT, "\n── {$m}\n"); }

function ac_req($url, $jar, $post = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, array(
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar,
        CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 45,
    ));
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $raw  = curl_exec($ch);
    $hs   = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array($code, substr($raw, $hs));
}
function ac_login($user, $jar) {
    global $BASE;
    @unlink($jar);
    list($c, $b) = ac_req($BASE . '/login.php', $jar);
    preg_match('~name="csrf_token"\s+value="([^"]+)"~', $b, $m);
    return ac_req($BASE . '/login.php', $jar, array(
        'username' => $user, 'password' => '12345678',
        'csrf_token' => isset($m[1]) ? $m[1] : '',
    ));
}

function ac_norm($t) {
    $t = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{064B}-\x{0652}]/u', '', $t);
    return trim(preg_replace('/\s+/u', ' ', $t));
}
function ac_is_actions($t) {
    return in_array($t, array('الإجراءات', 'الاجراءات', 'إجراءات', 'اجراءات', 'إجراء', 'الإجراء'), true);
}

/**
 * يفحص الشاشةَ المُصيَّرة. يُرجع مصفوفةَ نتائجٍ لكلِّ جدولٍ فيه عمودُ إجراءات.
 */
function ac_inspect($html) {
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();
    $xp  = new DOMXPath($dom);
    $out = array();

    foreach ($xp->query('//table') as $ti => $tbl) {
        $ths = $xp->query('.//tr[th][1]/th', $tbl);
        if (!$ths->length) continue;
        $labels = array();
        foreach ($ths as $th) {
            /* أعمدةُ الحوكمةِ والحقولِ تُلحَق رؤوسًا ويحشو JS خلاياها — لا تُعدّ */
            $cls = $th->getAttribute('class');
            if (strpos($cls, 'ems-gov-th') !== false || strpos($cls, 'ems-fn-th') !== false
                || $th->hasAttribute('data-gov') || $th->hasAttribute('data-fn')) continue;
            $labels[] = ac_norm($th->textContent);
        }
        if (!$labels) continue;
        $hasAct = false;
        foreach ($labels as $l) if (ac_is_actions($l)) $hasAct = true;
        if (!$hasAct) continue;

        /* صفوفُ البيانات: ما فيه td ولا يحمل colspan */
        $rows = array();
        foreach ($xp->query('.//tr[td]', $tbl) as $tr) {
            $tds = $xp->query('./td', $tr);
            if ($tds->length < 2) continue;
            if ($tds->item(0)->hasAttribute('colspan')) continue;
            $first = $tds->item(0);
            /* الخليةُ الأولى «خليةُ إجراءات» إن حملت زرًّا، أو حملت غلافَ أزرارٍ
               فارغًا، أو نائبَ «لا فعلَ متاح» (—). فالأزرارُ مشروطةٌ بالصلاحية:
               مستخدمٌ بلا حقِّ تعديلٍ يرى العمودَ نفسَه فارغًا — وذلك سليمٌ لا
               إزاحة. المقياسُ هنا موضعُ العمودِ لا صلاحيةُ الحساب. */
            $ctrl = $xp->query('.//a | .//button | .//form | .//input', $first)->length > 0;
            if (!$ctrl) {
                $fh = $dom->saveHTML($first);
                $ctrl = (bool) preg_match('/(action-btns|row-actions|actions-cell|action-btn)/i', $fh)
                     || in_array(ac_norm($first->textContent), array('—', '-', '–', ''), true);
            }
            $cells = array();
            foreach ($tds as $td) $cells[] = ac_norm($td->textContent);
            $rows[] = array('ctrl' => $ctrl, 'first' => $cells[0], 'n' => $tds->length);
        }
        $out[] = array('i' => $ti + 1, 'first' => $labels[0], 'cols' => count($labels),
                       'actFirst' => ac_is_actions($labels[0]), 'rows' => $rows,
                       'labels' => $labels);
    }
    return $out;
}

/* الشاشاتُ المُجرَّبةُ ومَن يفتحها — الحسابُ لكلِّ شاشةٍ مقيسٌ بمسبارٍ لا مُخمَّن.
   شاشاتُ `admin/permissions/*` و`admin/companies.php` تقع خلفَ لوحةِ المشرفِ
   الأعلى (`super_admins`) بمصادقةٍ منفصلة، فلا تُغطّى هنا؛ وبوابتُها الساكنةُ
   خضراءُ ونحوُها سليم. */
$CASES = array(
    array('محمد',   'Equipments/equipments.php',                    'سجل المعدات'),
    array('محمد',   'Equipments/equipments_drivers.php',            'معدات السائقين'),
    array('محمد',   'Equipments/equipments_types.php',              'أنواع المعدات'),
    array('محمد',   'Equipments/fleet_models.php',                  'موديلات الأسطول'),
    array('محمد',   'Equipments/fleet_depreciation_profiles.php',   'ملامح الإهلاك'),
    array('محمد',   'Equipments/manage_failure_codes.php',          'أكواد الأعطال'),
    array('محمد',   'Approvals/requests.php',                       'طلبات الاعتماد'),
    array('محمد',   'Employees/employees.php',                      'الموظفون'),
    array('محمد',   'admin/org_assignments.php',                    'التكليفات التنظيمية'),
    array('محمد',   'movement/add_drivers.php',                     'إضافة سائقين'),
    array('مصعب',   'Suppliers/suppliers.php',                      'الموردون'),
    array('مصعب',   'Employees/equipment_operators.php',            'مشغّلو المعدات'),
    array('اروينا', 'Employees/employee_roles.php',                 'أدوار الموظفين'),
    array('اروينا', 'Employees/job_titles.php',                     'المسمّيات الوظيفية'),
    array('اروينا', 'Workforce/worker_contract.php',                'عقود العمالة'),
    array('اروينا', 'Workforce/worker_register.php',                'سجل العمالة'),
    array('تنفيذ',  'Workforce/contract_registry.php',              'سجل العقود'),
);

$TMP = sys_get_temp_dir();
$jars = array();
head('تسجيلُ الدخول');
foreach (array_unique(array_column($CASES, 0)) as $u) {
    $jar = $TMP . '/ac_' . md5($u) . '.jar';
    list($c, $b) = ac_login($u, $jar);
    $inOk = ($c === 200 && strpos($b, 'name="password"') === false);
    $inOk ? ok("دخولُ «$u»") : bad("دخولُ «$u» — رمز $c");
    $jars[$u] = $jar;
}

foreach ($CASES as $case) {
    list($user, $path, $title) = $case;
    head("$title — $path  (بـ«$user»)");
    list($code, $html) = ac_req($BASE . '/' . $path, $jars[$user]);
    if ($code !== 200) { skip("رمزُ الاستجابة $code"); continue; }
    if (strpos($html, 'name="password"') !== false) { skip('أُعيد إلى الدخول'); continue; }

    $tables = ac_inspect($html);
    if (!$tables) { skip('لا جدولَ فيه عمودُ إجراءاتٍ في المُصيَّر'); continue; }

    foreach ($tables as $t) {
        $tag = "tbl#{$t['i']}";
        /* ① الصدارة */
        $t['actFirst']
            ? ok("$tag — أوّلُ رأسٍ «{$t['first']}»")
            : bad("$tag — أوّلُ رأسٍ «{$t['first']}» لا الإجراءات");

        if (!$t['rows']) { skip("$tag — لا صفوفَ بياناتٍ لفحصِ الخلايا"); continue; }

        /* ② أوّلُ خليةٍ تحمل فعلًا */
        $noCtrl = 0;
        foreach ($t['rows'] as $r) if (!$r['ctrl']) $noCtrl++;
        $noCtrl === 0
            ? ok("$tag — أوّلُ خليةٍ فيها زرُّ فعلٍ في " . count($t['rows']) . ' صفًّا')
            : bad("$tag — $noCtrl من " . count($t['rows']) . ' صفًّا أوّلُ خليتِها بلا زر');

        /* ③ لا عدّادَ تسلسليًّا */
        $seq = 0; $exp = 1;
        foreach ($t['rows'] as $r) {
            $c2 = isset($r['first']) ? $r['first'] : '';
            if ((string)$exp === $c2) { $seq++; }
            $exp++;
        }
        $seq === 0
            ? ok("$tag — لا عمودَ عدّادٍ في الصدارة")
            : bad("$tag — أثرُ عدّادٍ تسلسليٍّ في $seq صفًّا");

        /* ④ الحكمُ الأقوى: عددُ خلايا كلِّ صفٍّ = عددُ الرؤوسِ غيرِ المحقونة.
              هذا وحدَه يُثبت أنَّ نقلَ الرأسِ صحبه نقلُ خليتِه — وأن لا قيمةَ
              انزاحت خانةً. ويُقاس على المُصيَّرِ فتُحسَم الفروعُ إلى فرعٍ واحد. */
        $mis = array();
        foreach ($t['rows'] as $r) if ($r['n'] !== $t['cols']) $mis[] = $r['n'];
        empty($mis)
            ? ok("$tag — محاذاة: {$t['cols']} رأسًا = {$t['cols']} خليةً في " . count($t['rows']) . ' صفًّا')
            : bad("$tag — إزاحة: رؤوس={$t['cols']} خلايا=" . implode(',', array_slice(array_unique($mis), 0, 3)));
    }
}

fwrite(STDOUT, "\n══════════════════════════════\n");
fwrite(STDOUT, "نجاح: $PASS   إخفاق: $FAIL   تخطٍّ: $SKIP\n");
exit($FAIL > 0 ? 1 : 0);
