<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
<h4><?php echo html_escape($title); ?></h4>
<?php echo form_open(admin_url('kt_matbao_invoice/sync_templates')); ?>
<?php echo render_input('year', 'Year', date('Y'), 'number'); ?>
<button type="submit" class="btn btn-primary">Sync templates</button>
<?php echo form_close(); ?>
<hr>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>Year</th><th>KHMSHDon</th><th>KHHDon</th><th>thDon</th><th>sLuong</th><th>cLai</th><th>Synced</th></tr></thead><tbody>
<?php foreach (($templates ?? []) as $row) { ?><tr><td><?php echo (int)$row['id']; ?></td><td><?php echo (int)$row['year']; ?></td><td><?php echo html_escape($row['khmshdon']); ?></td><td><?php echo html_escape($row['khhdon']); ?></td><td><?php echo html_escape($row['thdon']); ?></td><td><?php echo html_escape($row['sluong']); ?></td><td><?php echo html_escape($row['clai']); ?></td><td><?php echo html_escape($row['synced_at']); ?></td></tr><?php } ?>
</tbody></table></div>
</div></div></div></div>
<?php init_tail(); ?>
