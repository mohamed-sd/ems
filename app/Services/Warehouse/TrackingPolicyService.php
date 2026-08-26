<?php
/**
 * app/Services/Warehouse/TrackingPolicyService.php — سياسةُ تتبّعِ الصنف (DEC-OPEN-15)
 * ═══════════════════════════════════════════════════════════════════════════
 * **قرارُ المالكِ نصًّا** (2026-08-26):
 *
 * > «النظامُ يدعم كلَّ مستوياتِ التتبّعِ من البداية، لكنَّ تفعيلَها وإلزاميّتَها
 * >  يكونان قابلَين للضبطِ حسب الفئةِ والصنفِ ومرحلةِ نضجِ البيانات.»
 *
 * وثلاثُ كلماتٍ تلخّص المرحلة: **قدرةٌ كاملة · ضبطٌ مرن · لا تعطيلَ للتشغيل**.
 *
 * ◆ **الحلُّ بمستويَين**: سياسةُ الصنفِ تغلب افتراضَ فئتِه، وافتراضُ الفئةِ يغلب
 *   الافتراضَ الجذريَّ (كلُّها `OFF`). و**لا شيءَ مكتوبٌ في هذا الملفّ**: لا
 *   فئةٌ ولا صنفٌ ولا درجة — كلُّها من `proc_track_policy`.
 *
 * ◆ **والزمنُ بُعدٌ في الحلّ**: `resolve()` تأخذ تاريخًا وتعيد **السياسةَ
 *   الساريةَ لحظتَه** لا الساريةَ اليوم. فحركةٌ وقعت قبل تشديدِ السياسةِ
 *   **تُحاسَب بسياستِها هي** — «ولا نطبّق القاعدةَ الجديدةَ بأثرٍ رجعيّ».
 *
 * ◆ **والاختياريُّ لا يمنع أبدًا** (‏القاعدةُ ⑨ في الجواب): `checkOperation`
 *   تعيد ثلاثةَ أحكام — `pass` يمضي · `gap` يمضي **ويُسجَّل قيدَ جودة** ·
 *   `block` يُردُّ. و`block` **لا يقع إلّا على `REQUIRED`** أو على منتهٍ
 *   بسياسةِ `HARD_BLOCK`. ⛔ ولا حاجبَ من نقصِ بياناتٍ اختياريّة.
 *
 * ◆ **وسلسلةُ ارتدادِ الصرفِ لا تتوقّف** (‏القاعدةُ ⑯): `FEFO` إن توفّرت
 *   الصلاحية ← `FIFO` إن توفّرت تواريخُ الاستلام ← **بالكميّة** إن لم تتوفّر
 *   بياناتٌ كافية. والاقتراحُ في المرحلةِ الانتقاليّةِ **اقتراحٌ لا إلزام**.
 *
 * ◆ **وأمينُ المخزنِ لا يمدّد الصلاحيةَ من عنده** (‏القاعدةُ ⑬): تجاوزُ المنتهي
 *   يوجب دورًا مخوَّلًا **من السياسةِ نفسِها** لا اسمًا في الشيفرة.
 *
 * ⛔ **ولا قيمةَ افتراضيّةٌ مخترَعةٌ هنا**: صنفٌ بلا سياسةٍ ولا فئةٍ مسجَّلةٍ
 *   يعود بكلِّ الخصائصِ `OFF` — أي **لا يُطلَب منه شيءٌ ولا يُمنَع بشيء**،
 *   وهو أسلمُ افتراضٍ في مرحلةِ انتقال.
 */

namespace App\Services\Warehouse;

use App\Core\TenantDb;

class TrackingPolicyService
{
    /** الخصائصُ الثمانُ بأسمائها في السجلّ — مصدرٌ واحدٌ لأسماءِ الأعمدة */
    const LEVEL_KEYS = array('lot', 'serial', 'mfg_date', 'expiry', 'warranty');
    const MODE_KEYS  = array('expiry_enforce', 'issue_policy', 'requalify');

    /** الافتراضُ الجذريُّ — **لا يُطلَب شيءٌ ولا يُمنَع شيء** */
    const ROOT_DEFAULT = array(
        'lot' => 'OFF', 'serial' => 'OFF', 'mfg_date' => 'OFF',
        'expiry' => 'OFF', 'warranty' => 'OFF',
        'expiry_enforce' => 'WARNING', 'issue_policy' => 'FIFO', 'requalify' => 'DISABLED',
        'override_authority' => '', 'scope' => 'NONE', 'version' => 0,
    );

