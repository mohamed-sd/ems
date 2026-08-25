<?php
require_once __DIR__ . '/../includes/session_bootstrap.php'; // مخزن الجلسات المشترك — يسبق session_start()
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../login.php");
    exit();
}

include '../config.php';
include '../includes/permissions_helper.php';
require_once __DIR__ . '/../includes/equipment_log_helper.php';

$current_role = isset($_SESSION['user']['role']) ? strval($_SESSION['user']['role']) : '';
$is_super_admin = ($current_role === '-1');
$company_id = isset($_SESSION['user']['company_id']) ? intval($_SESSION['user']['company_id']) : 0;
$current_user_id = isset($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;

if (!$is_super_admin && $company_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد بيئة شركة صالحة للمستخدم ❌', 'GOV-SCOPE-403', '');
    exit();
}

// العزل عبر بوابة المستأجر (K9 · هجرة 2026-07-15): كشوف الأعمدة والترحيلات الذاتية
// وقتَ التشغيل أُسقطوا — الأعمدة (shift_type/target_daily_hours/prev_equipment_category/
// op_state/availability_status/availability_state...) مضمونةٌ بالترحيلات (لقطة catch-up)،
// وإحداثيات المعدات (latitude/longitude) غير موجودة أصلًا فتبقى NULL AS كسلوك الأصل
// الفعلي. {TENANT_SCOPE} والبوابة مسؤولا النطاق، والسوبر عبر forAllTenants المسجَّل.
$mvp_gate = $is_super_admin ? ems_tenant_db()->forAllTenants('movement operations super') : ems_tenant_db();

// الصلاحيات
$ops_perm = check_page_permissions($conn, 'movement/move_oprators.php');
$drv_perm = check_page_permissions($conn, 'movement/project_drivers.php');
$can_view = (!empty($ops_perm['can_view']) || !empty($drv_perm['can_view']));
$can_add = (!empty($ops_perm['can_add']) || !empty($drv_perm['can_add']));
$can_edit = (!empty($ops_perm['can_edit']) || !empty($drv_perm['can_edit']));

if (!$can_view) {
    ems_gov_flash_redirect('../main/dashboard.php', 'لا توجد صلاحية عرض الشاشة الموحدة ❌', 'GOV-PERM-403', '');
    exit();
}

// نطاق المشروع — عبر البوابة ({TENANT_SCOPE})

// اختيار المشروع
$session_user_project_id = isset($_SESSION['user']['project_id']) ? intval($_SESSION['user']['project_id']) : 0;
$selected_project_id = 0;
if (isset($_GET['project_id']) && intval($_GET['project_id']) > 0) {
    $selected_project_id = intval($_GET['project_id']);
    $_SESSION['operations_project_id'] = $selected_project_id;
} elseif (isset($_SESSION['operations_project_id']) && intval($_SESSION['operations_project_id']) > 0) {
    $selected_project_id = intval($_SESSION['operations_project_id']);
} elseif ($session_user_project_id > 0) {
    $selected_project_id = $session_user_project_id;
    $_SESSION['operations_project_id'] = $selected_project_id;
}

if ($selected_project_id <= 0) {
    ems_gov_flash_redirect('../main/dashboard.php', '❌ لا يوجد مشروع مرتبط بالمستخدم', 'GOV-SCOPE-403', '');
    exit();
}

try {
    $project_rows = $mvp_gate->scopedQuery(array(
        'scope' => array('project' => 'project'),
    ), "SELECT id, name, project_code, project.latitude AS latitude, project.longitude AS longitude
        FROM project WHERE {TENANT_SCOPE} AND id = ? AND status = 1", array($selected_project_id));
} catch (\Throwable $t) { $project_rows = array(); }
if (empty($project_rows)) {
    unset($_SESSION['operations_project_id']);
    ems_gov_flash_redirect('../main/dashboard.php', '❌ المشروع غير متاح', 'GOV-SCOPE-403', '');
    exit();
}
$selected_project = $project_rows[0];

$msg = '';
$is_success = true;

// معالجة الـ POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!$can_edit) {
        $msg = 'لا توجد صلاحية للتعديل ❌';
        $is_success = false;
    } else {
        $action = trim((string)$_POST['action']);
        $allowed_shift = array('D', 'N', 'B');

        // حفظ فردي لتشغيل واحد
        if ($action === 'save_single_operation') {
            try {
                $op_id = intval($_POST['op_id'] ?? 0);
                if ($op_id <= 0) {
                    throw new Exception('معرف التشغيل غير صحيح');
                }

                // اقرأ القيم الحالية قبل التحديث (لتسجيل التغيّر الفعلي ومعرفة المعدة).
                $old_shift = null; $old_status = null; $log_equipment = 0;
                try {
                    $pre_row = $mvp_gate->selectOne('operations', array(
                        'columns' => array('equipment', 'shift_type', 'status'),
                        'where'   => array('id' => $op_id, 'project_id' => $selected_project_id),
                    ));
                } catch (\Throwable $t) { $pre_row = null; }
                if ($pre_row) {
                    $log_equipment = intval($pre_row['equipment']);
                    $old_shift     = (string)$pre_row['shift_type'];
                    $old_status    = intval($pre_row['status']);
                }

                // الدور (أساسي/احتياطي) لا يُكتب من هذه الشاشة إطلاقاً — يُدار من شاشة التشغيل فقط.
                // الحالة التشغيلية (op_state) لها معالج مستقل (set_op_state). هنا نحفظ الوردية والتواريخ فقط.
                $shift_type = isset($_POST['shift_type']) ? trim((string)$_POST['shift_type']) : 'B';
                if (!in_array($shift_type, $allowed_shift, true)) {
                    $shift_type = 'B';
                }

                $status = intval($_POST['status'] ?? 0);
                $status = ($status === 0) ? 0 : 1;

                $start = isset($_POST['start']) ? trim((string)$_POST['start']) : '';
                $end = isset($_POST['end']) ? trim((string)$_POST['end']) : '';

                if ($start !== '') {
                    $start_obj = DateTime::createFromFormat('Y-m-d', $start);
                    if (!$start_obj || $start_obj->format('Y-m-d') !== $start) {
                        throw new Exception('صيغة تاريخ البداية غير صحيحة');
                    }
                }
                if ($end !== '') {
                    $end_obj = DateTime::createFromFormat('Y-m-d', $end);
                    if (!$end_obj || $end_obj->format('Y-m-d') !== $end) {
                        throw new Exception('صيغة تاريخ النهاية غير صحيحة');
                    }
                }
                if ($start !== '' && $end !== '' && strtotime($end) < strtotime($start)) {
                    throw new Exception('تاريخ النهاية يجب أن يكون بعد البداية');
                }

                // ORG-13 · حارس الأذونات: إنهاء التشغيل = خروج المعدة من الموقع (ORG-01 §5-④)
                if ($old_status === 1 && $status === 0 && $log_equipment > 0) {
                    require_once dirname(__DIR__) . '/includes/permit_gate.php';
                    $pg_site = ems_default_site_of_project($conn, $company_id, $selected_project_id);
                    $pg = ems_permit_gate($conn, $company_id, 'equipment_site_exit',
                        'EQ:' . $log_equipment, $pg_site, intval($current_user_id ?? 0));
                    if (!$pg['ok']) { throw new Exception($pg['reason']); }
                }

                $op_fields = array(
                    'shift_type' => $shift_type,
                    'status'     => $status,
                );
                if ($start !== '') {
                    $op_fields['start'] = $start;
                }
                if ($end !== '') {
                    $op_fields['end'] = $end;
                }

                try {
                    $mvp_gate->update('operations', $op_fields,
                        array('id' => $op_id, 'project_id' => $selected_project_id));
                } catch (\Throwable $t) {
                    throw new Exception('خطأ في تحديث التشغيل');
                }

                // تسجيل التحركات: تغيير وردية و/أو إنهاء تشغيل — عند التغيّر الفعلي فقط.
                if ($log_equipment > 0) {
                    $log_base = ['operation_id' => $op_id];
                    if ($company_id > 0) { $log_base['company_id'] = $company_id; }
                    if ($old_shift !== null && $old_shift !== $shift_type) {
                        $ems_shift_label = function ($s) { return $s === 'D' ? 'نهاري' : ($s === 'N' ? 'ليلي' : 'نهاري + ليلي'); };
                        log_equipment_event($conn, $log_equipment, 'تغيير وردية',
                            array_merge($log_base, ['from' => $ems_shift_label($old_shift), 'to' => $ems_shift_label($shift_type)]));
                    }
                    if ($old_status === 1 && $status === 0) {
                        log_equipment_event($conn, $log_equipment, 'إنهاء تشغيل', $log_base);
                    }
                }

                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => 'تم حفظ التشغيل ✅']);
                    exit;
                }
                $msg = 'تم حفظ التشغيل ✅';
                $is_success = true;
            } catch (Exception $ex) {
                $msg = $ex->getMessage() . ' ❌';
                $is_success = false;
                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
            }
        }

        // حفظ فردي لسائق واحد
        elseif ($action === 'save_single_driver') {
            try {
                $rel_id = intval($_POST['rel_id'] ?? 0);
                if ($rel_id <= 0) {
                    throw new Exception('معرف السائق غير صحيح');
                }

                $shift_type = isset($_POST['shift_type']) ? trim((string)$_POST['shift_type']) : 'B';
                if (!in_array($shift_type, $allowed_shift, true)) {
                    $shift_type = 'B';
                }

                $status = intval($_POST['status'] ?? 0);
                $status = ($status === 0) ? 0 : 1;

                $start_date = isset($_POST['start_date']) ? trim((string)$_POST['start_date']) : '';
                $end_date = isset($_POST['end_date']) ? trim((string)$_POST['end_date']) : '';

                if ($start_date !== '') {
                    $start_obj = DateTime::createFromFormat('Y-m-d', $start_date);
                    if (!$start_obj || $start_obj->format('Y-m-d') !== $start_date) {
                        throw new Exception('صيغة تاريخ بداية السائق غير صحيحة');
                    }
                }
                if ($end_date !== '') {
                    $end_obj = DateTime::createFromFormat('Y-m-d', $end_date);
                    if (!$end_obj || $end_obj->format('Y-m-d') !== $end_date) {
                        throw new Exception('صيغة تاريخ نهاية السائق غير صحيحة');
                    }
                }
                if ($start_date !== '' && $end_date !== '' && strtotime($end_date) < strtotime($start_date)) {
                    throw new Exception('تاريخ النهاية يجب أن يكون بعد البداية');
                }

                // التواريخ لم تعد تُعرض في الجدول؛ فما لم يُرسَل منها يبقى كما هو في القاعدة
                // (وإلا لأعاد الحفظُ ضبطَ البداية على اليوم والنهاية على 2099-12-31 بلا قصد).
                $drv_fields = array(
                    'status'     => $status,
                    'shift_type' => $shift_type,
                );
                if ($start_date !== '') {
                    $drv_fields['start_date'] = $start_date;
                }
                if ($end_date !== '') {
                    $drv_fields['end_date'] = $end_date;
                }

                try {
                    $mvp_gate->update('equipment_drivers', $drv_fields, array('id' => $rel_id));
                } catch (\Throwable $t) {
                    throw new Exception('خطأ في تحديث السائق');
                }

                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => 'تم حفظ السائق ✅']);
                    exit;
                }
                $msg = 'تم حفظ السائق ✅';
                $is_success = true;
            } catch (Exception $ex) {
                $msg = $ex->getMessage() . ' ❌';
                $is_success = false;
                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
            }
        }

        // إضافة تشغيل جديد
        elseif ($action === 'add_new_operation') {
            try {
                $equipment = intval($_POST['equipment'] ?? 0);
                $contract_id = intval($_POST['contract_id'] ?? 0);
                $supplier_id = intval($_POST['supplier_id'] ?? 0);
                $equipment_type = intval($_POST['equipment_type'] ?? 0);

                if ($equipment <= 0 || $contract_id <= 0 || $supplier_id <= 0 || $equipment_type <= 0) {
                    throw new Exception('بيانات ناقصة: تأكد من اختيار المعدة والعقد والمورد والنوع');
                }

                $equipment_category = isset($_POST['equipment_category']) ? trim((string)$_POST['equipment_category']) : 'أساسي';
                if ($equipment_category !== 'أساسي' && $equipment_category !== 'احتياطي' && $equipment_category !== 'متعطل') {
                    $equipment_category = 'أساسي';
                }

                $shift_type = isset($_POST['shift_type']) ? trim((string)$_POST['shift_type']) : 'B';
                if (!in_array($shift_type, $allowed_shift, true)) {
                    $shift_type = 'B';
                }

                $start = isset($_POST['start']) ? trim((string)$_POST['start']) : date('Y-m-d');
                $end = isset($_POST['end']) ? trim((string)$_POST['end']) : '';

                if ($start !== '') {
                    $start_obj = DateTime::createFromFormat('Y-m-d', $start);
                    if (!$start_obj || $start_obj->format('Y-m-d') !== $start) {
                        throw new Exception('صيغة تاريخ البداية غير صحيحة');
                    }
                }
                if ($end !== '') {
                    $end_obj = DateTime::createFromFormat('Y-m-d', $end);
                    if (!$end_obj || $end_obj->format('Y-m-d') !== $end) {
                        throw new Exception('صيغة تاريخ النهاية غير صحيحة');
                    }
                }
                if ($start !== '' && $end !== '' && strtotime($end) < strtotime($start)) {
                    throw new Exception('تاريخ النهاية يجب أن يكون بعد البداية');
                }

                // التحقق من عدم وجود تشغيل ساري للمعدة نفسها (العزل عبر البوابة)
                try {
                    $conflict_check = $mvp_gate->selectOne('operations', array(
                        'columns'  => array('id'),
                        'where'    => array('equipment' => $equipment),
                        'whereRaw' => 'status = 1',
                    ));
                } catch (\Throwable $t) { $conflict_check = null; }
                if ($conflict_check) {
                    throw new Exception('المعدة تعمل بالفعل في تشغيل ساري آخر');
                }

                // ORG-13 · حارس الأذونات: دخول معدة إلى الموقع (ORG-01 §5-①)
                require_once dirname(__DIR__) . '/includes/permit_gate.php';
                $pg_site = ems_default_site_of_project($conn, $company_id, $selected_project_id);
                $pg = ems_permit_gate($conn, $company_id, 'equipment_site_entry',
                    'EQ:' . $equipment, $pg_site, intval($current_user_id ?? 0));
                if (!$pg['ok']) { throw new Exception($pg['reason']); }

                $total_equipment_hours = floatval($_POST['total_equipment_hours'] ?? 0);
                $shift_hours = floatval($_POST['shift_hours'] ?? 0);
                $status = intval($_POST['status'] ?? 1);
                $status = ($status === 0) ? 0 : 1;
                // الهدف اليومي: قيمة المستخدم إن وُجدت، وإلا افتراضي (الاحتياطية=0، غيرها=ساعات الوردية×عدد الورديات)
                $target_daily_hours = (isset($_POST['target_daily_hours']) && $_POST['target_daily_hours'] !== '')
                    ? floatval($_POST['target_daily_hours'])
                    : (($equipment_category === 'احتياطي') ? 0.0 : ($shift_hours * ($shift_type === 'B' ? 2 : 1)));

                try {
                    $mvp_gate->insert('operations', array(
                        'equipment'             => $equipment,
                        'equipment_type'        => $equipment_type,
                        'equipment_category'    => $equipment_category,
                        'project_id'            => $selected_project_id,
                        'contract_id'           => $contract_id,
                        'supplier_id'           => $supplier_id,
                        'start'                 => $start,
                        'end'                   => $end,
                        'days'                  => 0,
                        'total_equipment_hours' => $total_equipment_hours,
                        'shift_hours'           => $shift_hours,
                        'target_daily_hours'    => $target_daily_hours,
                        'shift_type'            => $shift_type,
                        'status'                => $status,
                    ));
                } catch (\Throwable $t) {
                    throw new Exception('خطأ في إضافة التشغيل الجديد');
                }

                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => 'تم إضافة التشغيل ✅']);
                    exit;
                }
                $msg = 'تم إضافة التشغيل الجديد ✅';
                $is_success = true;
            } catch (Exception $ex) {
                $msg = $ex->getMessage() . ' ❌';
                $is_success = false;
                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
            }
        }

        // إضافة سائق جديد
        elseif ($action === 'add_new_driver') {
            try {
                $employee_id = intval($_POST['employee_id'] ?? 0);
                $equipment_id = intval($_POST['equipment_id'] ?? 0);

                if ($employee_id <= 0 || $equipment_id <= 0) {
                    throw new Exception('بيانات ناقصة: اختر السائق والمعدة');
                }

                $shift_type = isset($_POST['shift_type']) ? trim((string)$_POST['shift_type']) : 'B';
                if (!in_array($shift_type, $allowed_shift, true)) {
                    $shift_type = 'B';
                }

                $start_date = isset($_POST['start_date']) ? trim((string)$_POST['start_date']) : date('Y-m-d');
                $end_date = isset($_POST['end_date']) ? trim((string)$_POST['end_date']) : '2099-12-31';

                // التحقق من وجود معدة في التشغيلات النشطة (العزل عبر البوابة)
                try {
                    $eq_check = $mvp_gate->selectOne('operations', array(
                        'columns'  => array('id'),
                        'where'    => array('equipment' => $equipment_id, 'project_id' => $selected_project_id),
                        'whereRaw' => 'status = 1',
                    ));
                } catch (\Throwable $t) { $eq_check = null; }
                if (!$eq_check) {
                    throw new Exception('المعدة المختارة غير مشغلة في مشروع ساري');
                }

                // ORG-13 · حارس الأذونات: دخول مشغّل (ORG-01 §5-⑦)
                require_once dirname(__DIR__) . '/includes/permit_gate.php';
                $pg_site = ems_default_site_of_project($conn, $company_id, $selected_project_id);
                $pg = ems_permit_gate($conn, $company_id, 'operator_site_entry',
                    'EMP:' . $employee_id, $pg_site, intval($current_user_id ?? 0));
                if (!$pg['ok']) { throw new Exception($pg['reason']); }

                // إنهاء التعيينات السابقة النشطة للسائق
                try {
                    $mvp_gate->update('equipment_drivers',
                        array('status' => 0, 'end_date' => $start_date),
                        array('employee_id' => $employee_id), 'status = 1');
                } catch (\Throwable $t) {}

                try {
                    $mvp_gate->insert('equipment_drivers', array(
                        'equipment_id' => $equipment_id,
                        'employee_id'  => $employee_id,
                        'start_date'   => $start_date,
                        'end_date'     => $end_date,
                        'status'       => 1,
                        'shift_type'   => $shift_type,
                    ));
                } catch (\Throwable $t) {
                    throw new Exception('خطأ في إضافة السائق');
                }

                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => 'تم إضافة السائق ✅']);
                    exit;
                }
                $msg = 'تم إضافة السائق الجديد ✅';
                $is_success = true;
            } catch (Exception $ex) {
                $msg = $ex->getMessage() . ' ❌';
                $is_success = false;
                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
            }
        }

        // تحويل المعدة إلى وضع الصيانة (مدير الحركة أول من يعلم بالعطل من السائق)
        // ملاحظة: الحالة الفعلية للمعدة تُدار عبر availability_status / availability_state،
        // وليس عبر العمود العام status (الذي هو علم «صف نشط» = 1). لا نمسّ operations.status
        // ولا equipments.status — تغيير الصحة الفنية للتشغيل من اختصاص أمر الصيانة نفسه.
        elseif ($action === 'set_equipment_maintenance') {
            try {
                $equipment_id = intval($_POST['equipment_id'] ?? 0);
                if ($equipment_id <= 0) {
                    throw new Exception('معرف المعدة غير صحيح');
                }
                // التأكد أن المعدة مرتبطة بتشغيل في هذا المشروع (العزل عبر البوابة)
                try {
                    $check_row = $mvp_gate->selectOne('operations', array(
                        'columns' => array('id'),
                        'where'   => array('equipment' => $equipment_id, 'project_id' => $selected_project_id),
                    ));
                } catch (\Throwable $t) { $check_row = null; }
                if (!$check_row) {
                    throw new Exception('المعدة غير مرتبطة بهذا المشروع');
                }

                // (شرط الأصل «company IS NULL» زال — كل المعدات معبّأة company_id، مقيس)
                try {
                    $mvp_gate->update('equipments', array(
                        'availability_status' => 'تحت الصيانة',
                        'availability_state'  => 'غير متوفرة',
                    ), array('id' => $equipment_id));
                } catch (\Throwable $t) {
                    throw new Exception('خطأ في تحويل المعدة إلى وضع الصيانة');
                }

                // تحويل الحالة التشغيلية للتشغيلات السارية لهذه المعدة إلى «معطلة» + فتح أمر صيانة تلقائي (ذرّيًا).
                // الدور (أساسي/احتياطي) لا يُمسّ — الحالة منفصلة عن الدور.
                require_once __DIR__ . '/../Maintenance/mnt_helpers.php';
                $maint_state_changed = false;
                try {
                    $mvp_gate->runInTransaction(function ($g) use ($equipment_id, $selected_project_id, $company_id, $current_user_id, $conn, &$maint_state_changed) {
                        try {
                            $affected = $g->update('operations',
                                array('op_state' => 'معطلة'),
                                array('equipment' => $equipment_id, 'project_id' => $selected_project_id),
                                'status = 1');
                        } catch (\Throwable $t) {
                            throw new \Exception('خطأ في تحديث حالة التشغيل');
                        }
                        $maint_state_changed = ($affected > 0);

                        // منع التكرار: لا نفتح أمرًا تلقائيًا ثانيًا لمعدة لها أمر تلقائي مفتوح بالفعل.
                        if ($company_id > 0) {
                            $dup = $g->scopedQuery(array(
                                'scope' => array('mnt_order' => 'mnt_order'),
                            ), "SELECT 1 AS x FROM mnt_order
                                WHERE {TENANT_SCOPE} AND equipment_id = ? AND project_id = ?
                                  AND is_auto = 1 AND state IN ('بلاغ','تنفيذ','فحص') LIMIT 1",
                                array($equipment_id, $selected_project_id));
                            if (empty($dup)) {
                                $auto_code  = mnt_next_code($conn, 'mnt_order', 'MNT', $company_id);
                                $auto_src   = 'بلاغ';   // المصدر الدلالي
                                $auto_state = 'بلاغ';   // الحالة الابتدائية (مفتوح)
                                $g->insert('mnt_order', array(
                                    'code'         => $auto_code,
                                    'equipment_id' => $equipment_id,
                                    'project_id'   => $selected_project_id,
                                    'source'       => $auto_src,
                                    'is_auto'      => 1,
                                    'state'        => $auto_state,
                                    'created_by'   => $current_user_id,
                                ));
                            }
                        }

                        // سجل «إسناد للصيانة» — فقط إن تغيّرت الحالة فعليًا إلى «معطلة».
                        if ($maint_state_changed) {
                            $log_opts = ['to' => 'معطلة', 'project_id' => $selected_project_id];
                            if ($company_id > 0) { $log_opts['company_id'] = $company_id; }
                            log_equipment_event($conn, $equipment_id, 'إسناد للصيانة', $log_opts);
                        }
                    }, 'إسناد آلية للصيانة مع أمر تلقائي');
                } catch (\Throwable $txEx) {
                    throw new Exception($txEx->getMessage());
                }

                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => 'تم تحويل المعدة إلى وضع الصيانة ✅']);
                    exit;
                }
                $msg = 'تم تحويل المعدة إلى وضع الصيانة ✅';
                $is_success = true;
            } catch (Exception $ex) {
                $msg = $ex->getMessage() . ' ❌';
                $is_success = false;
                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
            }
        }

        // تبديل الحالة التشغيلية يدويًا: تعمل ⇄ جاهزة (يُمنع ضبط «معطلة» من هنا — لها مسار الصيانة).
        elseif ($action === 'set_op_state') {
            try {
                $op_id = intval($_POST['op_id'] ?? 0);
                if ($op_id <= 0) {
                    throw new Exception('معرف التشغيل غير صحيح');
                }
                $new_state = (isset($_POST['op_state']) && in_array($_POST['op_state'], ['تعمل', 'جاهزة'], true))
                    ? $_POST['op_state'] : null;
                if ($new_state === null) {
                    throw new Exception('حالة غير صالحة (يسمح ب «تعمل» أو «جاهزة» فقط)');
                }
                // اقرأ الحالة الحالية والمعدة قبل التحديث (لتسجيل التغيّر الفعلي).
                $old_op_state = null; $sos_equipment = 0;
                try {
                    $sos_row = $mvp_gate->selectOne('operations', array(
                        'columns' => array('equipment', 'op_state'),
                        'where'   => array('id' => $op_id, 'project_id' => $selected_project_id),
                    ));
                } catch (\Throwable $t) { $sos_row = null; }
                if ($sos_row) {
                    $sos_equipment = intval($sos_row['equipment']);
                    $old_op_state  = (string)$sos_row['op_state'];
                }

                // ORG-13 · حارس الأذونات: «تعمل» = إدخال للخدمة (§5-②) و«جاهزة» = إخراج منها (§5-③)
                if ($sos_equipment > 0 && $old_op_state !== null && $old_op_state !== $new_state) {
                    require_once dirname(__DIR__) . '/includes/permit_gate.php';
                    $pg_site = ems_default_site_of_project($conn, $company_id, $selected_project_id);
                    $pg = ems_permit_gate($conn, $company_id,
                        $new_state === 'تعمل' ? 'equipment_service_entry' : 'equipment_service_exit',
                        'EQ:' . $sos_equipment, $pg_site, intval($current_user_id ?? 0));
                    if (!$pg['ok']) { throw new Exception($pg['reason']); }
                }

                try {
                    $mvp_gate->update('operations',
                        array('op_state' => $new_state),
                        array('id' => $op_id, 'project_id' => $selected_project_id),
                        'status = 1');
                } catch (\Throwable $t) {
                    throw new Exception('تعذر تجهيز الاستعلام');
                }

                // سجل «تغيير حالة» عند التغيّر الفعلي فقط.
                if ($sos_equipment > 0 && $old_op_state !== null && $old_op_state !== $new_state) {
                    $log_opts = ['from' => $old_op_state, 'to' => $new_state, 'operation_id' => $op_id];
                    if ($company_id > 0) { $log_opts['company_id'] = $company_id; }
                    log_equipment_event($conn, $sos_equipment, 'تغيير حالة', $log_opts);
                }

                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'message' => 'تم تحديث الحالة ✅']);
                    exit;
                }
                $msg = 'تم تحديث الحالة ✅';
                $is_success = true;
            } catch (Exception $ex) {
                $msg = $ex->getMessage() . ' ❌';
                $is_success = false;
                if (isset($_POST['json']) && $_POST['json'] === '1') {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit;
                }
            }
        }

        // ملاحظة: لا يوجد إرجاع يدوي من الصيانة من شاشة الحركة — المعدة تعود «جاهزة» تلقائياً
        // عند إغلاق/إلغاء أمر الصيانة (mnt_return_equipment_available في Maintenance/mnt_helpers.php).
    }
}

