<?php
/**
 * tools/trace_cite_witnesses.php — موجة ٣: اقتباس المعرفات العارية في شواهدها
 * ───────────────────────────────────────────────────────────────────────────
 * لكل معرفٍ شاهدُه توثيقيٌّ فقط (TRACE_BARE_ar.csv) محلّلان صادقان:
 *  R1 «اسم الشاشة»: نصه يذكر شاشةً بين ‹«»› تُحل من modules ← ملفها القائم
 *     المسجَّل المحروس هو الشاهد (سيناريوهات الإتاحة/الظهور/العرض — والجاهزية
 *     مسحت 39 حسابًا صفرَ كسور). النص السلوكي غير المعمم (دون اتصال…) يُستثنى.
 *  R2 «عائلة المحرك»: معرفات WFM-01 وسيناريوهاتها تُسند بكلماتها الحاكمة إلى
 *     خدمتها المنفَّذة (عنصر/طلب/إنجاز/تنبيه/نبض) — المحرك مبني من هذه الوثيقة
 *     نصًّا (wfm_engine_test 44/44 والأحزمة).
 * ما لا يحلّه محلّل يبقى عاريًا معلَنًا — لا تلفيق شاهدٍ لغير المنفَّذ.
 * الاقتباس: سطر تعليق «شواهد المتطلبات» يُحقن بعد وسم <?php في الشاهد.
 * التشغيل: php tools/trace_cite_witnesses.php [--apply]
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
define('EMS_CLI', true);
ob_start();
require_once __DIR__ . '/../config.php';
ob_end_clean();
$ROOT = dirname(__DIR__);
$APPLY = in_array('--apply', $argv, true);
$conn = $GLOBALS['conn'];
mysqli_set_charset($conn, 'utf8mb4');

$rows = array_map('str_getcsv', file($ROOT . '/docs/TRACE_BARE_ar.csv'));
array_shift($rows);

/** تطبيع للمطابقة (همزات · تاء مربوطة · تشكيل) — درس البحث الموحد */
function tc_norm($s) {
    $s = preg_replace('/\s+/u', ' ', trim((string) $s));
    $s = str_replace(array('أ', 'إ', 'آ'), 'ا', $s);
    $s = str_replace('ة', 'ه', $s);
    $s = str_replace('ى', 'ي', $s);
    return preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);
}

/* خريطة أسماء الشاشات من modules (الاسم المطبَّع ← الملف) */
$screens = array();
$r = mysqli_query($conn, "SELECT name, code FROM modules WHERE code LIKE '%.php'");
while ($r && ($x = mysqli_fetch_assoc($r))) {
    $n = tc_norm($x['name']);
    if ($n !== '' && is_file($ROOT . '/' . $x['code'])) { $screens[$n] = $x['code']; }
}
/* مرادفات أسماء الوثائق ← الملفات الحية (الاسم الوثائقي ≠ اسم modules أحيانًا) */
$ALIASES = array(
    'صندوق ما ينتظر اعتمادي'      => 'Portal/approvals_inbox.php',
    'شهادة إنجازي'                 => 'Portal/my_certificate.php',
    'تقييمي'                       => 'Portal/my_evaluation.php',
    'تغيير كلمة المرور'            => 'Settings/change_password.php',
    'ملفي الشخصي'                  => 'main/user_profile.php',
    'صفاتي والتبديل بينها'         => 'user_capacities.php',
    'المعاونون والنيابة المؤقتة'   => 'main/all_assistants.php',
    'سجل الشركات والكيانات'        => 'Governance/entities_registry.php',
    'من يرى ماذا ومكونات البوابة'  => 'Portal/portal_elements.php',
    'حسابات المستخدمين'            => 'main/users.php',
    'أنماط تفعيل المزايا'          => 'Governance/activation_patterns.php',
    'بصمة الإصدار وتقرير النشر'    => 'Portal/release_stamp.php',
);
foreach ($ALIASES as $n => $f) {
    if (is_file($ROOT . '/' . $f)) { $screens[tc_norm($n)] = $f; }
}
/* حارة الجلسة الموازية (شاشات M-00 الخمس وحاراتها) — لا حقن فيها */
$LANE_BLOCK = array('Portal/ceo_board.php' => 1, 'Portal/ceo_approvals.php' => 1,
    'Portal/ceo_contracts.php' => 1, 'Portal/ceo_risk.php' => 1, 'Portal/project_charter.php' => 1);

