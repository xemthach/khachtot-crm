<?php defined('BASEPATH') or exit('No direct script access allowed'); init_head(); ?>
<div id="wrapper"><div class="content kt-misa-admin"><div class="panel_s"><div class="panel-body">
<h4><?php echo html_escape($title); ?></h4>
<p>ID <?php echo (int) $request['id']; ?> — <?php echo html_escape($request['status']); ?></p>
<p>Correlation: <?php echo html_escape($request['correlation_id']); ?></p>
<div class="alert alert-info">Payload and sensitive metadata are not rendered by the default detail view.</div>
</div></div></div></div><?php init_tail(); ?>
