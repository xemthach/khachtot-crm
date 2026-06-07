<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
<h4><?php echo html_escape($title); ?></h4>
<p>Records: <strong><?php echo count($records ?? []); ?></strong> | API logs: <strong><?php echo count($logs ?? []); ?></strong> | Webhook logs: <strong><?php echo count($webhook_logs ?? []); ?></strong></p>
<p><a class="btn btn-primary" href="<?php echo admin_url('kt_matbao_invoice/settings'); ?>">Settings</a></p>
</div></div></div></div>
<?php init_tail(); ?>
