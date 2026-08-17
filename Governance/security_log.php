<?php
/**
 * Governance/security_log.php — سجلُّ الأمان (الدور ١٥ · قراءةٌ فقط)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ ما تضيفه هذه الشاشةُ ولا يعرضه غيرُها
 *   في النظامِ ثلاثةُ أسطحٍ تعرض جوانبَ من الأمن، ولا واحدَ منها يقرأ
 *   `logs/security.log`:
 *     ① `Governance/guard_denials.php` — يقرأ **جدولَي** `guard_denials`
 *        و`action_execution_log` من القاعدة. فما يكتبه حارسُ الكيانِ وحارسُ
 *        CSRF وفاحصُ ثوابتِ الأدوارِ وحاجبُ DDL **في الملف** لا يبلغه أبدًا.
 *     ② `ActivityLogs/activity_logs.php` — سجلُّ الأفعالِ **الناجحة** من
 *        `activity_logs`: مَن فعل ماذا ونجح. وسؤالُنا هنا عكسُه: ما الذي
 *        رُفض أو أُنذر منه.
 *     ③ `admin/csrf_monitor.php` — يقرأ الملفَّ نفسَه لكنه **لنوعٍ واحدٍ**
 *        (`csrf_violation`) ولا يفتحه إلا **المديرُ الأعلى** بقشرةِ `admin/`
 *        المنفصلة. فمديرُ الصلاحياتِ (الدور ١٥) لا سبيلَ له إليه.
 *
 *   فهذه الشاشةُ هي الطريقُ الوحيدُ لمديرِ الصلاحياتِ إلى **الملفِّ كلِّه**
 *   بتسعةٍ وأربعين نوعًا مقيسة — مصنَّفةً ثلاثَ درجاتٍ ومترجَمةً إلى جملٍ
 *   عربيةٍ يفهمها غيرُ التقنيّ.
 *
 *   ◆ **تداخلٌ معلَنٌ لا مسكوتٌ عنه**: `csrf_violation` يظهر هنا وفي
 *     `csrf_monitor` معًا. والفرقُ أن هناك تُقاس «جاهزيةُ الحجبِ لكلِّ مسار»
 *     (قرارُ ADR-05) وهنا يُعرض الحدثُ في سياقِ بقيةِ أنواعِ الأمن. ولمن يملك
 *     القشرتَين: قرارُ الحجبِ من `csrf_monitor` لا من هنا.
 *
 * ◆ قراءةٌ فقط — بلا معالجِ POST وبلا حارسِ أفعالٍ (لا فعلَ كاتبًا فيها).
 * ◆ الملفُّ يتجاوز ستين ميجابايتًا وينمو، فيُقرأ **من نهايته بنافذةٍ محدودة**
 *   (fseek بسالبٍ ثم SEEK_END) على نمطِ `admin/csrf_monitor.php` — ولا يُحمَّل
 *   ولا تُعَدُّ أسطرُه كلُّها. والمعروضُ يُعلَن «آخرَ N حدثًا» لا «كلَّ السجل».
 */

require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset($_SESSION['user'])) { header('Location: ../login.php'); exit(); }
include '../config.php';
require_once __DIR__ . '/../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/screen_contract.php';

$current_role   = strval($_SESSION['user']['role'] ?? '');
$is_super_admin = ($current_role === '-1');
$company_id     = intval($_SESSION['user']['company_id'] ?? 0);
if (!$is_super_admin && $company_id <= 0) { header('Location: ../login.php'); exit(); }

$__pp = check_page_permissions($conn, 'Governance/security_log.php');
if (!$is_super_admin && empty($__pp['can_view'])) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا تملك صلاحية عرض سجل الأمان',
        'GOV-PERM-403', 'شاشات الحوكمة يمنحها مدير الصلاحيات');
}
ems_shell_axes($__pp);

/* ═══════════════════════════════════════════════════════════════════════════
   الخريطةُ الدلالية — مصفوفةٌ واحدةٌ معلَنةٌ في رأس الملف
   ───────────────────────────────────────────────────────────────────────────
   لكلِّ نوعٍ: array(الدرجة، الجملةُ العربية).
   الدرجات: 'violation' مخالفة · 'warning' تحذير · 'routine' حدثٌ روتيني.

   ◆ **أكثرُ من نصفِ السجلِّ ليس مخالفة**: `tenant_gate_transaction` و
     `tenant_gate_system_context` و`tenant_gate_replace_children` تسجيلٌ
     روتينيٌّ لعملِ الحارسِ نفسِه — لا حدثَ أمنٍ فيها. فعرضُها بالافتراضِ
     يُغرق المخالفاتِ الحقيقيةَ في ضجيجٍ نسبتُه أربعةُ أخماسِ الملف.
   ◆ وأيُّ نوعٍ **غيرِ مذكورٍ هنا** يُعرض باسمِه الخام ويُوسَم «نوعٌ غيرُ
     مصنَّف» بدرجةِ «تحذير» — لا يُبتلع ولا يُخفى ولا يُفترض سليمًا.
   ═══════════════════════════════════════════════════════════════════════════ */
