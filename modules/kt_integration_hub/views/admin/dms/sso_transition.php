<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
  <title>Đang mở Khách Tốt DMS</title>
  <style>
    body{margin:0;font-family:Arial,sans-serif;background:#f5f7fb;color:#172033}.wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}.card{width:min(520px,100%);background:#fff;border:1px solid #e1e7ef;border-radius:8px;box-shadow:0 12px 36px rgba(18,38,63,.12);padding:32px;text-align:center}.logo{display:inline-flex;width:56px;height:56px;align-items:center;justify-content:center;border-radius:12px;background:#0f5b9d;color:#fff;font-weight:700;font-size:20px}h1{font-size:22px;line-height:1.3;margin:18px 0 8px}p{font-size:14px;line-height:1.6;color:#5f6b7a;margin:0 0 16px}.bar{height:8px;background:#e8eef7;border-radius:99px;overflow:hidden;margin:18px 0}.bar span{display:block;height:100%;width:100%;background:#0f5b9d;animation:pulse 1s ease-in-out infinite}.actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:18px}.btn{display:inline-flex;min-height:40px;align-items:center;border-radius:6px;padding:0 14px;text-decoration:none;font-weight:600;font-size:14px}.primary{background:#0f5b9d;color:#fff}.secondary{border:1px solid #cdd6e3;color:#172033;background:#fff}.code{margin-top:18px;font-size:12px;color:#7a8797}@keyframes pulse{0%{opacity:.55}50%{opacity:1}100%{opacity:.55}}
  </style>
</head>
<body>
  <main class="wrap" data-r25-sso-transition><section class="card">
    <div class="logo">KT</div><h1>Đang mở Khách Tốt DMS</h1>
    <p>Đang xác thực và chuyển bạn đến workspace <strong><?php echo html_escape($workspace_name ?? 'DMS'); ?></strong>.</p>
    <div class="bar" aria-hidden="true"><span></span></div><p>Nếu quá 3 giây, bạn có thể thử lại hoặc mở trang đăng nhập DMS.</p>
    <div class="actions"><a id="r25-sso-retry" class="btn primary" href="<?php echo html_escape($target_url ?? '#'); ?>">Thử lại</a><?php if (!empty($fallback_url)) { ?><a class="btn secondary" href="<?php echo html_escape($fallback_url); ?>">Đăng nhập DMS</a><?php } ?></div>
    <div class="code">Mã hỗ trợ: <?php echo html_escape($support_code ?? 'SSO'); ?></div>
  </section></main>
  <?php if (!$this->input->get('manual')) { ?><script>window.setTimeout(function(){window.location.assign(<?php echo json_encode((string) ($target_url ?? ''), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>);},0);</script><?php } ?>
</body>
</html>
