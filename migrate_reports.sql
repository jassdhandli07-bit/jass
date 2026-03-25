USE hospital_db;

-- Create patient_reports table with charge column
CREATE TABLE IF NOT EXISTS patient_reports (
    report_id    INT AUTO_INCREMENT PRIMARY KEY,
    patient_id   INT NOT NULL,
    report_type  ENUM('X-Ray','Blood Test','MRI Scan','CT Scan','Urine Test','ECG','Ultrasound','Other') NOT NULL,
    report_title VARCHAR(200) NOT NULL,
    description  TEXT DEFAULT NULL,
    charge       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    file_path    VARCHAR(300) DEFAULT NULL,
    file_name    VARCHAR(200) DEFAULT NULL,
    report_date  DATE NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
);

-- If table already exists, just add the charge column
ALTER TABLE patient_reports ADD COLUMN IF NOT EXISTS charge DECIMAL(10,2) NOT NULL DEFAULT 0.00;
