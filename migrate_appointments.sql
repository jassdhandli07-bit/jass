USE hospital_db;

CREATE TABLE IF NOT EXISTS appointments (
    appointment_id  INT AUTO_INCREMENT PRIMARY KEY,
    patient_name    VARCHAR(100) NOT NULL,
    patient_phone   VARCHAR(20)  NOT NULL,
    patient_age     INT          DEFAULT NULL,
    patient_gender  ENUM('Male','Female','Other') DEFAULT 'Male',
    doctor_id       INT          NOT NULL,
    appointment_date DATE        NOT NULL,
    appointment_time TIME        NOT NULL,
    reason          VARCHAR(255) DEFAULT NULL,
    status          ENUM('Scheduled','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
    notes           TEXT         DEFAULT NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(doctor_id) ON DELETE CASCADE
);
