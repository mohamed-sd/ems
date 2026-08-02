<?php
/**
 * app/Services/Actions/ImpactResolver.php — محلّلُ الأثر (ACT-01 v6 §8-④)
 * ───────────────────────────────────────────────────────────────────────────
 * «يقرأ action_impacts فيُخطر ويحدّث العدّادات ويعلّم اللوحاتِ المتأثرةَ
 * بالتحديث — فلا لوحةَ تبقى قديمةً بعد فعلٍ يمسّها.»
 *
 * الكتابةُ في action_impact_log (insert-only) — واللوحاتُ تقرأ غيرَ المقروء
 * لجهتها فتعلم أن أرقامَها بحاجةِ إنعاش. يُستدعى بعد نجاح الفعل — إما صراحةً
 * من الخدمة المالكة، وإما آليًّا من خطاف نهاية الطلب في حارس الأفعال.
 */

namespace App\Services\Actions;

class ImpactResolver
{
    /**
     * يطبّق خريطةَ أثرِ فعلٍ نجح: سطرٌ لكل متأثرٍ معلَن.
     * @return int عددُ الأسطر المطبَّقة
     */
    public static function apply($conn, $companyId, $actionCode, $subjectRef = null, $actorId = null)
    {
        $companyId = intval($companyId);
        $code = mysqli_real_escape_string($conn, (string) $actionCode);
        $r = mysqli_query($conn, "SELECT impacted_type, impacted_ref, effect FROM action_impacts
                                  WHERE action_code = '$code'");
        if (!$r) { return 0; }
        $n = 0;
        $subj = $subjectRef === null ? 'NULL' : "'" . mysqli_real_escape_string($conn, mb_substr((string) $subjectRef, 0, 120)) . "'";
        $actor = $actorId === null ? 'NULL' : intval($actorId);
        while ($i = mysqli_fetch_assoc($r)) {
            $t = mysqli_real_escape_string($conn, $i['impacted_type']);
            $ref = mysqli_real_escape_string($conn, $i['impacted_ref']);
            $e = mysqli_real_escape_string($conn, $i['effect']);
            if (mysqli_query($conn, "INSERT INTO action_impact_log
                    (company_id, action_code, impacted_type, impacted_ref, effect, subject_ref, actor_person_id)
                    VALUES ($companyId, '$code', '$t', '$ref', '$e', $subj, $actor)")) {
                $n++;
            }
        }
        return $n;
    }

    /**
     * ما ينتظر جهةً (لوحةَ وحدةٍ أو شخصًا): الأسطرُ غيرُ المقروءة — تقرؤها اللوحةُ
     * فتعلم أنها مَسّها فعلٌ منذ آخرِ إنعاش، ثم تعلّمها مقروءةً.
     */
    public static function pendingFor($conn, $companyId, $impactedType, $impactedRef, $limit = 50)
    {
        $companyId = intval($companyId);
        $t = mysqli_real_escape_string($conn, (string) $impactedType);
        $ref = mysqli_real_escape_string($conn, (string) $impactedRef);
        $limit = max(1, min(200, intval($limit)));
        $out = array();
        $r = mysqli_query($conn, "SELECT il_id, action_code, effect, subject_ref, created_at
                                  FROM action_impact_log
                                  WHERE company_id = $companyId AND impacted_type = '$t'
                                    AND impacted_ref = '$ref' AND seen = 0
                                  ORDER BY il_id LIMIT $limit");
        if ($r) { while ($x = mysqli_fetch_assoc($r)) { $out[] = $x; } }
        return $out;
    }

    /** تعليمُ أسطرٍ مقروءةً بعد أن أنعشت اللوحةُ أرقامَها. */
    public static function markSeen($conn, array $ids)
    {
        $ids = array_filter(array_map('intval', $ids));
        if (!$ids) { return 0; }
        mysqli_query($conn, "UPDATE action_impact_log SET seen = 1 WHERE il_id IN (" . implode(',', $ids) . ")");
        return mysqli_affected_rows($conn);
    }
}
