-- LUXE CMS / CRM admin module
-- Adds editable storefront pages, FAQ items and global brand/contact settings.
-- Safe to run repeatedly.

USE luxe_shop;

-- Long content for each storefront page (about, contact, faq, terms, privacy, return_policy).
CREATE TABLE IF NOT EXISTS cms_pages (
  page_key      VARCHAR(64)  NOT NULL PRIMARY KEY,
  hero_kicker   VARCHAR(120) NOT NULL DEFAULT '',
  hero_title    VARCHAR(255) NOT NULL DEFAULT '',
  hero_lead     VARCHAR(1000) NOT NULL DEFAULT '',
  body_html     MEDIUMTEXT   NULL,
  meta_description VARCHAR(500) NOT NULL DEFAULT '',
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cms_pages (page_key, hero_kicker, hero_title, hero_lead, meta_description) VALUES
  ('contact',       'We are here to help', 'Contact Us', 'Share your question about orders, payments, returns, or account settings. Our support team reviews every message and responds as quickly as possible during working hours.', 'Get in touch with LUXE support for order help, account issues, and general questions.'),
  ('about',         'Our story',           'About Us',  'At LUXE, we combine product curation, engineering, and design thinking to build a premium shopping experience.', 'Learn more about LUXE, our mission, and why shoppers trust our platform.'),
  ('faq',           'Help centre',         'Frequently Asked Questions', 'Quick answers about orders, payments and account basics. Still stuck? Contact our team.', 'Frequently asked questions about orders, shipping, returns, and account support on LUXE.'),
  ('terms',         'Legal',               'Terms & Conditions', 'Please read these terms carefully. They govern your access to LUXE and your relationship with us and with independent sellers on the platform.', 'Terms and conditions for using the LUXE marketplace and services.'),
  ('privacy',       'Legal',               'Privacy Policy', 'We respect your privacy. This policy explains what we collect, why we collect it, and the choices you have when you shop or use LUXE.', 'How LUXE collects, uses and protects your personal information.'),
  ('return_policy', 'Customer care',       'Return Policy', 'We want you to shop with confidence. Here is how returns, exchanges and refunds typically work across sellers on LUXE.', 'How returns, exchanges and refunds work on LUXE marketplace orders.')
ON DUPLICATE KEY UPDATE page_key = VALUES(page_key);

-- Repeating Q/A items rendered on the public FAQ page.
CREATE TABLE IF NOT EXISTS cms_faqs (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question    VARCHAR(500) NOT NULL,
  answer      TEXT         NOT NULL,
  sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cms_faqs_active_order (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO cms_faqs (id, question, answer, sort_order, is_active) VALUES
  (1, 'How do I place an order on LUXE?',           'Browse products, add items to your cart, and proceed to checkout. You can create an account or continue as a guest where supported. Review your address and payment details before confirming.', 10, 1),
  (2, 'What payment methods are accepted?',         'We support major cards, UPI, and net banking where enabled by our payment partner. Available options are shown at checkout before you pay.', 20, 1),
  (3, 'How long does delivery take?',               'Delivery times depend on the seller, your location, and the shipping option you choose. Estimated timelines appear on the product or checkout page when applicable.', 30, 1),
  (4, 'Can I return or exchange an item?',          'Return and exchange policies may vary by seller and product category. Check the product page and your order details for eligibility. Contact support if you need help with a specific order.', 40, 1),
  (5, 'How do I track my order?',                   'Sign in and open the Orders section from your profile. You will see status updates and tracking information when the seller or carrier provides them.', 50, 1),
  (6, 'How do I become a seller on LUXE?',          'Vendors can apply through our seller onboarding flow. If you are interested, use the "Become A Vendor" link in the footer or contact us for partnership details.', 60, 1)
ON DUPLICATE KEY UPDATE id = VALUES(id);

-- The site_settings table already has setting_value VARCHAR(255), enough for short brand/contact strings.
-- The address can be longer, so widen to TEXT to be safe.
ALTER TABLE site_settings MODIFY setting_value TEXT NOT NULL;

INSERT INTO site_settings (setting_key, setting_value) VALUES
  ('site_brand_name',     'LUXE'),
  ('site_logo_path',      ''),
  ('site_contact_email',  'info@luxe.com'),
  ('site_contact_phone',  '+123 324 5879 39'),
  ('site_contact_address','37 W 24th St, New York, NY'),
  ('site_contact_hours',  'Mon–Sat, 9:00–18:00 IST'),
  ('storefront_theme',    'default')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
