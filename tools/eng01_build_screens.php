<?php
/**
 * eng01_build_screens.php — مولِّدُ شاشاتِ المحرّكاتِ الثماني (ENG-01 ⑨)
 * ═══════════════════════════════════════════════════════════════════════════
 * «◆ ولا تجعلْ الحارسَ بعدَ الكتابة — الترتيبُ في كلِّ ملفٍّ: جلسةٌ ثم إعدادٌ
 *    ثم حارسُ شاشةٍ ثم حارسُ فعلٍ ثم رمزُ حمايةٍ ثم معالجُ POST ثم العرض»
 * «◆ ولا تكتبْ في جدولٍ من ملفِّ الشاشةِ مباشرةً — الشاشةُ تُنادي خدمةً
 *    والخدمةُ تكتب»
 *
 * المولِّدُ يفرض الترتيبَ في كلِّ ملفٍّ نصًّا — «◆ والعلاجُ في المولِّدِ لا في
 * المخرَج» (ENG-01 DM-02). وتعديلُ الترتيبِ هنا يُعيد الثمانيةَ بموجةٍ واحدة.
 *
 * التشغيل: php tools/eng01_build_screens.php [--force]
 */
if (PHP_SAPI !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT  = dirname(__DIR__);
$force = in_array('--force', $argv, true);

/** الأقسامُ السبعةُ بترتيبِها الملزم — تُبنى لكلِّ شاشة. */
function build(array $s)
{
    $up      = $s['dir'] === 'Governance' ? '..' : '..';
    $route   = $s['dir'] . '/' . $s['file'];
    $title   = $s['title'];
    $actions = $s['actions'];
    $svcReq  = '';
    foreach ($s['services'] as $svc) {
        $svcReq .= "require_once __DIR__ . '/{$up}/app/Services/{$svc}';\n";
    }
    $actionList = "'" . implode("', '", $actions) . "'";

    $head = <<<PHP
<?php
/**
 * {$route} — {$title}
 * ───────────────────────────────────────────────────────────────────────────
 * ENG-01 · المحرّكاتُ المشتركة. موضعُها من GOV-24: {$s['stage']}
 * أفعالُها في قاموسِ الأفعال: {$actionList}
 *
 * ◆ الترتيبُ الملزمُ في هذا الملفّ:
 *   ① جلسة → ② إعداد → ③ حارسُ شاشة → ④ حارسُ فعل → ⑤ رمزُ حماية
 *   → ⑥ معالجُ POST → ⑦ العرض
 * ◆ ولا تكتب هذه الشاشةُ في جدولٍ مباشرةً — تنادي خدمةً والخدمةُ تكتب.
 */

// ═══ ① جلسة ═══
require_once __DIR__ . '/{$up}/includes/session_bootstrap.php';
session_start();
if (!isset(\$_SESSION['user'])) { header('Location: {$up}/login.php'); exit(); }

// ═══ ② إعداد ═══
require_once __DIR__ . '/{$up}/config.php';
require_once __DIR__ . '/{$up}/includes/permissions_helper.php';
require_once __DIR__ . '/{$up}/includes/security.php';
{$svcReq}
\$company_id     = isset(\$_SESSION['user']['company_id']) ? intval(\$_SESSION['user']['company_id']) : 0;
\$is_super_admin = (strval(\$_SESSION['user']['role'] ?? '') === '-1');
\$uid            = intval(\$_SESSION['user']['id'] ?? 0);
\$role_id        = intval(\$_SESSION['user']['role'] ?? 0);
\$SCREEN         = '{$route}';
if (!\$is_super_admin && \$company_id <= 0) { header('Location: {$up}/main/dashboard.php'); exit(); }

// ═══ ③ حارسُ الشاشة — can_view من سجلِّ الوحدات قبلَ أيِّ قراءةٍ أو كتابة ═══
\$__pp = check_page_permissions(\$conn, \$SCREEN);
if (!\$is_super_admin && empty(\$__pp['can_view'])) {
    header('Location: {$up}/main/dashboard.php?denied=' . rawurlencode(\$SCREEN));
    exit();
}

// ═══ ④ حارسُ الفعل — الكتابةُ تحتاج منحةً صريحةً لا مجرَّدَ عرض ═══
\$__canWrite = \$is_super_admin || !empty(\$__pp['can_add']) || !empty(\$__pp['can_edit']);
if (\$_SERVER['REQUEST_METHOD'] === 'POST' && !\$__canWrite) {
    http_response_code(403);
    exit('غير مصرَّحٍ بالكتابة في هذه الشاشة — اطلبِ المنحةَ من مدير الصلاحيات');
}

// ═══ ⑤ رمزُ الحماية — قبلَ أيِّ معالجةٍ لا بعدَها ═══
if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!function_exists('verify_csrf_token') || !verify_csrf_token(\$_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('رمزُ الحمايةِ غيرُ صالح — أعدْ تحميلَ الصفحة');
    }
}

// ═══ ⑥ معالجُ POST — ينادي الخدمةَ ولا يكتب في جدول ═══
\$flash = null; \$flashKind = 'info';
if (\$_SERVER['REQUEST_METHOD'] === 'POST') {
    \$__action = (string) (\$_POST['action'] ?? '');
{$s['post']}
}

// ═══ ⑦ العرض ═══
{$s['view']}

PHP;
    return $head;
}

