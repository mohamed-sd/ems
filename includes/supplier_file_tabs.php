<?php
/**
 * includes/supplier_file_tabs.php — تبويباتُ ملف المورد (NAV-01 §8 · update0006-b E-02)
 * ───────────────────────────────────────────────────────────────────────────
 * الاستعمال:  $sf_supplier_id = $supplier_id;  $sf_active = 'documents';
 *             include __DIR__ . '/../includes/supplier_file_tabs.php';
 */
if (!isset($sf_supplier_id)) { return; }
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
?>
<div class="sf-tabs" dir="rtl" style="margin:0 0 12px">
  <ul class="nav nav-tabs" style="flex-wrap:wrap">
    <?php foreach ($sf_tabs as $tk => $tv): ?>
      <li class="nav-item">
        <a class="nav-link<?= $tk === $sf_top ? ' active' : '' ?>"
           href="<?= $sf_pre . sprintf($tv[1], $sf_id) ?>"><?= $tv[0] ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php if ($sf_top === 'rules'): ?>
  <div style="padding:6px 4px;background:#f8f9fa;border:1px solid #dee2e6;border-top:0">
    <?php foreach ($sf_subs as $sk => $sv): ?>
      <a href="<?= $sf_pre . sprintf($sv[1], $sf_id) ?>"
         class="badge" style="font-size:.85em;margin:0 2px;<?= $sk === $sf_act
            ? 'background:#0d6efd;color:#fff' : 'background:#e9ecef;color:#333' ?>">
        <?= $sv[0] ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
