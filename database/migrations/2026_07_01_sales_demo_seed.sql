-- ══════════════════════════════════════════════════════════════════════════════
-- بيانات تجريبية مترابطة لشاشات المبيعات (company_id = 4) — S05
-- تُملأ: الفرص، الأنشطة، المخاطر، المناقصات، العروض، نماذج التسعير.
-- تُستثنى: العملاء/المشاريع/العقود (تُستخدم كمراسٍ للربط الحقيقي).
-- created_by = 13 (مسؤول المبيعات). التنفيذ: mysql -u root --default-character-set=utf8mb4 < ملف
-- التراجع في نهاية الملف.
-- ══════════════════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;
SET @cid := 4;
SET @by  := 13;

-- ── 1) الفرص (12) — مرتبطة بعملاء حقيقيين، تغطي كامل المسار
INSERT INTO opportunities
 (company_id, opp_code, title, client_id, source, sector_category, state_region, revenue_model,
  expected_revenue, currency, probability, stage, attractiveness, strategy_fit, capacity_summary,
  funding_needed, study_decision, expected_close_date, lost_reason, win_reason, review_notes, notes, created_by)
VALUES
 (@cid,'OPP-1001','تأجير حفارات لتوسعة منجم إليانس',1,'مناقصة','تعدين','نهر النيل','hourly',4800000,'USD',75,'تفاوض','عالية','عالي','3 حفارات + 5 قلابات + مشغلون على ورديتين',0,'متابعة','2026-08-20',NULL,NULL,NULL,'فرصة استراتيجية مع عميل قائم',@by),
 (@cid,'OPP-1002','نقل خام بالطن لشركة محمد',2,'إحالة','تعدين','البحر الأحمر','ton',2300000,'USD',55,'عرض مقدم','عالية','متوسط','8 قلابات نقل خام',150000,'متابعة','2026-09-05',NULL,NULL,NULL,NULL,@by),
 (@cid,'OPP-1003','تخريم آبار لطريق النيل للمقاولات',3,'سوق','بنية تحتية','الخرطوم','meter',1150000,'USD',35,'مؤهلة','متوسطة','متوسط','خرّامتان + لقم حفر',0,'متابعة','2026-09-30',NULL,NULL,NULL,NULL,@by),
 (@cid,'OPP-1004','أعمال حفر لشركة علي',4,'عميل قائم','بنية تحتية','الجزيرة','mixed',900000,'SDG',20,'قيد الدراسة','منخفضة','منخفض','حفارة + لودر',300000,'تعليق','2026-10-15',NULL,NULL,NULL,'يحتاج ترتيب تمويل قبل المتابعة',@by),
 (@cid,'OPP-1005','تأجير لوادرات لدال للسيارات',5,'إحالة','تعدين','نهر النيل','hourly',3600000,'USD',100,'فوز','عالية','عالي','4 لوادرات + مشغلون',0,'متابعة','2026-07-10',NULL,'سعر تنافسي وجاهزية أسطول عالية',NULL,'تحوّلت إلى عقد نافذ',@by),
 (@cid,'OPP-1006','عقد نقل صافولا',6,'عميل قائم','بنية تحتية','سنار','ton',5200000,'USD',100,'فوز','عالية','عالي','12 قلاب نقل',0,'متابعة','2026-07-01',NULL,'علاقة قوية وخدمة سابقة ناجحة',NULL,'تحوّلت إلى عقد نافذ',@by),
 (@cid,'OPP-1007','مناقصة تخريم حكومية',3,'مناقصة','بنية تحتية','كسلا','meter',2000000,'USD',0,'خسارة','عالية','متوسط','3 خرّامات',0,'متابعة','2026-06-20','منافس بسعر أقل بنسبة 12%',NULL,'نراجع تسعير المناقصات الحكومية ونحسّن الجاهزية',NULL,@by),
 (@cid,'OPP-1008','توسعة أسطول إليانس مرحلة 2',1,'سوق','تعدين','نهر النيل','hourly',1800000,'USD',10,'جديدة','متوسطة','عالي','حفارتان إضافيتان',0,NULL,'2026-11-01',NULL,NULL,NULL,NULL,@by),
 (@cid,'OPP-1009','خدمات نقل بالطن لشركة محمد موسم 2',2,'إحالة','تعدين','البحر الأحمر','ton',700000,'SDG',0,'مستبعدة','منخفضة','منخفض','4 قلابات',0,'استبعاد','2026-06-15','خارج القدرة الحالية للأسطول',NULL,'نعيد التقييم الموسم القادم',NULL,@by),
 (@cid,'OPP-1010','تأجير حفارات للنيل للمقاولات',3,'سوق','بنية تحتية','الخرطوم','hourly',2650000,'USD',75,'تفاوض','عالية','متوسط','حفارتان + قلابات',0,'متابعة','2026-08-31',NULL,NULL,NULL,NULL,@by),
 (@cid,'OPP-1011','مشروع تخريم دال',5,'إحالة','تعدين','نهر النيل','meter',1400000,'USD',55,'عرض مقدم','متوسطة','متوسط','خرّامة + مشغل',0,'متابعة','2026-09-12',NULL,NULL,NULL,NULL,@by),
 (@cid,'OPP-1012','عقد نقل وصيانة صافولا إضافي',6,'عميل قائم','بنية تحتية','سنار','mixed',3100000,'USD',35,'مؤهلة','عالية','عالي','قلابات + دعم صيانة',0,'متابعة','2026-10-05',NULL,NULL,NULL,NULL,@by);