$SCREENS = array(
// ─────────────────────────────── ناقلُ الأحداث ───────────────────────────────
array(
'dir' => 'Governance', 'file' => 'bus_outbox.php',
'title' => 'صندوق الأحداث الصادر',
'stage' => 'المرحلة «نراقب الأحداثَ والمهام» · مجموعة «ناقلُ الأحداث»',
'actions' => array('bus.event.publish'),
'services' => array('Bus/EventOutboxFanout.php'),
'post' => <<<'P'
    if ($__action === 'recount') {
        // إعادةُ عدِّ المستهلكينَ المعلَنينَ لحدثٍ — خدمةٌ لا كتابةٌ من هنا
        $oid = (int) ($_POST['outbox_id'] ?? 0);
        $row = $conn->query("SELECT event_key, company_id FROM ems_business_events WHERE id=" . $oid)->fetch_assoc();
        if (!$row) { $flash = 'صفُّ صادرٍ غيرُ موجود'; $flashKind = 'error'; }
        else {
            $n = \App\Services\Bus\EventOutboxFanout::open($conn, $oid, $row['event_key'], (int) $row['company_id']);
            $flash = 'أُعيد فتحُ ' . $n . ' صفَّ تسليمٍ للحدث #' . $oid; $flashKind = 'success';
        }
    }
P,
'view' => <<<'V'
$where = $is_super_admin ? '1=1' : ('e.company_id = ' . (int) $company_id);
$whereBare = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT e.id, e.event_no, e.event_key, e.entity_type, e.entity_id, e.created_at,
            e.consumers_declared, e.delivered_ok, e.delivered_failed, e.in_dlq, e.seed_tag
       FROM ems_business_events e
      WHERE {$where}
      ORDER BY e.id DESC LIMIT 200"
);
$stats = $conn->query(
    "SELECT COUNT(*) total,
            SUM(delivered_ok > 0) delivered,
            SUM(in_dlq = 1) dlq,
            SUM(delivered_ok = 0 AND delivered_failed = 0) pending
       FROM ems_business_events WHERE {$whereBare}"
)->fetch_assoc();
$PAGE_TITLE = 'صندوق الأحداث الصادر';
$TILES = array(
    array('وقائعُ منشورة', (int) $stats['total']),
    array('سُلّمت لمستهلكٍ واحدٍ فأكثر', (int) $stats['delivered']),
    array('بانتظار التسليم', (int) $stats['pending']),
    array('في صندوق الموتى', (int) $stats['dlq']),
);
$COLS = array('#','رقم الحدث','رمز الحدث','نوع الواقعة','معرّفها','وقت النشر','مستهلكون معلَنون','نجح','فشل','صندوق الموتى','وسم البذر');
include __DIR__ . '/../includes/eng01_screen_view.php';
V,
),
array(
'dir' => 'Governance', 'file' => 'bus_deliveries.php',
'title' => 'تسليمات الأحداث وحالاتها',
'stage' => 'المرحلة «نراقب الأحداثَ والمهام» · مجموعة «ناقلُ الأحداث»',
'actions' => array('bus.deliver', 'bus.dlq.decide'),
'services' => array('Bus/EventDeliveryWorker.php'),
'post' => <<<'P'
    $w = new \App\Services\Bus\EventDeliveryWorker($conn, 'ui-' . $uid);
    if ($__action === 'deliver') {
        $r = $w->deliverOne((int) ($_POST['delivery_id'] ?? 0));
        $flash = $r === null ? 'لم يُلتقط — سبقه عاملٌ آخر' : ('نتيجةُ التسليم: ' . $r);
        $flashKind = ($r === 'processed') ? 'success' : ($r === null ? 'info' : 'error');
    } elseif ($__action === 'dlq_decide') {
        $r = $w->decideDlq((int) ($_POST['delivery_id'] ?? 0), (string) ($_POST['decision'] ?? ''),
                           (string) ($_POST['reason'] ?? ''), $uid);
        $flash = $r['reason']; $flashKind = $r['ok'] ? 'success' : 'error';
    } elseif ($__action === 'release_stale') {
        $n = $w->releaseStale(3600);
        $flash = 'حُرّر ' . $n . ' تسليمًا عالقًا'; $flashKind = 'success';
    }
P,
'view' => <<<'V'
$where = $is_super_admin ? '1=1' : ('d.company_id = ' . (int) $company_id);
$whereBare = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT d.id, e.event_key, d.consumer_key, d.state, d.attempt_no,
            d.next_attempt_at, d.processed_at, d.result_ref, d.fail_code,
            LEFT(COALESCE(d.fail_text,''), 60) fail_text, d.seed_tag
       FROM ems_event_deliveries d
  LEFT JOIN ems_business_events e ON e.id = d.outbox_id
      WHERE {$where}
      ORDER BY d.id DESC LIMIT 200"
);
$stats = $conn->query(
    "SELECT SUM(state='published') published, SUM(state='claimed') claimed,
            SUM(state='processing') processing, SUM(state='processed') processed,
            SUM(state='failed') failed, SUM(state='dlq') dlq
       FROM ems_event_deliveries WHERE {$whereBare}"
)->fetch_assoc();
$PAGE_TITLE = 'تسليمات الأحداث وحالاتها';
$TILES = array(
    array('منشور', (int) $stats['published']), array('ملتقَط', (int) $stats['claimed']),
    array('قيد التنفيذ', (int) $stats['processing']), array('نجح', (int) $stats['processed']),
    array('فشل', (int) $stats['failed']), array('صندوق الموتى', (int) $stats['dlq']),
);
$COLS = array('#','رمز الحدث','المستهلك','الحالة','المحاولة','الإعادة القادمة','وقت النجاح','مرجع الأثر','رمز الفشل','سبب الفشل','وسم البذر');
include __DIR__ . '/../includes/eng01_screen_view.php';
V,
),
array(
'dir' => 'Governance', 'file' => 'bus_board.php',
'title' => 'لوحة الناقل',
'stage' => 'المرحلة «نراقب الأحداثَ والمهام» · مجموعة «ناقلُ الأحداث»',
'actions' => array('bus.board.view'),
'services' => array(),
'post' => "    // شاشةُ قراءةٍ فقط — لا فعلَ كتابةٍ مسجَّلٌ لها (Read Only)\n    \$flash = 'هذه شاشةُ عرضٍ لا تكتب'; \$flashKind = 'info';",
'view' => <<<'V'
$where = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT e.event_key AS 'رمز الحدث',
            COUNT(*) AS 'وقائع',
            SUM(e.delivered_ok) AS 'تسليمات ناجحة',
            SUM(e.delivered_failed) AS 'تسليمات فاشلة',
            SUM(e.in_dlq) AS 'في صندوق الموتى',
            MAX(e.created_at) AS 'آخر نشر'
       FROM ems_business_events e
      WHERE {$where}
      GROUP BY e.event_key
      ORDER BY COUNT(*) DESC LIMIT 200"
);
$g = $conn->query(
    "SELECT (SELECT COUNT(*) FROM ems_business_events WHERE {$where}) facts,
            (SELECT COUNT(*) FROM ems_event_deliveries WHERE outbox_id > 0) deliveries,
            (SELECT COUNT(*) FROM ems_event_deliveries WHERE state='processed' AND outbox_id > 0) processed,
            (SELECT COUNT(*) FROM ems_event_deliveries WHERE state='dlq') dlq,
            (SELECT COUNT(DISTINCT event_name) FROM event_consumers WHERE active=1) subs"
)->fetch_assoc();
$PAGE_TITLE = 'لوحة الناقل';
$TILES = array(
    array('وقائعُ الجذر', (int) $g['facts']),
    array('تسليماتٌ حقيقية', (int) $g['deliveries']),
    array('سُلّمت بنجاح', (int) $g['processed']),
    array('صندوق الموتى', (int) $g['dlq']),
    array('أنواعٌ لها مشتركون', (int) $g['subs']),
);
$COLS = null; // أعمدةُ الاستعلامِ بأسمائِها
include __DIR__ . '/../includes/eng01_screen_view.php';
V,
),
// ─────────────────────────────── طابورُ المهام ───────────────────────────────
array(
'dir' => 'Governance', 'file' => 'job_queue.php',
'title' => 'طابور المهام',
'stage' => 'المرحلة «نراقب الأحداثَ والمهام» · مجموعة «طابورُ المهام»',
'actions' => array('job.enqueue', 'job.claim'),
'services' => array('Queue/JobQueueService.php', 'Queue/JobScheduleService.php'),
'post' => <<<'P'
    if ($__action === 'release_locks') {
        $n = \App\Services\Queue\JobQueueService::releaseExpiredLocks($conn);
        $flash = 'حُرّر ' . $n . ' قفلًا منقضيًا'; $flashKind = 'success';
    } elseif ($__action === 'materialize') {
        $r = \App\Services\Queue\JobScheduleService::materialize($conn);
        $flash = 'أُدرج ' . $r['enqueued'] . ' وتُخطّي ' . $r['skipped'];
        $flashKind = 'success';
    }
P,
'view' => <<<'V'
$where = $is_super_admin ? '1=1' : ('q.company_id = ' . (int) $company_id);
$whereBare = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT q.job_id, q.job_type, q.state, q.source, q.source_ref, q.worker_id,
            q.claimed_at, q.lock_expires_at, q.attempts, q.max_attempts,
            q.fail_code, LEFT(COALESCE(q.last_error,''), 60) last_error, q.created_at, q.seed_tag
       FROM ems_job_queue q
      WHERE {$where}
      ORDER BY q.job_id DESC LIMIT 200"
);
$stats = $conn->query(
    "SELECT SUM(state='queued') queued, SUM(state='claimed') claimed,
            SUM(state IN ('processing','running')) running, SUM(state='done') done,
            SUM(state IN ('failed','dead','dlq')) failed,
            SUM(state='claimed' AND lock_expires_at < NOW(3)) stuck
       FROM ems_job_queue WHERE {$whereBare}"
)->fetch_assoc();
$PAGE_TITLE = 'طابور المهام';
$TILES = array(
    array('في الطابور', (int) $stats['queued']), array('ملتقَطة', (int) $stats['claimed']),
    array('قيد التنفيذ', (int) $stats['running']), array('تمّت', (int) $stats['done']),
    array('فاشلة أو معزولة', (int) $stats['failed']),
    array('مقفولةٌ منتهيةُ المهلة (CK-14)', (int) $stats['stuck']),
);
$COLS = array('#','النوع','الحالة','المصدر','مرجع المصدر','العامل','وقت الالتقاط','انتهاء القفل','محاولات','الحد','رمز الفشل','آخر خطأ','أُنشئت','وسم البذر');
include __DIR__ . '/../includes/eng01_screen_view.php';
V,
),
array(
'dir' => 'Governance', 'file' => 'job_schedule.php',
'title' => 'جدولة المهام الدورية',
'stage' => 'المرحلة «نراقب الأحداثَ والمهام» · مجموعة «طابورُ المهام»',
'actions' => array('job.schedule.define'),
'services' => array('Queue/JobScheduleService.php'),
'post' => <<<'P'
    if ($__action === 'define') {
        $r = \App\Services\Queue\JobScheduleService::define($conn, array(
            'job_type'            => $_POST['job_type'] ?? '',
            'cron_expr'           => $_POST['cron_expr'] ?? '',
            'max_runtime_seconds' => $_POST['max_runtime_seconds'] ?? 600,
            'alert_after_seconds' => $_POST['alert_after_seconds'] ?? 3600,
            'owner_role_id'       => $_POST['owner_role_id'] ?? 0,
            'is_active'           => isset($_POST['is_active']) ? 1 : 0,
            'company_id'          => 0,
            'created_by'          => $uid,
        ));
        $flash = $r['reason']; $flashKind = $r['ok'] ? 'success' : 'error';
    } elseif ($__action === 'alert_stalled') {
        $n = \App\Services\Queue\JobScheduleService::alertStalled($conn);
        $flash = 'رُفع ' . $n . ' إنذارَ توقف'; $flashKind = 'success';
    }
P,
'view' => <<<'V'
$rows = $conn->query(
    "SELECT s.job_type, s.cron_expr, s.max_runtime_seconds, s.alert_after_seconds,
            s.owner_role_id, s.last_success_at,
            TIMESTAMPDIFF(MINUTE, s.last_success_at, NOW()) AS since_min,
            s.is_active, s.replaces_manual
       FROM ems_job_schedule s ORDER BY s.job_type"
);
$stalled = \App\Services\Queue\JobScheduleService::stalled($conn);
$tot = (int) $conn->query('SELECT COUNT(*) FROM ems_job_schedule WHERE is_active=1')->fetch_row()[0];
$repl = (int) $conn->query("SELECT COUNT(*) FROM ems_job_schedule WHERE replaces_manual IS NOT NULL AND replaces_manual NOT LIKE '—%'")->fetch_row()[0];
$PAGE_TITLE = 'جدولة المهام الدورية';
$TILES = array(
    array('جدولاتٌ نشطة', $tot),
    array('ألغت أمرًا يدويًّا', $repl),
    array('متوقفةٌ فوق مهلة الإنذار (CK-15)', count($stalled)),
);
$COLS = array('النوع','التعبير الزمني','أقصى زمن تشغيل','مهلة الإنذار','الدور المسؤول','آخر نجاح','منذ (دقيقة)','مفعَّلة','الأمر اليدوي الملغى');
include __DIR__ . '/../includes/eng01_screen_view.php';
V,
),
// ─────────────────────────────── الاستعادة ───────────────────────────────
array(
'dir' => 'Governance', 'file' => 'dr_restore.php',
'title' => 'الاستعادة ومحضرها',
'stage' => 'المرحلة «نراقب الأحداثَ والمهام» · مجموعة «الاستعادة»',
'actions' => array('dr.restore.drill'),
'services' => array('Dr/RestoreDrillService.php'),
'post' => <<<'P'
    if ($__action === 'record_drill') {
        $r = \App\Services\Dr\RestoreDrillService::record($conn, array(
            'company_id'               => $company_id,
            'drill_kind'               => $_POST['drill_kind'] ?? 'pitr',
            'started_at'               => $_POST['started_at'] ?? '',
            'finished_at'              => $_POST['finished_at'] ?? '',
            'target_point'             => $_POST['target_point'] ?? '',
            'rpo_target_minutes'       => $_POST['rpo_target_minutes'] ?? 15,
            'rpo_actual_minutes'       => $_POST['rpo_actual_minutes'] ?? '',
            'rto_actual_seconds'       => $_POST['rto_actual_seconds'] ?? '',
            'rows_before'              => $_POST['rows_before'] ?? 0,
            'rows_after_expected_gone' => $_POST['rows_after_expected_gone'] ?? 0,
            'rows_after_actual'        => $_POST['rows_after_actual'] ?? 0,
            'verdict'                  => $_POST['verdict'] ?? '',
            'operator_note'            => $_POST['operator_note'] ?? '',
            'runbook_ref'              => 'docs/ENG01_RESTORE_RUNBOOK_ar.md',
            'actor'                    => $uid,
            'actor_role'               => $role_id,
        ));
        $flash = $r['ok'] ? ('سُجّل المحضر ' . $r['drill_no']) : $r['reason'];
        $flashKind = $r['ok'] ? 'success' : 'error';
    }
P,
'view' => <<<'V'
$where = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT drill_no, drill_kind, started_at, finished_at, target_point,
            rpo_target_minutes, rpo_actual_minutes, rto_actual_seconds,
            rows_before, rows_after_expected_gone, rows_after_actual, verdict, runbook_ref
       FROM dr_drills WHERE {$where} ORDER BY id DESC LIMIT 100"
);
$logBin = $conn->query("SHOW VARIABLES LIKE 'log_bin'")->fetch_assoc();
$ret = $conn->query("SELECT @@binlog_expire_logs_seconds/86400")->fetch_row()[0];
$days = \App\Services\Dr\RestoreDrillService::daysSinceLastPass($conn, $company_id ?: 1);
$PAGE_TITLE = 'الاستعادة ومحضرها';
$TILES = array(
    array('سجلُّ الثنائيات (CK-16)', $logBin ? $logBin['Value'] : '—'),
    array('مدةُ الاحتفاظ (يومًا)', round((float) $ret, 1)),
    array('أيامٌ منذ آخر تجربةٍ ناجحة', $days === null ? 'لم تُجرَّب' : $days),
);
$COLS = array('رقم المحضر','النوع','البدء','الانتهاء','نقطة الاستعادة','هدف RPO (د)','RPO المقيس (د)','RTO (ث)','صفوف قبل','يجب ألا تعود','عادت فعلًا','الحكم','المحضر');
include __DIR__ . '/../includes/eng01_screen_view.php';
V,
),
// ─────────────────────────────── الأصولُ والإهلاك ───────────────────────────────
array(
'dir' => 'Finance', 'file' => 'asset_hours_link.php',
'title' => 'ربط الأصل بساعات تشغيله',
'stage' => 'خارج GOV-24 (سايدبار الحوكمة) — وموضعُها الحيُّ: مجموعة «الإهلاك» لدور المالية',
'actions' => array('asset.hours.link'),
'services' => array('Assets/AssetHoursService.php'),
'post' => <<<'P'
    if ($__action === 'link') {
        $r = \App\Services\Assets\AssetHoursService::link($conn, array(
            'company_id'        => $company_id,
            'equipment_id'      => $_POST['equipment_id'] ?? 0,
            'period'            => $_POST['period'] ?? '',
            'asset_id'          => $_POST['asset_id'] ?? '',
            'depr_method'       => $_POST['depr_method'] ?? '',
            'useful_life_hours' => $_POST['useful_life_hours'] ?? '',
            'rate_per_hour'     => $_POST['rate_per_hour'] ?? '',
            'actor'             => $uid,
        ));
        $flash = $r['ok']
            ? ('رُبط — ساعاتُ التشغيل من القيد اليومي: ' . $r['hours'] . ' · الملكية: ' . $r['owner_type'])
            : $r['reason'];
        $flashKind = $r['ok'] ? 'success' : 'error';
    } elseif ($__action === 'refresh_all') {
        $n = \App\Services\Assets\AssetHoursService::refreshAll($conn, $company_id);
        $flash = 'رُدمت الساعاتُ لـ' . $n . ' صفَّ معدة-شهر'; $flashKind = 'success';
    }
P,
'view' => <<<'V'
$where = $is_super_admin ? '1=1' : ('a.company_id = ' . (int) $company_id);
$whereBare = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT a.rec_id, a.machine_code, a.period, a.owner_type, a.asset_id, a.depr_method,
            a.useful_life_hours, a.depreciation_per_hour, a.hours_from_shifts,
            a.hours_undepreciated, a.depreciation_amount, a.depr_reversed_amount, a.depr_reversal_ref
       FROM asset_hour_reconciliations a
      WHERE {$where} AND a.hours_from_shifts > 0
      ORDER BY a.period DESC, a.machine_code LIMIT 200"
);
$s = $conn->query(
    "SELECT COUNT(*) n, SUM(owner_type='supplier') sup,
            SUM(hours_from_shifts) hrs,
            SUM(owner_type='company' AND depreciation_amount IS NULL AND hours_from_shifts>0) undep
       FROM asset_hour_reconciliations WHERE {$whereBare}"
)->fetch_assoc();
$PAGE_TITLE = 'ربط الأصل بساعات تشغيله';
$TILES = array(
    array('صفوفُ معدة-شهر', (int) $s['n']),
    array('منها معداتُ موردين', (int) $s['sup']),
    array('ساعاتُ تشغيلٍ مقيسة', round((float) $s['hrs'], 1)),
    array('عملت بلا إهلاك (CK-17)', (int) $s['undep']),
);
$COLS = array('#','كود المعدة','الفترة','الملكية','الأصل','طريقة الإهلاك','العمر (ساعة)','معدل الساعة','ساعات التشغيل','ساعات بلا إهلاك','الإهلاك','مبلغ معكوس','مرجع العكس');
include __DIR__ . '/../includes/eng01_screen_view.php';
V,
),
array(
'dir' => 'Finance', 'file' => 'depr_run.php',
'title' => 'احتساب إهلاك الفترة',
'stage' => 'خارج GOV-24 (سايدبار الحوكمة) — وموضعُها الحيُّ: مجموعة «الإهلاك» لدور المالية',
'actions' => array('depr.run', 'depr.reverse'),
'services' => array('Assets/DepreciationRunService.php'),
'post' => <<<'P'
    if ($__action === 'run') {
        $r = \App\Services\Assets\DepreciationRunService::run(
            $conn, $company_id, (string) ($_POST['period'] ?? ''), $uid);
        $flash = $r['summary']; $flashKind = $r['ok'] ? 'success' : 'error';
    } elseif ($__action === 'reverse') {
        $r = \App\Services\Assets\DepreciationRunService::reverse($conn, array(
            'rec_id' => $_POST['rec_id'] ?? 0,
            'reason' => $_POST['reason'] ?? '',
            'actor'  => $uid,
        ));
        $flash = $r['ok'] ? ('عُكس ' . $r['reversed'] . ' بمرجعٍ ' . $r['ref']) : $r['reason'];
        $flashKind = $r['ok'] ? 'success' : 'error';
    }
P,
'view' => <<<'V'
$where = $is_super_admin ? '1=1' : ('a.company_id = ' . (int) $company_id);
$whereBare = $is_super_admin ? '1=1' : ('company_id = ' . (int) $company_id);
$rows = $conn->query(
    "SELECT a.rec_id, a.machine_code, a.period, a.owner_type, a.depr_method,
            a.hours_from_shifts, a.depreciation_per_hour, a.depreciation_amount,
            a.journal_ref, a.depr_reversed_amount, a.depr_reversal_ref, a.depr_reversed_at
       FROM asset_hour_reconciliations a
      WHERE {$where}
        AND (a.depreciation_amount IS NOT NULL OR a.depr_reversed_amount IS NOT NULL)
      ORDER BY a.period DESC, a.rec_id DESC LIMIT 200"
);
$s = $conn->query(
    "SELECT SUM(depreciation_amount) live, SUM(depr_reversed_amount) rev,
            SUM(owner_type='supplier' AND depreciation_amount IS NOT NULL) bad
       FROM asset_hour_reconciliations WHERE {$whereBare}"
)->fetch_assoc();
$PAGE_TITLE = 'احتساب إهلاك الفترة';
$TILES = array(
    array('إهلاكٌ قائم', number_format((float) $s['live'], 2)),
    array('مبالغُ معكوسةٌ بمرجعها', number_format((float) $s['rev'], 2)),
    array('إهلاكٌ على معدةِ مورد (CK-18)', (int) $s['bad']),
);
$COLS = array('#','كود المعدة','الفترة','الملكية','الطريقة','ساعات التشغيل','معدل الساعة','الإهلاك','مرجع القيد','مبلغ معكوس','مرجع العكس','وقت العكس');
include __DIR__ . '/../includes/eng01_screen_view.php';
V,
),
);

$made = 0; $skipped = 0;
foreach ($SCREENS as $s) {
    $path = $ROOT . '/' . $s['dir'] . '/' . $s['file'];
    if (file_exists($path) && !$force) { echo "   · موجودٌ سلفًا: {$s['dir']}/{$s['file']}\n"; $skipped++; continue; }
    file_put_contents($path, build($s));
    echo "   ✔ {$s['dir']}/{$s['file']}\n";
    $made++;
}
echo "\n   بُنيت: $made · متروكة: $skipped\n";
