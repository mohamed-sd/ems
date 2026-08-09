<?php
/**
 * مستهلكُ التوجيهِ المالي — RoutingConsumer (FIN-OBL-01 OBL-0002 · OBL-0003)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ الحكمُ الذي يُنفذه:
 *   OBL-0002 «التوجيهُ يقع **بحدثٍ منشورٍ لا بنداءٍ مباشرٍ** — والإدارةُ المصدرُ
 *   تنشر والماليةُ تستهلك.»
 *
 *   ولهذا **لا يُستدعى محرّكُ التوجيهِ من شاشةِ إدارةٍ ولا من خدمتِها**. تنشر
 *   الإدارةُ حدثَها في الناقلِ (`EventPublisher`)، ويقرأه هذا المستهلكُ بمؤشِّرٍ
 *   (`EventDispatcher`) فيوجّهه إلى محاسبِ تخصصِه. فلو نادت الإدارةُ الماليةَ
 *   مباشرةً لصار العزلُ دعوى.
 *
 *   OBL-0003 «الطلبُ الموجَّهُ يظهر في مهامِّ محاسبِ التخصصِ فورًا بمهلة» —
 *   والمهمةُ يولّدها `RoutingEngine` عبر `WorkItemService`.
 *
 * ◆ العطالة: `fin_routing_log` عليه مفتاحٌ فريدٌ على (الكيان · نوعُ المستندِ ·
 *   مرجعُه · المسار) — فإعادةُ تشغيلِ المستهلكِ على الحدثِ نفسِه تُحدِّث ولا
 *   تُكرِّر، ولا تُنشئ مهمةً ثانية.
 *
 * ◆ وما لا مفتاحَ له لا يسقط: الحكمُ الجامعُ RT-17 يلتقطه (OBL-0020).
 */

namespace App\Services\Finance;

require_once dirname(__DIR__, 2) . '/Services/Work/WorkItemService.php';
require_once __DIR__ . '/RoutingEngine.php';

class RoutingConsumer
{
    /** اسمُ المستهلكِ في `ems_event_consumers` — ومؤشِّرُه محفوظٌ به. */
    const NAME = 'finance_routing';

    /**
     * يُبنى المعالِجُ الذي يُسجَّل في `EventDispatcher`.
     * ◆ عقدُ الموزِّع: يُنادي المعالِجَ بـ**(الحدثُ أولًا ثم الاتصال)** — كما
     *   يفعل معالِجُ الماليةِ القائمُ في `cron_events.php`. وعكسُ الترتيبِ يُلقي
     *   TypeError فيُعزل الحدثُ رسالةً ميتةً بلا توجيه.
     *
     * @return callable(array, \mysqli): void
     */
    public static function handler()
    {
        return function (array $event, \mysqli $conn) {
            $co = intval($event['company_id'] ?? 0);
            if ($co <= 0) { return; }

            /* الوقائعُ الملغاةُ أو المعكوسةُ لا تُوجَّه — فالتوجيهُ للحيِّ لا للمُلغى. */
            $status = (string) ($event['event_status'] ?? '');
            if ($status === 'reversed' || (string) ($event['state'] ?? '') === 'rejected') { return; }

            $route = self::routeFor($conn, (string) ($event['event_key'] ?? ''),
                                           (string) ($event['source_module'] ?? ''));

            $ref = (string) ($event['event_no'] ?? '');
            if ($ref === '') { $ref = 'FEV-' . intval($event['id']); }

            $res = RoutingEngine::route($conn, array(
                'company_id'   => $co,
                'route_code'   => $route,                 // فارغٌ = يسقط للحكمِ الجامع
                'source_kind'  => 'financial_event',
                'source_ref'   => $ref,
                'source_dept'  => self::deptOf((string) ($event['source_module'] ?? '')),
                'title'        => 'مراجعةٌ محاسبية: ' . self::labelOf($event),
                'event_ref'    => (string) ($event['correlation_id'] ?? ''),
                'org_unit_id'  => self::orgUnitFor($conn, $co),
                'project_id'   => !empty($event['project_id']) ? intval($event['project_id']) : null,
                'site_id'      => null,
                'created_by'   => intval($event['created_by'] ?? 0) ?: 1,
                'owner_user_id' => intval($event['created_by'] ?? 0) ?: null,
            ));

            /* ◆ OBL-0307: الحدثُ الذي لا يجد وجهةً **ثغرةٌ تُسجَّل عيبًا لا تُهمَل**.
                 والفشلُ هنا لا يُسقط المستهلكَ — يُسجَّل ويمضي، فحدثٌ واحدٌ متعثِّرٌ
                 لا يوقف توجيهَ الباقي. */
            if (empty($res['ok'])) {
                if (function_exists('log_security_event')) {
                    log_security_event('ROUTING_GAP', $ref . ' — ' . (string) ($res['reason'] ?? '؟'));
                } else {
                    error_log('[u13 routing gap] ' . $ref . ' — ' . (string) ($res['reason'] ?? '؟'));
                }
                return;
            }

            /* ربطُ الشاهدِ بالحدثِ الذي ولّده — فالتتبعُ من الطرفين. */
            if (!empty($res['log_id'])) {
                $st = $conn->prepare("UPDATE fin_routing_log SET financial_event_id = ? WHERE id = ?");
                if ($st) {
                    $fid = intval($event['id']); $lid = intval($res['log_id']);
                    $st->bind_param('ii', $fid, $lid);
                    $st->execute();
                    $st->close();
                }
            }
        };
    }

