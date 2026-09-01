<?php
/**
 * operations/site_request_batches.php — دفعات استلام طلب الموقع (DEP-12 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: دفعةُ استلامٍ واحدةٌ على طلبِ موقع
 * المالك: إدارة الموقع · مصدرُ الحقيقة: site_request_batch
 * الأصل: ورقةُ «إدارة الموقع» — السطح «دفعات استلام طلب الموقع»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'operations/site_request_batches.php',
    'screen'     => 'site_request_batch',
    'table'      => 'site_request_batch',
    'title'      => 'دفعات استلام طلب الموقع',
    'icon'       => 'fa fa-boxes-stacked',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · دفعات استلام طلب الموقع',
    'intro'      => 'دفعاتُ الاستلامِ على الطلبِ الواحدِ بتسلسلِها وسندِ صرفِها ومطابقتِها',
    'rule'       => 'الطلبُ يُستلَم دفعاتٍ — وكلُّ دفعةٍ بسندِها ومطابقتِها لا بجمعٍ في آخرِه',
    'empty_hint' => 'لا دفعةَ استلامٍ مسجَّلةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.site_request_batch.register',
            'label' => 'تسجيلُ دفعات استلام طلب الموقع',
            'rule'  => 'الطلبُ يُستلَم دفعاتٍ — وكلُّ دفعةٍ بسندِها ومطابقتِها لا بجمعٍ في آخرِه',
            'fields' => array(
                'request_ref' => 'رقم الطلب ◄',
                'batch_seq' => 'تسلسل الدفعة',
                'receipt_date' => 'تاريخ الاستلام',
                'batch_match' => 'مطابقة الدفعة ▼',
                'batch_state' => 'حالة الدفعة ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('request_ref', 'batch_seq', 'receipt_date', 'batch_match', 'batch_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('site_request_batch', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'SRB-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('site_request_batch',
                            array('batch_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في site_request_batch');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