$SEC_EVENT_MAP = array(
    /* ── مخالفات ─────────────────────────────────────────────────────────── */
    'tenant_gate_violation'      => array('violation', 'حارسُ الكيانِ رفض العمليةَ ومنعها'),
    'tenant_gate_cross_tenant'   => array('violation', 'محاولةُ وصولٍ لبياناتِ كيانٍ آخر'),
    'csrf_violation'             => array('violation', 'طلبٌ بلا رمزِ حمايةٍ صالح'),
    'action_permission_violation' => array('violation', 'محاولةُ فعلٍ بلا صلاحية'),
    'WRITE_WITHOUT_PERMISSION_DENY' => array('violation', 'محاولةُ كتابةٍ بلا صلاحية — مُنعت'),
    'RUNTIME_DDL_BLOCKED'        => array('violation', 'محاولةُ تعديلِ بنيةِ القاعدةِ أثناءَ التشغيل — مُنعت'),
    'UNREGISTERED_SCREEN_DENY'   => array('violation', 'شاشةٌ غيرُ مسجَّلةٍ — مُنع الدخولُ إليها'),
    'PERM_DENY_UNREGISTERED'     => array('violation', 'مُنع لغيابِ تسجيلِ الشاشةِ في الموديولات'),
    'GOVERNANCE_SCREEN_DENY'     => array('violation', 'شاشةُ حوكمةٍ — مُنع الدخولُ إليها'),
    'GOVERNANCE_SCREEN_DENY_UNREGISTERED' => array('violation', 'شاشةُ حوكمةٍ غيرُ مسجَّلةٍ — مُنع الدخول'),
    'AUDITOR_WRITE_DENY'         => array('violation', 'مراجعٌ حاول الكتابةَ — مُنع'),
    'ROLE_ESCALATION_BLOCKED'    => array('violation', 'محاولةُ رفعِ دورٍ — مُنعت'),
    'SUPER_ADMIN_LOGIN_FAIL'     => array('violation', 'فشلُ دخولِ المديرِ الأعلى'),
    'SUPER_ADMIN_LOGIN_CSRF_FAIL' => array('violation', 'دخولُ المديرِ الأعلى بلا رمزِ حمايةٍ صالح'),
    'U13_ACTION_CSRF_FAIL'       => array('violation', 'فعلٌ رُدَّ لغيابِ رمزِ الحماية'),
    'rfq_cross_supplier_read'    => array('violation', 'اطّلاعُ موردٍ على عرضِ مورّدٍ غيرِه'),

    /* ── تحذيرات ─────────────────────────────────────────────────────────── */
    'role_constant_mismatch'     => array('warning', 'انحرافُ ثابتِ الدورِ عن مرجعِه المعتمد'),
    'UNREGISTERED_SCREEN_WOULD_DENY' => array('warning', 'شاشةٌ غيرُ مسجَّلةٍ — كانت ستُمنع'),
    'tenant_gate_would_deny'     => array('warning', 'حارسُ الكيانِ كان سيمنع — وهو في وضعِ المراقبة'),
    'container_gate_would_block' => array('warning', 'حارسُ الحاوياتِ كان سيحجب — وهو في وضعِ المراقبة'),
    'SENSITIVE_READ'             => array('warning', 'اطّلاعٌ على حقلٍ حسّاس'),
    'tenant_gate_delete_row'     => array('warning', 'حذفُ سجلٍّ عبرَ حارسِ الكيان'),
    'tenant_gate_delete_child'   => array('warning', 'حذفُ سجلٍّ تابعٍ عبرَ حارسِ الكيان'),
    'FANOUT_LEGAL_PARTIAL'       => array('warning', 'مروحةُ الأثرِ اكتملت جزئيًّا — أثرٌ ناقص'),
    'FANOUT_TS_DEFERRED'         => array('warning', 'أثرُ ساعاتٍ مؤجَّلٌ لم يقع بعد'),
    'OBL_ALERT_ESCALATED'        => array('warning', 'إنذارُ التزامٍ صُعِّد لجهةٍ أعلى'),
    'EVENT_ORPHANED_ALERT'       => array('warning', 'واقعةٌ بلا مرجعٍ أبٍ — إنذار'),
    'CONVERT_QUEUE_FAILED'       => array('warning', 'فشلُ طابورِ التحويل'),
    'REF_RESOLUTION_MISS'        => array('warning', 'مرجعٌ لم يُحَلَّ إلى سجلٍّ قائم'),
    'U13_CFIELD_MISSING'         => array('warning', 'حقلٌ حاكمٌ غائبٌ عن الطلب'),
    'CEO_CEILING_ESCALATED'      => array('warning', 'تجاوزُ سقفِ الاعتمادِ — صُعِّد للرئيس'),
    'RUNTIME_DDL_EXECUTED'       => array('warning', 'نُفِّذ تعديلُ بنيةٍ أثناءَ التشغيل'),

    /* ── أحداثٌ روتينية ───────────────────────────────────────────────────── */
    'tenant_gate_transaction'    => array('routine', 'معاملةٌ مرّت عبرَ حارسِ الكيان'),
    'tenant_gate_system_context' => array('routine', 'عملٌ بسياقِ النظامِ عبرَ حارسِ الكيان'),
    'tenant_gate_replace_children' => array('routine', 'استبدالُ سجلاتٍ تابعةٍ عبرَ حارسِ الكيان'),
    'tenant_gate_platform_context' => array('routine', 'عملٌ بسياقِ المنصّةِ عبرَ حارسِ الكيان'),
    'UNIT_CONVERTED'             => array('routine', 'تحويلُ وحدةِ قياس'),
    'U13_ACTION'                 => array('routine', 'فعلٌ مسجَّلٌ في الحارسِ المركزي'),
    'EVENT_HOOK_PUBLISHED'       => array('routine', 'واقعةُ عملٍ نُشرت في السجلِّ المحايد'),
    'EVENT_HOOK_MONITOR'         => array('routine', 'خطّافُ الوقائعِ في وضعِ المراقبة'),
    'FANOUT_TS'                  => array('routine', 'مروحةُ أثرِ ساعاتٍ وقعت'),
    'tickets_cron_run'           => array('routine', 'تشغيلُ مهمّةِ البلاغاتِ المجدولة'),
    'PROJECT_CREATED'            => array('routine', 'أُنشئ مشروع'),
    'PROJECT_UPDATED'            => array('routine', 'عُدِّل مشروع'),
    'PROJECT_UPDATE_REQUESTED'   => array('routine', 'طُلب تعديلُ مشروع'),
    'PROJECT_DELETE_REQUESTED'   => array('routine', 'طُلب حذفُ مشروع'),
    'PROJECT_SOFT_DELETED'       => array('routine', 'أُرشف مشروع'),
    'CEO_APPROVAL_DECIDED'       => array('routine', 'بتَّ الرئيسُ في اعتماد'),
    'CEO_CONTRACT_SIGNED'        => array('routine', 'وقّع الرئيسُ عقدًا'),
);

