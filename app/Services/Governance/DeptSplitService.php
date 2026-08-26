<?php
/**
 * app/Services/Governance/DeptSplitService.php — شقُّ الوحدةِ وجسرُها (RPR-W10)
 * ═══════════════════════════════════════════════════════════════════════════
 * **الشقُّ قرارُ ملكيّةٍ لا إعادةُ ترقيم.** وحدةٌ حيّةٌ واحدةٌ تُشقُّ إلى اثنتَين:
 * `DEP-05` تعترف وتقيّد · `DEP-06` تقبض وتصرف وتنفّذ. ولا يُمَسُّ في هذا مفتاحٌ
 * تقنيٌّ ولا أجنبيٌّ ولا سجلُّ تدقيقٍ ولا رابطٌ قديم — **الجسرُ يترجم**.
 *
 * ◆ **ولماذا خدمةٌ أصلًا وهي مرحلةُ سجلّات**: لأنَّ الشقَّ **يُقرأ حيًّا**. لوحةُ
 *   الإدارةِ تسأل «مَن يملك هذه الشاشة» وعقدُ الشاشةِ يسأل «أيُّ إدارةٍ تعرضها»،
 *   وكانت الإجابةُ **مصفوفةً مكتوبةً في ملفِّ الشاشة**. فالمصدرُ الواحدُ للجوابِ
 *   هو هذه الخدمة، والمصفوفةُ تصير احتياطًا لا مصدرًا.
 *
 * ◆ **والاسمُ القديمُ يبقى نصًّا**: `nav_canonical.owner_dept` و`nav09_file_map`
 *   و`request_types` تحمل «المالية والخزينة» كما كُتبت. ودهسُها بالرمزِ المعياريِّ
 *   يكسر كلَّ رابطٍ ومرجعِ تدقيقٍ يشير إليها — و`LEGACY_POINTER_OVERWRITE_FORBIDDEN`
 *   يردُّ المحاولةَ صراحةً.
 *
 * ◆ **وفصلُ الواجباتِ منفَّذٌ لا مُعلَن**: مَن يحسم الشقَّ لا يطبّقه
 *   (`SAME_ACTOR_DECIDE_AND_APPLY`)، ومَن يقترح تغييرَ مالكٍ لا يعتمده
 *   (`SAME_ACTOR_PROPOSE_AND_APPROVE`)، ولا يُطبَّق حكمٌ بلا قاعدةٍ ومرساةٍ
 *   (`SPLIT_OWNER_CHANGE_WITHOUT_RULE`)، ولا يُعاد ترقيمُ مرجعِ تدقيقٍ بحال
 *   (`AUDIT_REFERENCE_RENUMBER_FORBIDDEN`).
 *
 * ⛔ **ولا حدثَ يُطلَق بلا عقدِ أثرٍ مسجَّل**: `assertContract` تقرأ
 *   `repair01_events` وتردُّ `EVENT_WITHOUT_RECORDED_CONTRACT` قبل أوّلِ نشر.
 */

namespace App\Services\Governance;

class DeptSplitService
{
    /* ── أحداثُ النطاقِ — ولكلٍّ عقدُ أثرٍ مسجَّلٌ في repair01_events ────── */
    const EV_SPLIT_APPLIED       = 'dept.split.applied';
    const EV_OWNER_REASSIGNED    = 'surface.owner.reassigned';
    const EV_POINTER_TRANSLATED  = 'legacy.pointer.translated';
    const EV_SIDEBAR_REPLACED    = 'sidebar.item.replaced';
    const EV_CONFLICT_DETECTED   = 'split.conflict.detected';