    /**
     * المسارُ المطابقُ لمفتاحِ الحدثِ — الأدقُّ أولًا:
     * (مفتاحٌ + إدارة) ثم (مفتاحٌ وحدَه) ثم (إدارةٌ وحدَها بـ%).
     * والفارغُ يعني «لا مسارَ خاصًّا» فيسقط للحكمِ الجامعِ في المحرّك.
     */
    public static function routeFor(\mysqli $conn, $eventKey, $sourceModule)
    {
        $st = $conn->prepare(
            "SELECT route_code FROM fin_routing_event_map
              WHERE active = 1
                AND (event_key = ? OR event_key = '%')
                AND (source_module = '' OR source_module = ?)
              ORDER BY (event_key <> '%') DESC, (source_module <> '') DESC, priority ASC
              LIMIT 1");
        if (!$st) { return ''; }
        $st->bind_param('ss', $eventKey, $sourceModule);
        if (!$st->execute()) { $st->close(); return ''; }
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? (string) $row['route_code'] : '';
    }

    /** الاسمُ العربيُّ للإدارةِ المصدر — يُطابَق به `source_dept` في المصفوفة. */
    public static function deptOf($module)
    {
        static $m = array(
            'sales' => 'المبيعاتُ والعقود', 'suppliers' => 'إدارةُ الموردين',
            'workforce' => 'الموارد البشرية', 'procurement' => 'المشترياتُ التشغيلية',
            'warehouse' => 'إدارةُ المخازن', 'maintenance' => 'إدارةُ الصيانة',
            'projects' => 'إدارةُ التشغيل', 'revenue' => 'المبيعاتُ والعقود',
            'assets' => 'إدارةُ الأسطول', 'treasury' => 'الخزينةُ والبنوك',
            'movement' => 'إدارةُ التشغيل', 'finance' => 'المالية',
            'transport' => 'النقلُ والترحيل', 'sites' => 'إدارةُ الموقع',
            'tickets' => 'مركزُ البلاغات', 'admin' => 'الشؤونُ الإدارية',
            'system' => 'المالية',
        );
        return isset($m[$module]) ? $m[$module] : (string) $module;
    }

    /** عنوانٌ مقروءٌ للمهمة — من المفتاحِ والمرجعِ لا من رقمٍ مجرَّد. */
    private static function labelOf(array $e)
    {
        $k = (string) ($e['event_key'] ?? '');
        $n = (string) ($e['event_no'] ?? '');
        return trim(($k !== '' ? $k : 'واقعةٌ مالية') . ($n !== '' ? ' · ' . $n : ''));
    }

    /** وحدةٌ تنظيميةٌ للنطاق — `work_items` يرفض عنصرًا بلا نطاق (WF-02). */
    private static function orgUnitFor(\mysqli $conn, $co)
    {
        static $cache = array();
        if (isset($cache[$co])) { return $cache[$co]; }
        $r = $conn->query("SELECT unit_id FROM org_units WHERE company_id = " . (int) $co
                        . " AND active = 1 ORDER BY unit_id LIMIT 1");
        $row = $r ? $r->fetch_row() : null;
        if (!$row) {
            $r = $conn->query("SELECT unit_id FROM org_units ORDER BY unit_id LIMIT 1");
            $row = $r ? $r->fetch_row() : null;
        }
        $cache[$co] = $row ? intval($row[0]) : null;
        return $cache[$co];
    }
}
