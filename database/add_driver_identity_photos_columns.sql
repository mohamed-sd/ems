-- Add driver and identity photo columns to drivers table safely
-- This statement only adds the columns if they do not already exist.

ALTER TABLE drivers
  ADD COLUMN IF NOT EXISTS driver_photo varchar(255) DEFAULT NULL COMMENT 'مسار صورة السائق (تجهيزي)',
  ADD COLUMN IF NOT EXISTS identity_photo varchar(255) DEFAULT NULL COMMENT 'مسار صورة الهوية (تجهيزي)';
