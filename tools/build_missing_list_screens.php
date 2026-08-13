<?php
/**
 * tools/build_missing_list_screens.php — مُولِّدُ شاشاتِ القراءةِ الست (مرّةً)
 * ═══════════════════════════════════════════════════════════════════════════
 * ⇐ INJ-0134 · INJ-0156 · INJ-0164 · INJ-0268 · INJ-0354 · INJ-0580
 *
 * ستُّ شاشاتٍ يطلبها السجلُّ **ولكلٍّ منها جدولٌ حيٌّ يسندها** — فتُبنى عارضاتٍ
 * على بيانٍ قائم، لا نماذجَ تُخترع.
 *
 * ── وخمسٌ **لا تُبنى** ولن يُلفَّق لها نموذج ────────────────────────────────
 * `disposal.php` · `fin_exit.php` · `fin_variance.php` · `fin_idle.php` ·
 * `site_approval.php` — جُسَّت القاعدةُ فلا جدولَ لأيٍّ منها ولا لمرادفاتِها.
 * وبناؤها يعني **اختراعَ نموذجِ بيانات** (إخراجٌ من الخدمة · خروجٌ من الملكية ·
 * محضرُ اعتمادِ موقع) — وهو تلفيقٌ تمنعه الوثيقةُ نصًّا، وسابقتُه INJ-0416
 * الموقوفُ بوسمِ «مصدرٌ مفقود». فتبقى مُعلَنةً مفتوحةً بسببِها المكتوب.
 *
 * ── وكلُّ شاشةٍ هنا ────────────────────────────────────────────────────────
 * ◆ **قارئةٌ محضةٌ** — لا فعلَ كاتبًا؛ فشاشةُ عرضٍ تكتب تُنشئ مسارًا ثانيًا
 *   يتفرّق عن مسارِ الوحدةِ الأصليّ.
 * ◆ **بحارسِ سجلِّ الشاشات** — والتسجيلُ حراسةٌ لا توثيق (fail-open بدونه).
 * ◆ **وتميّز «تعذّر السؤال» من «لا صفوف»** — `config.php` يضبط mysqli على
 *   عدمِ الرمي، فعمودٌ ناقصٌ يعود `false` صامتًا ويُقرأ «الجدولُ خالٍ».
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (php_sapi_name() !== 'cli') { exit("CLI فقط\n"); }
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');
$ROOT = dirname(__DIR__);
require_once $ROOT . '/includes/env.php';
$host = ems_env('DB_HOST'); $port = 3306;
if (strpos($host, ':') !== false) { list($host, $port) = explode(':', $host); $port = (int) $port; }
mysqli_report(MYSQLI_REPORT_OFF);
$conn = new mysqli($host, ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), $port);
if ($conn->connect_errno) { exit("تعذّر الاتصال: {$conn->connect_error}\n"); }
$conn->set_charset('utf8mb4');
$force = in_array('--force', $argv, true);

/* اسمُ الملفِّ ⇒ [الجدول, العنوان, الأيقونة, الأعمدة(sql=>عنوان), شرطٌ, ترتيب] */
$S = array(
'Fleet/readiness_cert.php' => array(
    'table' => 'readiness_lines', 'title' => 'شهاداتُ جاهزيةِ المعدات', 'icon' => 'fa fa-certificate',
    'cols' => array('readiness_code' => 'رمزُ الجاهزية', 'name' => 'البند', 'contract_ref' => 'العقد',
                    'required' => 'المطلوب', 'available' => 'المتاح', 'state' => 'الحال',
                    'gap_note' => 'ملاحظةُ الفجوة', 'created_at' => 'أُنشئ'),
    'where' => 'is_deleted = 0', 'order' => 'id DESC',
    'note' => 'الجاهزيةُ سطرٌ لكلِّ بندٍ مطلوبٍ بعقدٍ — والفجوةُ فرقُ «المطلوب» عن «المتاح»، تُقرأ ولا تُصحَّح من هنا.',
),
'Procurement/warehouses.php' => array(
    'table' => 'proc_warehouse', 'title' => 'المخازنُ وأنواعُها', 'icon' => 'fa fa-warehouse',
    'cols' => array('code' => 'الرمز', 'name' => 'المخزن', 'type' => 'النوع',
                    'location' => 'الموقع', 'status' => 'الحال', 'notes' => 'ملاحظات',
                    'created_at' => 'أُنشئ'),
    'where' => 'is_deleted = 0', 'order' => 'code',
    'note' => 'سجلُّ المخازنِ المرجعيّ — والإضافةُ والتعديلُ من بياناتِ المشترياتِ المرجعية، فمسارا تعريفٍ يتفرّقان أسوأُ من شاشةٍ ناقصة.',
),
'Operations/monthly_plan.php' => array(
    'table' => 'scr_op_monthly', 'title' => 'الخطةُ الشهريةُ للتشغيل', 'icon' => 'fa fa-calendar-days',
    'cols' => array('month_ref' => 'الشهر', 'code_operator' => 'المشغّل', 'equipment_name' => 'المعدة',
                    'site_name' => 'الموقع', 'days_work' => 'أيامُ عمل', 'hours_operations' => 'ساعاتُ تشغيل',
                    'hours_standby' => 'ساعاتُ استعداد', 'pct_achievement' => 'نسبةُ الإنجاز',
                    'status_label' => 'الحال'),
    'where' => '1=1', 'order' => 'month_ref DESC, id DESC',
    'note' => 'الخطةُ الشهريةُ مقابلَ المنفَّذ — وخطةُ الغدِ اليوميةُ في daily_plan.php وهي غيرُ هذه.',
),
'Suppliers/quota_approval_minutes.php' => array(
    'table' => 'substitute_coverages', 'title' => 'محاضرُ اعتمادِ وحداتِ المورد', 'icon' => 'fa fa-file-signature',
    'cols' => array('cov_id' => '#', 'level' => 'الدرجة', 'covered_seat_id' => 'المقعدُ المغطّى',
                    'reason_code' => 'السبب', 'valid_from' => 'من', 'valid_to' => 'إلى',
                    'estimated_hours' => 'ساعاتٌ مقدَّرة', 'state' => 'الحال', 'approvals_ref' => 'مرجعُ الاعتماد'),
    'where' => '1=1', 'order' => 'cov_id DESC', 'pk' => 'cov_id',
    'note' => 'التغطيةُ البديلةُ باعتمادين (CAP-01) — والطلبُ يُنشأ من Operations/swap_request.php والاعتمادُ من صندوقِ الاعتمادِ الجامع؛ وهذه الشاشةُ محضرُها.',
),
'Tickets/ticket_kpi.php' => array(
    'table' => 'tickets', 'title' => 'مؤشراتُ البلاغات', 'icon' => 'fa fa-chart-simple',
    'cols' => array('ticket_no' => 'رقمُ البلاغ', 'stage' => 'المرحلة', 'priority' => 'الأولوية',
                    'business_impact' => 'الأثرُ التشغيلي', 'call_date' => 'تاريخُ البلاغ',
                    'resolution_due_at' => 'المهلة', 'close_date' => 'أُغلق'),
    'where' => '1=1', 'order' => 'id DESC',
    'note' => 'المؤشراتُ تُحسب على البلاغاتِ الحيّةِ لا على جدولٍ موازٍ — فرقمانِ لشيءٍ واحدٍ يتفرّقان.',
),
'Tickets/my_tickets.php' => array(
    'table' => 'tickets', 'title' => 'بلاغاتي', 'icon' => 'fa fa-inbox',
    'cols' => array('ticket_no' => 'رقمُ البلاغ', 'stage' => 'المرحلة', 'priority' => 'الأولوية',
                    'call_date' => 'التاريخ', 'resolution_due_at' => 'المهلة'),
    'where' => 'reporter_user_id = {UID}', 'order' => 'id DESC',
    'note' => 'ما رفعتَه أنت — والشرطُ على المُبلِّغِ لا على الإدارة؛ فبلاغاتُ إدارتِك في dept_inbox.php.',
),
);