-- ── 2) نماذج التسعير (10) — كتالوج مرجعي للنماذج الثلاثة
INSERT INTO pricelists
 (company_id, pricelist_code, name, currency, revenue_model, base_price, distance_factor, shift_factor, volume_factor, duration_factor, notes, created_by)
VALUES
 (@cid,'PL-1001','أسعار تأجير الحفارات بالساعة - دولار','USD','hourly',120.00,1.000,1.150,0.950,0.900,'المعيار للحفارات',@by),
 (@cid,'PL-1002','أسعار تأجير اللوادرات بالساعة - دولار','USD','hourly',95.00,1.000,1.100,0.960,0.920,NULL,@by),
 (@cid,'PL-1003','أسعار تأجير الدوزرات بالساعة - جنيه','SDG','hourly',85000.00,1.000,1.120,0.980,0.950,'تسعير محلي بالجنيه',@by),
 (@cid,'PL-1004','أسعار النقل بالطن - خام - دولار','USD','ton',14.50,1.200,1.050,0.900,0.950,'خام المناجم',@by),
 (@cid,'PL-1005','أسعار النقل بالطن - ويست - دولار','USD','ton',9.75,1.150,1.050,0.920,0.960,NULL,@by),
 (@cid,'PL-1006','أسعار النقل بالطن - جنيه','SDG','ton',5200.00,1.100,1.030,0.940,0.970,'تسعير محلي',@by),
 (@cid,'PL-1007','أسعار التخريم بالمتر الطولي - دولار','USD','meter',38.00,1.000,1.100,0.950,0.930,NULL,@by),
 (@cid,'PL-1008','أسعار التخريم بالمتر المكعب - دولار','USD','meter',52.00,1.000,1.120,0.940,0.930,NULL,@by),
 (@cid,'PL-1009','أسعار تأجير الحفارات - عقود طويلة - دولار','USD','hourly',110.00,1.000,1.100,0.900,0.800,'خصم مدة للعقود الطويلة',@by),
 (@cid,'PL-1010','أسعار النقل بالطن - مسافات بعيدة - دولار','USD','ton',18.00,1.500,1.080,0.900,0.950,'يشمل عامل مسافة مرتفع',@by);

-- ── 3) المناقصات (10) — authority → عملاء، opportunity → فرص
INSERT INTO tenders
 (company_id, tender_code, name, authority_id, opportunity_id, closing_date, participation_state, result, result_reason, notes, created_by)