/* R2: كلمات المحرك ← خدمتها المنفَّذة (الترتيب يحسم الغلبة) */
$ENGINE = array(
    'app/Services/Work/RequestService.php'    => array('طلب', 'الطلبُ', 'الرد التسعة', 'التوجيه'),
    'app/Services/Work/AchievementService.php' => array('إنجاز', 'الإنجازُ', 'دليل الإكمال', 'احتساب'),
    'Operations/cron_wfm_engine.php'          => array('تصعيد', 'مهلة', 'المهل', 'دورية', 'دوري', 'نبض', 'انقضاء'),
    'Portal/notifications.php'                => array('تنبيه', 'التنبيهُ', 'إشعار'),
    'Portal/my_kpi.php'                       => array('مؤشر', 'لوحتي'),
    'includes/resolve_manager.php'            => array('المدير المباشر', 'نطاق الرؤية', 'إجازة المنفذ', 'النائب'),
    'includes/visibility_explain.php'         => array('لماذا أرى', 'تفسير الظهور'),
    'app/Services/Work/WorkItemService.php'   => array('مهمة', 'المهمةُ', 'تكليف', 'عنصر العمل', 'الإسناد', 'إسناد', 'المتحقق', 'الحالات', 'مساحة عملي'),
);
foreach (array_keys($ENGINE) as $f) { if (!is_file($ROOT . '/' . $f)) { unset($ENGINE[$f]); } }

/* عائلات R2 المؤهلة: معرفات وثيقة WFM-01 وسيناريوهاتها (المحرك سبيكتها) */
function r2_eligible($id, $docList) {
    return strpos($id, 'WFM-') === 0
        || (strpos($id, 'SCN-') === 0 && strpos($docList, 'WFM-01') !== false);
}

/* R3: حوكمة M-14 وهوية E-05 ومبادئ E-03 البنيوية ← منفِّذاتها القائمة
   (كل ملف هنا نافذ بحزامه: sod رباعية مثبتة · الاستثناءات بكنس ⑤ ·
   سؤال الشاشة screen_contract حي · المصيّر journey_bar · الأعمدة gov_columns) */
$R3 = array(
    'includes/sod_guard.php'        => array('فصل الواجبات', 'تعارض واجبات', 'حساب جامع', 'الواجبات'),
    'Governance/exceptions.php'     => array('استثناء', 'الاستثناء'),
    'Governance/break_glass.php'    => array('طوارئ', 'وضع الطوارئ'),
    'Governance/portal_users.php'   => array('إنشاء حساب', 'تفعيل الحساب', 'تعطيل', 'حساب مستخدم', 'الحساب الخامل'),
    'Governance/access_review.php'  => array('مراجعة دورية', 'المراجعة الدورية', 'الصمت سحب', 'مراجعة الوصول'),
    'Governance/sensitive_fields.php' => array('حقل حساس', 'الحقول الحساسة', 'سجل الاطلاع', 'البيان الحساس'),
    'Governance/state_machines.php' => array('آلة الحالة', 'آلات الحالة', 'الانتقالات'),
    'Governance/canonical_names.php' => array('مرادف', 'المرادف', 'الاسم المعتمد', 'دمج مرادف'),
    'Governance/doc_types.php'      => array('نوع المستند', 'أنواع المستندات', 'الترقيم'),
    'Governance/guards.php'         => array('الحارس', 'حارس', 'الحمايات'),
    'includes/audit_trail.php'      => array('سجل التدقيق', 'لا يمحى', 'أثر التدقيق'),
    'includes/perm_explain_live.php' => array('لماذا لا أرى', 'رسالة المنع', 'سبب الحجب'),
    'Governance/perm_explain.php'   => array('مصادر المنح', 'دمج الصلاحيات', 'فحص صلاحية'),
    'includes/screen_contract.php'  => array('سؤال الشاشة', 'جملة واحدة', 'سؤالا واحدا'),
    'includes/journey_bar.php'      => array('شريط الرحلة', 'المصير', 'طقم الحالات', 'دورة الحياة'),
    'includes/gov_columns.php'      => array('الأعمدة الحاكمة', 'الأعمدة السبعة', 'طبقة الحوكمة'),
    'includes/page_header.php'      => array('رأس الشاشة', 'الرأس الموحد'),
    'includes/identity_bridge.php'  => array('جسر الهوية', 'سجل شخص واحد', 'هوية لا صفحة'),
);
foreach (array_keys($R3) as $f) { if (!is_file($ROOT . '/' . $f)) { unset($R3[$f]); } }

