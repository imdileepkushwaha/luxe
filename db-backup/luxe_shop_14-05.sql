-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 14, 2026 at 08:07 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `luxe_shop`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(120) NOT NULL DEFAULT 'Administrator',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `email`, `password_hash`, `full_name`, `is_active`, `created_at`) VALUES
(1, 'admin@luxe.com', '$2y$10$X7eroI8zJSxoDt56c4Peg.pIsS7E7VjzG35w4/Sk9K2eHciLNNmgO', 'Super Admin', 1, '2026-04-15 13:02:42');

-- --------------------------------------------------------

--
-- Table structure for table `cms_faqs`
--

CREATE TABLE `cms_faqs` (
  `id` int(10) UNSIGNED NOT NULL,
  `question` varchar(500) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cms_faqs`
--

INSERT INTO `cms_faqs` (`id`, `question`, `answer`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'How do I place an order on LUXE?', 'Browse products, add items to your cart, and proceed to checkout. You can create an account or continue as a guest where supported. Review your address and payment details before confirming.', 10, 1, '2026-05-02 17:39:50', '2026-05-02 17:39:50'),
(2, 'What payment methods are accepted?', 'We support major cards, UPI, and net banking where enabled by our payment partner. Available options are shown at checkout before you pay.', 20, 1, '2026-05-02 17:39:50', '2026-05-02 17:39:50'),
(3, 'How long does delivery take?', 'Delivery times depend on the seller, your location, and the shipping option you choose. Estimated timelines appear on the product or checkout page when applicable.', 30, 1, '2026-05-02 17:39:50', '2026-05-02 17:39:50'),
(4, 'Can I return or exchange an item?', 'Return and exchange policies may vary by seller and product category. Check the product page and your order details for eligibility. Contact support if you need help with a specific order.', 40, 1, '2026-05-02 17:39:50', '2026-05-02 17:39:50'),
(5, 'How do I track my order?', 'Sign in and open the Orders section from your profile. You will see status updates and tracking information when the seller or carrier provides them.', 50, 1, '2026-05-02 17:39:50', '2026-05-02 17:39:50'),
(6, 'How do I become a seller on LUXE?', 'Vendors can apply through our seller onboarding flow. If you are interested, use the \"Become A Vendor\" link in the footer or contact us for partnership details.', 60, 1, '2026-05-02 17:39:50', '2026-05-02 17:39:50');

-- --------------------------------------------------------

--
-- Table structure for table `cms_pages`
--

CREATE TABLE `cms_pages` (
  `page_key` varchar(64) NOT NULL,
  `hero_kicker` varchar(120) NOT NULL DEFAULT '',
  `hero_title` varchar(255) NOT NULL DEFAULT '',
  `hero_lead` varchar(1000) NOT NULL DEFAULT '',
  `body_html` mediumtext DEFAULT NULL,
  `meta_description` varchar(500) NOT NULL DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cms_pages`
--

INSERT INTO `cms_pages` (`page_key`, `hero_kicker`, `hero_title`, `hero_lead`, `body_html`, `meta_description`, `updated_at`) VALUES
('about', 'Our story', 'About Us', 'At LUXE, we combine product curation, engineering, and design thinking to build a premium shopping experience.', NULL, 'Learn more about LUXE, our mission, and why shoppers trust our platform.', '2026-05-02 17:39:50'),
('contact', 'We are here to help', 'Contact Us', 'Share your question about orders, payments, returns, or account settings. Our support team reviews every message and responds as quickly as possible during working hours.', '', 'Get in touch with LUXE support for order help, account issues, and general questions.', '2026-05-02 17:47:15'),
('faq', 'Help centre', 'Frequently Asked Questions', 'Quick answers about orders, payments and account basics. Still stuck? Contact our team.', NULL, 'Frequently asked questions about orders, shipping, returns, and account support on LUXE.', '2026-05-02 17:39:50'),
('privacy', 'Legal', 'Privacy Policy', 'We respect your privacy. This policy explains what we collect, why we collect it, and the choices you have when you shop or use LUXE.', NULL, 'How LUXE collects, uses and protects your personal information.', '2026-05-02 17:39:50'),
('return_policy', 'Customer care', 'Return Policy', 'We want you to shop with confidence. Here is how returns, exchanges and refunds typically work across sellers on LUXE.', NULL, 'How returns, exchanges and refunds work on LUXE marketplace orders.', '2026-05-02 17:39:50'),
('terms', 'Legal', 'Terms & Conditions', 'Please read these terms carefully. They govern your access to LUXE and your relationship with us and with independent sellers on the platform.', NULL, 'Terms and conditions for using the LUXE marketplace and services.', '2026-05-02 17:39:50');

-- --------------------------------------------------------

--
-- Table structure for table `live_chat_conversations`
--

CREATE TABLE `live_chat_conversations` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_message_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `live_chat_messages`
--

CREATE TABLE `live_chat_messages` (
  `id` int(10) UNSIGNED NOT NULL,
  `conversation_id` int(10) UNSIGNED NOT NULL,
  `sender_role` varchar(16) NOT NULL DEFAULT 'user',
  `user_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `message_text` varchar(1000) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `order_ref` varchar(32) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'processing',
  `total_amount` int(10) UNSIGNED NOT NULL,
  `platform_fee_rupees` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `admin_commission_rupees` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `payment_method` varchar(128) NOT NULL DEFAULT '',
  `shipping_address` varchar(512) NOT NULL DEFAULT '',
  `delivered_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmed_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `out_for_delivery_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `order_ref`, `status`, `total_amount`, `platform_fee_rupees`, `admin_commission_rupees`, `payment_method`, `shipping_address`, `delivered_at`, `created_at`, `confirmed_at`, `shipped_at`, `out_for_delivery_at`) VALUES
(1, 1, 'LUXE83920741', 'delivered', 27998, 0, 280, 'HDFC Credit Card', 'Sector 15, Noida, UP 201301', '2026-04-10 12:00:00', '2026-04-10 06:30:00', NULL, NULL, NULL),
(2, 1, 'LUXE92810465', 'shipped', 19500, 0, 195, 'UPI - rahul@ok', 'Sector 15, Noida, UP 201301', NULL, '2026-04-06 04:30:00', NULL, NULL, NULL),
(3, 1, 'LUXE77541238', 'delivered', 6797, 0, 68, 'Amazon Pay', 'Sector 15, Noida, UP 201301', '2026-03-28 15:00:00', '2026-03-28 09:30:00', NULL, NULL, NULL),
(4, 2, 'LUXE440117319', 'processing', 37000, 0, 370, 'Card', 'Saved on profile', NULL, '2026-04-17 15:35:17', NULL, NULL, NULL),
(5, 2, 'LUXE449077390', 'delivered', 11598, 0, 116, 'Card', 'Saved on profile', '2026-04-17 23:34:37', '2026-04-17 18:04:37', NULL, NULL, NULL),
(6, 2, 'LUXE485229365', 'cancelled', 3868, 0, 0, 'Card', 'Saved on profile', NULL, '2026-04-18 04:07:09', NULL, NULL, NULL),
(7, 2, 'LUXE486019870', 'delivered', 3868, 0, 39, 'Card', 'Saved on profile', '2026-04-18 09:50:19', '2026-04-18 04:20:19', NULL, NULL, NULL),
(8, 2, 'LUXE488865946', 'cancelled', 3865, 0, 0, 'Card', 'Saved on profile', NULL, '2026-04-18 05:07:45', NULL, NULL, NULL),
(9, 2, 'LUXE490389688', 'delivered', 3865, 0, 0, 'Cash on Delivery (COD)', 'The Artist · 9876543210 — C-603 HPCL Housing Socity — Greator Noida, Uttar Pradesh 201301', '2026-04-18 11:03:09', '2026-04-18 05:33:09', NULL, NULL, NULL),
(10, 2, 'LUXE498753837', 'cancelled', 1205, 5, 0, 'Card', 'The Artist · C-603 HPCL Housing Socity · Greator Noida, Uttar Pradesh 201301 · Ph: 9876543210', NULL, '2026-04-18 07:52:33', NULL, NULL, NULL),
(11, 2, 'LUXE501008270', 'delivered', 335, 5, 0, 'COD', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', '2026-04-18 14:00:08', '2026-04-18 08:30:08', NULL, NULL, NULL),
(12, 2, 'LUXE530476784', 'delivered', 635, 5, 0, 'UPI', 'The Artist · C-603 HPCL Housing Socity · Greator Noida, Uttar Pradesh 201301 · Ph: 9876543210', '2026-04-18 22:11:16', '2026-04-18 16:41:16', NULL, NULL, NULL),
(13, 2, 'LUXE536924775', 'delivered', 335, 5, 0, 'UPI', 'The Artist · C-603 HPCL Housing Socity · Greator Noida, Uttar Pradesh 201301 · Ph: 9876543210', '2026-04-18 23:58:44', '2026-04-18 18:28:44', NULL, NULL, NULL),
(14, 2, 'LUXE537105209', 'delivered', 3870, 5, 39, 'COD', 'The Artist · C-603 HPCL Housing Socity · Greator Noida, Uttar Pradesh 201301 · Ph: 9876543210', '2026-04-19 00:01:45', '2026-04-18 18:31:45', NULL, NULL, NULL),
(15, 2, 'LUXE537452882', 'delivered', 3870, 5, 0, 'UPI', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', '2026-04-19 00:07:32', '2026-04-18 18:37:32', NULL, NULL, NULL),
(16, 2, 'LUXE539365564', 'delivered', 3870, 5, 39, 'COD', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', '2026-04-19 00:39:25', '2026-04-18 19:09:25', NULL, NULL, NULL),
(17, 2, 'LUXE767991187', 'delivered', 1034, 5, 10, 'UPI', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', '2026-04-21 16:09:51', '2026-04-21 10:39:51', NULL, NULL, NULL),
(18, 2, 'LUXE783547370', 'delivered', 4001, 5, 40, 'COD', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', '2026-04-21 20:29:07', '2026-04-21 14:59:07', NULL, NULL, NULL),
(19, 2, 'LUXE788396481', 'delivered', 1993, 5, 20, 'COD', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', '2026-04-21 21:49:56', '2026-04-21 16:19:56', NULL, NULL, NULL),
(20, 2, 'LUXE838851397', 'cancelled', 1252, 5, 0, 'Card', 'The Artist · C-603 HPCL Housing Socity · Greator Noida, Uttar Pradesh 201301 · Ph: 9876543210', NULL, '2026-04-22 06:20:51', NULL, NULL, NULL),
(21, 2, 'LUXE841536307', 'delivered', 4900, 5, 50, 'UPI', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', '2026-04-22 12:54:10', '2026-04-22 07:05:36', NULL, NULL, '2026-04-22 12:50:39'),
(22, 2, 'LUXE845359635', 'delivered', 928, 5, 0, 'COD', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', '2026-04-22 13:44:30', '2026-04-22 08:09:19', NULL, '2026-04-22 13:40:18', '2026-04-22 13:43:08'),
(23, 2, 'LUXE871280991', 'delivered', 1354, 5, 0, 'COD', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', '2026-04-22 23:18:46', '2026-04-22 15:21:20', '2026-04-22 22:58:21', '2026-04-22 22:59:29', '2026-04-22 23:02:16'),
(24, 2, 'LUXE882425335', 'delivered', 3870, 5, 39, 'COD', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', '2026-04-23 00:13:55', '2026-04-22 18:27:05', '2026-04-22 23:57:19', '2026-04-23 00:08:12', '2026-04-23 00:13:52'),
(25, 2, 'LUXE928562369', 'delivered', 1034, 5, 10, 'COD', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', '2026-04-23 12:50:26', '2026-04-23 07:16:02', '2026-04-23 12:46:36', '2026-04-23 12:47:23', '2026-04-23 12:49:40'),
(26, 4, 'LUXE929058728', 'cancelled', 479, 5, 0, 'COD', 'Rahul · hghsg hjsg jshgdf jshdgf shg · Mumbai, maharastra 400001 · Ph: 2233445566', NULL, '2026-04-23 07:24:18', NULL, NULL, NULL),
(27, 2, 'LUXE929744514', 'cancelled', 335, 5, 0, 'COD', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', NULL, '2026-04-23 07:35:44', NULL, NULL, NULL),
(28, 2, 'LUXE021013809', 'delivered', 1034, 5, 10, 'COD', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', '2026-04-24 14:59:01', '2026-04-24 08:56:53', '2026-04-24 14:27:23', '2026-04-24 14:29:44', '2026-04-24 14:58:21'),
(29, 2, 'LUXE044795636', 'delivered', 316, 5, 3, 'Card', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', '2026-04-24 21:04:36', '2026-04-24 15:33:15', '2026-04-24 21:04:13', '2026-04-24 21:04:22', '2026-04-24 21:04:29'),
(30, 2, 'LUXE055221488', 'cancelled', 305, 5, 0, 'Razorpay (dev skip)', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', NULL, '2026-04-24 18:27:01', NULL, NULL, NULL),
(31, 2, 'LUXE223242649', 'cancelled', 316, 5, 0, 'COD', 'Deadly Crew · 91 Spring Board, Near Lemontree Hotel · Noida, Uttar Pradesh 201311 · Ph: +91 98765 43210', NULL, '2026-04-26 17:07:22', NULL, NULL, NULL),
(32, 2, 'LUXE763329237', 'processing', 1109, 5, 10, 'COD', 'The Artist · C-603 HPCL Housing Socity · Greator Noida, Uttar Pradesh 201301 · Ph: 9876543210', NULL, '2026-05-14 12:55:29', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `seller_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `emoji` varchar(16) NOT NULL DEFAULT '??',
  `variant_text` varchar(255) NOT NULL DEFAULT '',
  `price` int(10) UNSIGNED NOT NULL,
  `qty` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `status` varchar(32) NOT NULL DEFAULT 'processing',
  `confirmed_at` datetime DEFAULT NULL,
  `shipped_at` datetime DEFAULT NULL,
  `out_for_delivery_at` datetime DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `seller_id`, `name`, `emoji`, `variant_text`, `price`, `qty`, `status`, `confirmed_at`, `shipped_at`, `out_for_delivery_at`, `delivered_at`) VALUES
(1, 1, NULL, NULL, 'AirMax Pro 2026', '??', 'UK 8 ? Purple', 8999, 1, 'delivered', NULL, NULL, NULL, '2026-04-10 12:00:00'),
(2, 1, NULL, NULL, 'Sony WH-1000XM5', '??', 'Black', 18999, 1, 'delivered', NULL, NULL, NULL, '2026-04-10 12:00:00'),
(3, 2, NULL, NULL, 'Apple Watch SE 44mm', '?', 'Midnight ? GPS', 19500, 1, 'shipped', NULL, NULL, NULL, NULL),
(4, 3, NULL, NULL, 'Linen Co-ord Set', '??', 'S ? Beige', 3299, 1, 'delivered', NULL, NULL, NULL, '2026-03-28 15:00:00'),
(5, 3, NULL, NULL, 'Retinol Serum Kit', '??', '30ml', 1899, 1, 'delivered', NULL, NULL, NULL, '2026-03-28 15:00:00'),
(6, 3, NULL, NULL, 'LED Desk Lamp', '??', 'White', 1599, 1, 'delivered', NULL, NULL, NULL, '2026-03-28 15:00:00'),
(7, 4, NULL, NULL, 'AirMax Pro 2026', '??', 'UK 8 · Cosmic Purple', 8999, 2, 'processing', NULL, NULL, NULL, NULL),
(8, 4, NULL, NULL, 'Sony WH-1000XM5', '??', '— · Default', 18999, 1, 'processing', NULL, NULL, NULL, NULL),
(9, 5, NULL, NULL, 'NIKE Revolution 8 Running Shoes For Men', '📦', 'UK 8 · Cosmic Purple', 3865, 2, 'delivered', NULL, NULL, NULL, '2026-04-17 23:34:37'),
(10, 5, 18, 2, 'NIKE Revolution 8 Running Shoes For Men', '📦', '— · Default', 3865, 1, 'delivered', NULL, NULL, NULL, '2026-04-17 23:34:37'),
(11, 6, 18, 2, 'NIKE Revolution 8 Running Shoes For Men', '📦', '— · Default', 3865, 1, 'cancelled', NULL, NULL, NULL, NULL),
(12, 7, 18, 2, 'NIKE Revolution 8 Running Shoes For Men', '📦', 'UK 8 · Cosmic Purple', 3865, 1, 'delivered', NULL, NULL, NULL, '2026-04-18 09:50:19'),
(13, 8, 18, 2, 'NIKE Revolution 8 Running Shoes For Men', '📦', 'Standard · Default', 3865, 1, 'cancelled', NULL, NULL, NULL, NULL),
(14, 9, 18, 2, 'NIKE Revolution 8 Running Shoes For Men', '📦', 'Standard · Default', 3865, 1, 'delivered', NULL, NULL, NULL, '2026-04-18 11:03:09'),
(15, 10, 19, 2, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', '📦', 'M · Yellow', 300, 2, 'cancelled', NULL, NULL, NULL, NULL),
(16, 10, 19, 2, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', '📦', 'L · Yellow', 300, 2, 'cancelled', NULL, NULL, NULL, NULL),
(17, 11, 19, 2, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', '📦', 'XL · Yellow', 300, 1, 'delivered', NULL, NULL, NULL, '2026-04-18 14:00:08'),
(18, 12, 19, 2, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', '📦', 'L · Yellow', 300, 2, 'delivered', NULL, NULL, NULL, '2026-04-18 22:11:16'),
(19, 13, 19, 2, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', '📦', 'XL · Yellow', 300, 1, 'delivered', NULL, NULL, NULL, '2026-04-18 23:58:44'),
(20, 14, 18, 2, 'NIKE Revolution 8 Running Shoes For Men', '📦', 'UK 7 · White', 3865, 1, 'delivered', NULL, NULL, NULL, '2026-04-19 00:01:45'),
(21, 15, 18, 2, 'NIKE Revolution 8 Running Shoes For Men', '📦', 'UK 6 · White', 3865, 1, 'delivered', NULL, NULL, NULL, '2026-04-19 00:07:32'),
(22, 16, 18, 2, 'NIKE Revolution 8 Running Shoes For Men', '📦', 'UK 9 · White', 3865, 1, 'delivered', NULL, NULL, NULL, '2026-04-19 00:39:25'),
(23, 17, NULL, NULL, 'REDTAPE Men Skinny Mid Rise Blue Jeans', '📦', 'Standard · Default', 999, 1, 'delivered', NULL, NULL, NULL, '2026-04-21 16:09:51'),
(24, 18, 24, 2, 'REDTAPE Men Skinny Mid Rise Blue Jeans', '📦', '30 · Blue', 999, 2, 'delivered', NULL, NULL, NULL, '2026-04-21 20:29:07'),
(25, 18, 24, 2, 'REDTAPE Men Skinny Mid Rise Blue Jeans', '📦', '34 · Blue', 999, 2, 'delivered', NULL, NULL, NULL, '2026-04-21 20:29:07'),
(26, 19, 25, 8, 'REDTAPE Men Skinny Mid Rise Blue Jeans', '📦', '28 · Blue', 994, 2, 'delivered', NULL, NULL, NULL, '2026-04-21 21:49:56'),
(27, 20, 26, 8, 'Roadster Men Slim Mid Rise Dark Blue Jeans', '📦', '32 · Blue', 449, 3, 'cancelled', NULL, NULL, NULL, NULL),
(28, 21, 24, 2, 'REDTAPE Men Skinny Mid Rise Blue Jeans', '📦', '32 · Blue', 999, 5, 'delivered', NULL, NULL, '2026-04-22 12:50:39', '2026-04-22 12:54:10'),
(29, 22, 26, 8, 'Roadster Men Slim Mid Rise Dark Blue Jeans', '📦', '32 · Blue', 449, 2, 'delivered', NULL, '2026-04-22 13:40:18', '2026-04-22 13:43:08', '2026-04-22 13:44:30'),
(30, 23, 19, 2, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', '📦', 'M · Yellow', 300, 1, 'delivered', '2026-04-22 22:58:21', '2026-04-22 22:59:29', '2026-04-22 23:02:16', '2026-04-22 23:18:46'),
(31, 23, 25, 8, 'REDTAPE Men Skinny Mid Rise Blue Jeans', '📦', '28 · Blue', 994, 1, 'delivered', '2026-04-22 22:56:56', '2026-04-22 22:58:49', '2026-04-22 23:02:16', '2026-04-22 23:16:10'),
(32, 24, 18, NULL, 'NIKE Revolution 8 Running Shoes For Men', '📦', 'UK 9 · White', 3865, 1, 'delivered', '2026-04-22 23:57:19', '2026-04-23 00:08:12', '2026-04-23 00:13:52', '2026-04-23 00:13:55'),
(33, 25, 24, NULL, 'REDTAPE Men Skinny Mid Rise Blue Jeans', '📦', '28 · Blue', 999, 1, 'delivered', '2026-04-23 12:46:36', '2026-04-23 12:47:23', '2026-04-23 12:49:40', '2026-04-23 12:50:26'),
(34, 26, 26, NULL, 'Roadster Men Slim Mid Rise Dark Blue Jeans', '📦', '30 · Blue', 449, 1, 'cancelled', '2026-04-23 12:55:33', NULL, NULL, NULL),
(35, 27, 19, NULL, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', '📦', 'M · Yellow', 300, 1, 'cancelled', NULL, NULL, NULL, NULL),
(36, 28, 24, NULL, 'REDTAPE Men Skinny Mid Rise Blue Jeans', '📦', '28 · Blue', 999, 1, 'delivered', '2026-04-24 14:27:23', '2026-04-24 14:29:44', '2026-04-24 14:58:21', '2026-04-24 14:59:01'),
(37, 29, 29, NULL, 'Women Regular Fit Striped Mandarin Collar Casual Shirt', '📦', 'L · Blue', 286, 1, 'delivered', '2026-04-24 21:04:13', '2026-04-24 21:04:22', '2026-04-24 21:04:29', '2026-04-24 21:04:36'),
(38, 30, 30, NULL, 'Women Regular Fit Striped Mandarin Collar Casual Shirt', '📦', 'L · Yellow', 275, 1, 'cancelled', NULL, NULL, NULL, NULL),
(39, 31, 29, NULL, 'Women Regular Fit Striped Mandarin Collar Casual Shirt', '📦', 'L · Blue', 286, 1, 'cancelled', '2026-04-26 23:14:17', NULL, NULL, NULL),
(40, 32, 26, NULL, 'Roadster Men Slim Mid Rise Dark Blue Jeans', '📦', '30 · Blue', 449, 1, 'processing', NULL, NULL, NULL, NULL),
(41, 32, 19, NULL, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', '📦', 'S · Yellow', 300, 2, 'processing', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `platform_payment_gateway_config`
--

CREATE TABLE `platform_payment_gateway_config` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `gateway` varchar(32) NOT NULL DEFAULT 'none',
  `mode` varchar(8) NOT NULL DEFAULT 'test',
  `public_key` varchar(255) NOT NULL DEFAULT '',
  `secret_key` varchar(255) NOT NULL DEFAULT '',
  `merchant_id` varchar(120) NOT NULL DEFAULT '',
  `webhook_secret` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_payment_gateway_config`
--

INSERT INTO `platform_payment_gateway_config` (`id`, `gateway`, `mode`, `public_key`, `secret_key`, `merchant_id`, `webhook_secret`, `created_at`, `updated_at`) VALUES
(1, 'none', 'test', '', '', '', '', '2026-04-21 19:06:05', '2026-04-21 19:06:05');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `sku` varchar(80) DEFAULT NULL,
  `category` varchar(64) NOT NULL,
  `product_type` varchar(64) NOT NULL DEFAULT '',
  `style_group_code` varchar(120) DEFAULT NULL,
  `gender` varchar(16) NOT NULL DEFAULT 'unisex',
  `price` int(10) UNSIGNED NOT NULL,
  `original_price` int(10) UNSIGNED NOT NULL,
  `emoji` varchar(16) NOT NULL DEFAULT '??',
  `badge` varchar(64) NOT NULL DEFAULT '',
  `rating` decimal(2,1) NOT NULL DEFAULT 4.5,
  `review_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `brand` varchar(255) NOT NULL DEFAULT 'LUXE',
  `image_bg` varchar(32) NOT NULL DEFAULT '#1a0a2e',
  `image_path` varchar(255) DEFAULT NULL,
  `size_options` varchar(255) NOT NULL DEFAULT '',
  `color_options` varchar(255) NOT NULL DEFAULT '',
  `primary_color` varchar(64) DEFAULT NULL,
  `stock_qty` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `offer_flash_text` varchar(150) NOT NULL DEFAULT '',
  `offer_countdown_seconds` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `offer_bank_text` varchar(150) NOT NULL DEFAULT '',
  `shipping_class` varchar(32) NOT NULL DEFAULT 'standard',
  `manufacturer_generic_name` varchar(255) NOT NULL DEFAULT '',
  `manufacturer_country` varchar(128) NOT NULL DEFAULT '',
  `manufacturer_name_address` varchar(2000) NOT NULL DEFAULT '',
  `packer_name_address` varchar(2000) NOT NULL DEFAULT '',
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `approval_status` varchar(20) NOT NULL DEFAULT 'approved',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `seller_id`, `name`, `slug`, `sku`, `category`, `product_type`, `style_group_code`, `gender`, `price`, `original_price`, `emoji`, `badge`, `rating`, `review_count`, `brand`, `image_bg`, `image_path`, `size_options`, `color_options`, `primary_color`, `stock_qty`, `description`, `offer_flash_text`, `offer_countdown_seconds`, `offer_bank_text`, `shipping_class`, `manufacturer_generic_name`, `manufacturer_country`, `manufacturer_name_address`, `packer_name_address`, `active`, `approval_status`, `created_at`) VALUES
(18, 2, 'NIKE Revolution 8 Running Shoes For Men', 'nike-revolution-8-running-shoes-for-men-2', 'FAS-NIKERE-V1GU', 'fashion', '', NULL, 'unisex', 3865, 4295, '📦', 'Hot', 5.0, 1, 'Nike', '#1a0a2e', 'uploads/products/seller-2-1776447692-0-f8619acb.jpg', 'UK 6, UK 7, UK 8, UK 9, UK 10', 'Black, White, Blue, Navy, Red, Green', NULL, 7, 'The Revolution 8 is an evolution of your faithful favourite. Every stride is cushioned thanks to a foam midsole and a flexible forefoot that makes your steps soft. Plus, the mesh upper is even more breathable than the previous iteration, so you can run in cool, collected comfort.', 'Extra 10% off with HDFC card', 172800, '', 'standard', '', '', '', '', 1, 'approved', '2026-04-18 19:13:36'),
(19, 2, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', 'mack-jonney-men-solid-polo-neck-cotton-blend-yellow-t-shirt', 'FAS-MACKJO-9RR2', 'fashion', 'tshirt', 'fashion-mack-jonney-men-solid-polo-neck-cotton-blend-yellow-t-shirt', 'men', 300, 999, '📦', 'Sale', 4.0, 1, 'MACK JONNEY', '#1a0a2e', 'uploads/products/seller-2-1776492174-0-39ebad45.jpg', 'S, M, L', 'Yellow', 'Yellow', 10, '<p>If you are a fashion cognizant, you will surely gravitate towards this T-Shirt from the Mack Jonney. Its premium quality fabric feels good against your skin and ensures maximum breathability and easy maintenance. Besides, it flaunts a polo neck and a other pattern which accentuates its overall design.</p>', 'Flash deal ends in', 259200, 'Extra 5% off with Axis card', 'standard', '', '', '', '', 1, 'approved', '2026-04-18 19:13:36'),
(24, 2, 'REDTAPE Men Skinny Mid Rise Blue Jeans', 'redtape-men-skinny-mid-rise-blue-jeans', 'FAS-REDTAP-9FAE', 'fashion', 'jeans', 'fashion-jeans-redtape-men-skinny-mid-rise-blue-jeans', 'men', 999, 5999, '📦', 'Sale', 4.5, 0, 'REDTAPE', '#1a0a2e', 'uploads/products/seller-2-1776778826-0-b3b04536.jpg', '28, 30, 32, 34, 36', 'Blue', 'Blue', 89, '<p><span style=\"color: rgb(51, 51, 51);\">Crafted from a soft and flexible blend of 75% cotton, 23% polyester, and 2% elastane, these Red Tape knitted denim jeans combine the look of denim with the comfort of knitwear. Designed for casual wear, they feature a mid-rise waist, solid pattern, and a secure button-and-zip fly closure, making them your go-to jeans for effortless everyday style. Features: Stretchable Knit Denim: Offers a soft feel and comfortable stretch with a durable cotton-poly-elastane blend. Mid-Rise Fit: Sits naturally on the waist for everyday ease. Classic 5-Pocket Style: Includes functional front and back pockets for utility and design. Solid Look: Clean, versatile design perfect for pairing with tees, shirts, or jackets.</span></p>', 'Flash deal ends in', 172800, 'Extra 10% off with HDFC card', 'standard', 'Jeans', 'India', 'REDTAPE LIMITED, Plot No - 08, Sector 90, Noida, Gautam Buddha Nagar, Uttar Pradesh - 201301, Customer Care Email id - customercare@redtapeindia.com, Phone Number +91 7836850000', 'REDTAPE LIMITED, Plot No - 08, Sector 90, Noida, Gautam Buddha Nagar, Uttar Pradesh - 201301, Customer Care Email id - customercare@redtapeindia.com, Phone Number +91 7836850000', 1, 'approved', '2026-04-21 13:40:26'),
(25, 8, 'REDTAPE Men Skinny Mid Rise Blue Jeans', 'redtape-men-skinny-mid-rise-blue-jeans-2', 'FAS-REDTAP-747A', 'fashion', 'jeans', NULL, 'unisex', 994, 5999, '📦', 'Hot Deal', 5.0, 1, 'Redtape', '#1a0a2e', 'uploads/products/seller-8-1776787890-0-acea2d89.jpg', '28, 30, 32, 34, 36', 'Blue', NULL, 23, '<p><span style=\"color: rgb(51, 51, 51);\">Crafted from a soft and flexible blend of 75% cotton, 23% polyester, and 2% elastane, these Red Tape knitted denim jeans combine the look of denim with the comfort of knitwear. Designed for casual wear, they feature a mid-rise waist, solid pattern, and a secure button-and-zip fly closure, making them your go-to jeans for effortless everyday style. Features: Stretchable Knit Denim: Offers a soft feel and comfortable stretch with a durable cotton-poly-elastane blend. Mid-Rise Fit: Sits naturally on the waist for everyday ease. Classic 5-Pocket Style: Includes functional front and back pockets for utility and design. Solid Look: Clean, versatile design perfect for pairing with tees, shirts, or jackets.</span></p>', 'Flash deal ends in', 172800, 'Extra 10% off with ICICI Card', 'standard', 'Jeans', 'India', 'REDTAPE LIMITED, Plot No - 08, Sector 90, Noida, Gautam Buddha Nagar, Uttar Pradesh - 201301, Customer Care Email id - customercare@redtapeindia.com, Phone Number +91 7836850000', 'REDTAPE LIMITED, Plot No - 08, Sector 90, Noida, Gautam Buddha Nagar, Uttar Pradesh - 201301, Customer Care Email id - customercare@redtapeindia.com, Phone Number +91 7836850000', 1, 'approved', '2026-04-21 16:11:30'),
(26, 8, 'Roadster Men Slim Mid Rise Dark Blue Jeans', 'roadster-men-slim-mid-rise-dark-blue-jeans', 'FAS-ROADST-E141', 'fashion', 'jeans', NULL, 'unisex', 449, 999, '📦', 'Sale', 2.0, 1, 'Roadster', '#1a0a2e', 'uploads/products/seller-8-1776832901-0-d823cad3.jpg', '30, 32, 34', 'Blue', NULL, 46, '<p><span style=\"color: rgb(51, 51, 51);\">Dark shade, light fade blue jeans. Features a slim fit with a mid-rise waist, clean look, and stretchable fabric for comfort. Comes in a classic 5-pocket design with a regular length. The model (height 6\'2\") is wearing a size 32.</span></p>', 'Flash deal ends in', 86400, 'Extra 5% off with UPI Payment', 'standard', 'Jeans', 'India', 'Maan Enterprise  203  Sachet Allure  Opposite Riddhi Tower   Ahmedabad  Gujarat  380015', 'Maan Enterprise  203  Sachet Allure  Opposite Riddhi Tower   Ahmedabad  Gujarat  380015', 1, 'approved', '2026-04-22 04:41:41'),
(29, 8, 'Women Regular Fit Striped Mandarin Collar Casual Shirt', 'women-regular-fit-striped-mandarin-collar-casual-shirt', 'FAS-WOMENR-A21A', 'fashion', 'shirt', 'women-shirt-striped-mandarin', 'women', 286, 999, '📦', 'Sale', 3.0, 1, 'Sheetal Associates', '#1a0a2e', 'uploads/products/seller-8-1777033304-0-94c3647b.jpg', 'S, M, L', 'Blue', 'Blue', 30, '<p><span style=\"color: rgb(51, 51, 51);\">Elevate your everyday style with this blue and white vertical striped casual shirt for women. Designed with a smart mandarin collar, button-down front, and 3/4 sleeves, it offers a perfect balance of comfort and style. The lightweight, breathable fabric ensures all-day ease, while the relaxed fit makes it ideal for casual outings, office wear, or weekend get-togethers. Pair it effortlessly with jeans, trousers, or shorts for a chic, versatile look.</span></p>', 'Flash deal ends in', 259200, 'Extra 10% off with HDFC card', 'standard', 'Shirt', 'India', 'Sheetal Associates', 'Sheetal Associates', 1, 'approved', '2026-04-24 12:21:44'),
(30, 8, 'Women Regular Fit Striped Mandarin Collar Casual Shirt', 'women-regular-fit-striped-mandarin-collar-casual-shirt-2', 'FAS-WOMENR-73E0', 'fashion', 'shirt', 'women-shirt-striped-mandarin', 'women', 275, 999, '📦', 'Hot', 4.5, 0, 'Sheetal Associates', '#1a0a2e', 'uploads/products/seller-8-1777033424-0-2d652803.jpg', 'S, M, L', 'Yellow', 'Yellow', 30, '<p><span style=\"color: rgb(51, 51, 51);\">Elevate your everyday style with this blue and white vertical striped casual shirt for women. Designed with a smart mandarin collar, button-down front, and 3/4 sleeves, it offers a perfect balance of comfort and style. The lightweight, breathable fabric ensures all-day ease, while the relaxed fit makes it ideal for casual outings, office wear, or weekend get-togethers. Pair it effortlessly with jeans, trousers, or shorts for a chic, versatile look.</span></p>', 'Flash deal ends in', 259200, 'Extra 10% off with HDFC card', 'standard', 'Shirt', 'India', 'Sheetal Associates', 'Sheetal Associates', 1, 'approved', '2026-04-24 12:23:44');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `color_label` varchar(64) DEFAULT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_path`, `color_label`, `sort_order`, `created_at`) VALUES
(9, 18, 'uploads/products/seller-2-1776447692-0-f8619acb.jpg', NULL, 0, '2026-04-17 17:41:32'),
(10, 18, 'uploads/products/seller-2-1776447692-1-eed05b0c.jpg', NULL, 1, '2026-04-17 17:41:32'),
(11, 19, 'uploads/products/seller-2-1776492174-0-39ebad45.jpg', NULL, 0, '2026-04-18 06:02:54'),
(12, 19, 'uploads/products/seller-2-1776492174-1-95f642a3.jpg', NULL, 1, '2026-04-18 06:02:54'),
(24, 24, 'uploads/products/seller-2-1776778826-0-b3b04536.jpg', NULL, 0, '2026-04-21 13:40:26'),
(25, 24, 'uploads/products/seller-2-1776778826-1-3133fd27.jpg', NULL, 1, '2026-04-21 13:40:26'),
(26, 24, 'uploads/products/seller-2-1776778826-2-56a34a92.jpg', NULL, 2, '2026-04-21 13:40:26'),
(27, 24, 'uploads/products/seller-2-1776778826-3-ad855847.jpg', NULL, 3, '2026-04-21 13:40:26'),
(28, 25, 'uploads/products/seller-8-1776787890-0-acea2d89.jpg', NULL, 0, '2026-04-21 16:11:30'),
(29, 25, 'uploads/products/seller-8-1776787890-1-e75b89d3.jpg', NULL, 1, '2026-04-21 16:11:30'),
(30, 25, 'uploads/products/seller-8-1776787890-2-e4e22db4.jpg', NULL, 2, '2026-04-21 16:11:30'),
(31, 25, 'uploads/products/seller-8-1776787890-3-95ab77d6.jpg', NULL, 3, '2026-04-21 16:11:30'),
(32, 26, 'uploads/products/seller-8-1776832901-0-d823cad3.jpg', NULL, 0, '2026-04-22 04:41:41'),
(33, 26, 'uploads/products/seller-8-1776832901-1-6c8faf08.jpg', NULL, 1, '2026-04-22 04:41:41'),
(34, 26, 'uploads/products/seller-8-1776832901-2-6cb41006.jpg', NULL, 2, '2026-04-22 04:41:41'),
(35, 26, 'uploads/products/seller-8-1776832901-3-635d9d51.jpg', NULL, 3, '2026-04-22 04:41:41'),
(50, 29, 'uploads/products/seller-8-1777033304-0-94c3647b.jpg', NULL, 0, '2026-04-24 12:21:44'),
(51, 29, 'uploads/products/seller-8-1777033304-1-0ccaf9d7.jpg', NULL, 1, '2026-04-24 12:21:44'),
(52, 29, 'uploads/products/seller-8-1777033304-2-3891b330.jpg', NULL, 2, '2026-04-24 12:21:44'),
(53, 29, 'uploads/products/seller-8-1777033304-3-edf2c9ea.jpg', NULL, 3, '2026-04-24 12:21:44'),
(54, 30, 'uploads/products/seller-8-1777033424-0-2d652803.jpg', NULL, 0, '2026-04-24 12:23:44'),
(55, 30, 'uploads/products/seller-8-1777033424-1-c7f9b46c.jpg', NULL, 1, '2026-04-24 12:23:44'),
(56, 30, 'uploads/products/seller-8-1777033424-2-af7dfc14.jpg', NULL, 2, '2026-04-24 12:23:44'),
(57, 30, 'uploads/products/seller-8-1777033424-3-ccf0a855.jpg', NULL, 3, '2026-04-24 12:23:44');

-- --------------------------------------------------------

--
-- Table structure for table `product_reviews`
--

CREATE TABLE `product_reviews` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(120) NOT NULL DEFAULT 'Customer',
  `rating` tinyint(3) UNSIGNED NOT NULL DEFAULT 5,
  `review_text` varchar(1000) NOT NULL DEFAULT '',
  `review_status` varchar(16) NOT NULL DEFAULT 'pending',
  `seller_response` varchar(1000) NOT NULL DEFAULT '',
  `seller_reviewed_at` datetime DEFAULT NULL,
  `seller_responded_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_reviews`
--

INSERT INTO `product_reviews` (`id`, `product_id`, `user_id`, `customer_name`, `rating`, `review_text`, `review_status`, `seller_response`, `seller_reviewed_at`, `seller_responded_at`, `created_at`, `updated_at`) VALUES
(1, 18, 2, 'The Artist', 5, 'Nice Product in this price', 'approved', 'Thanks for your Purchase', '2026-04-18 00:10:39', '2026-04-18 00:10:39', '2026-04-17 18:35:43', '2026-04-17 18:40:39'),
(2, 19, 2, 'The Artist', 4, 'Average product, But k hisab se sahi hai', 'approved', 'Thanks', '2026-04-19 10:09:19', '2026-04-19 10:09:19', '2026-04-19 04:38:53', '2026-04-19 04:39:19'),
(3, 25, 2, 'The Artist', 5, 'Good Quality, and Great offer Deal', 'approved', 'Thanks', '2026-04-21 23:54:24', '2026-04-21 23:54:24', '2026-04-21 16:49:45', '2026-04-21 18:24:24'),
(4, 26, 2, 'The Artist', 2, 'Quality not as expected', 'approved', 'Sorry For inconvenience', '2026-04-22 13:52:16', '2026-04-22 13:52:16', '2026-04-22 08:21:27', '2026-04-22 08:22:16'),
(5, 29, 2, 'The Artist', 3, 'Quality not good', 'approved', '', '2026-04-26 23:30:48', NULL, '2026-04-26 17:59:50', '2026-04-26 18:00:48');

-- --------------------------------------------------------

--
-- Table structure for table `product_variant_inventory`
--

CREATE TABLE `product_variant_inventory` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `size_label` varchar(64) NOT NULL DEFAULT '',
  `color_label` varchar(64) NOT NULL DEFAULT '',
  `stock_qty` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_variant_inventory`
--

INSERT INTO `product_variant_inventory` (`id`, `product_id`, `size_label`, `color_label`, `stock_qty`, `active`, `created_at`, `updated_at`) VALUES
(1, 18, 'UK 6', 'White', 1, 1, '2026-04-18 05:35:28', '2026-04-18 18:40:49'),
(2, 18, 'UK 7', 'White', 1, 1, '2026-04-18 05:35:28', '2026-04-18 18:31:45'),
(3, 18, 'UK 8', 'White', 1, 1, '2026-04-18 05:35:28', '2026-04-18 05:35:28'),
(4, 18, 'UK 9', 'White', 3, 1, '2026-04-18 05:35:28', '2026-04-22 18:27:05'),
(5, 18, 'UK 10', 'White', 1, 1, '2026-04-18 05:35:28', '2026-04-18 05:36:05'),
(11, 19, 'S', 'Yellow', 0, 1, '2026-04-18 06:05:13', '2026-05-14 12:55:29'),
(12, 19, 'M', 'Yellow', 1, 1, '2026-04-18 06:05:13', '2026-04-23 07:35:44'),
(13, 19, 'L', 'Yellow', 3, 1, '2026-04-18 06:05:13', '2026-04-28 12:45:30'),
(14, 19, 'XL', 'Yellow', 2, 1, '2026-04-18 06:05:13', '2026-04-18 18:30:17'),
(15, 19, 'XXL', 'Yellow', 2, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(16, 18, 'UK 6', 'Black', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(18, 18, 'UK 6', 'Blue', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(19, 18, 'UK 6', 'Navy', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(20, 18, 'UK 6', 'Red', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(21, 18, 'UK 6', 'Green', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(22, 18, 'UK 7', 'Black', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(24, 18, 'UK 7', 'Blue', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(25, 18, 'UK 7', 'Navy', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(26, 18, 'UK 7', 'Red', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(27, 18, 'UK 7', 'Green', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(28, 18, 'UK 8', 'Black', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(30, 18, 'UK 8', 'Blue', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(31, 18, 'UK 8', 'Navy', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(32, 18, 'UK 8', 'Red', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(33, 18, 'UK 8', 'Green', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(34, 18, 'UK 9', 'Black', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(36, 18, 'UK 9', 'Blue', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(37, 18, 'UK 9', 'Navy', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(38, 18, 'UK 9', 'Red', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(39, 18, 'UK 9', 'Green', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(40, 18, 'UK 10', 'Black', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(42, 18, 'UK 10', 'Blue', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(43, 18, 'UK 10', 'Navy', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(44, 18, 'UK 10', 'Red', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(45, 18, 'UK 10', 'Green', 0, 1, '2026-04-18 06:05:13', '2026-04-18 06:05:13'),
(46, 24, '28', 'Blue', 18, 1, '2026-04-21 13:40:26', '2026-04-24 08:56:53'),
(47, 24, '30', 'Blue', 13, 1, '2026-04-21 13:40:26', '2026-04-21 14:59:07'),
(48, 24, '32', 'Blue', 20, 1, '2026-04-21 13:40:26', '2026-04-22 07:05:36'),
(49, 24, '34', 'Blue', 18, 1, '2026-04-21 13:40:26', '2026-04-21 14:59:07'),
(50, 24, '36', 'Blue', 20, 1, '2026-04-21 13:40:26', '2026-04-21 13:40:26'),
(91, 25, '28', 'Blue', 3, 1, '2026-04-21 16:11:30', '2026-04-22 17:54:34'),
(92, 25, '30', 'Blue', 5, 1, '2026-04-21 16:11:30', '2026-04-21 16:11:30'),
(93, 25, '32', 'Blue', 5, 1, '2026-04-21 16:11:30', '2026-04-21 16:11:30'),
(94, 25, '34', 'Blue', 5, 1, '2026-04-21 16:11:30', '2026-04-21 16:11:30'),
(95, 25, '36', 'Blue', 5, 1, '2026-04-21 16:11:30', '2026-04-21 16:11:30'),
(101, 26, '30', 'Blue', 18, 1, '2026-04-22 04:41:41', '2026-05-14 12:55:29'),
(102, 26, '32', 'Blue', 17, 1, '2026-04-22 04:41:41', '2026-04-22 08:20:23'),
(103, 26, '34', 'Blue', 10, 1, '2026-04-22 04:41:41', '2026-04-22 04:43:06'),
(246, 29, 'S', 'Blue', 10, 1, '2026-04-24 12:21:44', '2026-04-24 12:21:44'),
(247, 29, 'M', 'Blue', 10, 1, '2026-04-24 12:21:44', '2026-04-24 12:21:44'),
(248, 29, 'L', 'Blue', 8, 1, '2026-04-24 12:21:44', '2026-04-26 17:07:22'),
(249, 30, 'S', 'Yellow', 10, 1, '2026-04-24 12:23:44', '2026-04-24 12:23:44'),
(250, 30, 'M', 'Yellow', 10, 1, '2026-04-24 12:23:44', '2026-04-24 12:23:44'),
(251, 30, 'L', 'Yellow', 9, 1, '2026-04-24 12:23:44', '2026-04-24 18:27:01');

-- --------------------------------------------------------

--
-- Table structure for table `seller_account_deletion_requests`
--

CREATE TABLE `seller_account_deletion_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `full_name` varchar(120) NOT NULL DEFAULT '',
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seller_account_deletion_requests`
--

INSERT INTO `seller_account_deletion_requests` (`id`, `seller_id`, `email`, `full_name`, `status`, `requested_at`, `reviewed_by`, `reviewed_at`, `rejection_reason`) VALUES
(1, 1, 'seller@luxe.com', 'Default Seller', 'approved', '2026-04-17 17:03:55', 1, '2026-04-17 22:34:27', '');

-- --------------------------------------------------------

--
-- Table structure for table `seller_bank_accounts`
--

CREATE TABLE `seller_bank_accounts` (
  `id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `bank_name` varchar(120) NOT NULL,
  `account_holder_name` varchar(120) NOT NULL,
  `account_number` varchar(40) NOT NULL,
  `ifsc` varchar(20) NOT NULL,
  `upi_id` varchar(100) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seller_coupons`
--

CREATE TABLE `seller_coupons` (
  `id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(32) NOT NULL,
  `discount_type` enum('percent','flat') NOT NULL,
  `discount_value` int(10) UNSIGNED NOT NULL,
  `max_discount_rupees` int(10) UNSIGNED DEFAULT NULL,
  `min_order_rupees` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `description` varchar(255) NOT NULL DEFAULT '',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seller_coupons`
--

INSERT INTO `seller_coupons` (`id`, `seller_id`, `code`, `discount_type`, `discount_value`, `max_discount_rupees`, `min_order_rupees`, `description`, `is_active`, `valid_from`, `valid_until`, `created_at`) VALUES
(1, 2, 'SAVE10', 'percent', 10, 100, 1000, '', 1, '2026-04-19', '2026-05-18', '2026-04-19 13:55:18'),
(3, 8, 'SUMM10', 'percent', 10, 100, 1000, 'Summer Sale', 1, '2026-04-21', '2026-04-23', '2026-04-21 16:13:44');

-- --------------------------------------------------------

--
-- Table structure for table `seller_create_requests`
--

CREATE TABLE `seller_create_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(40) NOT NULL DEFAULT '',
  `requested_password_hash` varchar(255) NOT NULL DEFAULT '',
  `requested_categories` varchar(255) NOT NULL DEFAULT '',
  `note` varchar(500) NOT NULL DEFAULT '',
  `business_name` varchar(150) NOT NULL DEFAULT '',
  `gst_number` varchar(20) NOT NULL DEFAULT '',
  `pan_number` varchar(20) NOT NULL DEFAULT '',
  `aadhaar_number` varchar(20) NOT NULL DEFAULT '',
  `bank_account_name` varchar(120) NOT NULL DEFAULT '',
  `bank_account_number` varchar(40) NOT NULL DEFAULT '',
  `bank_ifsc` varchar(20) NOT NULL DEFAULT '',
  `address_line1` varchar(255) NOT NULL DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `state` varchar(100) NOT NULL DEFAULT '',
  `pin_code` varchar(20) NOT NULL DEFAULT '',
  `id_proof_type` varchar(40) NOT NULL DEFAULT '',
  `id_proof_number` varchar(80) NOT NULL DEFAULT '',
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `seller_id` int(10) UNSIGNED DEFAULT NULL,
  `rejection_reason` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seller_create_requests`
--

INSERT INTO `seller_create_requests` (`id`, `full_name`, `email`, `phone`, `requested_password_hash`, `requested_categories`, `note`, `business_name`, `gst_number`, `pan_number`, `aadhaar_number`, `bank_account_name`, `bank_account_number`, `bank_ifsc`, `address_line1`, `city`, `state`, `pin_code`, `id_proof_type`, `id_proof_number`, `status`, `reviewed_by`, `reviewed_at`, `seller_id`, `rejection_reason`, `created_at`) VALUES
(1, 'The Artist', 'dcrewesports@gmail.com', '+91 98765 43210', '$2y$10$KjnPsws2f2YUFdS96J36meeT/YrXBYhctiVwl05I.fwfdFXthAXm6', 'fashion,electronics,beauty,home', '', 'Deadly Crew', '22AAAAA0000A1Z5', '', '', '', '', '', '', '', '', '', '', '', 'approved', 1, '2026-04-17 21:45:23', 2, '', '2026-04-17 16:14:54'),
(2, 'Farji Artist', 'farziarrtist@gmail.com', '1234567898', '$2y$10$kkskh45Gs1kPawuO73JkL.rOSluwknR09WGHjAo0tJRJlzCLsZ0uq', 'fashion,electronics,home', '', 'Farji Artist', '24ABCDE0000A1Z6', '', '', '', '', '', '', '', '', '', '', '', 'approved', 1, '2026-04-21 21:34:59', 8, '', '2026-04-21 16:02:36'),
(3, 'emo', 'demoseller@yopmail.com', '7081708138', '$2y$10$E6wj8qFq9yHyrr4hErzwfe2OtwurAhUA13GYCquqHRbmMKv9ohhFy', 'fashion,electronics,beauty', '', 'Kola', '22AAAAA0000A1G5', '', '', '', '', '', '', '', '', '', '', '', 'approved', 1, '2026-05-09 11:14:29', 9, '', '2026-05-09 05:16:36');

-- --------------------------------------------------------

--
-- Table structure for table `seller_delivery_options`
--

CREATE TABLE `seller_delivery_options` (
  `id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `option_code` varchar(32) NOT NULL,
  `option_label` varchar(80) NOT NULL,
  `eta_min_days` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `eta_max_days` tinyint(3) UNSIGNED NOT NULL DEFAULT 3,
  `fee_amount` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seller_delivery_options`
--

INSERT INTO `seller_delivery_options` (`id`, `seller_id`, `option_code`, `option_label`, `eta_min_days`, `eta_max_days`, `fee_amount`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 2, 'standard', 'Standard Delivery', 3, 7, 30, 1, 1, '2026-04-17 18:18:36', '2026-04-17 18:30:52'),
(2, 2, 'express', 'Express Delivery', 1, 2, 99, 1, 2, '2026-04-17 18:18:36', '2026-04-18 07:41:30'),
(3, 2, 'same_day', 'Same Day Delivery', 0, 1, 149, 1, 3, '2026-04-17 18:18:36', '2026-04-18 07:35:35'),
(4, 8, 'standard', 'Standard Delivery', 2, 5, 0, 1, 1, '2026-04-21 16:11:51', '2026-04-21 16:12:12'),
(5, 8, 'express', 'Express Delivery', 1, 2, 89, 1, 2, '2026-04-21 16:11:51', '2026-04-21 16:12:12'),
(6, 8, 'same_day', 'Same Day Delivery', 0, 1, 149, 1, 3, '2026-04-21 16:11:51', '2026-04-21 16:12:12');

-- --------------------------------------------------------

--
-- Table structure for table `seller_payment_gateway_configs`
--

CREATE TABLE `seller_payment_gateway_configs` (
  `seller_id` int(10) UNSIGNED NOT NULL,
  `gateway` varchar(32) NOT NULL DEFAULT 'none',
  `mode` varchar(8) NOT NULL DEFAULT 'test',
  `public_key` varchar(255) NOT NULL DEFAULT '',
  `secret_key` varchar(255) NOT NULL DEFAULT '',
  `merchant_id` varchar(120) NOT NULL DEFAULT '',
  `webhook_secret` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `seller_return_settings`
--

CREATE TABLE `seller_return_settings` (
  `seller_id` int(10) UNSIGNED NOT NULL,
  `return_window_days` tinyint(3) UNSIGNED NOT NULL DEFAULT 7,
  `return_conditions` text DEFAULT NULL,
  `refund_method` varchar(40) NOT NULL DEFAULT 'original_payment',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seller_return_settings`
--

INSERT INTO `seller_return_settings` (`seller_id`, `return_window_days`, `return_conditions`, `refund_method`, `updated_at`) VALUES
(2, 10, 'Product unused hona chahiye, original packaging required, damaged product return nahi hoga, etc.', 'original_payment', '2026-04-17 18:28:39'),
(8, 7, '', 'original_payment', '2026-04-21 16:12:19');

-- --------------------------------------------------------

--
-- Table structure for table `seller_shipping_settings`
--

CREATE TABLE `seller_shipping_settings` (
  `seller_id` int(10) UNSIGNED NOT NULL,
  `handling_time_days` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `default_shipping_fee` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `free_shipping_min_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `shipping_regions` varchar(255) NOT NULL DEFAULT 'All India',
  `cod_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `shipping_policy` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seller_shipping_settings`
--

INSERT INTO `seller_shipping_settings` (`seller_id`, `handling_time_days`, `default_shipping_fee`, `free_shipping_min_order`, `shipping_regions`, `cod_enabled`, `shipping_policy`, `updated_at`) VALUES
(2, 2, 30, 1000, 'All India', 1, '', '2026-04-18 07:49:02'),
(8, 2, 25, 1000, 'All India', 1, '', '2026-04-21 16:11:49');

-- --------------------------------------------------------

--
-- Table structure for table `seller_users`
--

CREATE TABLE `seller_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(120) NOT NULL DEFAULT 'Seller',
  `allowed_categories` varchar(255) NOT NULL DEFAULT 'fashion,electronics,beauty,home',
  `business_name` varchar(150) NOT NULL DEFAULT '',
  `gst_number` varchar(20) NOT NULL DEFAULT '',
  `pan_number` varchar(20) NOT NULL DEFAULT '',
  `aadhaar_number` varchar(20) NOT NULL DEFAULT '',
  `bank_name` varchar(120) NOT NULL DEFAULT '',
  `gst_doc_path` varchar(255) NOT NULL DEFAULT '',
  `pan_doc_path` varchar(255) NOT NULL DEFAULT '',
  `aadhaar_doc_path` varchar(255) NOT NULL DEFAULT '',
  `bank_account_name` varchar(120) NOT NULL DEFAULT '',
  `bank_account_number` varchar(40) NOT NULL DEFAULT '',
  `bank_ifsc` varchar(20) NOT NULL DEFAULT '',
  `address_line1` varchar(255) NOT NULL DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `state` varchar(100) NOT NULL DEFAULT '',
  `pin_code` varchar(20) NOT NULL DEFAULT '',
  `id_proof_type` varchar(40) NOT NULL DEFAULT '',
  `id_proof_number` varchar(80) NOT NULL DEFAULT '',
  `phone_number` varchar(40) NOT NULL DEFAULT '',
  `phone_verified_at` datetime DEFAULT NULL,
  `business_address` varchar(255) NOT NULL DEFAULT '',
  `logo_path` varchar(255) NOT NULL DEFAULT '',
  `banner_path` varchar(255) NOT NULL DEFAULT '',
  `kyc_completed` tinyint(1) NOT NULL DEFAULT 0,
  `kyc_updated_at` datetime DEFAULT NULL,
  `kyc_final_approved` tinyint(1) NOT NULL DEFAULT 0,
  `kyc_final_reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `kyc_final_reviewed_at` datetime DEFAULT NULL,
  `kyc_rejection_reason` varchar(255) NOT NULL DEFAULT '',
  `kyc_edit_request_status` varchar(16) NOT NULL DEFAULT 'none',
  `kyc_edit_requested_at` datetime DEFAULT NULL,
  `kyc_edit_reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `kyc_edit_reviewed_at` datetime DEFAULT NULL,
  `kyc_edit_rejection_reason` varchar(255) NOT NULL DEFAULT '',
  `kyc_edit_unlocked` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `storefront_cms_theme` varchar(24) NOT NULL DEFAULT 'luxe'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seller_users`
--

INSERT INTO `seller_users` (`id`, `email`, `email_verified_at`, `password_hash`, `full_name`, `allowed_categories`, `business_name`, `gst_number`, `pan_number`, `aadhaar_number`, `bank_name`, `gst_doc_path`, `pan_doc_path`, `aadhaar_doc_path`, `bank_account_name`, `bank_account_number`, `bank_ifsc`, `address_line1`, `city`, `state`, `pin_code`, `id_proof_type`, `id_proof_number`, `phone_number`, `phone_verified_at`, `business_address`, `logo_path`, `banner_path`, `kyc_completed`, `kyc_updated_at`, `kyc_final_approved`, `kyc_final_reviewed_by`, `kyc_final_reviewed_at`, `kyc_rejection_reason`, `kyc_edit_request_status`, `kyc_edit_requested_at`, `kyc_edit_reviewed_by`, `kyc_edit_reviewed_at`, `kyc_edit_rejection_reason`, `kyc_edit_unlocked`, `is_active`, `created_at`, `storefront_cms_theme`) VALUES
(2, 'dcrewesports@gmail.com', '2026-04-17 21:45:23', '$2y$10$KjnPsws2f2YUFdS96J36meeT/YrXBYhctiVwl05I.fwfdFXthAXm6', 'The Artist', 'fashion,electronics,beauty,home', 'Deadly Crew', '22AAAAA0000A1Z5', 'ABCDE1234E', '212121212121', 'HDFC Bank', 'uploads/seller-kyc/seller-2-gst_document-1776444652-f318d952.png', 'uploads/seller-kyc/seller-2-pan_document-1776444652-9cf10d10.png', 'uploads/seller-kyc/seller-2-aadhaar_document-1776444652-e594fc6b.png', 'The Artist', '1234567891', 'HDFC0001234', 'Address', 'LKO', 'UP', '201301', 'aadhaar', '212121212121', '9876543210', '2026-04-17 21:45:23', 'Noida UP', 'uploads/seller-branding/seller-2-logo_file-1776450191-847be4b0.png', 'uploads/seller-branding/seller-2-banner_file-1776450191-df91dc64.png', 1, '2026-04-17 22:20:52', 1, 1, '2026-04-17 22:24:23', '', 'none', NULL, NULL, NULL, '', 0, 1, '2026-04-17 16:15:23', 'theme-2'),
(7, 'seller@luxe.com', '2026-04-17 22:47:52', '$2y$10$6u7OEuIjrfAc97.0H25wpONcf40Wsgan7iXNNOdAfKEHblR87AS1a', 'Default Seller', 'fashion,electronics', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, '', '', '', 0, NULL, 0, NULL, NULL, '', 'none', NULL, NULL, NULL, '', 0, 1, '2026-04-17 17:17:52', 'luxe'),
(8, 'farziarrtist@gmail.com', '2026-04-21 21:34:59', '$2y$10$kkskh45Gs1kPawuO73JkL.rOSluwknR09WGHjAo0tJRJlzCLsZ0uq', 'Farji Artist', 'fashion,electronics,home', 'Farji Artist', '24ABCDE0000A1Z6', 'ABCDE3254E', '875487985644', 'ICICI Bank', 'uploads/seller-kyc/seller-8-gst_document-1776787624-f1d7cd69.png', 'uploads/seller-kyc/seller-8-pan_document-1776787624-4f1fd75b.png', 'uploads/seller-kyc/seller-8-aadhaar_document-1776787624-4782fe6f.png', 'Farji Artist', '848596748564', 'HDFC0004321', 'Address', 'Lucknow', 'UP', '226001', 'pan', 'ABCDE3254E', '1234567890', '2026-04-21 21:34:59', 'Lucknow UP-226001', 'uploads/seller-branding/seller-8-logo_file-1776787711-18eb89b3.png', 'uploads/seller-branding/seller-8-banner_file-1776787711-198899f7.png', 1, '2026-04-21 21:37:04', 1, 1, '2026-04-21 21:37:28', '', 'none', NULL, NULL, NULL, '', 0, 1, '2026-04-21 16:04:59', 'luxe'),
(9, 'demoseller@yopmail.com', '2026-05-09 11:14:29', '$2y$10$E6wj8qFq9yHyrr4hErzwfe2OtwurAhUA13GYCquqHRbmMKv9ohhFy', 'emo', 'fashion,electronics,beauty', 'Kola', '22AAAAA0000A1G5', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', NULL, '', '', '', 0, NULL, 0, NULL, NULL, '', 'none', NULL, NULL, NULL, '', 0, 1, '2026-05-09 05:44:29', 'luxe');

-- --------------------------------------------------------

--
-- Table structure for table `seller_withdraw_requests`
--

CREATE TABLE `seller_withdraw_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `amount` int(10) UNSIGNED NOT NULL,
  `method` varchar(32) NOT NULL DEFAULT 'bank',
  `account_ref` varchar(255) NOT NULL DEFAULT '',
  `note` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `rejection_reason` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `seller_withdraw_requests`
--

INSERT INTO `seller_withdraw_requests` (`id`, `seller_id`, `amount`, `method`, `account_ref`, `note`, `status`, `requested_at`, `reviewed_by`, `reviewed_at`, `rejection_reason`) VALUES
(1, 2, 10000, 'upi', 'der@upi', '', 'paid', '2026-04-18 18:49:57', 1, '2026-04-19 00:23:13', ''),
(2, 2, 5000, 'upi', 'der@upi', 'Please approve pay request', 'rejected', '2026-04-18 18:58:10', 1, '2026-04-19 00:29:40', 'no payment');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('active_storefront_theme', 'theme-2', '2026-04-26 09:13:14'),
('admin_seller_commission_percent', '1', '2026-04-23 19:51:27'),
('cart_below_min_shipping_fee_rupees', '40', '2026-04-23 19:51:27'),
('cart_free_shipping_min_rupees', '1000', '2026-04-23 19:51:27'),
('platform_fee_rupees', '5', '2026-04-23 19:51:27'),
('site_brand_name', 'LUXE', '2026-05-02 18:07:42'),
('site_contact_address', '37 W 24th St, New York, NY', '2026-05-02 18:07:42'),
('site_contact_email', 'infoweb@luxe.com', '2026-05-02 18:07:42'),
('site_contact_hours', 'Mon-Sat, 9:00-18:00 IST', '2026-05-02 18:07:42'),
('site_contact_phone', '+123 324 5879 39', '2026-05-02 18:07:42'),
('site_logo_path', 'uploads/site/logo_20260502_194408_f8cb4d.png', '2026-05-02 17:44:08'),
('storefront_theme', 'theme-1', '2026-05-12 05:36:02');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email_verified_at` datetime DEFAULT NULL,
  `phone_verified_at` datetime DEFAULT NULL,
  `phone` varchar(40) NOT NULL DEFAULT '',
  `dob` date DEFAULT NULL,
  `gender` varchar(16) DEFAULT NULL,
  `loyalty_points_redeemed` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `first_name`, `last_name`, `created_at`, `email_verified_at`, `phone_verified_at`, `phone`, `dob`, `gender`, `loyalty_points_redeemed`) VALUES
(1, 'demo@luxe.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Rahul', 'Sharma', '2026-04-15 06:15:57', '2026-04-15 11:45:57', NULL, '', NULL, NULL, 0),
(2, 'demo@yopmail.com', '$2y$10$FtRp3ZTyeh3NbecMUeZ7GOCERsuSTM2dUIuRVrJSs4iPJxvrb78Fy', 'The', 'Artist', '2026-04-15 06:48:36', '2026-04-15 12:18:36', '2026-04-26 22:55:08', '12345678790', '1999-06-16', 'female', 0),
(4, 'dk@yopmail.com', '$2y$10$ZNLRSmiW.NK3.gXl9i.SyurlngIS.eRLPy8KyAN4y93s49kTYD8DS', 'Rahul', 'Sharma', '2026-04-22 19:23:20', '2026-04-23 00:53:20', '2026-04-23 01:03:49', '7081708138', '2010-08-15', 'male', 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_account_deletion_requests`
--

CREATE TABLE `user_account_deletion_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `process_after` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_account_deletion_requests`
--

INSERT INTO `user_account_deletion_requests` (`id`, `user_id`, `email`, `first_name`, `last_name`, `status`, `requested_at`, `process_after`, `completed_at`) VALUES
(1, 3, 'dk@yopmail.com', 'Dileep', 'Kushwaha', 'completed', '2026-04-15 14:24:40', '2026-04-17 16:24:40', '2026-04-17 20:46:33'),
(2, 5, 'democode@yopmail.com', 'Demo', 'Code', 'completed', '2026-04-23 06:27:16', '2026-04-25 08:27:16', '2026-05-02 21:01:29');

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type_label` varchar(32) NOT NULL DEFAULT 'Home',
  `full_name` varchar(255) NOT NULL,
  `phone` varchar(40) NOT NULL DEFAULT '',
  `line1` varchar(255) NOT NULL,
  `line2` varchar(255) NOT NULL DEFAULT '',
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `pin` varchar(20) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_addresses`
--

INSERT INTO `user_addresses` (`id`, `user_id`, `type_label`, `full_name`, `phone`, `line1`, `line2`, `city`, `state`, `pin`, `is_default`) VALUES
(1, 1, 'Home', 'Rahul Sharma', '+91 98765 43210', 'Flat 402, Emerald Heights', 'Sector 15, Near Metro Station', 'Noida', 'Uttar Pradesh', '201301', 1),
(3, 2, 'Other', 'The Artist', '9876543210', 'C-603 HPCL Housing Socity', '', 'Greator Noida', 'Uttar Pradesh', '201301', 1),
(4, 2, 'Work', 'Deadly Crew', '+91 98765 43210', '91 Spring Board', 'Near Lemontree Hotel', 'Noida', 'Uttar Pradesh', '201311', 0),
(5, 4, 'Work', 'Rahul', '2233445566', 'hghsg hjsg jshgdf jshdgf shg', '', 'Mumbai', 'maharastra', '400001', 1);

-- --------------------------------------------------------

--
-- Table structure for table `user_order_cancel_requests`
--

CREATE TABLE `user_order_cancel_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `order_ref` varchar(32) NOT NULL,
  `reason` varchar(120) NOT NULL DEFAULT '',
  `details` varchar(1000) NOT NULL DEFAULT '',
  `seller_note` varchar(255) NOT NULL DEFAULT '',
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_order_cancel_requests`
--

INSERT INTO `user_order_cancel_requests` (`id`, `user_id`, `order_id`, `seller_id`, `order_ref`, `reason`, `details`, `seller_note`, `status`, `requested_at`, `reviewed_at`) VALUES
(1, 2, 6, 2, 'LUXE485229365', 'Changed my mind', 'Sorry', '', 'approved', '2026-04-18 04:17:48', '2026-04-18 09:48:02'),
(2, 2, 8, 2, 'LUXE488865946', 'Changed my mind', '', '', 'approved', '2026-04-18 05:11:55', '2026-04-18 10:42:07'),
(3, 2, 10, 2, 'LUXE498753837', 'Ordered by mistake', '', '', 'approved', '2026-04-18 08:30:15', '2026-04-18 14:00:22'),
(4, 2, 11, 2, 'LUXE501008270', 'Need faster delivery', '', '', 'rejected', '2026-04-18 08:31:20', '2026-04-18 14:01:47'),
(5, 2, 11, 2, 'LUXE501008270', 'Need faster delivery', '', '', 'rejected', '2026-04-18 08:31:55', '2026-04-18 14:12:27'),
(6, 2, 20, 8, 'LUXE838851397', 'Need faster delivery', '', '', 'approved', '2026-04-22 06:22:20', '2026-04-22 11:52:28'),
(7, 4, 26, 8, 'LUXE929058728', 'Ordered by mistake', '', '', 'approved', '2026-04-23 07:24:55', '2026-04-23 12:55:12'),
(8, 4, 26, 8, 'LUXE929058728', 'Ordered by mistake', '', '', 'approved', '2026-04-23 07:28:05', '2026-04-23 12:58:19'),
(9, 2, 27, 2, 'LUXE929744514', 'Ordered by mistake', '', '', 'approved', '2026-04-23 07:36:03', '2026-04-23 13:06:11'),
(10, 2, 30, 8, 'LUXE055221488', 'Ordered by mistake', '', '', 'approved', '2026-04-24 18:27:17', '2026-04-24 23:57:38'),
(11, 2, 31, 8, 'LUXE223242649', 'Ordered by mistake', '', '', 'approved', '2026-04-26 17:44:04', '2026-04-26 23:14:25');

-- --------------------------------------------------------

--
-- Table structure for table `user_order_enquiries`
--

CREATE TABLE `user_order_enquiries` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `order_item_id` int(10) UNSIGNED NOT NULL,
  `order_ref` varchar(32) NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `product_name` varchar(255) NOT NULL DEFAULT '',
  `message` varchar(1000) NOT NULL DEFAULT '',
  `seller_reply` varchar(1000) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `replied_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_order_enquiries`
--

INSERT INTO `user_order_enquiries` (`id`, `user_id`, `seller_id`, `order_id`, `order_item_id`, `order_ref`, `product_id`, `product_name`, `message`, `seller_reply`, `created_at`, `replied_at`) VALUES
(1, 2, 2, 24, 32, 'LUXE882425335', 18, 'NIKE Revolution 8 Running Shoes For Men', 'isme faster delivery hai?', 'an delivey change nahi kar sakte', '2026-04-22 18:28:00', '2026-04-22 23:58:26');

-- --------------------------------------------------------

--
-- Table structure for table `user_return_requests`
--

CREATE TABLE `user_return_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `order_ref` varchar(32) NOT NULL,
  `order_id` int(10) UNSIGNED DEFAULT NULL,
  `order_item_id` int(10) UNSIGNED DEFAULT NULL,
  `seller_id` int(10) UNSIGNED DEFAULT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `product_name` varchar(255) NOT NULL DEFAULT '',
  `reason` varchar(120) NOT NULL DEFAULT '',
  `details` varchar(1000) NOT NULL DEFAULT '',
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `pickup_status` varchar(24) NOT NULL DEFAULT 'not_scheduled',
  `pickup_note` varchar(255) NOT NULL DEFAULT '',
  `refund_amount` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `refund_mode` varchar(80) NOT NULL DEFAULT '',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `pickup_scheduled_at` datetime DEFAULT NULL,
  `pickup_completed_at` datetime DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_return_requests`
--

INSERT INTO `user_return_requests` (`id`, `user_id`, `order_ref`, `order_id`, `order_item_id`, `seller_id`, `product_id`, `product_name`, `reason`, `details`, `status`, `pickup_status`, `pickup_note`, `refund_amount`, `refund_mode`, `requested_at`, `reviewed_at`, `pickup_scheduled_at`, `pickup_completed_at`, `resolved_at`) VALUES
(1, 2, 'LUXE501008270', NULL, NULL, NULL, NULL, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', 'Damaged product', '', 'pending', 'not_scheduled', '', 0, '', '2026-04-18 08:45:54', NULL, NULL, NULL, NULL),
(2, 2, 'LUXE501008270', NULL, NULL, NULL, NULL, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', 'Damaged product', '', 'pending', 'not_scheduled', '', 0, '', '2026-04-18 08:47:57', NULL, NULL, NULL, NULL),
(3, 2, 'LUXE501008270', 11, 17, 2, 19, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', 'Damaged product', '', 'refunded', 'completed', '', 0, '', '2026-04-18 08:51:04', '2026-04-18 14:29:24', '2026-04-18 14:29:32', '2026-04-18 14:30:04', '2026-04-18 14:30:15'),
(4, 2, 'LUXE490389688', 9, 14, 2, 18, 'NIKE Revolution 8 Running Shoes For Men', 'Damaged product', '', 'refunded', 'completed', '', 3865, 'Cash on Delivery (COD)', '2026-04-18 13:06:51', '2026-04-18 19:11:41', '2026-04-18 19:14:11', '2026-04-18 19:15:00', '2026-04-18 19:15:02'),
(5, 2, 'LUXE530476784', 12, 18, 2, 19, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', 'Wrong item delivered', '', 'refunded', 'completed', '', 600, 'UPI', '2026-04-18 16:41:59', '2026-04-18 22:12:21', '2026-04-18 22:12:46', '2026-04-18 22:13:09', '2026-04-18 22:13:11'),
(6, 2, 'LUXE536924775', 13, 19, 2, 19, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', 'Wrong item delivered', '', 'refunded', 'completed', 'amount credited in your same account', 300, 'UPI', '2026-04-18 18:29:16', '2026-04-18 23:59:40', '2026-04-18 23:59:50', '2026-04-18 23:59:56', '2026-04-19 00:00:17'),
(7, 2, 'LUXE537452882', 15, 21, 2, 18, 'NIKE Revolution 8 Running Shoes For Men', 'Wrong item delivered', '', 'refunded', 'completed', '', 3865, 'UPI', '2026-04-18 18:40:31', '2026-04-19 00:10:44', '2026-04-19 00:10:46', '2026-04-19 00:10:48', '2026-04-19 00:10:49'),
(8, 2, 'LUXE845359635', 22, 29, 8, 26, 'Roadster Men Slim Mid Rise Dark Blue Jeans', 'Size/Fit issue', '', 'refunded', 'completed', '', 898, 'COD', '2026-04-22 08:15:11', '2026-04-22 13:45:28', '2026-04-22 13:48:47', '2026-04-22 13:50:12', '2026-04-22 13:50:23'),
(9, 2, 'LUXE871280991', 23, 30, 2, 19, 'MACK JONNEY Men Solid Polo Neck Cotton Blend Yellow T-Shirt', 'Wrong item delivered', '', 'refunded', 'completed', '', 300, 'COD', '2026-04-22 17:49:09', '2026-04-22 23:22:54', '2026-04-22 23:23:04', '2026-04-22 23:23:09', '2026-04-22 23:23:13'),
(10, 2, 'LUXE871280991', 23, 31, 8, 25, 'REDTAPE Men Skinny Mid Rise Blue Jeans', 'Quality not as expected', '', 'rejected', 'cancelled', '', 994, 'COD', '2026-04-22 17:49:25', '2026-04-22 23:19:50', NULL, NULL, '2026-04-22 23:19:50'),
(11, 2, 'LUXE871280991', 23, 31, 8, 25, 'REDTAPE Men Skinny Mid Rise Blue Jeans', 'Damaged product', '', 'refunded', 'completed', '', 994, 'COD', '2026-04-22 17:54:12', '2026-04-22 23:24:22', '2026-04-22 23:24:28', '2026-04-22 23:24:33', '2026-04-22 23:24:34'),
(12, 2, 'LUXE021013809', 28, 36, 2, 24, 'REDTAPE Men Skinny Mid Rise Blue Jeans', 'Damaged product', '', 'rejected', 'cancelled', '', 999, 'COD', '2026-04-24 09:29:40', '2026-04-24 21:16:50', NULL, NULL, '2026-04-24 21:16:50'),
(13, 2, 'LUXE044795636', 29, 37, 8, 29, 'Women Regular Fit Striped Mandarin Collar Casual Shirt', 'Wrong item delivered', '', 'rejected', 'cancelled', '', 286, 'Card', '2026-04-24 15:35:17', '2026-04-24 21:07:18', NULL, NULL, '2026-04-24 21:07:18'),
(14, 2, 'LUXE044795636', 29, 37, 8, 29, 'Women Regular Fit Striped Mandarin Collar Casual Shirt', 'Wrong item delivered', '', 'rejected', 'cancelled', '', 286, 'Card', '2026-04-24 15:37:44', '2026-04-24 21:24:40', NULL, NULL, '2026-04-24 21:24:40');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `cms_faqs`
--
ALTER TABLE `cms_faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cms_faqs_active_order` (`is_active`,`sort_order`,`id`);

--
-- Indexes for table `cms_pages`
--
ALTER TABLE `cms_pages`
  ADD PRIMARY KEY (`page_key`);

--
-- Indexes for table `live_chat_conversations`
--
ALTER TABLE `live_chat_conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_live_chat_pair` (`user_id`,`seller_id`),
  ADD KEY `idx_live_chat_seller` (`seller_id`,`last_message_at`),
  ADD KEY `idx_live_chat_user` (`user_id`,`last_message_at`);

--
-- Indexes for table `live_chat_messages`
--
ALTER TABLE `live_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_live_chat_messages_conversation` (`conversation_id`,`id`),
  ADD KEY `idx_live_chat_messages_user` (`user_id`,`created_at`),
  ADD KEY `idx_live_chat_messages_seller` (`seller_id`,`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_ref` (`order_ref`),
  ADD KEY `fk_orders_user` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_oi_order` (`order_id`),
  ADD KEY `fk_oi_product` (`product_id`);

--
-- Indexes for table `platform_payment_gateway_config`
--
ALTER TABLE `platform_payment_gateway_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `uq_products_sku` (`sku`),
  ADD KEY `idx_products_seller_id` (`seller_id`),
  ADD KEY `idx_products_approval_status` (`approval_status`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_images_product` (`product_id`,`sort_order`,`id`);

--
-- Indexes for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_reviews_product` (`product_id`,`created_at`),
  ADD KEY `idx_product_reviews_user` (`user_id`);

--
-- Indexes for table `product_variant_inventory`
--
ALTER TABLE `product_variant_inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_product_variant_inventory` (`product_id`,`size_label`,`color_label`),
  ADD KEY `idx_product_variant_inventory_product` (`product_id`,`active`);

--
-- Indexes for table `seller_account_deletion_requests`
--
ALTER TABLE `seller_account_deletion_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_seller_del_status` (`status`,`requested_at`),
  ADD KEY `idx_seller_del_seller` (`seller_id`,`status`),
  ADD KEY `fk_seller_del_admin` (`reviewed_by`);

--
-- Indexes for table `seller_bank_accounts`
--
ALTER TABLE `seller_bank_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seller_bank_account` (`seller_id`,`account_number`),
  ADD KEY `idx_seller_bank_accounts_seller` (`seller_id`,`created_at`);

--
-- Indexes for table `seller_coupons`
--
ALTER TABLE `seller_coupons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seller_coupons_code` (`code`),
  ADD KEY `idx_seller_coupons_seller` (`seller_id`,`is_active`);

--
-- Indexes for table `seller_create_requests`
--
ALTER TABLE `seller_create_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_seller_req_status` (`status`,`created_at`),
  ADD KEY `idx_seller_req_email` (`email`),
  ADD KEY `fk_seller_req_admin` (`reviewed_by`),
  ADD KEY `fk_seller_req_seller` (`seller_id`);

--
-- Indexes for table `seller_delivery_options`
--
ALTER TABLE `seller_delivery_options`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seller_delivery_option` (`seller_id`,`option_code`),
  ADD KEY `idx_seller_delivery_options_seller` (`seller_id`,`is_active`,`sort_order`);

--
-- Indexes for table `seller_payment_gateway_configs`
--
ALTER TABLE `seller_payment_gateway_configs`
  ADD PRIMARY KEY (`seller_id`);

--
-- Indexes for table `seller_return_settings`
--
ALTER TABLE `seller_return_settings`
  ADD PRIMARY KEY (`seller_id`);

--
-- Indexes for table `seller_shipping_settings`
--
ALTER TABLE `seller_shipping_settings`
  ADD PRIMARY KEY (`seller_id`);

--
-- Indexes for table `seller_users`
--
ALTER TABLE `seller_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `seller_withdraw_requests`
--
ALTER TABLE `seller_withdraw_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_seller_withdraw_seller` (`seller_id`,`status`,`requested_at`),
  ADD KEY `idx_seller_withdraw_status` (`status`,`requested_at`),
  ADD KEY `fk_seller_withdraw_admin` (`reviewed_by`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_account_deletion_requests`
--
ALTER TABLE `user_account_deletion_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_status` (`user_id`,`status`),
  ADD KEY `idx_status_process` (`status`,`process_after`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_addr_user` (`user_id`);

--
-- Indexes for table `user_order_cancel_requests`
--
ALTER TABLE `user_order_cancel_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_cancel_user` (`user_id`,`requested_at`),
  ADD KEY `idx_user_cancel_order` (`order_id`,`seller_id`,`status`),
  ADD KEY `idx_user_cancel_seller` (`seller_id`,`status`,`requested_at`);

--
-- Indexes for table `user_order_enquiries`
--
ALTER TABLE `user_order_enquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_order_enquiry_user` (`user_id`,`created_at`),
  ADD KEY `idx_user_order_enquiry_seller` (`seller_id`,`created_at`),
  ADD KEY `idx_user_order_enquiry_order` (`order_id`,`order_item_id`,`seller_id`),
  ADD KEY `fk_user_order_enquiry_order_item` (`order_item_id`);

--
-- Indexes for table `user_return_requests`
--
ALTER TABLE `user_return_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_return_user` (`user_id`,`requested_at`),
  ADD KEY `idx_user_return_status` (`status`,`requested_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cms_faqs`
--
ALTER TABLE `cms_faqs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `live_chat_conversations`
--
ALTER TABLE `live_chat_conversations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `live_chat_messages`
--
ALTER TABLE `live_chat_messages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `product_reviews`
--
ALTER TABLE `product_reviews`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `product_variant_inventory`
--
ALTER TABLE `product_variant_inventory`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=329;

--
-- AUTO_INCREMENT for table `seller_account_deletion_requests`
--
ALTER TABLE `seller_account_deletion_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `seller_bank_accounts`
--
ALTER TABLE `seller_bank_accounts`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `seller_coupons`
--
ALTER TABLE `seller_coupons`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `seller_create_requests`
--
ALTER TABLE `seller_create_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `seller_delivery_options`
--
ALTER TABLE `seller_delivery_options`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `seller_users`
--
ALTER TABLE `seller_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `seller_withdraw_requests`
--
ALTER TABLE `seller_withdraw_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_account_deletion_requests`
--
ALTER TABLE `user_account_deletion_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_order_cancel_requests`
--
ALTER TABLE `user_order_cancel_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_order_enquiries`
--
ALTER TABLE `user_order_enquiries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `user_return_requests`
--
ALTER TABLE `user_return_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `live_chat_conversations`
--
ALTER TABLE `live_chat_conversations`
  ADD CONSTRAINT `fk_live_chat_conversation_seller` FOREIGN KEY (`seller_id`) REFERENCES `seller_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_live_chat_conversation_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `live_chat_messages`
--
ALTER TABLE `live_chat_messages`
  ADD CONSTRAINT `fk_live_chat_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `live_chat_conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_live_chat_messages_seller` FOREIGN KEY (`seller_id`) REFERENCES `seller_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_live_chat_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `fk_oi_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_oi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_seller` FOREIGN KEY (`seller_id`) REFERENCES `seller_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `fk_product_images_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_reviews`
--
ALTER TABLE `product_reviews`
  ADD CONSTRAINT `fk_product_reviews_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_product_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_variant_inventory`
--
ALTER TABLE `product_variant_inventory`
  ADD CONSTRAINT `fk_product_variant_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seller_account_deletion_requests`
--
ALTER TABLE `seller_account_deletion_requests`
  ADD CONSTRAINT `fk_seller_del_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `seller_bank_accounts`
--
ALTER TABLE `seller_bank_accounts`
  ADD CONSTRAINT `fk_seller_bank_accounts_seller` FOREIGN KEY (`seller_id`) REFERENCES `seller_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seller_coupons`
--
ALTER TABLE `seller_coupons`
  ADD CONSTRAINT `fk_seller_coupons_seller` FOREIGN KEY (`seller_id`) REFERENCES `seller_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seller_create_requests`
--
ALTER TABLE `seller_create_requests`
  ADD CONSTRAINT `fk_seller_req_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_seller_req_seller` FOREIGN KEY (`seller_id`) REFERENCES `seller_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `seller_delivery_options`
--
ALTER TABLE `seller_delivery_options`
  ADD CONSTRAINT `fk_seller_delivery_options_seller` FOREIGN KEY (`seller_id`) REFERENCES `seller_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seller_payment_gateway_configs`
--
ALTER TABLE `seller_payment_gateway_configs`
  ADD CONSTRAINT `fk_seller_pgw_seller` FOREIGN KEY (`seller_id`) REFERENCES `seller_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seller_return_settings`
--
ALTER TABLE `seller_return_settings`
  ADD CONSTRAINT `fk_seller_return_settings_seller` FOREIGN KEY (`seller_id`) REFERENCES `seller_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seller_shipping_settings`
--
ALTER TABLE `seller_shipping_settings`
  ADD CONSTRAINT `fk_seller_shipping_settings_seller` FOREIGN KEY (`seller_id`) REFERENCES `seller_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `seller_withdraw_requests`
--
ALTER TABLE `seller_withdraw_requests`
  ADD CONSTRAINT `fk_seller_withdraw_admin` FOREIGN KEY (`reviewed_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_seller_withdraw_seller` FOREIGN KEY (`seller_id`) REFERENCES `seller_users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `fk_addr_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_order_cancel_requests`
--
ALTER TABLE `user_order_cancel_requests`
  ADD CONSTRAINT `fk_user_cancel_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_cancel_seller` FOREIGN KEY (`seller_id`) REFERENCES `seller_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_cancel_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_order_enquiries`
--
ALTER TABLE `user_order_enquiries`
  ADD CONSTRAINT `fk_user_order_enquiry_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_order_enquiry_order_item` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_order_enquiry_seller` FOREIGN KEY (`seller_id`) REFERENCES `seller_users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_order_enquiry_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_return_requests`
--
ALTER TABLE `user_return_requests`
  ADD CONSTRAINT `fk_user_return_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
