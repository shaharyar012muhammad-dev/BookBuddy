<?php
/**
 * BookBuddy Complete Database Seeder & Schema Initializer
 * Designed for local development and zero-config Railway/Cloud deployment.
 */

// Retry logic to wait for database connectivity
$max_attempts = 30;
$attempt = 0;
$connected = false;

while ($attempt < $max_attempts && !$connected) {
    try {
        require_once __DIR__ . "/config/config.php";
        $connected = true;
    } catch (PDOException $e) {
        $attempt++;
        if ($attempt < $max_attempts) {
            echo "Database connection attempt $attempt/$max_attempts failed. Retrying in 2 seconds...\n";
            sleep(2);
        } else {
            die("Failed to connect to database after $max_attempts attempts. Error: " . htmlspecialchars($e->getMessage()));
        }
    }
}

echo "Database connected successfully after $attempt attempts\n";

// Section 1: Ensure Database Schema Exists
$schemaStatements = [
    "CREATE TABLE IF NOT EXISTS user (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fullname VARCHAR(50) NOT NULL,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        profileImage VARCHAR(255) DEFAULT NULL,
        role ENUM('user', 'admin') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(50) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS book (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        author VARCHAR(255) NOT NULL,
        ISBN VARCHAR(20) DEFAULT NULL,
        publishDate DATE DEFAULT NULL,
        Publisher VARCHAR(255) NOT NULL,
        Original_Price INT NOT NULL,
        Discount_Percentage INT DEFAULT 0,
        Discount_Price INT AS (
            ROUND(Original_Price * (100 - IFNULL(Discount_Percentage, 0)) / 100)
        ) STORED,
        Stock INT NOT NULL DEFAULT 0,
        description_para_1 TEXT NOT NULL,
        description_para_2 TEXT DEFAULT NULL,
        coverImage VARCHAR(255) NOT NULL,
        category_id INT DEFAULT NULL,
        totalSales INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS deals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        book_id INT NOT NULL,
        discount_percentage INT NOT NULL,
        start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        end_time TIMESTAMP GENERATED ALWAYS AS (DATE_ADD(start_time, INTERVAL 24 HOUR)),
        status ENUM('active', 'processing', 'expired') DEFAULT 'active',
        lock_token VARCHAR(64) DEFAULT NULL,
        processed_at DATETIME DEFAULT NULL,
        FOREIGN KEY (book_id) REFERENCES book(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        body TEXT NOT NULL,
        book_id INT NOT NULL,
        user_id INT NOT NULL,
        ratings INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_book_review (user_id, book_id),
        FOREIGN KEY (book_id) REFERENCES book(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS cart (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        book_id INT NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user_cart_item (user_id, book_id),
        FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
        FOREIGN KEY (book_id) REFERENCES book(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS user_address (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        province VARCHAR(50) NOT NULL,
        district VARCHAR(100) DEFAULT NULL,
        city VARCHAR(100) NOT NULL,
        postcode VARCHAR(20) NOT NULL,
        address TEXT NOT NULL,
        contact VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        total_amount DECIMAL(10, 2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL DEFAULT 'Cash on Delivery',
        payment_status ENUM('pending', 'paid', 'completed', 'failed') NOT NULL DEFAULT 'pending',
        order_status ENUM('processing', 'shipped', 'delivered', 'cancelled', 'returned') NOT NULL DEFAULT 'processing',
        shipping_address_id INT DEFAULT NULL,
        shipping_address_text TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE CASCADE,
        FOREIGN KEY (shipping_address_id) REFERENCES user_address(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        book_id INT DEFAULT NULL,
        book_title VARCHAR(255) DEFAULT NULL,
        quantity INT NOT NULL,
        price_at_purchase DECIMAL(10, 2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (book_id) REFERENCES book(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

foreach ($schemaStatements as $sql) {
    $connection->exec($sql);
}

// Section 2: Seed Default Admin & Customer Accounts
$defaultUsers = [
    [
        "fullname" => "AliAulakh",
        "username" => "aliraza1",
        "email" => "muhammadshaharyaraulakh@gmail.com",
        "password" => '$2y$12$p0pKKnA9gSsgmvbHE.4A5enFVLSd5VH9o4SXU1lvV.XYuEpI.UB2y',
        "role" => "admin"
    ],
    [
        "fullname" => "AliRazaJutt",
        "username" => "aliraza123",
        "email" => "shaharyaraulakh@gmail.com",
        "password" => '$2y$12$2sH3eGv0YyE0Hl2u9rZ3jO6o8P6sD4p2Q1l0W8m7K5j4H3g2F1e0a',
        "role" => "user"
    ]
];

$userCheckStmt = $connection->prepare("SELECT id FROM user WHERE username = :username OR email = :email LIMIT 1");
$userInsertStmt = $connection->prepare("
    INSERT INTO user (fullname, username, email, password, role) 
    VALUES (:fullname, :username, :email, :password, :role)
");

foreach ($defaultUsers as $u) {
    $userCheckStmt->execute([":username" => $u["username"], ":email" => $u["email"]]);
    if (!$userCheckStmt->fetch()) {
        $userInsertStmt->execute([
            ":fullname" => $u["fullname"],
            ":username" => $u["username"],
            ":email" => $u["email"],
            ":password" => $u["password"],
            ":role" => $u["role"]
        ]);
    }
}

echo "Database seeding completed successfully\n";
?>

