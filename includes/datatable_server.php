<?php
/**
 * مساعدُ المعالجة الخادمية لجداول DataTables — H-22 (UI-01 §4 · §9)
 * ───────────────────────────────────────────────────────────────────────────
 * «المعالجةُ خادمية — الترقيمُ والفرزُ والبحثُ في الخادم أبدًا، ولا تحميلَ
 *  لكل السجلات في المتصفح مهما قلّت» (UI-01 §4) · «خمسون صفًّا افتراضًا ولا
 *  استعلامَ بلا حدٍّ ولا فهرس» (§9).
 *
 * طبقةٌ رقيقةٌ عمدًا: تفكّ بروتوكولَ DataTables وتُحصّنه (سقفُ الطول ·
 * قائمةُ سماحٍ للفرز — لا يصل اسمُ عمودٍ من المتصفح إلى SQL أبدًا) وتُخرج
 * الردَّ القياسي. الاستعلامُ نفسُه يبقى في شاشته/مستودعه لأن أنماطَ العزل
 * تختلف (بوابةُ المستأجر · scopedQuery · مستودعٌ خام) — لا استعلامَ عامًّا
 * يُفلت من نمط عزل شاشته.
 *
 * الاستعمال في نقطة ?ajax=dt:
 *   $dt = ems_dt_params(array(0 => 'al.created_at', 3 => 'al.module_name'));
 *   … نفّذ العدَّ والصفحةَ بمعاملات $dt …
 *   ems_dt_emit($dt['draw'], $total, $filtered, $rowsCells);
 */

if (!function_exists('ems_dt_params')) {

    /**
     * فكُّ طلب DataTables وتحصينُه.
     *
     * @param array $orderableCols  قائمةُ سماح: فهرسُ العمود ⇒ عبارةُ SQL للفرز.
     *                              ما ليس فيها يسقط إلى أول عنصرٍ منها.
     * @param int   $defaultLength  حجمُ الصفحة الافتراضي (UI-01 §9: 50).
     * @param int   $maxLength      السقفُ الصلب — طلبٌ أكبر يُقصّ إليه.
     * @return array{draw:int,start:int,length:int,search:string,order_sql:string}
     */
    function ems_dt_params(array $orderableCols, $defaultLength = 50, $maxLength = 250)
    {
        $draw   = isset($_REQUEST['draw']) ? intval($_REQUEST['draw']) : 0;
        $start  = isset($_REQUEST['start']) ? max(0, intval($_REQUEST['start'])) : 0;
        $length = isset($_REQUEST['length']) ? intval($_REQUEST['length']) : intval($defaultLength);
        // length=-1 (الكل) محظورٌ بنص UI-01 §4 «لا تحميلَ لكل السجلات» — يُقص للسقف
        if ($length <= 0 || $length > intval($maxLength)) {
            $length = ($length <= 0) ? intval($maxLength) : intval($maxLength);
        }

        $search = '';
        if (isset($_REQUEST['search']) && is_array($_REQUEST['search']) && isset($_REQUEST['search']['value'])) {
            $search = trim(strval($_REQUEST['search']['value']));
        }

        // الفرز: فهرسُ العمود يُترجم عبر قائمة السماح حصرًا — الافتراضُ أولُها
        $orderSql = '';
        if (!empty($orderableCols)) {
            $firstCol = reset($orderableCols);
            $colSql = $firstCol;
            $dir = 'DESC';
            if (isset($_REQUEST['order']) && is_array($_REQUEST['order']) && isset($_REQUEST['order'][0])) {
                $oc = intval($_REQUEST['order'][0]['column'] ?? -1);
                if (isset($orderableCols[$oc])) { $colSql = $orderableCols[$oc]; }
                $od = strtolower(strval($_REQUEST['order'][0]['dir'] ?? ''));
                $dir = ($od === 'asc') ? 'ASC' : 'DESC';
            }
            $orderSql = $colSql . ' ' . $dir;
        }

        return array(
            'draw'      => $draw,
            'start'     => $start,
            'length'    => $length,
            'search'    => $search,
            'order_sql' => $orderSql,
        );
    }
}

if (!function_exists('ems_dt_like_clause')) {
    /**
     * بناءُ شرط بحثٍ LIKE على الحقول المعرَّفة (UI-01 §4: «يبحث في الحقول
     * المعرَّفة لا في كل عمود») — يعيد '' حين لا بحث.
     *
     * @param \mysqli $conn
     * @param string  $search
     * @param array   $columns أسماءُ أعمدةِ SQL المسموحُ البحثُ فيها
     */
    function ems_dt_like_clause(\mysqli $conn, $search, array $columns)
    {
        $search = trim(strval($search));
        if ($search === '' || empty($columns)) { return ''; }
        $esc = mysqli_real_escape_string($conn, $search);
        $parts = array();
        foreach ($columns as $c) { $parts[] = $c . " LIKE '%{$esc}%'"; }
        return '(' . implode(' OR ', $parts) . ')';
    }
}

if (!function_exists('ems_dt_emit')) {
    /**
     * إخراجُ ردِّ DataTables القياسي وإنهاءُ الطلب.
     *
     * @param int   $draw
     * @param int   $recordsTotal    عددُ النطاق الكامل (قبل البحث)
     * @param int   $recordsFiltered عددُه بعد البحث والفلاتر
     * @param array $data            مصفوفةُ صفوف (كلُّ صفٍّ مصفوفةُ خلايا أو كائن)
     */
    function ems_dt_emit($draw, $recordsTotal, $recordsFiltered, array $data)
    {
        while (ob_get_level() > 0) { ob_end_clean(); }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array(
            'draw'            => intval($draw),
            'recordsTotal'    => intval($recordsTotal),
            'recordsFiltered' => intval($recordsFiltered),
            'data'            => $data,
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
