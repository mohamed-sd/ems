<?php
/**
 * app/Services/Contract/ContractSiteService.php — نطاقُ العقد التشغيلي (P-01)
 * ═══════════════════════════════════════════════════════════════════════════
 * PLAN-03 §2.1 (عبر `EXECUTION_ADDENDUM_PLAN03_ar.md` §3-`P-01`):
 * «`contract_operational_sites` — **نطاقٌ داخل العقد باسمه وتاريخه وبنوده**».
 *
 * ── لماذا كيانٌ لا عمود ──────────────────────────────────────────────────────
 * «نطاقُ العقد» مفهومٌ **بأربعة أبعاد** (اسمٌ · مدةٌ · موقعٌ · بنود)، وقد اختُزل
 * في `contracts.site_id` — **عمودٍ واحدٍ يشير إلى موقعٍ واحد**. فعقدٌ يعمل في
 * موقعين **لا يمكن تمثيلُه أصلًا**. والعمودُ يبقى مرآةً موروثةً (§0-④ لا حذف).
 *
 * ── أربعُ قواعد ─────────────────────────────────────────────────────────────
 * ① **النطاقُ داخل مدة عقده** — بدايةٌ قبله أو نهايةٌ بعده ⇒ **422 بتاريخيه**.
 * ② **والموقعُ مرةً واحدة** في العقد ⇒ **409 بمرجع القائم**.
 * ③ **ورئيسٌ واحدٌ على الأكثر** — وتعيينُ رئيسٍ ثانٍ **ينقل الرئاسة ذرّيًّا**.
 * ④ **والإقفالُ بسببٍ مكتوب** — و`CHECK` يجعل خلافَه مستحيلًا.
 */

namespace App\Services\Contract;

class ContractSiteService
{
    const STATES = array('planned', 'active', 'paused', 'closed');

    const LABELS_AR = array('planned' => 'مخطط', 'active' => 'نافذ',
                            'paused' => 'موقوف', 'closed' => 'مقفل');

    public static function labelAr($s)
    {
        $s = (string) $s;
        return isset(self::LABELS_AR[$s]) ? self::LABELS_AR[$s] : $s;
    }

    // ═════════════════════════════════════════════════════════════════════
    // ① الإضافة
    // ═════════════════════════════════════════════════════════════════════

    /**
     * @return array{ok:bool,code:int,reason:string,scope_id:?int}
     */
    public static function add($conn, $gate, $companyId, $contractId, array $args, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'scope_id' => null);
        $c = self::contractOf($gate, (int) $contractId);
        if (!$c) { $out['code'] = 404; $out['reason'] = 'العقدُ غيرُ موجودٍ في نطاقك'; return $out; }

        $siteId = (int) (isset($args['site_id']) ? $args['site_id'] : 0);
        if ($siteId <= 0) { $out['code'] = 422; $out['reason'] = 'الموقعُ إلزامي'; return $out; }
        $site = null;
        try { $site = $gate->selectOne('sites', array('where' => array('id' => $siteId))); }
        catch (\Throwable $t) { $site = null; }
        if (!$site) { $out['code'] = 422; $out['reason'] = 'الموقعُ غيرُ موجودٍ في نطاقك'; return $out; }

        $name = trim((string) (isset($args['scope_name']) ? $args['scope_name'] : ''));
        if ($name === '') { $name = (string) $site['name']; }
        if ($name === '') { $out['code'] = 422; $out['reason'] = 'اسمُ النطاق إلزامي'; return $out; }

        $from = self::dateOrNull(isset($args['start_date']) ? $args['start_date'] : null);
        $to   = self::dateOrNull(isset($args['end_date']) ? $args['end_date'] : null);
        $span = self::assertWithinContract($c, $from, $to);
        if ($span !== null) { return array_merge($out, $span); }

        // ② الموقعُ مرةً واحدة
        $ex = null;
        try {
            $ex = $gate->selectOne('contract_operational_sites', array(
                'whereRaw' => 'contract_id = ? AND site_id = ?',
                'params' => array((int) $contractId, $siteId)));
        } catch (\Throwable $t) { $ex = null; }
        if ($ex) {
            $out['code'] = 409; $out['scope_id'] = (int) $ex['id'];
            $out['reason'] = 'للموقع نطاقٌ قائمٌ في هذا العقد #' . (int) $ex['id']
                           . ' — «الموقعُ مرةً واحدةً في العقد»';
            return $out;
        }

        $state = (string) (isset($args['state']) ? $args['state'] : 'active');
        if (!in_array($state, self::STATES, true)) { $state = 'active'; }
        if ($state === 'closed') {
            $out['code'] = 422; $out['reason'] = 'لا يُنشأ نطاقٌ مقفلًا — يُنشأ ثم يُقفل بسببه'; return $out;
        }
        $primary = !empty($args['is_primary']) ? 1 : 0;

