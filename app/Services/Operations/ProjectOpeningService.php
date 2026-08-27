<?php
namespace App\Services\Operations;

/**
 * app/Services/Operations/ProjectOpeningService.php — فتحُ المشروعِ عند مالكِه (RPR-W15)
 * ═══════════════════════════════════════════════════════════════════════════
 * ◆ **الرؤيةُ لا تساوي السلطة** (‏قيدُ المالك §٢): قرارُ فتحِ المشروعِ يقع في
 *   مساحةِ القيادة، **وتوليدُ المشروعِ والموقعِ ومركزِ التكلفةِ يقع هنا عند
 *   مالكِه** — إدارةُ التشغيل. ⛔ **ولا يكتب سطحُ القيادةِ في جدولِ إدارةٍ
 *   مباشرةً**، وكان يكتب في ثلاثةٍ منها قبل هذه المرحلة.
 *
 * ◆ **والقرارُ يمرُّ بمحرّكِ الاعتمادِ نفسِه**: هذه الخدمةُ **لا تفتح مشروعًا
 *   بلا مرجعِ سلطةٍ محسوم** — `authority_ref` شرطُ تنفيذٍ لا حقلٌ اختياريّ،
 *   ويأتيها من `ExecDecisionRouter` وحدَه.
 *
 * ◆ **والمعاملةُ ذرّيّة**: مشروعٌ ومركزُ تكلفةٍ ومواقعُ في معاملةٍ واحدة —
 *   فقرارٌ يُرتَدُّ لا يترك مشروعًا بلا مركزِ تكلفة.
 *
 * ◆ **والكتابةُ عبرَ بوّابةِ العزلِ وحدَها** — الكيانُ يُحقَن ولا يُمرَّر.
 * ═══════════════════════════════════════════════════════════════════════════
 */
final class ProjectOpeningService
{
    /** الإدارةُ المالكةُ — إدارة التشغيل. */
    const OWNER_DEPT = 'DEP-11';

    const OK             = 'OK';
    const NO_AUTHORITY   = 'NO_AUTHORITY_REF';
    const BAD_INPUT      = 'BAD_INPUT';
    const WRITE_FAILED   = 'WRITE_FAILED';

    /**
     * يفتح المشروعَ ومركزَ تكلفتِه ومواقعَه — **بمرجعِ سلطةٍ محسوم**.
     *
     * @param array $d `company_id` · `project_name` · `client` · `sites_text`
     *                 · `project_code` · `cost_center_code` · `actor_id`
     *                 · `authority_ref`
     * @return array `verdict` · `project_id` · `cost_center_id` · `site_ids` · `why`
     */
    public static function open($gate, array $d)
    {
        $co    = isset($d['company_id']) ? (int) $d['company_id'] : 0;
        $actor = isset($d['actor_id']) ? (int) $d['actor_id'] : 0;
        $auth  = isset($d['authority_ref']) ? trim((string) $d['authority_ref']) : '';
        $name  = trim((string) (isset($d['project_name']) ? $d['project_name'] : ''));

        if ($auth === '') {
            return self::fail(self::NO_AUTHORITY, 'لا فتح مشروع بلا مرجع سلطة محسوم');
        }
        if ($co <= 0 || $name === '') {
            return self::fail(self::BAD_INPUT, 'لا مشروع بلا كيان واسم');
        }

        $client    = trim((string) (isset($d['client']) ? $d['client'] : '')) ?: '—';
        $sitesText = trim((string) (isset($d['sites_text']) ? $d['sites_text'] : ''));
        $code      = trim((string) (isset($d['project_code']) ? $d['project_code'] : ''));
        $ccCode    = trim((string) (isset($d['cost_center_code']) ? $d['cost_center_code'] : ''));

        $siteNames = array_values(array_filter(array_map('trim',
            preg_split('/[·,]+/u', $sitesText))));
        if (!$siteNames) { $siteNames = array($name); }

        $out = array('project_id' => 0, 'cost_center_id' => 0, 'site_ids' => array());
        try {
            $gate->runInTransaction(function () use ($gate, $co, $name, $client, $sitesText,
                                                     $code, $ccCode, $actor, $siteNames, &$out) {
                $out['project_id'] = (int) $gate->insert('project', array(
                    'name'         => $name,
                    'client'       => $client,
                    'location'     => $sitesText,
                    'project_code' => $code,
                    'total'        => '0',
                    'status'       => 1,
                    'created_by'   => $actor,
                ));
                $out['cost_center_id'] = (int) $gate->insert('fin_cost_centers', array(
                    'code'         => $ccCode !== '' ? $ccCode : ('CC-PRJ-' . $out['project_id']),
                    'name'         => 'مركز تكلفة ' . $name,
                    'center_type'  => 'cost',
                    'owner_module' => 'projects',
                    'level'        => 0,
                    'active'       => 1,
                    'created_by'   => $actor,
                ));
                foreach ($siteNames as $k => $sn) {
                    $out['site_ids'][] = (int) $gate->insert('sites', array(
                        'project_id' => $out['project_id'],
                        'name'       => $sn,
                        'site_kind'  => 'site',
                        'status'     => 1,
                        'is_default' => $k === 0 ? 1 : 0,
                    ));
                }
            }, 'RPR-W15 فتح مشروع بقرار قيادي بمرجع سلطته');
        } catch (\Throwable $t) {
            error_log('w15 project opening: ' . $t->getMessage());
            return self::fail(self::WRITE_FAILED, 'تعذر فتح المشروع عند مالكه');
        }
        if ($out['project_id'] <= 0) {
            return self::fail(self::WRITE_FAILED, 'تعذر فتح المشروع عند مالكه');
        }
        return array('verdict' => self::OK, 'project_id' => $out['project_id'],
                     'cost_center_id' => $out['cost_center_id'],
                     'site_ids' => $out['site_ids'], 'why' => '');
    }

    private static function fail($verdict, $why)
    {
        return array('verdict' => $verdict, 'project_id' => 0, 'cost_center_id' => 0,
                     'site_ids' => array(), 'why' => $why);
    }
}
