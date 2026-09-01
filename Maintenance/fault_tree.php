<?php
/**
 * maintenance/fault_tree.php — شجرة الأعطال المرجعية (DEP-14 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: عقدةُ عطلٍ واحدةٌ في الشجرةِ المرجعية
 * المالك: إدارة الصيانة · مصدرُ الحقيقة: failure_codes
 * الأصل: ورقةُ «إدارة الصيانة» — السطح «شجرة الأعطال المرجعية»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'maintenance/fault_tree.php',
    'screen'     => 'mnt_fault_tree',
    'table'      => 'failure_codes',
    'title'      => 'شجرة الأعطال المرجعية',
    'icon'       => 'fa fa-network-wired',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · شجرة الأعطال المرجعية',
    'intro'      => 'شجرةُ الأعطالِ بمستوياتِها وآثارِها: الفوترةُ ووحدةُ المورِّدِ وأجرُ المشغّلِ وإيقافُ الجاهزية',
    'rule'       => 'الشجرةُ مصدرٌ واحدٌ لكلِّ الأسطح — و`unified_fault_taxonomy` منظرٌ عليها لا جدولٌ ثانٍ (§7)',
    'empty_hint' => 'لا عقدةَ عطلٍ مسجَّلةٌ بعد',
    'order'       => 'full_code ASC, id ASC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.mnt_fault_tree.register',
            'label' => 'تسجيلُ شجرة الأعطال المرجعية',
            'rule'  => 'الشجرةُ مصدرٌ واحدٌ لكلِّ الأسطح — و`unified_fault_taxonomy` منظرٌ عليها لا جدولٌ ثانٍ (§7)',
            'fields' => array(
                'full_code' => 'كود العقدة',
                'node_level' => 'المستوى ▼',
                'parent_node' => 'العقدة الأم ◄',
                'failure_detail' => 'اسم العطل/المصدر',
                'node_desc' => 'الوصف',
                'billing_effect' => 'أثر الفوترة ▼',
                'supplier_unit_effect' => 'أثر وحدة المورد ▼',
                'operator_wage_effect' => 'أثر أجر المشغّل ▼',
                'stops_readiness' => 'يوقف الجاهزية؟ ▼',
                'status' => 'حالة العقدة ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('full_code', 'node_level', 'parent_node', 'failure_detail', 'node_desc', 'billing_effect', 'supplier_unit_effect', 'operator_wage_effect', 'stops_readiness', 'status');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('failure_codes', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في failure_codes');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