        $sid = null;
        try {
            $gate->runInTransaction(function ($g) use (&$sid, $contractId, $siteId, $name, $from,
                                                       $to, $state, $primary, $args, $actor) {
                // ③ تعيينُ رئيسٍ ثانٍ **ينقل الرئاسة** — والنقلُ قبل الإدراج
                if ($primary === 1) { self::demoteCurrentPrimary($g, (int) $contractId); }
                $sid = (int) $g->insert('contract_operational_sites', array(
                    'contract_id' => (int) $contractId, 'site_id' => $siteId,
                    'scope_name' => mb_substr($name, 0, 190),
                    'start_date' => $from, 'end_date' => $to,
                    'state' => $state, 'is_primary' => $primary,
                    'note' => isset($args['note']) ? mb_substr((string) $args['note'], 0, 255) : null,
                    'created_by' => (int) $actor ?: null,
                ));
                if ($sid <= 0) { throw new \RuntimeException('تعذّر الإدراج'); }
            }, 'إضافة نطاق للعقد ' . $contractId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّرت الإضافة: ' . $t->getMessage(); return $out;
        }

        self::audit($conn, $companyId, $actor, 'add_scope', (int) $sid, array(),
            array('contract_id' => (int) $contractId, 'site_id' => $siteId, 'primary' => $primary));
        $out['ok'] = true; $out['code'] = 200; $out['scope_id'] = (int) $sid;
        return $out;
    }

    /** ③ نقلُ الرئاسة — **ذرّيًّا**: لا لحظةَ فيها رئيسان ولا لحظةَ بلا رئيس. */
    public static function setPrimary($conn, $gate, $companyId, $scopeId, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $s = self::scopeOf($gate, (int) $scopeId);
        if (!$s) { $out['code'] = 404; $out['reason' ] = 'النطاقُ غيرُ موجود'; return $out; }
        if ((int) $s['is_primary'] === 1) {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'رئيسٌ سلفًا'; return $out;
        }
        if ((string) $s['state'] === 'closed') {
            $out['code'] = 422; $out['reason'] = 'لا يُرأَّس نطاقٌ مقفل'; return $out;
        }
        try {
            $gate->runInTransaction(function ($g) use ($s, $scopeId) {
                self::demoteCurrentPrimary($g, (int) $s['contract_id']);
                $g->update('contract_operational_sites', array('is_primary' => 1),
                    array('id' => (int) $scopeId));
            }, 'نقل رئاسة النطاق ' . $scopeId);
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر النقل: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'set_primary', (int) $scopeId,
            array('is_primary' => 0), array('is_primary' => 1));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    /** ④ الإقفالُ بسببٍ مكتوب. */
    public static function close($conn, $gate, $companyId, $scopeId, $reason, $actor)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $s = self::scopeOf($gate, (int) $scopeId);
        if (!$s) { $out['code'] = 404; $out['reason'] = 'النطاقُ غيرُ موجود'; return $out; }
        if ((string) $s['state'] === 'closed') {
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'مقفلٌ سلفًا'; return $out;
        }
        $why = trim((string) $reason);
        if ($why === '') {
            $out['code'] = 422;
            $out['reason'] = '**سببُ الإقفال إلزامي** — ونطاقٌ يُقفل بلا سببٍ يترك عملًا لا يُفسَّر';
            return $out;
        }
        try {
            $gate->update('contract_operational_sites',
                array('state' => 'closed', 'close_reason' => mb_substr($why, 0, 255)),
                array('id' => (int) $scopeId));
        } catch (\Throwable $t) {
            $out['code'] = 422; $out['reason'] = 'تعذّر الإقفال: ' . $t->getMessage(); return $out;
        }
        self::audit($conn, $companyId, $actor, 'close_scope', (int) $scopeId,
            array('state' => (string) $s['state']), array('state' => 'closed', 'reason' => $why));
        $out['ok'] = true; $out['code'] = 200;
        return $out;
    }

    // ═════════════════════════════════════════════════════════════════════
    // قراءات
    // ═════════════════════════════════════════════════════════════════════

