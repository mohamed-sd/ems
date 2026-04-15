<?php
include '../config.php';
require_login();
require_once '../includes/approval_workflow.php';

$user_role = approval_get_user_role();
$user_id = approval_get_user_id();

$page_title = 'إيكوبيشن | طلبات الموافقات';
include '../inheader.php';
include '../insidebar.php';

$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'pending';
$allowed_status = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($status_filter, $allowed_status, true)) {
    $status_filter = 'pending';
}

$where = "1=1";
if ($status_filter !== 'all') {
    $status_esc = mysqli_real_escape_string($conn, $status_filter);
    $where .= " AND ar.status = '$status_esc'";
}

if ($user_role !== '-1') {
    $role_esc = mysqli_real_escape_string($conn, $user_role);
    $where .= " AND (
        ar.requested_by = $user_id
        OR EXISTS (
            SELECT 1 FROM approval_steps aps
            WHERE aps.request_id = ar.id
              AND aps.status = 'pending'
              AND (FIND_IN_SET('$role_esc', aps.role_required) > 0)
        )
        OR EXISTS (
            SELECT 1 FROM approval_steps aps_done
            WHERE aps_done.request_id = ar.id
              AND aps_done.approved_by = $user_id
              AND aps_done.status IN ('approved', 'rejected')
        )
    )";
}

$sql = "SELECT ar.*,
               u.username AS requester_name,
               aps.role_required AS pending_role,
               aps.step_order AS pending_step
        FROM approval_requests ar
        LEFT JOIN users u ON u.id = ar.requested_by
        LEFT JOIN approval_steps aps
            ON aps.request_id = ar.id
           AND aps.status = 'pending'
           AND aps.step_order = (
               SELECT MIN(s2.step_order)
               FROM approval_steps s2
               WHERE s2.request_id = ar.id
                 AND s2.status = 'pending'
           )
        WHERE $where
        ORDER BY ar.id DESC";

$result = mysqli_query($conn, $sql);
?>
<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">

