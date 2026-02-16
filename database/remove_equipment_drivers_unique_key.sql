-- إزالة القيد الفريد من جدول equipment_drivers للسماح بتكرار الربط مع تواريخ مختلفة
ALTER TABLE equipment_drivers DROP INDEX equipment_id;
