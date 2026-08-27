<?php
namespace App\Services\Workspace;

/**
 * app/Services/Workspace/RequestLauncher.php — مُطلِقُ الطلبات (RPR-W15)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **القاعدةُ الرباعيّةُ بنصِّ المالك** (‏القرار ③ · `DEC-OPEN-17` معتمَد):
 *
 *      Governance governs the registry
 *      Domain owns request definition
 *      AAM resolves approval authority
 *      System executes routing
 *
 * ◆ **ومساحةُ عملي `Launcher + Projection` ولا تصير `Owner`** — نصُّ القرارِ
 *   حرفًا. فهذه الخدمةُ:
 *   - **تُطلِق** طلبًا **يملك النطاقُ تعريفَه**: تقرأ النوعَ من
 *     `gov_request_type` (‏سجلٌّ تحكمه الحوكمة) وتسلّمه **لخدمةِ مالكِه**
 *     المسجَّلةِ في الصفِّ نفسِه — ⛔ **ولا تكتب هي حرفًا في جدولِ أحد**.
 *   - **وتعرض** حالتَه **بمرجعٍ حيّ**: تقرأ من جدولِ المالكِ بعمودِ صاحبِ
 *     الطلبِ المسجَّلِ في السجلّ — ⛔ **ولا نسخةَ محلّيّةً في مساحةِ العمل**.
 *
 * ◆ **والتوجيهُ بيانٌ لا شيفرة**: `owner_table` و`owner_service` و
 *   `projection_user_col` تُقرأ من السجلِّ المركزيّ، و`chk_grt_binding` يردُّ
 *   نوعًا نافذًا بلا رابطةٍ مكتملة. ⛔ **ولا خريطةَ أنواعٍ مكتوبةٌ هنا.**
 *
 * ◆ **و`AAM` يحدّد من يعتمد ولا يحدّد إلى أين يذهب**: `authority_rule_id`
 *   يُمرَّر لخدمةِ المالكِ ولا يقرّر الوجهةَ — الوجهةُ `routing_rule_ref`.
 * ═══════════════════════════════════════════════════════════════════════════
 */
final class RequestLauncher
{
    const OK                 = 'OK';
    const UNKNOWN_TYPE       = 'UNKNOWN_REQUEST_TYPE';
    const TYPE_NOT_ACTIVE    = 'REQUEST_TYPE_NOT_ACTIVE';
    const BINDING_MISSING    = 'OWNER_BINDING_MISSING';
    const OWNER_REFUSED      = 'OWNER_SERVICE_REFUSED';
    const LOCAL_STORE_DENIED = 'LOCAL_STORE_DENIED';

    /**
     * أنواعُ الطلباتِ المتاحةُ للإطلاق — **من السجلِّ المركزيِّ وحدَه**.
     * ⛔ ولا قائمةَ أنواعٍ مكتوبةٌ في شاشةٍ ولا في هذه الخدمة.
     */
    public static function catalogue($gate, $companyId)
    {
        $out = array();
        foreach (self::registryRows($gate) as $x) {
            if ((string) $x['state'] !== 'active' || $x['retired_at'] !== null) { continue; }
            $out[] = $x;
        }
        usort($out, function ($a, $b) {
            $c = strcmp((string) $a['definition_owner_dept'], (string) $b['definition_owner_dept']);
            return $c !== 0 ? $c : strcmp((string) $a['type_code'], (string) $b['type_code']);
        });
        return $out;
    }

    /** صفُّ نوعٍ واحدٍ بحالتِه — والغائبُ يُردُّ ولا يُخترَع. */
    public static function type($gate, $companyId, $typeCode)
    {
        $best = null;
        foreach (self::registryRows($gate) as $x) {
            if ((string) $x['type_code'] !== (string) $typeCode) { continue; }
            if ($best === null || (int) $x['version_no'] > (int) $best['version_no']) { $best = $x; }
        }
        return $best;
    }

