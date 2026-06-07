<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4><?php echo html_escape($title); ?></h4>
                        <p class="text-muted">Tài liệu kiến trúc chi tiết nằm tại:</p>
                        <p><code><?php echo html_escape($doc_path); ?></code></p>
                        <p>Đọc file markdown trong repo để review đầy đủ chiến lược landlord, tenant, billing, provisioning, scale và migration roadmap.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
