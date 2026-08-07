<?php
/**
 * Risk/_legacy_view_panel.php — R9 (ورقة 32): الشاشة القديمة تصير منظرًا
 * نطاقيًّا على السجل المركزي لا مصدرًا. يُضمَّن بعد رأس الصفحة مع ضبط:
 *   $legacy_ru_codes = array('RU-07');       // زاوية الوحدات (فارغة = الحرجة فقط)
 *   $legacy_view_note = '...';               // نص الزاوية
 * القراءة مباشرة على risk_register بعزل الشركة — صفر كتابة من هنا.
 */
if (!isset($conn) || !isset($company_id)) { return; }
$lv_ru = isset($legacy_ru_codes) && is_array($legacy_ru_codes) ? $legacy_ru_codes : array();
$lv_where = "rr.company_id = " . intval($company_id) . " AND rr.merged_into_id IS NULL AND rr.state <> 'closed'";
if (!empty($lv_ru)) {
    $codes = implode("','", array_map(function ($c) use ($conn) { return $conn->real_escape_string($c); }, $lv_ru));
    $lv_where .= " AND ru.ru_code IN ('{$codes}')";
} else {
    $lv_where .= " AND rr.current_level IN ('حرج','محظور')";
}
$lv_rows = array();
$lv_res = @$conn->query("SELECT rr.id, rr.risk_code, rr.title, rr.current_level, rr.state, ru.ru_code
                           FROM risk_register rr JOIN risk_units ru ON ru.id = rr.ru_id
                          WHERE {$lv_where}
                          ORDER BY FIELD(COALESCE(rr.current_level,''),'محظور','حرج','مرتفع','متوسط','منخفض',''), rr.updated_at DESC LIMIT 15");
if ($lv_res) { while ($lv_x = $lv_res->fetch_assoc()) { $lv_rows[] = $lv_x; } }
?>
<div class="card" style="margin-bottom:14px;border-inline-start:4px solid #b45309">
  <div class="card-body" style="padding:14px 18px">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <b><i class="fas fa-triangle-exclamation"></i> منظر نطاقي على السجل المركزي للمخاطر</b>
        <small class="text-muted"><?php echo htmlspecialchars(isset($legacy_view_note) ? $legacy_view_note : 'الخطر يُسجَّل مركزيًّا ويُعرض هنا بزاويته — هذه الشاشة ليست مصدرًا (M-16 ورقة 32)'); ?></small>
        <a class="btn btn-sm btn-outline-dark" style="margin-inline-start:auto" href="../Risk/risk_register.php">السجل المركزي ↗</a>
    </div>
    <?php if (empty($lv_rows)): ?>
        <div class="text-muted" style="font-size:.82rem;margin-top:6px">لا مخاطر مفتوحة في هذه الزاوية</div>
    <?php else: ?>
    <table class="table table-sm" style="margin:8px 0 0" data-no-dt>
        <thead><tr><th>الرمز</th><th>العنوان</th><th>الوحدة</th><th>المستوى</th><th>الحالة</th><th></th></tr></thead>
        <tbody><?php foreach ($lv_rows as $lv_x): ?>
        <tr>
            <td><?php echo htmlspecialchars($lv_x['risk_code']); ?></td>
            <td><?php echo htmlspecialchars($lv_x['title']); ?></td>
            <td><?php echo htmlspecialchars($lv_x['ru_code']); ?></td>
            <td><?php echo htmlspecialchars((string) $lv_x['current_level'] ?: '—'); ?></td>
            <td><?php echo htmlspecialchars($lv_x['state']); ?></td>
            <td><a href="../Risk/risk_card.php?id=<?php echo (int) $lv_x['id']; ?>">ملف الخطر</a></td>
        </tr>
        <?php endforeach; ?></tbody>
    </table>
    <?php endif; ?>
  </div>
</div>
