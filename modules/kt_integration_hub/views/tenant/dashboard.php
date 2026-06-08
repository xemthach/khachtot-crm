<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4 class="tw-mt-0"><?php echo html_escape($title); ?></h4>
                <div class="row">
                    <?php foreach ($summary as $label => $value) { ?>
                        <div class="col-md-2 col-sm-4">
                            <div class="well text-center">
                                <h3 class="tw-mt-0"><?php echo (int) $value; ?></h3>
                                <span><?php echo html_escape(ucwords(str_replace('_', ' ', $label))); ?></span>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
        <div class="panel_s">
            <div class="panel-body">
                <div class="tw-flex tw-justify-between tw-items-center">
                    <h4 class="tw-mt-0"><?php echo _l('kt_integration_hub_connections'); ?></h4>
                    <a href="<?php echo admin_url('kt_integration_hub/connections'); ?>" class="btn btn-primary"><?php echo _l('kt_integration_hub_manage_connections'); ?></a>
                </div>
                <?php $this->load->view('kt_integration_hub/tenant/partials_connections_table', ['connections' => $connections]); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
