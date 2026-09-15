-- ==========================================================
-- Child Vaccination Management System
-- Database: child_vaccination
-- ==========================================================

CREATE DATABASE IF NOT EXISTS child_vaccination;
USE child_vaccination;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS vaccination_records;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS hospital_vaccines;
DROP TABLE IF EXISTS vaccines;
DROP TABLE IF EXISTS children;
DROP TABLE IF EXISTS hospitals;
DROP TABLE IF EXISTS parents;
DROP TABLE IF EXISTS admins;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================
-- 1. ROLES TABLE
-- =========================================

CREATE TABLE roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO roles (role_id, role_name) VALUES
(1, 'Admin'),
(2, 'Parent'),
(3, 'Hospital');


-- =========================================
-- 2. ADMIN TABLE
-- =========================================

CREATE TABLE admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',

    FOREIGN KEY (role_id)
        REFERENCES roles(role_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

-- Default Admin Account: admin / admin123 (hashed + fallback supported in auth)
INSERT INTO admins (admin_id, role_id, name, email, username, password, status) VALUES
(1, 1, 'System Administrator', 'admin@vaccination.gov', 'admin', '$2y$10$tZ2zV9Q9x7Pwq1W8dK9lUOGB64KkK2.46sWq1p5w2u4bZ0R8K8Oeq', 'Active'),
(2, 1, 'Healthcare Supervisor', 'supervisor@vaccination.gov', 'supervisor', '$2y$10$tZ2zV9Q9x7Pwq1W8dK9lUOGB64KkK2.46sWq1p5w2u4bZ0R8K8Oeq', 'Active');


-- =========================================
-- 3. PARENT TABLE
-- =========================================

CREATE TABLE parents (
    parent_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    address TEXT,

    FOREIGN KEY (role_id)
        REFERENCES roles(role_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

INSERT INTO parents (parent_id, role_id, name, email, phone, username, password, address) VALUES
(1, 2, 'Robert Jenkins', 'robert.jenkins@example.com', '+1 (555) 234-5678', 'parent1', '$2y$10$tZ2zV9Q9x7Pwq1W8dK9lUOGB64KkK2.46sWq1p5w2u4bZ0R8K8Oeq', '742 Evergreen Terrace, Springfield, IL'),
(2, 2, 'Sophia Martinez', 'sophia.m@example.com', '+1 (555) 876-5432', 'parent2', '$2y$10$tZ2zV9Q9x7Pwq1W8dK9lUOGB64KkK2.46sWq1p5w2u4bZ0R8K8Oeq', '124 Conch Street, Chicago, IL'),
(3, 2, 'David Chen', 'david.chen@example.com', '+1 (555) 345-6789', 'parent3', '$2y$10$tZ2zV9Q9x7Pwq1W8dK9lUOGB64KkK2.46sWq1p5w2u4bZ0R8K8Oeq', '88 Maple Ave, Evanston, IL');


-- =========================================
-- 4. HOSPITAL TABLE
-- =========================================

CREATE TABLE hospitals (
    hospital_id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    hospital_name VARCHAR(150) NOT NULL,
    address TEXT,
    location VARCHAR(150),
    phone VARCHAR(20),
    email VARCHAR(100) UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    status ENUM('Active', 'Inactive', 'Pending', 'Rejected') DEFAULT 'Pending',

    FOREIGN KEY (role_id)
        REFERENCES roles(role_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

INSERT INTO hospitals (hospital_id, role_id, hospital_name, address, location, phone, email, username, password, status) VALUES
(1, 3, 'City Children General Hospital', '100 Medical Center Blvd, Suite 400', 'Downtown Springfield', '+1 (555) 100-2000', 'info@citychildrenhospital.org', 'hospital1', '$2y$10$tZ2zV9Q9x7Pwq1W8dK9lUOGB64KkK2.46sWq1p5w2u4bZ0R8K8Oeq', 'Active'),
(2, 3, 'St. Jude Pediatric Healthcare Center', '550 Health Science Drive', 'North District', '+1 (555) 200-3000', 'contact@stjudepediatrics.org', 'hospital2', '$2y$10$tZ2zV9Q9x7Pwq1W8dK9lUOGB64KkK2.46sWq1p5w2u4bZ0R8K8Oeq', 'Active'),
(3, 3, 'Community Child Wellness Clinic', '78 Oakwood Road, West Wing', 'West Suburbs', '+1 (555) 300-4000', 'support@communitywellness.org', 'hospital3', '$2y$10$tZ2zV9Q9x7Pwq1W8dK9lUOGB64KkK2.46sWq1p5w2u4bZ0R8K8Oeq', 'Active'),
(4, 3, 'Metro Maternal & Child Clinic', '900 Grand River Ave', 'Metro Central', '+1 (555) 400-5000', 'contact@metromaternal.org', 'hospital4', '$2y$10$tZ2zV9Q9x7Pwq1W8dK9lUOGB64KkK2.46sWq1p5w2u4bZ0R8K8Oeq', 'Inactive');


-- =========================================
-- 5. CHILD TABLE
-- =========================================

CREATE TABLE children (
    child_id INT AUTO_INCREMENT PRIMARY KEY,
    parent_id INT NOT NULL,
    child_name VARCHAR(100) NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    date_of_birth DATE NOT NULL,
    address TEXT,
    notes TEXT,

    FOREIGN KEY (parent_id)
        REFERENCES parents(parent_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

INSERT INTO children (child_id, parent_id, child_name, gender, date_of_birth, address, notes) VALUES
(1, 1, 'Liam Jenkins', 'Male', '2025-08-15', '742 Evergreen Terrace, Springfield, IL', 'No known allergies. Normal birth weight.'),
(2, 1, 'Emma Jenkins', 'Female', '2024-02-10', '742 Evergreen Terrace, Springfield, IL', 'Mild lactose sensitivity.'),
(3, 2, 'Lucas Martinez', 'Male', '2025-11-04', '124 Conch Street, Chicago, IL', 'Premature by 2 weeks. Monitored regularly.'),
(4, 3, 'Olivia Chen', 'Female', '2025-05-20', '88 Maple Ave, Evanston, IL', 'Healthy. Completed initial birth vaccines.');


-- =========================================
-- 6. VACCINE TABLE
-- =========================================

CREATE TABLE vaccines (
    vaccine_id INT AUTO_INCREMENT PRIMARY KEY,
    vaccine_name VARCHAR(150) NOT NULL,
    description TEXT,
    age_group VARCHAR(100),
    stock_status ENUM('Available', 'Unavailable') DEFAULT 'Available'
);

INSERT INTO vaccines (vaccine_id, vaccine_name, description, age_group, stock_status) VALUES
(1, 'BCG (Bacillus Calmette-Guérin)', 'Protects against Tuberculosis (TB) and related complications.', 'At Birth', 'Available'),
(2, 'Hepatitis B (Birth Dose - HepB-1)', 'First dose preventing mother-to-child transmission of Hepatitis B virus.', 'At Birth (within 24h)', 'Available'),
(3, 'OPV (Oral Polio Vaccine - Dose 0)', 'Protects against Poliovirus types causing infantile paralysis.', 'At Birth', 'Available'),
(4, 'Pentavalent Vaccine (Dose 1: DTP + HepB + Hib)', 'Combination vaccine protecting against Diphtheria, Tetanus, Pertussis, Hepatitis B and Haemophilus influenzae b.', '6 Weeks', 'Available'),
(5, 'Rotavirus Vaccine (RV-1)', 'Prevents severe rotaviral gastroenteritis and diarrheal dehydration.', '6 Weeks', 'Available'),
(6, 'Pneumococcal Conjugate (PCV-1)', 'Protects infants against Streptococcus pneumoniae bacteremia and pneumonia.', '6 Weeks', 'Available'),
(7, 'Pentavalent Vaccine (Dose 2)', 'Second booster dose for DTP + HepB + Hib immunization.', '10 Weeks', 'Available'),
(8, 'Pentavalent Vaccine (Dose 3)', 'Third primary dose for complete DTP + HepB + Hib protection.', '14 Weeks', 'Available'),
(9, 'Measles, Mumps & Rubella (MMR-1)', 'Provides long-lasting immunity against Measles, Mumps, and Rubella infections.', '9 Months', 'Available'),
(10, 'Inactivated Polio Vaccine (IPV Booster)', 'Injectable polio vaccine boosting circulating antibodies.', '9 Months', 'Available'),
(11, 'Japanese Encephalitis (JE-1)', 'Protection against mosquito-borne encephalitis in designated zones.', '12 Months', 'Unavailable'),
(12, 'DTP Booster-1 (Diphtheria, Tetanus, Pertussis)', 'Reinforces childhood immunity against whooping cough, lockjaw, and diphtheria.', '16-24 Months', 'Available');


-- =========================================
-- 6B. HOSPITAL VACCINE INVENTORY TABLE
-- =========================================

CREATE TABLE hospital_vaccines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,
    vaccine_id INT NOT NULL,
    stock_status ENUM('Available', 'Unavailable') DEFAULT 'Available',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_hospital_vaccine (hospital_id, vaccine_id),
    FOREIGN KEY (hospital_id)
        REFERENCES hospitals(hospital_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    FOREIGN KEY (vaccine_id)
        REFERENCES vaccines(vaccine_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

-- Seed hospital-specific vaccine availability
INSERT INTO hospital_vaccines (hospital_id, vaccine_id, stock_status) VALUES
-- Hospital 1 (City Children General Hospital)
(1, 1, 'Available'), (1, 2, 'Available'), (1, 3, 'Available'), (1, 4, 'Available'),
(1, 5, 'Available'), (1, 6, 'Available'), (1, 7, 'Available'), (1, 8, 'Available'),
(1, 9, 'Available'), (1, 10, 'Available'), (1, 11, 'Unavailable'), (1, 12, 'Available'),
-- Hospital 2 (St. Jude Pediatric Healthcare Center)
(2, 1, 'Available'), (2, 2, 'Available'), (2, 3, 'Available'), (2, 4, 'Available'),
(2, 5, 'Available'), (2, 6, 'Available'), (2, 7, 'Available'), (2, 8, 'Available'),
(2, 9, 'Available'), (2, 10, 'Available'), (2, 11, 'Available'), (2, 12, 'Available'),
-- Hospital 3 (Community Child Wellness Clinic)
(3, 1, 'Available'), (3, 2, 'Available'), (3, 3, 'Available'), (3, 4, 'Available'),
(3, 5, 'Available'), (3, 6, 'Available'), (3, 7, 'Available'), (3, 8, 'Available'),
(3, 9, 'Available'), (3, 10, 'Available'), (3, 11, 'Unavailable'), (3, 12, 'Available');


-- =========================================
-- 7. BOOKING / APPOINTMENT TABLE
-- =========================================

CREATE TABLE bookings (
    booking_id INT AUTO_INCREMENT PRIMARY KEY,

    child_id INT NOT NULL,
    hospital_id INT NOT NULL,
    vaccine_id INT NOT NULL,
    admin_id INT NULL,

    booking_date DATE NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NULL,

    status ENUM(
        'Pending',
        'Approved',
        'Rejected',
        'Completed'
    ) DEFAULT 'Pending',

    approval_date DATE NULL,
    hospital_notes TEXT NULL,

    FOREIGN KEY (child_id)
        REFERENCES children(child_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    FOREIGN KEY (hospital_id)
        REFERENCES hospitals(hospital_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    FOREIGN KEY (vaccine_id)
        REFERENCES vaccines(vaccine_id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (admin_id)
        REFERENCES admins(admin_id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
);

INSERT INTO bookings (booking_id, child_id, hospital_id, vaccine_id, admin_id, booking_date, appointment_date, appointment_time, status, approval_date, hospital_notes) VALUES
-- Completed bookings (with vaccination records)
(1, 1, 1, 1, 1, '2025-08-16', '2025-08-18', '09:30:00', 'Completed', '2025-08-16', 'Vaccination completed smoothly.'),
(2, 1, 1, 2, 1, '2025-08-16', '2025-08-18', '10:00:00', 'Completed', '2025-08-16', 'Dose administered.'),
(3, 4, 2, 1, 1, '2025-05-21', '2025-05-23', '11:15:00', 'Completed', '2025-05-21', 'All clear.'),
(4, 2, 1, 4, 1, '2024-03-25', '2024-03-28', '10:30:00', 'Completed', '2024-03-25', 'Child in good health.'),
(5, 2, 1, 9, 1, '2024-11-10', '2024-11-15', '09:00:00', 'Completed', '2024-11-10', 'Vaccine administered.'),

-- Approved / Upcoming Bookings
(6, 1, 1, 4, 1, '2026-09-01', '2026-09-08', '10:00:00', 'Approved', '2026-09-01', 'Please bring child health booklet.'),
(7, 3, 2, 4, 1, '2026-09-01', '2026-09-12', '11:00:00', 'Approved', '2026-09-01', 'Scheduled in Pediatric Ward 2.'),
(8, 4, 3, 9, 1, '2026-09-01', '2026-09-18', '14:30:00', 'Approved', '2026-09-01', 'Afternoon slot confirmed.'),

-- Pending Requests from Parents (Awaiting Hospital Approval & Schedule)
(9, 1, 2, 5, NULL, '2026-09-01', '2026-09-15', NULL, 'Pending', NULL, NULL),
(10, 3, 1, 5, NULL, '2026-09-01', '2026-09-20', NULL, 'Pending', NULL, NULL),
(11, 2, 3, 12, NULL, '2026-09-01', '2026-09-25', NULL, 'Pending', NULL, NULL),

-- Rejected Request
(12, 3, 4, 11, 1, '2026-08-28', '2026-09-02', NULL, 'Rejected', '2026-08-29', 'Vaccine temporarily unavailable at this location.');


-- =========================================
-- 8. VACCINATION RECORD TABLE
-- =========================================

CREATE TABLE vaccination_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,

    booking_id INT NOT NULL,
    child_id INT NOT NULL,

    vaccination_date DATE NOT NULL,

    status ENUM(
        'Vaccinated',
        'Not Vaccinated'
    ) NOT NULL,

    remarks TEXT,

    FOREIGN KEY (booking_id)
        REFERENCES bookings(booking_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    FOREIGN KEY (child_id)
        REFERENCES children(child_id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    UNIQUE (booking_id)
);

INSERT INTO vaccination_records (record_id, booking_id, child_id, vaccination_date, status, remarks) VALUES
(1, 1, 1, '2025-08-18', 'Vaccinated', 'Administered left upper arm 0.05ml. Batch #BCG-9921.'),
(2, 2, 1, '2025-08-18', 'Vaccinated', 'Administered anterolateral thigh 0.5ml. Batch #HEP-4412.'),
(3, 3, 4, '2025-05-23', 'Vaccinated', 'Administered smoothly, no adverse reaction observed. Batch #BCG-8819.'),
(4, 4, 2, '2024-03-28', 'Vaccinated', 'First Pentavalent dose given. Batch #PENT-1102.'),
(5, 5, 2, '2024-11-15', 'Vaccinated', 'MMR-1 administered right upper arm. Batch #MMR-6031.');
