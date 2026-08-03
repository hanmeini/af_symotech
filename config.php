<?php
// Set variabel lingkungan menggunakan putenv()
putenv("APP_ENV=development");
putenv("DB_HOST=127.0.0.1");
putenv("DB_USER=root");
putenv("DB_PASS=");
putenv("DB_NAME=symotech_af");

// ============================================================
// DEPLOY (untuk fitur gitpull.php)
// ============================================================
putenv("CPANEL_DEPLOY_URL=https://af.symotech.id/gitpull.php");
putenv("CPANEL_DEPLOY_SECRET=AF_SECRET_DEPLOY_2026");
