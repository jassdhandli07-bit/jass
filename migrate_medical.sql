USE hospital_db;

CREATE TABLE IF NOT EXISTS patient_medications (
    med_id      INT AUTO_INCREMENT PRIMARY KEY,
    patient_id  INT NOT NULL,
    med_type    ENUM('Medicine','Injection','Glucose','IV Fluid','Oxygen','Vaccine','Other') NOT NULL DEFAULT 'Medicine',
    med_name    VARCHAR(200) NOT NULL,
    dosage      VARCHAR(100) DEFAULT NULL,
    frequency   VARCHAR(100) DEFAULT NULL,
    start_date  DATE DEFAULT NULL,
    end_date    DATE DEFAULT NULL,
    charge      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    notes       TEXT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
);

ALTER TABLE patient_reports ADD COLUMN IF NOT EXISTS charge DECIMAL(10,2) NOT NULL DEFAULT 0.00;
