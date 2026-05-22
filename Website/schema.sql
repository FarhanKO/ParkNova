-- Parking Intelligence AI - SQL Schema for MySQL / phpMyAdmin
-- Create the database if it doesn't already exist
CREATE DATABASE IF NOT EXISTS parknova_db;

-- Select the database so the subsequent tables are created inside it
USE parknova_db;


-- 1. Create Independent Tables First

CREATE TABLE User (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    password VARCHAR(255) NOT NULL,
    username VARCHAR(50) UNIQUE,
    user_type ENUM('Admin', 'Customer', 'Manager', 'Staff') NOT NULL, 
    activity_frequency DECIMAL(5,2) DEFAULT 0.00 -- AI derived frequency metric
);

CREATE TABLE Vehicle_Category (
    cat_id INT PRIMARY KEY AUTO_INCREMENT,
    cat_type VARCHAR(50) NOT NULL
);

CREATE TABLE Parking_Area (
    zone_id INT PRIMARY KEY AUTO_INCREMENT,
    zone_name VARCHAR(100) NOT NULL,
    capacity INT NOT NULL
);

-- 2. Create Tables with 1st Level Dependencies

CREATE TABLE Slot (
    slot_id INT PRIMARY KEY AUTO_INCREMENT,
    zone_id INT,
    floor_level INT,
    occupancy BOOLEAN DEFAULT FALSE,
    slot_type ENUM('Standard', 'Emergency', 'VIP') DEFAULT 'Standard',
    available_predict DECIMAL(5,2), -- Prediction metric
    FOREIGN KEY (zone_id) REFERENCES Parking_Area(zone_id) ON DELETE CASCADE
);

CREATE TABLE Vehicles (
    license_plate VARCHAR(20) PRIMARY KEY,
    user_id INT,
    cat_id INT,
    reg_status VARCHAR(50),
    vehicle_type ENUM('Standard', 'Emergency', 'VIP') DEFAULT 'Standard',
    penalty_score INT DEFAULT 0, -- Warning for any violation
    risk_score DECIMAL(5,2) DEFAULT 0.00, -- AI calculated risk
    FOREIGN KEY (user_id) REFERENCES User(user_id) ON DELETE CASCADE,
    FOREIGN KEY (cat_id) REFERENCES Vehicle_Category(cat_id) ON DELETE SET NULL
);

-- Mapping Table for Slot and Vehicle Category (M:N Catalog relationship)
CREATE TABLE Slot_Catalog (
    slot_id INT,
    cat_id INT,
    PRIMARY KEY (slot_id, cat_id),
    FOREIGN KEY (slot_id) REFERENCES Slot(slot_id) ON DELETE CASCADE,
    FOREIGN KEY (cat_id) REFERENCES Vehicle_Category(cat_id) ON DELETE CASCADE
);

-- 3. Create Tables with 2nd Level Dependencies

CREATE TABLE Session (
    session_id INT PRIMARY KEY AUTO_INCREMENT,
    license_plate VARCHAR(20),
    entry_time DATETIME NOT NULL,
    exit_time DATETIME,
    duration INT, -- Stored in minutes
    FOREIGN KEY (license_plate) REFERENCES Vehicles(license_plate) ON DELETE CASCADE
);

-- Mapping Table for Session and Slot (M:N Occupy relationship)
CREATE TABLE Session_Slot (
    session_id INT,
    slot_id INT,
    PRIMARY KEY (session_id, slot_id),
    FOREIGN KEY (session_id) REFERENCES Session(session_id) ON DELETE CASCADE,
    FOREIGN KEY (slot_id) REFERENCES Slot(slot_id) ON DELETE CASCADE
);

-- 4. Create Tables with 3rd Level Dependencies (Billing, Auditing, and Logs)

CREATE TABLE Payment (
    slip_id INT PRIMARY KEY AUTO_INCREMENT,
    session_id INT UNIQUE, -- 1:1 relationship with Session
    base_rate DECIMAL(10,2) NOT NULL,
    demand_multiply DECIMAL(5,2) DEFAULT 1.00,
    v_type_multiply DECIMAL(5,2) DEFAULT 1.00,
    peak_hour_charge DECIMAL(10,2) DEFAULT 0.00,
    loyalty_discount DECIMAL(10,2) DEFAULT 0.00,
    vat DECIMAL(10,2) DEFAULT 0.00,
    extra_fee DECIMAL(10,2) DEFAULT 0.00,
    final_fee DECIMAL(10,2),
    predicted_cost DECIMAL(10,2), -- AI based cost prediction
    suggestions TEXT, -- Fuel saving suggestions
    FOREIGN KEY (session_id) REFERENCES Session(session_id) ON DELETE CASCADE
);

CREATE TABLE Violations (
    violation_id INT PRIMARY KEY AUTO_INCREMENT,
    vehicleNumber VARCHAR(20),
    slip_id INT,
    violation_type VARCHAR(100) NOT NULL,
    status ENUM('pending', 'resolved') DEFAULT 'pending',
    distance_score DECIMAL(5,2), -- Related to speeding in free area
    FOREIGN KEY (vehicleNumber) REFERENCES Vehicles(license_plate) ON DELETE CASCADE,
    FOREIGN KEY (slip_id) REFERENCES Payment(slip_id) ON DELETE SET NULL
);

CREATE TABLE Blacklist (
    blacklist_id INT PRIMARY KEY AUTO_INCREMENT,
    violation_id INT UNIQUE, -- 1:1 relationship with Violations
    date_added DATETIME NOT NULL,
    reason TEXT NOT NULL,
    FOREIGN KEY (violation_id) REFERENCES Violations(violation_id) ON DELETE CASCADE
);

CREATE TABLE Review (
    review_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    text TEXT,
    submit_date DATETIME NOT NULL,
    sentiment_score DECIMAL(5,2), -- AI Sentiment Score
    fake_flag BOOLEAN DEFAULT FALSE, -- AI based fake review detection
    rating_score INT CHECK (rating_score BETWEEN 1 AND 5),
    FOREIGN KEY (user_id) REFERENCES User(user_id) ON DELETE CASCADE
);

CREATE TABLE SYS_ACT_Log (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    actions TEXT NOT NULL,
    anomaly_flag BOOLEAN DEFAULT FALSE, -- AI Based Anomaly Detection
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES User(user_id) ON DELETE CASCADE
);
