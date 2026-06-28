<?php
/**
 * طبقة القوى التشغيلية (EQUIP-OPE-S04) — محرّك الجاهزية البشرية.
 *
 * يحجب تخصيص العامل غير المؤهَّل أو غير اللائق طبياً أو ذي الاعتماد الحرج المنتهي.
 * يُستدعى قبل أي تخصيص (8.4). يعتمد على AccreditationService.
 */

require_once __DIR__ . '/AccreditationService.php';

if (!function_exists('ems_worker_readiness')) {
    /**
     * @return array ['ready'=>bool,'reasons'=>string[]]  (reasons = أسباب المنع إن وُجدت)
     */
    function ems_worker_readiness($conn, $worker_id)
    {
        $worker_id = (int) $worker_id;
        $reasons = [];
        if ($worker_id <= 0) {
            return ['ready' => false, 'reasons' => ['عامل غير صالح']];
        }

        // 1) حالة العامل ولياقته الطبية
        $stmt = $conn->prepare("SELECT workforce_state AS state, medical_fitness_status FROM employees WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $worker_id);
            $stmt->execute();
            $wp = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$wp) {
                return ['ready' => false, 'reasons' => ['العامل غير موجود']];
            }
            if (in_array($wp['state'], ['مرشّح', 'منتهٍ'], true)) {
                $reasons[] = 'حالة العامل لا تسمح بالتخصيص (' . $wp['state'] . ')';
            }
            if (in_array($wp['medical_fitness_status'], ['موقوف طبيًّا', 'يحتاج إعادة تقييم'], true)) {
                $reasons[] = 'اللياقة الطبية: ' . $wp['medical_fitness_status'];
            }
        }

        // 2) اعتمادٌ حرجٌ منتهٍ (محرّك الاعتمادات)
        if (function_exists('ems_worker_has_critical_expired') && ems_worker_has_critical_expired($conn, $worker_id)) {
            $reasons[] = 'يوجد اعتمادٌ حرجٌ منتهٍ (رخصة/سلامة/فحص طبي)';
        }

        return ['ready' => empty($reasons), 'reasons' => $reasons];
    }
}
