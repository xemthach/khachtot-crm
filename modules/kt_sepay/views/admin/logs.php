<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo html_escape($title ?? 'Nhật ký'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <?php if (!empty($logs)) { ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Trạng thái</th>
                                        <th>Thời gian tạo</th>
                                        <th>Chi tiết</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $row) { ?>
                                    <tr>
                                        <td><?php echo (int) $row['id']; ?></td>
                                        <td><span class="label label-<?php echo kt_sepay_status_badge_class($row['status'] ?? 'default'); ?>"><?php echo html_escape(kt_sepay_status_label($row['status'] ?? '')); ?></span></td>
                                        <td><?php echo html_escape($row['created_at'] ?? ''); ?></td>
                                        <td><pre class="kt-sepay-pre"><?php echo html_escape($row['error_message'] ?? ($row['metadata_json'] ?? $row['raw_body'] ?? '')); ?></pre></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
