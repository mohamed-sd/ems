<?php
/**
 * includes/contract_file_tabs.php — تبويباتُ ملف عقد المشروع (NAV-01 §8/§9.3 · update0006-b E-02)
 * ───────────────────────────────────────────────────────────────────────────
 * «ستةُ تبويباتٍ عليا لا ثلاثةَ عشر» — والتبويبُ يُحمَّل عند فتحه (§8-②):
 * شريطُ روابطَ مشتركٌ يُضمَّن في رأس ملف العقد وفي كلِّ شاشاته الفرعية،
 * فتصير الرحلةُ ملفًّا واحدًا والشاشاتُ الاثنتا عشرةَ تبويباتِه لا قائمةً.
 *
 * الاستعمال:  $cf_contract_id = $contract_id;  $cf_active = 'lines';
 *             include __DIR__ . '/../includes/contract_file_tabs.php';
 */
if (!isset($cf_contract_id)) { return; }
$cf_id  = intval($cf_contract_id);
$cf_act = isset($cf_active) ? $cf_active : 'summary';
$cf_pre = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/Contracts/') !== false
        || strpos($_SERVER['SCRIPT_NAME'] ?? '', '/Clients/') !== false) ? '../' : '';

/* الستةُ العليا (NAV-01 §9.3) — وما بداخل كلٍّ أقسامٌ (روابطُ ثانوية) لا تبويباتٌ عليا */
$cf_tabs = array(
    'summary' => array('الملخصُ والخطُّ التجاري', array(
        'summary'   => array('الملخص', 'Contracts/contracts_details.php?id=%d'),
        'baseline'  => array('خطُّ الأساس', 'Contracts/contract_baseline.php?contract=%d'),
        'price'     => array('شروطُ السعر', 'Contracts/price_terms.php?contract=%d'),
    )),
    'obligations' => array('الالتزاماتُ ونطاقاتُ التنفيذ', array(
        'obligations' => array('الالتزامات', 'Contracts/contract_obligations.php?contract=%d'),
        'commitments' => array('التعهدات', 'Clients/contract_commitments.php?contract=%d'),
        'sites'       => array('نطاقاتُ التنفيذ', 'Contracts/contract_sites.php?contract=%d'),
        'lines'       => array('البنود', 'Contracts/contract_lines.php?contract=%d'),
    )),
    'plans' => array('الخططُ والموارد', array(
        'monthly'  => array('الخطةُ الشهرية', 'Contracts/contract_monthly_plan.php?contract=%d'),
        'resource' => array('خطةُ الموارد', 'Contracts/contract_resource_plan.php?contract=%d'),
        'payment'  => array('خطةُ الدفع', 'Contracts/contract_payment_schedule.php?contract=%d'),
        'actual'   => array('المخططُ والمنفَّذ', 'Contracts/plan_actual_link.php?contract=%d'),
    )),
    'coverage' => array('التغطيةُ والتنفيذ', array(
        'coverage' => array('التغطيةُ التعاقدية', 'Contracts/contracts_details.php?id=%d#coverage'),
        'penalties'=> array('الجزاءات', 'Contracts/penalties.php?contract=%d'),
        'lifecycle'=> array('اقتصادُ دورة الحياة', 'Contracts/contract_lifecycle.php?contract=%d'),
    )),
    'billing' => array('المستخلصُ والفاتورةُ والتحصيل', array(
        'claims' => array('المستخلصات', 'Contracts/claims.php?contract=%d'),
    )),
    'annex' => array('الملاحقُ والمستنداتُ والسجل', array(
        'amendments' => array('الملاحق', 'Clients/contract_amendments.php?contract=%d'),
        'guarantees' => array('الضمانات', 'Contracts/contract_guarantees.php?contract=%d'),
        'events'     => array('السجلُّ الزمني', 'Clients/contract_events.php?contract=%d'),
    )),
);
/* أيُّ تبويبٍ علويٍّ نشطٌ؟ — من القسم النشط */
$cf_top_active = 'summary';
foreach ($cf_tabs as $tk => $tv) { if (isset($tv[1][$cf_act])) { $cf_top_active = $tk; break; } }
?>
<div class="cf-tabs" dir="rtl" style="margin:0 0 12px">
  <ul class="nav nav-tabs" style="flex-wrap:wrap">
    <?php foreach ($cf_tabs as $tk => $tv): ?>
      <li class="nav-item">
        <a class="nav-link<?= $tk === $cf_top_active ? ' active' : '' ?>"
           href="<?= $cf_pre . sprintf($tv[1][array_key_first($tv[1])][1], $cf_id) ?>">
          <?= $tv[0] ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php $cf_secs = $cf_tabs[$cf_top_active][1]; if (count($cf_secs) > 1): ?>
  <div style="padding:6px 4px;background:#f8f9fa;border:1px solid #dee2e6;border-top:0">
    <?php foreach ($cf_secs as $sk => $sv): ?>
      <a href="<?= $cf_pre . sprintf($sv[1], $cf_id) ?>"
         class="badge" style="font-size:.85em;margin:0 2px;<?= $sk === $cf_act
            ? 'background:#0d6efd;color:#fff' : 'background:#e9ecef;color:#333' ?>">
        <?= $sv[0] ?></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
