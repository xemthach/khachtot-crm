<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4><?php echo html_escape($title); ?></h4>
                <?php echo form_open(admin_url('kt_matbao_invoice/plan_entitlements')); ?>
                <div class="table-responsive" style="overflow:auto; max-height:70vh;">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Gói CRM</th>
                                <?php foreach (($features ?? []) as $featureKey => $meta) { ?>
                                    <th><?php echo html_escape($meta['label']); ?><br><small><?php echo html_escape($featureKey); ?></small></th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($plans ?? []) as $plan) {
                                $planId = (int) ($plan['id'] ?? 0);
                                $row = $entitlements[$planId] ?? [];
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo html_escape($plan['plan_name'] ?? ''); ?></strong><br>
                                    <small><?php echo html_escape($plan['plan_code'] ?? ''); ?> (ID <?php echo $planId; ?>)</small>
                                </td>
                                <?php foreach (($features ?? []) as $featureKey => $meta) {
                                    $cell = $row[$featureKey] ?? ['is_enabled' => false, 'feature_value' => ''];
                                    $isEnabled = !empty($cell['is_enabled']);
                                    $featureValue = (string) ($cell['feature_value'] ?? '');
                                    $featureSafeKey = str_replace('.', '__DOT__', $featureKey);
                                ?>
                                <td>
                                    <label class="checkbox-inline" style="margin-bottom:5px;">
                                        <input type="checkbox" name="entitlements[<?php echo $planId; ?>][<?php echo html_escape($featureSafeKey); ?>][enabled]" value="1" <?php echo $isEnabled ? 'checked' : ''; ?>> Bật quyền
                                    </label>
                                    <?php if (($meta['type'] ?? '') === 'limit') { ?>
                                        <input type="number" min="0" class="form-control" name="entitlements[<?php echo $planId; ?>][<?php echo html_escape($featureSafeKey); ?>][value]" value="<?php echo html_escape($featureValue); ?>" placeholder="0 = không giới hạn/tắt">
                                    <?php } ?>
                                </td>
                                <?php } ?>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <button type="submit" class="btn btn-primary" onclick="return confirm('Lưu cấu hình quyền dùng KT Mắt Bão Invoice?');">Lưu cấu hình quyền</button>
                <?php echo form_close(); ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
