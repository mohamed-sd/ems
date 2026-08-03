<?php
require_once __DIR__ . '/includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'سجل المراجعة';
$current_page = 'audit-log';

// ── Filters ───────────────────────────────────────────────────────────────
$f_action   = trim($_GET['action_type'] ?? '');
$f_date_from = trim($_GET['date_from'] ?? '');
$f_date_to   = trim($_GET['date_to']   ?? '');
$f_q         = trim($_GET['q']         ?? '');
$page        = max(1, intval($_GET['p'] ?? 1));
$per         = 30;
$offset      = ($page - 1) * $per;

// ── Build where ───────────────────────────────────────────────────────────
$wheres = ['1=1'];
if ($f_action !== '') {
    $esc = mysqli_real_escape_string($conn, $f_action);
    $wheres[] = "action_type = '$esc'";
}
if ($f_date_from !== '') {
    $esc = mysqli_real_escape_string($conn, $f_date_from);
    $wheres[] = "DATE(created_at) >= '$esc'";
}
if ($f_date_to !== '') {
    $esc = mysqli_real_escape_string($conn, $f_date_to);
    $wheres[] = "DATE(created_at) <= '$esc'";
}
if ($f_q !== '') {
    $esc = mysqli_real_escape_string($conn, $f_q);
    $wheres[] = "(description LIKE '%$esc%' OR target_name LIKE '%$esc%')";
}
$where = implode(' AND ', $wheres);

// ── Fetch ─────────────────────────────────────────────────────────────────
$logs         = [];
$total_count  = 0;
$table_exists = false;