$SEC_GRADES = array(
    'violation' => array('مخالفة',      'badge-danger'),
    'warning'   => array('تحذير',       'badge-warning'),
    'routine'   => array('حدثٌ روتيني', 'badge-secondary'),
);

/* ═══════════════════════════════════════════════════════════════════════════
   قراءةُ الملفِّ من نهايته — بنافذةٍ محدودة
   ═══════════════════════════════════════════════════════════════════════════ */

/**
 * يقرأ آخرَ نافذةٍ من ملفٍّ نصيٍّ ضخمٍ بلا تحميلِه ولا عدِّ أسطرِه.
 * ◆ ويفرّق **صراحةً** بين «لا ملفّ» و«تعذّر الفتح»: الأولى حالةٌ طبيعيةٌ قبل
 *   أولِ حدث، والثانيةُ عطبُ أذوناتٍ يجب أن يُعلَن لا أن يُعرض جدولًا فارغًا.
 *
 * @param  string $path      مسارُ السجل
 * @param  int    $maxBytes  سقفُ البايتاتِ المقروءةِ من النهاية
 * @param  string $err       يُملأ بـ '' | 'missing' | 'unreadable'
 * @return array|null        أسطرٌ خامٌّ (مصفوفة) أو null عند التعذّر
 */
function sec_log_tail_lines($path, $maxBytes, &$err)
{
    $err = '';
    if (!file_exists($path)) { $err = 'missing'; return null; }
    if (!is_readable($path)) { $err = 'unreadable'; return null; }

    $size = @filesize($path);
    $fh   = @fopen($path, 'rb');
    if ($size === false || !$fh) { $err = 'unreadable'; return null; }

    $truncated = false;
    if ($size > $maxBytes) {
        if (@fseek($fh, -$maxBytes, SEEK_END) !== 0) { fclose($fh); $err = 'unreadable'; return null; }
        $truncated = true;
        fgets($fh);  // السطرُ الأولُ بعد القفزِ مبتورٌ — يُطرح
    }

    $lines = array();
    while (($ln = fgets($fh)) !== false) { $lines[] = rtrim($ln, "\r\n"); }
    fclose($fh);

    $GLOBALS['SEC_WINDOW_TRUNCATED'] = $truncated;
    $GLOBALS['SEC_FILE_SIZE'] = $size;
    return $lines;
}

/**
 * يطوي الأسطرَ إلى **أحداث**.
 * ◆ الگوتشا المقيسة: `Details` حرٌّ وقد يحوي SQL بأسطرٍ متعددة — ففي الملفِّ
 *   ١٩٩٬٩٤٦ سطرًا ماديًّا مقابلَ ١٨٩٬٠٥٦ حدثًا (١٠٬٨٩٠ سطرَ امتداد). ومَن يقسم
 *   بـ`\n` ويطرح ما لا يطابق **يبتر التفاصيلَ ويخطئ العدَّ معًا**.
 *   فالحدثُ يبدأ بـ`[تاريخ] [نوع]`، وكلُّ ما عداه امتدادٌ يُضَمُّ لسابقِه.
 */
