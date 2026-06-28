<?php
/**
 * طبقة القوى التشغيلية (EQUIP-OPE-S04) — محرّك التغطية (Coverage).
 *
 * لعاملٍ خارجٍ (إجازة/غياب)، يرتّب البدائل: أساسي ← احتياطي ← مؤقت
 * من employees.primary_backup_id + worker_backup، ويعيد البديل المتاح الأنسب.
 * يُستدعى من worker_leave_absence.php (substitute_id) وworker_allocation.php (active_backup_id).
 *
 * نمط دوالٍ خفيفٌ. Prepared Statements. صفر لمسٍ للقائم.
 * يعتمد على محرّك الجاهزية البشرية لفحص توفّر البديل.
 */

require_once __DIR__ . '/HumanReadinessService.php';

if (!function_exists('ems_coverage_is_available')) {
    /**
     * هل البديل متاحٌ للتغطية الآن؟ جاهزٌ بشرياً + ليس في إجازة/غيابٍ ساري + ليس منتهياً.
     */
    function ems_coverage_is_available($conn, $candidate_id)
    {
        $candidate_id = (int) $candidate_id;
        if ($candidate_id <= 0) {
            return false;
        }
        // 1) الجاهزية البشرية (حالة + لياقة + اعتماد حرج)
        if (function_exists('ems_worker_readiness')) {
            $rd = ems_worker_readiness($conn, $candidate_id);
            if (empty($rd['ready'])) {
                return false;
            }
        }
        // 2) ليس في إجازة/غيابٍ ساري اليوم
        $stmt = $conn->prepare(
            "SELECT 1 FROM worker_leave_absence
              WHERE employee_id = ?
                AND state IN ('معتمد','مفتوح','مُغطًّى')
                AND (date_from IS NULL OR date_from <= CURDATE())
                AND (date_to   IS NULL OR date_to   >= CURDATE())
              LIMIT 1"
        );
        if ($stmt) {
            $stmt->bind_param('i', $candidate_id);
            $stmt->execute();
            $busy = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($busy) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('ems_coverage_candidates')) {
    /**
     * يعيد قائمة البدائل المرتّبة (أساسي ← احتياطي ← مؤقت) لعاملٍ، مع حالة التوفّر.
     * يُزيل التكرار بحيث يظهر كل بديلٍ مرّةً واحدةً بأعلى رتبةٍ له.
     *
     * @return array صفوف [id, name, rank('أساسي'|'احتياطي'|'مؤقت'), order(int), available(bool)]
     */
    function ems_coverage_candidates($conn, $worker_id)
    {
        $worker_id = (int) $worker_id;
        $out = [];
        if ($worker_id <= 0) {
            return $out;
        }
        $seen = [];

        $add = function ($cid, $rank, $order) use (&$out, &$seen, $conn) {
            $cid = (int) $cid;
            if ($cid <= 0 || isset($seen[$cid])) {
                return;
            }
            $name = null;
            $st = $conn->prepare(
                "SELECT name FROM employees WHERE id = ? LIMIT 1"
            );
            if ($st) {
                $st->bind_param('i', $cid);
                $st->execute();
                $r = $st->get_result()->fetch_assoc();
                $st->close();
                $name = $r ? $r['name'] : null;
            }
            $seen[$cid] = true;
            $out[] = [
                'id'        => $cid,
                'name'      => $name,
                'rank'      => $rank,
                'order'     => $order,
                'available' => ems_coverage_is_available($conn, $cid),
            ];
        };

        // 1) البديل الأساسي من employees.primary_backup_id
        $st = $conn->prepare("SELECT primary_backup_id FROM employees WHERE id = ? LIMIT 1");
        if ($st) {
            $st->bind_param('i', $worker_id);
            $st->execute();
            $r = $st->get_result()->fetch_assoc();
            $st->close();
            if ($r && !empty($r['primary_backup_id'])) {
                $add($r['primary_backup_id'], 'أساسي', 1);
            }
        }

        // 2) البدائل الإضافية من worker_backup (احتياطي قبل مؤقت)
        $st = $conn->prepare(
            "SELECT backup_employee_id, backup_type FROM worker_backup
              WHERE employee_id = ?
              ORDER BY (backup_type = 'احتياطي') DESC, id ASC"
        );
        if ($st) {
            $st->bind_param('i', $worker_id);
            $st->execute();
            $res = $st->get_result();
            while ($row = $res->fetch_assoc()) {
                $rank  = ($row['backup_type'] === 'مؤقت') ? 'مؤقت' : 'احتياطي';
                $order = ($rank === 'احتياطي') ? 2 : 3;
                $add($row['backup_employee_id'], $rank, $order);
            }
            $st->close();
        }

        // ترتيب نهائيٌّ بالأولوية ثم بظهور الإدخال
        usort($out, function ($a, $b) {
            return $a['order'] <=> $b['order'];
        });
        return $out;
    }
}

if (!function_exists('ems_coverage_best')) {
    /**
     * أنسب بديلٍ متاحٍ (أعلى رتبة + متوفّر)، أو null إن لم يوجد متاح.
     * @return array|null  صفّ المرشّح كما في ems_coverage_candidates.
     */
    function ems_coverage_best($conn, $worker_id)
    {
        foreach (ems_coverage_candidates($conn, $worker_id) as $cand) {
            if (!empty($cand['available'])) {
                return $cand;
            }
        }
        return null;
    }
}

if (!function_exists('ems_coverage_best_id')) {
    /** معرّف أنسب بديلٍ متاحٍ أو null (مختصرٌ للاستدعاء من الشاشات). */
    function ems_coverage_best_id($conn, $worker_id)
    {
        $best = ems_coverage_best($conn, $worker_id);
        return $best ? (int) $best['id'] : null;
    }
}
