-- Kin Cafe SQL package for pre-oral (prepared 2026-09-10)
-- Includes schema + DUMMY operational data for Doc review.
-- Default logins: admin/admin123 , cashier/cashier123
-- Contents: users, menu, customers, ingredients, recipes,
--           ~30 days completed orders, inventory logs, anomaly spike day
-- Import tip: phpMyAdmin > Import this file (fresh DB).
-- If kin_cafe already exists, this script drops and recreates it.

-- Database schema for Kin Cafe Cashier System
-- Updated package reference: sql/kin_cafe_preoral_2026-09-10.sql (2026-09-10)

DROP DATABASE IF EXISTS kin_cafe;
CREATE DATABASE kin_cafe;
USE kin_cafe;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(100),
  role VARCHAR(20) NOT NULL DEFAULT 'supervisor',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password, email, role, is_active)
VALUES
('admin', '$2y$10$qrK17ZfHRHZ59qPlWFzrG.Eio9phNq/UzpxCm1rn64A2ptNtFg2FC', 'admin@kincafe.com', 'supervisor', 1),
('cashier', '$2y$10$npf4pLTAWNaqxWluba9KzuyUYrjD9OIrmz6HgVWZTIoDe5TvKMZ92', 'cashier@kincafe.com', 'cashier', 1);

CREATE TABLE settings (
  setting_key VARCHAR(100) PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO settings (setting_key, setting_value) VALUES
('tax_rate', '0.00'),
('loyalty_points_per_currency', '0.00'),
('backup_enabled', '1'),
('receipt_footer', 'Thank you for visiting Kin Cafe.');

CREATE TABLE menu_categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  parent_id INT NULL,
  category_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES menu_categories(id) ON DELETE SET NULL
);

INSERT INTO menu_categories (id, name, description, parent_id, category_order) VALUES
(6, 'Espresso Based', '', NULL, 0),
(7, 'Milk Based', '', NULL, 0),
(8, 'Matcha Series', '', NULL, 0),
(9, 'Chaofan Rice Meals', '', NULL, 0),
(10, 'Soda Based', '', NULL, 0),
(11, 'Add Ons', '', NULL, 0),
(12, 'Desserts', '', NULL, 0),
(13, 'Pasta', '', NULL, 0),
(14, 'Pizza', '', NULL, 0),
(15, 'Snacks', '', NULL, 0),
(16, 'Burgers', '', NULL, 0),
(17, 'Sandwiches', '', NULL, 0);

CREATE TABLE customers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NULL,
  phone VARCHAR(30) NULL,
  email VARCHAR(100) NULL,
  loyalty_points INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  contact VARCHAR(100),
  address TEXT
);

CREATE TABLE ingredients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  unit VARCHAR(50) DEFAULT 'pcs',
  stock_quantity DECIMAL(10,2) DEFAULT 0,
  manufacturing_date DATE NULL,
  expiration_date DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE menu_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL,
  price_solo DECIMAL(10,2) NULL,
  price_sharing DECIMAL(10,2) NULL,
  price_hot DECIMAL(10,2) NULL,
  price_iced DECIMAL(10,2) NULL,
  size_option_enabled TINYINT(1) NOT NULL DEFAULT 0,
  size_label_1 VARCHAR(30) NULL,
  size_label_2 VARCHAR(30) NULL,
  price_size_1 DECIMAL(10,2) NULL,
  price_size_2 DECIMAL(10,2) NULL,
  temperature VARCHAR(10) NULL,
  temperature_option_enabled TINYINT(1) NOT NULL DEFAULT 0,
  product_code VARCHAR(50) NULL,
  promo_price DECIMAL(10,2) NULL,
  promo_start DATETIME NULL,
  promo_end DATETIME NULL,
  tax_exempt TINYINT(1) NOT NULL DEFAULT 0,
  category_id INT,
  image VARCHAR(255),
  available BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES menu_categories(id) ON DELETE SET NULL
);

