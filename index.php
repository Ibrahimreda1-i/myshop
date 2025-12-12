<?php
session_start();

// --- 1. SETUP & DATABASE (Automatic) ---
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'school_shop_db';

// Connect to MySQL
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) die("Connection Failed: " . $conn->connect_error);

// Create DB & Tables automatically
$conn->query("CREATE DATABASE IF NOT EXISTS $dbname");
$conn->select_db($dbname);

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(10) DEFAULT 'user'
)");

$conn->query("CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(50) NOT NULL,
    image VARCHAR(255) NOT NULL
)");

// Create Admin Account if missing
$check = $conn->query("SELECT * FROM users WHERE email='admin@store.com'");
if($check->num_rows == 0) {
    // Note: In real apps, use password_hash()
    $conn->query("INSERT INTO users (email, password, role) VALUES ('admin@store.com', 'admin123', 'admin')");
    // Add some dummy products
    $conn->query("INSERT INTO products (name, price, category, image) VALUES 
    ('Pro Laptop', 999.99, 'Electronics', 'https://placehold.co/300x200/2c3e50/white?text=Laptop'),
    ('Cool T-Shirt', 25.00, 'Fashion', 'https://placehold.co/300x200/27ae60/white?text=Shirt'),
    ('Coffee Mug', 12.50, 'Home', 'https://placehold.co/300x200/f1c40f/white?text=Mug'),
    ('Headphones', 59.99, 'Electronics', 'https://placehold.co/300x200/e74c3c/white?text=Audio')");
}

// --- 2. CONTROLLER (Logic) ---

// Handle Login
if (isset($_POST['login'])) {
    $email = $_POST['email'];
    $pwd = $_POST['password'];
    $res = $conn->query("SELECT * FROM users WHERE email='$email' AND password='$pwd'");
    
    if ($res->num_rows > 0) {
        $_SESSION['user'] = $res->fetch_assoc();
        header("Location: index.php"); exit();
    } else {
        $error = "Incorrect Email or Password.";
    }
}

// Handle Register (With Validation)
if (isset($_POST['register'])) {
    $email = $_POST['email'];
    $pwd = $_POST['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid Email Format.";
    } elseif (strlen($pwd) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $check = $conn->query("SELECT * FROM users WHERE email='$email'");
        if ($check->num_rows > 0) {
            $error = "Email already exists.";
        } else {
            $conn->query("INSERT INTO users (email, password) VALUES ('$email', '$pwd')");
            echo "<script>alert('Account Created! Please Login.'); window.location='index.php?page=login';</script>";
        }
    }
}

// Handle Add to Cart
if (isset($_POST['add_cart'])) {
    if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
    $_SESSION['cart'][] = ['name' => $_POST['name'], 'price' => $_POST['price']];
    header("Location: index.php"); exit();
}

// Handle Checkout
if (isset($_POST['checkout'])) {
    if (!isset($_SESSION['user'])) {
        echo "<script>alert('Please Login to Checkout.'); window.location='index.php?page=login';</script>";
    } else {
        $_SESSION['cart'] = [];
        echo "<script>alert('Thank you for your purchase!'); window.location='index.php';</script>";
    }
}

// Handle Admin Actions
if (isset($_POST['add_product'])) {
    $conn->query("INSERT INTO products (name, price, category, image) VALUES ('{$_POST['name']}', '{$_POST['price']}', '{$_POST['category']}', '{$_POST['image']}')");
    header("Location: index.php?page=admin"); exit();
}

if (isset($_GET['delete']) && isset($_SESSION['user']) && $_SESSION['user']['role'] == 'admin') {
    $conn->query("DELETE FROM products WHERE id=" . $_GET['delete']);
    header("Location: index.php?page=admin"); exit();
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php"); exit();
}

// Page Routing
$page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>School Shop Project</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- NAVBAR -->
    <nav>
        <div class="nav-content">
            <a href="index.php" class="brand">🛍 Fashion store </a>
            <div class="nav-links">
                <a href="index.php">Shop</a>
                <a href="index.php?page=cart">Cart (<?php echo isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0; ?>)</a>
                
                <?php if(isset($_SESSION['user'])): ?>
                    <?php if($_SESSION['user']['role'] == 'admin'): ?>
                        <a href="index.php?page=admin" style="color: #f1c40f;">Admin</a>
                    <?php endif; ?>
                    <a href="index.php?logout=true" style="color: #ff6b6b;">Logout</a>
                <?php else: ?>
                    <a href="index.php?page=login">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container">

        <!-- --- HOME PAGE --- -->
        <?php if($page == 'home'): ?>
            <?php
                // Filter Logic
                $search = $_GET['search'] ?? '';
                $cat = $_GET['cat'] ?? 'All';
                
                $sql = "SELECT * FROM products WHERE name LIKE '%$search%'";
                if($cat != 'All') $sql .= " AND category='$cat'";
                $result = $conn->query($sql);
                
                // Get Categories for Chips
                $cats = $conn->query("SELECT DISTINCT category FROM products");
            ?>

            <!-- Search Bar -->
            <div class="search-wrapper">
                <form class="search-form" method="GET">
                    <input type="text" name="search" placeholder="Search for products..." value="<?php echo $search; ?>">
                    <button type="submit">Search</button>
                </form>
            </div>

            <!-- Category Chips -->
            <div class="category-scroll">
                <a href="index.php" class="cat-chip <?php echo $cat=='All'?'active':''; ?>">All Categories</a>
                <?php while($c = $cats->fetch_assoc()): ?>
                    <a href="index.php?cat=<?php echo $c['category']; ?>" 
                       class="cat-chip <?php echo $cat==$c['category']?'active':''; ?>">
                       <?php echo $c['category']; ?>
                    </a>
                <?php endwhile; ?>
            </div>

            <!-- Product Grid -->
            <div class="grid">
                <?php if($result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <div class="card">
                            <img src="<?php echo $row['image']; ?>" alt="Product">
                            <div class="card-body">
                                <span class="card-category"><?php echo $row['category']; ?></span>
                                <h3><?php echo $row['name']; ?></h3>
                                <span class="price">$<?php echo $row['price']; ?></span>
                                <form method="POST">
                                    <input type="hidden" name="name" value="<?php echo $row['name']; ?>">
                                    <input type="hidden" name="price" value="<?php echo $row['price']; ?>">
                                    <button type="submit" name="add_cart" class="btn-add">Add to Cart</button>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p>No products found.</p>
                <?php endif; ?>
            </div>

        <!-- --- LOGIN PAGE --- -->
        <?php elseif($page == 'login'): ?>
            <div class="form-box">
                <h2>Login</h2>
                <?php if(isset($error)) echo "<div class='error-msg'>$error</div>"; ?>
                <form method="POST">
                    <input type="email" name="email" placeholder="Email Address" required>
                    <input type="password" name="password" placeholder="Password" required>
                    <button type="submit" name="login">Login</button>
                </form>
                <p style="margin-top: 15px; font-size: 0.9rem;">
                    Don't have an account? <a href="index.php?page=register">Register</a>
                </p>
            </div>

        <!-- --- REGISTER PAGE --- -->
        <?php elseif($page == 'register'): ?>
            <div class="form-box">
                <h2>Create Account</h2>
                <?php if(isset($error)) echo "<div class='error-msg'>$error</div>"; ?>
                <form method="POST">
                    <input type="text" name="uaername" placeholder="Enter Your Your Name" required><br>                    
                    <input type="email" name="email" placeholder="Enter Your Email Address" required>
                    <input type="password" name="password" placeholder="Enter Your Password (Min 6 chars)" required>
                    
                    <button type="submit" name="register" style="background: var(--accent);">Sign Up</button>
                </form>
                <p style="margin-top: 15px; font-size: 0.9rem;">
                    Already have an account? <a href="index.php?page=login">Login</a>
                </p>
            </div>

        <!-- --- CART PAGE --- -->
        <?php elseif($page == 'cart'): ?>
            <div class="container" style="background:white; padding:30px; border-radius:10px;">
                <h2 style="margin-bottom:20px;">Shopping Cart</h2>
                <?php if(!empty($_SESSION['cart'])): ?>
                    <table>
                        <tr><th>Product</th><th>Price</th></tr>
                        <?php 
                        $total = 0;
                        foreach($_SESSION['cart'] as $item): 
                            $total += $item['price'];
                        ?>
                        <tr>
                            <td><?php echo $item['name']; ?></td>
                            <td>$<?php echo $item['price']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="font-weight:bold; background:#fff;">
                            <td style="text-align:right; padding-top:20px;">Total:</td>
                            <td style="padding-top:20px; font-size:1.2rem; color: var(--accent);">$<?php echo number_format($total, 2); ?></td>
                        </tr>
                    </table>
                    <form method="POST" style="text-align:right; margin-top:20px;">
                        <button type="submit" name="checkout" style="padding:12px 30px; background:var(--accent); color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold;">Checkout</button>
                    </form>
                <?php else: ?>
                    <p style="color:#777;">Your cart is empty.</p>
                    <a href="index.php" style="color:var(--primary); font-weight:bold;">Go Shopping</a>
                <?php endif; ?>
            </div>

        <!-- --- ADMIN PAGE --- -->
        <?php elseif($page == 'admin'): ?>
            <?php if(!isset($_SESSION['user']) || $_SESSION['user']['role'] != 'admin') die("Access Denied"); ?>
            
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px;">
                <!-- Add Form -->
                <div style="background: white; padding: 25px; border-radius: 10px; height: fit-content; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <h3 style="margin-top:0;">Add Product</h3>
                    <form method="POST">
                        <input type="text" name="name" placeholder="Product Name" style="width:100%; padding:10px; margin:5px 0; border:1px solid #ddd; border-radius:5px;" required>
                        <input type="number" step="0.01" name="price" placeholder="Price" style="width:100%; padding:10px; margin:5px 0; border:1px solid #ddd; border-radius:5px;" required>
                        <select name="category" style="width:100%; padding:10px; margin:5px 0; border:1px solid #ddd; border-radius:5px;">
                            <option>Electronics</option>
                            <option>Fashion</option>
                            <option>Home</option>
                            <option>Books</option>
                        </select>
                        <input type="text" name="image" placeholder="Image URL" style="width:100%; padding:10px; margin:5px 0; border:1px solid #ddd; border-radius:5px;" required>
                        <button type="submit" name="add_product" style="width:100%; padding:10px; margin-top:10px; background:var(--accent); color:white; border:none; border-radius:5px; cursor:pointer;">Add Item</button>
                    </form>
                </div>

                <!-- List -->
                <div style="background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                    <h3 style="margin-top:0;">Inventory</h3>
                    <table>
                        <tr><th>Name</th><th>Category</th><th>Price</th><th>Action</th></tr>
                        <?php 
                        $res = $conn->query("SELECT * FROM products ORDER BY id DESC");
                        while($r = $res->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $r['name']; ?></td>
                            <td><?php echo $r['category']; ?></td>
                            <td>$<?php echo $r['price']; ?></td>
                            <td><a href="index.php?delete=<?php echo $r['id']; ?>" class="btn-danger" onclick="return confirm('Are you sure?')">Delete</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>
</body>
</html>