USE hospital_db;

-- Step 1: Check current table structure
DESCRIBE patients;

-- Step 2: Fix the assigned_doctor_id column to properly allow NULL with no default
ALTER TABLE patients 
    MODIFY COLUMN assigned_doctor_id INT DEFAULT NULL;

-- Step 3: Drop any triggers that might be auto-assigning doctors
DROP TRIGGER IF EXISTS before_patient_insert;
DROP TRIGGER IF EXISTS after_patient_insert;
DROP TRIGGER IF EXISTS before_patient_update;
DROP TRIGGER IF EXISTS after_patient_update;

-- Step 4: Verify - show all patients and their assigned_doctor_id
SELECT patient_id, name, assigned_doctor_id FROM patients;
