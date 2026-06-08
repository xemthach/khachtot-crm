<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
    <div class="tw-flex tw-justify-between tw-items-center">
        <h4 class="tw-mt-0"><?php echo html_escape($title); ?></h4>
        <a href="<?php echo admin_url('kt_integration_hub/cron_process_jobs'); ?>" class="btn btn-default"><?php echo _l('kt_integration_hub_run_cron_now'); ?></a>
    </div>
    <?php $this->load->view('kt_integration_hub/tenant/partials_jobs_table', ['jobs' => $jobs]); ?>
</div></div></div></div>
<?php init_tail(); ?>
