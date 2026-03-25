-- Hospital Management System Database Schema
-- Re-import this in phpMyAdmin to apply new tables

CREATE DATABASE IF NOT EXISTS hospital_db;
USE hospital_db;

-- Drop in FK order
DROP TABLE IF EXISTS bills;
DROP TABLE IF EXISTS patients;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS doctors;

-- Doctors
CREATE TABLE doctors (
    doctor_id       INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    specialization  VARCHAR(100) NOT NULL,
    license_no      VARCHAR(50)  NOT NULL UNIQUE,
    contact         VARCHAR(20)  NOT NULL,
    password        VARCHAR(255) NOT NULL,
    photo_path      VARCHAR(255) DEFAULT 'images/ram.jpg',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Rooms
CREATE TABLE rooms (
    room_id     INT AUTO_INCREMENT PRIMARY KEY,
    room_number VARCHAR(10)  NOT NULL UNIQUE,
    room_type   ENUM('General','Private','ICU','Emergency') NOT NULL DEFAULT 'General',
    status      ENUM('Available','Occupied') NOT NULL DEFAULT 'Available',
    price_per_day DECIMAL(10,2) NOT NULL DEFAULT 500.00
);

-- Patients
CREATE TABLE patients (
    patient_id          INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(100) NOT NULL,
    age                 INT NOT NULL,
    gender              ENUM('Male','Female','Other') NOT NULL,
    disease             VARCHAR(150) DEFAULT NULL,
    treatment           TEXT DEFAULT NULL,
    admission_date      DATE DEFAULT NULL,
    discharge_date      DATE DEFAULT NULL,
    assigned_doctor_id  INT DEFAULT NULL,
    room_id             INT DEFAULT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_doctor_id) REFERENCES doctors(doctor_id) ON DELETE SET NULL,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE SET NULL
);

-- Bills
CREATE TABLE bills (
    bill_id         INT AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT NOT NULL,
    room_charges    DECIMAL(10,2) DEFAULT 0.00,
    medicine_charges DECIMAL(10,2) DEFAULT 0.00,
    doctor_charges  DECIMAL(10,2) DEFAULT 0.00,
    other_charges   DECIMAL(10,2) DEFAULT 0.00,
    total_amount    DECIMAL(10,2) DEFAULT 0.00,
    payment_status  ENUM('Pending','Paid') DEFAULT 'Pending',
    bill_date       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
);

-- Sample doctors
INSERT INTO doctors (name, specialization, license_no, contact, password, photo_path) VALUES
('Dr. John Smith', 'Cardiology',  'LIC-001', '9876543210', 'pass123', 'images/ram.jpg'),
('Dr. Sarah Khan', 'Neurology',   'LIC-002', '9876543211', 'pass123', 'images/ram.jpg'),
('Dr. Raj Patel',  'Orthopedics', 'LIC-003', '9876543212', 'pass123', 'images/ram.jpg');

-- Sample rooms (10 rooms)
INSERT INTO rooms (room_number, room_type, status, price_per_day) VALUES
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

-- Sample patients
INSERT INTO patients (name, age, gender, disease, treatment, admission_date, assigned_doctor_id, room_id) VALUES
('Alice Johnson', 34, 'Female', 'Hypertension',   'Beta blockers prescribed',   '2026-03-01', 1, 1),
('Bob Williams',  52, 'Male',   'Diabetes Type 2','Insulin therapy',            '2026-03-05', 2, 4),
('Carol Davis',   28, 'Female', 'Fracture - Leg', 'Cast applied, rest advised', '2026-03-10', 3, 2);

-- Mark those rooms as occupied
UPDATE rooms SET status='Occupied' WHERE room_id IN (1, 2, 4);