    /**
     * **السياسةُ الساريةُ لصنفٍ في تاريخٍ بعينه.**
     *
     * الأسبقيّة: سياسةُ الصنفِ ⇐ افتراضُ فئتِه ⇐ الافتراضُ الجذريّ.
     * و`$onDate` يجعل الحلَّ **تاريخيًّا**: أعطِها تاريخَ الحركةِ لتحاسبها
     * بسياستِها هي — وأعطِها اليومَ لتعرف ما يسري الآن.
     *
     * @return array الخصائصُ الثمانُ + `scope` + `version` + `override_authority`
     */
    public static function resolve(TenantDb $gate, $itemId, $onDate = null)
    {
        $itemId = (int) $itemId;
        $day = $onDate !== null && $onDate !== '' ? substr((string) $onDate, 0, 10) : date('Y-m-d');

        $out = self::ROOT_DEFAULT;
        $item = $gate->selectOne('proc_item', array('where' => array('id' => $itemId)));
        if (!$item) { return $out; }

        /* ① افتراضُ الفئةِ إن وُجد */
        $cat = trim((string) (isset($item['category']) ? $item['category'] : ''));
        if ($cat !== '') {
            $p = self::liveRow($gate, 'CATEGORY', $cat, $day);
            if ($p) { $out = self::merge($out, $p, 'CATEGORY'); }
        }
        /* ② ثمَّ تخصيصُ الصنفِ — **يغلب الافتراض** */
        $p = self::liveRow($gate, 'ITEM', (string) $itemId, $day);
        if ($p) { $out = self::merge($out, $p, 'ITEM'); }

        return $out;
    }

    /** صفُّ السياسةِ السارية — **بالتاريخِ لا بالأحدث** */
    private static function liveRow(TenantDb $gate, $kind, $key, $day)
    {
        $rows = $gate->select('proc_track_policy', array(
            'where' => array('scope_kind' => $kind, 'scope_key' => $key),
            'orderBy' => 'version DESC', 'limit' => 50,
        ));
        foreach ($rows as $r) {
            $from = (string) $r['effective_from'];
            $to   = (string) $r['effective_to'];
            if ($from !== '' && $day < $from) { continue; }
            if ($to !== '' && $to !== '0000-00-00' && $day > $to) { continue; }
            return $r;
        }
        return null;
    }

    private static function merge(array $base, array $row, $scope)
    {
        foreach (array_merge(self::LEVEL_KEYS, self::MODE_KEYS) as $k) {
            if (isset($row[$k]) && (string) $row[$k] !== '') { $base[$k] = (string) $row[$k]; }
        }
        if (trim((string) $row['override_authority']) !== '') {
            $base['override_authority'] = (string) $row['override_authority'];
        }
        $base['scope']   = $scope;
        $base['version'] = (int) $row['version'];
        return $base;
    }

    /* ══════════════════════════════════════════════════════════════════════
       الحكمُ على عمليّةٍ — ثلاثةُ أحكامٍ لا حكمان
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * **هل تمضي هذه العمليّةُ ببياناتِ التتبّعِ المقدَّمة؟**
     *
     * `pass`  كلُّ ما توجبه السياسةُ حاضر.
     * `gap`   ينقص **اختياريٌّ** — العمليّةُ تمضي ويُسجَّل نقصُها قيدَ جودة.
     * `block` ينقص **إلزاميٌّ** — وهذا وحدَه ما يمنع.
     *
     * ⛔ **ولا يُبنى `block` على نقصِ اختياريٍّ مهما كثر** — نصُّ القرار:
     *    «لا أريد منعَ الاستلامِ ولا الصرفِ ولا التحويلِ ولا الجرد».
     *
     * @return array{verdict:string,missing:array,policy:array,code:string,detail:string}
     */
    public static function checkOperation(TenantDb $gate, $itemId, array $data, $onDate = null)
    {
        $pol = self::resolve($gate, $itemId, $onDate);
        $need = array(
            'lot'      => array('lot_no',         'الدفعة'),
            'serial'   => array('serial_no',      'الرقم التسلسلي'),
            'mfg_date' => array('mfg_date',       'تاريخ التصنيع'),
            'expiry'   => array('expiry_date',    'تاريخ الصلاحية'),
            'warranty' => array('warranty_until', 'الضمان'),
        );
        $missReq = array(); $missOpt = array();
        foreach ($need as $k => $f) {
            $lvl = (string) $pol[$k];
            if ($lvl === 'OFF') { continue; }
            $val = isset($data[$f[0]]) ? trim((string) $data[$f[0]]) : '';
            if ($val !== '' && $val !== '0000-00-00') { continue; }
            if ($lvl === 'REQUIRED') { $missReq[] = $f[1]; } else { $missOpt[] = $f[1]; }
        }

        if ($missReq) {
            return array('verdict' => 'block', 'missing' => $missReq, 'policy' => $pol,
                'code' => 'TRACKING_REQUIRED_FOR_ITEM',
                'detail' => 'سياسة الصنف توجب ' . implode(' و', $missReq));
        }
        if ($missOpt) {
            return array('verdict' => 'gap', 'missing' => $missOpt, 'policy' => $pol,
                'code' => '', 'detail' => 'بيانات التتبع غير مكتملة وهي ' . implode(' و', $missOpt));
        }
        return array('verdict' => 'pass', 'missing' => array(), 'policy' => $pol,
            'code' => '', 'detail' => '');
    }

