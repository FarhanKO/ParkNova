# 🚀 XAMPP Setup Instructions

To run this project on your local computer using XAMPP, follow these steps:

### 1. Copy Files

Copy all files from this project into your XAMPP installation folder:
`C:\xampp\htdocs\parking_system\`

### 2. Start XAMPP

Open the **XAMPP Control Panel** and Start:

- **Apache**
- **MySQL**

### 3. Create Database

1. Go to `http://localhost/phpmyadmin/`
2. Click **New** and create a database named `parknova_db`.
3. Click on the database, then go to the **SQL** tab.
4. Open the `database.sql` file from this project, copy the code inside, paste it into the phpMyAdmin SQL box, and click **Go**.

### 4. Open Application

Go to your browser and type:
`http://localhost/ParkNova/index.php`

---

### Default Login Credentials:

- **Username:** `admin`
- **Password:** `admin123`

### Notes for Teacher:

- Built with **PHP 8 + PDO** (Secured against SQL injection).
- Uses **Tailwind CSS** via CDN for a modern look without external CSS files.
- **MySQL Architecture:** 13 interconnected AI-focused tables ensuring complete referential integrity.
