-- Database schema for Kin Cafe Cashier System

CREATE DATABASE IF NOT EXISTS kin_cafe;
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
