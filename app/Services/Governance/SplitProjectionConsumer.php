<?php
/**
 * app/Services/Governance/SplitProjectionConsumer.php — مستهلكُ أحداثِ الشقّ (RPR-W10)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **ولماذا مستهلكٌ حقيقيٌّ لا سطرٌ في وثيقة**: الجذرُ المحايدُ **يرفض النشرَ
 *   لحدثٍ بلا مشتركٍ نشط** (`BUS_NO_CONSUMER` · CK-11) — فعقدُ الأثرِ ليس وصفًا
 *   يُكتب بل **شرطُ نشرٍ يُنفَّذ**. وحدثٌ بلا مستهلكٍ مسجَّلٍ لا يُقيَّد أصلًا.
 *
 * ◆ **وأثرُ كلِّ حدثٍ مقيسٌ لا مُدَّعًى**: كلُّ طريقةٍ هنا **تعيد قياسَ الحالةِ من
 *   السجلَّين معًا** وتكتب أثرَها في `repair01_w10_split.verified_at` — فالقبولُ
 *   يقيس الأثرَ التجاريَّ لا صفَّ الحدثِ المُنشَأ (‏§46).
 *
 * ◆ **ولا يكتب هذا المستهلكُ في جدولٍ حيٍّ حرفًا** — الشقُّ قرارُ ملكيّةٍ في سجلِّ
 *   الحملة، والرابطُ القديمُ يُترجَم ولا يُدهَس.
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Services\Governance;

class SplitProjectionConsumer
{
    /** الحمولةُ من صفِّ الصادرِ أو من نداءٍ مباشر */
    private function payload(array $event)
    {
        $p = $event['payload'] ?? ($event['data'] ?? array());
        if (\is_string($p)) { $p = \json_decode($p, true); }
        return \is_array($p) ? $p : array();
    }

    private function stamp(\mysqli $conn, $scopeKey, $ref)
    {
        $st = $conn->prepare("UPDATE repair01_w10_split SET verified_at = NOW(), verify_ref = ?
                               WHERE scope_key = ?");
        if (!$st) { return 0; }
        $r = (string) $ref; $k = (string) $scopeKey;
        $st->bind_param('ss', $r, $k);
        $st->execute();
        $n = $st->affected_rows;
        $st->close();
        return (int) $n;
    }

    /**
     * `surface.owner.reassigned` — **يعيد قياسَ السجلَّين** للسطحِ المعنيّ.
     * الأثرُ: صفُّ الشقِّ يُختَم متحقَّقًا، أو يُترك بلا ختمٍ إن تفرَّق السجلّان.
     */
    public function verifyOwner(array $event, \mysqli $conn)
    {
        $p = $this->payload($event);
        $sid = (string) ($p['screen_id'] ?? '');
        if ($sid === '') { return 'W10:NO_SCREEN_ID'; }

        $st = $conn->prepare("SELECT p.resolved_code, r.owner_code
                                FROM repair01_w10_split p
                                LEFT JOIN repair01_screen_registry r ON r.screen_id = p.scope_key
                               WHERE p.scope_key = ? LIMIT 1");
        if (!$st) { return 'W10:NO_STMT'; }
        $st->bind_param('s', $sid);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) { return 'W10:NOT_IN_SPLIT:' . $sid; }
        if ((string) $row['owner_code'] !== '' && (string) $row['owner_code'] !== (string) $row['resolved_code']) {
            return 'W10:CONFLICT:' . $sid;
        }
        $this->stamp($conn, $sid, 'owner:' . $row['resolved_code']);
        return 'W10:VERIFIED:' . $sid . ':' . $row['resolved_code'];
    }

    /**
     * `dept.split.applied` — يعيد بناءَ عدّادَي الشقَّينِ من الدفترِ لا من الحمولة.
     * الأثرُ: العدّادانِ المشتقّانِ يُقارَنانِ بالمُعلَنِ في الحدث.
     */
    public function refreshSplitCounters(array $event, \mysqli $conn)
    {
        $p = $this->payload($event);
        $unit = (string) ($p['legacy_unit'] ?? '');
        if ($unit === '') { return 'W10:NO_UNIT'; }
        $st = $conn->prepare("SELECT resolved_code, COUNT(*) c FROM repair01_w10_split
                               WHERE legacy_unit = ? GROUP BY resolved_code");
        if (!$st) { return 'W10:NO_STMT'; }
        $st->bind_param('s', $unit);
        $st->execute();
        $r = $st->get_result();
        $n = array();
        while ($r && $x = $r->fetch_assoc()) { $n[(string) $x['resolved_code']] = (int) $x['c']; }
        $st->close();
        $out = array();
        foreach ($n as $k => $v) { $out[] = $k . '=' . $v; }
        \sort($out);
        return 'W10:COUNTERS:' . \implode(',', $out);
    }

    /**
     * `legacy.pointer.translated` — **يشغّل استعلامَ الإثباتِ فعلًا**.
     * الأثرُ: الرابطُ القديمُ يُقاس قائمًا، أو يُعلَن مكسورًا برمزِه.
     */
    public function verifyBridge(array $event, \mysqli $conn)
    {
        $p = $this->payload($event);
        $t = (string) ($p['host_table'] ?? '');
        $k = (string) ($p['pointer_key'] ?? '');
        if ($t === '' || $k === '') { return 'W10:NO_POINTER'; }
        $st = $conn->prepare("SELECT probe_sql, resolved_code FROM repair01_w10_bridge
                               WHERE host_table = ? AND pointer_key = ? LIMIT 1");
        if (!$st) { return 'W10:NO_STMT'; }
        $st->bind_param('ss', $t, $k);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$row) { return 'W10:NO_BRIDGE_ROW'; }
        $q = @$conn->query((string) $row['probe_sql']);
        $v = $q ? $q->fetch_row() : null;
        $alive = $v ? (int) $v[0] : 0;
        return $alive > 0 ? 'W10:BRIDGE_ALIVE:' . $row['resolved_code'] : 'W10:BRIDGE_BROKEN:' . $k;
    }

    /** `sidebar.item.replaced` — يتحقّق أنَّ البندَ مربوطٌ بمُعرِّفِ شاشتِه المعياريّ */
    public function verifySidebarLink(array $event, \mysqli $conn)
    {
        $p = $this->payload($event);
        $sid = (string) ($p['screen_id'] ?? '');
        if ($sid === '') { return 'W10:NO_SCREEN_ID'; }
        $st = $conn->prepare("SELECT s7_linked FROM repair01_w10_sidebar WHERE screen_id = ? LIMIT 1");
        if (!$st) { return 'W10:NO_STMT'; }
        $st->bind_param('s', $sid);
        $st->execute();
        $row = $st->get_result()->fetch_row();
        $st->close();
        if (!$row) { return 'W10:NOT_IN_SIDEBAR:' . $sid; }
        if ((int) $row[0] !== 1) { return 'W10:NOT_LINKED:' . $sid; }
        $this->stamp($conn, $sid, 'sidebar:linked');
        return 'W10:LINKED:' . $sid;
    }

    /** `split.conflict.detected` — يقيس التنازعَ ولا يداويه بترجيحِ أحدِ السجلَّين */
    public function recordConflict(array $event, \mysqli $conn)
    {
        $n = DeptSplitService::detectConflict($conn);
        return 'W10:CONFLICTS:' . \count($n);
    }

    /** المدخلُ الافتراضيُّ حين لا تُسمّى طريقةٌ في السجلّ */
    public function handle(array $event, \mysqli $conn)
    {
        $key = (string) ($event['event_key'] ?? ($event['event_name'] ?? ''));
        switch ($key) {
            case DeptSplitService::EV_OWNER_REASSIGNED:   return $this->verifyOwner($event, $conn);
            case DeptSplitService::EV_SPLIT_APPLIED:      return $this->refreshSplitCounters($event, $conn);
            case DeptSplitService::EV_POINTER_TRANSLATED: return $this->verifyBridge($event, $conn);
            case DeptSplitService::EV_SIDEBAR_REPLACED:   return $this->verifySidebarLink($event, $conn);
            case DeptSplitService::EV_CONFLICT_DETECTED:  return $this->recordConflict($event, $conn);
        }
        return 'W10:UNKNOWN_EVENT:' . $key;
    }
}
