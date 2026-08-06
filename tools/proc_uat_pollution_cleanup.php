<?php
/**
 * tools/proc_uat_pollution_cleanup.php — كنسُ تلوث UAT في جداول المشتريات
 * ═══════════════════════════════════════════════════════════════════════════
 * المقيس (جولة مدير المشتريات 2026-08-06): ≈نصفُ صفوف proc_* بذورُ UAT فاسدة —
 * أصنافٌ بأسماء أشخاص، وحالاتٌ/أولوياتٌ/فئاتٌ حُشيت **عباراتِ اعتمادٍ**
 * («بانتظار اعتماد الإدارة المختصة · UAT-2026» …) تتسرّب إلى القوائم المنسدلة.
 *
 * المنهج — «الأرشفة لا الحذف» (الوحدة مُهاجَرة: لا حذفَ صلبًا):
 *   · صفٌّ حالتُه خارج قائمة حالاته الصالحة = بذرة فاسدة → حذفٌ ناعم
 *   · صفٌّ في حقوله عبارةُ اعتمادٍ من القائمة أدناه → حذفٌ ناعم
 *   · proc_supplier: **مسحُ الحقول الملوثة فقط** (NULL) لا الصف — فالموردون
 *     مُشارٌ إليهم من أوامرَ حقيقية والاسمُ يلزم للعرض
 *   · حركاتُ proc_stock_move لا تُمسّ (أرصدةُ العرض التجريبية سليمة البنية)
 *
 * php tools/proc_uat_pollution_cleanup.php            # dry-run
 * php tools/proc_uat_pollution_cleanup.php --apply    # تنفيذ + سجل تراجع JSON
 * التراجع: storage/reports/proc_uat_cleanup_<ts>.json (قوائم المعرفات) —
 * إرجاعها = UPDATE is_deleted=0 لمعرّفاتها.
 */
define('EMS_CLI', true);
require_once __DIR__ . '/../includes/env.php';

$APPLY = in_array('--apply', $argv, true);
$conn = new mysqli(ems_env('DB_HOST', 'localhost'), ems_env('DB_USER'), ems_env('DB_PASS'), ems_env('DB_NAME'), (int) ems_env('DB_PORT', 3306));
$conn->set_charset('utf8mb4');
$o = function ($s) { fwrite(STDOUT, $s . "\n"); };

/** عباراتُ الاعتماد الدخيلة (تُطابَق بادئةً — فالأعمدة القصيرة بترتها). */
$PHRASES = array(
    'أُثبت من واقع التشغيل',
    'بانتظار اعتماد الإدارة',
    'بناءً على طلب الموقع',
    'مراجَعٌ من المالية',
    'مطابقٌ للمستند المرفق',
    'مُدرجٌ ضمن الدورة الشهرية',
    'وفق المعتمد في محضر',
    'يخضع لمراجعة الربع',
);

$phraseCond = function (array $cols) use ($PHRASES, $conn) {
    $parts = array();
    foreach ($cols as $c) {
        $parts[] = "$c LIKE '%UAT-20%'";
        foreach ($PHRASES as $p) {
            $parts[] = "$c LIKE '" . $conn->real_escape_string($p) . "%'";
        }
    }
    return '(' . implode(' OR ', $parts) . ')';
};
$inList = function (array $vals) use ($conn) {
    return "('" . implode("','", array_map(array($conn, 'real_escape_string'), $vals)) . "')";
};

