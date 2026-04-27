-- ============================================================
-- Trip.com Flight Booking System — Database Schema
-- Per data dictionary. System extensions noted in comments.
-- DB: FlightBookingDB  |  root / (empty) for XAMPP default
-- Demo: admin@trip.com / password | user@trip.com / password
-- ============================================================

DROP DATABASE IF EXISTS FlightBookingDB;
CREATE DATABASE FlightBookingDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE FlightBookingDB;

-- ── User ─────────────────────────────────────────────────────
CREATE TABLE User (
    User_ID           INT AUTO_INCREMENT PRIMARY KEY,
    User_Name         VARCHAR(100) NOT NULL,
    User_Email        VARCHAR(100) NOT NULL UNIQUE,
    User_Password     VARCHAR(255) NOT NULL,   -- extended from VARCHAR(15) for bcrypt
    User_PhoneNo      VARCHAR(20),
    User_Nationality  VARCHAR(50),
    User_DOB          DATE,
    User_Loyalty      INT          DEFAULT 0,
    User_Status       ENUM('ACTIVE','SUSPENDED') DEFAULT 'ACTIVE',
    User_Registration DATETIME     DEFAULT NOW(),
    User_Role         ENUM('user','admin') DEFAULT 'user'  -- system extension
);

-- ── Airliner ─────────────────────────────────────────────────
CREATE TABLE Airliner (
    Airln_ID      INT AUTO_INCREMENT PRIMARY KEY,
    Airln_Name    VARCHAR(100) NOT NULL,
    Airln_Code    VARCHAR(5)   NOT NULL,
    Airln_Country VARCHAR(80),
    Airln_Contact VARCHAR(20),
    Airln_Email   VARCHAR(100),
    Airln_Status  ENUM('ACTIVE','INACTIVE') DEFAULT 'ACTIVE'
);

-- ── Flight ───────────────────────────────────────────────────
CREATE TABLE Flight (
    Flght_ID         INT AUTO_INCREMENT PRIMARY KEY,
    Flght_AirlnID    INT           NOT NULL,
    Flght_No         VARCHAR(10)   NOT NULL,
    Flght_Depart     CHAR(3)       NOT NULL,  -- IATA departure code
    Flght_Arrival    CHAR(3)       NOT NULL,  -- IATA arrival code
    Flght_DepartDate DATETIME      NOT NULL,
    Flght_ArriveDate DATETIME      NOT NULL,
    Flght_SeatAvail  INT           NOT NULL DEFAULT 180,
    Flght_Fare       DECIMAL(10,2) NOT NULL,  -- base economy fare
    Flght_TotalSeats INT           NOT NULL DEFAULT 180,  -- system extension
    Flght_Status     ENUM('SCHEDULED','CANCELLED','COMPLETED') DEFAULT 'SCHEDULED',  -- system extension
    FOREIGN KEY (Flght_AirlnID) REFERENCES Airliner(Airln_ID)
);

-- ── Promotion ────────────────────────────────────────────────
CREATE TABLE Promotion (
    Promo_ID           INT AUTO_INCREMENT PRIMARY KEY,
    Promo_Code         VARCHAR(20)   NOT NULL UNIQUE,
    Promo_DiscountType ENUM('PERCENTAGE','FIXED') NOT NULL,
    Promo_Value        DECIMAL(10,2) NOT NULL,
    Promo_ValidFrom    DATETIME      NOT NULL,
    Promo_ValidTo      DATETIME      NOT NULL,
    Promo_Usage        INT           DEFAULT 0
);

-- ── Booking ──────────────────────────────────────────────────
CREATE TABLE Booking (
    Book_ID      INT AUTO_INCREMENT PRIMARY KEY,
    Book_UserID  INT           NOT NULL,
    Book_Date    DATETIME      DEFAULT NOW(),
    Book_Status  ENUM('PENDING','CONFIRMED','CANCELLED') DEFAULT 'PENDING',
    Book_Total   DECIMAL(10,2) NOT NULL,
    Book_Pay     ENUM('PAID','UNPAID','REFUNDED') DEFAULT 'UNPAID',
    Book_Confirm VARCHAR(20)   NOT NULL UNIQUE,
    Book_PromoID INT           NULL,
    FOREIGN KEY (Book_UserID)  REFERENCES User(User_ID),
    FOREIGN KEY (Book_PromoID) REFERENCES Promotion(Promo_ID)
);

