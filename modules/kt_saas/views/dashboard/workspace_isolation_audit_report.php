<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$result = is_array($result ?? null) ? $result : [];
$checks = is_array($result['checks'] ?? null) ? $result['checks'] : [];
$ok = !empty($result['success']);
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo html_escape($title ?? 'Workspace Isolation Audit'); ?></h4>
                        <hr class="hr-panel-heading" />
                        <?php if (!$ok) { ?>
                            <div class="alert alert-danger"><?php echo html_escape((string) ($result['message'] ?? 'Audit failed.')); ?></div>
                        <?php } else { ?>
                            <div class="alert <?php echo !empty($result['all_pass']) ? 'alert-success' : 'alert-warning'; ?>">
                                Tenant #<?php echo (int) ($result['tenant_id'] ?? 0); ?> (<?php echo html_escape((string) ($result['tenant_code'] ?? '')); ?>):
                                <?php echo !empty($result['all_pass']) ? 'PASS' : 'FAIL'; ?>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Group</th>
                                            <th>Status</th>
                                            <th>Feature</th>
                                            <th>Missing options</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($checks as $group => $check) { ?>
                                            <tr>
                                                <td><strong><?php echo html_escape((string) $group); ?></strong></td>
                                                <td>
                                                    <span class="label label-<?php echo !empty($check['pass']) ? 'success' : 'danger'; ?>">
                                                        <?php echo !empty($check['pass']) ? 'PASS' : 'FAIL'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo html_escape((string) ($check['feature_key'] ?? '')); ?> (enabled: <?php echo !empty($check['feature_enabled']) ? '1' : '0'; ?>)</td>
                                                <td><?php echo html_escape(implode(', ', (array) ($check['missing_options'] ?? []))); ?></td>
                                                <td>
                                                    <?php if ($group === 'branding') { ?>
                                                        <div><strong>Tenant files:</strong> <?php echo html_escape(json_encode((array) ($check['files'] ?? []), JSON_UNESCAPED_UNICODE)); ?></div>
                                                        <div><strong>Landlord files:</strong> <?php echo html_escape(json_encode((array) ($check['landlord_files'] ?? []), JSON_UNESCAPED_UNICODE)); ?></div>
                                                        <div><strong>Issues:</strong> <?php echo html_escape(json_encode((array) ($check['file_issues'] ?? []), JSON_UNESCAPED_UNICODE)); ?></div>
                                                    <?php } ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
