<?php
/**
 * workforce/wf_settlement.php — التسوية ونهاية التخصيص (DEP-13 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: تسويةُ نهايةِ تخصيصٍ واحدةٌ لفرد
 * المالك: إدارة القوى التشغيلية · مصدرُ الحقيقة: worker_settlement
 * الأصل: ورقةُ «إدارة القوى التشغيلية» — السطح «التسوية ونهاية التخصيص»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'workforce/wf_settlement.php',
    'screen'     => 'wf_settlement',
    'table'      => 'worker_settlement',
    'title'      => 'التسوية ونهاية التخصيص',
    'icon'       => 'fa fa-file-circle-check',
    'nature'     => 'document',
    'doc'        => '01 · الدليل المعماري · التسوية ونهاية التخصيص',
    'intro'      => 'تسويةُ نهايةِ التخصيصِ بإخلاءِ السكنِ والعُهدِ المردودةِ والمعلَّقةِ وأساسِ المستحق',
    'rule'       => 'العهدةُ المعلَّقةُ تمنع الإقفال — والمستحقُّ أساسُه مشتقٌّ لا مُدخَل',
    'empty_hint' => 'لا تسويةَ مسجَّلةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.wf_settlement.register',
            'label' => 'تسجيلُ التسوية ونهاية التخصيص',
            'rule'  => 'العهدةُ المعلَّقةُ تمنع الإقفال — والمستحقُّ أساسُه مشتقٌّ لا مُدخَل',
            'fields' => array(
                'allocation_ref' => 'رقم التخصيص ◄',
                'person_ref' => 'كود الفرد ◄',
                'settlement_state' => 'حالة التسوية ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('allocation_ref', 'person_ref', 'settlement_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('worker_settlement', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'WFT-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('worker_settlement',
                            array('settlement_no' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في worker_settlement');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
