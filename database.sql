-- حذف الجداول القديمة
DROP TABLE IF EXISTS attendance;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS teachers;
DROP TABLE IF EXISTS admins;

-- جدول المديرين (للدخول)
CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول المعلمين (مع إضافة حقل الجنس)
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    gender ENUM('male', 'female') NOT NULL,   -- male = معلم, female = معلمة
    specialization VARCHAR(255),
    schedule TEXT,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- جدول الطلاب (مع إضافة حقل الفئة)
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    category ENUM('boy', 'girl', 'child') NOT NULL,  -- أولاد, بنات, أطفال
    birth_date DATE,
    teacher_id INT,
    parent_phone VARCHAR(20),
    level VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id)
);

-- جدول الحضور (للطلاب والمعلمين)
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    person_type ENUM('student', 'teacher') NOT NULL,
    person_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late') DEFAULT 'present',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_attendance (person_type, person_id, date)
);

-- إدخال حساب مدير افتراضي (admin / 123456)
INSERT INTO admins (username, password) VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'); -- كلمة المرور: 123456

-- إدخال بعض المعلمين تجريبياً
INSERT INTO teachers (name, gender, specialization, schedule, phone) VALUES
('أحمد محمد', 'male', 'القرآن والتجويد', 'السبت - الاثنين - الأربعاء 4:30 عصراً', '01234567890'),
('فاطمة علي', 'female', 'تحفيظ الأطفال', 'الأحد - الثلاثاء - الخميس 5:00 عصراً', '01234567891');