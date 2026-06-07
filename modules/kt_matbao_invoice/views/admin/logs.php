<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body"><h4><?php echo html_escape($title); ?></h4>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>ID</th><th>Action</th><th>HTTP</th><th>Success</th><th>Latency</th><th>Created</th></tr></thead><tbody>
<?php foreach (($logs ?? []) as $row) { ?><tr><td><?php echo (int)$row['id']; ?></td><td><?php echo html_escape($row['action']); ?></td><td><?php echo html_escape($row['http_code']); ?></td><td><?php echo !empty($row['success']) ? 'Yes' : 'No'; ?></td><td><?php echo html_escape($row['latency_ms']); ?> ms</td><td><?php echo html_escape($row['created_at']); ?></td></tr><?php } ?>
</tbody></table></div></div></div></div></div>
<?php init_tail(); ?>