-- ── Bookingdetails ───────────────────────────────────────────
CREATE TABLE Bookingdetails (
    Bokde_ID        INT AUTO_INCREMENT PRIMARY KEY,
    Bokde_BookID    INT           NOT NULL,
    Bokde_FlghtID   INT           NOT NULL,
    Bokde_Passenger VARCHAR(100)  NOT NULL,
    Bokde_SeatClass ENUM('ECONOMY','BUSINESS','FIRST') DEFAULT 'ECONOMY',
    Bokde_SeatNo    VARCHAR(10)   NULL,
    Bokde_Ticket    DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (Bokde_BookID)  REFERENCES Booking(Book_ID),
    FOREIGN KEY (Bokde_FlghtID) REFERENCES Flight(Flght_ID)
);

-- ── Payment ──────────────────────────────────────────────────
CREATE TABLE Payment (
    Paymt_ID          INT AUTO_INCREMENT PRIMARY KEY,
    Paymt_BookID      INT           NOT NULL,
    Paymt_Method      VARCHAR(50)   NOT NULL,
    Paymt_Date        DATETIME      DEFAULT NOW(),
    Paymt_Amt         DECIMAL(10,2) NOT NULL,
    Paymt_Status      ENUM('SUCCESS','FAILED','REFUNDED') DEFAULT 'SUCCESS',
    Paymt_Transaction VARCHAR(50)   NOT NULL,
    FOREIGN KEY (Paymt_BookID) REFERENCES Booking(Book_ID)
);

-- ── Review ───────────────────────────────────────────────────
CREATE TABLE Review (
    Reviw_ID      INT AUTO_INCREMENT PRIMARY KEY,
    Reviw_UserID  INT     NOT NULL,
    Reviw_BookID  INT     NOT NULL,
    Reviw_Rate    TINYINT NOT NULL,
    Reviw_Comment TEXT,
    Reviw_Date    DATETIME DEFAULT NOW(),
    CONSTRAINT chk_rate CHECK (Reviw_Rate BETWEEN 1 AND 5),
    FOREIGN KEY (Reviw_UserID) REFERENCES User(User_ID),
    FOREIGN KEY (Reviw_BookID) REFERENCES Booking(Book_ID)
);