function sec_fold_events(array $lines)
{
    $events = array();
    $cur = null;
    foreach ($lines as $ln) {
        if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s*\[([^\]]+)\]/u', $ln, $m)) {
            if ($cur !== null) { $events[] = $cur; }
            $cur = array('ts' => $m[1], 'type' => $m[2], 'raw' => $ln);
        } elseif ($cur !== null) {
            $cur['raw'] .= "\n" . $ln;    // امتدادُ التفاصيل
        }
        /* سطرُ امتدادٍ بلا حدثٍ سابقٍ = بقيةُ حدثٍ سقط خارجَ النافذة — يُطرح */
    }
    if ($cur !== null) { $events[] = $cur; }
    return $events;
}

/** يفكّك حقولَ الحدثِ من سطرِه الخام. */
function sec_parse_event(array $ev)
{
    $raw = $ev['raw'];

    $ev['ip'] = '';
    if (preg_match('/\|\s*IP:\s*(\S+)/u', $raw, $m) || preg_match('/\bIP:\s*(\S+)/u', $raw, $m)) {
        $ev['ip'] = $m[1];
    }

    /* «الاسم (المعرّف)» — والاسمُ عربيٌّ فلا يُقيَّد بـ\w */
    $ev['user_name'] = ''; $ev['user_id'] = '';
    if (preg_match('/\bUser:\s*(.*?)\s*\(([^)]*)\)\s*\|/u', $raw, $m)) {
        $ev['user_name'] = trim($m[1]);
        $ev['user_id']   = trim($m[2]);
    }

    /* UA آخرُ الحقول — والتفاصيلُ ما بين `Details:` وآخرِ `| UA:` */
    $ev['ua'] = '';
    if (preg_match('/\|\s*UA:\s*(.*)\z/su', $raw, $m)) { $ev['ua'] = trim($m[1]); }

    $ev['details'] = '';
    $dAt = mb_strpos($raw, 'Details:');
    if ($dAt !== false) {
        $tail = mb_substr($raw, $dAt + mb_strlen('Details:'));
        $uAt  = mb_strrpos($tail, '| UA:');
        $ev['details'] = trim($uAt !== false ? mb_substr($tail, 0, $uAt) : $tail);
    }

    /* «أين» — المسارُ أو الجدولُ المذكورُ في التفاصيل، إن وُجد */
    $ev['where'] = '';
    if (preg_match('/\bscript=(\S+)/u', $ev['details'], $m)) {
        $ev['where'] = $m[1];
    } elseif (preg_match('~\b([A-Za-z][A-Za-z0-9_]*/[A-Za-z0-9_.\-]+\.php)~u', $ev['details'], $m)) {
        $ev['where'] = $m[1];
    } elseif (preg_match('/`([A-Za-z0-9_]+)`/u', $ev['details'], $m)) {
        $ev['where'] = $m[1];
    }

    /* ضجيجُ أدواتِ الاختبار — لا سلوكَ مستخدمٍ حقيقيّ (القرارُ يُبنى على
       المتصفحاتِ وحدَها) */
    $ev['harness'] = ($ev['user_id'] === 'GUEST' || $ev['user_name'] === 'GUEST'
                   || $ev['ip'] === 'UNKNOWN'
                   || stripos($ev['ua'], 'curl') !== false);

    $ev['epoch'] = strtotime($ev['ts']);
    return $ev;
}

/** الدرجةُ والجملةُ — وغيرُ المصنَّفِ يُعلَن ولا يُبتلع. */
function sec_classify($type, array $map)
{
    if (isset($map[$type])) {
        return array($map[$type][0], $map[$type][1], false);
    }
    return array('warning', $type, true);   // اسمُه الخام + وسمُ «غيرُ مصنَّف»
}

/** «منذ كم» بصياغةٍ عربيةٍ مختصرة. */
function sec_ago($epoch)
{
    if (!$epoch) { return '—'; }
    $d = time() - $epoch;
    if ($d < 60)    { return 'الآن'; }
    if ($d < 3600)  { return 'منذ ' . intval($d / 60) . ' دقيقة'; }
    if ($d < 86400) { return 'منذ ' . intval($d / 3600) . ' ساعة'; }
    return 'منذ ' . intval($d / 86400) . ' يوم';
}

/* ── مدخلاتُ الترشيح ────────────────────────────────────────────────────── */
$WIN_CHOICES = array(1500, 500, 5000);
$win = intval($_GET['win'] ?? 0);
if (!in_array($win, $WIN_CHOICES, true)) { $win = $WIN_CHOICES[0]; }

$grade  = (string) ($_GET['grade'] ?? 'flagged');
if (!in_array($grade, array('flagged', 'violation', 'warning', 'routine', 'all'), true)) { $grade = 'flagged'; }
$fType  = trim((string) ($_GET['type'] ?? ''));
$fUser  = trim((string) ($_GET['user'] ?? ''));
$period = (string) ($_GET['period'] ?? 'all');
if (!in_array($period, array('all', 'today', '7', '30'), true)) { $period = 'all'; }
$q      = trim((string) ($_GET['q'] ?? ''));
$noise  = !empty($_GET['noise']);

