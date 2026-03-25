USE hospital_db;

-- Add missing columns to patients table
ALTER TABLE patients 
    ADD COLUMN IF NOT EXISTS discharge_date DATE DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS room_id INT DEFAULT NULL;

-- Add rooms table if it doesn't exist
CREATE TABLE IF NOT EXISTS rooms (
    room_id       INT AUTO_INCREMENT PRIMARY KEY,
    room_number   VARCHAR(10)  NOT NULL UNIQUE,
    room_type     ENUM('General','Private','ICU','Emergency') NOT NULL DEFAULT 'General',
    status        ENUM('Available','Occupied') NOT NULL DEFAULT 'Available',
    price_per_day DECIMAL(10,2) NOT NULL DEFAULT 500.00
);

-- Add bills table if it doesn't exist
CREATE TABLE IF NOT EXISTS bills (
    bill_id          INT AUTO_INCREMENT PRIMARY KEY,
    patient_id       INT NOT NULL,
    room_charges     DECIMAL(10,2) DEFAULT 0.00,
    medicine_charges DECIMAL(10,2) DEFAULT 0.00,
    doctor_charges   DECIMAL(10,2) DEFAULT 0.00,
    other_charges    DECIMAL(10,2) DEFAULT 0.00,
    total_amount     DECIMAL(10,2) DEFAULT 0.00,
    payment_status   ENUM('Pending','Paid') DEFAULT 'Pending',
    bill_date        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
);

-- Add FK for room_id (ignore error if already exists)
ALTER TABLE patients
    ADD FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL;

-- Insert sample rooms (ignore duplicates)
INSERT IGNORE INTO rooms (room_number, room_type, status, price_per_day) VALUES
('101', 'General',   'Available', 500.00),
('102', 'General',   'Available', 500.00),
('103', 'General',   'Available', 500.00),
('201', 'Private',   'Available', 1500.00),
('202', 'Private',   'Available', 1500.00),
('203', 'Private',   'Available', 1500.00),
('301', 'ICU',       'Available', 5000.00),
('302', 'ICU',       'Available', 5000.00),
('401', 'Emergency', 'Available', 3000.00),
('402', 'Emergency', 'Available', 3000.00);
