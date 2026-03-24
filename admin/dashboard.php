<?php
require_once __DIR__ . '/includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'لوحة التحكم';
$current_page = 'dashboard';

// ── EMS counts (always available) ──────────────────────────────────────────
function _dash_count($conn, $sql) {
    $r = @mysqli_query($conn, $sql);
    if ($r && ($row = mysqli_fetch_assoc($r))) return intval($row['c']);
    return 0;
}
$cnt_users     = _dash_count($conn, "SELECT COUNT(*) AS c FROM users");
$cnt_projects  = _dash_count($conn, "SELECT COUNT(*) AS c FROM project WHERE status=1");
$cnt_equip     = _dash_count($conn, "SELECT COUNT(*) AS c FROM equipments WHERE status=1");
$cnt_suppliers = _dash_count($conn, "SELECT COUNT(*) AS c FROM suppliers WHERE status=1");

// ── SaaS counts (tables may not exist yet — suppressed) ────────────────────
$cnt_companies  = _dash_count($conn, "SELECT COUNT(*) AS c FROM admin_companies WHERE status='active'");
$cnt_pending    = _dash_count($conn, "SELECT COUNT(*) AS c FROM admin_subscription_requests WHERE status='pending'");

// ── Recent subscription requests (stub) ────────────────────────────────────
$recent_requests = [];
$rr = @mysqli_query($conn, "SELECT r.*, p.plan_name FROM admin_subscription_requests r LEFT JOIN admin_subscription_plans p ON r.plan_id=p.id ORDER BY r.created_at DESC LIMIT 5");
if ($rr) { while ($row = mysqli_fetch_assoc($rr)) $recent_requests[] = $row; }

require_once __DIR__ . '/includes/layout_head.php';
?>

<!-- ── Welcome header ──────────────────────────────────────────────────── -->
<div class="phead">
    <div>
        <h2>مرحبًا، <?php echo e($admin['name']); ?> 👋</h2>
        <p class="sub">نظرة عامة على المنصة — <?php echo date('l، j F Y'); ?></p>
    </div>
    <div class="phead-right">
        <a href="<?php echo e(super_admin_url('managers')); ?>" class="btn btn-ghost btn-sm">
            <i class="fas fa-user-shield"></i> إدارة المدراء
        </a>
        <a href="<?php echo e(super_admin_url('companies')); ?>" class="btn btn-primary btn-sm">
            <i class="fas fa-building"></i> إدارة الشركات
        </a>
        <a href="<?php echo e(super_admin_url('subscriptions/requests')); ?>" class="btn btn-gold btn-sm">
            <i class="fas fa-file-circle-check"></i> الطلبات
            <?php if ($cnt_pending > 0): ?>
                <span style="background:rgba(255,255,255,0.25);border-radius:999px;padding:1px 7px;font-size:0.72rem;"><?php echo $cnt_pending; ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<!-- ── Stat cards ───────────────────────────────────────────────────────── -->
<div class="stat-grid">

    <div class="stat-card">
        <div class="stat-row">
            <div>
                <div class="stat-val"><?php echo $cnt_companies; ?></div>
                <div class="stat-lbl">شركات نشطة</div>
            </div>
            <div class="stat-ico" style="background:#2563eb"><i class="fas fa-building"></i></div>
        </div>
        <div class="stat-foot">
            <a href="<?php echo e(super_admin_url('companies')); ?>" style="color:inherit">عرض الكل ←</a>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-row">
            <div>
                <div class="stat-val"><?php echo $cnt_pending; ?></div>
                <div class="stat-lbl">طلبات معلقة</div>
            </div>
            <div class="stat-ico" style="background:<?php echo $cnt_pending > 0 ? '#d97706' : '#64748b'; ?>">
                <i class="fas fa-hourglass-half"></i>
            </div>
        </div>
        <div class="stat-foot <?php echo $cnt_pending > 0 ? 'warn' : ''; ?>">
            <?php echo $cnt_pending > 0 ? 'تتطلب مراجعة' : 'لا يوجد طلبات معلقة'; ?>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-row">
            <div>
                <div class="stat-val"><?php echo $cnt_users; ?></div>
                <div class="stat-lbl">مستخدموا النظام</div>
            </div>
            <div class="stat-ico" style="background:#0f766e"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-foot">إجمالي الحسابات المسجلة</div>
    </div>

    <div class="stat-card">
        <div class="stat-row">
            <div>
                <div class="stat-val"><?php echo $cnt_projects; ?></div>
                <div class="stat-lbl">مشاريع نشطة</div>
            </div>
            <div class="stat-ico" style="background:#7c3aed"><i class="fas fa-diagram-project"></i></div>
        </div>
        <div class="stat-foot">في قاعدة بيانات EMS</div>
    </div>

    <div class="stat-card">
        <div class="stat-row">
            <div>
                <div class="stat-val"><?php echo $cnt_equip; ?></div>
                <div class="stat-lbl">معدات مسجلة</div>
            </div>
            <div class="stat-ico" style="background:#b45309"><i class="fas fa-truck-monster"></i></div>
        </div>
        <div class="stat-foot">في قاعدة بيانات EMS</div>
    </div>

    <div class="stat-card">
        <div class="stat-row">
            <div>
                <div class="stat-val"><?php echo $cnt_suppliers; ?></div>
                <div class="stat-lbl">موردون</div>
            </div>
            <div class="stat-ico" style="background:#dc2626"><i class="fas fa-truck-field"></i></div>
        </div>
        <div class="stat-foot">في قاعدة بيانات EMS</div>
    </div>

