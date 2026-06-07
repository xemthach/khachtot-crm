<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4><?php echo html_escape($title); ?></h4>
                <p>Doanh nghiệp: <?php echo html_escape($tenant['company_name'] ?? ''); ?></p>
                <p>Hóa đơn đã ghi nhận: <?php echo count($records ?? []); ?> | Nhật ký xử lý: <?php echo count($logs ?? []); ?></p>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
