<?php
/**
 * ThresholdRegistry — قارئُ العتباتِ المحايدُ (RPR-W14)
 * ═══════════════════════════════════════════════════════════════════════════
 * ⛔ **لا رقمَ يكتبه المبرّجُ من عندِه** (‏قيدُ المالك §٦ · قرارُ المالك الأخير):
 *   كلُّ عتبةٍ تُقرأ من `repair01_w14_thresholds` بحالتِها الثلاثيّة:
 *
 *   | الحالة | القيمة | ماذا يفعل المحرّك |
 *   |---|---|---|
 *   | `OWNER_APPROVED` | رقمٌ نصَّ عليه المالكُ بمرجعِه | يعمل بها |
 *   | `CONFIG_PENDING` | **عدم** | **يردُّ فشلًا مغلقًا** ولا يفترض |
 *   | قيمةُ اختبارٍ | موسومةٌ في عمودٍ منفصل | لا تُقرأ إلّا خلفَ علمِ الاختبارِ **وتُوسَم في المخرَج** |
 *
 * ◆ **وهذا قارئُ سجلٍّ لا محرّكُ نطاق**: لا يكتب في جدولِ نطاقٍ واحدٍ من
 *   الثلاثة، فوجودُه لا يخرق «ثلاثةُ نطاقاتٍ لا محرّكٌ واحد».
 *
 * ◆ **والفشلُ مغلقٌ لا مفتوح**: عتبةٌ غيرُ معتمَدةٍ تردُّ `THRESHOLD_NOT_CONFIGURED`
 *   ولا تُستبدَل بصفرٍ ولا بلا نهاية — «والقيمةُ غيرُ المعتمَدةِ لا تمنع البناءَ
 *   ولا تُخترَع»، فالبناءُ قائمٌ والقرارُ وحدَه هو المؤجَّل.
 * ═══════════════════════════════════════════════════════════════════════════
 */

namespace App\Services\Control;

class ThresholdRegistry
{
    const NOT_CONFIGURED = 'THRESHOLD_NOT_CONFIGURED';

    private static $conn = null;
    private static $cache = null;
    private static $testMode = false;

    public static function setConnection(\mysqli $conn) { self::$conn = $conn; self::$cache = null; }

    /** علمُ الاختبار — ولا يُشغَّل في الإنتاج، والمخرَجُ يُوسَم به */
    public static function enableTestValues($on = true) { self::$testMode = (bool) $on; }
    public static function testMode() { return self::$testMode; }

    private static function load()
    {
        if (self::$cache !== null) { return; }
        self::$cache = array();
        $c = self::$conn;
        if (!($c instanceof \mysqli)) { return; }
        $r = @$c->query("SELECT threshold_key, value_num, test_value_num, status, registry
                           FROM repair01_w14_thresholds");
        while ($r && $x = $r->fetch_assoc()) { self::$cache[$x['threshold_key']] = $x; }
    }

    /**
     * قراءةُ عتبةٍ بحالتِها.
     * @return array{ok:bool, value:float|null, status:string, tagged:string, code:string}
     */
    public static function read($key)
    {
        self::load();
        if (!isset(self::$cache[$key])) {
            return array('ok' => false, 'value' => null, 'status' => 'UNKNOWN',
                         'tagged' => '', 'code' => 'THRESHOLD_KEY_UNKNOWN');
        }
        $row = self::$cache[$key];
        if ($row['status'] === 'OWNER_APPROVED' && $row['value_num'] !== null) {
            return array('ok' => true, 'value' => (float) $row['value_num'],
                         'status' => 'OWNER_APPROVED', 'tagged' => 'OWNER_APPROVED', 'code' => 'OK');
        }
        if (self::$testMode && $row['test_value_num'] !== null) {
            return array('ok' => true, 'value' => (float) $row['test_value_num'],
                         'status' => 'CONFIG_PENDING', 'tagged' => 'TEST_ONLY_VALUE', 'code' => 'OK');
        }
        return array('ok' => false, 'value' => null, 'status' => (string) $row['status'],
                     'tagged' => '', 'code' => self::NOT_CONFIGURED);
    }

    /** العتباتُ المعلَّقةُ — تُعرَض بمفاتيحِها ولا تُخمَّن قيمتُها */
    public static function pending()
    {
        self::load();
        $out = array();
        foreach (self::$cache as $k => $row) {
            if ($row['status'] === 'CONFIG_PENDING') { $out[$k] = (string) $row['registry']; }
        }
        return $out;
    }
}
