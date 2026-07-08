<?php
/**
 * خدمة الترقيم الخادمي — ServerId (K8 · خطة المرحلة 1 §2 · عقد §9)
 * ───────────────────────────────────────────────────────────────────────────
 * المصدر الوحيد لتوليد معرّفات العقد المؤسسي خادميًّا (معيار القبول §4-⑤).
 *
 * الآلية والمقايضات:
 *   • ulid()          — ULID (Crockford Base32، 26 حرفًا): 48-bit طابع ملي-ثانية
 *                       + 80-bit عشوائية مشفّرة (random_bytes). زمنيّ الترتيب
 *                       معجميًّا عبر الملي-ثواني (جاهز لتعدّد المراكز — R02 §11.2-4)؛
 *                       داخل الملي-ثانية الواحدة الترتيب غير مضمون (مقايضة مقبولة:
 *                       ترتيب الاستهلاك الرسمي عبر id التسلسلي/الـCursor لا ULID).
 *                       احتمال التصادم ≈ 2^-80 لكل زوج داخل نفس الملي-ثانية — مهمل.
 *   • correlationId() — ULID جديد لحدث «الجذر» فقط. الأحداث المشتقة لا تولّد؛
 *                       بل «ترث» correlation_id الأصل (وسيط اختياري في ناشر K3) —
 *                       فيجمع كل سلسلة الأثر من أصلٍ واحدٍ طرفًا لطرف (غرض عقد §9).
 *   • idempotencyKey()— حتمي لا عشوائي: نفس العملية المصدرية ⇒ نفس المفتاح دائمًا،
 *                       فتصطدم إعادة المحاولة بـ uq_ffe_idempotency وتُمنع الازدواجية.
 *   • nextNo()        — أرقام أعمالٍ متسلسلة per-scope بتخصيصٍ ذرّي عبر ems_sequences
 *                       (INSERT..ON DUPLICATE KEY UPDATE مع LAST_INSERT_ID — قفل صفٍّ
 *                       يضمن التفرّد تحت التزامن). بديل fin_gen_code (COUNT+1 السباقي
 *                       المعرَّض لإعادة الاستخدام بعد الحذف) — القديم يبقى لشاشاته
 *                       حتى هجرتها، وكلّ مستهلكٍ جديدٍ يستعمل نطاقًا/بادئةً منفصلة.
 *
 * العلاقة بـ sync_uuid (char(36) الخامل في الجداول): متكاملان لا متوازيان —
 *   sync_uuid هوية «ما قبل الخادم» يولّدها العميل الميداني أوفلاين (ADR-11 المؤجَّل،
 *   صفر كتّاب اليوم)، وهذه الخدمة تصدر «الهوية النهائية الخادمية» عند الوصول.
 *   لا تولّد هذه الخدمة sync_uuid ولا تلمسه.
 */

namespace App\Core;

class ServerId
{
    /** أبجدية Crockford Base32 (بلا I L O U). */
    const CROCKFORD = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** ULID قياسي: 10 أحرف زمن (ms) + 16 حرف عشوائية = 26 حرفًا. */
    public static function ulid()
    {
        $ms = (int) floor(microtime(true) * 1000);
        $time = '';
        for ($i = 0; $i < 10; $i++) {
            $time = self::CROCKFORD[$ms % 32] . $time;
            $ms = intdiv($ms, 32);
        }
        $rand = '';
        $bytes = random_bytes(10); // 80 bit
        // 16 حرفًا × 5 بت = 80 بت — نقرأ بتيًّا من الـ 10 بايتات.
        $acc = 0; $bits = 0;
        for ($i = 0; $i < 10; $i++) {
            $acc = ($acc << 8) | ord($bytes[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $rand .= self::CROCKFORD[($acc >> $bits) & 31];
            }
        }
        return $time . $rand;
    }

    /** معرّف سلسلة الأثر لحدث الجذر — المشتقات ترث ولا تولّد (عقد §9). */
    public static function correlationId()
    {
        return self::ulid();
    }

    /**
     * مفتاح عطالة الأثر الحتمي: نفس (نوع الحدث، الكيان، معرّفه) ⇒ نفس المفتاح.
     * ≤64 حرفًا (حدّ العمود)؛ ما زاد يُكثَّف sha1 مع بقاء الحتمية.
     */
    public static function idempotencyKey($eventKey, $entityType, $entityId)
    {
        $raw = $eventKey . ':' . $entityType . ':' . $entityId;
        if (strlen($raw) <= 64) {
            return $raw;
        }
        return 'sha1:' . sha1($raw); // 45 حرفًا — حتمي أيضًا
    }

    /**
     * الرقم التالي في متتالية scope — ذرّي تحت التزامن (قفل صف ems_sequences).
     * يعيد int موجبًا، أو يرمي RuntimeException عند فشل القاعدة (لا أرقام صامتة الفشل).
     */
    public static function next(\mysqli $conn, $scope)
    {
        $stmt = $conn->prepare(
            'INSERT INTO `ems_sequences` (`scope`, `next_val`) VALUES (?, LAST_INSERT_ID(1)) '
            . 'ON DUPLICATE KEY UPDATE `next_val` = LAST_INSERT_ID(`next_val` + 1)'
        );
        if (!$stmt) {
            throw new \RuntimeException('ServerId::next prepare failed: ' . $conn->error);
        }
        $stmt->bind_param('s', $scope);
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new \RuntimeException('ServerId::next execute failed: ' . $err);
        }
        $stmt->close();
        $val = (int) $conn->insert_id;
        if ($val < 1) {
            throw new \RuntimeException('ServerId::next returned non-positive value for scope=' . $scope);
        }
        return $val;
    }

    /** رقم أعمالٍ مقروء: PREFIX-0007 ضمن scope (مثال scope: 'fin_financial_events:EV:'.company_id). */
    public static function nextNo(\mysqli $conn, $scope, $prefix, $pad = 4)
    {
        return $prefix . '-' . str_pad((string) self::next($conn, $scope), $pad, '0', STR_PAD_LEFT);
    }
}