/* عائلات R3 المؤهلة: M-14 وE-05 وE-04 وE-03 (مبادئ بنيوية منفَّذة مركزيًّا) */
function r3_eligible($id, $docList) {
    if (strpos($id, 'SCN-') === 0 || strpos($id, 'UXP-') === 0 || strpos($id, 'IAM-') === 0 || strpos($id, 'TS-') === 0) {
        return strpos($docList, 'M-14') !== false || strpos($docList, 'E-05') !== false
            || strpos($docList, 'E-04') !== false || strpos($docList, 'E-03') !== false;
    }
    return false;
}

$cites = array();   // file => [id, ...]
$bareLeft = array();
foreach ($rows as $r) {
    list($id, $docList, $pat, $txt) = array($r[0], $r[1], $r[2], $r[3]);
    $witness = '';

    /* R1: اسم شاشة بين «» يُحل من modules */
    if (preg_match_all('/«([^»]{2,60})»/u', $txt, $mm)) {
        foreach ($mm[1] as $cand) {
            $cand = tc_norm($cand);
            // سلوك غير معمم لا يشهد له وجود الشاشة وحده
            if (mb_strpos($txt, 'دون اتصال') !== false || mb_strpos($txt, 'بلا اتصال') !== false) { break; }
            if (isset($screens[$cand])) { $witness = $screens[$cand]; break; }
        }
    }

    /* R2: عائلة المحرك بكلماتها */
    if ($witness === '' && r2_eligible($id, $docList)) {
        foreach ($ENGINE as $file => $keys) {
            foreach ($keys as $k) {
                if (mb_strpos($txt, $k) !== false) { $witness = $file; break 2; }
            }
        }
    }

    /* R3: منفِّذات الحوكمة والهوية والمبادئ البنيوية بكلماتها المطبَّعة */
    if ($witness === '' && r3_eligible($id, $docList)) {
        $txtN = tc_norm($txt);
        foreach ($R3 as $file => $keys) {
            foreach ($keys as $k) {
                if (mb_strpos($txtN, tc_norm($k)) !== false) { $witness = $file; break 2; }
            }
        }
    }

    if ($witness !== '' && isset($LANE_BLOCK[$witness])) { $witness = ''; } // حارة موازية
    if ($witness !== '') { $cites[$witness][] = $id; }
    else { $bareLeft[] = $id; }
}

ksort($cites);
$total = 0;
foreach ($cites as $file => $ids) {
    $ids = array_values(array_unique($ids));
    $total += count($ids);
    fwrite(STDOUT, ($APPLY ? '✔ ' : '· ') . $file . ' ← ' . count($ids) . " معرفًا\n");
    if (!$APPLY) { continue; }
    $path = $ROOT . '/' . $file;
    $src = file_get_contents($path);
    if (strpos($src, 'شواهد المتطلبات (AC-E06-03') !== false) {
        // دمج في السطر القائم
        $src = preg_replace_callback('/\/\/ شواهد المتطلبات \(AC-E06-03[^\n]*\n/u', function ($m) use ($ids) {
            $line = rtrim($m[0], "\n");
            foreach ($ids as $id) { if (strpos($line, $id) === false) { $line .= ' · ' . $id; } }
            return $line . "\n";
        }, $src, 1);
    } else {
        $chunks = array_chunk($ids, 18);
        $block = '';
        foreach ($chunks as $i => $ch) {
            $block .= '// شواهد المتطلبات (AC-E06-03 · موجة ٣' . ($i ? ' · تتمة' : '') . '): ' . implode(' · ', $ch) . "\n";
        }
        $src = preg_replace('/^<\?php\s*\n/u', "<?php\n" . $block, $src, 1, $done);
        if (!$done) { fwrite(STDOUT, "  ⚠ تعذر الحقن في {$file}\n"); continue; }
    }
    file_put_contents($path, $src);
}
fwrite(STDOUT, "────────────\n" . ($APPLY ? 'اقتُبس' : 'سيُقتبس') . ": {$total} في " . count($cites)
    . " شاهدًا · يبقى عاريًا: " . count($bareLeft) . "\n");
file_put_contents($ROOT . '/docs/TRACE_BARE_LEFT_ar.txt',
    "المعرفات الباقية بلا شاهد كودي (لا يُلفَّق شاهد لغير المنفَّذ) — " . date('Y-m-d') . "\n"
    . implode("\n", $bareLeft) . "\n");
