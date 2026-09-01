<?php
/**
 * workforce/wf_taxonomy.php — تصنيف الفئات التشغيلية (DEP-13 · GOV_EXEC §5)
 * ──────────────────────────────────────────────────────────────────────
 * ◆ ملفٌّ مولَّد (gov_exec:generated) من `tools/gov_exec_dept_build.php`
 *   ومواصفتِه في `tools/specs/`. لا يُحرَّر يدويًّا — أعِد التوليد.
 *
 * الحبّة: فئةٌ تشغيليةٌ واحدة
 * المالك: إدارة القوى التشغيلية · مصدرُ الحقيقة: wf_category
 * الأصل: ورقةُ «إدارة القوى التشغيلية» — السطح «تصنيف الفئات التشغيلية — Workforce Taxonomy»
 *
 * ◆ الأعمدةُ تُقرأ من `gov_field_class` — والحقلُ بلا صنفٍ لا يُصيَّر
 *   أصلًا (OBL-0052). فأسماءُ الحقولِ من الورقةِ حرفًا ولا تُكتب هنا.
 */
$U13 = array(
    'file'       => 'workforce/wf_taxonomy.php',
    'screen'     => 'wf_taxonomy',
    'table'      => 'wf_category',
    'title'      => 'تصنيف الفئات التشغيلية',
    'icon'       => 'fa fa-sitemap',
    'nature'     => 'register',
    'doc'        => '01 · الدليل المعماري · تصنيف الفئات التشغيلية — Workforce Taxonomy',
    'intro'      => 'الفئاتُ التشغيليةُ بعائلاتِها الوظيفيةِ ومتطلباتِ مصفوفتِها المرجعية',
    'rule'       => 'العائلةُ الوظيفيةُ تُشتقُّ من الهيكلِ النافذِ — ولا فئةَ توازي مسمًّى وظيفيًّا بلا حكم',
    'empty_hint' => 'لا فئةَ تشغيليةٌ مسجَّلةٌ بعد',

    'actions' => array(
        'register' => array(
            'code'  => 'gov.wf_taxonomy.register',
            'label' => 'تسجيلُ تصنيف الفئات التشغيلية',
            'rule'  => 'العائلةُ الوظيفيةُ تُشتقُّ من الهيكلِ النافذِ — ولا فئةَ توازي مسمًّى وظيفيًّا بلا حكم',
            'fields' => array(
                'category_name' => 'اسم الفئة',
                'equipment_applies' => 'تنطبق عليها معدة؟ ▼',
                'title_examples' => 'أمثلة مسميات',
                'category_state' => 'حالة الفئة ▼',
            ),
            'run' => function ($conn, $co, $uid, $in) {
                $keys = array('category_name', 'equipment_applies', 'title_examples', 'category_state');
                $row = array();
                foreach ($keys as $k) {
                    $v = trim((string) ($in[$k] ?? ''));
                    if ($v !== '') { $row[$k] = $v; }
                }
                if (!$row) { return array('ok' => false, 'reason' => 'لا حقلَ مملوءًا — السجلُّ لا يُفتح فارغًا'); }
                $row['created_by'] = $uid;
                try {
                    ems_tenant_db()->insert('wf_category', $row);
                } catch (\Throwable $t) {
                    return array('ok' => false, 'reason' => 'قيدُ المخطّطِ ردَّ المدخلات — راجعْها');
                }
                $nid = (int) $conn->insert_id;
                if ($nid > 0) {
                    $code = 'WFC-' . str_pad((string) $nid, 5, '0', STR_PAD_LEFT);
                    try {
                        ems_tenant_db()->update('wf_category',
                            array('category_code' => $code), array('id' => $nid));
                    } catch (\Throwable $t) { /* السطرُ مسجَّلٌ ومفتاحُه يُستكمل بمرورٍ لاحق */ }
                }
                return array('ok' => true, 'reason' => 'سُجِّل السطرُ في wf_category');
            }),
    ),
);
require __DIR__ . '/../includes/u13_screen_kit.php';
