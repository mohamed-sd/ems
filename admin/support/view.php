<?php
require_once dirname(__DIR__) . '/includes/auth.php';
super_admin_require_login();

$admin        = super_admin_current();
$page_title   = 'الدعم الفني';
$current_page = 'support';

// ── Company lookup (read-only) ────────────────────────────────────────────
$lookup    = trim($_POST['lookup'] ?? $_GET['q'] ?? '');
$found_co  = null;
$found_users = [];

if ($lookup !== '') {
    $esc = mysqli_real_escape_string($conn, $lookup);
    $cq  = @mysqli_query($conn,
        "SELECT c.*, p.plan_name FROM admin_companies c
         LEFT JOIN admin_subscription_plans p ON c.plan_id = p.id
         WHERE c.company_name LIKE '%$esc%' OR c.email LIKE '%$esc%'
         LIMIT 1"
    );
    if ($cq) { $found_co = mysqli_fetch_assoc($cq); }

    if ($found_co) {
        $co_id = intval($found_co['id']);
        $uq = @mysqli_query($conn,
            "SELECT id, username, role, created_at FROM users WHERE company_id = $co_id ORDER BY created_at DESC"
        );
        if ($uq) { while ($row = mysqli_fetch_assoc($uq)) $found_users[] = $row; }
    }
}

require_once dirname(__DIR__) . '/includes/layout_head.php';
?>

<div class="phead">
    <div>
        <h2>الدعم الفني</h2>
        <p class="sub">عرض بيانات الشركات لأغراض الدعم — وضع القراءة فقط</p>
    </div>
</div>

<div class="alert alert-info">
    <i class="fas fa-shield-halved"></i>
    <div>هذه الصفحة بوضع <strong>القراءة فقط</strong> — لا يمكن تعديل أي بيانات من هنا لضمان سلامة البيانات.</div>
</div>

<!-- ── Search form ────────────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:18px;">
    <div class="card-hd"><span class="card-hd-title"><i class="fas fa-search" style="color:var(--blue);margin-left:6px"></i>البحث عن شركة</span></div>
    <div class="card-body">
        <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;">
            <div class="input-icon-wrap" style="flex:1;min-width:240px;">
                <i class="fas fa-building"></i>
                <input class="form-ctrl" style="width:100%;padding-right:34px;" name="lookup"
                       value="<?php echo e($lookup); ?>"
                       placeholder="ابحث باسم الشركة أو البريد الإلكتروني...">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> بحث</button>
            <?php if ($lookup): ?>
            <a href="<?php echo e(super_admin_url('support/view')); ?>" class="btn btn-ghost">
                <i class="fas fa-times"></i> مسح
            </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ── Results ────────────────────────────────────────────────────────────── -->
<?php if ($lookup && !$found_co): ?>
<div class="alert alert-warning">
    <i class="fas fa-triangle-exclamation"></i>
    <span>لم يتم العثور على أي شركة تطابق "<strong><?php echo e($lookup); ?></strong>"</span>
</div>

<?php elseif ($found_co): ?>

<!-- Company info -->
<div class="card" style="margin-bottom:18px;">
    <div class="card-hd" style="background:linear-gradient(135deg,#0b1933,#1a3a5c);border-radius:13px 13px 0 0;padding:18px 22px;">
        <div class="flex">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(214,167,0,0.15);border:1px solid rgba(214,167,0,0.3);display:flex;align-items:center;justify-content:center;color:#d6a700;font-size:1.1rem;flex-shrink:0;">
                <i class="fas fa-building"></i>
            </div>
            <div>
                <div style="font-weight:800;color:#fff;font-size:1.05rem;"><?php echo e($found_co['company_name']); ?></div>
                <div style="color:rgba(255,255,255,0.6);font-size:0.82rem;"><?php echo e($found_co['email']); ?></div>
            </div>
        </div>
        <?php
        $st = $found_co['status'] ?? 'pending';
        $sc = ['active'=>'bg-green','suspended'=>'bg-red','pending'=>'bg-orange'];
        $sl = ['active'=>'نشط','suspended'=>'موقوف','pending'=>'معلق'];
        ?>
        <span class="badge <?php echo $sc[$st] ?? 'bg-gray'; ?>"><?php echo $sl[$st] ?? $st; ?></span>
    </div>
</div>

<div class="g2" style="margin-bottom:18px;">
    <!-- Company details -->
    <div class="card">
        <div class="card-hd"><span class="card-hd-title">بيانات الشركة</span></div>
        <div class="card-body">
            <?php
            $rows = [
                'البريد الإلكتروني'  => $found_co['email'],
                'رقم الهاتف'         => $found_co['phone'] ?? '—',
                'العنوان'            => $found_co['address'] ?? '—',
                'خطة الاشتراك'       => $found_co['plan_name'] ?? '—',
                'تاريخ الانضمام'     => $found_co['created_at'] ? date('d/m/Y H:i', strtotime($found_co['created_at'])) : '—',
            ];
            foreach ($rows as $l => $v):
            ?>
            <div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--line);font-size:0.88rem;">
                <span class="text-muted"><?php echo e($l); ?></span>
                <span style="font-weight:600;"><?php echo e($v); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Users -->
    <div class="card">
        <div class="card-hd">
            <span class="card-hd-title">المستخدمون (<?php echo count($found_users); ?>)</span>
        </div>
        <?php if (empty($found_users)): ?>
        <div class="empty-state">
            <i class="fas fa-user-slash"></i>
            <p>لا يوجد مستخدمون مرتبطون</p>
        </div>
        <?php else: ?>
        <div class="tbl-wrap">
            <table>
                <thead><tr><th>اسم المستخدم</th><th>الدور</th><th>تاريخ الإنشاء</th></tr></thead>
                <tbody>
                    <?php foreach ($found_users as $u): ?>
                    <tr>
                        <td style="font-weight:600;"><?php echo e($u['username']); ?></td>
                        <td><?php echo intval($u['role']); ?></td>
                        <td class="text-muted"><?php echo e(date('d/m/Y', strtotime($u['created_at']))); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; /* end results */ ?>

<!-- ── Info box (when idle) ──────────────────────────────────────────────── -->
<?php if (!$lookup): ?>
<div class="card">
    <div class="card-body" style="text-align:center;padding:40px;">
        <div style="font-size:2.5rem;color:rgba(37,99,235,0.25);margin-bottom:16px;"><i class="fas fa-headset"></i></div>
        <h3 style="font-size:1rem;font-weight:700;margin-bottom:8px;color:var(--ink-2);">بحث سريع لأغراض الدعم</h3>
        <p class="text-muted">أدخل اسم الشركة أو بريدها الإلكتروني في حقل البحث أعلاه<br>لعرض معلوماتها ومستخدميها.</p>
    </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__) . '/includes/layout_foot.php'; ?>
