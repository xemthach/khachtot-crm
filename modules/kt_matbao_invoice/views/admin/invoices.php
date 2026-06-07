<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body"><h4><?php echo html_escape($title); ?></h4>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>Tenant</th><th>Source</th><th>MaSoHDon</th><th>MaTraCuu</th><th>Status</th><th>Amount</th><th>Updated</th></tr></thead><tbody>
<?php foreach (($records ?? []) as $row) { ?><tr><td><?php echo (int)$row['id']; ?></td><td><?php echo html_escape($row['tenant_id']); ?></td><td><?php echo html_escape($row['source_type']); ?></td><td><?php echo html_escape($row['ma_so_hdon']); ?></td><td><?php echo html_escape($row['ma_tra_cuu']); ?></td><td><?php echo html_escape($row['local_status']); ?></td><td><?php echo html_escape($row['total_amount']); ?></td><td><?php echo html_escape($row['updated_at']); ?></td></tr><?php } ?>
</tbody></table></div></div></div></div></div>
<?php init_tail(); ?>
