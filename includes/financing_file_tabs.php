<?php
/**
 * includes/financing_file_tabs.php — تبويباتُ ملف عملية التمويل (NAV-01 §9.13 · update0007-ب)
 * ستةُ تبويبات: الشروطُ والعائد · الأعيانُ المموَّلة · الحصصُ عبر الزمن ·
 * الأقساطُ والسداد · الحركةُ في الدفتر · المستنداتُ والسجل.
 * الاستعمال: $ff_op_id = ...; $ff_active = 'terms'; include هذا الملف.
 */
if (!isset($ff_op_id)) { return; }
$ff_id  = intval($ff_op_id);
$ff_act = isset($ff_active) ? $ff_active : 'terms';
$ff_tabs = array(
    'terms'    => array('الشروطُ والعائد',      'Financing/operation_profile.php?id=%d'),
    'assets'   => array('الأعيانُ المموَّلة',    'Financing/operation_profile.php?id=%d&tab=assets'),
    'shares'   => array('الحصصُ عبر الزمن',     'Financing/operation_profile.php?id=%d&tab=shares'),
    'installments' => array('الأقساطُ والسداد', 'Financing/installments.php?op=%d'),
    'ledger'   => array('الحركةُ في الدفتر',    'Financing/operation_profile.php?id=%d&tab=ledger'),
    'docs'     => array('المستنداتُ والسجل',    'Financing/operation_profile.php?id=%d&tab=docs'),
);
?>
<div class="ff-tabs" dir="rtl" style="margin:0 0 12px">
  <ul class="nav nav-tabs" style="flex-wrap:wrap">
    <?php foreach ($ff_tabs as $tk => $tv): ?>
      <li class="nav-item"><a class="nav-link<?= $tk === $ff_act ? ' active' : '' ?>"
          href="../<?= sprintf($tv[1], $ff_id) ?>"><?= $tv[0] ?></a></li>
    <?php endforeach; ?>
  </ul>
</div>