</div><!-- /.stat-grid -->

<!-- ── Two-column section ─────────────────────────────────────────────── -->
<div class="g2">

    <!-- Recent subscription requests -->
    <div class="card">
        <div class="card-hd">
            <span class="card-hd-title"><i class="fas fa-file-circle-check" style="color:#d97706;margin-left:6px"></i>آخر طلبات الاشتراك</span>
            <a href="<?php echo e(super_admin_url('subscriptions/requests')); ?>" class="btn btn-ghost btn-sm">عرض الكل</a>
        </div>
        <?php if (empty($recent_requests)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>لا توجد طلبات حتى الآن</p>
        </div>
        <?php else: ?>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>الشركة</th><th>الخطة</th><th>الحالة</th><th>التاريخ</th></tr></thead>
                <tbody>
                    <?php foreach ($recent_requests as $req): ?>
                    <tr>
                        <td><?php echo e($req['company_name'] ?? '—'); ?></td>
                        <td><?php echo e($req['plan_name'] ?? '—'); ?></td>
                        <td>
                            <?php
                            $st = $req['status'] ?? 'pending';
                            $sc = ['pending'=>'bg-orange','approved'=>'bg-green','rejected'=>'bg-red'];
                            $sl = ['pending'=>'معلق','approved'=>'مقبول','rejected'=>'مرفوض'];
                            ?>
                            <span class="badge <?php echo $sc[$st] ?? 'bg-gray'; ?>"><?php echo $sl[$st] ?? $st; ?></span>
                        </td>
                        <td class="text-muted"><?php echo e(date('d/m/Y', strtotime($req['created_at']))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick links -->
    <div class="card">
        <div class="card-hd">
            <span class="card-hd-title"><i class="fas fa-bolt" style="color:#d6a700;margin-left:6px"></i>وصول سريع</span>
        </div>
        <div class="card-body">
            <div style="display:grid;gap:10px;">
                <?php
                $quick = [
                    ['url'=>'managers',               'icon'=>'fa-user-shield',       'label'=>'إدارة المدراء',      'clr'=>'#0f2240'],
                    ['url'=>'companies',              'icon'=>'fa-building',          'label'=>'إدارة الشركات',     'clr'=>'#2563eb'],
                    ['url'=>'subscriptions/requests', 'icon'=>'fa-file-circle-check', 'label'=>'طلبات الاشتراك',    'clr'=>'#d97706'],
                    ['url'=>'plans',                  'icon'=>'fa-layer-group',       'label'=>'خطط الاشتراك',      'clr'=>'#7c3aed'],
                    ['url'=>'support/view',           'icon'=>'fa-headset',           'label'=>'الدعم الفني',       'clr'=>'#059669'],
                    ['url'=>'audit-log',              'icon'=>'fa-scroll',            'label'=>'سجل المراجعة',      'clr'=>'#64748b'],
                    ['url'=>'settings',               'icon'=>'fa-gear',              'label'=>'الإعدادات',         'clr'=>'#0f2240'],
                ];
                foreach ($quick as $q):
                ?>
                <a href="<?php echo e(super_admin_url($q['url'])); ?>"
                   style="display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:9px;border:1px solid var(--line);color:var(--ink);transition:background 0.15s;"
                   onmouseover="this.style.background='var(--surface)'" onmouseout="this.style.background='transparent'">
                    <div style="width:34px;height:34px;border-radius:9px;background:<?php echo $q['clr']; ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.85rem;flex-shrink:0;">
                        <i class="fas <?php echo $q['icon']; ?>"></i>
                    </div>
                    <span style="font-weight:600;font-size:0.88rem;"><?php echo e($q['label']); ?></span>
                    <i class="fas fa-chevron-left" style="margin-right:auto;color:var(--muted);font-size:0.75rem;"></i>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

</div><!-- /.g2 -->

<!-- ── Session info strip ─────────────────────────────────────────────── -->
<div class="alert alert-info mt-16" style="margin-top:18px;">
    <i class="fas fa-circle-info"></i>
    <div>
        آخر تسجيل دخول:
        <strong><?php echo $admin['last_login_at'] ? e($admin['last_login_at']) : 'أول تسجيل'; ?></strong>
        &nbsp;|&nbsp; البريد الإلكتروني: <strong><?php echo e($admin['email']); ?></strong>
    </div>
</div>

<?php require_once __DIR__ . '/includes/layout_foot.php'; ?>