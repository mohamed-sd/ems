<?php
/**
 * includes/w8_machine_panel.php — لوحةُ آلةِ الحالةِ المؤلَّفةِ (موجة W8)
 * ───────────────────────────────────────────────────────────────────────────
 * ◆ تقرأ آلةَ كيانٍ من مخزنِ الموجةِ `repair01_w8_states` (المرجعُ الحاكمُ
 *   الذي رُبطت به المتطلباتُ بمرجعٍ صريحٍ) وتُصيِّرها جدولَ حوكمةٍ:
 *   الانتقالُ ومالكُه وشرطُه المسبقُ ومستندُه وبوّابتُه — **عرضُ المؤلَّفِ
 *   حرفًا لا تأليفٌ جديد**، والممنوعُ صراحةً يُعرَض ممنوعًا.
 * ◆ جدولُ الموجةِ حوكميٌّ عالميٌّ (ليس جدولَ مستأجِرٍ) — قراءتُه المباشرةُ
 *   خارجَ مدى سقّاطةِ GAP-29 بتصنيفِه.
 */
if (!function_exists('ems_w8_machine_rows')) {
    function ems_w8_machine_rows($conn, $entity)
    {
        $out = array();
        $ent = $conn->real_escape_string((string) $entity);
        $r = @$conn->query("SELECT from_state, to_state, allowed, owner_role, precondition, official_doc, approval_gate
                              FROM repair01_w8_states WHERE entity = '$ent'
                             ORDER BY allowed DESC, from_state, to_state");
        while ($r && ($x = $r->fetch_assoc())) { $out[] = $x; }
        return $out;
    }

    function ems_w8_machine_panel($conn, $entity, $titleAr)
    {
        $rows = ems_w8_machine_rows($conn, $entity);
        $h = '<div class="table-container"><table class="ems-data-table" data-no-dt="1">'
           . '<thead><tr><th colspan="6">' . htmlspecialchars($titleAr) . '</th></tr>'
           . '<tr><th>من حالة</th><th>الى حالة</th><th>الحكم</th><th>مالك الانتقال</th><th>الشرط المسبق</th><th>المستند والبوابة</th></tr></thead><tbody>';
        foreach ($rows as $x) {
            $doc = trim((string) $x['official_doc'] . ' ' . (string) $x['approval_gate']);
            $h .= '<tr><td>' . htmlspecialchars((string) $x['from_state']) . '</td>'
                . '<td>' . htmlspecialchars((string) $x['to_state']) . '</td>'
                . '<td>' . ((int) $x['allowed'] === 1 ? 'مسموح' : 'ممنوع صراحة') . '</td>'
                . '<td>' . htmlspecialchars((string) $x['owner_role'] !== '' ? (string) $x['owner_role'] : 'غير منطبق') . '</td>'
                . '<td>' . htmlspecialchars((string) $x['precondition'] !== '' ? (string) $x['precondition'] : 'غير منطبق') . '</td>'
                . '<td>' . htmlspecialchars($doc !== '' ? $doc : 'غير منطبق') . '</td></tr>';
        }
        if (!$rows) { $h .= '<tr><td colspan="6">لا صفوف آلة لهذا الكيان في مخزن الموجة</td></tr>'; }
        return $h . '</tbody></table></div>';
    }
}
