<?php
/**
 * suppliers/supplier_payment_allocation.php — تخصيص الدفع على الإقفالات (DEP-02 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: تخصيصُ دفعةٍ واحدةٍ على إقفالٍ واحد
 * المالك: إدارة الموردين · مصدرُ الحقيقة: sup_allocation_payment_closure
 * الأصل: ورقةُ «إدارة الموردين» — السطح «تخصيص الدفع على الإقفالات»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'suppliers/supplier_payment_allocation.php',
    'screen'     => 'sup_pay_alloc',
    'table'      => 'sup_allocation_payment_closure',
    'title'      => 'تخصيص الدفع على الإقفالات',
    'icon'       => 'fa fa-money-check',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · تخصيص الدفع على الإقفالات',
    'intro'      => 'أيُّ مبلغٍ من طلبِ دفعٍ خُصِّص على أيِّ إقفالٍ وبأيِّ قاعدة',
    'rule'       => 'تجاوزُ صافي الشهرِ عَلَمٌ مشتقٌّ يُرفع ولا يُطفأ يدويًّا',
    'empty_hint' => 'لا تخصيصَ دفعٍ مسجَّلٌ بعد',
    'order'       => 'date_allocation DESC, id DESC',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.sup_pay_alloc.register',
            'label' => 'تسجيلُ تخصيص الدفع على الإقفالات',
            'rule'  => 'تجاوزُ صافي الشهرِ عَلَمٌ مشتقٌّ يُرفع ولا يُطفأ يدويًّا',
            'fields' => array(
                'no_payment' => 'رقم طلب الدفع',
                'no_supplier' => 'رقم المورد',
                'no_closure' => 'رقم الإقفال (م17)',
                'currency_ref' => 'العملة',
                'allocated_amount' => 'المبلغ المخصص',
                'date_allocation' => 'تاريخ التخصيص',
                'allocation_rule' => 'قاعدة التخصيص',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('no_payment', 'no_supplier', 'no_closure', 'currency_ref', 'allocated_amount', 'date_allocation', 'allocation_rule');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('sup_allocation_payment_closure', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SPA-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('sup_allocation_payment_closure',
                            array('alloc_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في sup_allocation_payment_closure');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
