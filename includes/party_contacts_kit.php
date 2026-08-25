<?php
/**
 * includes/party_contacts_kit.php — عُدَّةُ جهاتِ الاتصالِ والمفوَّضين (SAL-02 · SUP-02)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **عُدَّةٌ واحدةٌ لسطحَين** — لأنَّ الجدولَ واحدٌ والحقولَ واحدةٌ حرفًا. ونسختانِ
 *   من المنطقِ في ملفَّين **تتفرّقانِ بأوَّلِ تعديل**: تُصلَح إحداهما وتبقى
 *   الأخرى، ولا شيءَ يُنبِّه. (قانونُ «التوأمِ الراكد» نفسُه.)
 *
 * ◆ **والتفويضُ حجّيةٌ لا خانةُ تأشير**: من يُوسَم موقِّعًا يلزمه **صفةٌ ومدًى
 *   ومستندٌ مرجعيّ** — والقاعدةُ ترفضه بـ`chk_pc_authority` قبلَ أن تصله
 *   الشاشة. **فالشاشةُ تشرح المنعَ ولا تخترعه.**
 *
 * ◆ **والحذفُ ناعم**: جهةُ اتصالٍ ذُكرت في عقدٍ لا تُمحى من أثرِ التدقيق.
 *
 * ◆ الكتابةُ كلُّها عبرَ بوابةِ المستأجِر — لا استعلامَ خامٌّ على جدولِ مستأجِر.
 * ═══════════════════════════════════════════════════════════════════════════
 */
