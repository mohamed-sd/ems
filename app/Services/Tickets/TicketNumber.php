<?php
/**
 * TicketNumber — المرجع الواحد لترقيم البلاغات
 * ───────────────────────────────────────────────────────────────────────────
 * «رقمُ البلاغ يصدر من سلطةٍ واحدة». كان للبلاغ مُرقِّمان: متتاليةٌ ذرّية
 * في ems_sequences تخدم شاشة التسجيل والتفريع والتوليد الدوري، ومسحٌ نصيٌّ
 * MAX(...)+1 يخدم الفتح السياقي. كلاهما يكتب في عمودٍ عليه uq_ticket_no،
 * فيتلاقيان حتمًا متى استُعمل المسلكان في الشهر نفسه — ويُصدران الرقم ذاته.
 *
 * التصميم هنا يقفل البابين معًا:
 *   ① سلطةٌ واحدة — كل الكتّاب يمرّون بـallocate() ولا أحد يمسح النص.
 *   ② تخصيصٌ ذاتي الشفاء — العبارة الذرّية الواحدة ترفع العدّاد إلى أعلى
 *      رقمٍ مستعملٍ فعليًّا قبل الزيادة (GREATEST)، فلو أدرج كاتبٌ خارجَ
 *      المتتالية يومًا — أو ارتدّت معاملةٌ فتراجع العدّاد — لحقت به
 *      المتتالية من تلقاء نفسها بدل أن تعلق تطلب رقمًا محجوزًا إلى الأبد.
 *   ③ التخصيص خارج المعاملة — يُستدعى قبل begin، فارتدادُ الإدراج يحرق
 *      رقمًا (فجوةٌ مقبولة) ولا يُعيد استعماله. الفجوة أرخص من التصادم.
 *
 * المتتالية لكل (شركة × سنة) متصلةٌ عبر الأشهر، والشهر للعرض في البادئة —
 * وهو عقد الترقيم القائم (yy-mm-NNNN) ولم يُمَس.
 */

namespace App\Services\Tickets;

class TicketNumber
{
    /** أقصى محاولات إعادة التخصيص عند تصادمٍ نادر تحت التزامن. */
    const MAX_ATTEMPTS = 5;

    /**
     * يخصّص رقم بلاغٍ جديدًا لشركة — ذرّيًّا وذاتي الشفاء.
     * يُستدعى **قبل** فتح المعاملة لا بداخلها.
     *
     * @return string مثل 26-08-9182
     * @throws \RuntimeException إن تعذّر التخصيص
     */
    public static function allocate(\mysqli $conn, $companyId)
    {
        $co    = intval($companyId);
        $yy    = date('y');
        $mm    = date('m');
        $scope = 'tickets:c' . $co . ':y' . $yy;

        // أرضية الشفاء: أعلى تسلسلٍ مستعملٍ فعليًّا في سنة الشركة. نقيسها من
        // الصفوف لا من العدّاد، لأن العدّاد هو ما قد يكون متخلّفًا.
        $floor = self::currentFloor($conn, $co, $yy);

        $stmt = $conn->prepare(
            'INSERT INTO `ems_sequences` (`scope`, `next_val`) VALUES (?, LAST_INSERT_ID(?)) '
            . 'ON DUPLICATE KEY UPDATE `next_val` = LAST_INSERT_ID(GREATEST(`next_val`, ?) + 1)'
        );
        if (!$stmt) {
            throw new \RuntimeException('TicketNumber: تعذّر تحضير عبارة التخصيص: ' . $conn->error);
        }
        $seed = $floor + 1;
        $stmt->bind_param('sii', $scope, $seed, $floor);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new \RuntimeException('TicketNumber: فشل التخصيص: ' . $err);
        }
        $stmt->close();

        $val = intval($conn->insert_id);
        if ($val < 1) {
            throw new \RuntimeException('TicketNumber: قيمة تخصيصٍ غير صالحة للنطاق ' . $scope);
        }

        return $yy . '-' . $mm . '-' . str_pad((string) $val, 4, '0', STR_PAD_LEFT);
    }

    /**
     * يخصّص رقمًا غيرَ مستعملٍ يقينًا — يعيد المحاولة إن سبقه كاتبٌ متزامن.
     * الشفاء الذاتي يجعل التصادم شبه مستحيل، وهذه الحلقة حزامُ أمانٍ
     * لحالة السباق بين قياس الأرضية والتخصيص.
     */
    public static function allocateUnique(\mysqli $conn, $companyId)
    {
        $co = intval($companyId);
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $no = self::allocate($conn, $co);
            $stmt = $conn->prepare('SELECT 1 FROM tickets WHERE company_id = ? AND ticket_no = ? LIMIT 1');
            if (!$stmt) {
                return $no; // تعذّر الفحص — الرقم مخصَّصٌ ذرّيًّا أصلًا
            }
            $stmt->bind_param('is', $co, $no);
            $stmt->execute();
            $taken = $stmt->get_result()->num_rows > 0;
            $stmt->close();
            if (!$taken) {
                return $no;
            }
        }
        throw new \RuntimeException('TicketNumber: تعذّر إيجاد رقمٍ شاغرٍ بعد ' . self::MAX_ATTEMPTS . ' محاولات');
    }

    /** أعلى تسلسلٍ مستعملٍ في (شركة × سنة) — قياسٌ من الصفوف لا من العدّاد. */
    private static function currentFloor(\mysqli $conn, $companyId, $yy)
    {
        $co  = intval($companyId);
        $like = $conn->real_escape_string($yy) . '-%';
        $stmt = $conn->prepare(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(ticket_no, '-', -1) AS UNSIGNED)), 0) mx
               FROM tickets
              WHERE company_id = ? AND ticket_no LIKE ?
                AND ticket_no REGEXP '^[0-9]{2}-[0-9]{2}-[0-9]+$'"
        );
        if (!$stmt) {
            return 0;
        }
        $stmt->bind_param('is', $co, $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return intval($row['mx'] ?? 0);
    }
}