INSERT INTO menu_items (
  id, name, description, price, price_solo, price_sharing, price_hot, price_iced,
  size_option_enabled, size_label_1, size_label_2, price_size_1, price_size_2,
  temperature, temperature_option_enabled, product_code, promo_price, promo_start,
  promo_end, tax_exempt, category_id, image, available
) VALUES
(5, 'Caramel Latte', NULL, 119.00, NULL, NULL, 119.00, 129.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0005', NULL, NULL, NULL, 0, 6, NULL, 1),
(7, 'Spanish Latte', NULL, 119.00, NULL, NULL, 119.00, 129.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0007', NULL, NULL, NULL, 0, 6, NULL, 1),
(9, 'Salted Caramel Latte', NULL, 119.00, NULL, NULL, 119.00, 129.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0009', NULL, NULL, NULL, 0, 6, NULL, 1),
(14, 'Latte', NULL, 119.00, NULL, NULL, 119.00, 129.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0014', NULL, NULL, NULL, 0, 6, NULL, 1),
(17, 'Caramel Macchiato', NULL, 135.00, NULL, NULL, 135.00, 139.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0017', NULL, NULL, NULL, 0, 6, NULL, 1),
(19, 'Biscoff Latte', NULL, 135.00, NULL, NULL, 135.00, 139.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0019', NULL, NULL, NULL, 0, 6, NULL, 1),
(22, 'Americano', NULL, 119.00, NULL, NULL, 119.00, 109.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0022', NULL, NULL, NULL, 0, 6, NULL, 1),
(26, 'Dark Mocha', NULL, 135.00, NULL, NULL, 135.00, 139.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0026', NULL, NULL, NULL, 0, 6, NULL, 1),
(28, 'White Chocolate', NULL, 135.00, NULL, NULL, 135.00, 139.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0028', NULL, NULL, NULL, 0, 6, NULL, 1),
(29, 'Vanilla Latte', NULL, 135.00, NULL, NULL, 135.00, 139.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0029', NULL, NULL, NULL, 0, 6, NULL, 1),
(31, 'Hazelnut Latte', NULL, 115.00, NULL, NULL, 115.00, 119.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0031', NULL, NULL, NULL, 0, 6, NULL, 1),
(34, 'Chocolate', NULL, 85.00, NULL, NULL, 85.00, 89.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0034', NULL, NULL, NULL, 0, 7, NULL, 1),
(35, 'Choco Hazelnut', NULL, 95.00, NULL, NULL, 95.00, 99.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0035', NULL, NULL, NULL, 0, 7, NULL, 1),
(37, 'Blueberry Latte', NULL, 95.00, NULL, NULL, 95.00, 99.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0037', NULL, NULL, NULL, 0, 7, NULL, 1),
(40, 'Strawberry Latte', NULL, 95.00, NULL, NULL, 95.00, 99.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0040', NULL, NULL, NULL, 0, 7, NULL, 1),
(41, 'Strawberry Choco', NULL, 95.00, NULL, NULL, 95.00, 99.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0041', NULL, NULL, NULL, 0, 7, NULL, 1),
(42, 'Biscoff', NULL, 95.00, NULL, NULL, 95.00, 99.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0042', NULL, NULL, NULL, 0, 7, NULL, 1),
(43, 'Matcha Latte', NULL, 85.00, NULL, NULL, 85.00, 89.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0043', NULL, NULL, NULL, 0, 8, NULL, 1),
(44, 'Strawberry Matcha', NULL, 85.00, NULL, NULL, 85.00, 99.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0044', NULL, NULL, NULL, 0, 8, NULL, 1),
(45, 'Choco Matcha', NULL, 85.00, NULL, NULL, 85.00, 99.00, 0, NULL, NULL, NULL, NULL, NULL, 1, 'ITM0045', NULL, NULL, NULL, 0, 8, NULL, 1),
(6, 'Tapa Chaofan', NULL, 109.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0006', NULL, NULL, NULL, 0, 9, 'menu_69d283ceac8d81.98367455.webp', 1),
(8, 'Chicken Poppers Chaofan', NULL, 129.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0008', NULL, NULL, NULL, 0, 9, 'menu_69d28409740d35.83321276.webp', 1),
(10, 'Liempo Chaofan', NULL, 149.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0010', NULL, NULL, NULL, 0, 9, 'menu_69d283bfc9b679.89802205.webp', 1),
(11, 'Longanisa Chaofan', NULL, 109.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0011', NULL, NULL, NULL, 0, 9, 'menu_69d283c8379587.42214172.webp', 1),
(12, 'Sisig Chaofan', NULL, 159.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0012', NULL, NULL, NULL, 0, 9, 'menu_69d283b859df22.83671266.webp', 1),
(13, 'Tocino Chaofan', NULL, 109.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0013', NULL, NULL, NULL, 0, 9, 'menu_69d282bb67ff53.61531606.webp', 1),
(15, 'Siomai Chaofan', NULL, 109.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0015', NULL, NULL, NULL, 0, 9, 'menu_69d283b0c06ed1.75154920.webp', 1),
(16, 'Fish Fillet Chaofan', NULL, 129.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0016', NULL, NULL, NULL, 0, 9, 'menu_69d283f7045798.29436394.webp', 1),
(49, 'Blueberry Soda', NULL, 49.00, NULL, NULL, NULL, NULL, 1, '12', '16', 49.00, 69.00, NULL, 0, 'ITM0049', NULL, NULL, NULL, 0, 10, NULL, 1),
(52, 'Strawberry Soda', NULL, 49.00, NULL, NULL, NULL, NULL, 1, '12', '16', 49.00, 69.00, NULL, 0, 'ITM0052', NULL, NULL, NULL, 0, 10, NULL, 1),
(54, 'Green Apple Soda', NULL, 49.00, NULL, NULL, NULL, NULL, 1, '12', '16', 49.00, 69.00, NULL, 0, 'ITM0054', NULL, NULL, NULL, 0, 10, NULL, 1),
(58, 'Lychee Soda', NULL, 49.00, NULL, NULL, NULL, NULL, 1, '12', '16', 49.00, 69.00, NULL, 0, 'ITM0058', NULL, NULL, NULL, 0, 10, NULL, 1),
(60, 'Oat Milk (Oatside)', NULL, 10.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0060', NULL, NULL, NULL, 0, 11, NULL, 1),
(18, 'Ham & Cheese Waffle', NULL, 49.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0018', NULL, NULL, NULL, 0, 12, NULL, 1),
(20, 'Cheese Waffle', NULL, 38.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0020', NULL, NULL, NULL, 0, 12, NULL, 1),
(21, 'Biscoff Waffle', NULL, 49.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0021', NULL, NULL, NULL, 0, 12, NULL, 1),
(23, 'Strawberry Drizzled Waffle', NULL, 49.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0023', NULL, NULL, NULL, 0, 12, NULL, 1),
(24, 'Creamy Carbonara', NULL, 149.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0024', NULL, NULL, NULL, 0, 13, NULL, 1),
(25, 'Meaty Spaghetti', NULL, 149.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0025', NULL, NULL, NULL, 0, 13, 'menu_69d28740dfa001.56857794.webp', 1),
(27, 'Creamy Truffle', NULL, 199.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0027', NULL, NULL, NULL, 0, 13, NULL, 1),
(30, 'Pepperoni Pizza', NULL, 249.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0030', NULL, NULL, NULL, 0, 14, 'menu_69d2844dc89b33.98201711.webp', 1),
(32, 'Hawaiian Pizza', NULL, 249.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0032', NULL, NULL, NULL, 0, 14, 'menu_69d28459960353.25716337.webp', 1),
(33, 'Four Cheese Pizza', NULL, 249.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0033', NULL, NULL, NULL, 0, 14, 'menu_69d28460daadb3.48643211.webp', 1),
(36, 'Beef Nachos', NULL, 149.00, 149.00, 199.00, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0036', NULL, NULL, NULL, 0, 15, 'menu_69d284a12192f2.25082123.webp', 1),
(38, 'Chicken Poppers', NULL, 129.00, 129.00, 199.00, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0038', NULL, NULL, NULL, 0, 15, 'menu_69d28686308ea4.37328518.webp', 1),
(39, 'French Fries', NULL, 69.00, 69.00, 139.00, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0039', NULL, NULL, NULL, 0, 15, NULL, 1),
(46, 'Beef Burger', NULL, 109.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0046', NULL, NULL, NULL, 0, 16, NULL, 1),
(47, 'Sisig Burger', NULL, 89.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0047', NULL, NULL, NULL, 0, 16, NULL, 1),
(48, 'Chicken Fillet Burger', NULL, 79.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0048', NULL, NULL, NULL, 0, 16, NULL, 1),
(50, 'Beef Burger with Fries', NULL, 129.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0050', NULL, NULL, NULL, 0, 16, 'menu_69d285f2481bf6.38069682.webp', 1),
(51, 'Sisig Burger with Fries', NULL, 119.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0051', NULL, NULL, NULL, 0, 16, 'menu_69d2860d7dff65.61176446.webp', 1),
(53, 'Chicken Fillet Burger with Fries', NULL, 109.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0053', NULL, NULL, NULL, 0, 16, 'menu_69d286021214e6.12405566.webp', 1),
(55, 'Ham & Cheese Sandwich', NULL, 89.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0055', NULL, NULL, NULL, 0, 17, NULL, 1),
(56, 'Tuna Sandwich', NULL, 79.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0056', NULL, NULL, NULL, 0, 17, NULL, 1),
(57, 'Ham & Cheese Sandwich w/ Fries', NULL, 129.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0057', NULL, NULL, NULL, 0, 17, 'menu_69d2849866fdb7.62887572.webp', 1),
(59, 'Tuna Sandwich w/ Fries', NULL, 119.00, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, 0, 'ITM0059', NULL, NULL, NULL, 0, 17, 'menu_69d285451ba2b2.74499446.webp', 1);

CREATE TABLE promotions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL,
  name VARCHAR(100) NOT NULL,
  discount_type ENUM('fixed', 'percent') NOT NULL,
  discount_value DECIMAL(10,2) NOT NULL,
  minimum_order DECIMAL(10,2) NOT NULL DEFAULT 0,
  start_at DATETIME NULL,
  end_at DATETIME NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  usage_count INT NOT NULL DEFAULT 0,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  total_amount DECIMAL(10,2) NOT NULL,
  subtotal_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  change_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  cash_received_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  cash_received_denominations_json TEXT NULL,
  cash_change_denominations_json TEXT NULL,
  payment_method ENUM('cash') NOT NULL DEFAULT 'cash',
  payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
  receipt_number VARCHAR(40) NULL,
  customer_id INT NULL,
  transaction_status VARCHAR(30) NOT NULL DEFAULT 'completed',
  refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_by INT NULL,
  notes TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT,
  menu_item_id INT,
  quantity INT NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  item_name_snapshot VARCHAR(255) NULL,
  product_code_snapshot VARCHAR(50) NULL,
  customizations TEXT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (menu_item_id) REFERENCES menu_items(id)
);

CREATE TABLE menu_item_recipes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  menu_item_id INT NOT NULL,
  ingredient_id INT NOT NULL,
  quantity DECIMAL(10,2) NOT NULL DEFAULT 0,
  quantity_unit VARCHAR(20) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
  FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
);

CREATE TABLE order_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  payment_method ENUM('cash') NOT NULL DEFAULT 'cash',
  amount DECIMAL(10,2) NOT NULL,
  denominations_received_json TEXT NULL,
  change_denominations_json TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE order_discounts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  promotion_id INT NULL,
  discount_code VARCHAR(50) NULL,
  discount_amount DECIMAL(10,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE SET NULL
);

CREATE TABLE inventory_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  menu_item_id INT NULL,
  ingredient_id INT NULL,
  action ENUM('add', 'remove', 'sale') NOT NULL,
  quantity DECIMAL(10,2) NOT NULL,
  reason TEXT,
  performed_by INT NULL,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
  FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
  FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE transaction_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NULL,
  reference_number VARCHAR(100) NULL,
  event_type VARCHAR(50) NOT NULL,
  status VARCHAR(30) NOT NULL,
  message TEXT NULL,
  payload_json LONGTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
);

CREATE TABLE audit_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50) NOT NULL,
  entity_id INT NULL,
  details_json LONGTEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE backups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  file_name VARCHAR(255) NULL,
  file_path VARCHAR(255) NULL,
  status VARCHAR(20) NOT NULL,
  reason VARCHAR(50) NULL,
  message TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE daily_reconciliations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  business_date DATE NOT NULL,
  gross_sales DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  refund_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  net_sales DECIMAL(10,2) NOT NULL DEFAULT 0,
  cash_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  order_count INT NOT NULL DEFAULT 0,
  reconciled_by INT NULL,
  notes TEXT NULL,
  reconciled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_business_date (business_date),
  FOREIGN KEY (reconciled_by) REFERENCES users(id) ON DELETE SET NULL
);


-- ============================================================
-- DUMMY DATA (pre-oral demo)
-- ============================================================

SET FOREIGN_KEY_CHECKS=0;

-- Customers
INSERT INTO customers (id, name, phone, loyalty_points, created_at) VALUES (1, 'Maria Santos', '09171234501', 120, '2026-05-05 00:00:00');
INSERT INTO customers (id, name, phone, loyalty_points, created_at) VALUES (2, 'Juan Dela Cruz', '09181234502', 85, '2026-05-09 00:00:00');
INSERT INTO customers (id, name, phone, loyalty_points, created_at) VALUES (3, 'Ana Reyes', '09191234503', 210, '2026-05-13 00:00:00');
INSERT INTO customers (id, name, phone, loyalty_points, created_at) VALUES (4, 'Carlo Mendoza', '09201234504', 64, '2026-05-17 00:00:00');
INSERT INTO customers (id, name, phone, loyalty_points, created_at) VALUES (5, 'Bea Fernandez', '09211234505', 140, '2026-05-21 00:00:00');
INSERT INTO customers (id, name, phone, loyalty_points, created_at) VALUES (6, 'Miguel Torres', '09221234506', 55, '2026-05-25 00:00:00');
INSERT INTO customers (id, name, phone, loyalty_points, created_at) VALUES (7, 'Sofia Ramos', '09231234507', 98, '2026-05-29 00:00:00');
INSERT INTO customers (id, name, phone, loyalty_points, created_at) VALUES (8, 'Ethan Villanueva', '09241234508', 175, '2026-06-02 00:00:00');
INSERT INTO customers (id, name, phone, loyalty_points, created_at) VALUES (9, 'Walk-in Regular', '09251234509', 40, '2026-06-06 00:00:00');
INSERT INTO customers (id, name, phone, loyalty_points, created_at) VALUES (10, 'Team Alpha', '09261234510', 33, '2026-06-10 00:00:00');

-- Ingredients (includes low-stock Fresh Milk / Espresso Beans)
INSERT INTO ingredients (id, name, unit, stock_quantity, manufacturing_date, expiration_date) VALUES (1, 'Espresso Beans', 'grams', 120.00, '2026-07-01', '2027-01-15');
INSERT INTO ingredients (id, name, unit, stock_quantity, manufacturing_date, expiration_date) VALUES (2, 'Fresh Milk', 'liters', 4.00, '2026-09-01', '2026-09-18');
INSERT INTO ingredients (id, name, unit, stock_quantity, manufacturing_date, expiration_date) VALUES (3, 'Jasmine Rice', 'grams', 12000.00, '2026-06-01', '2027-06-01');
INSERT INTO ingredients (id, name, unit, stock_quantity, manufacturing_date, expiration_date) VALUES (4, 'Chicken Fillet', 'grams', 3500.00, '2026-08-20', '2026-09-25');
INSERT INTO ingredients (id, name, unit, stock_quantity, manufacturing_date, expiration_date) VALUES (5, 'Cooking Oil', 'liters', 40.00, '2026-05-01', '2027-05-01');
INSERT INTO ingredients (id, name, unit, stock_quantity, manufacturing_date, expiration_date) VALUES (6, 'Sugar Syrup', 'liters', 8.50, '2026-07-10', '2026-12-10');
INSERT INTO ingredients (id, name, unit, stock_quantity, manufacturing_date, expiration_date) VALUES (7, 'Matcha Powder', 'grams', 900.00, '2026-06-15', '2027-03-15');
INSERT INTO ingredients (id, name, unit, stock_quantity, manufacturing_date, expiration_date) VALUES (8, 'Burger Bun', 'pcs', 180.00, '2026-09-05', '2026-09-20');

-- Sample recipes
INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (14, 1, 18, 'grams');
INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (14, 2, 0.18, 'liters');
INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (5, 1, 18, 'grams');
INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (5, 2, 0.18, 'liters');
INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (6, 3, 220, 'grams');
INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (6, 5, 0.015, 'liters');
INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (12, 3, 220, 'grams');
INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (12, 4, 90, 'grams');
INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (8, 3, 220, 'grams');
INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (8, 4, 80, 'grams');
INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (46, 8, 1, 'pcs');
INSERT INTO menu_item_recipes (menu_item_id, ingredient_id, quantity, quantity_unit) VALUES (46, 4, 100, 'grams');

-- Inventory anomaly-friendly logs
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 6.00, 'Spoilage / waste demo', 1, '2026-09-03 10:15:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 8.00, 'Bulk kitchen prep', 1, '2026-09-04 09:40:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 7.50, 'Expired trim waste', 1, '2026-09-06 16:20:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'add', 30.00, 'Supplier delivery', 1, '2026-09-07 08:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'add', 40.00, 'Rice restock', 1, '2026-09-08 08:30:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 5.50, 'Demo consumption', 1, '2026-09-09 14:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 1.55, 'Daily usage seed', 1, '2026-09-10 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 2.28, 'Daily usage seed', 1, '2026-09-10 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 1.12, 'Daily usage seed', 1, '2026-09-10 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 1.62, 'Daily usage seed', 1, '2026-09-10 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 1.78, 'Daily usage seed', 1, '2026-09-10 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 1.56, 'Daily usage seed', 1, '2026-09-09 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 0.55, 'Daily usage seed', 1, '2026-09-09 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 2.10, 'Daily usage seed', 1, '2026-09-09 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 1.75, 'Daily usage seed', 1, '2026-09-09 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 2.29, 'Daily usage seed', 1, '2026-09-09 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 1.54, 'Daily usage seed', 1, '2026-09-08 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 1.59, 'Daily usage seed', 1, '2026-09-08 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 1.68, 'Daily usage seed', 1, '2026-09-08 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 1.13, 'Daily usage seed', 1, '2026-09-08 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 1.95, 'Daily usage seed', 1, '2026-09-08 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 2.26, 'Daily usage seed', 1, '2026-09-07 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 0.58, 'Daily usage seed', 1, '2026-09-07 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 0.51, 'Daily usage seed', 1, '2026-09-07 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 1.40, 'Daily usage seed', 1, '2026-09-07 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 0.99, 'Daily usage seed', 1, '2026-09-07 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 1.43, 'Daily usage seed', 1, '2026-09-06 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 1.06, 'Daily usage seed', 1, '2026-09-06 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 0.80, 'Daily usage seed', 1, '2026-09-06 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 0.62, 'Daily usage seed', 1, '2026-09-06 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 1.70, 'Daily usage seed', 1, '2026-09-06 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 2.50, 'Daily usage seed', 1, '2026-09-05 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 0.70, 'Daily usage seed', 1, '2026-09-05 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 1.49, 'Daily usage seed', 1, '2026-09-05 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 2.18, 'Daily usage seed', 1, '2026-09-05 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 1.05, 'Daily usage seed', 1, '2026-09-05 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 1.24, 'Daily usage seed', 1, '2026-09-04 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 0.50, 'Daily usage seed', 1, '2026-09-04 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 0.70, 'Daily usage seed', 1, '2026-09-04 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 1.13, 'Daily usage seed', 1, '2026-09-04 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 1.95, 'Daily usage seed', 1, '2026-09-04 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 0.81, 'Daily usage seed', 1, '2026-09-03 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 1.88, 'Daily usage seed', 1, '2026-09-03 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 1.95, 'Daily usage seed', 1, '2026-09-03 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 2.09, 'Daily usage seed', 1, '2026-09-03 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 2.49, 'Daily usage seed', 1, '2026-09-03 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 2.21, 'Daily usage seed', 1, '2026-09-02 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 0.58, 'Daily usage seed', 1, '2026-09-02 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 1.82, 'Daily usage seed', 1, '2026-09-02 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 1.07, 'Daily usage seed', 1, '2026-09-02 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 1.95, 'Daily usage seed', 1, '2026-09-02 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 0.96, 'Daily usage seed', 1, '2026-09-01 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 1.78, 'Daily usage seed', 1, '2026-09-01 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 1.61, 'Daily usage seed', 1, '2026-09-01 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 1.45, 'Daily usage seed', 1, '2026-09-01 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 1.37, 'Daily usage seed', 1, '2026-09-01 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 1.58, 'Daily usage seed', 1, '2026-08-31 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 1.48, 'Daily usage seed', 1, '2026-08-31 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 1.10, 'Daily usage seed', 1, '2026-08-31 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 0.83, 'Daily usage seed', 1, '2026-08-31 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 1.15, 'Daily usage seed', 1, '2026-08-31 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 2.05, 'Daily usage seed', 1, '2026-08-30 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 2.03, 'Daily usage seed', 1, '2026-08-30 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 1.59, 'Daily usage seed', 1, '2026-08-30 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 0.56, 'Daily usage seed', 1, '2026-08-30 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 1.73, 'Daily usage seed', 1, '2026-08-30 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 2.41, 'Daily usage seed', 1, '2026-08-29 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 0.84, 'Daily usage seed', 1, '2026-08-29 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 1.69, 'Daily usage seed', 1, '2026-08-29 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 1.70, 'Daily usage seed', 1, '2026-08-29 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 1.80, 'Daily usage seed', 1, '2026-08-29 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (1, 'remove', 0.88, 'Daily usage seed', 1, '2026-08-28 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (2, 'remove', 1.11, 'Daily usage seed', 1, '2026-08-28 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (3, 'remove', 1.33, 'Daily usage seed', 1, '2026-08-28 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (4, 'remove', 1.94, 'Daily usage seed', 1, '2026-08-28 15:00:00');
INSERT INTO inventory_logs (ingredient_id, action, quantity, reason, performed_by, timestamp) VALUES (5, 'remove', 1.01, 'Daily usage seed', 1, '2026-08-28 15:00:00');

-- Completed demo orders (~30 days) for analytics / anomaly / forecast
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (1, 456.00, 456.00, 0.00, 0.00, 476.00, 20.00, 476.00, 'cash', 'completed', 'RCPT-DEMO-20260812-0001', NULL, 'completed', 0.00, 1, '2026-08-12 19:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (1, 1, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (2, 1, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (1, 1, 'cash', 476.00, '2026-08-12 19:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (2, 565.00, 565.00, 0.00, 0.00, 585.00, 20.00, 585.00, 'cash', 'completed', 'RCPT-DEMO-20260812-0002', 10, 'completed', 0.00, 1, '2026-08-12 14:03:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (3, 2, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (4, 2, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (5, 2, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (2, 2, 'cash', 585.00, '2026-08-12 14:03:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (3, 516.00, 516.00, 0.00, 0.00, 516.00, 0.00, 516.00, 'cash', 'completed', 'RCPT-DEMO-20260812-0003', NULL, 'completed', 0.00, 1, '2026-08-12 14:38:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (6, 3, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (7, 3, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (8, 3, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (3, 3, 'cash', 516.00, '2026-08-12 14:38:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (4, 605.00, 605.00, 0.00, 0.00, 605.00, 0.00, 605.00, 'cash', 'completed', 'RCPT-DEMO-20260812-0004', 8, 'completed', 0.00, 1, '2026-08-12 13:37:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (9, 4, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (10, 4, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (11, 4, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (4, 4, 'cash', 605.00, '2026-08-12 13:37:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (5, 665.00, 665.00, 0.00, 0.00, 665.00, 0.00, 665.00, 'cash', 'completed', 'RCPT-DEMO-20260812-0005', NULL, 'completed', 0.00, 1, '2026-08-12 13:52:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (12, 5, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (13, 5, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (14, 5, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (5, 5, 'cash', 665.00, '2026-08-12 13:52:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (6, 377.00, 377.00, 0.00, 0.00, 427.00, 50.00, 427.00, 'cash', 'completed', 'RCPT-DEMO-20260812-0006', 10, 'completed', 0.00, 1, '2026-08-12 13:33:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (15, 6, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (16, 6, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (6, 6, 'cash', 427.00, '2026-08-12 13:33:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (7, 347.00, 347.00, 0.00, 0.00, 367.00, 20.00, 367.00, 'cash', 'completed', 'RCPT-DEMO-20260812-0007', 7, 'completed', 0.00, 1, '2026-08-12 18:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (17, 7, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (18, 7, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (7, 7, 'cash', 367.00, '2026-08-12 18:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (8, 268.00, 268.00, 0.00, 0.00, 278.00, 10.00, 278.00, 'cash', 'completed', 'RCPT-DEMO-20260812-0008', 6, 'completed', 0.00, 1, '2026-08-12 11:22:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (19, 8, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (20, 8, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (8, 8, 'cash', 278.00, '2026-08-12 11:22:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (9, 506.00, 506.00, 0.00, 0.00, 556.00, 50.00, 556.00, 'cash', 'completed', 'RCPT-DEMO-20260812-0009', NULL, 'completed', 0.00, 1, '2026-08-12 18:44:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (21, 9, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (22, 9, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (23, 9, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (9, 9, 'cash', 556.00, '2026-08-12 18:44:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (10, 636.00, 636.00, 0.00, 0.00, 636.00, 0.00, 636.00, 'cash', 'completed', 'RCPT-DEMO-20260812-0010', 9, 'completed', 0.00, 1, '2026-08-12 11:08:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (24, 10, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (25, 10, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (10, 10, 'cash', 636.00, '2026-08-12 11:08:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (11, 347.00, 347.00, 0.00, 0.00, 397.00, 50.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260812-0011', 5, 'completed', 0.00, 1, '2026-08-12 13:48:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (26, 11, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (27, 11, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (11, 11, 'cash', 397.00, '2026-08-12 13:48:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (12, 477.00, 477.00, 0.00, 0.00, 527.00, 50.00, 527.00, 'cash', 'completed', 'RCPT-DEMO-20260813-0012', 3, 'completed', 0.00, 1, '2026-08-13 18:59:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (28, 12, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (29, 12, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (30, 12, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (12, 12, 'cash', 527.00, '2026-08-13 18:59:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (13, 247.00, 247.00, 0.00, 0.00, 257.00, 10.00, 257.00, 'cash', 'completed', 'RCPT-DEMO-20260813-0013', 9, 'completed', 0.00, 1, '2026-08-13 11:14:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (31, 13, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (32, 13, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (13, 13, 'cash', 257.00, '2026-08-13 11:14:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (14, 425.00, 425.00, 0.00, 0.00, 475.00, 50.00, 475.00, 'cash', 'completed', 'RCPT-DEMO-20260813-0014', NULL, 'completed', 0.00, 1, '2026-08-13 17:56:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (33, 14, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (34, 14, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (35, 14, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (14, 14, 'cash', 475.00, '2026-08-13 17:56:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (15, 496.00, 496.00, 0.00, 0.00, 496.00, 0.00, 496.00, 'cash', 'completed', 'RCPT-DEMO-20260813-0015', 6, 'completed', 0.00, 1, '2026-08-13 12:31:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (36, 15, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (37, 15, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (15, 15, 'cash', 496.00, '2026-08-13 12:31:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (16, 486.00, 486.00, 0.00, 0.00, 506.00, 20.00, 506.00, 'cash', 'completed', 'RCPT-DEMO-20260813-0016', NULL, 'completed', 0.00, 1, '2026-08-13 12:12:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (38, 16, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (39, 16, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (40, 16, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (16, 16, 'cash', 506.00, '2026-08-13 12:12:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (17, 158.00, 158.00, 0.00, 0.00, 178.00, 20.00, 178.00, 'cash', 'completed', 'RCPT-DEMO-20260813-0017', NULL, 'completed', 0.00, 1, '2026-08-13 12:35:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (41, 17, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (42, 17, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (17, 17, 'cash', 178.00, '2026-08-13 12:35:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (18, 228.00, 228.00, 0.00, 0.00, 228.00, 0.00, 228.00, 'cash', 'completed', 'RCPT-DEMO-20260813-0018', 3, 'completed', 0.00, 1, '2026-08-13 19:15:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (43, 18, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (44, 18, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (18, 18, 'cash', 228.00, '2026-08-13 19:15:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (19, 586.00, 586.00, 0.00, 0.00, 596.00, 10.00, 596.00, 'cash', 'completed', 'RCPT-DEMO-20260813-0019', NULL, 'completed', 0.00, 1, '2026-08-13 19:48:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (45, 19, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (46, 19, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (47, 19, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (19, 19, 'cash', 596.00, '2026-08-13 19:48:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (20, 496.00, 496.00, 0.00, 0.00, 506.00, 10.00, 506.00, 'cash', 'completed', 'RCPT-DEMO-20260813-0020', 9, 'completed', 0.00, 1, '2026-08-13 19:31:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (48, 20, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (49, 20, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (50, 20, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (20, 20, 'cash', 506.00, '2026-08-13 19:31:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (21, 347.00, 347.00, 0.00, 0.00, 397.00, 50.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260813-0021', 8, 'completed', 0.00, 1, '2026-08-13 12:12:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (51, 21, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (52, 21, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (21, 21, 'cash', 397.00, '2026-08-13 12:12:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (22, 326.00, 326.00, 0.00, 0.00, 336.00, 10.00, 336.00, 'cash', 'completed', 'RCPT-DEMO-20260814-0022', 5, 'completed', 0.00, 1, '2026-08-14 13:01:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (53, 22, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (54, 22, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (55, 22, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (22, 22, 'cash', 336.00, '2026-08-14 13:01:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (23, 496.00, 496.00, 0.00, 0.00, 546.00, 50.00, 546.00, 'cash', 'completed', 'RCPT-DEMO-20260814-0023', NULL, 'completed', 0.00, 1, '2026-08-14 19:08:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (56, 23, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (57, 23, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (23, 23, 'cash', 546.00, '2026-08-14 19:08:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (24, 536.00, 536.00, 0.00, 0.00, 536.00, 0.00, 536.00, 'cash', 'completed', 'RCPT-DEMO-20260814-0024', 6, 'completed', 0.00, 1, '2026-08-14 19:32:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (58, 24, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (59, 24, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (24, 24, 'cash', 536.00, '2026-08-14 19:32:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (25, 248.00, 248.00, 0.00, 0.00, 268.00, 20.00, 268.00, 'cash', 'completed', 'RCPT-DEMO-20260814-0025', 1, 'completed', 0.00, 1, '2026-08-14 18:53:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (60, 25, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (61, 25, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (25, 25, 'cash', 268.00, '2026-08-14 18:53:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (26, 198.00, 198.00, 0.00, 0.00, 208.00, 10.00, 208.00, 'cash', 'completed', 'RCPT-DEMO-20260814-0026', 5, 'completed', 0.00, 1, '2026-08-14 18:05:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (62, 26, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (63, 26, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (26, 26, 'cash', 208.00, '2026-08-14 18:05:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (27, 347.00, 347.00, 0.00, 0.00, 397.00, 50.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260814-0027', 5, 'completed', 0.00, 1, '2026-08-14 12:49:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (64, 27, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (65, 27, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (27, 27, 'cash', 397.00, '2026-08-14 12:49:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (28, 317.00, 317.00, 0.00, 0.00, 367.00, 50.00, 367.00, 'cash', 'completed', 'RCPT-DEMO-20260814-0028', 3, 'completed', 0.00, 1, '2026-08-14 13:59:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (66, 28, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (67, 28, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (68, 28, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (28, 28, 'cash', 367.00, '2026-08-14 13:59:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (29, 247.00, 247.00, 0.00, 0.00, 267.00, 20.00, 267.00, 'cash', 'completed', 'RCPT-DEMO-20260814-0029', 4, 'completed', 0.00, 1, '2026-08-14 17:40:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (69, 29, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (70, 29, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (29, 29, 'cash', 267.00, '2026-08-14 17:40:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (30, 316.00, 316.00, 0.00, 0.00, 316.00, 0.00, 316.00, 'cash', 'completed', 'RCPT-DEMO-20260814-0030', 8, 'completed', 0.00, 1, '2026-08-14 18:14:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (71, 30, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (72, 30, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (30, 30, 'cash', 316.00, '2026-08-14 18:14:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (31, 318.00, 318.00, 0.00, 0.00, 338.00, 20.00, 338.00, 'cash', 'completed', 'RCPT-DEMO-20260814-0031', NULL, 'completed', 0.00, 1, '2026-08-14 19:12:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (73, 31, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (74, 31, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (31, 31, 'cash', 338.00, '2026-08-14 19:12:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (32, 515.00, 515.00, 0.00, 0.00, 565.00, 50.00, 565.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0032', NULL, 'completed', 0.00, 1, '2026-08-15 18:02:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (75, 32, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (76, 32, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (77, 32, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (32, 32, 'cash', 565.00, '2026-08-15 18:02:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (33, 616.00, 616.00, 0.00, 0.00, 626.00, 10.00, 626.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0033', 5, 'completed', 0.00, 1, '2026-08-15 14:28:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (78, 33, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (79, 33, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (80, 33, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (33, 33, 'cash', 626.00, '2026-08-15 14:28:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (34, 207.00, 207.00, 0.00, 0.00, 217.00, 10.00, 217.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0034', 7, 'completed', 0.00, 1, '2026-08-15 13:41:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (81, 34, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (82, 34, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (34, 34, 'cash', 217.00, '2026-08-15 13:41:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (35, 267.00, 267.00, 0.00, 0.00, 287.00, 20.00, 287.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0035', 4, 'completed', 0.00, 1, '2026-08-15 19:39:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (83, 35, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (84, 35, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (35, 35, 'cash', 287.00, '2026-08-15 19:39:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (36, 427.00, 427.00, 0.00, 0.00, 477.00, 50.00, 477.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0036', 5, 'completed', 0.00, 1, '2026-08-15 12:34:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (85, 36, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (86, 36, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (36, 36, 'cash', 477.00, '2026-08-15 12:34:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (37, 318.00, 318.00, 0.00, 0.00, 368.00, 50.00, 368.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0037', 9, 'completed', 0.00, 1, '2026-08-15 12:55:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (87, 37, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (88, 37, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (37, 37, 'cash', 368.00, '2026-08-15 12:55:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (38, 228.00, 228.00, 0.00, 0.00, 238.00, 10.00, 238.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0038', NULL, 'completed', 0.00, 1, '2026-08-15 14:24:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (89, 38, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (90, 38, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (38, 38, 'cash', 238.00, '2026-08-15 14:24:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (39, 437.00, 437.00, 0.00, 0.00, 437.00, 0.00, 437.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0039', NULL, 'completed', 0.00, 1, '2026-08-15 11:30:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (91, 39, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (92, 39, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (39, 39, 'cash', 437.00, '2026-08-15 11:30:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (40, 427.00, 427.00, 0.00, 0.00, 427.00, 0.00, 427.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0040', 9, 'completed', 0.00, 1, '2026-08-15 18:14:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (93, 40, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (94, 40, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (40, 40, 'cash', 427.00, '2026-08-15 18:14:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (41, 466.00, 466.00, 0.00, 0.00, 486.00, 20.00, 486.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0041', NULL, 'completed', 0.00, 1, '2026-08-15 14:03:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (95, 41, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (96, 41, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (97, 41, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (41, 41, 'cash', 486.00, '2026-08-15 14:03:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (42, 337.00, 337.00, 0.00, 0.00, 387.00, 50.00, 387.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0042', NULL, 'completed', 0.00, 1, '2026-08-15 18:16:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (98, 42, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (99, 42, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (42, 42, 'cash', 387.00, '2026-08-15 18:16:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (43, 278.00, 278.00, 0.00, 0.00, 298.00, 20.00, 298.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0043', 8, 'completed', 0.00, 1, '2026-08-15 17:37:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (100, 43, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (101, 43, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (43, 43, 'cash', 298.00, '2026-08-15 17:37:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (44, 207.00, 207.00, 0.00, 0.00, 217.00, 10.00, 217.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0044', 8, 'completed', 0.00, 1, '2026-08-15 17:24:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (102, 44, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (103, 44, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (44, 44, 'cash', 217.00, '2026-08-15 17:24:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (45, 318.00, 318.00, 0.00, 0.00, 368.00, 50.00, 368.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0045', 6, 'completed', 0.00, 1, '2026-08-15 11:00:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (104, 45, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (105, 45, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (45, 45, 'cash', 368.00, '2026-08-15 11:00:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (46, 317.00, 317.00, 0.00, 0.00, 327.00, 10.00, 327.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0046', NULL, 'completed', 0.00, 1, '2026-08-15 19:13:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (106, 46, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (107, 46, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (108, 46, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (46, 46, 'cash', 327.00, '2026-08-15 19:13:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (47, 437.00, 437.00, 0.00, 0.00, 437.00, 0.00, 437.00, 'cash', 'completed', 'RCPT-DEMO-20260815-0047', NULL, 'completed', 0.00, 1, '2026-08-15 11:56:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (109, 47, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (110, 47, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (47, 47, 'cash', 437.00, '2026-08-15 11:56:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (48, 278.00, 278.00, 0.00, 0.00, 288.00, 10.00, 288.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0048', 9, 'completed', 0.00, 1, '2026-08-16 18:47:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (111, 48, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (112, 48, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (48, 48, 'cash', 288.00, '2026-08-16 18:47:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (49, 207.00, 207.00, 0.00, 0.00, 217.00, 10.00, 217.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0049', 9, 'completed', 0.00, 1, '2026-08-16 17:48:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (113, 49, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (114, 49, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (49, 49, 'cash', 217.00, '2026-08-16 17:48:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (50, 347.00, 347.00, 0.00, 0.00, 357.00, 10.00, 357.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0050', 6, 'completed', 0.00, 1, '2026-08-16 18:21:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (115, 50, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (116, 50, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (117, 50, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (50, 50, 'cash', 357.00, '2026-08-16 18:21:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (51, 267.00, 267.00, 0.00, 0.00, 287.00, 20.00, 287.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0051', 3, 'completed', 0.00, 1, '2026-08-16 11:19:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (118, 51, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (119, 51, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (51, 51, 'cash', 287.00, '2026-08-16 11:19:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (52, 267.00, 267.00, 0.00, 0.00, 287.00, 20.00, 287.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0052', 1, 'completed', 0.00, 1, '2026-08-16 18:58:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (120, 52, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (121, 52, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (52, 52, 'cash', 287.00, '2026-08-16 18:58:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (53, 377.00, 377.00, 0.00, 0.00, 427.00, 50.00, 427.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0053', 5, 'completed', 0.00, 1, '2026-08-16 18:29:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (122, 53, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (123, 53, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (53, 53, 'cash', 427.00, '2026-08-16 18:29:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (54, 347.00, 347.00, 0.00, 0.00, 357.00, 10.00, 357.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0054', 5, 'completed', 0.00, 1, '2026-08-16 19:30:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (124, 54, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (125, 54, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (54, 54, 'cash', 357.00, '2026-08-16 19:30:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (55, 526.00, 526.00, 0.00, 0.00, 576.00, 50.00, 576.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0055', 7, 'completed', 0.00, 1, '2026-08-16 18:14:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (126, 55, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (127, 55, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (128, 55, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (55, 55, 'cash', 576.00, '2026-08-16 18:14:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (56, 685.00, 685.00, 0.00, 0.00, 735.00, 50.00, 735.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0056', 1, 'completed', 0.00, 1, '2026-08-16 12:35:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (129, 56, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (130, 56, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (131, 56, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (56, 56, 'cash', 735.00, '2026-08-16 12:35:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (57, 396.00, 396.00, 0.00, 0.00, 416.00, 20.00, 416.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0057', 7, 'completed', 0.00, 1, '2026-08-16 13:16:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (132, 57, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (133, 57, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (57, 57, 'cash', 416.00, '2026-08-16 13:16:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (58, 377.00, 377.00, 0.00, 0.00, 427.00, 50.00, 427.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0058', 9, 'completed', 0.00, 1, '2026-08-16 13:49:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (134, 58, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (135, 58, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (58, 58, 'cash', 427.00, '2026-08-16 13:49:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (59, 318.00, 318.00, 0.00, 0.00, 318.00, 0.00, 318.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0059', 5, 'completed', 0.00, 1, '2026-08-16 17:24:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (136, 59, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (137, 59, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (59, 59, 'cash', 318.00, '2026-08-16 17:24:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (60, 437.00, 437.00, 0.00, 0.00, 457.00, 20.00, 457.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0060', 7, 'completed', 0.00, 1, '2026-08-16 13:21:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (138, 60, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (139, 60, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (60, 60, 'cash', 457.00, '2026-08-16 13:21:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (61, 347.00, 347.00, 0.00, 0.00, 347.00, 0.00, 347.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0061', NULL, 'completed', 0.00, 1, '2026-08-16 11:59:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (140, 61, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (141, 61, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (61, 61, 'cash', 347.00, '2026-08-16 11:59:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (62, 397.00, 397.00, 0.00, 0.00, 447.00, 50.00, 447.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0062', 5, 'completed', 0.00, 1, '2026-08-16 11:30:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (142, 62, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (143, 62, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (62, 62, 'cash', 447.00, '2026-08-16 11:30:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (63, 337.00, 337.00, 0.00, 0.00, 347.00, 10.00, 347.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0063', 9, 'completed', 0.00, 1, '2026-08-16 18:34:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (144, 63, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (145, 63, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (63, 63, 'cash', 347.00, '2026-08-16 18:34:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (64, 316.00, 316.00, 0.00, 0.00, 326.00, 10.00, 326.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0064', 3, 'completed', 0.00, 1, '2026-08-16 14:37:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (146, 64, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (147, 64, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (64, 64, 'cash', 326.00, '2026-08-16 14:37:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (65, 536.00, 536.00, 0.00, 0.00, 556.00, 20.00, 556.00, 'cash', 'completed', 'RCPT-DEMO-20260816-0065', 3, 'completed', 0.00, 1, '2026-08-16 12:32:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (148, 65, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (149, 65, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (65, 65, 'cash', 556.00, '2026-08-16 12:32:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (66, 437.00, 437.00, 0.00, 0.00, 447.00, 10.00, 447.00, 'cash', 'completed', 'RCPT-DEMO-20260817-0066', NULL, 'completed', 0.00, 1, '2026-08-17 19:12:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (150, 66, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (151, 66, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (66, 66, 'cash', 447.00, '2026-08-17 19:12:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (67, 396.00, 396.00, 0.00, 0.00, 396.00, 0.00, 396.00, 'cash', 'completed', 'RCPT-DEMO-20260817-0067', 10, 'completed', 0.00, 1, '2026-08-17 12:02:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (152, 67, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (153, 67, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (67, 67, 'cash', 396.00, '2026-08-17 12:02:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (68, 516.00, 516.00, 0.00, 0.00, 566.00, 50.00, 566.00, 'cash', 'completed', 'RCPT-DEMO-20260817-0068', 9, 'completed', 0.00, 1, '2026-08-17 17:57:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (154, 68, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (155, 68, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (156, 68, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (68, 68, 'cash', 566.00, '2026-08-17 17:57:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (69, 387.00, 387.00, 0.00, 0.00, 387.00, 0.00, 387.00, 'cash', 'completed', 'RCPT-DEMO-20260817-0069', NULL, 'completed', 0.00, 1, '2026-08-17 18:06:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (157, 69, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (158, 69, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (159, 69, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (69, 69, 'cash', 387.00, '2026-08-17 18:06:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (70, 526.00, 526.00, 0.00, 0.00, 576.00, 50.00, 576.00, 'cash', 'completed', 'RCPT-DEMO-20260817-0070', NULL, 'completed', 0.00, 1, '2026-08-17 11:37:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (160, 70, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (161, 70, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (162, 70, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (70, 70, 'cash', 576.00, '2026-08-17 11:37:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (71, 347.00, 347.00, 0.00, 0.00, 397.00, 50.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260817-0071', 8, 'completed', 0.00, 1, '2026-08-17 18:00:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (163, 71, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (164, 71, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (71, 71, 'cash', 397.00, '2026-08-17 18:00:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (72, 347.00, 347.00, 0.00, 0.00, 397.00, 50.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260817-0072', 2, 'completed', 0.00, 1, '2026-08-17 17:04:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (165, 72, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (166, 72, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (72, 72, 'cash', 397.00, '2026-08-17 17:04:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (73, 496.00, 496.00, 0.00, 0.00, 506.00, 10.00, 506.00, 'cash', 'completed', 'RCPT-DEMO-20260817-0073', NULL, 'completed', 0.00, 1, '2026-08-17 19:57:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (167, 73, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (168, 73, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (73, 73, 'cash', 506.00, '2026-08-17 19:57:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (74, 247.00, 247.00, 0.00, 0.00, 257.00, 10.00, 257.00, 'cash', 'completed', 'RCPT-DEMO-20260817-0074', 5, 'completed', 0.00, 1, '2026-08-17 18:24:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (169, 74, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (170, 74, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (74, 74, 'cash', 257.00, '2026-08-17 18:24:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (75, 396.00, 396.00, 0.00, 0.00, 396.00, 0.00, 396.00, 'cash', 'completed', 'RCPT-DEMO-20260817-0075', NULL, 'completed', 0.00, 1, '2026-08-17 14:58:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (171, 75, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (172, 75, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (75, 75, 'cash', 396.00, '2026-08-17 14:58:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (76, 456.00, 456.00, 0.00, 0.00, 456.00, 0.00, 456.00, 'cash', 'completed', 'RCPT-DEMO-20260817-0076', 1, 'completed', 0.00, 1, '2026-08-17 19:18:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (173, 76, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (174, 76, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (76, 76, 'cash', 456.00, '2026-08-17 19:18:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (77, 556.00, 556.00, 0.00, 0.00, 566.00, 10.00, 566.00, 'cash', 'completed', 'RCPT-DEMO-20260818-0077', 3, 'completed', 0.00, 1, '2026-08-18 18:03:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (175, 77, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (176, 77, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (77, 77, 'cash', 566.00, '2026-08-18 18:03:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (78, 556.00, 556.00, 0.00, 0.00, 606.00, 50.00, 606.00, 'cash', 'completed', 'RCPT-DEMO-20260818-0078', 10, 'completed', 0.00, 1, '2026-08-18 11:58:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (177, 78, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (178, 78, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (179, 78, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (78, 78, 'cash', 606.00, '2026-08-18 11:58:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (79, 347.00, 347.00, 0.00, 0.00, 357.00, 10.00, 357.00, 'cash', 'completed', 'RCPT-DEMO-20260818-0079', 1, 'completed', 0.00, 1, '2026-08-18 14:05:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (180, 79, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (181, 79, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (79, 79, 'cash', 357.00, '2026-08-18 14:05:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (80, 327.00, 327.00, 0.00, 0.00, 337.00, 10.00, 337.00, 'cash', 'completed', 'RCPT-DEMO-20260818-0080', 4, 'completed', 0.00, 1, '2026-08-18 14:32:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (182, 80, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (183, 80, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (184, 80, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (80, 80, 'cash', 337.00, '2026-08-18 14:32:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (81, 397.00, 397.00, 0.00, 0.00, 417.00, 20.00, 417.00, 'cash', 'completed', 'RCPT-DEMO-20260818-0081', 4, 'completed', 0.00, 1, '2026-08-18 11:56:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (185, 81, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (186, 81, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (81, 81, 'cash', 417.00, '2026-08-18 11:56:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (82, 247.00, 247.00, 0.00, 0.00, 267.00, 20.00, 267.00, 'cash', 'completed', 'RCPT-DEMO-20260818-0082', NULL, 'completed', 0.00, 1, '2026-08-18 17:27:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (187, 82, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (188, 82, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (82, 82, 'cash', 267.00, '2026-08-18 17:27:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (83, 456.00, 456.00, 0.00, 0.00, 466.00, 10.00, 466.00, 'cash', 'completed', 'RCPT-DEMO-20260818-0083', 10, 'completed', 0.00, 1, '2026-08-18 19:28:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (189, 83, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (190, 83, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (83, 83, 'cash', 466.00, '2026-08-18 19:28:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (84, 247.00, 247.00, 0.00, 0.00, 257.00, 10.00, 257.00, 'cash', 'completed', 'RCPT-DEMO-20260818-0084', 9, 'completed', 0.00, 1, '2026-08-18 12:01:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (191, 84, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (192, 84, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (84, 84, 'cash', 257.00, '2026-08-18 12:01:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (85, 228.00, 228.00, 0.00, 0.00, 228.00, 0.00, 228.00, 'cash', 'completed', 'RCPT-DEMO-20260818-0085', 5, 'completed', 0.00, 1, '2026-08-18 12:51:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (193, 85, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (194, 85, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (85, 85, 'cash', 228.00, '2026-08-18 12:51:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (86, 506.00, 506.00, 0.00, 0.00, 526.00, 20.00, 526.00, 'cash', 'completed', 'RCPT-DEMO-20260818-0086', 1, 'completed', 0.00, 1, '2026-08-18 13:23:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (195, 86, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (196, 86, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (197, 86, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (86, 86, 'cash', 526.00, '2026-08-18 13:23:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (87, 636.00, 636.00, 0.00, 0.00, 646.00, 10.00, 646.00, 'cash', 'completed', 'RCPT-DEMO-20260818-0087', 3, 'completed', 0.00, 1, '2026-08-18 12:35:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (198, 87, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (199, 87, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (87, 87, 'cash', 646.00, '2026-08-18 12:35:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (88, 536.00, 536.00, 0.00, 0.00, 546.00, 10.00, 546.00, 'cash', 'completed', 'RCPT-DEMO-20260819-0088', NULL, 'completed', 0.00, 1, '2026-08-19 18:45:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (200, 88, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (201, 88, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (88, 88, 'cash', 546.00, '2026-08-19 18:45:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (89, 567.00, 567.00, 0.00, 0.00, 567.00, 0.00, 567.00, 'cash', 'completed', 'RCPT-DEMO-20260819-0089', 3, 'completed', 0.00, 1, '2026-08-19 11:45:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (202, 89, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (203, 89, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (89, 89, 'cash', 567.00, '2026-08-19 11:45:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (90, 567.00, 567.00, 0.00, 0.00, 567.00, 0.00, 567.00, 'cash', 'completed', 'RCPT-DEMO-20260819-0090', 8, 'completed', 0.00, 1, '2026-08-19 19:01:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (204, 90, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (205, 90, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (90, 90, 'cash', 567.00, '2026-08-19 19:01:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (91, 247.00, 247.00, 0.00, 0.00, 247.00, 0.00, 247.00, 'cash', 'completed', 'RCPT-DEMO-20260819-0091', NULL, 'completed', 0.00, 1, '2026-08-19 11:39:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (206, 91, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (207, 91, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (208, 91, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (91, 91, 'cash', 247.00, '2026-08-19 11:39:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (92, 685.00, 685.00, 0.00, 0.00, 695.00, 10.00, 695.00, 'cash', 'completed', 'RCPT-DEMO-20260819-0092', NULL, 'completed', 0.00, 1, '2026-08-19 17:11:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (209, 92, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (210, 92, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (211, 92, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (92, 92, 'cash', 695.00, '2026-08-19 17:11:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (93, 456.00, 456.00, 0.00, 0.00, 476.00, 20.00, 476.00, 'cash', 'completed', 'RCPT-DEMO-20260819-0093', NULL, 'completed', 0.00, 1, '2026-08-19 18:21:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (212, 93, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (213, 93, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (93, 93, 'cash', 476.00, '2026-08-19 18:21:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (94, 387.00, 387.00, 0.00, 0.00, 397.00, 10.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260819-0094', 10, 'completed', 0.00, 1, '2026-08-19 11:09:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (214, 94, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (215, 94, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (94, 94, 'cash', 397.00, '2026-08-19 11:09:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (95, 456.00, 456.00, 0.00, 0.00, 506.00, 50.00, 506.00, 'cash', 'completed', 'RCPT-DEMO-20260819-0095', 9, 'completed', 0.00, 1, '2026-08-19 17:55:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (216, 95, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (217, 95, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (95, 95, 'cash', 506.00, '2026-08-19 17:55:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (96, 476.00, 476.00, 0.00, 0.00, 496.00, 20.00, 496.00, 'cash', 'completed', 'RCPT-DEMO-20260819-0096', 7, 'completed', 0.00, 1, '2026-08-19 11:07:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (218, 96, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (219, 96, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (220, 96, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (96, 96, 'cash', 496.00, '2026-08-19 11:07:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (97, 396.00, 396.00, 0.00, 0.00, 406.00, 10.00, 406.00, 'cash', 'completed', 'RCPT-DEMO-20260819-0097', NULL, 'completed', 0.00, 1, '2026-08-19 19:12:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (221, 97, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (222, 97, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (97, 97, 'cash', 406.00, '2026-08-19 19:12:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (98, 318.00, 318.00, 0.00, 0.00, 328.00, 10.00, 328.00, 'cash', 'completed', 'RCPT-DEMO-20260819-0098', 7, 'completed', 0.00, 1, '2026-08-19 12:10:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (223, 98, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (224, 98, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (98, 98, 'cash', 328.00, '2026-08-19 12:10:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (99, 337.00, 337.00, 0.00, 0.00, 337.00, 0.00, 337.00, 'cash', 'completed', 'RCPT-DEMO-20260819-0099', NULL, 'completed', 0.00, 1, '2026-08-19 19:48:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (225, 99, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (226, 99, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (99, 99, 'cash', 337.00, '2026-08-19 19:48:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (100, 347.00, 347.00, 0.00, 0.00, 347.00, 0.00, 347.00, 'cash', 'completed', 'RCPT-DEMO-20260819-0100', 8, 'completed', 0.00, 1, '2026-08-19 11:16:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (227, 100, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (228, 100, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (100, 100, 'cash', 347.00, '2026-08-19 11:16:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (101, 466.00, 466.00, 0.00, 0.00, 486.00, 20.00, 486.00, 'cash', 'completed', 'RCPT-DEMO-20260820-0101', 3, 'completed', 0.00, 1, '2026-08-20 12:12:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (229, 101, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (230, 101, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (231, 101, 7, 1, 119.00, 119.00, 'Spanish Latte', 'ITM0007');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (101, 101, 'cash', 486.00, '2026-08-20 12:12:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (102, 247.00, 247.00, 0.00, 0.00, 257.00, 10.00, 257.00, 'cash', 'completed', 'RCPT-DEMO-20260820-0102', 5, 'completed', 0.00, 1, '2026-08-20 18:05:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (232, 102, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (233, 102, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (102, 102, 'cash', 257.00, '2026-08-20 18:05:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (103, 347.00, 347.00, 0.00, 0.00, 357.00, 10.00, 357.00, 'cash', 'completed', 'RCPT-DEMO-20260820-0103', 2, 'completed', 0.00, 1, '2026-08-20 12:19:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (234, 103, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (235, 103, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (103, 103, 'cash', 357.00, '2026-08-20 12:19:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (104, 387.00, 387.00, 0.00, 0.00, 437.00, 50.00, 437.00, 'cash', 'completed', 'RCPT-DEMO-20260820-0104', NULL, 'completed', 0.00, 1, '2026-08-20 17:59:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (236, 104, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (237, 104, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (104, 104, 'cash', 437.00, '2026-08-20 17:59:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (105, 456.00, 456.00, 0.00, 0.00, 506.00, 50.00, 506.00, 'cash', 'completed', 'RCPT-DEMO-20260820-0105', 9, 'completed', 0.00, 1, '2026-08-20 17:29:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (238, 105, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (239, 105, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (105, 105, 'cash', 506.00, '2026-08-20 17:29:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (106, 228.00, 228.00, 0.00, 0.00, 228.00, 0.00, 228.00, 'cash', 'completed', 'RCPT-DEMO-20260820-0106', 3, 'completed', 0.00, 1, '2026-08-20 17:02:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (240, 106, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (241, 106, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (106, 106, 'cash', 228.00, '2026-08-20 17:02:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (107, 318.00, 318.00, 0.00, 0.00, 338.00, 20.00, 338.00, 'cash', 'completed', 'RCPT-DEMO-20260820-0107', 7, 'completed', 0.00, 1, '2026-08-20 17:49:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (242, 107, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (243, 107, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (107, 107, 'cash', 338.00, '2026-08-20 17:49:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (108, 318.00, 318.00, 0.00, 0.00, 338.00, 20.00, 338.00, 'cash', 'completed', 'RCPT-DEMO-20260820-0108', 2, 'completed', 0.00, 1, '2026-08-20 11:48:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (244, 108, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (245, 108, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (108, 108, 'cash', 338.00, '2026-08-20 11:48:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (109, 695.00, 695.00, 0.00, 0.00, 705.00, 10.00, 705.00, 'cash', 'completed', 'RCPT-DEMO-20260820-0109', 3, 'completed', 0.00, 1, '2026-08-20 12:16:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (246, 109, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (247, 109, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (248, 109, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (109, 109, 'cash', 705.00, '2026-08-20 12:16:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (110, 425.00, 425.00, 0.00, 0.00, 445.00, 20.00, 445.00, 'cash', 'completed', 'RCPT-DEMO-20260820-0110', 1, 'completed', 0.00, 1, '2026-08-20 13:01:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (249, 110, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (250, 110, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (251, 110, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (110, 110, 'cash', 445.00, '2026-08-20 13:01:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (111, 267.00, 267.00, 0.00, 0.00, 267.00, 0.00, 267.00, 'cash', 'completed', 'RCPT-DEMO-20260820-0111', NULL, 'completed', 0.00, 1, '2026-08-20 17:41:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (252, 111, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (253, 111, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (111, 111, 'cash', 267.00, '2026-08-20 17:41:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (112, 387.00, 387.00, 0.00, 0.00, 407.00, 20.00, 407.00, 'cash', 'completed', 'RCPT-DEMO-20260820-0112', 8, 'completed', 0.00, 1, '2026-08-20 13:16:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (254, 112, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (255, 112, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (112, 112, 'cash', 407.00, '2026-08-20 13:16:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (113, 158.00, 158.00, 0.00, 0.00, 168.00, 10.00, 168.00, 'cash', 'completed', 'RCPT-DEMO-20260821-0113', 4, 'completed', 0.00, 1, '2026-08-21 14:22:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (256, 113, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (257, 113, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (113, 113, 'cash', 168.00, '2026-08-21 14:22:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (114, 636.00, 636.00, 0.00, 0.00, 656.00, 20.00, 656.00, 'cash', 'completed', 'RCPT-DEMO-20260821-0114', 4, 'completed', 0.00, 1, '2026-08-21 12:25:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (258, 114, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (259, 114, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (114, 114, 'cash', 656.00, '2026-08-21 12:25:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (115, 278.00, 278.00, 0.00, 0.00, 298.00, 20.00, 298.00, 'cash', 'completed', 'RCPT-DEMO-20260821-0115', 3, 'completed', 0.00, 1, '2026-08-21 13:36:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (260, 115, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (261, 115, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (115, 115, 'cash', 298.00, '2026-08-21 13:36:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (116, 596.00, 596.00, 0.00, 0.00, 616.00, 20.00, 616.00, 'cash', 'completed', 'RCPT-DEMO-20260821-0116', NULL, 'completed', 0.00, 1, '2026-08-21 18:28:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (262, 116, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (263, 116, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (264, 116, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (116, 116, 'cash', 616.00, '2026-08-21 18:28:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (117, 337.00, 337.00, 0.00, 0.00, 337.00, 0.00, 337.00, 'cash', 'completed', 'RCPT-DEMO-20260821-0117', 4, 'completed', 0.00, 1, '2026-08-21 12:03:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (265, 117, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (266, 117, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (117, 117, 'cash', 337.00, '2026-08-21 12:03:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (118, 785.00, 785.00, 0.00, 0.00, 835.00, 50.00, 835.00, 'cash', 'completed', 'RCPT-DEMO-20260821-0118', 8, 'completed', 0.00, 1, '2026-08-21 19:50:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (267, 118, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (268, 118, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (269, 118, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (118, 118, 'cash', 835.00, '2026-08-21 19:50:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (119, 347.00, 347.00, 0.00, 0.00, 357.00, 10.00, 357.00, 'cash', 'completed', 'RCPT-DEMO-20260821-0119', NULL, 'completed', 0.00, 1, '2026-08-21 18:20:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (270, 119, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (271, 119, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (119, 119, 'cash', 357.00, '2026-08-21 18:20:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (120, 636.00, 636.00, 0.00, 0.00, 646.00, 10.00, 646.00, 'cash', 'completed', 'RCPT-DEMO-20260821-0120', 4, 'completed', 0.00, 1, '2026-08-21 18:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (272, 120, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (273, 120, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (120, 120, 'cash', 646.00, '2026-08-21 18:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (121, 556.00, 556.00, 0.00, 0.00, 576.00, 20.00, 576.00, 'cash', 'completed', 'RCPT-DEMO-20260821-0121', 8, 'completed', 0.00, 1, '2026-08-21 17:42:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (274, 121, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (275, 121, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (121, 121, 'cash', 576.00, '2026-08-21 17:42:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (122, 575.00, 575.00, 0.00, 0.00, 625.00, 50.00, 625.00, 'cash', 'completed', 'RCPT-DEMO-20260821-0122', NULL, 'completed', 0.00, 1, '2026-08-21 19:08:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (276, 122, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (277, 122, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (278, 122, 7, 1, 119.00, 119.00, 'Spanish Latte', 'ITM0007');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (122, 122, 'cash', 625.00, '2026-08-21 19:08:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (123, 556.00, 556.00, 0.00, 0.00, 576.00, 20.00, 576.00, 'cash', 'completed', 'RCPT-DEMO-20260821-0123', NULL, 'completed', 0.00, 1, '2026-08-21 12:40:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (279, 123, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (280, 123, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (123, 123, 'cash', 576.00, '2026-08-21 12:40:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (124, 456.00, 456.00, 0.00, 0.00, 476.00, 20.00, 476.00, 'cash', 'completed', 'RCPT-DEMO-20260821-0124', NULL, 'completed', 0.00, 1, '2026-08-21 17:39:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (281, 124, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (282, 124, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (124, 124, 'cash', 476.00, '2026-08-21 17:39:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (125, 248.00, 248.00, 0.00, 0.00, 248.00, 0.00, 248.00, 'cash', 'completed', 'RCPT-DEMO-20260821-0125', 5, 'completed', 0.00, 1, '2026-08-21 14:30:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (283, 125, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (284, 125, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (125, 125, 'cash', 248.00, '2026-08-21 14:30:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (126, 278.00, 278.00, 0.00, 0.00, 298.00, 20.00, 298.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0126', 3, 'completed', 0.00, 1, '2026-08-22 17:50:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (285, 126, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (286, 126, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (126, 126, 'cash', 298.00, '2026-08-22 17:50:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (127, 486.00, 486.00, 0.00, 0.00, 536.00, 50.00, 536.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0127', 4, 'completed', 0.00, 1, '2026-08-22 17:08:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (287, 127, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (288, 127, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (289, 127, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (127, 127, 'cash', 536.00, '2026-08-22 17:08:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (128, 496.00, 496.00, 0.00, 0.00, 496.00, 0.00, 496.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0128', 1, 'completed', 0.00, 1, '2026-08-22 11:15:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (290, 128, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (291, 128, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (292, 128, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (128, 128, 'cash', 496.00, '2026-08-22 11:15:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (129, 158.00, 158.00, 0.00, 0.00, 208.00, 50.00, 208.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0129', 9, 'completed', 0.00, 1, '2026-08-22 13:20:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (293, 129, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (294, 129, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (129, 129, 'cash', 208.00, '2026-08-22 13:20:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (130, 567.00, 567.00, 0.00, 0.00, 587.00, 20.00, 587.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0130', NULL, 'completed', 0.00, 1, '2026-08-22 11:04:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (295, 130, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (296, 130, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (130, 130, 'cash', 587.00, '2026-08-22 11:04:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (131, 505.00, 505.00, 0.00, 0.00, 555.00, 50.00, 555.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0131', 3, 'completed', 0.00, 1, '2026-08-22 18:36:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (297, 131, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (298, 131, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (299, 131, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (131, 131, 'cash', 555.00, '2026-08-22 18:36:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (132, 456.00, 456.00, 0.00, 0.00, 466.00, 10.00, 466.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0132', NULL, 'completed', 0.00, 1, '2026-08-22 14:04:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (300, 132, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (301, 132, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (302, 132, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (132, 132, 'cash', 466.00, '2026-08-22 14:04:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (133, 318.00, 318.00, 0.00, 0.00, 318.00, 0.00, 318.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0133', NULL, 'completed', 0.00, 1, '2026-08-22 18:46:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (303, 133, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (304, 133, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (133, 133, 'cash', 318.00, '2026-08-22 18:46:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (134, 476.00, 476.00, 0.00, 0.00, 476.00, 0.00, 476.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0134', 8, 'completed', 0.00, 1, '2026-08-22 18:10:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (305, 134, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (306, 134, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (307, 134, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (134, 134, 'cash', 476.00, '2026-08-22 18:10:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (135, 427.00, 427.00, 0.00, 0.00, 447.00, 20.00, 447.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0135', 1, 'completed', 0.00, 1, '2026-08-22 11:01:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (308, 135, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (309, 135, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (135, 135, 'cash', 447.00, '2026-08-22 11:01:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (136, 556.00, 556.00, 0.00, 0.00, 556.00, 0.00, 556.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0136', 3, 'completed', 0.00, 1, '2026-08-22 17:30:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (310, 136, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (311, 136, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (136, 136, 'cash', 556.00, '2026-08-22 17:30:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (137, 367.00, 367.00, 0.00, 0.00, 367.00, 0.00, 367.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0137', NULL, 'completed', 0.00, 1, '2026-08-22 11:31:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (312, 137, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (313, 137, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (137, 137, 'cash', 367.00, '2026-08-22 11:31:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (138, 316.00, 316.00, 0.00, 0.00, 366.00, 50.00, 366.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0138', 9, 'completed', 0.00, 1, '2026-08-22 13:13:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (314, 138, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (315, 138, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (138, 138, 'cash', 366.00, '2026-08-22 13:13:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (139, 397.00, 397.00, 0.00, 0.00, 417.00, 20.00, 417.00, 'cash', 'completed', 'RCPT-DEMO-20260822-0139', NULL, 'completed', 0.00, 1, '2026-08-22 12:06:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (316, 139, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (317, 139, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (139, 139, 'cash', 417.00, '2026-08-22 12:06:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (140, 337.00, 337.00, 0.00, 0.00, 387.00, 50.00, 387.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0140', NULL, 'completed', 0.00, 1, '2026-08-23 18:47:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (318, 140, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (319, 140, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (140, 140, 'cash', 387.00, '2026-08-23 18:47:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (141, 158.00, 158.00, 0.00, 0.00, 208.00, 50.00, 208.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0141', 2, 'completed', 0.00, 1, '2026-08-23 19:08:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (320, 141, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (321, 141, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (141, 141, 'cash', 208.00, '2026-08-23 19:08:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (142, 367.00, 367.00, 0.00, 0.00, 377.00, 10.00, 377.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0142', 10, 'completed', 0.00, 1, '2026-08-23 18:10:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (322, 142, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (323, 142, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (142, 142, 'cash', 377.00, '2026-08-23 18:10:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (143, 387.00, 387.00, 0.00, 0.00, 387.00, 0.00, 387.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0143', 6, 'completed', 0.00, 1, '2026-08-23 19:53:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (324, 143, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (325, 143, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (143, 143, 'cash', 387.00, '2026-08-23 19:53:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (144, 636.00, 636.00, 0.00, 0.00, 636.00, 0.00, 636.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0144', NULL, 'completed', 0.00, 1, '2026-08-23 14:39:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (326, 144, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (327, 144, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (144, 144, 'cash', 636.00, '2026-08-23 14:39:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (145, 267.00, 267.00, 0.00, 0.00, 287.00, 20.00, 287.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0145', NULL, 'completed', 0.00, 1, '2026-08-23 14:24:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (328, 145, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (329, 145, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (145, 145, 'cash', 287.00, '2026-08-23 14:24:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (146, 207.00, 207.00, 0.00, 0.00, 207.00, 0.00, 207.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0146', 6, 'completed', 0.00, 1, '2026-08-23 13:29:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (330, 146, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (331, 146, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (332, 146, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (146, 146, 'cash', 207.00, '2026-08-23 13:29:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (147, 636.00, 636.00, 0.00, 0.00, 636.00, 0.00, 636.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0147', NULL, 'completed', 0.00, 1, '2026-08-23 14:56:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (333, 147, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (334, 147, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (147, 147, 'cash', 636.00, '2026-08-23 14:56:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (148, 318.00, 318.00, 0.00, 0.00, 328.00, 10.00, 328.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0148', 6, 'completed', 0.00, 1, '2026-08-23 13:32:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (335, 148, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (336, 148, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (148, 148, 'cash', 328.00, '2026-08-23 13:32:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (149, 396.00, 396.00, 0.00, 0.00, 396.00, 0.00, 396.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0149', 1, 'completed', 0.00, 1, '2026-08-23 13:13:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (337, 149, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (338, 149, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (149, 149, 'cash', 396.00, '2026-08-23 13:13:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (150, 475.00, 475.00, 0.00, 0.00, 475.00, 0.00, 475.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0150', NULL, 'completed', 0.00, 1, '2026-08-23 18:17:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (339, 150, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (340, 150, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (341, 150, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (150, 150, 'cash', 475.00, '2026-08-23 18:17:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (151, 437.00, 437.00, 0.00, 0.00, 437.00, 0.00, 437.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0151', NULL, 'completed', 0.00, 1, '2026-08-23 18:53:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (342, 151, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (343, 151, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (151, 151, 'cash', 437.00, '2026-08-23 18:53:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (152, 605.00, 605.00, 0.00, 0.00, 605.00, 0.00, 605.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0152', 7, 'completed', 0.00, 1, '2026-08-23 17:36:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (344, 152, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (345, 152, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (346, 152, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (152, 152, 'cash', 605.00, '2026-08-23 17:36:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (153, 437.00, 437.00, 0.00, 0.00, 447.00, 10.00, 447.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0153', NULL, 'completed', 0.00, 1, '2026-08-23 14:34:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (347, 153, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (348, 153, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (153, 153, 'cash', 447.00, '2026-08-23 14:34:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (154, 316.00, 316.00, 0.00, 0.00, 326.00, 10.00, 326.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0154', 4, 'completed', 0.00, 1, '2026-08-23 17:12:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (349, 154, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (350, 154, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (154, 154, 'cash', 326.00, '2026-08-23 17:12:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (155, 367.00, 367.00, 0.00, 0.00, 377.00, 10.00, 377.00, 'cash', 'completed', 'RCPT-DEMO-20260823-0155', 4, 'completed', 0.00, 1, '2026-08-23 17:16:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (351, 155, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (352, 155, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (155, 155, 'cash', 377.00, '2026-08-23 17:16:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (156, 198.00, 198.00, 0.00, 0.00, 218.00, 20.00, 218.00, 'cash', 'completed', 'RCPT-DEMO-20260824-0156', 3, 'completed', 0.00, 1, '2026-08-24 19:00:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (353, 156, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (354, 156, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (156, 156, 'cash', 218.00, '2026-08-24 19:00:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (157, 268.00, 268.00, 0.00, 0.00, 318.00, 50.00, 318.00, 'cash', 'completed', 'RCPT-DEMO-20260824-0157', 4, 'completed', 0.00, 1, '2026-08-24 18:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (355, 157, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (356, 157, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (157, 157, 'cash', 318.00, '2026-08-24 18:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (158, 367.00, 367.00, 0.00, 0.00, 417.00, 50.00, 417.00, 'cash', 'completed', 'RCPT-DEMO-20260824-0158', 10, 'completed', 0.00, 1, '2026-08-24 13:41:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (357, 158, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (358, 158, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (158, 158, 'cash', 417.00, '2026-08-24 13:41:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (159, 686.00, 686.00, 0.00, 0.00, 736.00, 50.00, 736.00, 'cash', 'completed', 'RCPT-DEMO-20260824-0159', NULL, 'completed', 0.00, 1, '2026-08-24 13:25:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (359, 159, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (360, 159, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (361, 159, 7, 1, 119.00, 119.00, 'Spanish Latte', 'ITM0007');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (159, 159, 'cash', 736.00, '2026-08-24 13:25:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (160, 386.00, 386.00, 0.00, 0.00, 436.00, 50.00, 436.00, 'cash', 'completed', 'RCPT-DEMO-20260824-0160', 4, 'completed', 0.00, 1, '2026-08-24 19:26:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (362, 160, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (363, 160, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (364, 160, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (160, 160, 'cash', 436.00, '2026-08-24 19:26:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (161, 248.00, 248.00, 0.00, 0.00, 248.00, 0.00, 248.00, 'cash', 'completed', 'RCPT-DEMO-20260824-0161', NULL, 'completed', 0.00, 1, '2026-08-24 19:20:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (365, 161, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (366, 161, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (161, 161, 'cash', 248.00, '2026-08-24 19:20:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (162, 247.00, 247.00, 0.00, 0.00, 267.00, 20.00, 267.00, 'cash', 'completed', 'RCPT-DEMO-20260824-0162', NULL, 'completed', 0.00, 1, '2026-08-24 17:39:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (367, 162, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (368, 162, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (162, 162, 'cash', 267.00, '2026-08-24 17:39:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (163, 347.00, 347.00, 0.00, 0.00, 367.00, 20.00, 367.00, 'cash', 'completed', 'RCPT-DEMO-20260824-0163', 8, 'completed', 0.00, 1, '2026-08-24 17:09:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (369, 163, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (370, 163, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (163, 163, 'cash', 367.00, '2026-08-24 17:09:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (164, 536.00, 536.00, 0.00, 0.00, 546.00, 10.00, 546.00, 'cash', 'completed', 'RCPT-DEMO-20260824-0164', 9, 'completed', 0.00, 1, '2026-08-24 17:10:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (371, 164, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (372, 164, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (164, 164, 'cash', 546.00, '2026-08-24 17:10:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (165, 556.00, 556.00, 0.00, 0.00, 576.00, 20.00, 576.00, 'cash', 'completed', 'RCPT-DEMO-20260824-0165', 4, 'completed', 0.00, 1, '2026-08-24 17:15:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (373, 165, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (374, 165, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (165, 165, 'cash', 576.00, '2026-08-24 17:15:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (166, 496.00, 496.00, 0.00, 0.00, 516.00, 20.00, 516.00, 'cash', 'completed', 'RCPT-DEMO-20260824-0166', 7, 'completed', 0.00, 1, '2026-08-24 11:15:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (375, 166, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (376, 166, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (377, 166, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (166, 166, 'cash', 516.00, '2026-08-24 11:15:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (167, 496.00, 496.00, 0.00, 0.00, 516.00, 20.00, 516.00, 'cash', 'completed', 'RCPT-DEMO-20260824-0167', 1, 'completed', 0.00, 1, '2026-08-24 11:19:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (378, 167, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (379, 167, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (167, 167, 'cash', 516.00, '2026-08-24 11:19:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (168, 416.00, 416.00, 0.00, 0.00, 436.00, 20.00, 436.00, 'cash', 'completed', 'RCPT-DEMO-20260825-0168', NULL, 'completed', 0.00, 1, '2026-08-25 11:18:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (380, 168, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (381, 168, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (382, 168, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (168, 168, 'cash', 436.00, '2026-08-25 11:18:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (169, 396.00, 396.00, 0.00, 0.00, 406.00, 10.00, 406.00, 'cash', 'completed', 'RCPT-DEMO-20260825-0169', 9, 'completed', 0.00, 1, '2026-08-25 14:09:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (383, 169, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (384, 169, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (169, 169, 'cash', 406.00, '2026-08-25 14:09:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (170, 347.00, 347.00, 0.00, 0.00, 357.00, 10.00, 357.00, 'cash', 'completed', 'RCPT-DEMO-20260825-0170', 3, 'completed', 0.00, 1, '2026-08-25 19:27:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (385, 170, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (386, 170, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (170, 170, 'cash', 357.00, '2026-08-25 19:27:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (171, 437.00, 437.00, 0.00, 0.00, 437.00, 0.00, 437.00, 'cash', 'completed', 'RCPT-DEMO-20260825-0171', 7, 'completed', 0.00, 1, '2026-08-25 18:00:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (387, 171, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (388, 171, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (171, 171, 'cash', 437.00, '2026-08-25 18:00:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (172, 496.00, 496.00, 0.00, 0.00, 496.00, 0.00, 496.00, 'cash', 'completed', 'RCPT-DEMO-20260825-0172', 6, 'completed', 0.00, 1, '2026-08-25 17:55:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (389, 172, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (390, 172, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (172, 172, 'cash', 496.00, '2026-08-25 17:55:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (173, 268.00, 268.00, 0.00, 0.00, 288.00, 20.00, 288.00, 'cash', 'completed', 'RCPT-DEMO-20260825-0173', 6, 'completed', 0.00, 1, '2026-08-25 18:59:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (391, 173, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (392, 173, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (173, 173, 'cash', 288.00, '2026-08-25 18:59:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (174, 377.00, 377.00, 0.00, 0.00, 427.00, 50.00, 427.00, 'cash', 'completed', 'RCPT-DEMO-20260825-0174', 2, 'completed', 0.00, 1, '2026-08-25 14:09:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (393, 174, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (394, 174, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (174, 174, 'cash', 427.00, '2026-08-25 14:09:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (175, 248.00, 248.00, 0.00, 0.00, 258.00, 10.00, 258.00, 'cash', 'completed', 'RCPT-DEMO-20260825-0175', NULL, 'completed', 0.00, 1, '2026-08-25 19:19:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (395, 175, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (396, 175, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (175, 175, 'cash', 258.00, '2026-08-25 19:19:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (176, 367.00, 367.00, 0.00, 0.00, 417.00, 50.00, 417.00, 'cash', 'completed', 'RCPT-DEMO-20260825-0176', 8, 'completed', 0.00, 1, '2026-08-25 19:54:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (397, 176, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (398, 176, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (176, 176, 'cash', 417.00, '2026-08-25 19:54:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (177, 556.00, 556.00, 0.00, 0.00, 576.00, 20.00, 576.00, 'cash', 'completed', 'RCPT-DEMO-20260825-0177', NULL, 'completed', 0.00, 1, '2026-08-25 18:06:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (399, 177, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (400, 177, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (177, 177, 'cash', 576.00, '2026-08-25 18:06:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (178, 377.00, 377.00, 0.00, 0.00, 427.00, 50.00, 427.00, 'cash', 'completed', 'RCPT-DEMO-20260826-0178', NULL, 'completed', 0.00, 1, '2026-08-26 13:57:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (401, 178, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (402, 178, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (178, 178, 'cash', 427.00, '2026-08-26 13:57:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (179, 367.00, 367.00, 0.00, 0.00, 367.00, 0.00, 367.00, 'cash', 'completed', 'RCPT-DEMO-20260826-0179', 6, 'completed', 0.00, 1, '2026-08-26 12:06:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (403, 179, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (404, 179, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (179, 179, 'cash', 367.00, '2026-08-26 12:06:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (180, 446.00, 446.00, 0.00, 0.00, 456.00, 10.00, 456.00, 'cash', 'completed', 'RCPT-DEMO-20260826-0180', 8, 'completed', 0.00, 1, '2026-08-26 11:50:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (405, 180, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (406, 180, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (407, 180, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (180, 180, 'cash', 456.00, '2026-08-26 11:50:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (181, 268.00, 268.00, 0.00, 0.00, 288.00, 20.00, 288.00, 'cash', 'completed', 'RCPT-DEMO-20260826-0181', 4, 'completed', 0.00, 1, '2026-08-26 13:49:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (408, 181, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (409, 181, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (181, 181, 'cash', 288.00, '2026-08-26 13:49:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (182, 347.00, 347.00, 0.00, 0.00, 397.00, 50.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260826-0182', 5, 'completed', 0.00, 1, '2026-08-26 18:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (410, 182, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (411, 182, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (182, 182, 'cash', 397.00, '2026-08-26 18:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (183, 356.00, 356.00, 0.00, 0.00, 366.00, 10.00, 366.00, 'cash', 'completed', 'RCPT-DEMO-20260826-0183', NULL, 'completed', 0.00, 1, '2026-08-26 14:05:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (412, 183, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (413, 183, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (414, 183, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (183, 183, 'cash', 366.00, '2026-08-26 14:05:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (184, 198.00, 198.00, 0.00, 0.00, 248.00, 50.00, 248.00, 'cash', 'completed', 'RCPT-DEMO-20260826-0184', 10, 'completed', 0.00, 1, '2026-08-26 19:54:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (415, 184, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (416, 184, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (184, 184, 'cash', 248.00, '2026-08-26 19:54:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (185, 228.00, 228.00, 0.00, 0.00, 278.00, 50.00, 278.00, 'cash', 'completed', 'RCPT-DEMO-20260826-0185', NULL, 'completed', 0.00, 1, '2026-08-26 11:08:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (417, 185, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (418, 185, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (185, 185, 'cash', 278.00, '2026-08-26 11:08:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (186, 486.00, 486.00, 0.00, 0.00, 496.00, 10.00, 496.00, 'cash', 'completed', 'RCPT-DEMO-20260826-0186', 2, 'completed', 0.00, 1, '2026-08-26 11:08:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (419, 186, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (420, 186, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (421, 186, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (186, 186, 'cash', 496.00, '2026-08-26 11:08:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (187, 496.00, 496.00, 0.00, 0.00, 496.00, 0.00, 496.00, 'cash', 'completed', 'RCPT-DEMO-20260826-0187', 7, 'completed', 0.00, 1, '2026-08-26 19:17:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (422, 187, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (423, 187, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (187, 187, 'cash', 496.00, '2026-08-26 19:17:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (188, 567.00, 567.00, 0.00, 0.00, 577.00, 10.00, 577.00, 'cash', 'completed', 'RCPT-DEMO-20260826-0188', 2, 'completed', 0.00, 1, '2026-08-26 12:36:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (424, 188, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (425, 188, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (188, 188, 'cash', 577.00, '2026-08-26 12:36:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (189, 365.00, 365.00, 0.00, 0.00, 385.00, 20.00, 385.00, 'cash', 'completed', 'RCPT-DEMO-20260826-0189', 2, 'completed', 0.00, 1, '2026-08-26 11:52:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (426, 189, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (427, 189, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (428, 189, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (189, 189, 'cash', 385.00, '2026-08-26 11:52:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (190, 316.00, 316.00, 0.00, 0.00, 336.00, 20.00, 336.00, 'cash', 'completed', 'RCPT-DEMO-20260826-0190', 7, 'completed', 0.00, 1, '2026-08-26 19:22:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (429, 190, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (430, 190, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (190, 190, 'cash', 336.00, '2026-08-26 19:22:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (191, 158.00, 158.00, 0.00, 0.00, 168.00, 10.00, 168.00, 'cash', 'completed', 'RCPT-DEMO-20260827-0191', 10, 'completed', 0.00, 1, '2026-08-27 19:59:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (431, 191, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (432, 191, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (191, 191, 'cash', 168.00, '2026-08-27 19:59:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (192, 397.00, 397.00, 0.00, 0.00, 417.00, 20.00, 417.00, 'cash', 'completed', 'RCPT-DEMO-20260827-0192', 8, 'completed', 0.00, 1, '2026-08-27 14:48:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (433, 192, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (434, 192, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (192, 192, 'cash', 417.00, '2026-08-27 14:48:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (193, 367.00, 367.00, 0.00, 0.00, 377.00, 10.00, 377.00, 'cash', 'completed', 'RCPT-DEMO-20260827-0193', 4, 'completed', 0.00, 1, '2026-08-27 18:13:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (435, 193, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (436, 193, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (193, 193, 'cash', 377.00, '2026-08-27 18:13:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (194, 357.00, 357.00, 0.00, 0.00, 377.00, 20.00, 377.00, 'cash', 'completed', 'RCPT-DEMO-20260827-0194', NULL, 'completed', 0.00, 1, '2026-08-27 17:26:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (437, 194, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (438, 194, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (439, 194, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (194, 194, 'cash', 377.00, '2026-08-27 17:26:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (195, 567.00, 567.00, 0.00, 0.00, 617.00, 50.00, 617.00, 'cash', 'completed', 'RCPT-DEMO-20260827-0195', 2, 'completed', 0.00, 1, '2026-08-27 12:00:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (440, 195, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (441, 195, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (195, 195, 'cash', 617.00, '2026-08-27 12:00:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (196, 158.00, 158.00, 0.00, 0.00, 168.00, 10.00, 168.00, 'cash', 'completed', 'RCPT-DEMO-20260827-0196', 9, 'completed', 0.00, 1, '2026-08-27 19:11:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (442, 196, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (443, 196, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (196, 196, 'cash', 168.00, '2026-08-27 19:11:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (197, 496.00, 496.00, 0.00, 0.00, 546.00, 50.00, 546.00, 'cash', 'completed', 'RCPT-DEMO-20260827-0197', 7, 'completed', 0.00, 1, '2026-08-27 12:38:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (444, 197, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (445, 197, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (197, 197, 'cash', 546.00, '2026-08-27 12:38:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (198, 536.00, 536.00, 0.00, 0.00, 556.00, 20.00, 556.00, 'cash', 'completed', 'RCPT-DEMO-20260827-0198', 4, 'completed', 0.00, 1, '2026-08-27 19:44:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (446, 198, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (447, 198, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (448, 198, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (198, 198, 'cash', 556.00, '2026-08-27 19:44:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (199, 228.00, 228.00, 0.00, 0.00, 228.00, 0.00, 228.00, 'cash', 'completed', 'RCPT-DEMO-20260827-0199', 9, 'completed', 0.00, 1, '2026-08-27 12:38:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (449, 199, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (450, 199, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (199, 199, 'cash', 228.00, '2026-08-27 12:38:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (200, 636.00, 636.00, 0.00, 0.00, 686.00, 50.00, 686.00, 'cash', 'completed', 'RCPT-DEMO-20260827-0200', 9, 'completed', 0.00, 1, '2026-08-27 19:22:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (451, 200, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (452, 200, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (200, 200, 'cash', 686.00, '2026-08-27 19:22:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (201, 556.00, 556.00, 0.00, 0.00, 606.00, 50.00, 606.00, 'cash', 'completed', 'RCPT-DEMO-20260827-0201', 8, 'completed', 0.00, 1, '2026-08-27 17:44:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (453, 201, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (454, 201, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (201, 201, 'cash', 606.00, '2026-08-27 17:44:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (202, 456.00, 456.00, 0.00, 0.00, 456.00, 0.00, 456.00, 'cash', 'completed', 'RCPT-DEMO-20260828-0202', 1, 'completed', 0.00, 1, '2026-08-28 12:41:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (455, 202, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (456, 202, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (202, 202, 'cash', 456.00, '2026-08-28 12:41:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (203, 247.00, 247.00, 0.00, 0.00, 247.00, 0.00, 247.00, 'cash', 'completed', 'RCPT-DEMO-20260828-0203', 9, 'completed', 0.00, 1, '2026-08-28 11:40:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (457, 203, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (458, 203, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (203, 203, 'cash', 247.00, '2026-08-28 11:40:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (204, 396.00, 396.00, 0.00, 0.00, 396.00, 0.00, 396.00, 'cash', 'completed', 'RCPT-DEMO-20260828-0204', NULL, 'completed', 0.00, 1, '2026-08-28 12:01:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (459, 204, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (460, 204, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (204, 204, 'cash', 396.00, '2026-08-28 12:01:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (205, 278.00, 278.00, 0.00, 0.00, 298.00, 20.00, 298.00, 'cash', 'completed', 'RCPT-DEMO-20260828-0205', 10, 'completed', 0.00, 1, '2026-08-28 14:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (461, 205, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (462, 205, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (205, 205, 'cash', 298.00, '2026-08-28 14:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (206, 347.00, 347.00, 0.00, 0.00, 367.00, 20.00, 367.00, 'cash', 'completed', 'RCPT-DEMO-20260828-0206', NULL, 'completed', 0.00, 1, '2026-08-28 12:26:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (463, 206, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (464, 206, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (206, 206, 'cash', 367.00, '2026-08-28 12:26:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (207, 228.00, 228.00, 0.00, 0.00, 228.00, 0.00, 228.00, 'cash', 'completed', 'RCPT-DEMO-20260828-0207', 9, 'completed', 0.00, 1, '2026-08-28 14:23:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (465, 207, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (466, 207, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (207, 207, 'cash', 228.00, '2026-08-28 14:23:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (208, 516.00, 516.00, 0.00, 0.00, 516.00, 0.00, 516.00, 'cash', 'completed', 'RCPT-DEMO-20260828-0208', 2, 'completed', 0.00, 1, '2026-08-28 14:49:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (467, 208, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (468, 208, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (469, 208, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (208, 208, 'cash', 516.00, '2026-08-28 14:49:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (209, 377.00, 377.00, 0.00, 0.00, 397.00, 20.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260828-0209', 2, 'completed', 0.00, 1, '2026-08-28 14:22:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (470, 209, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (471, 209, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (472, 209, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (209, 209, 'cash', 397.00, '2026-08-28 14:22:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (210, 347.00, 347.00, 0.00, 0.00, 397.00, 50.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260828-0210', 1, 'completed', 0.00, 1, '2026-08-28 17:41:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (473, 210, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (474, 210, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (475, 210, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (210, 210, 'cash', 397.00, '2026-08-28 17:41:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (211, 367.00, 367.00, 0.00, 0.00, 417.00, 50.00, 417.00, 'cash', 'completed', 'RCPT-DEMO-20260828-0211', NULL, 'completed', 0.00, 1, '2026-08-28 19:50:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (476, 211, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (477, 211, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (211, 211, 'cash', 417.00, '2026-08-28 19:50:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (212, 158.00, 158.00, 0.00, 0.00, 178.00, 20.00, 178.00, 'cash', 'completed', 'RCPT-DEMO-20260828-0212', 8, 'completed', 0.00, 1, '2026-08-28 17:53:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (478, 212, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (479, 212, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (212, 212, 'cash', 178.00, '2026-08-28 17:53:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (213, 456.00, 456.00, 0.00, 0.00, 456.00, 0.00, 456.00, 'cash', 'completed', 'RCPT-DEMO-20260828-0213', 4, 'completed', 0.00, 1, '2026-08-28 18:56:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (480, 213, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (481, 213, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (213, 213, 'cash', 456.00, '2026-08-28 18:56:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (214, 267.00, 267.00, 0.00, 0.00, 277.00, 10.00, 277.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0214', 3, 'completed', 0.00, 1, '2026-08-29 13:41:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (482, 214, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (483, 214, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (214, 214, 'cash', 277.00, '2026-08-29 13:41:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (215, 456.00, 456.00, 0.00, 0.00, 476.00, 20.00, 476.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0215', NULL, 'completed', 0.00, 1, '2026-08-29 19:42:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (484, 215, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (485, 215, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (215, 215, 'cash', 476.00, '2026-08-29 19:42:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (216, 567.00, 567.00, 0.00, 0.00, 567.00, 0.00, 567.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0216', NULL, 'completed', 0.00, 1, '2026-08-29 19:18:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (486, 216, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (487, 216, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (216, 216, 'cash', 567.00, '2026-08-29 19:18:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (217, 686.00, 686.00, 0.00, 0.00, 736.00, 50.00, 736.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0217', 1, 'completed', 0.00, 1, '2026-08-29 17:18:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (488, 217, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (489, 217, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (490, 217, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (217, 217, 'cash', 736.00, '2026-08-29 17:18:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (218, 567.00, 567.00, 0.00, 0.00, 587.00, 20.00, 587.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0218', NULL, 'completed', 0.00, 1, '2026-08-29 17:50:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (491, 218, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (492, 218, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (218, 218, 'cash', 587.00, '2026-08-29 17:50:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (219, 427.00, 427.00, 0.00, 0.00, 427.00, 0.00, 427.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0219', 9, 'completed', 0.00, 1, '2026-08-29 11:50:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (493, 219, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (494, 219, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (219, 219, 'cash', 427.00, '2026-08-29 11:50:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (220, 396.00, 396.00, 0.00, 0.00, 396.00, 0.00, 396.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0220', 3, 'completed', 0.00, 1, '2026-08-29 13:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (495, 220, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (496, 220, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (220, 220, 'cash', 396.00, '2026-08-29 13:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (221, 198.00, 198.00, 0.00, 0.00, 198.00, 0.00, 198.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0221', 7, 'completed', 0.00, 1, '2026-08-29 18:41:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (497, 221, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (498, 221, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (221, 221, 'cash', 198.00, '2026-08-29 18:41:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (222, 158.00, 158.00, 0.00, 0.00, 168.00, 10.00, 168.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0222', NULL, 'completed', 0.00, 1, '2026-08-29 17:30:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (499, 222, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (500, 222, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (222, 222, 'cash', 168.00, '2026-08-29 17:30:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (223, 347.00, 347.00, 0.00, 0.00, 367.00, 20.00, 367.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0223', 1, 'completed', 0.00, 1, '2026-08-29 17:28:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (501, 223, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (502, 223, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (223, 223, 'cash', 367.00, '2026-08-29 17:28:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (224, 466.00, 466.00, 0.00, 0.00, 516.00, 50.00, 516.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0224', 3, 'completed', 0.00, 1, '2026-08-29 17:04:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (503, 224, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (504, 224, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (505, 224, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (224, 224, 'cash', 516.00, '2026-08-29 17:04:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (225, 427.00, 427.00, 0.00, 0.00, 477.00, 50.00, 477.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0225', 2, 'completed', 0.00, 1, '2026-08-29 18:24:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (506, 225, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (507, 225, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (225, 225, 'cash', 477.00, '2026-08-29 18:24:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (226, 505.00, 505.00, 0.00, 0.00, 515.00, 10.00, 515.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0226', NULL, 'completed', 0.00, 1, '2026-08-29 17:44:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (508, 226, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (509, 226, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (510, 226, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (226, 226, 'cash', 515.00, '2026-08-29 17:44:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (227, 228.00, 228.00, 0.00, 0.00, 228.00, 0.00, 228.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0227', 8, 'completed', 0.00, 1, '2026-08-29 18:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (511, 227, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (512, 227, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (227, 227, 'cash', 228.00, '2026-08-29 18:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (228, 685.00, 685.00, 0.00, 0.00, 705.00, 20.00, 705.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0228', 7, 'completed', 0.00, 1, '2026-08-29 13:32:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (513, 228, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (514, 228, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (515, 228, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (228, 228, 'cash', 705.00, '2026-08-29 13:32:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (229, 536.00, 536.00, 0.00, 0.00, 586.00, 50.00, 586.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0229', 7, 'completed', 0.00, 1, '2026-08-29 12:29:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (516, 229, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (517, 229, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (518, 229, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (229, 229, 'cash', 586.00, '2026-08-29 12:29:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (230, 278.00, 278.00, 0.00, 0.00, 298.00, 20.00, 298.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0230', NULL, 'completed', 0.00, 1, '2026-08-29 11:24:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (519, 230, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (520, 230, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (230, 230, 'cash', 298.00, '2026-08-29 11:24:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (231, 267.00, 267.00, 0.00, 0.00, 277.00, 10.00, 277.00, 'cash', 'completed', 'RCPT-DEMO-20260829-0231', 3, 'completed', 0.00, 1, '2026-08-29 17:42:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (521, 231, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (522, 231, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (231, 231, 'cash', 277.00, '2026-08-29 17:42:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (232, 685.00, 685.00, 0.00, 0.00, 735.00, 50.00, 735.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0232', 2, 'completed', 0.00, 1, '2026-08-30 12:23:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (523, 232, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (524, 232, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (525, 232, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (232, 232, 'cash', 735.00, '2026-08-30 12:23:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (233, 496.00, 496.00, 0.00, 0.00, 506.00, 10.00, 506.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0233', 2, 'completed', 0.00, 1, '2026-08-30 12:29:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (526, 233, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (527, 233, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (233, 233, 'cash', 506.00, '2026-08-30 12:29:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (234, 367.00, 367.00, 0.00, 0.00, 417.00, 50.00, 417.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0234', 3, 'completed', 0.00, 1, '2026-08-30 12:31:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (528, 234, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (529, 234, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (234, 234, 'cash', 417.00, '2026-08-30 12:31:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (235, 437.00, 437.00, 0.00, 0.00, 447.00, 10.00, 447.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0235', 2, 'completed', 0.00, 1, '2026-08-30 17:46:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (530, 235, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (531, 235, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (235, 235, 'cash', 447.00, '2026-08-30 17:46:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (236, 316.00, 316.00, 0.00, 0.00, 316.00, 0.00, 316.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0236', NULL, 'completed', 0.00, 1, '2026-08-30 18:13:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (532, 236, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (533, 236, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (236, 236, 'cash', 316.00, '2026-08-30 18:13:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (237, 267.00, 267.00, 0.00, 0.00, 277.00, 10.00, 277.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0237', 3, 'completed', 0.00, 1, '2026-08-30 13:25:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (534, 237, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (535, 237, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (237, 237, 'cash', 277.00, '2026-08-30 13:25:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (238, 655.00, 655.00, 0.00, 0.00, 665.00, 10.00, 665.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0238', NULL, 'completed', 0.00, 1, '2026-08-30 14:22:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (536, 238, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (537, 238, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (538, 238, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (238, 238, 'cash', 665.00, '2026-08-30 14:22:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (239, 337.00, 337.00, 0.00, 0.00, 357.00, 20.00, 357.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0239', 3, 'completed', 0.00, 1, '2026-08-30 14:55:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (539, 239, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (540, 239, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (239, 239, 'cash', 357.00, '2026-08-30 14:55:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (240, 437.00, 437.00, 0.00, 0.00, 457.00, 20.00, 457.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0240', NULL, 'completed', 0.00, 1, '2026-08-30 17:51:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (541, 240, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (542, 240, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (543, 240, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (240, 240, 'cash', 457.00, '2026-08-30 17:51:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (241, 158.00, 158.00, 0.00, 0.00, 208.00, 50.00, 208.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0241', 6, 'completed', 0.00, 1, '2026-08-30 13:09:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (544, 241, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (545, 241, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (241, 241, 'cash', 208.00, '2026-08-30 13:09:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (242, 228.00, 228.00, 0.00, 0.00, 278.00, 50.00, 278.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0242', 9, 'completed', 0.00, 1, '2026-08-30 11:54:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (546, 242, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (547, 242, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (242, 242, 'cash', 278.00, '2026-08-30 11:54:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (243, 397.00, 397.00, 0.00, 0.00, 417.00, 20.00, 417.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0243', 8, 'completed', 0.00, 1, '2026-08-30 11:19:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (548, 243, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (549, 243, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (243, 243, 'cash', 417.00, '2026-08-30 11:19:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (244, 397.00, 397.00, 0.00, 0.00, 407.00, 10.00, 407.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0244', NULL, 'completed', 0.00, 1, '2026-08-30 19:10:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (550, 244, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (551, 244, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (552, 244, 7, 1, 119.00, 119.00, 'Spanish Latte', 'ITM0007');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (244, 244, 'cash', 407.00, '2026-08-30 19:10:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (245, 397.00, 397.00, 0.00, 0.00, 397.00, 0.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260830-0245', 8, 'completed', 0.00, 1, '2026-08-30 13:07:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (553, 245, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (554, 245, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (555, 245, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (245, 245, 'cash', 397.00, '2026-08-30 13:07:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (246, 396.00, 396.00, 0.00, 0.00, 406.00, 10.00, 406.00, 'cash', 'completed', 'RCPT-DEMO-20260831-0246', 1, 'completed', 0.00, 1, '2026-08-31 12:30:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (556, 246, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (557, 246, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (246, 246, 'cash', 406.00, '2026-08-31 12:30:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (247, 347.00, 347.00, 0.00, 0.00, 397.00, 50.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260831-0247', 1, 'completed', 0.00, 1, '2026-08-31 13:31:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (558, 247, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (559, 247, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (247, 247, 'cash', 397.00, '2026-08-31 13:31:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (248, 366.00, 366.00, 0.00, 0.00, 386.00, 20.00, 386.00, 'cash', 'completed', 'RCPT-DEMO-20260831-0248', NULL, 'completed', 0.00, 1, '2026-08-31 11:57:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (560, 248, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (561, 248, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (562, 248, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (248, 248, 'cash', 386.00, '2026-08-31 11:57:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (249, 278.00, 278.00, 0.00, 0.00, 328.00, 50.00, 328.00, 'cash', 'completed', 'RCPT-DEMO-20260831-0249', NULL, 'completed', 0.00, 1, '2026-08-31 17:42:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (563, 249, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (564, 249, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (249, 249, 'cash', 328.00, '2026-08-31 17:42:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (250, 267.00, 267.00, 0.00, 0.00, 277.00, 10.00, 277.00, 'cash', 'completed', 'RCPT-DEMO-20260901-0250', 9, 'completed', 0.00, 1, '2026-09-01 12:54:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (565, 250, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (566, 250, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (250, 250, 'cash', 277.00, '2026-09-01 12:54:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (251, 397.00, 397.00, 0.00, 0.00, 407.00, 10.00, 407.00, 'cash', 'completed', 'RCPT-DEMO-20260901-0251', 10, 'completed', 0.00, 1, '2026-09-01 18:13:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (567, 251, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (568, 251, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (251, 251, 'cash', 407.00, '2026-09-01 18:13:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (252, 456.00, 456.00, 0.00, 0.00, 456.00, 0.00, 456.00, 'cash', 'completed', 'RCPT-DEMO-20260901-0252', NULL, 'completed', 0.00, 1, '2026-09-01 19:59:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (569, 252, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (570, 252, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (252, 252, 'cash', 456.00, '2026-09-01 19:59:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (253, 158.00, 158.00, 0.00, 0.00, 158.00, 0.00, 158.00, 'cash', 'completed', 'RCPT-DEMO-20260901-0253', NULL, 'completed', 0.00, 1, '2026-09-01 12:55:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (571, 253, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (572, 253, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (253, 253, 'cash', 158.00, '2026-09-01 12:55:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (254, 556.00, 556.00, 0.00, 0.00, 556.00, 0.00, 556.00, 'cash', 'completed', 'RCPT-DEMO-20260901-0254', NULL, 'completed', 0.00, 1, '2026-09-01 18:46:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (573, 254, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (574, 254, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (254, 254, 'cash', 556.00, '2026-09-01 18:46:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (255, 228.00, 228.00, 0.00, 0.00, 278.00, 50.00, 278.00, 'cash', 'completed', 'RCPT-DEMO-20260901-0255', NULL, 'completed', 0.00, 1, '2026-09-01 17:07:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (575, 255, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (576, 255, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (255, 255, 'cash', 278.00, '2026-09-01 17:07:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (256, 377.00, 377.00, 0.00, 0.00, 387.00, 10.00, 387.00, 'cash', 'completed', 'RCPT-DEMO-20260901-0256', 10, 'completed', 0.00, 1, '2026-09-01 14:37:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (577, 256, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (578, 256, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (256, 256, 'cash', 387.00, '2026-09-01 14:37:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (257, 347.00, 347.00, 0.00, 0.00, 357.00, 10.00, 357.00, 'cash', 'completed', 'RCPT-DEMO-20260901-0257', NULL, 'completed', 0.00, 1, '2026-09-01 17:01:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (579, 257, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (580, 257, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (257, 257, 'cash', 357.00, '2026-09-01 17:01:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (258, 567.00, 567.00, 0.00, 0.00, 587.00, 20.00, 587.00, 'cash', 'completed', 'RCPT-DEMO-20260901-0258', 4, 'completed', 0.00, 1, '2026-09-01 18:29:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (581, 258, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (582, 258, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (258, 258, 'cash', 587.00, '2026-09-01 18:29:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (259, 296.00, 296.00, 0.00, 0.00, 346.00, 50.00, 346.00, 'cash', 'completed', 'RCPT-DEMO-20260901-0259', 6, 'completed', 0.00, 1, '2026-09-01 13:01:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (583, 259, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (584, 259, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (585, 259, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (259, 259, 'cash', 346.00, '2026-09-01 13:01:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (260, 367.00, 367.00, 0.00, 0.00, 377.00, 10.00, 377.00, 'cash', 'completed', 'RCPT-DEMO-20260901-0260', NULL, 'completed', 0.00, 1, '2026-09-01 13:14:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (586, 260, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (587, 260, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (588, 260, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (260, 260, 'cash', 377.00, '2026-09-01 13:14:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (261, 365.00, 365.00, 0.00, 0.00, 365.00, 0.00, 365.00, 'cash', 'completed', 'RCPT-DEMO-20260901-0261', 7, 'completed', 0.00, 1, '2026-09-01 14:50:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (589, 261, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (590, 261, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (591, 261, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (261, 261, 'cash', 365.00, '2026-09-01 14:50:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (262, 396.00, 396.00, 0.00, 0.00, 396.00, 0.00, 396.00, 'cash', 'completed', 'RCPT-DEMO-20260901-0262', 2, 'completed', 0.00, 1, '2026-09-01 19:21:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (592, 262, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (593, 262, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (262, 262, 'cash', 396.00, '2026-09-01 19:21:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (263, 347.00, 347.00, 0.00, 0.00, 347.00, 0.00, 347.00, 'cash', 'completed', 'RCPT-DEMO-20260902-0263', 9, 'completed', 0.00, 1, '2026-09-02 18:24:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (594, 263, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (595, 263, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (263, 263, 'cash', 347.00, '2026-09-02 18:24:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (264, 476.00, 476.00, 0.00, 0.00, 486.00, 10.00, 486.00, 'cash', 'completed', 'RCPT-DEMO-20260902-0264', NULL, 'completed', 0.00, 1, '2026-09-02 19:18:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (596, 264, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (597, 264, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (598, 264, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (264, 264, 'cash', 486.00, '2026-09-02 19:18:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (265, 515.00, 515.00, 0.00, 0.00, 565.00, 50.00, 565.00, 'cash', 'completed', 'RCPT-DEMO-20260902-0265', NULL, 'completed', 0.00, 1, '2026-09-02 17:44:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (599, 265, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (600, 265, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (601, 265, 7, 1, 119.00, 119.00, 'Spanish Latte', 'ITM0007');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (265, 265, 'cash', 565.00, '2026-09-02 17:44:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (266, 636.00, 636.00, 0.00, 0.00, 646.00, 10.00, 646.00, 'cash', 'completed', 'RCPT-DEMO-20260902-0266', 10, 'completed', 0.00, 1, '2026-09-02 13:01:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (602, 266, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (603, 266, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (266, 266, 'cash', 646.00, '2026-09-02 13:01:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (267, 486.00, 486.00, 0.00, 0.00, 536.00, 50.00, 536.00, 'cash', 'completed', 'RCPT-DEMO-20260902-0267', 10, 'completed', 0.00, 1, '2026-09-02 17:17:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (604, 267, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (605, 267, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (606, 267, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (267, 267, 'cash', 536.00, '2026-09-02 17:17:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (268, 367.00, 367.00, 0.00, 0.00, 377.00, 10.00, 377.00, 'cash', 'completed', 'RCPT-DEMO-20260902-0268', 4, 'completed', 0.00, 1, '2026-09-02 18:09:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (607, 268, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (608, 268, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (268, 268, 'cash', 377.00, '2026-09-02 18:09:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (269, 536.00, 536.00, 0.00, 0.00, 536.00, 0.00, 536.00, 'cash', 'completed', 'RCPT-DEMO-20260902-0269', 1, 'completed', 0.00, 1, '2026-09-02 18:50:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (609, 269, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (610, 269, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (269, 269, 'cash', 536.00, '2026-09-02 18:50:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (270, 556.00, 556.00, 0.00, 0.00, 556.00, 0.00, 556.00, 'cash', 'completed', 'RCPT-DEMO-20260902-0270', 1, 'completed', 0.00, 1, '2026-09-02 14:02:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (611, 270, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (612, 270, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (270, 270, 'cash', 556.00, '2026-09-02 14:02:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (271, 397.00, 397.00, 0.00, 0.00, 447.00, 50.00, 447.00, 'cash', 'completed', 'RCPT-DEMO-20260902-0271', NULL, 'completed', 0.00, 1, '2026-09-02 17:47:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (613, 271, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (614, 271, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (615, 271, 7, 1, 119.00, 119.00, 'Spanish Latte', 'ITM0007');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (271, 271, 'cash', 447.00, '2026-09-02 17:47:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (272, 556.00, 556.00, 0.00, 0.00, 556.00, 0.00, 556.00, 'cash', 'completed', 'RCPT-DEMO-20260902-0272', 2, 'completed', 0.00, 1, '2026-09-02 17:17:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (616, 272, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (617, 272, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (272, 272, 'cash', 556.00, '2026-09-02 17:17:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (273, 496.00, 496.00, 0.00, 0.00, 506.00, 10.00, 506.00, 'cash', 'completed', 'RCPT-DEMO-20260902-0273', 2, 'completed', 0.00, 1, '2026-09-02 14:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (618, 273, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (619, 273, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (273, 273, 'cash', 506.00, '2026-09-02 14:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (274, 427.00, 427.00, 0.00, 0.00, 437.00, 10.00, 437.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0274', 3, 'completed', 0.00, 1, '2026-09-03 11:04:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (620, 274, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (621, 274, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (274, 274, 'cash', 437.00, '2026-09-03 11:04:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (275, 596.00, 596.00, 0.00, 0.00, 596.00, 0.00, 596.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0275', 6, 'completed', 0.00, 1, '2026-09-03 11:56:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (622, 275, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (623, 275, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (624, 275, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (275, 275, 'cash', 596.00, '2026-09-03 11:56:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (276, 367.00, 367.00, 0.00, 0.00, 387.00, 20.00, 387.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0276', NULL, 'completed', 0.00, 1, '2026-09-03 13:06:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (625, 276, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (626, 276, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (276, 276, 'cash', 387.00, '2026-09-03 13:06:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (277, 387.00, 387.00, 0.00, 0.00, 437.00, 50.00, 437.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0277', NULL, 'completed', 0.00, 1, '2026-09-03 12:18:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (627, 277, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (628, 277, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (629, 277, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (277, 277, 'cash', 437.00, '2026-09-03 12:18:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (278, 476.00, 476.00, 0.00, 0.00, 486.00, 10.00, 486.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0278', 4, 'completed', 0.00, 1, '2026-09-03 14:31:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (630, 278, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (631, 278, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (632, 278, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (278, 278, 'cash', 486.00, '2026-09-03 14:31:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (279, 158.00, 158.00, 0.00, 0.00, 168.00, 10.00, 168.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0279', 6, 'completed', 0.00, 1, '2026-09-03 17:16:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (633, 279, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (634, 279, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (279, 279, 'cash', 168.00, '2026-09-03 17:16:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (280, 506.00, 506.00, 0.00, 0.00, 526.00, 20.00, 526.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0280', 6, 'completed', 0.00, 1, '2026-09-03 18:01:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (635, 280, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (636, 280, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (637, 280, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (280, 280, 'cash', 526.00, '2026-09-03 18:01:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (281, 268.00, 268.00, 0.00, 0.00, 318.00, 50.00, 318.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0281', 8, 'completed', 0.00, 1, '2026-09-03 11:09:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (638, 281, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (639, 281, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (281, 281, 'cash', 318.00, '2026-09-03 11:09:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (282, 456.00, 456.00, 0.00, 0.00, 466.00, 10.00, 466.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0282', 6, 'completed', 0.00, 1, '2026-09-03 17:41:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (640, 282, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (641, 282, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (642, 282, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (282, 282, 'cash', 466.00, '2026-09-03 17:41:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (283, 357.00, 357.00, 0.00, 0.00, 407.00, 50.00, 407.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0283', 8, 'completed', 0.00, 1, '2026-09-03 18:10:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (643, 283, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (644, 283, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (645, 283, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (283, 283, 'cash', 407.00, '2026-09-03 18:10:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (284, 356.00, 356.00, 0.00, 0.00, 376.00, 20.00, 376.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0284', NULL, 'completed', 0.00, 1, '2026-09-03 11:01:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (646, 284, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (647, 284, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (648, 284, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (284, 284, 'cash', 376.00, '2026-09-03 11:01:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (285, 377.00, 377.00, 0.00, 0.00, 397.00, 20.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0285', 9, 'completed', 0.00, 1, '2026-09-03 11:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (649, 285, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (650, 285, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (285, 285, 'cash', 397.00, '2026-09-03 11:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (286, 278.00, 278.00, 0.00, 0.00, 288.00, 10.00, 288.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0286', 1, 'completed', 0.00, 1, '2026-09-03 13:21:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (651, 286, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (652, 286, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (286, 286, 'cash', 288.00, '2026-09-03 13:21:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (287, 536.00, 536.00, 0.00, 0.00, 586.00, 50.00, 586.00, 'cash', 'completed', 'RCPT-DEMO-20260903-0287', NULL, 'completed', 0.00, 1, '2026-09-03 11:42:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (653, 287, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (654, 287, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (287, 287, 'cash', 586.00, '2026-09-03 11:42:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (288, 207.00, 207.00, 0.00, 0.00, 227.00, 20.00, 227.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0288', 3, 'completed', 0.00, 1, '2026-09-04 14:37:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (655, 288, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (656, 288, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (288, 288, 'cash', 227.00, '2026-09-04 14:37:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (289, 556.00, 556.00, 0.00, 0.00, 576.00, 20.00, 576.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0289', 10, 'completed', 0.00, 1, '2026-09-04 18:31:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (657, 289, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (658, 289, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (289, 289, 'cash', 576.00, '2026-09-04 18:31:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (290, 745.00, 745.00, 0.00, 0.00, 795.00, 50.00, 795.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0290', 8, 'completed', 0.00, 1, '2026-09-04 11:42:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (659, 290, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (660, 290, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (661, 290, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (290, 290, 'cash', 795.00, '2026-09-04 11:42:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (291, 316.00, 316.00, 0.00, 0.00, 366.00, 50.00, 366.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0291', NULL, 'completed', 0.00, 1, '2026-09-04 11:52:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (662, 291, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (663, 291, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (291, 291, 'cash', 366.00, '2026-09-04 11:52:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (292, 387.00, 387.00, 0.00, 0.00, 397.00, 10.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0292', NULL, 'completed', 0.00, 1, '2026-09-04 11:46:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (664, 292, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (665, 292, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (292, 292, 'cash', 397.00, '2026-09-04 11:46:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (293, 427.00, 427.00, 0.00, 0.00, 447.00, 20.00, 447.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0293', NULL, 'completed', 0.00, 1, '2026-09-04 18:38:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (666, 293, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (667, 293, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (293, 293, 'cash', 447.00, '2026-09-04 18:38:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (294, 496.00, 496.00, 0.00, 0.00, 496.00, 0.00, 496.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0294', 2, 'completed', 0.00, 1, '2026-09-04 12:55:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (668, 294, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (669, 294, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (670, 294, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (294, 294, 'cash', 496.00, '2026-09-04 12:55:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (295, 556.00, 556.00, 0.00, 0.00, 606.00, 50.00, 606.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0295', 8, 'completed', 0.00, 1, '2026-09-04 11:08:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (671, 295, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (672, 295, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (295, 295, 'cash', 606.00, '2026-09-04 11:08:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (296, 636.00, 636.00, 0.00, 0.00, 646.00, 10.00, 646.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0296', 3, 'completed', 0.00, 1, '2026-09-04 11:48:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (673, 296, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (674, 296, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (296, 296, 'cash', 646.00, '2026-09-04 11:48:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (297, 396.00, 396.00, 0.00, 0.00, 406.00, 10.00, 406.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0297', 6, 'completed', 0.00, 1, '2026-09-04 17:06:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (675, 297, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (676, 297, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (297, 297, 'cash', 406.00, '2026-09-04 17:06:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (298, 347.00, 347.00, 0.00, 0.00, 357.00, 10.00, 357.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0298', 2, 'completed', 0.00, 1, '2026-09-04 13:14:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (677, 298, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (678, 298, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (298, 298, 'cash', 357.00, '2026-09-04 13:14:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (299, 377.00, 377.00, 0.00, 0.00, 377.00, 0.00, 377.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0299', 10, 'completed', 0.00, 1, '2026-09-04 12:53:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (679, 299, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (680, 299, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (299, 299, 'cash', 377.00, '2026-09-04 12:53:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (300, 337.00, 337.00, 0.00, 0.00, 337.00, 0.00, 337.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0300', 4, 'completed', 0.00, 1, '2026-09-04 18:00:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (681, 300, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (682, 300, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (300, 300, 'cash', 337.00, '2026-09-04 18:00:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (301, 397.00, 397.00, 0.00, 0.00, 407.00, 10.00, 407.00, 'cash', 'completed', 'RCPT-DEMO-20260904-0301', NULL, 'completed', 0.00, 1, '2026-09-04 18:19:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (683, 301, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (684, 301, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (301, 301, 'cash', 407.00, '2026-09-04 18:19:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (302, 347.00, 347.00, 0.00, 0.00, 397.00, 50.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0302', 9, 'completed', 0.00, 1, '2026-09-05 17:45:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (685, 302, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (686, 302, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (302, 302, 'cash', 397.00, '2026-09-05 17:45:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (303, 248.00, 248.00, 0.00, 0.00, 258.00, 10.00, 258.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0303', 4, 'completed', 0.00, 1, '2026-09-05 17:07:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (687, 303, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (688, 303, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (303, 303, 'cash', 258.00, '2026-09-05 17:07:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (304, 268.00, 268.00, 0.00, 0.00, 288.00, 20.00, 288.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0304', NULL, 'completed', 0.00, 1, '2026-09-05 14:57:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (689, 304, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (690, 304, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (304, 304, 'cash', 288.00, '2026-09-05 14:57:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (305, 347.00, 347.00, 0.00, 0.00, 347.00, 0.00, 347.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0305', 3, 'completed', 0.00, 1, '2026-09-05 18:20:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (691, 305, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (692, 305, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (305, 305, 'cash', 347.00, '2026-09-05 18:20:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (306, 198.00, 198.00, 0.00, 0.00, 198.00, 0.00, 198.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0306', 9, 'completed', 0.00, 1, '2026-09-05 12:12:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (693, 306, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (694, 306, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (306, 306, 'cash', 198.00, '2026-09-05 12:12:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (307, 745.00, 745.00, 0.00, 0.00, 745.00, 0.00, 745.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0307', 6, 'completed', 0.00, 1, '2026-09-05 18:26:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (695, 307, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (696, 307, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (697, 307, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (307, 307, 'cash', 745.00, '2026-09-05 18:26:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (308, 636.00, 636.00, 0.00, 0.00, 686.00, 50.00, 686.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0308', 5, 'completed', 0.00, 1, '2026-09-05 17:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (698, 308, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (699, 308, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (308, 308, 'cash', 686.00, '2026-09-05 17:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (309, 456.00, 456.00, 0.00, 0.00, 506.00, 50.00, 506.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0309', NULL, 'completed', 0.00, 1, '2026-09-05 14:27:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (700, 309, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (701, 309, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (702, 309, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (309, 309, 'cash', 506.00, '2026-09-05 14:27:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (310, 366.00, 366.00, 0.00, 0.00, 416.00, 50.00, 416.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0310', NULL, 'completed', 0.00, 1, '2026-09-05 12:47:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (703, 310, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (704, 310, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (705, 310, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (310, 310, 'cash', 416.00, '2026-09-05 12:47:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (311, 347.00, 347.00, 0.00, 0.00, 357.00, 10.00, 357.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0311', 8, 'completed', 0.00, 1, '2026-09-05 19:36:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (706, 311, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (707, 311, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (311, 311, 'cash', 357.00, '2026-09-05 19:36:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (312, 556.00, 556.00, 0.00, 0.00, 606.00, 50.00, 606.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0312', NULL, 'completed', 0.00, 1, '2026-09-05 19:27:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (708, 312, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (709, 312, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (312, 312, 'cash', 606.00, '2026-09-05 19:27:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (313, 456.00, 456.00, 0.00, 0.00, 466.00, 10.00, 466.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0313', 2, 'completed', 0.00, 1, '2026-09-05 19:49:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (710, 313, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (711, 313, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (313, 313, 'cash', 466.00, '2026-09-05 19:49:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (314, 316.00, 316.00, 0.00, 0.00, 316.00, 0.00, 316.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0314', NULL, 'completed', 0.00, 1, '2026-09-05 12:02:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (712, 314, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (713, 314, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (314, 314, 'cash', 316.00, '2026-09-05 12:02:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (315, 207.00, 207.00, 0.00, 0.00, 257.00, 50.00, 257.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0315', 1, 'completed', 0.00, 1, '2026-09-05 11:57:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (714, 315, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (715, 315, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (315, 315, 'cash', 257.00, '2026-09-05 11:57:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (316, 477.00, 477.00, 0.00, 0.00, 497.00, 20.00, 497.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0316', 10, 'completed', 0.00, 1, '2026-09-05 19:21:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (716, 316, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (717, 316, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (718, 316, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (316, 316, 'cash', 497.00, '2026-09-05 19:21:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (317, 427.00, 427.00, 0.00, 0.00, 437.00, 10.00, 437.00, 'cash', 'completed', 'RCPT-DEMO-20260905-0317', NULL, 'completed', 0.00, 1, '2026-09-05 17:35:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (719, 317, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (720, 317, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (317, 317, 'cash', 437.00, '2026-09-05 17:35:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (318, 228.00, 228.00, 0.00, 0.00, 228.00, 0.00, 228.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0318', NULL, 'completed', 0.00, 1, '2026-09-06 17:56:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (721, 318, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (722, 318, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (318, 318, 'cash', 228.00, '2026-09-06 17:56:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (319, 387.00, 387.00, 0.00, 0.00, 387.00, 0.00, 387.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0319', 8, 'completed', 0.00, 1, '2026-09-06 14:21:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (723, 319, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (724, 319, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (319, 319, 'cash', 387.00, '2026-09-06 14:21:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (320, 525.00, 525.00, 0.00, 0.00, 525.00, 0.00, 525.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0320', 7, 'completed', 0.00, 1, '2026-09-06 13:56:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (725, 320, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (726, 320, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (727, 320, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (320, 320, 'cash', 525.00, '2026-09-06 13:56:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (321, 397.00, 397.00, 0.00, 0.00, 417.00, 20.00, 417.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0321', 2, 'completed', 0.00, 1, '2026-09-06 12:28:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (728, 321, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (729, 321, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (321, 321, 'cash', 417.00, '2026-09-06 12:28:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (322, 416.00, 416.00, 0.00, 0.00, 416.00, 0.00, 416.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0322', NULL, 'completed', 0.00, 1, '2026-09-06 14:07:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (730, 322, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (731, 322, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (732, 322, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (322, 322, 'cash', 416.00, '2026-09-06 14:07:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (323, 397.00, 397.00, 0.00, 0.00, 407.00, 10.00, 407.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0323', 4, 'completed', 0.00, 1, '2026-09-06 13:02:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (733, 323, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (734, 323, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (323, 323, 'cash', 407.00, '2026-09-06 13:02:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (324, 397.00, 397.00, 0.00, 0.00, 397.00, 0.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0324', 9, 'completed', 0.00, 1, '2026-09-06 17:38:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (735, 324, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (736, 324, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (737, 324, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (324, 324, 'cash', 397.00, '2026-09-06 17:38:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (325, 207.00, 207.00, 0.00, 0.00, 217.00, 10.00, 217.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0325', 3, 'completed', 0.00, 1, '2026-09-06 11:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (738, 325, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (739, 325, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (325, 325, 'cash', 217.00, '2026-09-06 11:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (326, 377.00, 377.00, 0.00, 0.00, 427.00, 50.00, 427.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0326', 6, 'completed', 0.00, 1, '2026-09-06 18:29:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (740, 326, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (741, 326, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (326, 326, 'cash', 427.00, '2026-09-06 18:29:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (327, 567.00, 567.00, 0.00, 0.00, 577.00, 10.00, 577.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0327', 3, 'completed', 0.00, 1, '2026-09-06 12:36:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (742, 327, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (743, 327, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (327, 327, 'cash', 577.00, '2026-09-06 12:36:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (328, 228.00, 228.00, 0.00, 0.00, 248.00, 20.00, 248.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0328', 8, 'completed', 0.00, 1, '2026-09-06 17:06:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (744, 328, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (745, 328, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (328, 328, 'cash', 248.00, '2026-09-06 17:06:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (329, 636.00, 636.00, 0.00, 0.00, 636.00, 0.00, 636.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0329', 4, 'completed', 0.00, 1, '2026-09-06 11:45:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (746, 329, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (747, 329, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (748, 329, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (329, 329, 'cash', 636.00, '2026-09-06 11:45:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (330, 267.00, 267.00, 0.00, 0.00, 267.00, 0.00, 267.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0330', 6, 'completed', 0.00, 1, '2026-09-06 18:32:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (749, 330, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (750, 330, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (330, 330, 'cash', 267.00, '2026-09-06 18:32:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (331, 347.00, 347.00, 0.00, 0.00, 347.00, 0.00, 347.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0331', 3, 'completed', 0.00, 1, '2026-09-06 14:12:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (751, 331, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (752, 331, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (331, 331, 'cash', 347.00, '2026-09-06 14:12:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (332, 427.00, 427.00, 0.00, 0.00, 447.00, 20.00, 447.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0332', 3, 'completed', 0.00, 1, '2026-09-06 14:52:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (753, 332, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (754, 332, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (332, 332, 'cash', 447.00, '2026-09-06 14:52:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (333, 278.00, 278.00, 0.00, 0.00, 328.00, 50.00, 328.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0333', 10, 'completed', 0.00, 1, '2026-09-06 14:24:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (755, 333, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (756, 333, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (333, 333, 'cash', 328.00, '2026-09-06 14:24:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (334, 347.00, 347.00, 0.00, 0.00, 367.00, 20.00, 367.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0334', NULL, 'completed', 0.00, 1, '2026-09-06 12:13:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (757, 334, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (758, 334, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (334, 334, 'cash', 367.00, '2026-09-06 12:13:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (335, 387.00, 387.00, 0.00, 0.00, 397.00, 10.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260906-0335', 10, 'completed', 0.00, 1, '2026-09-06 14:22:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (759, 335, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (760, 335, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (335, 335, 'cash', 397.00, '2026-09-06 14:22:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (336, 536.00, 536.00, 0.00, 0.00, 586.00, 50.00, 586.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0336', 10, 'completed', 0.00, 1, '2026-09-07 13:55:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (761, 336, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (762, 336, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (336, 336, 'cash', 586.00, '2026-09-07 13:55:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (337, 466.00, 466.00, 0.00, 0.00, 466.00, 0.00, 466.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0337', NULL, 'completed', 0.00, 1, '2026-09-07 14:02:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (763, 337, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (764, 337, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (765, 337, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (337, 337, 'cash', 466.00, '2026-09-07 14:02:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (338, 316.00, 316.00, 0.00, 0.00, 336.00, 20.00, 336.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0338', 5, 'completed', 0.00, 1, '2026-09-07 14:29:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (766, 338, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (767, 338, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (338, 338, 'cash', 336.00, '2026-09-07 14:29:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (339, 456.00, 456.00, 0.00, 0.00, 456.00, 0.00, 456.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0339', 7, 'completed', 0.00, 1, '2026-09-07 19:39:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (768, 339, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (769, 339, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (339, 339, 'cash', 456.00, '2026-09-07 19:39:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (340, 456.00, 456.00, 0.00, 0.00, 466.00, 10.00, 466.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0340', NULL, 'completed', 0.00, 1, '2026-09-07 17:01:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (770, 340, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (771, 340, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (340, 340, 'cash', 466.00, '2026-09-07 17:01:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (341, 556.00, 556.00, 0.00, 0.00, 566.00, 10.00, 566.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0341', NULL, 'completed', 0.00, 1, '2026-09-07 11:02:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (772, 341, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (773, 341, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (341, 341, 'cash', 566.00, '2026-09-07 11:02:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (342, 636.00, 636.00, 0.00, 0.00, 686.00, 50.00, 686.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0342', 6, 'completed', 0.00, 1, '2026-09-07 11:28:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (774, 342, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (775, 342, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (342, 342, 'cash', 686.00, '2026-09-07 11:28:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (343, 316.00, 316.00, 0.00, 0.00, 316.00, 0.00, 316.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0343', 1, 'completed', 0.00, 1, '2026-09-07 19:09:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (776, 343, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (777, 343, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (343, 343, 'cash', 316.00, '2026-09-07 19:09:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (344, 158.00, 158.00, 0.00, 0.00, 158.00, 0.00, 158.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0344', 6, 'completed', 0.00, 1, '2026-09-07 11:38:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (778, 344, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (779, 344, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (344, 344, 'cash', 158.00, '2026-09-07 11:38:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (345, 435.00, 435.00, 0.00, 0.00, 485.00, 50.00, 485.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0345', 5, 'completed', 0.00, 1, '2026-09-07 19:26:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (780, 345, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (781, 345, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (782, 345, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (345, 345, 'cash', 485.00, '2026-09-07 19:26:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (346, 207.00, 207.00, 0.00, 0.00, 207.00, 0.00, 207.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0346', 9, 'completed', 0.00, 1, '2026-09-07 19:05:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (783, 346, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (784, 346, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (346, 346, 'cash', 207.00, '2026-09-07 19:05:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (347, 615.00, 615.00, 0.00, 0.00, 625.00, 10.00, 625.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0347', NULL, 'completed', 0.00, 1, '2026-09-07 17:56:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (785, 347, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (786, 347, 8, 2, 129.00, 258.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (787, 347, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (347, 347, 'cash', 625.00, '2026-09-07 17:56:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (348, 565.00, 565.00, 0.00, 0.00, 615.00, 50.00, 615.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0348', 8, 'completed', 0.00, 1, '2026-09-07 11:34:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (788, 348, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (789, 348, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (790, 348, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (348, 348, 'cash', 615.00, '2026-09-07 11:34:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (349, 427.00, 427.00, 0.00, 0.00, 447.00, 20.00, 447.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0349', 4, 'completed', 0.00, 1, '2026-09-07 11:14:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (791, 349, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (792, 349, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (349, 349, 'cash', 447.00, '2026-09-07 11:14:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (350, 556.00, 556.00, 0.00, 0.00, 576.00, 20.00, 576.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0350', 5, 'completed', 0.00, 1, '2026-09-07 18:23:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (793, 350, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (794, 350, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (350, 350, 'cash', 576.00, '2026-09-07 18:23:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (351, 567.00, 567.00, 0.00, 0.00, 567.00, 0.00, 567.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0351', 6, 'completed', 0.00, 1, '2026-09-07 13:42:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (795, 351, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (796, 351, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (351, 351, 'cash', 567.00, '2026-09-07 13:42:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (352, 636.00, 636.00, 0.00, 0.00, 656.00, 20.00, 656.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0352', NULL, 'completed', 0.00, 1, '2026-09-07 11:09:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (797, 352, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (798, 352, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (352, 352, 'cash', 656.00, '2026-09-07 11:09:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (353, 248.00, 248.00, 0.00, 0.00, 258.00, 10.00, 258.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0353', 9, 'completed', 0.00, 1, '2026-09-07 18:52:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (799, 353, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (800, 353, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (353, 353, 'cash', 258.00, '2026-09-07 18:52:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (354, 387.00, 387.00, 0.00, 0.00, 397.00, 10.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0354', 3, 'completed', 0.00, 1, '2026-09-07 18:02:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (801, 354, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (802, 354, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (354, 354, 'cash', 397.00, '2026-09-07 18:02:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (355, 207.00, 207.00, 0.00, 0.00, 227.00, 20.00, 227.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0355', 2, 'completed', 0.00, 1, '2026-09-07 17:48:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (803, 355, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (804, 355, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (355, 355, 'cash', 227.00, '2026-09-07 17:48:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (356, 436.00, 436.00, 0.00, 0.00, 436.00, 0.00, 436.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0356', 9, 'completed', 0.00, 1, '2026-09-07 13:12:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (805, 356, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (806, 356, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (807, 356, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (356, 356, 'cash', 436.00, '2026-09-07 13:12:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (357, 585.00, 585.00, 0.00, 0.00, 635.00, 50.00, 635.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0357', 4, 'completed', 0.00, 1, '2026-09-07 12:36:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (808, 357, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (809, 357, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (810, 357, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (357, 357, 'cash', 635.00, '2026-09-07 12:36:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (358, 556.00, 556.00, 0.00, 0.00, 576.00, 20.00, 576.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0358', NULL, 'completed', 0.00, 1, '2026-09-07 18:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (811, 358, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (812, 358, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (813, 358, 7, 1, 119.00, 119.00, 'Spanish Latte', 'ITM0007');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (358, 358, 'cash', 576.00, '2026-09-07 18:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (359, 536.00, 536.00, 0.00, 0.00, 546.00, 10.00, 546.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0359', 10, 'completed', 0.00, 1, '2026-09-07 13:03:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (814, 359, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (815, 359, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (359, 359, 'cash', 546.00, '2026-09-07 13:03:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (360, 396.00, 396.00, 0.00, 0.00, 446.00, 50.00, 446.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0360', 9, 'completed', 0.00, 1, '2026-09-07 19:20:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (816, 360, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (817, 360, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (360, 360, 'cash', 446.00, '2026-09-07 19:20:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (361, 228.00, 228.00, 0.00, 0.00, 248.00, 20.00, 248.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0361', 1, 'completed', 0.00, 1, '2026-09-07 17:30:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (818, 361, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (819, 361, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (361, 361, 'cash', 248.00, '2026-09-07 17:30:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (362, 567.00, 567.00, 0.00, 0.00, 617.00, 50.00, 617.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0362', 9, 'completed', 0.00, 1, '2026-09-07 18:16:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (820, 362, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (821, 362, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (362, 362, 'cash', 617.00, '2026-09-07 18:16:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (363, 427.00, 427.00, 0.00, 0.00, 427.00, 0.00, 427.00, 'cash', 'completed', 'RCPT-DEMO-20260907-0363', NULL, 'completed', 0.00, 1, '2026-09-07 13:34:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (822, 363, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (823, 363, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (363, 363, 'cash', 427.00, '2026-09-07 13:34:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (364, 347.00, 347.00, 0.00, 0.00, 357.00, 10.00, 357.00, 'cash', 'completed', 'RCPT-DEMO-20260908-0364', NULL, 'completed', 0.00, 1, '2026-09-08 13:55:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (824, 364, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (825, 364, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (364, 364, 'cash', 357.00, '2026-09-08 13:55:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (365, 336.00, 336.00, 0.00, 0.00, 386.00, 50.00, 386.00, 'cash', 'completed', 'RCPT-DEMO-20260908-0365', NULL, 'completed', 0.00, 1, '2026-09-08 11:45:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (826, 365, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (827, 365, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (828, 365, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (365, 365, 'cash', 386.00, '2026-09-08 11:45:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (366, 615.00, 615.00, 0.00, 0.00, 625.00, 10.00, 625.00, 'cash', 'completed', 'RCPT-DEMO-20260908-0366', NULL, 'completed', 0.00, 1, '2026-09-08 12:13:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (829, 366, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (830, 366, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (831, 366, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (366, 366, 'cash', 625.00, '2026-09-08 12:13:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (367, 267.00, 267.00, 0.00, 0.00, 287.00, 20.00, 287.00, 'cash', 'completed', 'RCPT-DEMO-20260908-0367', 1, 'completed', 0.00, 1, '2026-09-08 17:10:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (832, 367, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (833, 367, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (834, 367, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (367, 367, 'cash', 287.00, '2026-09-08 17:10:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (368, 567.00, 567.00, 0.00, 0.00, 587.00, 20.00, 587.00, 'cash', 'completed', 'RCPT-DEMO-20260908-0368', 5, 'completed', 0.00, 1, '2026-09-08 18:23:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (835, 368, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (836, 368, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (368, 368, 'cash', 587.00, '2026-09-08 18:23:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (369, 347.00, 347.00, 0.00, 0.00, 397.00, 50.00, 397.00, 'cash', 'completed', 'RCPT-DEMO-20260908-0369', NULL, 'completed', 0.00, 1, '2026-09-08 12:27:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (837, 369, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (838, 369, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (369, 369, 'cash', 397.00, '2026-09-08 12:27:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (370, 367.00, 367.00, 0.00, 0.00, 377.00, 10.00, 377.00, 'cash', 'completed', 'RCPT-DEMO-20260908-0370', 9, 'completed', 0.00, 1, '2026-09-08 12:22:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (839, 370, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (840, 370, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (370, 370, 'cash', 377.00, '2026-09-08 12:22:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (371, 247.00, 247.00, 0.00, 0.00, 297.00, 50.00, 297.00, 'cash', 'completed', 'RCPT-DEMO-20260908-0371', 10, 'completed', 0.00, 1, '2026-09-08 17:14:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (841, 371, 24, 1, 149.00, 149.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (842, 371, 18, 2, 49.00, 98.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (371, 371, 'cash', 297.00, '2026-09-08 17:14:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (372, 267.00, 267.00, 0.00, 0.00, 287.00, 20.00, 287.00, 'cash', 'completed', 'RCPT-DEMO-20260908-0372', 8, 'completed', 0.00, 1, '2026-09-08 12:57:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (843, 372, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (844, 372, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (372, 372, 'cash', 287.00, '2026-09-08 12:57:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (373, 397.00, 397.00, 0.00, 0.00, 407.00, 10.00, 407.00, 'cash', 'completed', 'RCPT-DEMO-20260908-0373', 4, 'completed', 0.00, 1, '2026-09-08 19:39:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (845, 373, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (846, 373, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (373, 373, 'cash', 407.00, '2026-09-08 19:39:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (374, 318.00, 318.00, 0.00, 0.00, 338.00, 20.00, 338.00, 'cash', 'completed', 'RCPT-DEMO-20260909-0374', 10, 'completed', 0.00, 1, '2026-09-09 17:26:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (847, 374, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (848, 374, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (374, 374, 'cash', 338.00, '2026-09-09 17:26:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (375, 387.00, 387.00, 0.00, 0.00, 407.00, 20.00, 407.00, 'cash', 'completed', 'RCPT-DEMO-20260909-0375', 8, 'completed', 0.00, 1, '2026-09-09 13:32:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (849, 375, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (850, 375, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (375, 375, 'cash', 407.00, '2026-09-09 13:32:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (376, 567.00, 567.00, 0.00, 0.00, 577.00, 10.00, 577.00, 'cash', 'completed', 'RCPT-DEMO-20260909-0376', NULL, 'completed', 0.00, 1, '2026-09-09 13:57:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (851, 376, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (852, 376, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (376, 376, 'cash', 577.00, '2026-09-09 13:57:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (377, 158.00, 158.00, 0.00, 0.00, 208.00, 50.00, 208.00, 'cash', 'completed', 'RCPT-DEMO-20260909-0377', 5, 'completed', 0.00, 1, '2026-09-09 14:52:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (853, 377, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (854, 377, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (377, 377, 'cash', 208.00, '2026-09-09 14:52:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (378, 546.00, 546.00, 0.00, 0.00, 596.00, 50.00, 596.00, 'cash', 'completed', 'RCPT-DEMO-20260909-0378', 10, 'completed', 0.00, 1, '2026-09-09 18:13:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (855, 378, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (856, 378, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (857, 378, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (378, 378, 'cash', 596.00, '2026-09-09 18:13:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (379, 466.00, 466.00, 0.00, 0.00, 516.00, 50.00, 516.00, 'cash', 'completed', 'RCPT-DEMO-20260909-0379', 10, 'completed', 0.00, 1, '2026-09-09 14:00:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (858, 379, 24, 2, 149.00, 298.00, 'Creamy Carbonara', 'ITM0024');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (859, 379, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (860, 379, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (379, 379, 'cash', 516.00, '2026-09-09 14:00:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (380, 268.00, 268.00, 0.00, 0.00, 288.00, 20.00, 288.00, 'cash', 'completed', 'RCPT-DEMO-20260909-0380', 8, 'completed', 0.00, 1, '2026-09-09 19:15:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (861, 380, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (862, 380, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (380, 380, 'cash', 288.00, '2026-09-09 19:15:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (381, 436.00, 436.00, 0.00, 0.00, 486.00, 50.00, 486.00, 'cash', 'completed', 'RCPT-DEMO-20260909-0381', 10, 'completed', 0.00, 1, '2026-09-09 18:20:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (863, 381, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (864, 381, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (865, 381, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (381, 381, 'cash', 486.00, '2026-09-09 18:20:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (382, 316.00, 316.00, 0.00, 0.00, 326.00, 10.00, 326.00, 'cash', 'completed', 'RCPT-DEMO-20260909-0382', 8, 'completed', 0.00, 1, '2026-09-09 14:50:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (866, 382, 46, 2, 109.00, 218.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (867, 382, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (868, 382, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (382, 382, 'cash', 326.00, '2026-09-09 14:50:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (383, 207.00, 207.00, 0.00, 0.00, 217.00, 10.00, 217.00, 'cash', 'completed', 'RCPT-DEMO-20260909-0383', 4, 'completed', 0.00, 1, '2026-09-09 12:48:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (869, 383, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (870, 383, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (383, 383, 'cash', 217.00, '2026-09-09 12:48:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (384, 437.00, 437.00, 0.00, 0.00, 457.00, 20.00, 457.00, 'cash', 'completed', 'RCPT-DEMO-20260909-0384', NULL, 'completed', 0.00, 1, '2026-09-09 17:15:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (871, 384, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (872, 384, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (384, 384, 'cash', 457.00, '2026-09-09 17:15:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (385, 436.00, 436.00, 0.00, 0.00, 436.00, 0.00, 436.00, 'cash', 'completed', 'RCPT-DEMO-20260910-0385', 5, 'completed', 0.00, 1, '2026-09-10 12:56:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (873, 385, 5, 2, 119.00, 238.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (874, 385, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (875, 385, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (385, 385, 'cash', 436.00, '2026-09-10 12:56:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (386, 228.00, 228.00, 0.00, 0.00, 238.00, 10.00, 238.00, 'cash', 'completed', 'RCPT-DEMO-20260910-0386', 3, 'completed', 0.00, 1, '2026-09-10 14:53:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (876, 386, 14, 1, 119.00, 119.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (877, 386, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (386, 386, 'cash', 238.00, '2026-09-10 14:53:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (387, 297.00, 297.00, 0.00, 0.00, 347.00, 50.00, 347.00, 'cash', 'completed', 'RCPT-DEMO-20260910-0387', 6, 'completed', 0.00, 1, '2026-09-10 17:45:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (878, 387, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (879, 387, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (880, 387, 18, 1, 49.00, 49.00, 'Ham & Cheese Waffle', 'ITM0018');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (387, 387, 'cash', 347.00, '2026-09-10 17:45:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (388, 318.00, 318.00, 0.00, 0.00, 338.00, 20.00, 338.00, 'cash', 'completed', 'RCPT-DEMO-20260910-0388', NULL, 'completed', 0.00, 1, '2026-09-10 17:57:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (881, 388, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (882, 388, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (388, 388, 'cash', 338.00, '2026-09-10 17:57:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (389, 207.00, 207.00, 0.00, 0.00, 207.00, 0.00, 207.00, 'cash', 'completed', 'RCPT-DEMO-20260910-0389', 3, 'completed', 0.00, 1, '2026-09-10 14:14:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (883, 389, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (884, 389, 49, 2, 49.00, 98.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (389, 389, 'cash', 207.00, '2026-09-10 14:14:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (390, 227.00, 227.00, 0.00, 0.00, 277.00, 50.00, 277.00, 'cash', 'completed', 'RCPT-DEMO-20260910-0390', 7, 'completed', 0.00, 1, '2026-09-10 11:55:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (885, 390, 46, 1, 109.00, 109.00, 'Beef Burger', 'ITM0046');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (886, 390, 49, 1, 49.00, 49.00, 'Blueberry Soda', 'ITM0049');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (887, 390, 39, 1, 69.00, 69.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (390, 390, 'cash', 277.00, '2026-09-10 11:55:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (391, 397.00, 397.00, 0.00, 0.00, 447.00, 50.00, 447.00, 'cash', 'completed', 'RCPT-DEMO-20260910-0391', 9, 'completed', 0.00, 1, '2026-09-10 17:10:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (888, 391, 14, 2, 119.00, 238.00, 'Latte', 'ITM0014');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (889, 391, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (391, 391, 'cash', 447.00, '2026-09-10 17:10:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (392, 387.00, 387.00, 0.00, 0.00, 437.00, 50.00, 437.00, 'cash', 'completed', 'RCPT-DEMO-20260910-0392', 10, 'completed', 0.00, 1, '2026-09-10 12:43:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (890, 392, 30, 1, 249.00, 249.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (891, 392, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (392, 392, 'cash', 437.00, '2026-09-10 12:43:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (393, 427.00, 427.00, 0.00, 0.00, 477.00, 50.00, 477.00, 'cash', 'completed', 'RCPT-DEMO-20260910-0393', 6, 'completed', 0.00, 1, '2026-09-10 14:52:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (892, 393, 6, 1, 109.00, 109.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (893, 393, 12, 2, 159.00, 318.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (393, 393, 'cash', 477.00, '2026-09-10 14:52:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (394, 636.00, 636.00, 0.00, 0.00, 686.00, 50.00, 686.00, 'cash', 'completed', 'RCPT-DEMO-20260910-0394', 10, 'completed', 0.00, 1, '2026-09-10 19:20:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (894, 394, 30, 2, 249.00, 498.00, 'Pepperoni Pizza', 'ITM0030');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (895, 394, 39, 2, 69.00, 138.00, 'French Fries', 'ITM0039');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (394, 394, 'cash', 686.00, '2026-09-10 19:20:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (395, 496.00, 496.00, 0.00, 0.00, 496.00, 0.00, 496.00, 'cash', 'completed', 'RCPT-DEMO-20260910-0395', 6, 'completed', 0.00, 1, '2026-09-10 17:26:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (896, 395, 6, 2, 109.00, 218.00, 'Tapa Chaofan', 'ITM0006');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (897, 395, 12, 1, 159.00, 159.00, 'Sisig Chaofan', 'ITM0012');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (898, 395, 7, 1, 119.00, 119.00, 'Spanish Latte', 'ITM0007');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (395, 395, 'cash', 496.00, '2026-09-10 17:26:00');
INSERT INTO orders (id, total_amount, subtotal_amount, tax_amount, discount_amount, paid_amount, change_amount, cash_received_amount, payment_method, payment_status, receipt_number, customer_id, transaction_status, refund_amount, created_by, created_at) VALUES (396, 248.00, 248.00, 0.00, 0.00, 248.00, 0.00, 248.00, 'cash', 'completed', 'RCPT-DEMO-20260910-0396', 5, 'completed', 0.00, 1, '2026-09-10 14:20:00');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (899, 396, 5, 1, 119.00, 119.00, 'Caramel Latte', 'ITM0005');
INSERT INTO order_items (id, order_id, menu_item_id, quantity, price, line_total, item_name_snapshot, product_code_snapshot) VALUES (900, 396, 8, 1, 129.00, 129.00, 'Chicken Poppers Chaofan', 'ITM0008');
INSERT INTO order_payments (id, order_id, payment_method, amount, created_at) VALUES (396, 396, 'cash', 248.00, '2026-09-10 14:20:00');

-- Supplier sample
INSERT INTO suppliers (id, name, contact, address) VALUES (1, 'Metro Cafe Supply', '09170001111', 'Quezon City');

SET FOREIGN_KEY_CHECKS=1;

-- End of pre-oral dummy package
