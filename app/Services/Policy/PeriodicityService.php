<?php
/**
 * دورية إقفال سلسلة الاعتماد — PeriodicityService (DEC-01 ⑥ الموقَّع · POL-01 §4.1-④)
 * ───────────────────────────────────────────────────────────────────────────
 * القرار بسببه التشغيلي لا بالتفضيل:
 *   ① مواقع عقد الساعة أو الاستعداد المفوتر: **يومية إلزاميًّا** — إثبات
 *     الاستعداد ومسؤوليته يتعذر بعد أيام، وهو أكثر بنود العقد نزاعًا.
 *   ② الافتراض لكل ما لم يُنص عليه: **أسبوعية**.
 *   ③ الشهرية **بقرار لا تلقائيًّا** — شرطها ثلاثة أشهر متتالية بصفر اعتراض
 *     وصفر إعادة (يُثبتها الطالب بالأرقام ولا تُفترض).
 *   ④ **الرجوع لليومية آليًّا** عند اعتراضين في شهر واحد أو نزاع مع طرف.
 *   والدورية تُعلن في سياسة كل عقد وموقع — لا افتراض صامتًا (POL-01 §2-⑤).
 */

namespace App\Services\Policy;

class PeriodicityService
{
    /**
     * الدورية الملزمة لسياق العقد — اليومية لا تُتجاوز لعقود الساعة/الاستعداد
     * المفوتر مهما طلبت السياسة غير ذلك.
     * @param string $contractPricing  'hourly'|'readiness_billed'|'quantity'|…
     * @param string $requested       الدورية المطلوبة في السياسة ('' = لم تُحدد)
     * @return array{periodicity:string,forced:bool,reason:string}
     */
    public static function resolve($contractPricing, $requested = '')
    {
        $pricing = (string) $contractPricing;
        if (in_array($pricing, array('hourly', 'readiness_billed'), true)) {
            return array('periodicity' => 'daily', 'forced' => true,
                'reason' => 'يومية إلزاميًّا — عقد ساعة/استعداد مفوتر: إثبات الاستعداد يتعذر بعد أيام (DEC-01 ⑥)');
        }
        $requested = (string) $requested;
        if ($requested === '' || $requested === null) {
            return array('periodicity' => 'weekly', 'forced' => false,
                'reason' => 'أسبوعية — الافتراض المعلن لما لم يُنص عليه (DEC-01 ⑥)');
        }
        if ($requested === 'monthly') {
            // الشهرية لا تُحسم هنا — طريقها requestMonthly بشرطها المقيس
            return array('periodicity' => 'weekly', 'forced' => false,
                'reason' => 'الشهرية بقرار لا تلقائيًّا — تُطلب عبر requestMonthly بإثبات ثلاثة أشهر نظيفة');
        }
        return array('periodicity' => $requested, 'forced' => false, 'reason' => 'دورية السياسة المعلنة');
    }

    /**
     * طلب الترقية إلى الشهرية — **شرطه ثلاثة أشهر متتالية بصفر اعتراض وصفر
     * إعادة** على سلاسل هذه السياسة (يُقاس من chain_objections لا يُصرَّح).
     * @return array{ok:bool,code:int,reason:string}
     */
    public static function requestMonthly(\mysqli $conn, $companyId, $policyId, $actor)
    {
        $companyId = intval($companyId); $policyId = intval($policyId);
        $stmt = $conn->prepare(
            "SELECT COUNT(*) c FROM chain_objections
              WHERE company_id = ? AND policy_id = ? AND at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)");
        $stmt->bind_param('ii', $companyId, $policyId);
        $stmt->execute();
        $c = intval($stmt->get_result()->fetch_assoc()['c']);
        $stmt->close();
        if ($c > 0) {
            return array('ok' => false, 'code' => 422,
                'reason' => 'شرط الشهرية ثلاثة أشهر متتالية بصفر اعتراض — المرصود ' . $c . ' اعتراضًا في التسعين يومًا الأخيرة (DEC-01 ⑥)');
        }
        $stmt = $conn->prepare("UPDATE approval_chains SET periodicity = 'monthly' WHERE policy_id = ?");
        $stmt->bind_param('i', $policyId);
        $stmt->execute();
        $n = $stmt->affected_rows;
        $stmt->close();
        return array('ok' => true, 'code' => 200,
            'reason' => 'رُقّيت السياسة #' . $policyId . ' إلى الشهرية بقرار (' . $n . ' حلقة) — والرجوع لليومية آلي عند اعتراضين في شهر');
    }

    /**
     * الرجوع الآلي (DEC-01 ⑥-④): اعتراضان في شهر واحد — أو نزاع مسجَّل —
     * يعيدان دورية السياسة إلى اليومية آليًّا وبإشعار. تُستدعى من الدوريات.
     * @return int عدد السياسات المُرجَعة
     */
    public static function sweepAutoRevert(\mysqli $conn, $disputePolicyIds = array())
    {
        $reverted = 0;
        $rows = $conn->query(
            "SELECT o.company_id, o.policy_id, COUNT(*) c
               FROM chain_objections o
              WHERE o.policy_id IS NOT NULL AND o.at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              GROUP BY o.company_id, o.policy_id
             HAVING c >= 2"
        )->fetch_all(MYSQLI_ASSOC);
        $targets = array();
        foreach ($rows as $r) { $targets[intval($r['policy_id'])] = array(intval($r['company_id']), intval($r['c']) . ' اعتراضًا في 30 يومًا'); }
        foreach ($disputePolicyIds as $pid) { $targets[intval($pid)] = array(0, 'نزاع مع عميل أو مورد'); }

        foreach ($targets as $pid => $info) {
            $stmt = $conn->prepare("UPDATE approval_chains SET periodicity = 'daily' WHERE policy_id = ? AND periodicity <> 'daily'");
            $stmt->bind_param('i', $pid);
            $stmt->execute();
            $touched = $stmt->affected_rows;
            $stmt->close();
            if ($touched > 0) {
                $reverted++;
                $co = $info[0] ?: 4;
                $conn->query("INSERT INTO fin_notifications (company_id, target_level, title, link)
                              VALUES ({$co}, 'dept_manager',
                              'دورية سياسة الاعتماد #{$pid} عادت إلى اليومية آليًّا — السبب: " . $conn->real_escape_string($info[1]) . " (DEC-01 ⑥)', 'admin/bus_monitor.php')");
            }
        }
        return $reverted;
    }
}