/* النافذةُ بالبايت: مقاسٌ من متوسطِ الحدثِ (~٣٣١ بايتًا) بهامشٍ للأحداثِ
   الحاملةِ SQL متعددَ الأسطر — ثم يُقتطع الفائضُ بالعدد لا بالبايت. */
$maxBytes = min(8 * 1024 * 1024, max(256 * 1024, $win * 800));

$logPath = dirname(__DIR__) . '/logs/security.log';
$logErr  = '';
$lines   = sec_log_tail_lines($logPath, $maxBytes, $logErr);

$events = array();
if ($lines !== null) {
    $events = sec_fold_events($lines);
    if (count($events) > $win) { $events = array_slice($events, -$win); }
    foreach ($events as $i => $ev) { $events[$i] = sec_parse_event($ev); }
}

/* ── ما قُرئ فعلًا: يُعلَن ولا يُدَّعى ─────────────────────────────────────── */
$readCount = count($events);
$readFrom  = $readCount ? $events[0]['ts'] : '';
$readTo    = $readCount ? $events[$readCount - 1]['ts'] : '';

/* ── قوائمُ الترشيحِ تُبنى من النافذةِ نفسِها لا من قائمةٍ متخيَّلة ────────── */
$typesInWin = array(); $usersInWin = array();
foreach ($events as $ev) {
    if (!$noise && $ev['harness']) { continue; }
    $typesInWin[$ev['type']] = ($typesInWin[$ev['type']] ?? 0) + 1;
    if ($ev['user_id'] !== '') {
        $uk = $ev['user_id'];
        if (!isset($usersInWin[$uk])) { $usersInWin[$uk] = array('n' => 0, 'name' => $ev['user_name']); }
        $usersInWin[$uk]['n']++;
    }
}
arsort($typesInWin);
uasort($usersInWin, function ($a, $b) { return $b['n'] - $a['n']; });

/* ── التطبيق ────────────────────────────────────────────────────────────── */
$cutoff = 0;
if ($period === 'today') { $cutoff = strtotime('today 00:00:00'); }
elseif ($period === '7')  { $cutoff = strtotime('-7 days'); }
elseif ($period === '30') { $cutoff = strtotime('-30 days'); }

$rows = array();
$cViolation = 0; $cWarning = 0; $cRoutine = 0; $cHarnessHidden = 0;
$typeTally = array(); $userTally = array();

foreach ($events as $ev) {
    if (!$noise && $ev['harness']) { $cHarnessHidden++; continue; }
    if ($cutoff && $ev['epoch'] && $ev['epoch'] < $cutoff) { continue; }

    list($g, $sentence, $unmapped) = sec_classify($ev['type'], $SEC_EVENT_MAP);

    if ($grade === 'flagged' && $g === 'routine') { continue; }
    if (in_array($grade, array('violation', 'warning', 'routine'), true) && $g !== $grade) { continue; }
    if ($fType !== '' && $ev['type'] !== $fType) { continue; }
    if ($fUser !== '' && $ev['user_id'] !== $fUser) { continue; }
    if ($q !== '' && mb_stripos($ev['details'], $q) === false && mb_stripos($ev['raw'], $q) === false) { continue; }

    $ev['grade'] = $g; $ev['sentence'] = $sentence; $ev['unmapped'] = $unmapped;
    $rows[] = $ev;

    if ($g === 'violation') { $cViolation++; } elseif ($g === 'warning') { $cWarning++; } else { $cRoutine++; }
    $typeTally[$ev['type']] = ($typeTally[$ev['type']] ?? 0) + 1;
    if ($ev['user_id'] !== '') {
        $uk = $ev['user_id'];
        if (!isset($userTally[$uk])) { $userTally[$uk] = array('n' => 0, 'name' => $ev['user_name']); }
        $userTally[$uk]['n']++;
    }
}
$rows = array_reverse($rows);   // الأحدثُ أولًا

arsort($typeTally);
uasort($userTally, function ($a, $b) { return $b['n'] - $a['n']; });
$topType = null; $topTypeN = 0;
foreach ($typeTally as $t => $n) { $topType = $t; $topTypeN = $n; break; }
$topUser = null; $topUserN = 0; $topUserName = '';
foreach ($userTally as $uid => $u) { $topUser = $uid; $topUserN = $u['n']; $topUserName = $u['name']; break; }

$filtersActive = ($grade !== 'flagged' || $fType !== '' || $fUser !== '' || $period !== 'all' || $q !== '');

/** رابطٌ يحفظ حالةَ الترشيحِ ويبدّل ما يُمرَّر — فبطاقةُ المؤشرِ تُطبّق فلترَها. */
function sec_url(array $over)
{
    $base = array('win' => $_GET['win'] ?? '', 'grade' => $_GET['grade'] ?? '',
                  'type' => $_GET['type'] ?? '', 'user' => $_GET['user'] ?? '',
                  'period' => $_GET['period'] ?? '', 'q' => $_GET['q'] ?? '',
                  'noise' => $_GET['noise'] ?? '');
    $p = array_merge($base, $over);
    $p = array_filter($p, function ($v) { return $v !== '' && $v !== null; });
    return 'security_log.php' . ($p ? '?' . http_build_query($p) : '');
}

