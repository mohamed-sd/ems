ALTER TABLE equipments
ADD COLUMN machine_number VARCHAR(100) NULL COMMENT 'رقم الماكينة أو المحرك' AFTER chassis_number,
ADD COLUMN document_type VARCHAR(100) NULL COMMENT 'نوع الوثيقة' AFTER license_authority,
ADD COLUMN site_supervisor_name VARCHAR(200) NULL COMMENT 'اسم المهندس أو المشرف في الموقع' AFTER current_location,
ADD COLUMN site_supervisor_contact VARCHAR(200) NULL COMMENT 'بيانات الاتصال بالمشرف في الموقع' AFTER site_supervisor_name,
ADD COLUMN availability_state VARCHAR(20) NOT NULL DEFAULT 'متوفرة' COMMENT 'التوفر: متوفرة أو غير متوفرة' AFTER site_supervisor_contact;

UPDATE equipments
SET availability_state = CASE
    WHEN availability_status IS NULL OR availability_status = '' OR availability_status IN ('متاحة للعمل', 'قيد الاستخدام') THEN 'متوفرة'
    ELSE 'غير متوفرة'
END,
availability_status = CASE
    WHEN availability_status IS NULL OR availability_status = '' OR availability_status IN ('متاحة للعمل', 'قيد الاستخدام') THEN 'قيد الاستخدام'
    WHEN availability_status = 'موقوفة للصيانة' THEN 'تحت الصيانة'
    WHEN availability_status = 'مبيعة/مسحوبة' THEN 'مسحوبة'
    WHEN availability_status = 'معطلة مؤقتاً' THEN 'معطلة'
    ELSE availability_status
END;