VALUES
 (@cid,'TND-1001','مناقصة توسعة منجم إليانس 2026/14',1,(SELECT id FROM opportunities WHERE opp_code='OPP-1001' AND company_id=@cid LIMIT 1),'2026-08-10','مقدمة','قيد التقييم',NULL,'مقدَّمة وبانتظار النتيجة',@by),
 (@cid,'TND-1002','مناقصة تخريم حكومية 2026/22',3,(SELECT id FROM opportunities WHERE opp_code='OPP-1007' AND company_id=@cid LIMIT 1),'2026-06-15','مقدمة','خسارة','منافس بسعر أقل',NULL,@by),
 (@cid,'TND-1003','مناقصة تخريم دال 2026/31',5,(SELECT id FROM opportunities WHERE opp_code='OPP-1011' AND company_id=@cid LIMIT 1),'2026-09-05','إعداد','قيد التقييم',NULL,'قيد تجهيز المستندات',@by),
 (@cid,'TND-1004','مناقصة نقل صافولا 2026/09',6,(SELECT id FROM opportunities WHERE opp_code='OPP-1006' AND company_id=@cid LIMIT 1),'2026-06-25','مقدمة','فوز','أفضل عرض فني ومالي',NULL,@by),
 (@cid,'TND-1005','مناقصة نقل خام شركة محمد',2,(SELECT id FROM opportunities WHERE opp_code='OPP-1002' AND company_id=@cid LIMIT 1),'2026-08-28','مقدمة','قيد التقييم',NULL,NULL,@by),
 (@cid,'TND-1006','مناقصة أعمال حفر النيل للمقاولات',3,(SELECT id FROM opportunities WHERE opp_code='OPP-1010' AND company_id=@cid LIMIT 1),'2026-08-20','إعداد','قيد التقييم',NULL,NULL,@by),
 (@cid,'TND-1007','مناقصة تأجير لوادرات دال',5,(SELECT id FROM opportunities WHERE opp_code='OPP-1005' AND company_id=@cid LIMIT 1),'2026-06-30','مقدمة','فوز','جاهزية أسطول وسعر تنافسي',NULL,@by),
 (@cid,'TND-1008','مناقصة بنية تحتية شركة علي',4,(SELECT id FROM opportunities WHERE opp_code='OPP-1004' AND company_id=@cid LIMIT 1),'2026-07-15','مسحوبة','إلغاء','انسحبنا لعدم جاهزية التمويل',NULL,@by),
 (@cid,'TND-1009','مناقصة توسعة إليانس مرحلة 2',1,(SELECT id FROM opportunities WHERE opp_code='OPP-1008' AND company_id=@cid LIMIT 1),'2026-10-25','إعداد','قيد التقييم',NULL,NULL,@by),
 (@cid,'TND-1010','مناقصة صافولا إضافي',6,(SELECT id FROM opportunities WHERE opp_code='OPP-1012' AND company_id=@cid LIMIT 1),'2026-09-28','مقدمة','قيد التقييم',NULL,NULL,@by);

-- ── 4) العروض (10) — client + opportunity، حالات تعكس مراحل الفرص
INSERT INTO quotations
 (company_id, quotation_code, client_id, opportunity_id, currency, amount_total, validity_date, payment_terms, state, notes, created_by)
