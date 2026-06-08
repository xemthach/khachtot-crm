<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
    <h4 class="tw-mt-0"><?php echo html_escape($title); ?></h4>
    <?php $this->load->view('kt_integration_hub/tenant/partials_jobs_table', ['jobs' => $jobs]); ?>
</div></div></div></div>
<?php init_tail(); ?>