    /**
     * **السجلُّ المركزيُّ يُقرأ عبرَ بوّابةِ العزلِ لا باستعلامٍ خامّ**
     * (‏`FR-SEC-006` · `GAP-29`) — والكيانُ يُحقَن ولا يُمرَّر.
     */
    private static function registryRows($gate)
    {
        if ($gate === null) { return array(); }
        try { return $gate->select('gov_request_type', array('orderBy' => 'id', 'limit' => 500)); }
        catch (\Throwable $t) { \error_log('w15 request registry: ' . $t->getMessage()); return array(); }
    }

    /**
     * **يُطلِق** الطلبَ عند مالكِه — ولا يخزّن حقيقتَه.
     *
     * @return array `verdict` · `owner_dept` · `owner_table` · `owner_row_id`
     *               · `authority_rule` · `why`
     */
    public static function launch(\mysqli $conn, array $user, $typeCode, array $payload)
    {
        $companyId = isset($user['company_id']) ? (int) $user['company_id'] : 0;
        /* **البوّابةُ تأتي مع الفاعلِ إن كان النداءُ من نظامٍ بلا جلسة** —
           وإلّا فبوّابةُ الجلسة. ⛔ ولا استعلامَ خامٍّ على السجلِّ المركزيّ. */
        $gate = (isset($user['gate']) && $user['gate'] !== null) ? $user['gate'] : \ems_tenant_db();
        $t = self::type($gate, $companyId, $typeCode);
        if (!$t) {
            return self::fail(self::UNKNOWN_TYPE, 'نوع الطلب غير مسجل في السجل المركزي');
        }
        if ((string) $t['state'] !== 'active') {
            return self::fail(self::TYPE_NOT_ACTIVE, 'نوع الطلب غير نافذ');
        }
        if ((string) $t['owner_table'] === '' || (string) $t['owner_service'] === ''
            || (string) $t['projection_user_col'] === '') {
            return self::fail(self::BINDING_MISSING, 'رابطة المالك ناقصة في السجل');
        }
        /* **مساحةُ عملي لا تملك** — والنطاقُ المالكُ لا يكون الحوكمةَ ولا هي. */
        if ((string) $t['definition_owner_dept'] === 'WS-MY') {
            return self::fail(self::LOCAL_STORE_DENIED, 'مساحة العمل لا تملك تعريف طلب');
        }

        /* **النظامُ ينفّذ التوجيه**: خدمةُ المالكِ المسجَّلةُ هي التي تكتب —
           **والنظامُ يحمّلها من موضعِها المشتقِّ من اسمِها**، فالتحميلُ جزءٌ من
           التوجيهِ لا شرطٌ على المُطلِق. ⛔ **ولا قائمةَ خدماتٍ مكتوبةٌ هنا.** */
        list($cls, $method) = array_pad(explode('::', (string) $t['owner_service'], 2), 2, 'createFromLauncher');
        self::loadOwnerService($cls);
        if (!class_exists($cls) || !method_exists($cls, $method)) {
            return self::fail(self::BINDING_MISSING, 'خدمة المالك المسجلة غير موجودة');
        }

        /* **البوّابةُ تُمرَّر مع السياق** — فالنظامُ ينفّذ التوجيهَ ويعرف الكيانَ
           من طلبِ صاحبِه، **ولا تُستنبَط من جلسةٍ قد لا تكون**. وخدمةُ المالكِ
           تستعملها إن جاءت وتقع على بوّابةِ الجلسةِ إن غابت. */
        $ctx = array(
            'company_id'     => $companyId,
            'requester_id'   => isset($user['id']) ? (int) $user['id'] : 0,
            'type_code'      => (string) $t['type_code'],
            'authority_rule' => (string) $t['authority_rule_id'],
            'routing_rule'   => (string) $t['routing_rule_ref'],
            'gate'           => $gate,
        );
        try {
            $res = call_user_func(array($cls, $method), $conn, $ctx, $payload);
        } catch (\Throwable $e) {
            error_log('w15 launcher ' . $cls . ': ' . $e->getMessage());
            return self::fail(self::OWNER_REFUSED, 'خدمة المالك ردت الطلب');
        }
        if (!is_array($res) || empty($res['ok'])) {
            $why = is_array($res) && isset($res['why']) ? (string) $res['why'] : 'خدمة المالك ردت الطلب';
            return self::fail(self::OWNER_REFUSED, $why);
        }

        return array(
            'verdict'        => self::OK,
            'owner_dept'     => (string) $t['definition_owner_dept'],
            'owner_table'    => (string) $t['owner_table'],
            'owner_row_id'   => (int) $res['row_id'],
            'authority_rule' => (string) $t['authority_rule_id'],
            'why'            => '',
        );
    }