VALUES
 (@cid,'QUO-1001',1,(SELECT id FROM opportunities WHERE opp_code='OPP-1001' AND company_id=@cid LIMIT 1),'USD',4800000.00,'2026-08-31','دفعة مقدمة 25% ثم نصف شهري','مقدم','تحت التفاوض',@by),
 (@cid,'QUO-1002',2,(SELECT id FROM opportunities WHERE opp_code='OPP-1002' AND company_id=@cid LIMIT 1),'USD',2300000.00,'2026-09-15','شهري','مقدم',NULL,@by),
 (@cid,'QUO-1003',5,(SELECT id FROM opportunities WHERE opp_code='OPP-1005' AND company_id=@cid LIMIT 1),'USD',3600000.00,'2026-07-15','دفعة مقدمة 30%','مقبول','قُبل وتحوّل لعقد',@by),
 (@cid,'QUO-1004',6,(SELECT id FROM opportunities WHERE opp_code='OPP-1006' AND company_id=@cid LIMIT 1),'USD',5200000.00,'2026-07-05','شهري','مقبول','قُبل وتحوّل لعقد',@by),
 (@cid,'QUO-1005',3,(SELECT id FROM opportunities WHERE opp_code='OPP-1007' AND company_id=@cid LIMIT 1),'USD',2000000.00,'2026-06-18','شهري','مرفوض','رُفض لصالح منافس',@by),
 (@cid,'QUO-1006',3,(SELECT id FROM opportunities WHERE opp_code='OPP-1010' AND company_id=@cid LIMIT 1),'USD',2650000.00,'2026-09-10','دفعة مقدمة 20%','مقدم',NULL,@by),
 (@cid,'QUO-1007',5,(SELECT id FROM opportunities WHERE opp_code='OPP-1011' AND company_id=@cid LIMIT 1),'USD',1400000.00,'2026-09-20','شهري','مقدم',NULL,@by),
 (@cid,'QUO-1008',4,(SELECT id FROM opportunities WHERE opp_code='OPP-1004' AND company_id=@cid LIMIT 1),'SDG',900000.00,'2026-10-20','يحدد لاحقاً','مسودة','بانتظار الجاهزية التمويلية',@by),
 (@cid,'QUO-1009',1,(SELECT id FROM opportunities WHERE opp_code='OPP-1008' AND company_id=@cid LIMIT 1),'USD',1800000.00,'2026-11-10','نصف شهري','مسودة',NULL,@by),
 (@cid,'QUO-1010',6,(SELECT id FROM opportunities WHERE opp_code='OPP-1012' AND company_id=@cid LIMIT 1),'USD',3100000.00,'2026-10-12','شهري','مسودة',NULL,@by);

-- ── 5) الأنشطة (12) — polymorphic على فرص/عملاء/عقود
INSERT INTO activities
 (company_id, activity_code, activity_type, entity_type, entity_id, subject, activity_date, assigned_user_id, outcome, is_negotiation, notes, created_by)
VALUES
 (@cid,'ACT-1001','تفاوضي','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1001' AND company_id=@cid LIMIT 1),'جولة تفاوض أولى على سعر الساعة','2026-07-08',13,'اتفاق مبدئي على النطاق، السعر قيد النقاش',1,NULL,@by),
 (@cid,'ACT-1002','زيارة عميل','client',1,'زيارة تعريفية لإدارة إليانس','2026-06-30',13,'انطباع إيجابي وطلب عرض رسمي',0,NULL,@by),
 (@cid,'ACT-1003','اجتماع موقع','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1001' AND company_id=@cid LIMIT 1),'معاينة موقع المنجم لتقدير المتطلبات','2026-07-03',6,'تحديد 3 حفارات و5 قلابات',0,NULL,@by),
 (@cid,'ACT-1004','هاتفي','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1002' AND company_id=@cid LIMIT 1),'متابعة ملاحظات العميل على العرض','2026-07-06',13,'العميل يطلب مراجعة سعر الطن',0,NULL,@by),
 (@cid,'ACT-1005','تفاوضي','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1010' AND company_id=@cid LIMIT 1),'تفاوض على شروط الدفع','2026-07-09',12,'الاتفاق على دفعة مقدمة 20%',1,NULL,@by),
 (@cid,'ACT-1006','زيارة مناجم','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1003' AND company_id=@cid LIMIT 1),'زيارة موقع الحفر لتقييم الجدوى','2026-07-02',6,'الموقع مناسب، يلزم لقم حفر إضافية',0,NULL,@by),
 (@cid,'ACT-1007','افتراضي','client',6,'اجتماع أونلاين لتجديد التعاون','2026-06-28',13,'اتفاق على توسيع نطاق الخدمات',0,NULL,@by),
 (@cid,'ACT-1008','زيارة عميل','contract',6,'متابعة تنفيذ عقد دال مع العميل','2026-07-07',13,'رضا العميل عن الأداء',0,'نشاط على عقد نافذ',@by),
 (@cid,'ACT-1009','اجتماع موقع','contract',7,'اجتماع تشغيلي لمتابعة الأداء التعاقدي - صافولا','2026-07-05',6,'مراجعة الكميات المنفذة',0,NULL,@by),
 (@cid,'ACT-1010','هاتفي','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1005' AND company_id=@cid LIMIT 1),'تأكيد قبول العرض والبدء','2026-07-09',13,'قبول رسمي، إحالة لفتح العقد',0,NULL,@by),
 (@cid,'ACT-1011','تفاوضي','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1006' AND company_id=@cid LIMIT 1),'التفاوض النهائي على أسعار الطن','2026-06-24',12,'الاتفاق النهائي والفوز بالعقد',1,NULL,@by),
 (@cid,'ACT-1012','زيارة عميل','client',3,'زيارة لمناقشة مناقصة قادمة','2026-07-04',70,'الحصول على تفاصيل المناقصة',0,NULL,@by);

