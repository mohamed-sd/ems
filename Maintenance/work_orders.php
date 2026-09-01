<?php
/**
 * maintenance/work_orders.php — أمر العمل (DEP-14 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: أمرُ عملٍ واحدٌ على معدّة
 * المالك: إدارة الصيانة · مصدرُ الحقيقة: mnt_order
 * الأصل: ورقةُ «إدارة الصيانة» — السطح «أمر العمل»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'maintenance/work_orders.php',
    'screen'     => 'mnt_work_order',
    'table'      => 'mnt_order',
    'title'      => 'أمر العمل',
    'icon'       => 'fa fa-screwdriver-wrench',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · أمر العمل',
    'intro'      => 'أوامرُ العملِ بمصدرِها وأولويتِها وفنيِّها وزمنِها المخطَّطِ وتكلفةِ قطعِها التقديرية',
    'rule'       => 'الأمرُ يتبع تشخيصًا مقيَّدًا — وعمالتُه تفصَّل في سطحِها لا في سطرِه',
    'empty_hint' => 'لا أمرَ عملٍ مسجَّلٌ بعد',
    'where'       => 'is_deleted = 0',
    'order'       => 'created_at DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.mnt_work_order.register',
            'label' => 'تسجيلُ أمر العمل',
            'rule'  => 'الأمرُ يتبع تشخيصًا مقيَّدًا — وعمالتُه تفصَّل في سطحِها لا في سطرِه',
            'fields' => array(
                'open_date' => 'تاريخ الفتح',
                'source' => 'مصدر الأمر ▼',
                'diagnosis_ref' => 'رقم الفحص ◄',
                'equipment_id' => 'كود المعدة ◄',
                'maint_type' => 'نوع الأمر ▼',
                'priority' => 'الأولوية ▼',
                'workshop' => 'مكان التنفيذ ▼',
                'planned_time' => 'الزمن المخطط',
                'target_finish_date' => 'تاريخ الإنجاز المستهدف',
                'state' => 'حالة الأمر ▼',
                'reviewer' => 'المراجع',
                'approved_at' => 'تاريخ الاعتماد',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('open_date', 'source', 'diagnosis_ref', 'equipment_id', 'maint_type', 'priority', 'workshop', 'planned_time', 'target_finish_date', 'state', 'reviewer', 'approved_at');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('mnt_order', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في mnt_order');
            }),
    ),
);
require __DIR__ . '/../includes/w14_grid.php'; // شبكةُ حقولِ الورقةِ للسجلِّ الابن
require __DIR__ . '/../includes/u13_screen_kit.php'; ?>
    <!-- سجلُّ حقولِ الورقةِ بحبّتِه — يُضاف بجانبِ ما بُني لا بدلًا منه،
         فالمبنيُّ له أفعالُه والورقةُ تطلب السجلَّ بحقولِه كلِّها -->
    <div class="card"><div class="card-header"><h5><i class="fa fa-clipboard-list"></i> بنود امر العمل بحقول الورقة</h5></div>
    <div class="card-body"><div class="table-container">
        <?php /* GUIDE_COLS:govui_field_close:emsList_mnt_order_line
             الرأسُ والخليّةُ من خريطةٍ واحدةٍ (الأمرُ §11)
             والأسماءُ أسماءُ «09 · 02_تتبع_الحقول» والترتيبُ ترتيبُ دورةِ المستند،
             ⛔ ولا رأسَ بلا مصدرِ خليّةٍ مصرَّحٍ بجانبِه. */
        $GUIDE_COLS = array(
            'معرف البند' => 'g1',
            'رقم الأمر' => 'g2',
            'تسلسل البند' => 'g3',
            'نوع البند' => 'g4',
            'كود الصنف' => 'g5',
            'الوصف' => 'g6',
            'الكمية المطلوبة' => 'g7',
            'الكمية المصروفة' => 'g8',
            'رقم سند الصرف' => 'g9',
            'جهة الخدمة الخارجية' => 'g10',
            'التكلفة' => 'g11',
            'ضمان مورد؟' => 'g12',
            'حالة البند' => 'g13',
            'المنشئ' => 'g14',
            'تاريخ الإنشاء' => 'g15',
            'حالة البيانات' => 'g16',
            'مرجع المصدر' => 'g17',
        );
        $D = array();
        $__gridRows = ems_w14_guide_rows('mnt_order_line');
        echo ems_w14_grid('emsList_mnt_order_line', $GUIDE_COLS, $__gridRows, $D, 'لا سطر مسجل بعد في أمر العمل'); /* /GUIDE_COLS */ ?>
    </div></div></div>
    <?php 