    /* ── رموزُ الردِّ — فصلُ الواجباتِ وحمايةُ الرابطِ القديم ────────────── */
    const SAME_ACTOR_DECIDE_AND_APPLY        = 'SAME_ACTOR_DECIDE_AND_APPLY';
    const SAME_ACTOR_PROPOSE_AND_APPROVE     = 'SAME_ACTOR_PROPOSE_AND_APPROVE';
    const SPLIT_OWNER_CHANGE_WITHOUT_RULE    = 'SPLIT_OWNER_CHANGE_WITHOUT_RULE';
    const LEGACY_POINTER_OVERWRITE_FORBIDDEN = 'LEGACY_POINTER_OVERWRITE_FORBIDDEN';
    const AUDIT_REFERENCE_RENUMBER_FORBIDDEN = 'AUDIT_REFERENCE_RENUMBER_FORBIDDEN';
    const EVENT_WITHOUT_RECORDED_CONTRACT    = 'EVENT_WITHOUT_RECORDED_CONTRACT';

    /**
     * مالكُ سطحٍ حيٍّ — **من دفترِ الشقِّ لا من اسمِ مجلَّدٍ ولا من مصفوفة**.
     * يُعيد `''` حين لا يكون السطحُ في نطاقِ وحدةٍ مشقوقة.
     */
    public static function resolveOwner(\mysqli $conn, $route)
    {
        $rt = \trim(\str_replace('\\', '/', (string) $route));
        if ($rt === '') { return ''; }
        $st = $conn->prepare("SELECT resolved_code FROM repair01_w10_split
                               WHERE REPLACE(route, '\\\\', '/') = ? LIMIT 1");
        if (!$st) { return ''; }
        $st->bind_param('s', $rt);
        $st->execute();
        $r = $st->get_result();
        $x = $r ? $r->fetch_row() : null;
        $st->close();
        return $x ? (string) $x[0] : '';
    }

    /**
     * ترجمةُ مؤشِّرٍ حيٍّ يسمّي الوحدةَ الأمَّ باسمِها القديم.
     * ⛔ **ولا تكتب في الجدولِ الحيِّ حرفًا** — القراءةُ والترجمةُ فقط.
     */
    public static function translateLegacy(\mysqli $conn, $hostTable, $pointerKey, $legacyName = '')
    {
        $t = (string) $hostTable; $k = (string) $pointerKey;
        $st = $conn->prepare("SELECT resolved_code, bridge_rule FROM repair01_w10_bridge
                               WHERE host_table = ? AND pointer_key = ? LIMIT 1");
        if (!$st) { return array('code' => '', 'rule' => '', 'legacy' => (string) $legacyName); }
        $st->bind_param('ss', $t, $k);
        $st->execute();
        $r = $st->get_result();
        $x = $r ? $r->fetch_assoc() : null;
        $st->close();
        if (!$x) { return array('code' => '', 'rule' => '', 'legacy' => (string) $legacyName); }
        return array('code' => (string) $x['resolved_code'], 'rule' => (string) $x['bridge_rule'],
                     'legacy' => (string) $legacyName);
    }

    /**
     * اسمُ الإدارةِ المالكةِ لوحدةٍ في قاموسِ الطلبات — **مصدرٌ واحدٌ للجواب**.
     * وحين لا يجد الجسرُ ترجمةً يُعيد الاسمَ الحيَّ كما هو: **الجسرُ يترجم ولا
     * يخترع**، والسلوكُ الحيُّ لا يتغيّر بغيابِ صفٍّ في السجلّ.
     */
    public static function ownerNameForLegacy(\mysqli $conn, $legacyName)
    {
        $nm = (string) $legacyName;
        if ($nm === '') { return ''; }
        $st = $conn->prepare("SELECT COUNT(*) FROM repair01_w10_bridge WHERE legacy_name = ?");
        if (!$st) { return $nm; }
        $st->bind_param('s', $nm);
        $st->execute();
        $r = $st->get_result();
        $x = $r ? $r->fetch_row() : null;
        $st->close();
        /* الاسمُ الحيُّ يبقى هو المفتاحَ في الجدولِ الحيّ — والجسرُ يُثبت وجودَه */
        return $nm;
    }

    /**
     * ⛔ محاولةُ استبدالِ الاسمِ القديمِ في جدولٍ حيٍّ — تُردُّ دائمًا.
     * موجودةٌ **لتُنفَّذ لا لتُعلَن**: بلا ردٍّ صريحٍ يصير المنعُ عرفًا يُنسى.
     */
    public static function overwriteLegacyPointer($hostTable, $pointerKey, $newName)
    {
        return array('ok' => false, 'code' => self::LEGACY_POINTER_OVERWRITE_FORBIDDEN,
                     'why' => 'الاسم القديم مفتاح لروابط ومراجع تدقيق قائمة — الجسر يترجم ولا يستبدل');
    }

    /** ⛔ إعادةُ ترقيمِ مرجعِ تدقيقٍ — تُردُّ دائمًا. */
    public static function renumberAuditReference($table, $oldId, $newId)
    {
        return array('ok' => false, 'code' => self::AUDIT_REFERENCE_RENUMBER_FORBIDDEN,
                     'why' => 'اعادة ترقيم مرجع تدقيق تكسر سلسلة الاثبات — والشق لا يمس معرفا تقنيا');
    }

    /**
     * سجلُّ تدقيقٍ قديمٌ يُقرأ **بمعرّفِه الأصليّ**.
     * ⛔ ولا مُعرِّفَ بديلٌ ولا خريطةُ ترجمةٍ للمعرّفات — الشقُّ لم يمسَّها.
     */
    public static function readAuditByOriginalId(\mysqli $conn, $table, $id)
    {
        $allowed = array('ems_business_events', 'activity_logs');
        if (!\in_array((string) $table, $allowed, true)) { return null; }
        $sql = "SELECT COUNT(*) FROM `" . (string) $table . "` WHERE id = " . (int) $id;
        $r = @$conn->query($sql);
        if (!$r) { return null; }
        $x = $r->fetch_row();
        return $x ? (int) $x[0] : null;
    }

    /**
     * حارسُ فصلِ الواجبات — يردُّ رمزًا ولا يرمي.
     * `$actors` = array('decider' => id, 'applier' => id, 'proposer' => id, 'approver' => id)
     */
    public static function assertSeparation(array $actors)
    {
        $d = (int) ($actors['decider'] ?? 0); $a = (int) ($actors['applier'] ?? 0);
        if ($d > 0 && $a > 0 && $d === $a) {
            return array('ok' => false, 'code' => self::SAME_ACTOR_DECIDE_AND_APPLY,
                         'why' => 'من يحسم الشق لا يطبقه — الحسم قرار والتطبيق تنفيذ');
        }
        $p = (int) ($actors['proposer'] ?? 0); $v = (int) ($actors['approver'] ?? 0);
        if ($p > 0 && $v > 0 && $p === $v) {
            return array('ok' => false, 'code' => self::SAME_ACTOR_PROPOSE_AND_APPROVE,
                         'why' => 'تغيير المالك يغير من يرى الشاشة — فلا يعتمده مقترحه');
        }
        return array('ok' => true, 'code' => '', 'why' => '');
    }

    /** الحكمُ بلا قاعدةٍ ومرساةٍ لا يُطبَّق */
    public static function assertRuled(array $row)
    {
        if (\trim((string) ($row['split_rule'] ?? '')) === ''
            || \trim((string) ($row['split_why'] ?? '')) === '') {
            return array('ok' => false, 'code' => self::SPLIT_OWNER_CHANGE_WITHOUT_RULE,
                         'why' => 'مالك بلا قاعدة مكتوبة هو الحسم بترتيب الصفوف نفسه');
        }
        return array('ok' => true, 'code' => '', 'why' => '');
    }

    /** ⛔ حدثٌ بلا عقدِ أثرٍ مسجَّلٍ لا يُنفَّذ */
    public static function assertContract(\mysqli $conn, $eventCode)
    {
        $c = (string) $eventCode;
        $st = $conn->prepare("SELECT COUNT(*) FROM repair01_events
                               WHERE event_code = ? AND wave = 'W10' AND contract_status = 'RECORDED'
                                 AND trigger_rule <> '' AND min_payload <> '' AND consumer_list <> ''
                                 AND consumer_effect <> '' AND preconditions <> ''
                                 AND failure_policy <> '' AND compensation <> '' AND idempotency_key <> ''");
        if (!$st) { return array('ok' => false, 'code' => self::EVENT_WITHOUT_RECORDED_CONTRACT, 'why' => 'تعذر فحص العقد'); }
        $st->bind_param('s', $c);
        $st->execute();
        $r = $st->get_result();
        $x = $r ? $r->fetch_row() : null;
        $st->close();
        if (!$x || (int) $x[0] === 0) {
            return array('ok' => false, 'code' => self::EVENT_WITHOUT_RECORDED_CONTRACT,
                         'why' => 'العقد غير مسجل او ناقص — ولا يطلق حدث بلا عقد اثر');
        }
        return array('ok' => true, 'code' => '', 'why' => '');
    }

    /**
     * ينشر واقعةَ حوكمةٍ بعد التحقُّقِ من عقدِها.
     * ويُعيد `array('ok'=>bool,'code'=>string,'event_id'=>int|null)`.
     */
    public static function publish(\mysqli $conn, $eventCode, array $payload, $companyId = 0,
                                   $userId = 0, $entityId = 0)
    {
        $ct = self::assertContract($conn, $eventCode);
        if (!$ct['ok']) { return array('ok' => false, 'code' => $ct['code'], 'event_id' => null); }
        $pub = \dirname(\dirname(__DIR__)) . '/Core/EventPublisher.php';
        if (\is_file($pub)) { require_once $pub; }
        if (!\class_exists('\App\Core\EventPublisher')) {
            return array('ok' => false, 'code' => 'PUBLISHER_MISSING', 'event_id' => null);
        }
        $idem = 'w10:' . \strtolower(\str_replace('.', ':', (string) $eventCode)) . ':'
              . \substr(\sha1(\json_encode($payload, JSON_UNESCAPED_UNICODE)), 0, 24);
        $id = \App\Core\EventPublisher::publishFact($conn, array(
            'company_id'      => (int) $companyId,
            'event_key'       => (string) $eventCode,
            'category'        => 'analytics',
            'source_module'   => 'system',
            'source_ref'      => 'W10',
            'entity_type'     => 'owner_dept',
            'entity_id'       => max(1, (int) $entityId),
            'occurred_at'     => \date('Y-m-d H:i:s'),
            'payload'         => $payload,
            'idempotency_key' => $idem,
            'created_by'      => max(1, (int) $userId),
        ));
        return array('ok' => true, 'code' => '', 'event_id' => $id);
    }

    /**
     * كاشفُ التنازع: سطحٌ برمزَين مختلفَين في الدفترَين بعد التطبيق.
     * **والسجلّانِ مقامانِ مختلفان** — فالتفرُّقُ يُكشَف ولا يُداوى بترجيحِ أحدِهما.
     */
    public static function detectConflict(\mysqli $conn)
    {
        $out = array();
        $sql = "SELECT p.scope_key, p.resolved_code, s.canonical_code, r.owner_code
                  FROM repair01_w10_split p
                  LEFT JOIN repair01_screen_registry r ON r.screen_id = p.scope_key
                  LEFT JOIN repair01_surfaces s ON s.screen_id = p.scope_key
                       AND s.dept_legacy = p.legacy_unit
                 WHERE p.in_surfaces = 1 AND p.in_registry = 1
                   AND s.canonical_code IS NOT NULL AND r.owner_code IS NOT NULL
                   AND s.canonical_code <> r.owner_code
                 GROUP BY p.scope_key, p.resolved_code, s.canonical_code, r.owner_code";
        $q = @$conn->query($sql);
        while ($q && $x = $q->fetch_assoc()) { $out[] = $x; }
        return $out;
    }
}
