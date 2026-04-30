# 🎓 Secure Student Management System (PHP & MySQL)

![PHP](https://img.shields.io/badge/PHP-8.x-blue?logo=php)
![MySQL](https://img.shields.io/badge/MySQL-Database-orange?logo=mysql)
![Security](https://img.shields.io/badge/Security-CSRF%20%7C%20XSS%20%7C%20SQL--Injection-success)
![Architecture](https://img.shields.io/badge/Architecture-Structured%20PHP-informational)
![Status](https://img.shields.io/badge/Status-Stable-brightgreen)
![License](https://img.shields.io/badge/License-Academic-lightgrey)

A **secure and structured web application** for managing student records using **PHP (PDO)** and **MySQL**.

This project demonstrates **best practices in backend development**, including secure database interaction, input validation, and protection against common web vulnerabilities.

---

# 📌 Project Overview

The **Secure Student Management System** is a CRUD-based web application that allows users to:

* Manage student data efficiently
* Perform secure database operations
* Apply real-world web security practices

This project is designed as a **final-year academic project**, emphasizing **clean code, security, and maintainability**.

---

# ✨ Key Features

### 📋 Core Functionality

* Add new students
* View all students
* Update student information
* Delete student records

### 🔐 Security Features

* Prepared statements using PDO (SQL Injection prevention)
* CSRF token protection for all forms
* XSS protection using output escaping
* Server-side validation for all inputs

### 🧠 Best Practices

* Separation of concerns
* Reusable helper functions
* Clean error handling
* Secure session management

---

# 🏗️ Project Architecture

```txt
TP_CRUD_en_PHP_MySQL/
│
├── connexion.php     # Database connection (PDO)
├── index.php         # Display students list
├── ajouter.php       # Add student
├── modifier.php      # Edit student
├── supprimer.php     # Delete student
│
└── README.md
```

---

# ⚙️ Technology Stack

| Layer    | Technology           |
| -------- | -------------------- |
| Backend  | PHP (PDO)            |
| Database | MySQL                |
| Server   | Apache (XAMPP)       |
| Security | CSRF, XSS Protection |

---

# 🔐 Security Implementation

This project integrates multiple layers of security:

### ✔ SQL Injection Protection

All database queries use **prepared statements (PDO)**:

```php
$stmt = $pdo->prepare("INSERT INTO etudiants (...) VALUES (...)");
```

### ✔ CSRF Protection

Each form includes a **secure CSRF token**:

```php
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
```

### ✔ XSS Protection

All outputs are sanitized using:

```php
htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
```

### ✔ Input Validation

* Length validation
* Regex filtering
* Email validation (`filter_var`)

---

# 🧪 How It Works

### 🔹 Add Student

* Validates input
* Checks CSRF token
* Inserts data securely into database 

### 🔹 Display Students

* Fetches data using PDO
* Displays in structured HTML table 

### 🔹 Update Student

* Retrieves student by ID
* Updates securely using prepared statements 

### 🔹 Delete Student

* Confirmation step
* CSRF validation
* Secure deletion query 

---

# 🚀 Installation Guide

### 1. Clone the repository

```bash
git clone https://github.com/KHALIDMRJ/gestion_etudiants.git
```

### 2. Move project to XAMPP

```txt
C:\xampp\htdocs\
```

### 3. Start Apache & MySQL

### 4. Create database

```sql
CREATE DATABASE gestion_etudiants;

USE gestion_etudiants;

CREATE TABLE etudiants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    email VARCHAR(150),
    filieres VARCHAR(100)
);
```

### 5. Run the project

```
http://localhost/TP_CRUD_en_PHP_MySQL/index.php
```

---

# 📸 Screenshots 

```md
![Home](screenshots/home.PNG)
![Add](screenshots/add.PNG)
```

---

# 📊 Future Improvements

* Modern UI (Bootstrap / Tailwind)
* Search & filtering system
* Pagination
* Authentication system
* REST API version

---

# 👨‍💻 Author

**Khalid MORJAN**

* AI & Data Student
* Passionate about Software Engineering & Web Security

---

# 🏆 Project Value

This project demonstrates:

✔ Secure backend development
✔ Understanding of web vulnerabilities
✔ Clean and maintainable PHP code
✔ Professional GitHub structure

---

# 📄 License

This project is developed for **academic purposes**.
