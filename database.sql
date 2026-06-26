-- ============================================
-- LigarayPacina Final Output - Database Schema
-- CSIT226: Information Management 1
-- ============================================

CREATE DATABASE IF NOT EXISTS db_ligaraypacina;
USE db_ligaraypacina;

-- USER table (base entity)
CREATE TABLE IF NOT EXISTS tbluser (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    Institutional_Email VARCHAR(150) NOT NULL UNIQUE,
    FirstName VARCHAR(50) NOT NULL,
    MiddleName VARCHAR(50) NULL,
    LastName VARCHAR(50) NOT NULL,
    isActive TINYINT(1) DEFAULT 1,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    isOrganization TINYINT(1) DEFAULT 0,
    isStudent TINYINT(1) DEFAULT 0,
    Username VARCHAR(100) NOT NULL UNIQUE,
    Password VARCHAR(255) NOT NULL,
    Role ENUM('admin','student','organization') DEFAULT 'student'
);

-- STUDENT table
CREATE TABLE IF NOT EXISTS tblstudent (
    StudentID VARCHAR(11) PRIMARY KEY,
    Program VARCHAR(100) NOT NULL,
    YearLevel TINYINT NOT NULL,
    Department VARCHAR(150) NOT NULL,
    UserID INT NOT NULL,
    FOREIGN KEY (UserID) REFERENCES tbluser(UserID) ON DELETE CASCADE
);

-- ORGANIZATION table
CREATE TABLE IF NOT EXISTS tblorganization (
    OrgID INT AUTO_INCREMENT PRIMARY KEY,
    OrgName VARCHAR(150) NOT NULL,
    Type VARCHAR(50) NULL,
    Accreditation_Status TINYINT(1) DEFAULT 1,
    Contact_Email VARCHAR(150) NULL,
    UserID INT NOT NULL,
    FOREIGN KEY (UserID) REFERENCES tbluser(UserID) ON DELETE CASCADE
);

-- ITEM table
CREATE TABLE IF NOT EXISTS tblitem (
    ItemID INT AUTO_INCREMENT PRIMARY KEY,
    Item_Name VARCHAR(150) NOT NULL,
    Description TEXT NULL,
    Category VARCHAR(50) NULL,
    Image VARCHAR(255) NULL,
    Status ENUM('Good','Damaged','Under Repair','Lost') DEFAULT 'Good',
    Availability_Status ENUM('Available','Borrowed','Reserved','Archived') DEFAULT 'Available',
    OwnerUserID INT NOT NULL,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ArchivedAt DATETIME NULL,
    FOREIGN KEY (OwnerUserID) REFERENCES tbluser(UserID) ON DELETE CASCADE
);

-- AVAILABILITY SLOT table
CREATE TABLE IF NOT EXISTS tblavailabilityslot (
    SlotID INT AUTO_INCREMENT PRIMARY KEY,
    ItemID INT NOT NULL,
    Start_DateTime DATETIME NOT NULL,
    End_DateTime DATETIME NOT NULL,
    isReserved TINYINT(1) DEFAULT 0,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ItemID) REFERENCES tblitem(ItemID) ON DELETE CASCADE
);

-- BOOKING table
CREATE TABLE IF NOT EXISTS tblbooking (
    BookingID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    SlotID INT NOT NULL,
    Booking_DateTime DATETIME DEFAULT CURRENT_TIMESTAMP,
    Status ENUM('Pending','Confirmed','Cancelled','Completed') DEFAULT 'Pending',
    Description TEXT NULL,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES tbluser(UserID),
    FOREIGN KEY (SlotID) REFERENCES tblavailabilityslot(SlotID)
);

-- BORROW REQUEST table
CREATE TABLE IF NOT EXISTS tblborrowrequest (
    RequestID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    BookingID INT NOT NULL,
    Requested_Start DATETIME NOT NULL,
    Requested_End DATETIME NOT NULL,
    Status ENUM('Pending','Approved','Rejected','Cancelled','Completed') DEFAULT 'Pending',
    ReviewedAt DATETIME NULL,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES tbluser(UserID),
    FOREIGN KEY (BookingID) REFERENCES tblbooking(BookingID)
);

-- BORROW TRANSACTION table
CREATE TABLE IF NOT EXISTS tblborrowtransaction (
    TransactionID INT AUTO_INCREMENT PRIMARY KEY,
    RequestID INT NOT NULL,
    Release_DateTime DATETIME NOT NULL,
    ExpectedReturn_DateTime DATETIME NOT NULL,
    ActualReturn_DateTime DATETIME NULL,
    Condition_On_Release ENUM('Good','Fair','Damaged') DEFAULT 'Good',
    Condition_On_Return ENUM('Good','Fair','Damaged','Lost') NULL,
    Status ENUM('Active','Returned','Overdue','Lost') DEFAULT 'Active',
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ClosedAt DATETIME NULL,
    FOREIGN KEY (RequestID) REFERENCES tblborrowrequest(RequestID)
);

-- CANCEL TRANSACTION table
CREATE TABLE IF NOT EXISTS tblcanceltransaction (
    CancelID INT AUTO_INCREMENT PRIMARY KEY,
    RequestID INT NOT NULL,
    UserID INT NOT NULL,
    Cancelled_DateTime DATETIME NOT NULL,
    Reason TEXT NULL,
    Cancelled_By ENUM('Student','Organization') NOT NULL,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (RequestID) REFERENCES tblborrowrequest(RequestID),
    FOREIGN KEY (UserID) REFERENCES tbluser(UserID)
);

-- Default admin account (password: admin123)
INSERT INTO tbluser (Institutional_Email, FirstName, LastName, isActive, isOrganization, isStudent, Username, Password, Role)
VALUES (
    'admin@cit.edu',
    'Admin',
    'User',
    1, 0, 0,
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'admin'
);

-- Notification table
CREATE TABLE IF NOT EXISTS tblnotification (
    NotifID INT AUTO_INCREMENT PRIMARY KEY,
    UserID INT NOT NULL,
    Message VARCHAR(500) NOT NULL,
    Link VARCHAR(255) NULL,
    is_read TINYINT(1) DEFAULT 0,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (UserID) REFERENCES tbluser(UserID) ON DELETE CASCADE
);