echo "══ مُولِّدُ شاشاتِ القراءةِ — " . count($S) . " شاشة\n\n";
$made = 0; $skip = 0;
foreach ($S as $rel => $d) {
    $dir = dirname($rel);
    if (!is_dir($ROOT . '/' . $dir)) { echo "   ✘ لا مجلدَ {$dir}\n"; continue; }
    $path = $ROOT . '/' . $rel;
    if (is_file($path) && !$force) { echo "   ○ قائمٌ — {$rel}\n"; $skip++; continue; }
    /* جسٌّ: الجدولُ والأعمدةُ موجودةٌ فعلًا — فشاشةٌ على عمودٍ وهميٍّ تُصيَّر خاويةً */
    $have = array();
    $r = $conn->query('SHOW COLUMNS FROM `' . $d['table'] . '`');
    while ($r && ($x = $r->fetch_assoc())) { $have[$x['Field']] = true; }
    if (!$have) { echo "   ✘ لا جدولَ {$d['table']}\n"; continue; }
    foreach (array_keys($d['cols']) as $cn) {
        if (!isset($have[$cn])) { fwrite(STDERR, "✘ {$rel}: العمودُ «{$cn}» غيرُ موجودٍ في {$d['table']}\n"); exit(2); }
    }
    $pk    = isset($d['pk']) ? $d['pk'] : 'id';
    /* كلُّ الوجهاتِ بعمقٍ واحدٍ — ويُتحقَّق لا يُفترض؛ ولو تغيّر عمقُ وجهةٍ لتوقّف
       المُولِّدُ بدل أن يُخرج مسارًا لا يُحَل. وثبتُ '../' يُبقي حارسَ
       `nfr_infra_test` قادرًا على حلِّ المسارِ داخلَ نصِّ المُولِّدِ نفسِه. */
    if (substr_count($rel, '/') !== 1) { fwrite(STDERR, "✘ {$rel}: عمقٌ غيرُ مدعوم
"); exit(2); }
    $up    = '../';
    $sel   = implode(', ', array_map(function ($c) { return 't.`' . $c . '`'; }, array_keys($d['cols'])));
    $where = str_replace('{UID}', "' . \$uid . '", $d['where']);
    $th    = ''; $td = '';
    foreach ($d['cols'] as $c => $lbl) {
        $th .= "        <th>" . $lbl . "</th>\n";
        $td .= "          <td><?php echo htmlspecialchars((string) \$x['{$c}'], ENT_QUOTES, 'UTF-8'); ?></td>\n";
    }
    $note = $d['note'];
    $src = <<<PHP
<?php
/**
 * {$rel} — {$d['title']}
 * ═══════════════════════════════════════════════════════════════════════════
 * شاشةٌ يطلبها السجلُّ الجامعُ ولها جدولٌ حيٌّ يسندها (`{$d['table']}`).
 *
 * ◆ **قارئةٌ محضةٌ** — لا فعلَ كاتبًا فيها؛ فشاشةُ عرضٍ تكتب تُنشئ مسارًا ثانيًا
 *   يتفرّق عن مسارِ الوحدةِ الأصليّ عند أوّلِ تعديلٍ في القاعدة.
 * ◆ **وتميّز «تعذّر السؤال» من «لا صفوف»** — `config.php` يضبط mysqli على عدمِ
 *   الرمي، فعمودٌ ناقصٌ يعود `false` صامتًا فيُقرأ «الجدولُ خالٍ».
 * ◆ {$note}
 * ═══════════════════════════════════════════════════════════════════════════
 */
require_once __DIR__ . '/../includes/session_bootstrap.php';
session_start();
if (!isset(\$_SESSION['user'])) { header('Location: {$up}login.php'); exit(); }
include '{$up}config.php';
require_once __DIR__ . '/{$up}includes/permissions_helper.php';
require_once __DIR__ . '/{$up}includes/screen_contract.php';

\$current_role   = strval(\$_SESSION['user']['role'] ?? '');
\$is_super_admin = (\$current_role === '-1');
\$company_id     = intval(\$_SESSION['user']['company_id'] ?? 0);
\$uid            = intval(\$_SESSION['user']['id'] ?? 0);
if (!\$is_super_admin && \$company_id <= 0) { header('Location: {$up}login.php'); exit(); }

\$__pp = check_page_permissions(\$conn, '{$rel}');
if (!\$is_super_admin && empty(\$__pp['can_view'])) {
    ems_gov_flash_redirect('{$up}main/dashboard.php', 'لا تملك صلاحية عرض هذه الشاشة', 'GOV-PERM-403', 'الصلاحياتُ يمنحها مدير الصلاحيات');
}
ems_shell_axes(\$__pp);

\$rows = array(); \$failed = false;
\$sql = "SELECT {$sel} FROM `{$d['table']}` t
         WHERE t.company_id = ? AND ({$where})
         ORDER BY t.{$d['order']} LIMIT 500";
\$st = \$conn->prepare(\$sql);
if (!\$st) { \$failed = true; }
else {
    \$st->bind_param('i', \$company_id);
    if (!\$st->execute()) { \$failed = true; }
    else { \$res = \$st->get_result(); while (\$res && (\$x = \$res->fetch_assoc())) { \$rows[] = \$x; } }
    \$st->close();
}

\$page_title = '{$d['title']}';
include '{$up}inheader.php';
include '{$up}insidebar.php';
if (isset(\$conn)) { ems_screen_about_auto(\$conn); }
?>
<div class="main" dir="rtl">
<?php
\$header_icon = '{$d['icon']}';
\$header_title_html = htmlspecialchars('{$d['title']}', ENT_QUOTES, 'UTF-8');
\$header_actions = array();
\$header_back = false;
include __DIR__ . '/{$up}includes/page_header.php';
?>
  <?php if (\$failed): ?>
  <div class="alert alert-danger" style="margin:10px 0">
    <strong>تعذّرت قراءةُ البيانات.</strong>
    فرقٌ بين «لا صفَّ» و«تعذّر السؤال» — وهذه الثانية.
  </div>
  <?php else: ?>
  <div class="ems-card" style="padding:10px 14px;margin:10px 0;border-inline-start:4px solid #0d6efd;display:inline-block">
    <div style="font-size:.78rem;opacity:.75">صفوفٌ معروضة</div>
    <div style="font-size:1.4rem;font-weight:700"><?php echo number_format(count(\$rows)); ?></div>
  </div>
  <div class="card"><div class="card-body table-responsive">
    <table class="table table-sm table-striped" style="width:100%">
      <thead><tr>
{$th}      </tr></thead>
      <tbody>
      <?php if (!\$rows): ?>
        <tr><td colspan="99" style="text-align:center;opacity:.7">لا صفَّ مسجَّلٌ بعد.</td></tr>
      <?php else: foreach (\$rows as \$x): ?>
        <tr>
{$td}        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <p class="text-muted" style="font-size:.8rem;margin-top:8px">
      قراءةٌ محضة — {$note} وأحدثُ 500 صفٍّ.
    </p>
  </div></div>
  <?php endif; ?>
</div>

PHP;
    file_put_contents($path, $src);
    echo "   ✔ {$rel}  ⇐ {$d['table']}\n";
    $made++;
}
echo "\n── المحصّلة: أُنشئ {$made} · قائمٌ سلفًا {$skip}\n";
echo "   ◆ التسجيلُ بهجرةٍ منفصلة.\n";
