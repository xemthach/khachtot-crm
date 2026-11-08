<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title><?php echo html_escape((string) ($title ?? 'Chưa thể mở Khách Tốt DMS')); ?></title>
  <style>
    body{margin:0;background:#f4f7fb;color:#172b4d;font-family:Arial,sans-serif}.shell{max-width:680px;margin:10vh auto;padding:24px}.panel{padding:32px;border:1px solid #dbe4ef;border-radius:8px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.08);text-align:center}.mark{display:inline-grid;width:56px;height:56px;border-radius:8px;background:#dc2626;color:#fff;place-items:center;font-size:26px;font-weight:700}.actions{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-top:24px}.btn{display:inline-block;padding:10px 16px;border:1px solid #cbd5e1;border-radius:6px;color:#172b4d;text-decoration:none}.primary{border-color:#2563eb;background:#2563eb;color:#fff}.muted{color:#64748b}.code{margin-top:24px;font-size:12px}
  </style>
</head>
<body><main class="shell"><section class="panel" data-r29-sso-error data-r25-sso-error>
  <div class="mark">!</div>
  <h1>Chưa thể mở Khách Tốt DMS</h1>
  <p class="muted"><?php echo html_escape((string) ($message ?? 'DMS đang tạm thời chưa phản hồi. Vui lòng thử lại.')); ?></p>
  <div class="actions">
    <a class="btn primary" href="<?php echo html_escape((string) ($retry_url ?? '#')); ?>">Thử lại</a>
    <a class="btn" href="<?php echo html_escape((string) ($status_url ?? $fallback_url ?? '#')); ?>">Xem trạng thái DMS</a>
    <a class="btn" href="<?php echo html_escape((string) ($fallback_url ?? '#')); ?>">Kiểm tra thiết lập DMS</a>
    <a class="btn" href="<?php echo html_escape((string) ($support_url ?? $fallback_url ?? '#')); ?>">Liên hệ hỗ trợ</a>
  </div>
  <div class="muted code">Mã hỗ trợ: <?php echo html_escape((string) ($support_code ?? 'SSO')); ?></div>
</section></main></body></html>