if (!defined('EMS_PC_KIT')) {
    define('EMS_PC_KIT', 1);

    /** ترميزٌ للعرض */
    function ems_pc_e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

    /**
     * رمزُ CSRF — **رمزُ النظامِ نفسُه لا رمزٌ خاصٌّ بالسطح**.
     * ◆ **وقانونُ التوأمِ الراكدِ هنا حرفيًّا**: `ems_inject_csrf_fields`
     *   مُعالِجُ طابورِ إخراجٍ **يحقن `csrf_token` في كلِّ نموذجِ POST**
     *   مولَّدٍ من الخادم. فحقلٌ باسمِه من عندي يصير **حقلَين بالاسمِ
     *   نفسِه**، و`$_POST` تأخذ **آخرَهما** — فيُقارَن رمزُ النظامِ برمزٍ
     *   خاصٍّ فيرسُب أبدًا، **والردُّ تحويلٌ صامتٌ بلا سطرِ خطأ**.
     *   (قِيس: صفرُ صفٍّ يُكتَب و302 بلا رسالة.)
     * ⇐ فيُستعمل رمزُ النظام: التوأمانِ يحملان القيمةَ نفسَها فيستوي
     *   أيُّهما قُرئ.
     */
    function ems_pc_token()
    {
        if (function_exists('generate_csrf_token')) { return generate_csrf_token(); }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    function ems_pc_authority_kinds()
    {
        return array('—', 'تفويضٌ عام', 'تفويضٌ خاص', 'سلطةٌ أصلية');
    }
    function ems_pc_states() { return array('نشط', 'منتهٍ', 'ملغى'); }

    /**
     * معالجُ الكتابةِ — يُنادى **قبلَ** أيِّ إخراج.
     * @param string $selfRoute مسارُ العودةِ برسالةِ الحالة
     */
    function ems_pc_handle(mysqli $conn, $partyType, $partyRef, $selfRoute)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { return; }
        $act = isset($_POST['pc_action']) ? (string) $_POST['pc_action'] : '';
        if ($act === '') { return; }

        $posted = isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : '';
        $okTok = function_exists('verify_csrf_token')
            ? verify_csrf_token($posted)
            : ($posted !== '' && hash_equals(ems_pc_token(), $posted));
        if (!$okTok) {
            ems_gov_flash_redirect($selfRoute, 'رمز الحماية غير مطابق ❌', 'GOV-CSRF-403', '');
            exit();
        }

        $gate = ems_tenant_db();
        $s = static function ($k, $max = 190) {
            $v = isset($_POST[$k]) ? trim((string) $_POST[$k]) : '';
            /* ◆ **يُقطَع بحدِّ العمودِ هنا لا في القاعدة**: عمودٌ ضيّقٌ يبتر
                 ويُبلغ نجاحًا — والبترُ الصامتُ أسوأُ من الرفض. */
            return $v === '' ? null : mb_substr($v, 0, $max);
        };
        $d = static function ($k) {
            $v = isset($_POST[$k]) ? trim((string) $_POST[$k]) : '';
            return preg_match('~^\d{4}-\d{2}-\d{2}$~', $v) ? $v : null;
        };

        $isSig = isset($_POST['is_signatory']) ? 1 : 0;
        $row = array(
            'party_type'        => $partyType,
            'party_ref'         => (int) $partyRef,
            'contact_name'      => $s('contact_name'),
            'job_title'         => $s('job_title', 120),
            'phone'             => $s('phone', 40),
            'phone_alt'         => $s('phone_alt', 40),
            'email'             => $s('email'),
            'is_primary'        => isset($_POST['is_primary']) ? 1 : 0,
            'is_signatory'      => $isSig,
            'authority_kind'    => in_array((string) ($_POST['authority_kind'] ?? '—'), ems_pc_authority_kinds(), true)
                                   ? (string) $_POST['authority_kind'] : '—',
            'authority_scope'   => $s('authority_scope', 300),
            'authority_doc_ref' => $s('authority_doc_ref', 120),
            'authority_from'    => $d('authority_from'),
            'authority_to'      => $d('authority_to'),
            'state'             => in_array((string) ($_POST['state'] ?? 'نشط'), ems_pc_states(), true)
                                   ? (string) $_POST['state'] : 'نشط',
            'note'              => $s('note', 300),
        );

        /* ◆ **والتحقّقُ يخصُّ فعلَه لا كلَّ الأفعال**: الإزالةُ لا تحمل اسمًا،
             فشرطُ «الاسمُ مطلوب» فوقَ الأفعالِ كلِّها **يُبطل الإزالةَ صامتًا**
             ويردُّ برسالةٍ عن حقلٍ لا يطلبه الفعلُ أصلًا. (قِيس: الصفُّ يبقى
             و`is_deleted = 0` ولا سطرَ خطأٍ في أيِّ مكان.) */
        if ($act !== 'delete' && $row['contact_name'] === null) {
            ems_gov_flash_redirect($selfRoute, 'اسم جهة الاتصال مطلوب ❌', 'GOV-INFO-200', '');
            exit();
        }
        /* ◆ **ويُشرَح المنعُ قبلَ أن ترفضه القاعدة**: القيدُ هو الحكم، والرسالةُ
             هنا تقول **لماذا** — لا لتُغني عنه بل لئلّا يُقرأ عطبًا. */
        if ($act !== 'delete' && $isSig === 1 && ($row['authority_kind'] === '—' || $row['authority_scope'] === null
                             || $row['authority_doc_ref'] === null)) {
            ems_gov_flash_redirect($selfRoute,
                'المفوض بالتوقيع يلزمه صفة ومدى ومستند مرجعي — والتفويض حجية لا خانة تأشير ❌',
                'GOV-INFO-200', '');
            exit();
        }
        if ($act !== 'delete' && $row['authority_from'] !== null && $row['authority_to'] !== null
            && $row['authority_to'] < $row['authority_from']) {
            ems_gov_flash_redirect($selfRoute, 'نهاية التفويض قبل بدايته ❌', 'GOV-INFO-200', '');
            exit();
        }

        try {
            if ($act === 'add') {
                $row['created_by'] = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
                $gate->insert('party_contacts', $row);
                $msg = 'أضيفت جهة الاتصال ✅';
            } elseif ($act === 'edit') {
                $id = isset($_POST['pc_id']) ? (int) $_POST['pc_id'] : 0;
                if ($id <= 0) { throw new RuntimeException('معرف غير صالح'); }
                $gate->update('party_contacts', $row, array('id' => $id));
                $msg = 'حدثت جهة الاتصال ✅';
            } elseif ($act === 'delete') {
                $id = isset($_POST['pc_id']) ? (int) $_POST['pc_id'] : 0;
                if ($id <= 0) { throw new RuntimeException('معرف غير صالح'); }
                $gate->softDelete('party_contacts', $id);
                $msg = 'أزيلت جهة الاتصال (حذف ناعم يبقي الأثر) ✅';
            } else {
                return;
            }
        } catch (Throwable $e) {
            ems_gov_flash_redirect($selfRoute, 'تعذر الحفظ: ' . mb_substr($e->getMessage(), 0, 160) . ' ❌',
                'GOV-INFO-200', '');
            exit();
        }
        ems_gov_flash_redirect($selfRoute, $msg, 'GOV-INFO-200', '');
        exit();
    }

    /** صفوفُ الطرفِ — قراءةٌ عبرَ البوابة */
    function ems_pc_rows(mysqli $conn, $partyType, $partyRef)
    {
        $gate = ems_tenant_db();
        return $gate->select('party_contacts', array(
            'where'   => array('party_type' => $partyType, 'party_ref' => (int) $partyRef),
            'orderBy' => 'is_primary DESC, is_signatory DESC, contact_name ASC',
        ));
    }
}