    /**
     * **تسجيلُ نقصٍ اختياريٍّ — قيدُ جودةٍ لا حاجبُ عمل** (القاعدةُ ⑨).
     * تُنادى بعد `checkOperation` حين يكون الحكمُ `gap`، والعمليّةُ **تمضي**.
     */
    public static function logGap(TenantDb $gate, $itemId, $opKind, $opRef, array $missing, $level = 'OPTIONAL')
    {
        if (!$missing) { return 0; }
        return (int) $gate->insert('proc_track_gap', array(
            'item_id' => (int) $itemId, 'op_kind' => (string) $opKind,
            'op_ref' => (string) $opRef, 'missing' => implode(' و', $missing),
            'policy_level' => (string) $level,
        ));
    }

    /* ══════════════════════════════════════════════════════════════════════
       المنتهي — ثلاثةُ مستوياتِ إنفاذٍ لا مستويان
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * **حكمُ الصرفِ على صنفٍ منتهٍ** بحسب `expiry_enforce`:
     *
     * `WARNING`           ينبّه ويمضي — وهو افتراضُ المرحلةِ الحالية.
     * `APPROVAL_REQUIRED` لا يمضي إلّا باعتمادِ **الدورِ المخوَّلِ في السياسة**.
     * `HARD_BLOCK`        لا يمضي مطلقًا.
     *
     * ⛔ **وأمينُ المخزنِ لا يمدّد الصلاحيةَ من عنده**: `$approverRole` يُقارَن
     *    بـ`override_authority` من السياسةِ — ودورُ المنفِّذِ لا يُقبل بديلًا.
     */
    public static function expiryVerdict(TenantDb $gate, $itemId, $expiryDate, array $ctx = array(), $today = null)
    {
        $pol = self::resolve($gate, $itemId, $today);
        if ((string) $pol['expiry'] === 'OFF') {
            return array('verdict' => 'pass', 'policy' => $pol, 'code' => '', 'detail' => '');
        }
        $exp = trim((string) $expiryDate);
        if ($exp === '' || $exp === '0000-00-00') {
            return array('verdict' => 'pass', 'policy' => $pol, 'code' => '',
                'detail' => 'لا تاريخ صلاحية مسجل فلا حكم عليه');
        }
        $day = $today !== null && $today !== '' ? substr((string) $today, 0, 10) : date('Y-m-d');
        if ($exp >= $day) {
            return array('verdict' => 'pass', 'policy' => $pol, 'code' => '', 'detail' => '');
        }

        $mode = (string) $pol['expiry_enforce'];
        if ($mode === 'HARD_BLOCK') {
            return array('verdict' => 'block', 'policy' => $pol,
                'code' => 'EXPIRED_ITEM_ISSUE_BLOCKED',
                'detail' => 'الصنف منتهي الصلاحية وسياسته تمنع صرفه منعا كاملا');
        }
        if ($mode === 'APPROVAL_REQUIRED') {
            $auth = trim((string) $pol['override_authority']);
            $by   = trim((string) (isset($ctx['approver_role']) ? $ctx['approver_role'] : ''));
            $why  = trim((string) (isset($ctx['override_reason']) ? $ctx['override_reason'] : ''));
            if ($by === '' || $by !== $auth) {
                return array('verdict' => 'block', 'policy' => $pol,
                    'code' => 'EXPIRY_OVERRIDE_NEEDS_AUTHORITY',
                    'detail' => 'صرف المنتهي يوجب اعتماد ' . ($auth !== '' ? $auth : 'سلطة مسجلة في السياسة'));
            }
            if ($why === '') {
                return array('verdict' => 'block', 'policy' => $pol,
                    'code' => 'EXPIRY_OVERRIDE_WITHOUT_REASON',
                    'detail' => 'التجاوز بلا سبب مكتوب لا يقبل');
            }
            return array('verdict' => 'approved_override', 'policy' => $pol, 'code' => '',
                'detail' => 'تجاوز معتمد من ' . $auth);
        }
        /* WARNING — ينبّه ويمضي */
        return array('verdict' => 'warn', 'policy' => $pol, 'code' => '',
            'detail' => 'الصنف منتهي الصلاحية والسياسة تنبه ولا تمنع');
    }

