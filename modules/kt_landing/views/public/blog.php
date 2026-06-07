<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo html_escape($title ?? 'Blog'); ?></title>
    <link rel="stylesheet" href="<?php echo base_url('assets/plugins/bootstrap/css/bootstrap.min.css'); ?>">
</head>
<body>
<div class="container">
    <h2>Blog</h2>
    <?php foreach (($posts ?? []) as $post) { ?>
        <article class="panel panel-default">
            <div class="panel-body">
                <h4><?php echo html_escape($post['title'] ?? ''); ?></h4>
                <p><?php echo html_escape($post['excerpt'] ?? ''); ?></p>
            </div>
        </article>
    <?php } ?>
    <?php if (empty($posts)) { ?><p>Chưa có bài viết.</p><?php } ?>
</div>
</body>
</html>