-- ── 6) المخاطر التجارية (10) — polymorphic على فرص/عقود
INSERT INTO commercial_risks
 (company_id, risk_code, name, risk_type, severity, mitigation, owner_user_id, state, entity_type, entity_id, notes, created_by)
VALUES
 (@cid,'RSK-1001','خطر توقف المعدات في موقع بعيد','تشغيل','عالية','جدولة صيانة استباقية وتأمين حفارات احتياطية',13,'مفتوح','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1001' AND company_id=@cid LIMIT 1),NULL,@by),
 (@cid,'RSK-1002','نقص التمويل لتعبئة المشروع','تمويل','عالية','ترتيب تمويل أو ضمان بنكي قبل الاعتماد',56,'تحت المعالجة','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1004' AND company_id=@cid LIMIT 1),NULL,@by),
 (@cid,'RSK-1003','تأخر التحصيل من العميل','تحصيل','متوسطة','دفعة مقدمة وضمان بنكي وجدول تحصيل',56,'مفتوح','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1010' AND company_id=@cid LIMIT 1),NULL,@by),
 (@cid,'RSK-1004','الاعتماد على مورد وحيد للآليات','موردون','متوسطة','التعاقد مع مورد بديل احتياطي',5,'تحت المعالجة','contract',6,NULL,@by),
 (@cid,'RSK-1005','صعوبة الوصول للموقع في موسم الأمطار','موقع','منخفضة','خطة وصول بديلة وتخزين مسبق للوقود',13,'مفتوح','contract',7,NULL,@by),
 (@cid,'RSK-1006','عدم وضوح نطاق العمل','عميل','متوسطة','توثيق النطاق والاتفاق كتابياً قبل التوقيع',13,'مفتوح','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1002' AND company_id=@cid LIMIT 1),NULL,@by),
 (@cid,'RSK-1007','عدم جاهزية الخرّامات للوتيرة المطلوبة','تشغيل','عالية','—',13,'مغلق','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1007' AND company_id=@cid LIMIT 1),'أُغلق بخسارة المناقصة',@by),
 (@cid,'RSK-1008','تراكم مستحقات غير محصّلة','تحصيل','عالية','متابعة أسبوعية مع مالية العميل',56,'تحت المعالجة','contract',8,NULL,@by),
 (@cid,'RSK-1009','ذروة الطلب على القلابات','تشغيل','متوسطة','تأمين قلابات احتياطية من الموردين',6,'مفتوح','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1006' AND company_id=@cid LIMIT 1),NULL,@by),
 (@cid,'RSK-1010','تصاريح دخول الموقع','موقع','منخفضة','تنسيق مبكر مع الجهات المحلية',70,'مفتوح','opportunity',(SELECT id FROM opportunities WHERE opp_code='OPP-1011' AND company_id=@cid LIMIT 1),NULL,@by);

-- ══════════════════════════════════════════════════════════════════════════════
-- ROLLBACK (نفّذ فقط عند الطلب):
--   DELETE FROM commercial_risks WHERE company_id=4 AND risk_code LIKE 'RSK-10%';
--   DELETE FROM activities       WHERE company_id=4 AND activity_code LIKE 'ACT-10%';
--   DELETE FROM quotations       WHERE company_id=4 AND quotation_code LIKE 'QUO-10%';
--   DELETE FROM tenders          WHERE company_id=4 AND tender_code LIKE 'TND-10%';
--   DELETE FROM pricelists       WHERE company_id=4 AND pricelist_code LIKE 'PL-10%';
--   DELETE FROM opportunities    WHERE company_id=4 AND opp_code LIKE 'OPP-10%';
-- ══════════════════════════════════════════════════════════════════════════════
