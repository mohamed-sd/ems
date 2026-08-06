<?php
/**
 * includes/cmp03_local_store.php — محوّل شاشات CMP-03 إلى جداولها الأصلية (الموجة ٢)
 * ───────────────────────────────────────────────────────────────────────────
 * كانت الشاشات المولَّدة تكتب/تقرأ المخزن البيني cmp03_screen_rows (JSON بلا
 * فهارس ولا قيود). بعد هجرة 2026_11_14_cmp03_wave2_tables صار لكل شاشة جدولها
 * scr_* بأعمدة دلالية — وهاتان الدالتان تحفظان شكل الشاشات المولَّد نفسه
 * (payload تسمية←قيمة) فلا يعاد توليد الواجهات:
 *   cmp03_store_insert(): الحمولة ← أعمدة الجدول (الفارغ NULL — NULLIF).
 *   cmp03_store_rows():   الصفوف بشكل المخزن القديم (id·payload·status·…).
 * fail-closed: شاشة خارج السجل ترفض الكتابة (لا سقوط صامت للمخزن البيني).
 */

require_once __DIR__ . '/cmp03_registry.php';

if (!function_exists('cmp03_store_norm')) {
    /** تطبيع التسمية — مرآة مولّد السجل (فراغات + نزع التشكيل والتطويل) */
    function cmp03_store_norm($s)
    {
        $s = preg_replace('/\s+/u', ' ', trim((string) $s));
        return preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $s);
    }
}

if (!function_exists('cmp03_store_insert')) {
    /**
     * حفظ صف شاشةٍ في جدولها الأصلي. الحمولة (تسمية←قيمة) تُسقط على الأعمدة
     * عبر خريطة السجل؛ تسمية خارج الخريطة تُتجاهل معلَنة في سجل الأخطاء
     * (لا تلفيق عمود). يعيد true/false.
     */
    function cmp03_store_insert(mysqli $conn, $companyId, $canonical, array $payload, $status, $uid, $creatorName)
    {
        $reg = cmp03_registry();
        if (!isset($reg[$canonical])) {
            error_log("cmp03_local_store: شاشة خارج السجل — {$canonical}");
            return false;
        }
        $table = $reg[$canonical]['table'];
        $map   = $reg[$canonical]['map'];
        $cols = array('company_id', 'status', 'is_seed', 'created_by', 'created_by_name');
        $vals = array(intval($companyId), (string) $status, 0, intval($uid), (string) $creatorName);
        $types = 'isiis';
        foreach ($payload as $label => $v) {
            $n = cmp03_store_norm($label);
            if (!isset($map[$n])) {
                error_log("cmp03_local_store: تسمية بلا عمود في {$canonical} — «{$label}»");
                continue;
            }
            $v = trim((string) $v);
            if ($v === '') { continue; } // NULLIF — الفارغ يبقى NULL
            $cols[] = $map[$n];
            $vals[] = $v;
            $types .= 's';
        }
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $cols) . "`)
                VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ")";
        $st = $conn->prepare($sql);
        if (!$st) { error_log("cmp03_local_store: prepare فشل — " . $conn->error); return false; }
        $st->bind_param($types, ...$vals);
        $ok = $st->execute();
        if (!$ok) { error_log("cmp03_local_store: execute فشل — " . $st->error); }
        $st->close();
        return (bool) $ok;
    }
}

if (!function_exists('cmp03_store_rows')) {
    /**
     * قراءة صفوف شاشةٍ من جدولها الأصلي بشكل المخزن البيني القديم:
     * [id, payload(تسمية←قيمة), status, created_by_name, created_at, is_seed].
     * $companyId <= 0 (سوبر بلا شركة) يقرأ الكل.
     */
    function cmp03_store_rows(mysqli $conn, $canonical, $companyId, $limit = 500)
    {
        $reg = cmp03_registry();
        if (!isset($reg[$canonical])) { return array(); }
        $table = $reg[$canonical]['table'];
        // الحمولة تُعاد بالتسمية الأصلية (بتشكيلها) — فمفاتيح cmp03_cell في
        // الشاشات هي تسميات $FIELDS الحرفية لا المطبَّعة
        $rev = $reg[$canonical]['labels'];
        $co = intval($companyId);
        $lim = max(1, intval($limit));
        $sql = "SELECT * FROM `{$table}`" . ($co > 0 ? " WHERE company_id = {$co}" : '')
             . " ORDER BY id DESC LIMIT {$lim}";
        $out = array();
        $r = mysqli_query($conn, $sql);
        while ($r && ($x = mysqli_fetch_assoc($r))) {
            $payload = array();
            foreach ($rev as $col => $normLabel) {
                if (isset($x[$col]) && $x[$col] !== null && $x[$col] !== '') {
                    $payload[$normLabel] = $x[$col];
                }
            }
            $out[] = array(
                'id' => intval($x['id']),
                'payload' => $payload,
                'status' => $x['status'],
                'created_by_name' => $x['created_by_name'],
                'created_at' => $x['created_at'],
                'is_seed' => intval($x['is_seed']),
            );
        }
        return $out;
    }
}
