<?php
/**
 * includes/sales_family_tabs.php — تبويباتُ عائلاتِ المبيعاتِ والموردين
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **لماذا هذا الملف**: وثيقتا المواءمةِ تجعلان عشرَ شاشاتٍ **تبويباتٍ في
 *   ملفاتٍ أمّ** — والتبويبُ يجب أن **يوجد** لا أن يُعلَن. وقاعدةُ التطبيقِ
 *   عندنا صريحة: **لا يُخفى بندٌ من القائمةِ قبلَ إثباتِ منفذِه البديل**،
 *   فبقيت هذه العشرُ ظاهرةً حتى تُبنى تبويباتُها.
 *
 * ◆ **وثلاثُ عائلاتٍ لا أكثر**:
 *   ① **البيانات المرجعية** — كتالوجُ الخدمات · قوائمُ التسعير · دفترُ الأسعار ·
 *      وحداتُ القياس · نماذجُ العمل  (قرارُ الورقة 19)
 *   ② **الفرصة** — بيانُ الفرصة · الأنشطةُ والمتابعات · المناقصاتُ (نوعُ فرصةٍ
 *      لا سجلٌّ مستقل) · مراجعةُ ما قبل التعاقد  (الأوراق 04·05·10)
 *   ③ **المطالبة** — بيانُ المطالبة · الأداءُ الشهريُّ المعتمد · غيرُ المفوتر ·
 *      حالةُ الفاتورةِ والتحصيل (إسقاط)  (قرارُ الورقة 16)
 *   ورابعةٌ للموردين: **طاقةُ المورد** — الطاقةُ والجاهزية · تسليمُ الحصص
 *      (قرارُ الورقة م12: سطحان لوقائعَ واحدة).
 *
 * ◆ **ولا تُخترع تبويبةٌ لشاشةٍ ليست في قرارِ الوثيقة** — الشريطُ مرآةُ القرارِ
 *   لا اجتهادٌ محليّ. والمكوّنُ المركزيُّ نفسُه (`ems_file_tabs`) يُخرج الترميز،
 *   فلا صنفَ ولا تنسيقَ محليّ.
 *
 * الاستعمال:  $sft_family = 'reference'; $sft_active = 'pricelists';
 *             include __DIR__ . '/../includes/sales_family_tabs.php';
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (!isset($sft_family)) { return; }
require_once __DIR__ . '/file_tabs_kit.php';

$sft_pre = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/Clients/') !== false
         || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/Contracts/') !== false
         || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/Suppliers/') !== false
         || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/Operations/') !== false
         || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/Opportunities/') !== false) ? '../' : '';

$SFT = array(
    'reference' => array('البيانات المرجعية', array(
        'products'   => array('كتالوج الخدمات وبنود البيع', 'Clients/products.php'),
        'pricelists' => array('قوائم التسعير المعتمدة',     'Clients/pricelists.php'),
        'ratebooks'  => array('دفتر الأسعار بالشرائح',      'Clients/rate_books.php'),
        'uom'        => array('وحدات القياس والتحويل',      'Clients/units_of_measure.php'),
        'models'     => array('نماذج العمل',                'Portal/business_models.php'),
    )),
    'opportunity' => array('ملف الفرصة البيعية', array(
        'opportunity' => array('بيانات الفرصة',            'Opportunities/opportunities.php'),
        'need'        => array('احتياج العميل وطلب العرض', 'Opportunities/client_need_rfq.php'),
        'activities'  => array('الأنشطة والمتابعات',       'Clients/activities.php'),
        'tenders'     => array('المناقصات',                'Clients/tenders.php'),
        'readiness'   => array('مراجعة ما قبل التعاقد',    'Clients/readiness_lines.php'),
    )),
    'quotation' => array('ملف عرض السعر', array(
        'quotation' => array('بيانات العرض',              'Clients/quotations.php'),
        'lines'     => array('بنود العرض',                'Clients/quotation_lines.php'),
        'nego'      => array('سجل التفاوض ومراجعاته',     'Clients/quotation_negotiation.php'),
        'readiness' => array('مراجعة ما قبل التعاقد',     'Clients/readiness_lines.php'),
    )),
    'settlement' => array('ملف تسوية المورد', array(
        'settlement' => array('الاستحقاقات وكشف الحساب',  'Suppliers/settlements.php'),
        'advances'   => array('السلف والنيابية',           'Suppliers/supplier_advances.php'),
        'violations' => array('المخالفات والجزاءات',       'Suppliers/supplier_violations.php'),
    )),
    'claim' => array('ملف المطالبة التجارية', array(
        'claim'     => array('بيانات المطالبة',            'Contracts/claims.php'),
        'perf'      => array('الأداء الشهري المعتمد',      'Contracts/unit_statement_client.php'),
        'unbilled'  => array('الأعمال غير المفوترة',       'Operations/unbilled.php'),
        'invoice'   => array('حالة الفاتورة والتحصيل',     'Contracts/tax_invoices.php'),
    )),
    'capacity' => array('طاقة المورد وجاهزيته', array(
        'capacity' => array('الطاقة والجاهزية ومهلة الإحلال', 'Suppliers/supplier_capacity.php'),
        'handover' => array('تسليم الحصص بين الموردين',       'Suppliers/sup_handover.php'),
    )),
);

if (!isset($SFT[$sft_family])) { return; }
list($sft_label, $sft_items) = $SFT[$sft_family];
$sft_act = isset($sft_active) ? $sft_active : key($sft_items);

$sft_tabs = array();
foreach ($sft_items as $k => $v) {
    $sft_tabs[] = array(
        'text'   => $v[0],
        'href'   => $sft_pre . $v[1],
        'active' => ($k === $sft_act),
    );
}
ems_file_tabs(array('label' => $sft_label, 'tabs' => $sft_tabs));
unset($sft_family, $sft_active);
