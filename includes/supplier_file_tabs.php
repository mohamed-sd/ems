<?php
/**
 * includes/supplier_file_tabs.php — تبويباتُ ملف المورد (NAV-01 §8 · update0006-b E-02)
 * ───────────────────────────────────────────────────────────────────────────
 * الاستعمال:  $sf_supplier_id = $supplier_id;  $sf_active = 'documents';
 *             include __DIR__ . '/../includes/supplier_file_tabs.php';
 *
 * ◆ العرضُ كلُّه في `includes/file_tabs_kit.php` — هذا الملفُّ يصف تبويباتِه
 *   ولا يرسمها. وموضعُ الشريطِ تحتَ الشريطِ الأصفرِ يتكفّل به `page_header.php`.
 */
if (!isset($sf_supplier_id)) { return; }
require_once __DIR__ . '/file_tabs_kit.php';

$sf_id  = intval($sf_supplier_id);
$sf_act = isset($sf_active) ? $sf_active : 'profile';
$sf_pre = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/Suppliers/') !== false
        || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/Finance/') !== false) ? '../' : '';

$sf_tabs = array(
    'profile'   => array('الملف', 'Suppliers/supplier_profile.php?id=%d'),
    'contracts' => array('بنودُ العقد والحصص', 'Suppliers/supplier_contract_lines.php?supplier_id=%d'),
    'capacity'  => array('الجاهزيةُ والقدرة', 'Suppliers/supplier_capacity.php?supplier_id=%d'),
    'documents' => array('الوثائق', 'Suppliers/supplier_documents.php?supplier_id=%d'),
    'statement' => array('كشفُ الحساب', 'Finance/supplier_statement_fin.php?supplier_id=%d'),
    'rules'     => array('القواعدُ والتقييمُ والإقفال', 'Suppliers/supplier_rules.php?supplier_id=%d'),
);
/* القواعدُ والتقييمُ والإقفال أقسامٌ ثلاثةٌ تحت تبويبٍ واحد — ستةٌ حدًّا أقصى */
$sf_subs = array(
    'rules'      => array('القواعد', 'Suppliers/supplier_rules.php?supplier_id=%d'),
    'evaluation' => array('التقييم', 'Suppliers/supplier_evaluation.php?supplier_id=%d'),
    'closure'    => array('الإقفال', 'Suppliers/supplier_closure.php?supplier_id=%d'),
);
$sf_top = isset($sf_tabs[$sf_act]) ? $sf_act : (isset($sf_subs[$sf_act]) ? 'rules' : 'profile');

$sf_items = array();
foreach ($sf_tabs as $tk => $tv) {
    $sf_items[] = array('text' => $tv[0], 'href' => $sf_pre . sprintf($tv[1], $sf_id),
                        'active' => ($tk === $sf_top));
}
$sf_subItems = array();
if ($sf_top === 'rules') {
    foreach ($sf_subs as $sk => $sv) {
        $sf_subItems[] = array('text' => $sv[0], 'href' => $sf_pre . sprintf($sv[1], $sf_id),
                               'active' => ($sk === $sf_act));
    }
}

ems_file_tabs(array(
    'label' => 'ملفُّ المورد',
    'tabs'  => $sf_items,
    'subs'  => $sf_subItems,
));