    public static function scopesOf($gate, $contractId)
    {
        try {
            return $gate->scopedQuery(
                array('scope' => array('o' => 'contract_operational_sites'),
                      'enrich' => array('s' => 'sites')),
                "SELECT o.*, s.name AS site_name, s.site_kind
                   FROM contract_operational_sites o
                   LEFT JOIN sites s ON s.id = o.site_id
                  WHERE {TENANT_SCOPE} AND o.contract_id = ? AND COALESCE(o.is_deleted,0)=0
                  ORDER BY o.is_primary DESC, o.id", array((int) $contractId));
        } catch (\Throwable $t) { error_log('P-01 scopesOf: ' . $t->getMessage()); return array(); }
    }

    /** العقودُ بلا نطاقٍ — «الفجوةُ تُرى». */
    public static function contractsWithoutScope($gate, $limit = 100)
    {
        try {
            $rows = $gate->scopedQuery(
                array('scope' => array('c' => 'contracts'),
                      'enrich' => array('o' => 'contract_operational_sites')),
                "SELECT c.id, c.project_id, c.contract_status, c.actual_start, c.actual_end,
                        COUNT(o.id) AS scope_count
                   FROM contracts c
                   LEFT JOIN contract_operational_sites o
                          ON o.contract_id = c.id AND COALESCE(o.is_deleted,0)=0
                  WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
                  GROUP BY c.id, c.project_id, c.contract_status, c.actual_start, c.actual_end
                  ORDER BY c.id DESC LIMIT " . max(1, (int) $limit));
            $out = array();
            foreach ($rows as $r) { if ((int) $r['scope_count'] === 0) { $out[] = $r; } }
            return $out;
        } catch (\Throwable $t) { error_log('P-01 withoutScope: ' . $t->getMessage()); return array(); }
    }

    public static function contracts($gate, $limit = 200)
    {
        try {
            return $gate->scopedQuery(array('scope' => array('c' => 'contracts')),
                "SELECT c.id, c.project_id, c.site_id, c.contract_status,
                        c.actual_start, c.actual_end, c.second_party
                   FROM contracts c WHERE {TENANT_SCOPE} AND COALESCE(c.is_deleted,0)=0
                  ORDER BY c.id DESC LIMIT " . max(1, (int) $limit));
        } catch (\Throwable $t) { return array(); }
    }

    public static function sites($gate, $projectId = 0)
    {
        try {
            $w = (int) $projectId > 0 ? ' AND s.project_id = ?' : '';
            $p = (int) $projectId > 0 ? array((int) $projectId) : array();
            return $gate->scopedQuery(array('scope' => array('s' => 'sites')),
                "SELECT s.id, s.name, s.site_kind, s.project_id FROM sites s
                  WHERE {TENANT_SCOPE} AND COALESCE(s.is_deleted,0)=0" . $w
                . " ORDER BY s.name", $p);
        } catch (\Throwable $t) { return array(); }
    }

    public static function scopeOf($gate, $id)
    {
        try { return $gate->selectOne('contract_operational_sites', array('where' => array('id' => (int) $id))); }
        catch (\Throwable $t) { return null; }
    }

    // ═════════════════════════════════════════════════════════════════════

    /** ① النطاقُ داخل مدة عقده — «نطاقٌ خارج عقده لا معنى له». */
    private static function assertWithinContract(array $c, $from, $to)
    {
        $cs = ($c['actual_start'] !== null && (string) $c['actual_start'] !== '0000-00-00')
              ? substr((string) $c['actual_start'], 0, 10) : null;
        $ce = ($c['actual_end'] !== null && (string) $c['actual_end'] !== '0000-00-00')
              ? substr((string) $c['actual_end'], 0, 10) : null;
        if ($from !== null && $to !== null && $to < $from) {
            return array('code' => 422, 'reason' => 'نهايةُ النطاق قبل بدايته');
        }
        if ($cs !== null && $from !== null && $from < $cs) {
            return array('code' => 422,
                'reason' => 'بدايةُ النطاق ' . $from . ' **قبل بداية العقد** ' . $cs
                          . ' — «نطاقٌ خارج عقده لا معنى له»');
        }
        if ($ce !== null && $to !== null && $to > $ce) {
            return array('code' => 422,
                'reason' => 'نهايةُ النطاق ' . $to . ' **بعد نهاية العقد** ' . $ce);
        }
        return null;
    }

    private static function demoteCurrentPrimary($g, $contractId)
    {
        $cur = null;
        try {
            $cur = $g->selectOne('contract_operational_sites', array(
                'whereRaw' => 'contract_id = ? AND is_primary = 1', 'params' => array((int) $contractId)));
        } catch (\Throwable $t) { $cur = null; }
        if ($cur) {
            $g->update('contract_operational_sites', array('is_primary' => 0),
                array('id' => (int) $cur['id']));
        }
    }

    private static function contractOf($gate, $contractId)
    {
        try { return $gate->selectOne('contracts', array('where' => array('id' => (int) $contractId))); }
        catch (\Throwable $t) { return null; }
    }

    private static function dateOrNull($v)
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;
    }

    private static function audit($conn, $companyId, $actor, $action, $rowId, $before, $after)
    {
        require_once dirname(__DIR__, 3) . '/includes/audit_trail.php';
        ems_audit_change($conn, 'contracts', 'contract_operational_sites', $action, (int) $rowId,
            $before, $after, array('company_id' => (int) $companyId, 'user_id' => (int) $actor));
    }
}
