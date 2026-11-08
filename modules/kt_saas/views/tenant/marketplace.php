<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<?php
$CI = &get_instance();
$app = is_array($salesflow_app ?? null) ? $salesflow_app : [];
$state = is_array($app['status'] ?? null) ? $app['status'] : [];
$actions = is_array($marketplace_actions ?? null) ? $marketplace_actions : [];
$primary = is_array($actions['primary'] ?? null) ? $actions['primary'] : [];
$secondary = is_array($actions['secondary'] ?? null) ? $actions['secondary'] : [];
$health = is_array($app['health'] ?? null) ? $app['health'] : [];
$stateDisplay = is_array($app['status_display'] ?? null) ? $app['status_display'] : [];
$catalog = is_array($marketplace_catalog ?? null) ? $marketplace_catalog : [];
$groups = is_array($marketplace_groups ?? null) ? $marketplace_groups : [];
$installableItems = is_array($groups['installable'] ?? null) ? $groups['installable'] : [];
$requestableItems = is_array($groups['requestable'] ?? null) ? $groups['requestable'] : [];
$comingSoonItems = is_array($groups['coming_soon'] ?? null) ? $groups['coming_soon'] : [];
$statusClasses = [
    'DISCOVERED' => 'info',
    'SELECTED' => 'default',
    'PROVISIONING' => 'warning',
    'SETUP_REQUIRED' => 'warning',
    'ACTIVE' => 'success',
    'SUSPENDED' => 'danger',
];
$statusCode = strtoupper((string) ($state['code'] ?? 'DISCOVERED'));
$statusClass = $statusClasses[$statusCode] ?? 'default';
?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-8">
                <h4 class="tw-mb-1">Marketplace</h4>
                <p class="text-muted">Browse installable apps, paid add-on modules, and requestable services. Khach Tot DMS now follows a paid add-on lifecycle instead of a free CRM-plan install toggle.</p>
            </div>
        </div>

        <div class="panel_s">
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-8">
                        <div class="media">
                            <div class="media-left">
                                <div style="width:64px;height:64px;border-radius:8px;background:#eef4ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:28px;">
                                    <i class="<?php echo html_escape($app['icon'] ?? 'fa fa-cube'); ?>"></i>
                                </div>
                            </div>
                            <div class="media-body">
                                <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                    <h4 class="media-heading tw-mb-0"><?php echo html_escape($app['name'] ?? 'Khach Tot DMS'); ?></h4>
                                    <span class="label label-<?php echo html_escape($statusClass); ?>"><?php echo html_escape($stateDisplay['label'] ?? 'Not installed'); ?></span>
                                </div>
                                <p class="text-muted tw-mt-2 tw-mb-1"><?php echo html_escape($app['tagline'] ?? ''); ?></p>
                                <p class="text-muted tw-mb-0"><?php echo html_escape($app['short_description'] ?? ''); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-right">
                        <?php if (!empty($primary['url']) && (($primary['method'] ?? 'get') === 'post')) { ?>
                            <form action="<?php echo html_escape($primary['url']); ?>" method="post" class="tw-inline-block">
                                <input type="hidden" name="<?php echo html_escape($CI->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($CI->security->get_csrf_hash()); ?>">
                                <button type="submit" class="btn btn-primary"><?php echo html_escape($primary['label'] ?? 'Install'); ?></button>
                            </form>
                        <?php } elseif (!empty($primary['url'])) { ?>
                            <a href="<?php echo html_escape($primary['url']); ?>" class="btn btn-primary"><?php echo html_escape($primary['label'] ?? 'Open'); ?></a>
                        <?php } ?>
                        <a href="<?php echo admin_url('kt_saas/tenant_marketplace_app/' . ($app['slug'] ?? 'salesflow-dms')); ?>" class="btn btn-default">View Product Page</a>
                    </div>
                </div>

                <div class="row tw-mt-4">
                    <div class="col-md-4">
                        <div class="panel panel-default tw-mb-0">
                            <div class="panel-body">
                                <div class="text-uppercase text-muted small">Marketplace Installation</div>
                                <div class="tw-text-2xl tw-font-semibold tw-mt-2"><?php echo html_escape($stateDisplay['label'] ?? 'Unknown'); ?></div>
                                <div class="text-muted small tw-mt-1"><?php echo html_escape($stateDisplay['summary'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="panel panel-default tw-mb-0">
                            <div class="panel-body">
                                <div class="text-uppercase text-muted small">Commercial Access</div>
                                <div class="tw-text-lg tw-font-semibold tw-mt-2"><?php echo html_escape($app['packages_note'] ?? 'Paid DMS add-on'); ?></div>
                                <div class="text-muted small tw-mt-1"><?php echo html_escape($app['package_summary'] ?? ''); ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="panel panel-default tw-mb-0">
                            <div class="panel-body">
                                <div class="text-uppercase text-muted small">Operational Readiness</div>
                                <div class="tw-text-lg tw-font-semibold tw-mt-2"><?php echo html_escape($health['connection_status'] ?? 'Unknown'); ?></div>
                                <div class="text-muted small tw-mt-1">Provisioning: <?php echo html_escape($health['provisioning_status'] ?? 'not_started'); ?> | Last Sync: <?php echo html_escape(!empty($health['last_sync']) ? $health['last_sync'] : 'Not available'); ?></div>
                                <div class="text-muted small tw-mt-1">Readiness: <?php echo html_escape($health['readiness_state'] ?? 'DISCOVERED'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tw-mt-4">
                    <div class="alert alert-info tw-mb-0">
                        <strong>How Marketplace state works</strong>
                        <div class="tw-mt-1">CRM subscription keeps tenant access to the platform. DMS commercial rights come from the paid DMS add-on. Marketplace tracks package selection and install lifecycle. Setup Center confirms operational readiness. Runtime licensing gates actual DMS access.</div>
                    </div>
                </div>

                <div class="tw-mt-4">
                    <h5 class="tw-mb-3">Installable Apps</h5>
                    <div class="row">
                        <?php foreach ($installableItems as $item) { ?>
                            <div class="col-md-6">
                                <div class="panel panel-default">
                                    <div class="panel-body">
                                        <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                            <div style="width:48px;height:48px;border-radius:8px;background:#eef4ff;color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:20px;">
                                                <i class="<?php echo html_escape($item['icon'] ?? 'fa fa-cube'); ?>"></i>
                                            </div>
                                            <div class="tw-flex-1">
                                                <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                                    <strong><?php echo html_escape($item['name'] ?? 'App'); ?></strong>
                                                    <span class="label label-primary"><?php echo html_escape($item['type_label'] ?? 'Installable'); ?></span>
                                                    <span class="label label-<?php echo html_escape($statusClass); ?>"><?php echo html_escape($item['status_label'] ?? ''); ?></span>
                                                </div>
                                                <div class="text-muted small tw-mt-1"><?php echo html_escape($item['tagline'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                        <p class="text-muted tw-mt-3 tw-mb-3"><?php echo html_escape($item['short_description'] ?? ''); ?></p>
                                        <div class="alert alert-info tw-mb-3">
                                            <strong>What this app does</strong>
                                            <div class="tw-mt-1"><?php echo html_escape($item['tenant_note'] ?? ''); ?></div>
                                        </div>
                                        <?php $ownerRows = is_array($item['state_owner_rows'] ?? null) ? $item['state_owner_rows'] : []; ?>
                                        <?php if (!empty($ownerRows)) { ?>
                                            <div class="table-responsive tw-mb-3">
                                                <table class="table table-striped tw-mb-0">
                                                    <tbody>
                                                        <?php foreach ($ownerRows as $row) { ?>
                                                            <tr>
                                                                <th class="small text-muted text-uppercase"><?php echo html_escape($row['label'] ?? 'Status'); ?></th>
                                                                <td><?php echo html_escape($row['status'] ?? '-'); ?></td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php } ?>
                                        <div class="tw-flex tw-flex-wrap tw-gap-2">
                                            <?php $itemPrimary = is_array($item['primary_action'] ?? null) ? $item['primary_action'] : []; ?>
                                            <?php if (!empty($itemPrimary['url']) && (($itemPrimary['method'] ?? 'get') === 'post')) { ?>
                                                <form action="<?php echo html_escape($itemPrimary['url']); ?>" method="post" class="tw-inline-block">
                                                    <input type="hidden" name="<?php echo html_escape($CI->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($CI->security->get_csrf_hash()); ?>">
                                                    <button type="submit" class="btn btn-primary"><?php echo html_escape($itemPrimary['label'] ?? 'Install'); ?></button>
                                                </form>
                                            <?php } elseif (!empty($itemPrimary['url'])) { ?>
                                                <a href="<?php echo html_escape($itemPrimary['url']); ?>" class="btn btn-primary"><?php echo html_escape($itemPrimary['label'] ?? 'Open'); ?></a>
                                            <?php } ?>
                                            <?php if (!empty($item['detail_url'])) { ?>
                                                <a href="<?php echo html_escape($item['detail_url']); ?>" class="btn btn-default">View Product Page</a>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="tw-mt-4">
                    <h5 class="tw-mb-3">Requestable Services</h5>
                    <div class="row">
                        <?php foreach ($requestableItems as $item) { ?>
                            <div class="col-md-6">
                                <div class="panel panel-default">
                                    <div class="panel-body">
                                        <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                            <div style="width:48px;height:48px;border-radius:8px;background:#f8fafc;color:#475569;display:flex;align-items:center;justify-content:center;font-size:20px;">
                                                <i class="<?php echo html_escape($item['icon'] ?? 'fa fa-cube'); ?>"></i>
                                            </div>
                                            <div class="tw-flex-1">
                                                <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                                    <strong><?php echo html_escape($item['name'] ?? 'Service'); ?></strong>
                                                    <span class="label label-default"><?php echo html_escape($item['type_label'] ?? 'Service'); ?></span>
                                                </div>
                                                <div class="text-muted small tw-mt-1"><?php echo html_escape($item['status_label'] ?? ''); ?></div>
                                            </div>
                                        </div>
                                        <p class="text-muted tw-mt-3 tw-mb-1"><?php echo html_escape($item['short_description'] ?? ''); ?></p>
                                        <div class="text-muted small tw-mb-3"><?php echo html_escape($item['tenant_note'] ?? ''); ?></div>
                                        <?php $ownerRows = is_array($item['state_owner_rows'] ?? null) ? $item['state_owner_rows'] : []; ?>
                                        <?php if (!empty($ownerRows)) { ?>
                                            <div class="table-responsive tw-mb-3">
                                                <table class="table table-striped tw-mb-0">
                                                    <tbody>
                                                        <?php foreach ($ownerRows as $row) { ?>
                                                            <tr>
                                                                <th class="small text-muted text-uppercase"><?php echo html_escape($row['label'] ?? 'Status'); ?></th>
                                                                <td><?php echo html_escape($row['status'] ?? '-'); ?></td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php } ?>
                                        <?php $itemPrimary = is_array($item['primary_action'] ?? null) ? $item['primary_action'] : []; ?>
                                        <?php if (!empty($itemPrimary['url']) && (($itemPrimary['method'] ?? 'get') === 'post')) { ?>
                                            <form action="<?php echo html_escape($itemPrimary['url']); ?>" method="post" class="tw-inline-block">
                                                <input type="hidden" name="<?php echo html_escape($CI->security->get_csrf_token_name()); ?>" value="<?php echo html_escape($CI->security->get_csrf_hash()); ?>">
                                                <?php foreach ((array) ($itemPrimary['fields'] ?? []) as $fieldName => $fieldValue) { ?>
                                                    <input type="hidden" name="<?php echo html_escape((string) $fieldName); ?>" value="<?php echo html_escape((string) $fieldValue); ?>">
                                                <?php } ?>
                                                <button type="submit" class="btn btn-default"><?php echo html_escape($itemPrimary['label'] ?? 'Open'); ?></button>
                                            </form>
                                        <?php } elseif (!empty($itemPrimary['url'])) { ?>
                                            <a href="<?php echo html_escape($itemPrimary['url']); ?>" class="btn btn-default"><?php echo html_escape($itemPrimary['label'] ?? 'Open'); ?></a>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="tw-mt-4">
                    <h5 class="tw-mb-3">Coming Soon / Manual Services</h5>
                    <div class="row">
                        <?php foreach ($comingSoonItems as $item) { ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="panel panel-default">
                                    <div class="panel-body">
                                        <div class="tw-flex tw-flex-wrap tw-items-center tw-gap-2">
                                            <strong><?php echo html_escape($item['name'] ?? 'Item'); ?></strong>
                                            <span class="label label-warning"><?php echo html_escape($item['type_label'] ?? 'Coming Soon'); ?></span>
                                        </div>
                                        <div class="text-muted small tw-mt-2"><?php echo html_escape($item['status_summary'] ?? ''); ?></div>
                                        <?php $ownerRows = is_array($item['state_owner_rows'] ?? null) ? $item['state_owner_rows'] : []; ?>
                                        <?php if (!empty($ownerRows)) { ?>
                                            <div class="table-responsive tw-mt-3">
                                                <table class="table table-striped tw-mb-0">
                                                    <tbody>
                                                        <?php foreach ($ownerRows as $row) { ?>
                                                            <tr>
                                                                <th class="small text-muted text-uppercase"><?php echo html_escape($row['label'] ?? 'Status'); ?></th>
                                                                <td><?php echo html_escape($row['status'] ?? '-'); ?></td>
                                                            </tr>
                                                        <?php } ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="tw-mt-4">
                    <h5 class="tw-mb-3">What this app does</h5>
                    <div class="row">
                        <?php foreach ((array) ($app['value_proposition'] ?? []) as $item) { ?>
                            <div class="col-md-4 col-sm-6">
                                <div class="panel panel-default">
                                    <div class="panel-body">
                                        <div class="tw-font-semibold"><?php echo html_escape($item['title'] ?? 'Value'); ?></div>
                                        <div class="text-muted small tw-mt-1"><?php echo html_escape($item['body'] ?? ''); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="tw-mt-4">
                    <h5 class="tw-mb-3">Next Action</h5>
                    <?php if ($statusCode === 'DISCOVERED') { ?>
                        <div class="alert alert-info tw-mb-0">
                            <strong>Choose a DMS package</strong>
                            <div class="tw-mt-1">Khach Tot DMS is available in Marketplace, but no active paid DMS add-on exists yet for this tenant.</div>
                        </div>
                    <?php } elseif (in_array($statusCode, ['SELECTED', 'PROVISIONING', 'SETUP_REQUIRED'], true)) { ?>
                        <div class="alert alert-warning tw-mb-0">
                            <strong>Complete activation and operational setup</strong>
                            <div class="tw-mt-1">Marketplace tracks package activation and installation. Setup Center separately confirms staff access and first field activity.</div>
                        </div>
                    <?php } elseif ($statusCode === 'ACTIVE') { ?>
                        <div class="alert alert-success tw-mb-0">
                            <strong>Open DMS</strong>
                            <div class="tw-mt-1">The app is installed, operational readiness is complete, and runtime licensing permits access.</div>
                        </div>
                    <?php } else { ?>
                        <div class="alert alert-danger tw-mb-0">
                            <strong>Review Subscription</strong>
                            <div class="tw-mt-1">The tenant is suspended, so Khach Tot DMS cannot be opened until CRM subscription status is healthy again.</div>
                        </div>
                    <?php } ?>
                </div>

                <div class="tw-mt-4">
                    <div class="alert alert-warning tw-mb-0">
                        <strong>Catalog note</strong>
                        <div class="tw-mt-1">Only installable apps expose app install CTAs. Service add-ons and separate tenant workspaces are shown with their real action mode instead of a fake one-click install.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