// جلب البيانات (إحداثيات المعدات غير موجودة أصلًا — NULL AS كسلوك الأصل الفعلي)
try {
    $operations_rows = $mvp_gate->scopedQuery(array(
        'scope'  => array('o' => 'operations'),
        'enrich' => array('e' => 'equipments', 's' => 'suppliers'),
    ), "SELECT o.id, o.equipment, o.equipment_category, o.start, o.end, o.shift_type, o.status,
              o.op_state,
              o.total_equipment_hours, o.shift_hours,
              e.code AS equipment_code, e.name AS equipment_name,
              e.availability_status AS equipment_availability_status,
              et.type AS equipment_type_name,
              s.name AS supplier_name,
              NULL AS latitude,
              NULL AS longitude
       FROM operations o
       LEFT JOIN equipments e ON e.id = o.equipment
       LEFT JOIN equipments_types et ON et.id = e.type
       LEFT JOIN suppliers s ON s.id = o.supplier_id
       WHERE {TENANT_SCOPE} AND o.project_id = ?
       ORDER BY o.status DESC, o.id DESC", array($selected_project_id));
} catch (\Throwable $t) { $operations_rows = array(); }

$operations_rows_day = [];
$operations_rows_night = [];
foreach ($operations_rows as $op_row) {
    $shift_code = isset($op_row['shift_type']) ? strtoupper((string)$op_row['shift_type']) : 'B';
    if ($shift_code === 'D' || $shift_code === 'B') {
        $operations_rows_day[] = $op_row;
    }
    if ($shift_code === 'N' || $shift_code === 'B') {
        $operations_rows_night[] = $op_row;
    }
}

try {
    $drivers_rows = $mvp_gate->scopedQuery(array(
        'scope'  => array('ed' => 'equipment_drivers', 'd' => 'employees', 'e' => 'equipments'),
        'enrich' => array('operations' => 'operations'),
    ), "SELECT ed.id, ed.equipment_id, ed.employee_id, ed.start_date, ed.end_date, ed.status,
               ed.shift_type,
               d.name AS driver_name, d.phone AS driver_phone,
               e.code AS equipment_code, e.name AS equipment_name
        FROM equipment_drivers ed
        INNER JOIN employees d ON d.id = ed.employee_id
        INNER JOIN equipments e ON e.id = ed.equipment_id
        WHERE {TENANT_SCOPE} AND EXISTS (
            SELECT 1 FROM operations o
            WHERE o.equipment = ed.equipment_id
              AND o.project_id = ?
        )
        ORDER BY ed.status DESC, ed.id DESC", array($selected_project_id));
} catch (\Throwable $t) { $drivers_rows = array(); }

// تجميع السائقين حسب equipment_id للعرض المدمج داخل جداول التشغيل
$drivers_by_equipment = [];
foreach ($drivers_rows as $drv_item) {
    $eid = intval($drv_item['equipment_id']);
    if (!isset($drivers_by_equipment[$eid])) {
        $drivers_by_equipment[$eid] = [];
    }
    $drivers_by_equipment[$eid][] = $drv_item;
}

// عدّاد السائقين الفعّالين (status=1) لكل آلية — يُشتق من نفس قائمة السائقين المعروضة في الجدول الفرعي
// (المقيّدة بالشركة ووجود الموظف عبر JOIN employees)، فلا يتضخّم العدّاد عن المعروض كما كان يحدث
// حين كان يُحسب مباشرة من equipment_drivers دون تلك الفلاتر (يظهر «3» بلا سائقين معروضين).
$active_drivers_count_by_eq = [];
$ems_seen_active_driver = [];
foreach ($drivers_rows as $drv_item) {
    if (intval($drv_item['status']) !== 1) {
        continue;
    }
    $eid = intval($drv_item['equipment_id']);
    $did = intval($drv_item['employee_id']);
    $seen_key = $eid . ':' . $did;
    if (isset($ems_seen_active_driver[$seen_key])) {
        continue;
    }
    $ems_seen_active_driver[$seen_key] = true;
    $active_drivers_count_by_eq[$eid] = ($active_drivers_count_by_eq[$eid] ?? 0) + 1;
}

// قائمة المرشّحين لإسناد قيادة المعدّة: موظفون مسجَّلون كسائقين/مشغّلين فقط — أي لهم سجلٌ
// نشطٌ في equipment_operators (المرجع المعتمد، راجع Employees/equipment_operators.php:
// «جميع السائقين موظفون وليس كل الموظفين سائقين»). الجدول قائمٌ (حارس التوافق زال).
try {
    $all_drivers = $mvp_gate->scopedQuery(array(
        'scope'  => array('d' => 'employees'),
        'enrich' => array('equipment_operators' => 'equipment_operators'),
    ), "SELECT DISTINCT d.id, d.name, d.phone
        FROM employees d
        WHERE {TENANT_SCOPE}
          AND d.status = 1
          AND (d.project_id = ? OR d.project_id IS NULL)
          AND EXISTS (SELECT 1 FROM equipment_operators eo WHERE eo.employee_id = d.id AND eo.status = 1)
        ORDER BY d.name ASC", array($selected_project_id));
} catch (\Throwable $t) { $all_drivers = array(); }

// السائقون الذين لديهم تعيين ساري حالياً — يُستثنون من قوائم الإضافة
$active_driver_ids = [];
try {
    $adr_rows = $mvp_gate->scopedQuery(array(
        'scope' => array('equipment_drivers' => 'equipment_drivers'),
    ), "SELECT DISTINCT employee_id FROM equipment_drivers WHERE {TENANT_SCOPE} AND status = 1");
} catch (\Throwable $t) { $adr_rows = array(); }
foreach ($adr_rows as $adr) {
    $active_driver_ids[] = intval($adr['employee_id']);
}

try {
    $all_equipment = $mvp_gate->scopedQuery(array(
        'scope'  => array('e' => 'equipments'),
        'enrich' => array('operations' => 'operations'),
    ), "SELECT DISTINCT e.id, e.code, e.name
        FROM equipments e
        WHERE {TENANT_SCOPE} AND EXISTS (
          SELECT 1 FROM operations o
          WHERE o.equipment = e.id
            AND o.project_id = ?
        )
        ORDER BY e.code ASC", array($selected_project_id));
} catch (\Throwable $t) { $all_equipment = array(); }

// جلب العقود والموردين
try {
    $all_contracts = $mvp_gate->scopedQuery(array(
        'scope' => array('c' => 'contracts'),
    ), "SELECT DISTINCT c.id, c.contract_signing_date, c.id as contract_id
        FROM contracts c
        WHERE {TENANT_SCOPE} AND c.project_id = ?
          AND c.status = 1
        ORDER BY c.contract_signing_date DESC", array($selected_project_id));
} catch (\Throwable $t) { $all_contracts = array(); }

try {
    $all_suppliers = $mvp_gate->scopedQuery(array(
        'scope'  => array('s' => 'suppliers'),
        'enrich' => array('operations' => 'operations'),
    ), "SELECT DISTINCT s.id, s.name
        FROM suppliers s
        WHERE {TENANT_SCOPE} AND EXISTS (
          SELECT 1 FROM operations o
          WHERE o.supplier_id = s.id
            AND o.project_id = ?
        )
        ORDER BY s.name ASC", array($selected_project_id));
} catch (\Throwable $t) { $all_suppliers = array(); }

// خريطة
$map_rows = [];
$project_lat = isset($selected_project['latitude']) ? floatval($selected_project['latitude']) : 0;
$project_lng = isset($selected_project['longitude']) ? floatval($selected_project['longitude']) : 0;
foreach ($operations_rows as $op) {
    if (intval($op['status']) !== 1) {
        continue;
    }
    $lat = isset($op['latitude']) ? floatval($op['latitude']) : 0;
    $lng = isset($op['longitude']) ? floatval($op['longitude']) : 0;
    if ($lat == 0 || $lng == 0) {
        $lat = $project_lat;
        $lng = $project_lng;
    }
    if ($lat == 0 || $lng == 0) {
        continue;
    }

    $map_rows[] = [
        'equipment' => trim((string)$op['equipment_code']) . ' - ' . trim((string)$op['equipment_name']),
        'type' => isset($op['equipment_type_name']) ? $op['equipment_type_name'] : '-',
        'drivers' => intval($active_drivers_count_by_eq[intval($op['equipment'])] ?? 0),
        'shift' => isset($op['shift_type']) ? $op['shift_type'] : 'B',
        'lat' => $lat,
        'lng' => $lng,
    ];
}

$page_title = "الورديات";
// UXR P4: بذرُ محاورِ الغلافِ الحاكمِ CM-00 من الخادمِ قبل التصيير
require_once __DIR__ . '/../includes/screen_contract.php';
ems_shell_axes(null);
include '../inheader.php';
include '../insidebar.php';
require_once __DIR__ . '/../includes/screen_contract.php'; if (isset($conn)) { ems_screen_about_auto($conn); }
?>

<link rel="stylesheet" href="/ems/assets/vendor/datatables/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="../assets/css/main_admin_style.css">
<link href="/ems/assets/css/local-fonts.css" rel="stylesheet">

<style>
    .movement-unified-page {
        overflow-y: auto;
    }

    .movement-unified-page .main_head {
        margin-bottom: 20px;
    }

    .movement-unified-page .section-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--c-0b4c8c, #0b4c8c);
        margin: 20px 0 12px 0;
        border-right: 4px solid var(--c-0b4c8c, #0b4c8c);
        padding-right: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .movement-unified-page .card {
        margin-bottom: 20px;
        border: 1px solid var(--c-dae5f1, #dae5f1);
        border-radius: 12px;
        background: var(--c-fff, #fff);
    }

    .movement-unified-page .card-header {
        background: var(--c-f8fbff, #f8fbff);
        padding: 12px 15px;
        border-bottom: 1px solid var(--c-dae5f1, #dae5f1);
        border-radius: 12px 12px 0 0;
    }

    .movement-unified-page .card-header h5 {
        margin: 0;
        font-size: 16px;
        color: var(--c-0b4c8c, #0b4c8c);
        font-weight: 700;
    }

    .movement-unified-page .card-body {
        padding: 15px;
    }

    .movement-unified-page table {
        width: 100%;
        border-collapse: collapse;}

    .movement-unified-page table th {
        padding: 10px;
        text-align: right;
        border-bottom: 2px solid var(--c-d0deec, #d0deec);}

    .movement-unified-page table td {
        padding: 10px;
        text-align: right;}

    .movement-unified-page table input,
    .movement-unified-page table select {
        min-width: 100px;
        padding: 6px;
        border: 1px solid var(--c-cfe0f2, #cfe0f2);
        border-radius: 6px;
        font-size: 13px;
    }

    .movement-unified-page table input:disabled,
    .movement-unified-page table select:disabled {
        background: var(--c-f5f9ff, #f5f9ff);
        cursor: not-allowed;
        color: var(--c-999, #999);
    }

    .movement-unified-page .status-running {
        background: var(--c-d4edda, #d4edda);
        color: var(--c-155724, #155724);
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }

    .movement-unified-page .status-idle {
        background: var(--c-e8eef5, #e8eef5);
        color: var(--c-666, #666);
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
    }

    .movement-unified-page .status-maint {
        background: var(--c-fde7c8, #fde7c8);
        color: var(--c-92400e, #92400e);
        border: 1px solid var(--c-f0c98a, #f0c98a);
        padding: 3px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-right: 4px;
        white-space: nowrap;
    }

    .movement-unified-page .btn-primary {
        background: var(--c-0b4c8c, #0b4c8c);
        color: var(--c-fff, #fff);
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
    }

    .movement-unified-page .btn-primary:hover {
        background: var(--c-083a63, #083a63);
    }

    .movement-unified-page .btn-secondary {
        background: var(--c-dc2626, #dc2626);
        color: var(--c-fff, #fff);
        border: none;
        padding: 5px 10px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        margin-right: 4px;
    }

    .movement-unified-page .btn-secondary:hover {
        background: var(--c-b91c1c, #b91c1c);
    }

    .movement-unified-page .btn-secondary {
        background: var(--c-d97706, #d97706);
        color: var(--c-fff, #fff);
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        margin-right: 4px;
    }

    .movement-unified-page .btn-secondary:hover {
        background: var(--c-b45309, #b45309);
    }

    /* حاوية أزرار الإجراء (حفظ / صيانة / إنهاء) جنبًا إلى جنب */
    .movement-unified-page .row-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
    }

    .movement-unified-page .row-actions .btn-primary,
    .movement-unified-page .row-actions .btn-secondary,
    .movement-unified-page .row-actions .btn-secondary {
        margin-right: 0;
        white-space: nowrap;
    }

    .movement-unified-page .row-actions .op_state_sel {
        min-width: auto;
        padding: 5px 8px;
        font-size: 12px;
        font-weight: 600;
    }

    /* نص «في الصيانة» في عمود الإجراء لصفوف الآليات المعطلة (مدير الحركة لا يُرجعها يدويًا) */
    .movement-unified-page .op-maint-note {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: var(--c-92400e, #92400e);
        background: var(--c-fde7c8, #fde7c8);
        border: 1px solid var(--c-f0c98a, #f0c98a);
        padding: 5px 12px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 700;
        white-space: nowrap;
    }

    /* شارة الحالة التشغيلية الموحّدة (تعمل / جاهزة / معطلة) */
    .movement-unified-page .op-state-badge {
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 700;
        display: inline-block;
        white-space: nowrap;
    }

    .movement-unified-page .op-state-badge.op-state-working {
        background: var(--c-d4edda, #d4edda);
        color: var(--c-155724, #155724);
    }

    .movement-unified-page .op-state-badge.op-state-ready {
        background: var(--c-fde7c8, #fde7c8);
        color: var(--c-92400e, #92400e);
        border: 1px solid var(--c-f0c98a, #f0c98a);
    }

    .movement-unified-page .op-state-badge.op-state-broken {
        background: var(--c-f8d7da, #f8d7da);
        color: var(--c-b91c1c, #b91c1c);
        border: 1px solid var(--c-f1aeb5, #f1aeb5);
    }

    .movement-unified-page #monitoringMap {
        height: 400px;
        border: 1px solid var(--c-d5e2ef, #d5e2ef);
        border-radius: 12px;
        margin-bottom: 20px;
    }

    .movement-unified-page .form-grid {
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }

    .movement-unified-page .form-group {
        display: flex;
        flex-direction: column;
    }

    .movement-unified-page .form-group label {
        font-size: 13px;
        font-weight: 600;
        color: var(--c-0b4c8c, #0b4c8c);
        margin-bottom: 4px;
    }

    .movement-unified-page .form-group input,
    .movement-unified-page .form-group select {
        padding: 8px;
        border: 1px solid var(--c-cfe0f2, #cfe0f2);
        border-radius: 6px;
        font-size: 13px;
    }

    .movement-unified-page .btn-primary {
        background: var(--c-28a745, #28a745);
        color: var(--c-fff, #fff);
        border: none;
        padding: 12px 24px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 700;
        margin-top: 12px;
    }

    .movement-unified-page .btn-primary:hover {
        background: var(--c-218838, #218838);
    }

    .movement-unified-page .success-message {
        padding: 12px 15px;
        border-radius: 6px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .movement-unified-page .success-message.is-success {
        background: var(--c-d4edda, #d4edda);
        color: var(--c-155724, #155724);
        border: 1px solid var(--c-c3e6cb, #c3e6cb);
    }

    .movement-unified-page .success-message.is-error {
        background: var(--c-f8d7da, #f8d7da);
        color: var(--c-721c24, #721c24);
        border: 1px solid var(--c-f5c6cb, #f5c6cb);
    }

    .movement-unified-page .table-scroll {
        overflow-x: auto;
        margin-bottom: 12px;
    }

    .movement-unified-page .collapse-section {
        display: none;
    }

    .movement-unified-page .collapse-section.show {
        display: block;
    }

    .movement-unified-page .collapse-btn {
        background: var(--c-0b4c8c, #0b4c8c);
        color: var(--c-fff, #fff);
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .movement-unified-page .collapse-btn:hover {
        background: var(--c-083a63, #083a63);
    }

    /* صفوف السائقين المدمجة */
    .movement-unified-page .drivers-sub-row > td {
        background: var(--c-f4f8fd, #f4f8fd);
        padding: 0;}

    .movement-unified-page .sub-drivers-wrap {
        padding: 14px 18px;
    }

    .movement-unified-page .sub-drivers-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 14px;
        font-size: 13px;
        overflow: hidden;}

    .movement-unified-page .sub-drivers-table th {
        padding: 8px 10px;
        text-align: right;
        border-bottom: 1px solid var(--c-b8ceea, #b8ceea);}

    .movement-unified-page .sub-drivers-table td {
        padding: 8px 10px;
        background: var(--c-fff, #fff);}

    .movement-unified-page .btn-secondary {
        background: var(--c-0b4c8c, #0b4c8c);
        color: var(--c-fff, #fff);
        border: none;
        padding: 5px 11px;
        border-radius: 20px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: background 0.2s;
        white-space: nowrap;
    }

    .movement-unified-page .btn-secondary:hover {
        background: var(--c-083a63, #083a63);
    }

    .movement-unified-page .btn-secondary .toggle-icon {
        transition: transform 0.25s;
        font-size: 10px;
    }

    .movement-unified-page .btn-secondary.open .toggle-icon {
        transform: rotate(180deg);
    }

    .movement-unified-page .add-driver-inline {
        background: var(--c-eef6ff, #eef6ff);
        border: 1px dashed var(--c-7aadd4, #7aadd4);
        border-radius: 8px;
        padding: 12px 16px;
        margin-top: 6px;
    }

    .movement-unified-page .add-driver-inline > strong {
        display: block;
        color: var(--c-0b4c8c, #0b4c8c);
        margin-bottom: 10px;
        font-size: 13px;
    }

    .movement-unified-page .inline-form-row {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: flex-end;
    }

    .movement-unified-page .inline-form-row .form-group {
        flex: 1;
        min-width: 140px;
    }

    /* تبويبات الورديات (نهار/ليل) */
    .movement-unified-page .ems-tabs-nav {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        border-bottom: 2px solid var(--c-dae5f1, #dae5f1);
        margin-bottom: 18px;
    }

    .movement-unified-page .ems-tab-btn {
        background: var(--c-f0f5fa, #f0f5fa);
        color: var(--c-0b4c8c, #0b4c8c);
        border: 1px solid var(--c-dae5f1, #dae5f1);
        border-bottom: none;
        padding: 11px 26px;
        border-radius: 10px 10px 0 0;
        cursor: pointer;
        font-size: 15px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-bottom: -2px;
        transition: background 0.2s, color 0.2s;
    }

    .movement-unified-page .ems-tab-btn:hover {
        background: var(--c-e3eef9, #e3eef9);
    }

    .movement-unified-page .ems-tab-btn.active {
        background: var(--c-0b4c8c, #0b4c8c);
        color: var(--c-fff, #fff);
        border-color: var(--c-0b4c8c, #0b4c8c);
    }

    .movement-unified-page .ems-tab-count {
        background: var(--c-rgba1176140012, rgba(11, 76, 140, 0.12));
        color: var(--c-0b4c8c, #0b4c8c);
        border-radius: 12px;
        padding: 1px 9px;
        font-size: 12px;
        font-weight: 700;
        min-width: 20px;
        text-align: center;
    }

    .movement-unified-page .ems-tab-btn.active .ems-tab-count {
        background: var(--c-d4a017, #d4a017);
        color: var(--c-fff, #fff);
    }

    .movement-unified-page .ems-tab-panel {
        display: none;
    }

    .movement-unified-page .ems-tab-panel.active {
        display: block;
    }

    /* UXW-01 ②: أنماطٌ ثابتةٌ نُقلت من سماتِ style الموضعيةِ إلى أصنافِ الشاشة */
    .movement-unified-page td.mvun-empty-cell {
        text-align: center;
        padding: 14px;}

    .movement-unified-page td.mvun-empty-cell-sm {
        text-align: center;
        padding: 10px;}

    .movement-unified-page .form-group.mvun-fg-end {
        justify-content: flex-end;
    }

    .movement-unified-page .btn-primary.mvun-add-btn {
        padding: 8px 16px;
        font-size: 13px;
        margin-top: 0;
    }

    .movement-unified-page .card-body.mvun-body-flush {
        padding: 0;
    }
</style>

<div class="main movement-page movement-unified-page">
    <?php
    // Unified page header (structure: includes/page_header.php · styling: ems.main.all.style.css)
    $header_icon       = 'fas fa-route';
    $header_title_html = 'توزيع المعدات <i class="fas fa-project-diagram"></i> ' . htmlspecialchars($selected_project['name']);
    $header_actions = array(
        array('href' => '../main/dashboard.php', 'class' => 'movement-topbar-btn', 'icon' => 'fas fa-home', 'label' => 'لوحة التحكم'),
    );
    $header_back = array('href' => '../main/dashboard.php', 'icon' => 'fas fa-arrow-right', 'label' => 'رجوع');
    include(__DIR__ . '/../includes/page_header.php');
    // UXW-01 ⑨: حالاتُ الشاشةِ الدنيا (تحميل · فراغ · خطأ) — مخفيةٌ افتراضًا
    echo ems_states_bundle('لا معدات موزعة على ورديات هذا المشروع بعد', 'أضف أول تشغيل آلية من شاشة «إدارة التشغيل» ثم عد لمتابعة الورديات هنا');
    // TKT-15 · زر الإبلاغ السياقي الغني — التايم شيت والتشغيل اليومي (§2-①)
    require_once __DIR__ . '/../includes/report_button.php';
    require_once __DIR__ . '/../includes/permit_gate.php'; // لدالة موقع المشروع الافتراضي
    ems_report_button(array('screen' => 'movement', 'project_id' => $selected_project_id ?? null,
        'site_id' => !empty($selected_project_id) ? ems_default_site_of_project($conn, $company_id, $selected_project_id) : null));
    ?>

    <div class="ems-content">
        <?php if ($msg !== ''): ?>
            <div class="success-message <?php echo $is_success ? 'is-success' : 'is-error'; ?>">
                <i class="fas <?php echo $is_success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php
        // تبويب التشغيلات حسب الوردية (نهار/ليل): تاب لكل وردية، وجدول موحّد يضم كل الآليات دون تقسيم حسب الفئة.
        $operations_tables = [
            ['key' => 'day',   'title' => 'إدارة تشغيلات النهار', 'icon' => 'fa-sun',  'table_key' => 'day',   'rows' => $operations_rows_day],
            ['key' => 'night', 'title' => 'إدارة تشغيلات الليل',  'icon' => 'fa-moon', 'table_key' => 'night', 'rows' => $operations_rows_night],
        ];
        ?>
        <div class="ems-tabs">
            <div class="ems-tabs-nav">
                <?php foreach ($operations_tables as $tab_i => $tab_item): ?>
                    <button type="button"
                            class="ems-tab-btn<?php echo $tab_i === 0 ? ' active' : ''; ?>"
                            data-tab="<?php echo $tab_item['key']; ?>"
                            onclick="switchShiftTab('<?php echo $tab_item['key']; ?>')">
                        <i class="fas <?php echo $tab_item['icon']; ?>"></i>
                        <?php echo htmlspecialchars($tab_item['title'], ENT_QUOTES, 'UTF-8'); ?>
                        <span class="ems-tab-count"><?php echo count($tab_item['rows']); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <?php foreach ($operations_tables as $tab_i => $operations_table): ?>
            <div class="ems-tab-panel<?php echo $tab_i === 0 ? ' active' : ''; ?>" id="tab_<?php echo $operations_table['key']; ?>">
        <?php
        // داخل كل وردية: الآليات السارية مقسّمة حسب الحالة (تعمل / جاهزة / معطلة) ثم التشغيلات المنتهية.
        $ems_active_rows = array_values(array_filter($operations_table['rows'], function ($r) { return intval($r['status']) === 1; }));
        $ems_ended_rows  = array_values(array_filter($operations_table['rows'], function ($r) { return intval($r['status']) !== 1; }));
        $ems_state_of = function ($r) { return (isset($r['op_state']) && $r['op_state'] !== '') ? $r['op_state'] : 'جاهزة'; };
        $ems_working = array_values(array_filter($ems_active_rows, function ($r) use ($ems_state_of) { return $ems_state_of($r) === 'تعمل'; }));
        $ems_ready   = array_values(array_filter($ems_active_rows, function ($r) use ($ems_state_of) { return $ems_state_of($r) === 'جاهزة'; }));
        $ems_broken  = array_values(array_filter($ems_active_rows, function ($r) use ($ems_state_of) { return $ems_state_of($r) === 'معطلة'; }));
        $status_groups = [
            ['key' => 'working', 'label' => 'الآليات التي تعمل', 'icon' => 'fa-play-circle',         'empty' => 'لا توجد آليات تعمل',     'rows' => $ems_working],
            ['key' => 'ready',   'label' => 'الآليات الجاهزة',   'icon' => 'fa-clock',                'empty' => 'لا توجد آليات جاهزة',    'rows' => $ems_ready],
            ['key' => 'broken',  'label' => 'الآليات المعطلة',   'icon' => 'fa-triangle-exclamation', 'empty' => 'لا توجد آليات معطلة',    'rows' => $ems_broken],
            ['key' => 'ended',   'label' => 'التشغيلات المنتهية', 'icon' => 'fa-flag-checkered',       'empty' => 'لا توجد تشغيلات منتهية', 'rows' => $ems_ended_rows],
        ];
        foreach ($status_groups as $sgrp):
            $show_drivers = ($sgrp['key'] !== 'ended'); // عمود السائقين يظهر في الجداول السارية فقط، لا في المنتهية
            $col_count    = $show_drivers ? 7 : 6;
        ?>
        <div class="card">
            <div class="card-header">
                <h5><i class="fas <?php echo $sgrp['icon']; ?>"></i> <?php echo htmlspecialchars($operations_table['title'] . ' — ' . $sgrp['label'], ENT_QUOTES, 'UTF-8'); ?></h5>
            </div>
            <div class="card-body">
                <div class="table-scroll">
                    <table class="no-datatable">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>الآلية</th>
                                <th>النوع</th>
                                <th>الوردية</th>
                                <?php if ($show_drivers): ?><th>السائقون</th><?php endif; ?>
                                <th>الحالة</th>
                                <th>إجراء</th>
                                                <!-- U10-B12: النواة الحاكمة (الخلايا يحشوها ui-unification.js) -->
                    <th class="ems-gov-th" data-gov="entity" data-slice="1" title="عزل الشركات — لا صف بلا كيان مالك">الكيان</th>
                    <th class="ems-gov-th" data-gov="creator" data-slice="1" title="من أنشأ السجل وبأي صفة">المنشئ — الاسم والصفة</th>
                    <th class="ems-gov-th" data-gov="created_at" data-slice="1" title="لحظة الإنشاء">تاريخ الإنشاء</th>
                    <th class="ems-gov-th" data-gov="parent_ref" data-slice="1" title="السجل الذي تولد عنه">المرجع الأب</th>
</tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sgrp['rows'])): ?>
                                <tr><td class="mvun-empty-cell" colspan="<?php echo $col_count; ?>"><?php echo htmlspecialchars($sgrp['empty']); ?></td></tr>
                            <?php else: ?>
                                <?php $idx = 1; foreach ($sgrp['rows'] as $op):
                                    $op_id      = intval($op['id']);
                                    $eq_id      = intval($op['equipment']);
                                    $status     = intval($op['status']);
                                    $shift      = isset($op['shift_type']) ? $op['shift_type'] : 'B';
                                    $is_running = ($status === 1);
                                    $op_state   = (isset($op['op_state']) && $op['op_state'] !== '') ? $op['op_state'] : 'جاهزة';
                                    $is_broken  = ($op_state === 'معطلة');
                                    $shift_label = ($shift === 'D') ? 'نهاري' : (($shift === 'N') ? 'ليلي' : 'نهاري + ليلي');
                                    $eq_drivers  = isset($drivers_by_equipment[$eq_id]) ? $drivers_by_equipment[$eq_id] : [];
                                    $tkey        = $operations_table['table_key'];
                                ?>
                                <!-- صف الآلية الرئيسي -->
                                <tr id="op_row_<?php echo $tkey; ?>_<?php echo $op_id; ?>">
                                    <td><?php echo $idx++; ?></td>
                                    <td><?php echo htmlspecialchars(($op['equipment_code'] ?? '-') . ' - ' . ($op['equipment_name'] ?? '-')); ?></td>
                                    <td><?php echo htmlspecialchars($op['equipment_type_name'] ?? '-'); ?></td>
                                    <td>
                                        <?php if ($is_running && $can_edit): ?>
                                            <select class="op_shift" aria-label="نظام وردية الآلية" data-op="<?php echo $op_id; ?>">
                                                <option value="D" <?php echo $shift === 'D' ? 'selected' : ''; ?>>نهاري</option>
                                                <option value="N" <?php echo $shift === 'N' ? 'selected' : ''; ?>>ليلي</option>
                                                <option value="B" <?php echo $shift === 'B' ? 'selected' : ''; ?>>نهاري + ليلي</option>
                                            </select>
                                        <?php else: ?>
                                            <span><?php echo htmlspecialchars($shift_label); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($show_drivers): ?>
                                    <td>
                                        <button type="button"
                                                class="btn-secondary"
                                                id="toggle_btn_<?php echo $tkey; ?>_<?php echo $op_id; ?>"
                                                onclick="toggleDrivers('<?php echo $tkey; ?>', <?php echo $op_id; ?>)">
                                            <i class="fas fa-users"></i>
                                            <?php echo intval($active_drivers_count_by_eq[$eq_id] ?? 0); ?>
                                            <i class="fas fa-chevron-down toggle-icon"></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if ($is_running): ?>
                                            <?php $state_class = ($op_state === 'تعمل') ? 'op-state-working' : (($op_state === 'معطلة') ? 'op-state-broken' : 'op-state-ready'); ?>
                                            <span class="op-state-badge <?php echo $state_class; ?>"><?php echo htmlspecialchars($op_state); ?></span>
                                        <?php else: ?>
                                            <span class="status-idle">منتهي</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_running && $is_broken): ?>
                                            <span class="op-maint-note"><i class="fas fa-wrench"></i> في الصيانة</span>
                                        <?php elseif ($is_running && $can_edit): ?>
                                            <div class="row-actions">
                                                <select class="op_state_sel" aria-label="الحالة التشغيلية" title="الحالة التشغيلية" data-op="<?php echo $op_id; ?>" onchange="setOpState(<?php echo $op_id; ?>, this.value)">
                                                    <option value="تعمل" <?php echo $op_state === 'تعمل' ? 'selected' : ''; ?>>تعمل</option>
                                                    <option value="جاهزة" <?php echo $op_state === 'جاهزة' ? 'selected' : ''; ?>>جاهزة</option>
                                                </select>
                                                <button type="button" class="btn-primary" onclick="saveOperation(<?php echo $op_id; ?>, this)"><i class="fas fa-save"></i> حفظ</button>
                                                <button type="button" class="btn-secondary" onclick="setMaintenance(<?php echo $eq_id; ?>, this)" title="تحويل المعدة إلى وضع الصيانة (يفتح أمر صيانة تلقائي)"><i class="fas fa-wrench"></i> صيانة</button>
                                                <button type="button" class="btn-secondary" onclick="endOperation(<?php echo $op_id; ?>, this)"><i class="fas fa-stop-circle"></i> إنهاء</button>
                                            </div>
                                        <?php else: ?>
                                            <span>-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if ($show_drivers): ?>
                                <!-- صف السائقين القابل للتوسيع -->
                                <tr id="op_drivers_<?php echo $tkey; ?>_<?php echo $op_id; ?>" class="drivers-sub-row is-hidden">
                                    <td colspan="7">
                                        <div class="sub-drivers-wrap">
                                            <?php
                                            /* ══ جدولُ السائقين يُبنى عند الفتحِ لا مع الصفحة ═══════════════════
                                               ◆ **السببُ الثاني للتجمُّد** (مقيسٌ في المتصفح): الصفحةُ كانت تُصيِّر
                                                 **جدولًا داخلَ كلِّ صفٍّ** — ٤٤٠ جدولًا و٢١ ألفَ عقدةٍ لمشروعٍ
                                                 بـ٢١٦ عملية. وطبقةُ الجداولِ المشتركةِ (`ui-unification.js`) تكنس
                                                 **كلَّ جداولِ الصفحة** في `sweepTables`، وتُعيد الكنسَ عند كلِّ
                                                 تغيُّرٍ في DOM عبر `MutationObserver` على الجسمِ كلِّه. فالكنسةُ
                                                 الواحدةُ على ٤٤٠ جدولًا تُعيد نفسَها بلا انتهاء ويتجمّد المُصيِّر.
                                                 (البرهان: الصفحةُ نفسُها **بلا أيِّ سكربت** تُحمَّل في ١٫٦ ثانية.)
                                               ◆ **والعلاج بلا مساسٍ بالطبقةِ المشتركة** (تخدم ٤٠٠ شاشة): تُحمَل
                                                 بياناتُ سائقي الآليةِ في سمةٍ واحدةٍ، ويُبنى الجدولُ **لحظةَ
                                                 فتحِه** — فتنزل جداولُ الصفحةِ من ٤٤٠ إلى ثمانية.
                                               ◆ صفرُ تغييرٍ في البيانات المعروضةِ ولا في الأفعال. */
                                            $mvun_drv_payload = array();
                                            foreach ($eq_drivers as $drv) {
                                                $mvun_drv_payload[] = array(
                                                    'id' => intval($drv['id']),
                                                    'n'  => (string) ($drv['driver_name'] ?? '-'),
                                                    'p'  => (string) ($drv['driver_phone'] ?? '-'),
                                                    's'  => (string) (isset($drv['shift_type']) ? $drv['shift_type'] : 'B'),
                                                    'a'  => intval($drv['status']) === 1 ? 1 : 0,
                                                );
                                            }
                                            ?>
                                            <div class="mvun-drivers-slot"
                                                 data-mvun-drv="<?php echo htmlspecialchars(json_encode($mvun_drv_payload, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>"
                                                 data-mvun-edit="<?php echo $can_edit ? 1 : 0; ?>"></div>

                                            <?php if ($can_add && $is_running): ?>
                                            <?php
                                            /* ══ نموذجُ «إضافةِ سائق» لا يُطبع مع كلِّ آلية ═══════════════════════
                                               ◆ **المقيس**: النموذجُ ٣٫١ كيلوبايت، وفيه قائمةُ السائقين — وقائمتُها
                                                 **واحدةٌ للصفحةِ كلِّها** (`$active_driver_ids` عامٌّ لا يتغيّر بالصف).
                                                 ومشروعٌ بـ٢١٦ عمليةً كلُّها بوردية `B` يُصيَّر في **جدولَي النهارِ
                                                 والليل** = ٤٣٢ صفًّا × ٢٩٣ خيارًا = **١٢٦٫٥٧٦ عنصرَ `<option>`**،
                                                 فبلغ مخرَجُ الصفحةِ **٣٨ ميجابايت**. وعندها يتسلَّم
                                                 `ems_inject_csrf_fields` المخزَنَ كلَّه ليمسحَه فيطلب نسخةً ثانية،
                                                 فينفد حدُّ الذاكرةِ (١٢٨ ميجابايت) وتخرج الصفحةُ **500 بلا جسد** —
                                                 وهو «تفتح تقيلة… وأحيانًا لا تفتح».
                                               ◆ **والعلاج**: مَربطٌ خفيفٌ هنا، والنموذجُ كاملًا في قالبٍ واحدٍ أسفلَ
                                                 الصفحة يُبنى **لحظةَ فتحِ جدولِ سائقي الآلية** لا قبلَها. صفرُ تغييرٍ
                                                 في الخيارات ولا في الاستبعادِ ولا في ما يُرسَل عند الحفظ.
                                               ◆ **والمعرِّفاتُ صارت فريدة**: كانت `emsf_737_5def5` وأخواتُها ثابتةً
                                                 تتكرر في كلِّ صفّ، فـ`<label for>` يشير إلى حقلِ صفٍّ آخرَ دائمًا. */
                                            ?>
                                            <div class="add-driver-slot" data-mvun-eq="<?php echo $eq_id; ?>"
                                                 data-mvun-fid="<?php echo htmlspecialchars($tkey . '_' . $op_id, ENT_QUOTES, 'UTF-8'); ?>"></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        </div>

        <!-- خريطة المراقبة -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-map-marked-alt"></i> خريطة المراقبة</h5>
            </div>
            <div class="card-body mvun-body-flush">
                <div id="monitoringMap"></div>
            </div>
        </div>
    </div>
</div>

<?php
/* ══ قائمةُ السائقين المتاحين — تُطبع **مرّةً واحدةً للصفحة** ═══════════════
   الاستبعادُ (`$active_driver_ids`) عامٌّ لا يختلف بصفّ، فلا معنى لتكرارِ
   القائمةِ مع كلِّ آلية. و`<template>` لا يُصيَّر ولا يُحمِّل شيئًا حتى يُستنسَخ. */
?>
<template id="mvunDriverOptions">
    <?php foreach ($all_drivers as $d): ?>
        <?php if (in_array(intval($d['id']), $active_driver_ids)) continue; ?>
        <option value="<?php echo intval($d['id']); ?>"><?php echo htmlspecialchars($d['name'] . ' - ' . $d['phone'], ENT_QUOTES, 'UTF-8'); ?></option>
    <?php endforeach; ?>
</template>

<?php /* نموذجُ «إضافةِ سائق» — نصٌّ لا يُصيَّر؛ `__FID__` يُستبدل عند البناء */ ?>
<script type="text/html" id="mvunAddDriverTpl">
    <div class="add-driver-inline">
        <strong><i class="fas fa-plus-circle"></i> إضافة سائق لهذه الآلية</strong>
        <form class="add-driver-form">
            <div class="inline-form-row">
                <div class="form-group">
                    <label for="emsf_drv___FID__">السائق *</label>
                    <select name="employee_id" required id="emsf_drv___FID__" class="mvun-driver-picker" data-mvun-filled="0">
                        <option value="">-- اختر السائق --</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="emsf_shift___FID__">الوردية</label>
                    <select name="shift_type" id="emsf_shift___FID__">
                        <option value="D">نهاري</option>
                        <option value="N">ليلي</option>
                        <option value="B" selected>نهاري + ليلي</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="emsf_sd___FID__">بداية التعيين</label>
                    <input type="date" name="start_date" id="emsf_sd___FID__" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label for="emsf_ed___FID__">نهاية التعيين</label>
                    <input type="date" name="end_date" id="emsf_ed___FID__">
                </div>
                <div class="form-group mvun-fg-end">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-primary mvun-add-btn"><i class="fas fa-plus"></i> إضافة</button>
                </div>
            </div>
        </form>
    </div>
</script>

<script src="/ems/assets/vendor/jquery-3.7.1.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    /* ══ ملءُ قائمةِ السائقين عند الحاجةِ لا مع كلِّ صفّ ══════════════════════
       القائمةُ واحدةٌ للصفحةِ كلِّها، وكانت تُطبع داخلَ كلِّ صفٍّ فتضخَّم المخرَجُ
       إلى ثمانيةِ ميجابايتَ من `<option>` وحدَها. الآن تُطبع مرّةً في القالبِ
       أدناه، وتُنسَخ إلى قائمةِ الصفِّ **أوّلَ مرّةٍ يُفتح جدولُ سائقيه** — وما
       لا يُفتح لا يُنسَخ إليه شيء. */
    function mvunFillDriverPicker(scope) {
        var tpl = document.getElementById('mvunDriverOptions');
        if (!tpl || !scope) { return; }
        var sels = scope.querySelectorAll('select.mvun-driver-picker[data-mvun-filled="0"]');
        if (!sels.length) { return; }
        Array.prototype.forEach.call(sels, function (sel) {
            sel.appendChild(tpl.content.cloneNode(true));
            sel.setAttribute('data-mvun-filled', '1');
        });
    }

    /** بناءُ جدولِ سائقي الآليةِ من حمولتِه — مرّةً واحدةً عند أوّلِ فتح. */
    function mvunBuildDriversTable(scope) {
        if (!scope) { return; }
        var slots = scope.querySelectorAll('.mvun-drivers-slot:empty');
        if (!slots.length) { return; }
        var esc = function (v) {
            return String(v === null || v === undefined ? '' : v)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        };
        var shiftLabel = function (s) { return s === 'D' ? 'نهاري' : (s === 'N' ? 'ليلي' : 'نهاري + ليلي'); };
        Array.prototype.forEach.call(slots, function (slot) {
            var rows = [];
            try { rows = JSON.parse(slot.getAttribute('data-mvun-drv') || '[]') || []; } catch (e) { rows = []; }
            var canEdit = slot.getAttribute('data-mvun-edit') === '1';
            var body = '';
            if (!rows.length) {
                body = '<tr><td class="mvun-empty-cell-sm" colspan="6">لا يوجد سائقون مرتبطون بهذه الآلية</td></tr>';
            } else {
                rows.forEach(function (d, i) {
                    var live = d.a === 1;
                    var shiftCell = (live && canEdit)
                        ? '<select class="drv_shift" aria-label="وردية السائق" data-rel="' + d.id + '">'
                          + '<option value="D"' + (d.s === 'D' ? ' selected' : '') + '>نهاري</option>'
                          + '<option value="N"' + (d.s === 'N' ? ' selected' : '') + '>ليلي</option>'
                          + '<option value="B"' + (d.s === 'B' ? ' selected' : '') + '>نهاري + ليلي</option></select>'
                        : '<span>' + esc(shiftLabel(d.s)) + '</span>';
                    var actCell = (live && canEdit)
                        ? '<button type="button" class="btn-primary" onclick="saveDriver(' + d.id + ')"><i class="fas fa-save"></i> حفظ</button>'
                          + '<button type="button" class="btn-secondary" onclick="endDriver(' + d.id + ')"><i class="fas fa-stop-circle"></i> إنهاء</button>'
                        : '<span>-</span>';
                    body += '<tr id="drv_row_' + d.id + '">'
                          + '<td>' + (i + 1) + '</td>'
                          + '<td>' + esc(d.n) + '</td>'
                          + '<td>' + esc(d.p) + '</td>'
                          + '<td>' + shiftCell + '</td>'
                          + '<td>' + (live ? '<span class="status-running">ساري</span>' : '<span class="status-idle">منتهي</span>') + '</td>'
                          + '<td>' + actCell + '</td></tr>';
                });
            }
            /* ◆ `data-no-dt="hard"` هو **باب الخروج الصريح** الذي تعترف به
                 `ui-unification.js`؛ و`no-datatable` وحده صار **استشاريا**
                 (لا يمنع إلا مع تهيئة يدوية في الصفحة). ولولا التصريح
                 لهيئت DataTables على كل جدول سائقين — بحث وترقيم وغلاف
                 لجدول من صف أو صفين، وهو أصل الجمود حين كانت ٤٣٢ جدولا. */
            slot.innerHTML = '<table class="sub-drivers-table no-datatable" data-no-dt="hard" data-ems-empty="off">'
                + '<thead><tr><th>#</th><th>السائق</th><th>الهاتف</th><th>الوردية</th><th>الحالة</th><th>إجراء</th></tr></thead>'
                + '<tbody>' + body + '</tbody></table>';
        });
    }

    /** بناء نموذج «إضافة سائق» في مربطه — مرة واحدة لكل آلية عند أول فتح. */
    function mvunBuildAddDriver(scope) {
        var tplEl = document.getElementById('mvunAddDriverTpl');
        if (!tplEl || !scope) { return; }
        var slots = scope.querySelectorAll('.add-driver-slot:empty');
        if (!slots.length) { return; }
        var raw = tplEl.textContent;
        Array.prototype.forEach.call(slots, function (slot) {
            var fid = slot.getAttribute('data-mvun-fid') || '';
            var eq  = parseInt(slot.getAttribute('data-mvun-eq') || '0', 10);
            slot.innerHTML = raw.split('__FID__').join(fid);
            var form = slot.querySelector('form.add-driver-form');
            /* الحدث يربط في JS لا في `onsubmit`: النموذج نص في قالب واحد،
               ومعرف الآلية يأتي من المربط لا من طباعة الخادم في كل صف. */
            if (form) {
                form.addEventListener('submit', function (e) { submitAddDriver(e, form, eq); });
            }
        });
    }

    function toggleDrivers(tableKey, opId) {
        var subRow = document.getElementById('op_drivers_' + tableKey + '_' + opId);
        var btn    = document.getElementById('toggle_btn_' + tableKey + '_' + opId);
        if (!subRow) return;
        var isOpen = !subRow.classList.contains('is-hidden');
        if (!isOpen) {                       /* قبل الإظهار لا بعده */
            mvunBuildDriversTable(subRow);
            mvunBuildAddDriver(subRow);
            mvunFillDriverPicker(subRow);
        }
        subRow.classList.toggle('is-hidden', isOpen);
        if (btn) btn.classList.toggle('open', !isOpen);
    }

    function switchShiftTab(key) {
        document.querySelectorAll('.movement-unified-page .ems-tab-btn').forEach(function (b) {
            b.classList.toggle('active', b.getAttribute('data-tab') === key);
        });
        document.querySelectorAll('.movement-unified-page .ems-tab-panel').forEach(function (p) {
            p.classList.toggle('active', p.id === 'tab_' + key);
        });
    }

    function saveOperation(opId, triggerBtn) {
        var row = triggerBtn ? triggerBtn.closest('tr') : null;
        var get = function(sel) {
            var el = (row && row.querySelector(sel)) || document.querySelector(sel);
            return el ? el.value : '';
        };
        var formData = new FormData();
        formData.append('action', 'save_single_operation');
        formData.append('op_id', opId);
        formData.append('shift_type', get('.op_shift[data-op="' + opId + '"]') || 'B');
        formData.append('status', 1);
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? (alert('✅ تم الحفظ'), location.reload()) : alert('❌ ' + d.message); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); });
    }

    function setMaintenance(equipmentId, triggerBtn) {
        if (!confirm('هل تريد تحويل هذه المعدة إلى وضع الصيانة؟ ستصبح «معطلة» ويفتح أمر صيانة تلقائي.')) return;
        if (triggerBtn) triggerBtn.disabled = true;
        var formData = new FormData();
        formData.append('action', 'set_equipment_maintenance');
        formData.append('equipment_id', equipmentId);
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? (alert('✅ ' + d.message), location.reload()) : (alert('❌ ' + d.message), triggerBtn && (triggerBtn.disabled = false)); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); if (triggerBtn) triggerBtn.disabled = false; });
    }

    function setOpState(opId, newState) {
        var formData = new FormData();
        formData.append('action', 'set_op_state');
        formData.append('op_id', opId);
        formData.append('op_state', newState);
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? location.reload() : alert('❌ ' + d.message); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); });
    }

    function endOperation(opId, triggerBtn) {
        if (!confirm('هل تريد إنهاء هذا التشغيل؟')) return;
        var row = triggerBtn ? triggerBtn.closest('tr') : null;
        var get = function(sel) {
            var el = (row && row.querySelector(sel)) || document.querySelector(sel);
            return el ? el.value : '';
        };
        var formData = new FormData();
        formData.append('action', 'save_single_operation');
        formData.append('op_id', opId);
        formData.append('shift_type', get('.op_shift[data-op="' + opId + '"]') || 'B');
        formData.append('status', 0);
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? (alert('✅ تم إنهاء التشغيل'), location.reload()) : alert('❌ ' + d.message); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); });
    }

    function saveDriver(relId) {
        var get = function(sel) { var el = document.querySelector(sel); return el ? el.value : ''; };
        var formData = new FormData();
        formData.append('action', 'save_single_driver');
        formData.append('rel_id', relId);
        formData.append('shift_type', get('.drv_shift[data-rel="' + relId + '"]') || 'B');
        formData.append('status', 1);
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? (alert('✅ تم الحفظ'), location.reload()) : alert('❌ ' + d.message); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); });
    }

    function endDriver(relId) {
        if (!confirm('هل تريد إنهاء تشغيل هذا السائق؟')) return;
        var shiftEl = document.querySelector('.drv_shift[data-rel="' + relId + '"]');
        var formData = new FormData();
        formData.append('action', 'save_single_driver');
        formData.append('rel_id', relId);
        formData.append('shift_type', shiftEl ? shiftEl.value : 'B');
        formData.append('status', 0);
        formData.append('end_date', new Date().toISOString().split('T')[0]);
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? (alert('✅ تم إنهاء تشغيل السائق'), location.reload()) : alert('❌ ' + d.message); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); });
    }

    function submitAddDriver(event, form, equipmentId) {
        event.preventDefault();
        var driverSel  = form.querySelector('[name="employee_id"]');
        var shiftSel   = form.querySelector('[name="shift_type"]');
        var startInput = form.querySelector('[name="start_date"]');
        var endInput   = form.querySelector('[name="end_date"]');
        if (!driverSel || !driverSel.value) { alert('يرجى اختيار السائق'); return; }
        var formData = new FormData();
        formData.append('action', 'add_new_driver');
        formData.append('employee_id',   driverSel.value);
        formData.append('equipment_id', equipmentId);
        formData.append('shift_type',  shiftSel   ? shiftSel.value   : 'B');
        formData.append('start_date',  startInput ? startInput.value : '');
        formData.append('end_date',    endInput   ? endInput.value   : '');
        formData.append('json', '1');
        fetch(window.location.href, { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(d) { d.success ? (alert('✅ ' + d.message), location.reload()) : alert('❌ ' + d.message); })
            .catch(function()  { alert('❌ خطأ في الاتصال'); });
    }

    // خريطة Leaflet
    (function () {
        var mapRows    = <?php echo json_encode($map_rows, JSON_UNESCAPED_UNICODE); ?>;
        var projectLat = <?php echo isset($selected_project['latitude']) ? floatval($selected_project['latitude']) : 0; ?>;
        var projectLng = <?php echo isset($selected_project['longitude']) ? floatval($selected_project['longitude']) : 0; ?>;

        var mapCenterLat = 15.5007;
        var mapCenterLng = 32.5599;
        if (projectLat !== 0 && projectLng !== 0) {
            mapCenterLat = projectLat;
            mapCenterLng = projectLng;
        } else if (mapRows.length > 0) {
            mapCenterLat = parseFloat(mapRows[0].lat || mapCenterLat);
            mapCenterLng = parseFloat(mapRows[0].lng || mapCenterLng);
        }

        var map = L.map('monitoringMap').setView([mapCenterLat, mapCenterLng], 9);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        mapRows.forEach(function (row) {
            var shiftLabel = row.shift === 'D' ? 'نهاري' : (row.shift === 'N' ? 'ليلي' : 'نهاري + ليلي');
            L.marker([parseFloat(row.lat), parseFloat(row.lng)]).addTo(map)
                .bindPopup('<b>' + row.equipment + '</b><br>النوع: ' + (row.type || '-') + '<br>السائقون: ' + row.drivers + '<br>الوردية: ' + shiftLabel);
        });
    })();
</script>
