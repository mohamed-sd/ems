<?php
/**
 * PermissionTemplateService — القوالب وإصداراتها (SEC-01 §4⑥ · §12 خدمة ⑨ · SEC-23)
 * ───────────────────────────────────────────────────────────────────────────
 * «تعديل قالب نافذ يُنشئ إصدارًا جديدًا بسريان مستقبلي ولا يُعدَّل بأثر رجعي» ·
 * «يُعرض أثر التغيير قبل النشر: كم مستخدمًا يتأثر وأي صلاحية تُضاف أو تُسحب» ·
 * «ولا نشر بلا اختبار في وضع اختبار الصلاحيات» · تعديل إصدار نافذ → 423.
 */

namespace App\Services\Security;

class PermissionTemplateService
{
    /** إنشاء إصدار مسودة جديد لقالب. */
    public static function createVersion(\mysqli $conn, $tplKind, $keyCode, array $permissions, $changeReason = '', $effectiveFrom = null)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '', 'ver_id' => 0);
        $stmt = $conn->prepare('SELECT tpl_id FROM permission_templates WHERE tpl_kind = ? AND key_code = ? LIMIT 1');
        $stmt->bind_param('ss', $tplKind, $keyCode);
        $stmt->execute();
        $tpl = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$tpl) { $out['code'] = 404; $out['reason'] = 'قالب غير معرف — يضاف صفا'; return $out; }
        $tid = intval($tpl['tpl_id']);
        $v = intval($conn->query("SELECT COALESCE(MAX(version),0)+1 v FROM permission_template_versions WHERE tpl_id={$tid}")->fetch_assoc()['v']);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "INSERT INTO permission_template_versions (tpl_id, version, state, change_reason, effective_from)
                 VALUES (?, ?, 'draft', ?, ?)");
            $stmt->bind_param('iiss', $tid, $v, $changeReason, $effectiveFrom);
            $stmt->execute();
            $verId = intval($conn->insert_id);
            $stmt->close();
            foreach ($permissions as $p) {
                $stmt = $conn->prepare(
                    'INSERT INTO template_permissions (template_version_id, dimension, permission_code, scope_rule, amount_cap, currency, effect)
                     VALUES (?, ?, ?, ?, ?, ?, ?)');
                $dim = isset($p['dimension']) ? $p['dimension'] : 'action';
                $sr = isset($p['scope_rule']) ? $p['scope_rule'] : null;
                $cap = isset($p['amount_cap']) ? floatval($p['amount_cap']) : null;
                $cur = isset($p['currency']) ? $p['currency'] : null;
                $ef = isset($p['effect']) ? $p['effect'] : 'grant';
                $stmt->bind_param('isssdss', $verId, $dim, $p['permission_code'], $sr, $cap, $cur, $ef);
                $stmt->execute();
                $stmt->close();
            }
            $conn->commit();
            $out['ok'] = true; $out['code'] = 201; $out['ver_id'] = $verId;
            $out['reason'] = 'إصدار مسودة v' . $v;
            return $out;
        } catch (\Throwable $e) {
            $conn->rollback();
            $out['code'] = 500; $out['reason'] = $e->getMessage();
            return $out;
        }
    }

    /** تعديل محتوى إصدار — النافذ لا يُعدَّل بأثر رجعي → 423. */
    public static function amendVersion(\mysqli $conn, $verId, array $permissions)
    {
        $verId = intval($verId);
        $v = $conn->query("SELECT state FROM permission_template_versions WHERE ver_id = {$verId}")->fetch_assoc();
        if (!$v) { return array('ok' => false, 'code' => 404, 'reason' => 'إصدار غير موجود'); }
        if ($v['state'] === 'published' || $v['state'] === 'superseded') {
            return array('ok' => false, 'code' => 423,
                'reason' => 'لا يعدل إصدار نافذ بأثر رجعي (423) — أنشئ إصدارا جديدا بسريان مستقبلي');
        }
        $conn->query("DELETE FROM template_permissions WHERE template_version_id = {$verId}");
        foreach ($permissions as $p) {
            $stmt = $conn->prepare(
                'INSERT INTO template_permissions (template_version_id, dimension, permission_code, scope_rule, amount_cap, currency, effect)
                 VALUES (?, ?, ?, ?, ?, ?, ?)');
            $dim = isset($p['dimension']) ? $p['dimension'] : 'action';
            $sr = isset($p['scope_rule']) ? $p['scope_rule'] : null;
            $cap = isset($p['amount_cap']) ? floatval($p['amount_cap']) : null;
            $cur = isset($p['currency']) ? $p['currency'] : null;
            $ef = isset($p['effect']) ? $p['effect'] : 'grant';
            $stmt->bind_param('isssdss', $verId, $dim, $p['permission_code'], $sr, $cap, $cur, $ef);
            $stmt->execute();
            $stmt->close();
        }
        return array('ok' => true, 'code' => 200, 'reason' => 'عدلت المسودة');
    }

    /**
     * أثر التغيير قبل النشر: كم مستخدمًا يتأثر وأي صلاحية تُضاف أو تُسحب.
     */
    public static function impactPreview(\mysqli $conn, $verId)
    {
        $verId = intval($verId);
        $ver = $conn->query(
            "SELECT v.*, t.tpl_kind, t.key_code FROM permission_template_versions v
              JOIN permission_templates t ON t.tpl_id = v.tpl_id WHERE v.ver_id = {$verId}")->fetch_assoc();
        if (!$ver) { return array('ok' => false, 'reason' => 'إصدار غير موجود'); }

        // من يتأثر: أصحاب المراكز النشطة الحاملة لمفتاح القالب
        $col = array('relation' => 'relation_code', 'family' => 'family_code',
            'level' => 'level_code', 'title' => 'title_code');
        $affected = 0;
        if (isset($col[$ver['tpl_kind']])) {
            $c = $col[$ver['tpl_kind']];
            $r = $conn->query("SELECT COUNT(DISTINCT person_id) c FROM person_positions
                                WHERE {$c} = '" . $conn->real_escape_string($ver['key_code']) . "' AND state = 'active'");
            $affected = $r ? intval($r->fetch_assoc()['c']) : 0;
        }

        // المضاف والمسحوب مقابل الإصدار المنشور الحالي
        $current = array();
        $r = $conn->query(
            "SELECT tp.permission_code, tp.effect FROM permission_template_versions v
              JOIN template_permissions tp ON tp.template_version_id = v.ver_id
             WHERE v.tpl_id = " . intval($ver['tpl_id']) . " AND v.state = 'published'");
        while ($r && ($x = $r->fetch_assoc())) { $current[$x['permission_code'] . '|' . $x['effect']] = true; }
        $proposed = array();
        $r = $conn->query("SELECT permission_code, effect FROM template_permissions WHERE template_version_id = {$verId}");
        while ($r && ($x = $r->fetch_assoc())) { $proposed[$x['permission_code'] . '|' . $x['effect']] = true; }
        $added = array_values(array_diff(array_keys($proposed), array_keys($current)));
        $removed = array_values(array_diff(array_keys($current), array_keys($proposed)));

        $impact = array('affected_users' => $affected, 'added' => $added, 'removed' => $removed);
        $conn->query("UPDATE permission_template_versions SET impact_preview_json = '"
            . $conn->real_escape_string(json_encode($impact, JSON_UNESCAPED_UNICODE)) . "' WHERE ver_id = {$verId}");
        return array('ok' => true, 'impact' => $impact);
    }

    /** وسم الإصدار مختبَرًا — لا يتم إلا ووضع اختبار الصلاحيات مفعَّل. */
    public static function markTested(\mysqli $conn, $verId, $testRef = '')
    {
        $verId = intval($verId);
        $fm = $conn->query("SELECT enabled FROM founding_mode WHERE mode = 'permission_test' AND enabled = 1")->fetch_assoc();
        if (!$fm) {
            return array('ok' => false, 'code' => 409,
                'reason' => 'لا وسم «مختبر» ووضع اختبار الصلاحيات مطفأ — الاختبار بحسابات ممثلة شرط (§4⑥)');
        }
        $conn->query("UPDATE permission_template_versions SET state = 'tested',
                      approval_ref = CONCAT(COALESCE(approval_ref,''), ' tested:', '" . $conn->real_escape_string($testRef) . "')
                      WHERE ver_id = {$verId} AND state = 'draft'");
        return array('ok' => $conn->affected_rows > 0, 'code' => 200, 'reason' => 'وسم مختبرا');
    }

    /**
     * النشر: يتطلب مختبَرًا + أثرًا محسوبًا — ويجعل السابق superseded.
     */
    public static function publish(\mysqli $conn, $verId, $approvalRef)
    {
        $out = array('ok' => false, 'code' => 0, 'reason' => '');
        $verId = intval($verId);
        $v = $conn->query("SELECT * FROM permission_template_versions WHERE ver_id = {$verId}")->fetch_assoc();
        if (!$v) { $out['code'] = 404; $out['reason'] = 'إصدار غير موجود'; return $out; }
        if ($v['state'] !== 'tested') {
            $out['code'] = 409;
            $out['reason'] = 'لا نشر بلا اختبار في وضع اختبار الصلاحيات — الحالة: ' . $v['state'];
            return $out;
        }
        if ($v['impact_preview_json'] === null) {
            $out['code'] = 409; $out['reason'] = 'يعرض أثر التغيير قبل النشر — احسب impactPreview أولا';
            return $out;
        }
        $conn->begin_transaction();
        try {
            $tid = intval($v['tpl_id']);
            $conn->query("UPDATE permission_template_versions SET state = 'superseded', superseded_by = {$verId}
                          WHERE tpl_id = {$tid} AND state = 'published'");
            $stmt = $conn->prepare(
                "UPDATE permission_template_versions SET state = 'published', approval_ref = ?,
                        effective_from = COALESCE(effective_from, CURDATE()) WHERE ver_id = ?");
            $stmt->bind_param('si', $approvalRef, $verId);
            $stmt->execute();
            $stmt->close();
            $conn->commit();
            $out['ok'] = true; $out['code'] = 200; $out['reason'] = 'نشر — والسابق superseded بلا أثر رجعي';
            return $out;
        } catch (\Throwable $e) {
            $conn->rollback();
            $out['code'] = 500; $out['reason'] = $e->getMessage();
            return $out;
        }
    }
}
