<?php
/**
 * Environment-based config — do not put secrets in this file.
 *
 * The app loads includes/config.php, which auto-picks:
 *   localhost  → config.local.php
 *   live site  → config.production.php
 *
 * First-time setup:
 *   cp includes/config.local.example.php includes/config.local.php
 *   cp includes/config.production.example.php includes/config.production.php
 *
 * Edit each file with the matching DB / SMTP / Razorpay credentials.
 * See config.local.example.php and config.production.example.php for templates.
 */