$page_title = 'إيكوبيشن | سجل الأمان';
include '../inheader.php';
include '../insidebar.php';
if (isset($conn)) { ems_screen_about_auto($conn); }
?>
<div class="main ems-unified-page-shell">
    <?php
    $header_title   = 'سجل الأمان';
    $header_icon    = 'fas fa-shield-halved';
    $header_actions = array();
    $header_back    = array();
    $header_context = array(
        'المقام'  => 'logs/security.log — قراءةٌ فقط',
        'المقروء' => $readCount ? ('آخر ' . number_format($readCount) . ' حدثًا') : 'لا شيء',
        'من'      => $readFrom !== '' ? $readFrom : '—',
        'إلى'     => $readTo !== '' ? $readTo : '—',
    );
    include('../includes/page_header.php');
    // UXW-01 ⑨: تكملةُ حالاتِ الشاشةِ الدنيا — الفراغُ قائمٌ سلفًا (ems_state_empty
    // أسفلَ الجدول) فيُضاف التحميلُ والخطأُ وحدَهما مخفيَّين، بلا تكرارِ الفراغ
    echo ems_state('loading', 'جارٍ قراءةُ نافذةِ سجلِّ الأمان', '', '', true);
    echo ems_state('error', 'تعذّر قراءةُ سجلِّ الأمان', 'راجِعْ صلاحياتِ الوصولِ إلى logs/security.log ثم أعد المحاولة', '', true);
    ems_screen_about(
        'سجلُّ الأمانِ الخامُّ كما يكتبه الحرّاسُ في الملفِّ — لا في القاعدة. يعرض ما رفضه '
        . 'حارسُ الكيانِ وحارسُ رموزِ الحماية وفاحصُ ثوابتِ الأدوارِ وحاجبُ تعديلِ البنية، '
        . 'مترجَمًا إلى جملٍ عربيةٍ ومصنَّفًا ثلاثَ درجات.',
        array(
            'الملفُّ يتجاوز ستين ميجابايتًا: يُقرأ من نهايته بنافذةٍ محدودة — فالمعروضُ آخرُ الأحداثِ لا كلُّها',
            'أكثرُ من نصفِ السجلِّ تسجيلٌ روتينيٌّ لا مخالفة — ولذلك يُخفى بالافتراضِ ويُظهره زرٌّ صريح',
            'أسطرُ أدواتِ الاختبار (زائرٌ · عنوانٌ مجهول · curl) مستبعَدةٌ بالافتراض — القرارُ على المتصفحاتِ الحقيقيةِ وحدَها',
        ));
    ?>

    <?php if ($logErr === 'missing'): ?>
        <div class="alert alert-warning">
            <strong>لا ملفَّ سجلٍّ بعد.</strong>
            المسارُ <code>logs/security.log</code> غيرُ موجود — ولم يُكتب فيه حدثٌ أمنيٌّ حتى الآن.
            هذه حالةٌ طبيعيةٌ قبلَ أولِ حدث، وليست عطبًا.
        </div>
    <?php elseif ($logErr === 'unreadable'): ?>
        <div class="alert alert-danger">
            <strong>تعذّرت قراءةُ ملفِّ السجل.</strong>
            المسارُ <code>logs/security.log</code> موجودٌ ولكنْ **لم يُفتح** — راجعْ أذوناتِ الملفِّ
            على الخادم. <em>ولا يُعرض جدولٌ هنا: جدولٌ فارغٌ في هذه الحالةِ يكذب «لا أحداثَ»
            بينما الأحداثُ قائمةٌ ولم تُقرأ.</em>
        </div>
    <?php else: ?>

    <?php
    /* ── بطاقاتُ المؤشرات — كلُّها قابلةٌ للنقرِ تطبّق فلترَها ─────────────── */
    require_once __DIR__ . '/../includes/kpi_card.php';
    $periodLabel = 'النافذةُ المقروءة (' . ($readFrom !== '' ? $readFrom : '—') . ' ← ' . ($readTo !== '' ? $readTo : '—') . ')';
    ?>
    <div class="ems-grid">
        <?php
        echo ems_kpi_card(array(
            'title' => 'إجمالي المعروض', 'value' => number_format(count($rows)), 'unit' => 'حدث',
            'period' => $periodLabel, 'status' => 'neutral', 'drill' => sec_url(array('grade' => 'all')),
            'comparison' => 'من ' . number_format($readCount) . ' حدثًا مقروءًا',
            'scope' => 'انقر: أظهرِ الروتينيَّ أيضًا', 'icon' => 'fa-list', 'class' => 'ems-col-4'));

        echo ems_kpi_card(array(
            'title' => 'مخالفات', 'value' => number_format($cViolation), 'unit' => 'حدث',
            'period' => $periodLabel, 'status' => $cViolation > 0 ? 'err' : 'ok',
            'drill' => sec_url(array('grade' => 'violation')),
            'scope' => 'انقر: اقصرِ العرضَ عليها', 'icon' => 'fa-ban', 'class' => 'ems-col-4'));

        echo ems_kpi_card(array(
            'title' => 'تحذيرات', 'value' => number_format($cWarning), 'unit' => 'حدث',
            'period' => $periodLabel, 'status' => $cWarning > 0 ? 'warn' : 'ok',
            'drill' => sec_url(array('grade' => 'warning')),
            'scope' => 'انقر: اقصرِ العرضَ عليها', 'icon' => 'fa-triangle-exclamation', 'class' => 'ems-col-4'));

        echo ems_kpi_card(array(
            'title' => 'أكثرُ نوعٍ تكرارًا', 'value' => $topType === null ? '0' : number_format($topTypeN),
            'unit' => 'حدث', 'period' => $periodLabel, 'status' => $topType === null ? 'ok' : 'warn',
            'drill' => $topType === null ? sec_url(array()) : sec_url(array('type' => $topType, 'grade' => 'all')),
            'scope' => $topType === null ? 'لا نوعَ في المعروض' : sec_classify($topType, $SEC_EVENT_MAP)[1],
            'icon' => 'fa-layer-group', 'class' => 'ems-col-4'));

        echo ems_kpi_card(array(
            'title' => 'أكثرُ مستخدمٍ ظهورًا', 'value' => $topUser === null ? '0' : number_format($topUserN),
            'unit' => 'حدث', 'period' => $periodLabel, 'status' => 'neutral',
            'drill' => $topUser === null ? sec_url(array()) : sec_url(array('user' => $topUser, 'grade' => 'all')),
            'scope' => $topUser === null ? 'لا مستخدمَ في المعروض'
                     : ($topUserName !== '' ? $topUserName : 'بلا اسم') . ' (' . $topUser . ')',
            'icon' => 'fa-user', 'class' => 'ems-col-4'));
        ?>
    </div>

    <?php /* ── شريطُ الفلاتر: الرأسُ والعدّادُ وزرُّ التفريغِ من العدّةِ المركزية ── */ ?>
        <!-- صندوقُ الفلاترِ الموحَّد — التصميمُ في assets/css/ems-filters.css -->
    <div class="filter">
        <div class="filter-title"><span class="filter-title-icon"><i class="fa-solid fa-sliders"></i></span> فلاتر البحث</div>
        <div class="filter-body">
    <form method="get" action="security_log.php" class="ems-card">
        <div class="ems-grid">
            <div class="ems-col-3">
                <label for="secWin">حجمُ النافذةِ المقروءة</label>
                <select id="secWin" name="win" class="form-control form-control-sm">
                    <?php foreach ($WIN_CHOICES as $w): ?>
                    <option value="<?php echo $w; ?>" <?php echo $win === $w ? 'selected' : ''; ?>>
                        آخر <?php echo number_format($w); ?> حدثًا</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ems-col-3">
                <label for="secGrade">الدرجة</label>
                <select id="secGrade" name="grade" class="form-control form-control-sm">
                    <option value="flagged"   <?php echo $grade === 'flagged'   ? 'selected' : ''; ?>>مخالفاتٌ وتحذيراتٌ (الافتراضي)</option>
                    <option value="violation" <?php echo $grade === 'violation' ? 'selected' : ''; ?>>مخالفاتٌ فقط</option>
                    <option value="warning"   <?php echo $grade === 'warning'   ? 'selected' : ''; ?>>تحذيراتٌ فقط</option>
                    <option value="routine"   <?php echo $grade === 'routine'   ? 'selected' : ''; ?>>الأحداثُ الروتينيةُ فقط</option>
                    <option value="all"       <?php echo $grade === 'all'       ? 'selected' : ''; ?>>الكلُّ بما فيه الروتيني</option>
                </select>
            </div>
            <div class="ems-col-3">
                <label for="secPeriod">الفترة</label>
                <select id="secPeriod" name="period" class="form-control form-control-sm">
                    <option value="all"   <?php echo $period === 'all'   ? 'selected' : ''; ?>>كلُّ النافذةِ المقروءة</option>
                    <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>اليوم</option>
                    <option value="7"     <?php echo $period === '7'     ? 'selected' : ''; ?>>آخر ٧ أيام</option>
                    <option value="30"    <?php echo $period === '30'    ? 'selected' : ''; ?>>آخر ٣٠ يومًا</option>
                </select>
            </div>
            <div class="ems-col-3">
                <label for="secType">نوعُ الحدث</label>
                <select id="secType" name="type" class="form-control form-control-sm">
                    <option value="">كلُّ الأنواع</option>
                    <?php foreach ($typesInWin as $t => $n):
                        $cl = sec_classify($t, $SEC_EVENT_MAP); ?>
                    <option value="<?php echo htmlspecialchars($t); ?>" <?php echo $fType === $t ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cl[1]); ?> (<?php echo number_format($n); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ems-col-3">
                <label for="secUser">المستخدم</label>
                <select id="secUser" name="user" class="form-control form-control-sm">
                    <option value="">كلُّ المستخدمين</option>
                    <?php foreach ($usersInWin as $uid => $u): ?>
                    <option value="<?php echo htmlspecialchars((string) $uid); ?>" <?php echo $fUser === (string) $uid ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['name'] !== '' ? $u['name'] : 'بلا اسم'); ?>
                        (<?php echo htmlspecialchars((string) $uid); ?>) — <?php echo number_format($u['n']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="ems-col-3">
                <label for="secQ">بحثٌ في التفاصيل</label>
                <input type="text" id="secQ" name="q" class="form-control form-control-sm"
                       value="<?php echo htmlspecialchars($q); ?>" placeholder="نصٌّ داخل Details">
            </div>
            <div class="ems-col-3">
                <label for="secNoise">ضجيجُ أدواتِ الاختبار</label>
                <div>
                    <input type="checkbox" id="secNoise" name="noise" value="1" <?php echo $noise ? 'checked' : ''; ?>>
                    <span class="text-muted">أظهرْ أسطرَ الزائرِ وcurl والعنوانِ المجهول</span>
                </div>
            </div>
            <div class="ems-col-3">
                <button type="submit" class="btn-primary">تطبيقُ الترشيح</button>
                <?php if ($grade === 'flagged'): ?>
                <a class="btn-secondary" href="<?php echo htmlspecialchars(sec_url(array('grade' => 'all'))); ?>">إظهارُ الأحداثِ الروتينية</a>
                <?php else: ?>
                <a class="btn-secondary" href="<?php echo htmlspecialchars(sec_url(array('grade' => 'flagged'))); ?>">إخفاءُ الأحداثِ الروتينية</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
        </div>
    </div>

    <div class="card"><div class="card-body table-responsive">
        <h6>الأحداث (<?php echo number_format(count($rows)); ?>)
            <small class="text-muted">
                — مقروءٌ من نهايةِ الملفِّ: <?php echo number_format($readCount); ?> حدثًا
                <?php if (!empty($GLOBALS['SEC_WINDOW_TRUNCATED'])): ?>
                    من أصلِ ملفٍّ حجمُه <?php echo number_format(round(($GLOBALS['SEC_FILE_SIZE'] ?? 0) / 1048576, 1), 1); ?> ميجابايت
                <?php endif; ?>
                <?php if (!$noise && $cHarnessHidden > 0): ?>
                    · أُخفي <?php echo number_format($cHarnessHidden); ?> سطرَ أدواتِ اختبار
                <?php endif; ?>
            </small>
        </h6>

        <?php if (!$rows): ?>
            <?php if ($filtersActive) {
                ems_state_empty('لا نتائجَ لهذا الترشيح — والنافذةُ المقروءةُ فيها '
                    . number_format($readCount) . ' حدثًا. وسّعِ الفترةَ أو الدرجةَ، أو فرّغِ الفلاترَ من رأسِ الشريط.');
            } else {
                ems_state_empty('لا أحداثَ أمنيةٍ في النافذةِ المقروءة — الحرّاسُ هادئون ✨');
            } ?>
        <?php else: ?>
        <div class="table-container">
        <table class="alltables display nowrap">
            <thead><tr>
                <th>متى</th>
                <th>من</th>
                <th>ماذا حدث</th>
                <th>الدرجة</th>
                <th>أين</th>
                <th>من أي جهاز</th>
                <th>التفصيل</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $ev):
                $g = $SEC_GRADES[$ev['grade']]; ?>
                <tr>
                    <td data-order="<?php echo (int) $ev['epoch']; ?>">
                        <?php echo htmlspecialchars($ev['ts']); ?>
                        <small class="text-muted"><?php echo htmlspecialchars(sec_ago($ev['epoch'])); ?></small>
                    </td>
                    <td>
                        <?php if ($ev['harness']): ?>
                            <span class="badge badge-secondary">زائرٌ/أداة</span>
                            <small class="text-muted"><?php echo htmlspecialchars($ev['user_name'] !== '' ? $ev['user_name'] : '—'); ?></small>
                        <?php else: ?>
                            <?php echo htmlspecialchars($ev['user_name'] !== '' ? $ev['user_name'] : 'بلا اسم'); ?>
                            <small class="text-muted">(<?php echo htmlspecialchars($ev['user_id'] !== '' ? $ev['user_id'] : '—'); ?>)</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($ev['sentence']); ?>
                        <?php if ($ev['unmapped']): ?>
                            <span class="badge badge-info">نوعٌ غيرُ مصنَّف</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?php echo $g[1]; ?>"><?php echo htmlspecialchars($g[0]); ?></span></td>
                    <td><?php echo htmlspecialchars($ev['where'] !== '' ? $ev['where'] : '—'); ?></td>
                    <td><?php echo htmlspecialchars($ev['ip'] !== '' ? $ev['ip'] : '—'); ?></td>
                    <td>
                        <details>
                            <summary>السطرُ الخام</summary>
                            <pre><?php echo htmlspecialchars($ev['raw']); ?></pre>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div></div>

    <?php endif; ?>
</div>
</body>
</html>