    /* ══════════════════════════════════════════════════════════════════════
       ترتيبُ الصرفِ — سلسلةُ ارتدادٍ لا قاعدةٌ واحدة
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * **الدفعاتُ مرتَّبةً بالسياسةِ مع الارتدادِ التلقائيّ** (القاعدةُ ⑯):
     *
     * `FEFO` إن كانت لكلِّ دفعةٍ صلاحيةٌ ← وإلّا `FIFO` بتاريخِ الاستلام
     * ← وإلّا `QUANTITY` من الرصيدِ المتاحِ بالطريقةِ الحالية.
     *
     * **والمُرجَعُ اقتراحٌ لا إلزام** في المرحلةِ الانتقاليّة: `applied` يقول
     * أيَّ قاعدةٍ انطبقت فعلًا، و`asked` يقول أيَّها طُلبت — والفرقُ بينهما
     * هو ما يقيس نضجَ البيانات.
     *
     * @return array{asked:string,applied:string,why:string,lots:array}
     */
    public static function suggestIssueOrder(TenantDb $gate, $itemId, $warehouseId, $onDate = null)
    {
        $pol = self::resolve($gate, $itemId, $onDate);
        $asked = (string) $pol['issue_policy'];

        $lots = $gate->select('proc_lot', array(
            'where' => array('item_id' => (int) $itemId, 'warehouse_id' => (int) $warehouseId),
            'limit' => 500,
        ));
        $live = array();
        foreach ($lots as $l) { if ((float) $l['qty_available'] > 0) { $live[] = $l; } }

        if (!$live) {
            return array('asked' => $asked, 'applied' => 'QUANTITY', 'lots' => array(),
                'why' => 'لا دفعات متاحة فالصرف من الرصيد المتاح بالطريقة الحالية');
        }

        $allExpiry = true; $allReceipt = true;
        foreach ($live as $l) {
            $e = (string) $l['expiry_date'];
            if ($e === '' || $e === '0000-00-00') { $allExpiry = false; }
            if ((string) $l['created_at'] === '') { $allReceipt = false; }
        }

        if ($asked === 'MANUAL') {
            return array('asked' => $asked, 'applied' => 'MANUAL', 'lots' => $live,
                'why' => 'السياسة تترك الاختيار لامين المخزن');
        }
        if ($asked === 'FEFO' && $allExpiry) {
            usort($live, function ($a, $b) { return strcmp((string) $a['expiry_date'], (string) $b['expiry_date']); });
            return array('asked' => $asked, 'applied' => 'FEFO', 'lots' => $live,
                'why' => 'كل دفعة لها صلاحية فالاقرب انتهاء يقترح اولا');
        }
        if ($allReceipt) {
            usort($live, function ($a, $b) { return strcmp((string) $a['created_at'], (string) $b['created_at']); });
            return array('asked' => $asked, 'applied' => 'FIFO', 'lots' => $live,
                'why' => ($asked === 'FEFO'
                    ? 'صلاحية ناقصة في بعض الدفعات فارتد الترتيب الى الاقدم استلاما'
                    : 'الترتيب بتاريخ الاستلام'));
        }
        return array('asked' => $asked, 'applied' => 'QUANTITY', 'lots' => $live,
            'why' => 'بيانات التتبع غير كافية للترتيب فالصرف من الرصيد المتاح');
    }