// عبر بوابة المزوّد العابرة (هـ-1ب) — سجل المنصّة منصّيٌ T_PLATFORM
try {
    $al_pg = ems_platform_db();
    $cnt_q = $al_pg->scopedQuery(array('scope' => array('admin_audit_log' => 'admin_audit_log')),
        "SELECT COUNT(*) AS c FROM admin_audit_log WHERE $where AND {TENANT_SCOPE}");
    if (isset($cnt_q[0]['c'])) {
        $table_exists = true;
        $total_count  = intval($cnt_q[0]['c']);
    }
    $logs = $al_pg->scopedQuery(
        array('scope' => array('l' => 'admin_audit_log'), 'enrich' => array('a' => 'super_admins')),
        "SELECT l.*, a.name AS admin_name
     FROM admin_audit_log l
     LEFT JOIN super_admins a ON l.admin_id = a.id
     WHERE $where AND {TENANT_SCOPE}
     ORDER BY l.created_at DESC
     LIMIT $per OFFSET $offset");
} catch (\Throwable $t) { error_log('admin/audit_log list: ' . $t->getMessage()); }

$total_pages = max(1, (int)ceil($total_count / $per));

// ── Action types for filter ───────────────────────────────────────────────
$action_types = [];
try {
    $al_types = $al_pg->scopedQuery(array('scope' => array('admin_audit_log' => 'admin_audit_log')),
        "SELECT DISTINCT action_type FROM admin_audit_log WHERE 1=1 AND {TENANT_SCOPE} ORDER BY action_type");
    foreach ($al_types as $r) { $action_types[] = $r['action_type']; }
} catch (\Throwable $t) { error_log('admin/audit_log types: ' . $t->getMessage()); }

require_once __DIR__ . '/includes/layout_head.php';
?>

<div class="phead">
    <div>
        <h2>سجل المراجعة</h2>
        <p class="sub">سجل شامل لجميع العمليات على مستوى المنصة</p>
    </div>
    <?php if ($total_count > 0): ?>
    <div class="phead-right">
        <span class="text-muted"><?php echo number_format($total_count); ?> سجل</span>
    </div>
    <?php endif; ?>
</div>

<!-- ── Filters ────────────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:18px;">
    <form method="get" class="filter-bar">
        <div class="input-icon-wrap" style="flex:1;min-width:180px;">
            <i class="fas fa-search"></i>
            <input class="form-ctrl form-ctrl-sm" style="width:100%;padding-right:32px;" name="q"
                   value="<?php echo e($f_q); ?>" placeholder="بحث في الوصف أو الاسم...">
        </div>
        <select name="action_type" class="form-ctrl-sm">
            <option value="">كل الأنواع</option>
            <?php foreach ($action_types as $at): ?>
            <option value="<?php echo e($at); ?>" <?php echo $f_action === $at ? 'selected' : ''; ?>>
                <?php echo e($at); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" class="form-ctrl-sm" value="<?php echo e($f_date_from); ?>" title="من تاريخ">
        <input type="date" name="date_to"   class="form-ctrl-sm" value="<?php echo e($f_date_to); ?>"   title="إلى تاريخ">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-filter"></i> تصفية</button>
        <?php if ($f_q || $f_action || $f_date_from || $f_date_to): ?>
        <a href="<?php echo e(super_admin_url('audit-log')); ?>" class="btn btn-ghost btn-sm">
            <i class="fas fa-times"></i> مسح
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- ── Log table ─────────────────────────────────────────────────────────── -->
<div class="card">
    <?php if (!$table_exists): ?>
    <div class="coming-banner" style="margin:20px;">
        <i class="fas fa-database"></i>
        <h3>جدول سجل المراجعة غير موجود بعد</h3>
        <p>قم بتنفيذ <code>database/admin_saas_tables.sql</code> لإنشاء الجداول اللازمة.</p>
    </div>
    <?php elseif (empty($logs)): ?>
    <div class="empty-state">
        <i class="fas fa-scroll"></i>
        <p>لا توجد سجلات تطابق الفلتر المحدد</p>
    </div>
    <?php else: ?>
    <div class="tbl-wrap">
        <table>
            <thead>
                <tr>
                    <th>التاريخ والوقت</th>
                    <th>المسؤول</th>
                    <th>نوع العملية</th>
                    <th>العنصر المستهدف</th>
                    <th>الوصف</th>
                    <th>عنوان IP</th>
                    <!-- CMP-03 ⑤ الأعمدة الوظيفية بتصميم المستند — الخلايا يحشوها ui-unification.js حتى ربط المصدر -->
                    <th class="ems-fn-th" data-fn="1">رقم المحاولة</th>
                    <th class="ems-fn-th" data-fn="1">المستخدم</th>
                    <th class="ems-fn-th" data-fn="1">الصفة</th>
                    <th class="ems-fn-th" data-fn="1">الشاشة</th>
                    <th class="ems-fn-th" data-fn="1">الفعل المحاول</th>
                    <th class="ems-fn-th" data-fn="1">الحارس المانع</th>
                    <th class="ems-fn-th" data-fn="1">سبب المنع</th>
                    <th class="ems-fn-th" data-fn="1">رمز الاستجابة</th>
                    <th class="ems-fn-th" data-fn="1">تكرار المحاولة</th>
                    <th class="ems-fn-th" data-fn="1">هل طُلب استثناء؟</th>
                    <th class="ems-fn-th" data-fn="1">رقم الاستثناء</th>
                    <th class="ems-fn-th" data-fn="1">إجراء الحوكمة</th>
                    <!-- CMP-03 ②③④ طبقة الحوكمة المشتركة — الخلايا يحشوها ui-unification.js -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صفَّ بلا كيانٍ مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="status" data-slice="1" title="حالة المستند في دورته">الحالة</th>
                    </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log):
                    $at = $log['action_type'] ?? 'other';
                    $action_colors = [
                        'create'   => 'bg-green',
                        'update'   => 'bg-blue',
                        'delete'   => 'bg-red',
                        'approve'  => 'bg-green',
                        'reject'   => 'bg-red',
                        'suspend'  => 'bg-orange',
                        'activate' => 'bg-green',
                        'login'    => 'bg-blue',
                        'logout'   => 'bg-gray',
                    ];
                    $at_color = $action_colors[$at] ?? 'bg-gray';
                ?>
                <tr>
                    <td class="text-muted" style="white-space:nowrap;">
                        <?php echo e(date('d/m/Y', strtotime($log['created_at']))); ?><br>
                        <span style="font-size:0.76rem;"><?php echo e(date('H:i:s', strtotime($log['created_at']))); ?></span>
                    </td>
                    <td style="font-weight:600;"><?php echo e($log['admin_name'] ?? '—'); ?></td>
                    <td><span class="badge <?php echo $at_color; ?>"><?php echo e($at); ?></span></td>
                    <td><?php echo e($log['target_name'] ?? '—'); ?></td>
                    <td style="max-width:260px;font-size:0.83rem;"><?php echo e($log['description'] ?? '—'); ?></td>
                    <td class="text-muted" style="font-size:0.8rem;"><?php echo e($log['ip_address'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-top:1px solid var(--line);">
        <span class="text-muted">الصفحة <?php echo $page; ?> من <?php echo $total_pages; ?></span>
        <div class="flex" style="flex-wrap:wrap;gap:6px;">
            <?php
            $qs = http_build_query(array_filter(['q'=>$f_q,'action_type'=>$f_action,'date_from'=>$f_date_from,'date_to'=>$f_date_to]));
            $prev = max(1, $page-1);
            $next = min($total_pages, $page+1);
            ?>
            <a href="?p=<?php echo $prev; ?>&<?php echo $qs; ?>" class="btn btn-sm btn-ghost"><i class="fas fa-angle-right"></i></a>
            <?php for ($pg = max(1,$page-2); $pg <= min($total_pages,$page+2); $pg++): ?>
            <a href="?p=<?php echo $pg; ?>&<?php echo $qs; ?>"
               class="btn btn-sm <?php echo $pg === $page ? 'btn-primary' : 'btn-ghost'; ?>"
               style="min-width:36px;justify-content:center;"><?php echo $pg; ?></a>
            <?php endfor; ?>
            <a href="?p=<?php echo $next; ?>&<?php echo $qs; ?>" class="btn btn-sm btn-ghost"><i class="fas fa-angle-left"></i></a>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>
