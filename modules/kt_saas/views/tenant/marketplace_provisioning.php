<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$app = is_array($salesflow_app ?? null) ? $salesflow_app : [];
$state = is_array($marketplace_state ?? null) ? $marketplace_state : [];
$actions = is_array($marketplace_actions ?? null) ? $marketplace_actions : [];
$requirements = is_array($app['requirements'] ?? null) ? $app['requirements'] : [];
$health = is_array($app['health'] ?? null) ? $app['health'] : [];
$installHistory = is_array($app['install_history'] ?? null) ? $app['install_history'] : [];
$gettingStarted = is_array($app['getting_started'] ?? null) ? $app['getting_started'] : [];
$stateOwnership = is_array($app['state_ownership'] ?? null) ? $app['state_ownership'] : [];
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8">
                <p class="text-muted tw-mb-1">Marketplace &rarr; Khach Tot DMS &rarr; Installation</p>
                <h4 class="tw-mb-1">Khach Tot DMS Installation Progress</h4>
                <p class="text-muted">Marketplace owns package selection and installation lifecycle. After the DMS add-on is active, use Setup Center to complete staff access and operational readiness.</p>
            </div>
            <div class="col-md-4 text-right">
                <?php if (!empty($actions['setup_wizard_url'])) { ?>
                    <a href="<?php echo html_escape($actions['setup_wizard_url']); ?>" class="btn btn-primary">Open Setup Wizard</a>
                <?php } ?>
                <?php if (!empty($actions['secondary']['url'])) { ?>
                    <a href="<?php echo html_escape($actions['secondary']['url']); ?>" class="btn btn-default"><?php echo html_escape($actions['secondary']['label'] ?? 'Next'); ?></a>
                <?php } ?>
            </div>
        </div>

        <div class="alert alert-info">
            <strong>State alignment</strong>
            <div class="tw-mt-1">This page tracks DMS add-on activation, installation, and readiness only. CRM subscription keeps platform access, while runtime licensing decides whether DMS features can actually open after setup.</div>
        </div>

        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <div class="text-uppercase text-muted small">Bridge Health</div>
                        <div class="tw-text-xl tw-font-semibold tw-mt-2"><?php echo html_escape($health['connection_status'] ?? 'Unknown'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <div class="text-uppercase text-muted small">Marketplace Installation</div>
                        <div class="tw-text-xl tw-font-semibold tw-mt-2"><?php echo html_escape($health['marketplace_state'] ?? 'Unknown'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <div class="text-uppercase text-muted small">Staff Provisioning</div>
                        <div class="tw-text-xl tw-font-semibold tw-mt-2"><?php echo html_escape($health['staff_sync_status'] ?? 'Unknown'); ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="panel panel-default">
                    <div class="panel-body">
                        <div class="text-uppercase text-muted small">Setup Center Readiness</div>
                        <div class="tw-text-xl tw-font-semibold tw-mt-2"><?php echo html_escape($health['readiness_state'] ?? 'DISCOVERED'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-7">
                <div class="panel_s">
                    <div class="panel-heading">Installation & Readiness Signals</div>
                    <div class="panel-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Signal</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requirements as $requirement) { ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo html_escape($requirement['label'] ?? 'Requirement'); ?></strong>
                                                <div class="text-muted small"><?php echo html_escape($requirement['detail'] ?? ''); ?></div>
                                            </td>
                                            <td>
                                                <span class="label label-<?php echo !empty($requirement['done']) ? 'success' : 'warning'; ?>">
                                                    <?php echo !empty($requirement['done']) ? 'Ready' : 'Not Ready'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!empty($actions['setup_wizard_url'])) { ?>
                            <a href="<?php echo html_escape($actions['setup_wizard_url']); ?>" class="btn btn-primary">Continue in Setup Wizard</a>
                        <?php } ?>
                        <?php if (!empty($actions['open_dms_url']) && (($actions['open_dms_mode'] ?? 'disabled') !== 'disabled')) { ?>
                            <a href="<?php echo html_escape($actions['open_dms_url']); ?>" class="btn btn-default">Open DMS</a>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="col-md-5">
                <div class="panel_s">
                    <div class="panel-heading">Install History</div>
                    <div class="panel-body">
                        <table class="table table-striped">
                            <tbody>
                                <tr>
                                    <th>Installed At</th>
                                    <td><?php echo html_escape(!empty($installHistory['installed_at']) ? $installHistory['installed_at'] : 'Not installed yet'); ?></td>
                                </tr>
                                <tr>
                                    <th>Last Provisioned</th>
                                    <td><?php echo html_escape(!empty($installHistory['last_provisioned_at']) ? $installHistory['last_provisioned_at'] : 'Not available'); ?></td>
                                </tr>
                                <tr>
                                    <th>Last Sync</th>
                                    <td><?php echo html_escape(!empty($installHistory['last_sync']) ? $installHistory['last_sync'] : 'Not available'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($gettingStarted)) { ?>
                    <div class="panel_s">
                        <div class="panel-heading">Next Steps</div>
                        <div class="panel-body">
                            <ol class="tw-pl-5 tw-mb-0">
                                <?php foreach ($gettingStarted as $step) { ?>
                                    <li class="tw-mb-2">
                                        <strong><?php echo html_escape($step['label'] ?? 'Step'); ?></strong>
                                        <div class="text-muted small"><?php echo html_escape($step['detail'] ?? ''); ?></div>
                                    </li>
                                <?php } ?>
                            </ol>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div class="panel_s">
            <div class="panel-heading">Lifecycle Ownership</div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-striped tw-mb-0">
                        <thead>
                            <tr>
                                <th>Owner</th>
                                <th>Concern</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stateOwnership as $row) { ?>
                                <tr>
                                    <td><strong><?php echo html_escape($row['owner'] ?? '-'); ?></strong></td>
                                    <td><?php echo html_escape($row['label'] ?? '-'); ?></td>
                                    <td><?php echo html_escape($row['status'] ?? '-'); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