    /* ══════════════════════════════════════════════════════════════════════
       حلُّ السياسةِ إلى أعمدةِ الصنف — يُشتقُّ ولا يُكتب بيد
       ══════════════════════════════════════════════════════════════════════ */

    /**
     * **يعيد اشتقاقَ أعمدةِ الصنفِ المحلولةِ من السجلّ.**
     * والعلَمُ الثنائيُّ من W09 يبقى ويُشتقُّ: `REQUIRED` ⇒ `1` وما دونه `0` —
     * فبوّاباتُ W09 تقرأ معناها الأصليَّ «أيُلزِم هذا الصنفُ بياناتِ تتبّع؟».
     */
    public static function materialize(TenantDb $gate, $itemId, $onDate = null)
    {
        $pol = self::resolve($gate, $itemId, $onDate);
        $gate->update('proc_item', array(
            'track_lot_level'      => $pol['lot'],
            'track_serial_level'   => $pol['serial'],
            'track_mfg_level'      => $pol['mfg_date'],
            'track_expiry_level'   => $pol['expiry'],
            'track_warranty_level' => $pol['warranty'],
            'expiry_enforce'       => $pol['expiry_enforce'],
            'issue_policy'         => $pol['issue_policy'],
            'requalify'            => $pol['requalify'],
            'policy_scope'         => $pol['scope'],
            'policy_version'       => (int) $pol['version'],
            /* العلَمُ الثنائيُّ = الإلزامُ وحدَه */
            'track_lot'    => ($pol['lot'] === 'REQUIRED') ? 1 : 0,
            'track_serial' => ($pol['serial'] === 'REQUIRED') ? 1 : 0,
            'track_expiry' => ($pol['expiry'] === 'REQUIRED') ? 1 : 0,
        ), array('id' => (int) $itemId));
        return $pol;
    }

    /* ══════════════════════════════════════════════════════════════════════
       جودةُ البيانات — تُقاس ولا تحجب (القاعدتان ㉚ و㉛)
       ══════════════════════════════════════════════════════════════════════ */

    /** نسبُ اكتمالِ بياناتِ التتبّع — **مقياسٌ لا حاجب** */
    public static function dataQuality(TenantDb $gate)
    {
        $items = $gate->select('proc_item', array('limit' => 5000));
        $n = count($items);
        $o = array('items' => $n, 'with_policy' => 0, 'item_scoped' => 0,
                   'lot_on' => 0, 'serial_on' => 0, 'mfg_on' => 0, 'expiry_on' => 0, 'warranty_on' => 0,
                   'required_any' => 0, 'with_lot_rows' => 0, 'with_serial_rows' => 0,
                   'open_gaps' => 0, 'legacy_qty' => 0.0);
        foreach ($items as $it) {
            $id = (int) $it['id'];
            if ((string) $it['policy_scope'] !== '' && (string) $it['policy_scope'] !== 'NONE') { $o['with_policy']++; }
            if ((string) $it['policy_scope'] === 'ITEM') { $o['item_scoped']++; }
            foreach (array('lot' => 'track_lot_level', 'serial' => 'track_serial_level',
                           'mfg' => 'track_mfg_level', 'expiry' => 'track_expiry_level',
                           'warranty' => 'track_warranty_level') as $k => $col) {
                $v = (string) (isset($it[$col]) ? $it[$col] : 'OFF');
                if ($v !== 'OFF') { $o[$k . '_on']++; }
                if ($v === 'REQUIRED') { $o['required_any']++; }
            }
            if ((int) $gate->count('proc_lot', array('where' => array('item_id' => $id))) > 0) { $o['with_lot_rows']++; }
            if ((int) $gate->count('proc_serial', array('where' => array('item_id' => $id))) > 0) { $o['with_serial_rows']++; }
        }
        $o['open_gaps'] = (int) $gate->count('proc_track_gap', array('where' => array('resolved' => 0)));
        foreach ($gate->select('proc_stock_state', array('where' => array('state_key' => 'LEGACY_UNTRACKED'), 'limit' => 2000)) as $s) {
            $o['legacy_qty'] += (float) $s['qty'];
        }
        return $o;
    }
}