// (الجدول ⇐ شرطُ الفساد) — الحالاتُ الصالحة من proc_helpers (منسوخةً حرفيًّا:
// الأداة قائمة بذاتها بلا جلسة/بوابة)
$targets = array(
    'proc_request' => "state NOT IN " . $inList(array('مسودة', 'مقدَّم', 'اعتماد المشتريات', 'مراجعة مالية', 'معتمد مالياً', 'محوَّل لأمر شراء', 'مغلق', 'مرفوض')),
    'proc_order'   => "state NOT IN " . $inList(array('مسودة', 'مؤكَّد', 'استلام أولي', 'استلام نهائي', 'مطابَق', 'مغلق')),
    'proc_receipt_custody' => "state NOT IN " . $inList(array('مستلَمة', 'قيد الترحيل', 'مسلَّمة للوجهة')),
    'proc_issue'   => "state NOT IN " . $inList(array('مسودة', 'محجوز', 'مصروف', 'محمَّل التكلفة')),
    'proc_item'    => $phraseCond(array('name', 'category', 'material_nature', 'uom')),
    'proc_warehouse' => "(type NOT IN " . $inList(array('مخزن', 'ورشة', 'مباشر للآلية')) . " OR " . $phraseCond(array('name', 'type')) . ")",
    'proc_lookup'  => "(type NOT IN " . $inList(array('فئة صنف', 'وحدة قياس', 'طبيعة مادة')) . " OR " . $phraseCond(array('name', 'type')) . ")",
);

$o('══ proc_uat_pollution_cleanup — ' . ($APPLY ? 'APPLY' : 'DRY-RUN') . ' ══');
$log = array('ran_at' => date('c'), 'mode' => $APPLY ? 'apply' : 'dry-run', 'soft_deleted' => array(), 'supplier_scrubbed' => array());
$conn->begin_transaction();
try {
    foreach ($targets as $table => $cond) {
        $ids = array();
        $r = $conn->query("SELECT id FROM $table WHERE COALESCE(is_deleted,0) = 0 AND $cond");
        while ($row = $r->fetch_assoc()) { $ids[] = intval($row['id']); }
        $o(sprintf('%-22s فاسدٌ حي: %d %s', $table, count($ids), $ids ? ('[' . implode(',', array_slice($ids, 0, 12)) . (count($ids) > 12 ? ',…' : '') . ']') : ''));
        $log['soft_deleted'][$table] = $ids;
        if ($APPLY && $ids) {
            $conn->query("UPDATE $table SET is_deleted = 1, " .
                ($table === 'proc_lookup' ? 'is_active = 0, ' : '') .
                "deleted_at = NOW() WHERE id IN (" . implode(',', $ids) . ")");
        }
    }

    // proc_supplier: مسحُ الحقول الملوثة لا الصف
    $supCols = array();
    $r = $conn->query('SHOW COLUMNS FROM proc_supplier');
    while ($c = $r->fetch_assoc()) {
        if (stripos($c['Type'], 'char') !== false || stripos($c['Type'], 'text') !== false) { $supCols[] = $c['Field']; }
    }
    $supCols = array_values(array_diff($supCols, array('name', 'code')));   // الاسمُ والكودُ مرجعا عرضٍ — لا يُمسّان
    foreach ($supCols as $c) {
        $ids = array();
        $r = $conn->query("SELECT id FROM proc_supplier WHERE COALESCE(is_deleted,0) = 0 AND " . $phraseCond(array($c)));
        while ($row = $r->fetch_assoc()) { $ids[] = intval($row['id']); }
        if (!$ids) { continue; }
        $o(sprintf('proc_supplier.%-18s ملوث: %d [%s]', $c, count($ids), implode(',', $ids)));
        $log['supplier_scrubbed'][$c] = $ids;
        if ($APPLY) {
            $conn->query("UPDATE proc_supplier SET $c = NULL WHERE id IN (" . implode(',', $ids) . ")");
        }
    }

    if ($APPLY) {
        $dir = dirname(__DIR__) . '/storage/reports';
        if (!is_dir($dir)) { mkdir($dir, 0775, true); }
        $path = $dir . '/proc_uat_cleanup_' . date('Ymd_His') . '.json';
        file_put_contents($path, json_encode($log, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $conn->commit();
        $o('✔ COMMITTED · سجل التراجع: ' . $path);
    } else {
        $conn->rollback();
        $o('— dry-run: لا تغيير');
    }
} catch (\Throwable $t) {
    $conn->rollback();
    $o('✘ ROLLED BACK: ' . $t->getMessage());
    exit(1);
}