<div class="main">
    <div class="page-header">
        <div style="display:flex;align-items:center;gap:12px;">
            <div class="title-icon"><i class="fas fa-check-double"></i></div>
            <h1 class="page-title">طلبات الموافقات</h1>
        </div>
        <a href="../main/dashboard.php" class="back-btn"><i class="fas fa-arrow-right"></i> رجوع</a>
    </div>

    <?php
    // احصائيات الطلبات
    $stats_sql = "SELECT 
        status,
        COUNT(*) as count
    FROM approval_requests ar
    WHERE ($where)
    GROUP BY status;";
    
    $stats_result = mysqli_query($conn, $stats_sql);
    $stats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    while ($stat = mysqli_fetch_assoc($stats_result)) {
        $stats[$stat['status']] = $stat['count'];
    }
    $total = $stats['pending'] + $stats['approved'] + $stats['rejected'];
    ?>

    <!-- إحصائيات -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stats-card border-left-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">معلقة</h6>
                            <h3 class="mb-0 text-warning"><?php echo $stats['pending']; ?></h3>
                        </div>
                        <div class="stats-icon text-warning">
                            <i class="fas fa-hourglass-half fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stats-card border-left-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">معتمدة</h6>
                            <h3 class="mb-0 text-success"><?php echo $stats['approved']; ?></h3>
                        </div>
                        <div class="stats-icon text-success">
                            <i class="fas fa-check-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stats-card border-left-danger">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">مرفوضة</h6>
                            <h3 class="mb-0 text-danger"><?php echo $stats['rejected']; ?></h3>
                        </div>
                        <div class="stats-icon text-danger">
                            <i class="fas fa-times-circle fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="card stats-card border-left-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">الإجمالي</h6>
                            <h3 class="mb-0 text-info"><?php echo $total; ?></h3>
                        </div>
                        <div class="stats-icon text-info">
                            <i class="fas fa-list fa-2x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- الفلاتر -->
    <div class="card mb-3">
        <div class="card-body">
            <nav class="nav nav-tabs" role="tablist">
                <a class="nav-link <?php echo $status_filter === 'pending' ? 'active' : ''; ?>" href="?status=pending">
                    <i class="fas fa-hourglass-half"></i> معلقة <span class="badge bg-warning ms-2"><?php echo $stats['pending']; ?></span>
                </a>
                <a class="nav-link <?php echo $status_filter === 'approved' ? 'active' : ''; ?>" href="?status=approved">
                    <i class="fas fa-check-circle"></i> معتمدة <span class="badge bg-success ms-2"><?php echo $stats['approved']; ?></span>
                </a>
                <a class="nav-link <?php echo $status_filter === 'rejected' ? 'active' : ''; ?>" href="?status=rejected">
                    <i class="fas fa-times-circle"></i> مرفوضة <span class="badge bg-danger ms-2"><?php echo $stats['rejected']; ?></span>
                </a>
                <a class="nav-link <?php echo $status_filter === 'all' ? 'active' : ''; ?>" href="?status=all">
                    <i class="fas fa-list"></i> الكل <span class="badge bg-secondary ms-2"><?php echo $total; ?></span>
                </a>
            </nav>
        </div>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle"></i> <?php echo e($_GET['msg']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-list-check"></i> قائمة طلبات الموافقات</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="approvalsTable" class="display table table-hover mb-0" style="width:100%;">
                    <thead class="table-light">
                    <tr>
                        <th style="width: 5%;">م</th>
                        <th style="width: 12%;">الكيان</th>
                        <th style="width: 12%;">الإجراء</th>
                        <th style="width: 12%;">الطالب</th>
                        <th style="width: 15%;">المرحلة الحالية</th>
                        <th style="width: 10%;">الحالة</th>
                        <th style="width: 18%;">تاريخ الإنشاء</th>
                        <th style="width: 16%;">إجراءات</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($result): ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <?php
                            $can_act = false;
                            if ($row['status'] === 'pending') {
                                if ($user_role === '-1') {
                                    $can_act = true;
                                } elseif (!empty($row['pending_role'])) {
                                    $roles = array_map('trim', explode(',', $row['pending_role']));
                                    $can_act = in_array($user_role, $roles, true);
                                }
                            }

                            $status_badge = 'secondary';
                            $status_icon = 'fas fa-circle';
                            if ($row['status'] === 'pending') {
                                $status_badge = 'warning';
                                $status_icon = 'fas fa-hourglass-half';
                            }
                            if ($row['status'] === 'approved') {
                                $status_badge = 'success';
                                $status_icon = 'fas fa-check-circle';
                            }
                            if ($row['status'] === 'rejected') {
                                $status_badge = 'danger';
                                $status_icon = 'fas fa-times-circle';
                            }

                            $payload_preview = '';
                            $payload = json_decode($row['payload'], true);
                            if (is_array($payload) && isset($payload['summary'])) {
                                $payload_preview = json_encode($payload['summary'], JSON_UNESCAPED_UNICODE);
                            } else {
                                $payload_preview = mb_substr($row['payload'], 0, 300);
                            }
                            ?>
                            <tr>
                                <td><strong><?php echo intval($row['id']); ?></strong></td>
                                <td>
                                    <span class="badge bg-info">
                                        <?php echo e($row['entity_type']); ?>
                                    </span>
                                    <br>
                                    <small class="text-muted">#<?php echo intval($row['entity_id']); ?></small>
                                </td>
                                <td>
                                    <code><?php echo e($row['action']); ?></code>
                                </td>
                                <td>
                                    <small><?php echo e($row['requester_name'] ?: ('مستخدم #' . intval($row['requested_by']))); ?></small>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'pending'): ?>
                                        <div class="mb-1">
                                            <span class="badge bg-primary">المرحلة <?php echo intval($row['pending_step']); ?></span>
                                        </div>
                                        <small class="text-muted">الدور: <strong><?php echo e($row['pending_role']); ?></strong></small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $status_badge; ?>">
                                        <i class="<?php echo $status_icon; ?> me-1"></i>
                                        <?php 
                                        echo match($row['status']) {
                                            'pending' => 'معلقة',
                                            'approved' => 'معتمدة',
                                            'rejected' => 'مرفوضة',
                                            default => $row['status']
                                        };
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo date('Y-m-d', strtotime($row['created_at'])); ?>
                                    </small>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i>
                                        <?php echo date('H:i', strtotime($row['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-info viewPayloadBtn" 
                                                data-payload="<?php echo e($payload_preview); ?>"
                                                title="عرض التفاصيل">
                                            <i class="fas fa-eye"></i> عرض
                                        </button>

                                        <?php if ($can_act): ?>
                                            <button type="button" class="btn btn-outline-success approveBtn" 
                                                    data-id="<?php echo intval($row['id']); ?>"
                                                    title="الموافقة على الطلب">
                                                <i class="fas fa-check"></i> اعتماد
                                            </button>
                                            <button type="button" class="btn btn-outline-danger rejectBtn" 
                                                    data-id="<?php echo intval($row['id']); ?>"
                                                    title="رفض الطلب">
                                                <i class="fas fa-ban"></i> رفض
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p class="text-muted">لا توجد طلبات موافقات</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="payloadModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title">
                    <i class="fas fa-info-circle text-info"></i> تفاصيل الطلب
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <pre id="payloadText" class="bg-light p-3 rounded" style="white-space: pre-wrap; word-break: break-word; max-height: 400px; overflow-y: auto;"></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إغلاق
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="decisionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light" id="decisionHeader">
                <h5 class="modal-title" id="decisionTitle">
                    <i class="fas fa-check-circle text-success"></i> قرار الموافقة
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-3" id="decisionMessage">
                    <i class="fas fa-lightbulb"></i> يرجى إدخال ملاحظتك قبل تأكيد القرار
                </div>
                <input type="hidden" id="decisionRequestId" value="">
                <input type="hidden" id="decisionAction" value="">
                <div class="mb-3">
                    <label class="form-label" for="decisionNote">
                        <i class="fas fa-comment"></i> ملاحظات (اختياري)
                    </label>
                    <textarea id="decisionNote" class="form-control" rows="4" placeholder="أضف أي ملاحظات إضافية..."></textarea>
                    <small class="form-text text-muted d-block mt-2">أقصى 500 حرف</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> إلغاء
                </button>
                <button type="button" class="btn btn-primary" id="submitDecisionBtn">
                    <i class="fas fa-check"></i> <span id="submitBtnText">تأكيد</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- CSS للإحصائيات -->
<style>
.stats-card {
    transition: all 0.3s ease;
    border: none;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

.border-left-warning {
    border-left: 4px solid #ffc107 !important;
}

.border-left-success {
    border-left: 4px solid #28a745 !important;
}

.border-left-danger {
    border-left: 4px solid #dc3545 !important;
}

.border-left-info {
    border-left: 4px solid #17a2b8 !important;
}

.table-hover tbody tr:hover {
    background-color: #f8f9fa;
}

.nav-tabs .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    color: #555;
    font-weight: 500;
    margin-left: 0.5rem;
}

.nav-tabs .nav-link:hover {
    border-bottom-color: #ddd;
    color: #333;
}

.nav-tabs .nav-link.active {
    border-bottom-color: #007bff;
    color: #007bff;
    background: none;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
}
</style>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="/ems/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/ems/assets/vendor/datatables/js/jquery.dataTables.min.js"></script>
<script>
$(function () {
    // تهيئة DataTable
    const table = $('#approvalsTable').DataTable({
        pageLength: 25,
        order: [[0, 'desc']],
        language: { url: '/ems/assets/i18n/datatables/ar.json' },
        columnDefs: [
            { orderable: false, targets: [7] }
        ]
    });

    let payloadModal = null;
    let decisionModal = null;

    // تهيئة الـ modals عند تحميل الصفحة
    setTimeout(function() {
        payloadModal = new bootstrap.Modal(document.getElementById('payloadModal'));
        decisionModal = new bootstrap.Modal(document.getElementById('decisionModal'));
    }, 100);

    // عرض تفاصيل الطلب
    $(document).on('click', '.viewPayloadBtn', function () {
        const payload = $(this).data('payload');
        let displayText = 'لا توجد بيانات';

        if (payload !== undefined && payload !== null && payload !== '') {
            if (typeof payload === 'object') {
                try {
                    displayText = JSON.stringify(payload, null, 2);
                } catch (e) {
                    displayText = String(payload);
                }
            } else {
                displayText = String(payload);
                try {
                    const parsed = JSON.parse(displayText);
                    if (typeof parsed === 'object' && parsed !== null) {
                        displayText = JSON.stringify(parsed, null, 2);
                    }
                } catch (e) {}
            }
        }

        $('#payloadText').text(displayText);
        if (payloadModal) {
            payloadModal.show();
        } else {
            $('#payloadModal').modal('show');
        }
    });

    // فتح نموذج القرار
    $(document).on('click', '.approveBtn, .rejectBtn', function () {
        const isApprove = $(this).hasClass('approveBtn');
        const requestId = $(this).data('id');

        $('#decisionRequestId').val(requestId);
        $('#decisionAction').val(isApprove ? 'approve' : 'reject');

        if (isApprove) {
            $('#decisionTitle').html('<i class="fas fa-check-circle text-success"></i> اعتماد الطلب');
            $('#decisionMessage').html('<i class="fas fa-info-circle text-info"></i> يرجى تأكيد اعتماد هذا الطلب');
            $('#decisionHeader').removeClass().addClass('modal-header bg-success text-white');
            $('#submitBtnText').text('اعتماد');
            $('#submitDecisionBtn').removeClass().addClass('btn btn-success');
        } else {
            $('#decisionTitle').html('<i class="fas fa-ban text-danger"></i> رفض الطلب');
            $('#decisionMessage').html('<i class="fas fa-warning text-warning"></i> يرجى تحديد سبب الرفض');
            $('#decisionHeader').removeClass().addClass('modal-header bg-danger text-white');
            $('#submitBtnText').text('رفض');
            $('#submitDecisionBtn').removeClass().addClass('btn btn-danger');
        }

        $('#decisionNote').val('');
        if (decisionModal) {
            decisionModal.show();
        } else {
            $('#decisionModal').modal('show');
        }
    });

    // تقديم القرار
    $('#submitDecisionBtn').on('click', function () {
        const requestId = $('#decisionRequestId').val();
        const action = $('#decisionAction').val();
        const note = $('#decisionNote').val().trim();

        if (!requestId || !action) {
            alert('خطأ: بيانات غير كاملة');
            return;
        }

        // التحقق من طول الملاحظات
        if (note.length > 500) {
            alert('الملاحظات يجب أن لا تتجاوز 500 حرف');
            return;
        }

        const btn = $(this);
        const originalText = btn.html();

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> جارٍ التنفيذ...');

        $.ajax({
            url: 'approval_api.php',
            type: 'POST',
            dataType: 'json',
            data: {
                api_action: action,
                request_id: requestId,
                note: note,
                reason: note,
                csrf_token: '<?php echo generate_csrf_token(); ?>'
            },
            success: function (res) {
                if (res.success) {
                    // نجح
                    if (decisionModal) {
                        decisionModal.hide();
                    } else {
                        $('#decisionModal').modal('hide');
                    }
                    
                    // عرض رسالة النجاح
                    const alertClass = action === 'approve' ? 'alert-success' : 'alert-info';
                    const alertIcon = action === 'approve' ? 'fa-check-circle' : 'fa-info-circle';
                    const alertMsg = res.message || (action === 'approve' ? 'تم اعتماد الطلب بنجاح' : 'تم رفض الطلب بنجاح');
                    
                    const alert = $(`
                        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                            <i class="fas ${alertIcon}"></i> ${alertMsg}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    `);
                    
                    // إدراج الرسالة أعلى الجدول
                    $('.page-header').after(alert);

                    // إعادة تحميل الجدول
                    setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                } else {
                    // فشل
                    alert('خطأ: ' + (res.message || 'حدث خطأ في معالجة الطلب'));
                    btn.prop('disabled', false).html(originalText);
                }
            },
            error: function (xhr) {
                console.error('AJAX Error:', xhr);
                alert('خطأ: تعذر الاتصال بالخادم. يرجى المحاولة لاحقاً');
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    // السماح بالإرسال عند الضغط على Enter في نموذج الملاحظات
    $('#decisionNote').on('keypress', function(e) {
        if (e.ctrlKey && e.which === 13) { // Ctrl+Enter
            $('#submitDecisionBtn').click();
        }
    });
});
</script>


