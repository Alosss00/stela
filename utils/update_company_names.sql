-- Update Company Names: Remove dots after PT
-- Script untuk mengubah semua nama perusahaan dari "PT." menjadi "PT"

-- Update employees table
UPDATE employees SET contractor_company = REPLACE(contractor_company, 'PT. ', 'PT ') WHERE contractor_company LIKE 'PT. %';
UPDATE employees SET ruang_lingkup = REPLACE(ruang_lingkup, 'PT. ', 'PT ') WHERE ruang_lingkup LIKE 'PT. %';
UPDATE employees SET supervision_area = REPLACE(supervision_area, 'PT. ', 'PT ') WHERE supervision_area LIKE 'PT. %';

-- Update users table
UPDATE users SET full_name = REPLACE(full_name, 'PT. ', 'PT ') WHERE full_name LIKE 'PT. %';
UPDATE users SET department = REPLACE(department, 'PT. ', 'PT ') WHERE department LIKE 'PT. %';