-- ── SupportTicket ────────────────────────────────────────────
CREATE TABLE SupportTicket (
    Tickt_ID               INT AUTO_INCREMENT PRIMARY KEY,
    Tickt_UserID           INT         NOT NULL,
    Tickt_BookID           INT         NULL,
    Tickt_IssueType        VARCHAR(80) NOT NULL,
    Tickt_IssueDescription TEXT,
    Tickt_Status           ENUM('OPEN','IN_PROGRESS','RESOLVED','CLOSED') DEFAULT 'OPEN',
    Tickt_CreatedDate      DATETIME DEFAULT NOW(),
    FOREIGN KEY (Tickt_UserID) REFERENCES User(User_ID),
    FOREIGN KEY (Tickt_BookID) REFERENCES Booking(Book_ID)
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Users (password = 'password' — bcrypt hash)
INSERT INTO User (User_Name, User_Email, User_Password, User_PhoneNo, User_Nationality, User_Loyalty, User_Status, User_Registration, User_Role) VALUES
('Admin Trip',    'admin@trip.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+63 2 8888 0000', 'Filipino', 0, 'ACTIVE', NOW(), 'admin'),
('Juan Dela Cruz','user@trip.com',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+63 9 1234 5678', 'Filipino', 0, 'ACTIVE', NOW(), 'user');

-- Airlines
INSERT INTO Airliner (Airln_Name, Airln_Code, Airln_Country, Airln_Contact, Airln_Email, Airln_Status) VALUES
('Philippine Airlines', 'PR', 'Philippines', '+63 2 8855 8888', 'reservations@philippineairlines.com', 'ACTIVE'),
('Cebu Pacific',        '5J', 'Philippines', '+63 2 8702 0888', 'support@cebupacificair.com',           'ACTIVE'),
('Singapore Airlines',  'SQ', 'Singapore',   '+65 6223 8888',   'customercare@singaporeair.com',        'ACTIVE'),
('Emirates',            'EK', 'UAE',          '+971 600 555 555','contactus@emirates.com',               'ACTIVE'),
('Cathay Pacific',      'CX', 'Hong Kong',   '+852 2747 1888',  'customerrelations@cathaypacific.com',  'ACTIVE');

-- Promotions
INSERT INTO Promotion (Promo_Code, Promo_DiscountType, Promo_Value, Promo_ValidFrom, Promo_ValidTo, Promo_Usage) VALUES
('TRIP10',  'PERCENTAGE', 10.00, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 0),
('SUMMER500','FIXED',    500.00, '2026-05-01 00:00:00', '2026-06-30 23:59:59', 0),
('FLYPH20',  'PERCENTAGE', 20.00, '2026-05-01 00:00:00', '2026-05-31 23:59:59', 0);

-- Flights (Flght_Fare = base economy fare; Business = ×2.5, First = ×4 applied at booking)
-- IDs: PR=1, 5J=2, SQ=3, EK=4, CX=5
INSERT INTO Flight (Flght_AirlnID, Flght_No, Flght_Depart, Flght_Arrival, Flght_DepartDate, Flght_ArriveDate, Flght_SeatAvail, Flght_Fare, Flght_TotalSeats) VALUES
-- Manila → Singapore
(1, 'PR501', 'MNL', 'SIN', '2026-05-01 08:00:00', '2026-05-01 11:30:00',  120,  8500.00, 180),
(2, '5J301', 'MNL', 'SIN', '2026-05-01 14:00:00', '2026-05-01 17:30:00',  150,  6500.00, 180),
(3, 'SQ721', 'MNL', 'SIN', '2026-05-01 18:00:00', '2026-05-01 21:30:00',   60, 12000.00, 250),
(1, 'PR503', 'MNL', 'SIN', '2026-05-05 09:00:00', '2026-05-05 12:30:00',   90,  8800.00, 180),
(2, '5J303', 'MNL', 'SIN', '2026-05-05 15:00:00', '2026-05-05 18:30:00',  130,  6800.00, 180),
(1, 'PR505', 'MNL', 'SIN', '2026-05-10 08:00:00', '2026-05-10 11:30:00',  100,  9000.00, 180),
(3, 'SQ723', 'MNL', 'SIN', '2026-05-10 20:00:00', '2026-05-10 23:30:00',   70, 13000.00, 250),
(1, 'PR507', 'MNL', 'SIN', '2026-05-15 08:00:00', '2026-05-15 11:30:00',  140,  8500.00, 180),
(2, '5J305', 'MNL', 'SIN', '2026-05-15 13:00:00', '2026-05-15 16:30:00',  160,  6200.00, 180),
(1, 'PR509', 'MNL', 'SIN', '2026-05-20 08:00:00', '2026-05-20 11:30:00',  110,  8500.00, 180),
-- Manila → Dubai
(4, 'EK361', 'MNL', 'DXB', '2026-05-01 22:00:00', '2026-05-02 04:00:00',  200, 28000.00, 300),
(1, 'PR201', 'MNL', 'DXB', '2026-05-05 23:00:00', '2026-05-06 05:00:00',  180, 26000.00, 300),
(4, 'EK363', 'MNL', 'DXB', '2026-05-10 22:00:00', '2026-05-11 04:00:00',  170, 29000.00, 300),
(1, 'PR203', 'MNL', 'DXB', '2026-05-15 23:00:00', '2026-05-16 05:00:00',  160, 27000.00, 300),
-- Manila → Tokyo
(1, 'PR431', 'MNL', 'NRT', '2026-05-01 10:00:00', '2026-05-01 15:00:00',  100, 18000.00, 180),
(2, '5J801', 'MNL', 'NRT', '2026-05-01 08:00:00', '2026-05-01 13:00:00',   90, 15000.00, 180),
(1, 'PR433', 'MNL', 'NRT', '2026-05-05 10:00:00', '2026-05-05 15:00:00',  120, 19000.00, 180),
(1, 'PR435', 'MNL', 'NRT', '2026-05-10 10:00:00', '2026-05-10 15:00:00',  140, 18500.00, 180),
(2, '5J803', 'MNL', 'NRT', '2026-05-15 08:00:00', '2026-05-15 13:00:00',  100, 15500.00, 180),
-- Manila → Hong Kong
(5, 'CX903', 'MNL', 'HKG', '2026-05-01 07:00:00', '2026-05-01 09:30:00',  110,  9500.00, 180),
(1, 'PR701', 'MNL', 'HKG', '2026-05-01 12:00:00', '2026-05-01 14:30:00',   95,  8800.00, 180),
(5, 'CX905', 'MNL', 'HKG', '2026-05-05 07:00:00', '2026-05-05 09:30:00',  130,  9800.00, 180),
(1, 'PR703', 'MNL', 'HKG', '2026-05-10 12:00:00', '2026-05-10 14:30:00',  100,  9000.00, 180),
-- Cebu → Manila
(1, 'PR811', 'CEB', 'MNL', '2026-05-01 06:00:00', '2026-05-01 07:10:00',  160,  2500.00, 180),
(2, '5J101', 'CEB', 'MNL', '2026-05-01 08:00:00', '2026-05-01 09:10:00',  140,  2200.00, 180),
(1, 'PR813', 'CEB', 'MNL', '2026-05-05 10:00:00', '2026-05-05 11:10:00',  130,  2500.00, 180),
(2, '5J103', 'CEB', 'MNL', '2026-05-10 14:00:00', '2026-05-10 15:10:00',  150,  2100.00, 180),
-- Singapore → Tokyo
(3, 'SQ631', 'SIN', 'NRT', '2026-05-01 09:00:00', '2026-05-01 16:30:00',   80, 22000.00, 250),
(3, 'SQ633', 'SIN', 'NRT', '2026-05-05 11:00:00', '2026-05-05 18:30:00',   70, 23000.00, 250),
-- Bangkok → Singapore
(3, 'SQ733', 'BKK', 'SIN', '2026-05-01 10:00:00', '2026-05-01 13:30:00',  120,  9000.00, 200),
(3, 'SQ735', 'BKK', 'SIN', '2026-05-05 12:00:00', '2026-05-05 15:30:00',  100,  9500.00, 200),
-- Hong Kong → Singapore
(5, 'CX731', 'HKG', 'SIN', '2026-05-02 11:00:00', '2026-05-02 14:30:00',   90, 11000.00, 200),
-- Singapore → London
(3, 'SQ307', 'SIN', 'LHR', '2026-05-01 23:00:00', '2026-05-02 06:00:00',   60, 55000.00, 300),
-- Dubai → London
(4, 'EK001', 'DXB', 'LHR', '2026-05-02 08:00:00', '2026-05-02 12:30:00',  150, 42000.00, 400),
-- Manila → Bangkok
(1, 'PR601', 'MNL', 'BKK', '2026-05-01 09:00:00', '2026-05-01 11:30:00',  120, 10500.00, 180),
(2, '5J401', 'MNL', 'BKK', '2026-05-05 10:00:00', '2026-05-05 12:30:00',  140,  8800.00, 180),
-- Manila → Kuala Lumpur
(1, 'PR651', 'MNL', 'KUL', '2026-05-01 07:00:00', '2026-05-01 10:00:00',  110,  7500.00, 180),
(2, '5J451', 'MNL', 'KUL', '2026-05-05 08:00:00', '2026-05-05 11:00:00',  150,  5800.00, 180),
-- June flights
(1, 'PR511', 'MNL', 'SIN', '2026-06-01 08:00:00', '2026-06-01 11:30:00',  150,  8200.00, 180),
(2, '5J311', 'MNL', 'SIN', '2026-06-01 14:00:00', '2026-06-01 17:30:00',  160,  6000.00, 180),
(4, 'EK371', 'MNL', 'DXB', '2026-06-01 22:00:00', '2026-06-02 04:00:00',  190, 27000.00, 300),
(1, 'PR441', 'MNL', 'NRT', '2026-06-05 10:00:00', '2026-06-05 15:00:00',  120, 17500.00, 180),
(5, 'CX911', 'MNL', 'HKG', '2026-06-05 07:00:00', '2026-06-05 09:30:00',  130,  9200.00, 180);