    /**
     * **إسقاطُ طلباتِ صاحبِها** — مروحةُ دخولٍ حيّةٌ على جداولِ المُلّاك.
     * ⛔ ولا صفَّ يُقرأ من مخزنٍ محلّيٍّ في مساحةِ العمل.
     */
    public static function projection(\mysqli $conn, $gate, array $user)
    {
        $companyId = isset($user['company_id']) ? (int) $user['company_id'] : 0;
        $userId    = isset($user['id']) ? (int) $user['id'] : 0;
        $out = array();
        foreach (self::catalogue($gate, $companyId) as $t) {
            $col = (string) $t['projection_user_col'];
            $tbl = (string) $t['owner_table'];
            if ($col === '' || $tbl === '') { continue; }
            try {
                $rows = $gate->select($tbl, array(
                    'where'   => array($col => $userId),
                    'orderBy' => 'id DESC',
                    'limit'   => 200,
                ));
            } catch (\Throwable $e) {
                error_log('w15 projection ' . $tbl . ': ' . $e->getMessage());
                continue;
            }
            foreach ($rows as $r) {
                $out[] = array(
                    'type_code'   => (string) $t['type_code'],
                    'type_name'   => (string) $t['name_ar'],
                    'owner_dept'  => (string) $t['definition_owner_dept'],
                    'owner_table' => $tbl,
                    'row_id'      => isset($r['id']) ? (int) $r['id'] : 0,
                    'state'       => self::stateOf($r),
                    'created_at'  => self::whenOf($r),
                );
            }
        }
        return $out;
    }

    /** حالةُ الصفِّ كما سمّاها مالكُه — ولا حالةَ تُخترَع في مساحةِ العمل. */
    private static function stateOf(array $r)
    {
        foreach (array('state', 'status', 'stage', 'head_state') as $c) {
            if (isset($r[$c]) && (string) $r[$c] !== '') { return (string) $r[$c]; }
        }
        return '';
    }

    private static function whenOf(array $r)
    {
        foreach (array('created_at', 'report_datetime', 'date_from', 'call_date') as $c) {
            if (isset($r[$c]) && (string) $r[$c] !== '') { return (string) $r[$c]; }
        }
        return '';
    }

    /**
     * يحمّل خدمةَ المالكِ من موضعِها المشتقِّ من اسمِ صنفِها.
     * ⛔ **ولا يُحمَّل ما هو خارجَ طبقةِ الخدمات** — الاسمُ يأتي من السجلِّ،
     *   والسجلُّ تحكمه الحوكمةُ، ومع ذلك **لا يُقرأ مسارٌ خارجَ `app/Services`**.
     */
    private static function loadOwnerService($cls)
    {
        $cls = ltrim((string) $cls, '\\');
        if (strpos($cls, 'App\\Services\\') !== 0) { return; }
        if (class_exists($cls, false)) { return; }
        $rel = 'app/Services/' . str_replace('\\', '/', substr($cls, strlen('App\\Services\\'))) . '.php';
        if (preg_match('~\.\.~', $rel)) { return; }
        $path = dirname(dirname(dirname(__DIR__))) . '/' . $rel;
        if (is_file($path)) { require_once $path; }
    }

    private static function fail($verdict, $why)
    {
        return array('verdict' => $verdict, 'owner_dept' => '', 'owner_table' => '',
                     'owner_row_id' => 0, 'authority_rule' => '', 'why' => $why);
    }
}
