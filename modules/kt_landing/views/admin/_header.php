<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_ux'); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-mb-4"><?php echo html_escape($title ?? 'KT Landing'); ?></h4>
