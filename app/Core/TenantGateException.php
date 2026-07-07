<?php
/**
 * استثناء بوابة العزل (ADR-02) — يُرمى عند أي عمليةٍ مخالفةٍ لعقد البوابة
 * (جدول غير مسجَّل، سياق بلا شركة، محاولة تزوير هوية، كتابة على مرجعٍ عام...).
 * كل رميةٍ تُسبق بتسجيل tenant_gate_violation في security.log.
 */

namespace App\Core;

class TenantGateException extends \RuntimeException
{
}
