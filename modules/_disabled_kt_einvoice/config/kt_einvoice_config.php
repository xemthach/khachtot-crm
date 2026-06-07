<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
 * KT eInvoice Module Configuration
 * Tích hợp SePay eInvoice API v1
 */

// ── Version ──────────────────────────────────────────────────────────────────
define('KT_EINVOICE_VERSION', '1.0.0');
define('KT_EINVOICE_MIN_PERFEX_VERSION', '3.0.0');

// ── SePay API Base URLs ───────────────────────────────────────────────────────
define('KT_EINVOICE_API_PRODUCTION', 'https://my.sepay.vn/userapi');
define('KT_EINVOICE_API_SANDBOX',    'https://sandbox.sepay.vn/userapi');

// ── API Endpoints (relative) ──────────────────────────────────────────────────
define('KT_EINVOICE_ENDPOINT_TOKEN',            '/einvoice/v1/token');
define('KT_EINVOICE_ENDPOINT_PROVIDERS',        '/einvoice/v1/provider-accounts');
define('KT_EINVOICE_ENDPOINT_PROVIDER_DETAIL',  '/einvoice/v1/provider-accounts/{id}');
define('KT_EINVOICE_ENDPOINT_CREATE',           '/einvoice/v1/invoices/create');
define('KT_EINVOICE_ENDPOINT_CHECK',            '/einvoice/v1/invoices/check/{tracking_code}');
define('KT_EINVOICE_ENDPOINT_ISSUE',            '/einvoice/v1/invoices/issue');
define('KT_EINVOICE_ENDPOINT_LIST',             '/einvoice/v1/invoices');
define('KT_EINVOICE_ENDPOINT_DETAIL',           '/einvoice/v1/invoices/{id}');
define('KT_EINVOICE_ENDPOINT_DOWNLOAD',         '/einvoice/v1/invoices/{id}/download');
define('KT_EINVOICE_ENDPOINT_DELETE',           '/einvoice/v1/invoices/{id}');
define('KT_EINVOICE_ENDPOINT_USAGE',            '/einvoice/v1/usage');
define('KT_EINVOICE_ENDPOINT_CANCEL',           '/einvoice/v1/invoices/{id}/cancel');
define('KT_EINVOICE_ENDPOINT_ADJUST',           '/einvoice/v1/invoices/{id}/adjust');

// ── HTTP Settings ─────────────────────────────────────────────────────────────
define('KT_EINVOICE_HTTP_TIMEOUT',        30);    // seconds
define('KT_EINVOICE_HTTP_CONNECT_TIMEOUT', 10);   // seconds

// ── Token Cache ───────────────────────────────────────────────────────────────
define('KT_EINVOICE_TOKEN_EXPIRY_BUFFER', 300);   // Làm mới token trước 5 phút khi hết hạn

// ── Retry Policy ──────────────────────────────────────────────────────────────
define('KT_EINVOICE_MAX_CREATE_ATTEMPTS', 3);
define('KT_EINVOICE_MAX_ISSUE_ATTEMPTS',  3);
define('KT_EINVOICE_RETRY_BASE_DELAY',    300);   // seconds (5 phút) — exponential backoff
define('KT_EINVOICE_POLL_TIMEOUT',        600);   // seconds — hủy polling sau 10 phút

// ── Cron Settings ─────────────────────────────────────────────────────────────
define('KT_EINVOICE_CRON_STATUS_INTERVAL',  120);  // seconds — chạy mỗi 2 phút
define('KT_EINVOICE_CRON_BATCH_INTERVAL',   300);  // seconds — chạy mỗi 5 phút
define('KT_EINVOICE_CRON_QUOTA_SYNC_INTERVAL', 3600); // seconds — sync quota mỗi 1 giờ

// ── Batch Settings ────────────────────────────────────────────────────────────
define('KT_EINVOICE_BATCH_ITEM_DELAY_MS', 500);   // ms delay giữa các item trong batch
define('KT_EINVOICE_BATCH_DEFAULT_MAX',   50);     // Mặc định nếu plan chưa set

// ── Statuses ──────────────────────────────────────────────────────────────────
define('KT_EINVOICE_STATUS_PENDING_CREATE', 'pending_create');
define('KT_EINVOICE_STATUS_DRAFT',          'draft');
define('KT_EINVOICE_STATUS_PENDING_ISSUE',  'pending_issue');
define('KT_EINVOICE_STATUS_ISSUED',         'issued');
define('KT_EINVOICE_STATUS_FAILED_CREATE',  'failed_create');
define('KT_EINVOICE_STATUS_FAILED_ISSUE',   'failed_issue');
define('KT_EINVOICE_STATUS_DELETED',        'deleted');
define('KT_EINVOICE_STATUS_PENDING_CANCEL', 'pending_cancel');
define('KT_EINVOICE_STATUS_CANCELLED',      'cancelled');
define('KT_EINVOICE_STATUS_ADJUSTING',      'adjusting');
define('KT_EINVOICE_STATUS_ADJUSTED',       'adjusted');

// ── Feature Keys (dùng với TenantEntitlementService) ─────────────────────────
define('KT_EINVOICE_FEATURE_ENABLED',        'einvoice.enabled');
define('KT_EINVOICE_FEATURE_MONTHLY_QUOTA',  'einvoice.monthly_quota');
define('KT_EINVOICE_FEATURE_BATCH_ISSUE',    'einvoice.batch_issue');
define('KT_EINVOICE_FEATURE_AUTO_ISSUE',     'einvoice.auto_issue');
define('KT_EINVOICE_FEATURE_DOWNLOAD_XML',   'einvoice.download_xml');
define('KT_EINVOICE_FEATURE_CANCEL',         'einvoice.cancel_invoice');
define('KT_EINVOICE_FEATURE_MAX_BATCH_SIZE', 'einvoice.max_batch_size');

// ── Log Retention ─────────────────────────────────────────────────────────────
define('KT_EINVOICE_LOG_RETENTION_DAYS',   90);
define('KT_EINVOICE_CRON_LOG_RETENTION_DAYS', 30);

// ── Encryption ────────────────────────────────────────────────────────────────
define('KT_EINVOICE_CIPHER', 'AES-256-CBC');

// ── Download file types ───────────────────────────────────────────────────────
define('KT_EINVOICE_DOWNLOAD_PDF', 'pdf');
define('KT_EINVOICE_DOWNLOAD_XML', 'xml');
