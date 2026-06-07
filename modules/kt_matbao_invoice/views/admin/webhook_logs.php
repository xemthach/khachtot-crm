<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body"><h4><?php echo html_escape($title); ?></h4>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>Đã xử lý</th><th>Mã hóa đơn</th><th>Mẫu số</th><th>Trạng thái</th><th>Thời gian nhận</th></tr></thead><tbody>
<?php foreach (($logs ?? []) as $row) { ?><tr><td><?php echo (int)$row['id']; ?></td><td><?php echo !empty($row['processed']) ? 'Có' : 'Không'; ?></td><td><?php echo html_escape($row['inv_id']); ?></td><td><?php echo html_escape($row['ma_so_hdon']); ?></td><td><?php echo html_escape($row['status_name']); ?></td><td><?php echo html_escape($row['received_at']); ?></td></tr><?php } ?>
</tbody></table></div></div></div></div></div>
<?php init_tail(); ?>
