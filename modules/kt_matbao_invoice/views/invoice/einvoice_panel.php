<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (!empty($invoice) && is_object($invoice) && is_admin()) { ?>
<div class="btn-group mleft5">
    <a href="<?php echo admin_url('kt_matbao_invoice/tenant/invoices'); ?>" class="btn btn-default btn-sm">Hóa đơn điện tử</a>
    <?php echo form_open(admin_url('kt_matbao_invoice/tenant/create_draft/' . (int) $invoice->id), ['style' => 'display:inline']); ?>
    <button type="submit" class="btn btn-default btn-sm">Tạo bản nháp</button>
    <?php echo form_close(); ?>
    <?php echo form_open(admin_url('kt_matbao_invoice/tenant/issue/' . (int) $invoice->id), ['style' => 'display:inline', 'onsubmit' => "return confirm('Phát hành hóa đơn điện tử này ngay bây giờ?');"]); ?>
    <button type="submit" class="btn btn-primary btn-sm">Phát hành</button>
    <?php echo form_close(); ?>
</div>
<?php } ?>

