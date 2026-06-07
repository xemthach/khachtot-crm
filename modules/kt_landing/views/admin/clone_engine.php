<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<?php
$csrfTokenName = $this->security->get_csrf_token_name();
$csrfTokenHash = $this->security->get_csrf_hash();
$sourceTemplates = $source_templates ?? [];
$brandPresets = $brand_presets ?? [];
$industryPresets = $industry_presets ?? [];
$preview = $preview ?? null;
$result = $result ?? null;
$cloneRows = $clone_rows ?? [];
$selectedTemplate = (string) ($selected_template ?? '');
$publicPreviewUrl = (string) ($public_preview_url ?? '');
?>

<div class="panel_s">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-7">
                <form method="post" class="form-horizontal">
                    <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Source Template</label>
                            <select class="form-control" name="source_template_code" required>
                                <option value="">Select source template</option>
                                <?php foreach ($sourceTemplates as $templateCode) { ?>
                                    <option value="<?php echo html_escape($templateCode); ?>" <?php echo (string) ($preview['input']['source_template_code'] ?? $selectedTemplate) === (string) $templateCode ? 'selected' : ''; ?>>
                                        <?php echo html_escape($templateCode); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Target Name</label>
                            <input class="form-control" name="target_name" value="<?php echo html_escape($preview['input']['target_name'] ?? ''); ?>" placeholder="CRM HVAC" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Target Slug</label>
                            <input class="form-control" name="target_slug" value="<?php echo html_escape($preview['input']['target_slug'] ?? ''); ?>" placeholder="crm-hvac" required>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Brand Preset</label>
                            <select class="form-control" name="brand_preset">
                                <?php foreach ($brandPresets as $key => $preset) { ?>
                                    <option value="<?php echo html_escape($key); ?>" <?php echo (string) ($preview['input']['brand_preset'] ?? 'neutral') === (string) $key ? 'selected' : ''; ?>>
                                        <?php echo html_escape($preset['label'] ?? $key); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>Industry Preset</label>
                            <select class="form-control" name="industry_preset">
                                <?php foreach ($industryPresets as $key => $preset) { ?>
                                    <option value="<?php echo html_escape($key); ?>" <?php echo (string) ($preview['input']['industry_preset'] ?? 'crm_hvac') === (string) $key ? 'selected' : ''; ?>>
                                        <?php echo html_escape($preset['label'] ?? $key); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Preview URL</label>
                            <div class="form-control" style="height:auto; min-height:34px;">
                                <?php if ($publicPreviewUrl !== '') { ?>
                                    <a href="<?php echo html_escape($publicPreviewUrl); ?>" target="_blank" rel="noopener">Open public preview</a>
                                <?php } else { ?>
                                    Select a template and clone first
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <div class="btn-group">
                        <button class="btn btn-default" type="submit" name="action" value="preview">Preview</button>
                        <button class="btn btn-primary" type="submit" name="action" value="clone" onclick="return confirm('Create landing clone draft now?');">Clone Draft</button>
                    </div>
                </form>

                <hr>

                <?php if (!empty($cloneRows)) { ?>
                    <table class="table table-bordered table-hover">
                        <thead>
                        <tr>
                            <th>Template</th>
                            <th>Source</th>
                            <th>Target</th>
                            <th>Industry</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th>Preview</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cloneRows as $row) { ?>
                            <tr>
                                <td><code><?php echo html_escape($row['template_code']); ?></code></td>
                                <td><?php echo html_escape($row['source_template_code']); ?></td>
                                <td><?php echo html_escape($row['target_name']); ?> <small class="text-muted">(<?php echo html_escape($row['target_slug']); ?>)</small></td>
                                <td><?php echo html_escape($row['industry_preset']); ?></td>
                                <td><?php echo html_escape($row['status']); ?></td>
                                <td><?php echo html_escape($row['updated_at']); ?></td>
                                <td><a class="btn btn-default btn-xs" href="<?php echo html_escape(site_url('?tpl=' . rawurlencode((string) $row['template_code']))); ?>" target="_blank">Open</a></td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                <?php } ?>
            </div>

            <div class="col-md-5">
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Validation</strong></div>
                    <div class="panel-body">
                        <?php if (!empty($preview)) { ?>
                            <p><strong>Result:</strong> <?php echo !empty($preview['success']) ? 'Pass' : 'Blocked'; ?></p>
                            <p><strong>Blockers:</strong></p>
                            <?php if (!empty($preview['validation']['blockers'])) { ?>
                                <ul>
                                    <?php foreach ($preview['validation']['blockers'] as $item) { ?>
                                        <li><?php echo html_escape($item); ?></li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <p class="text-success">None</p>
                            <?php } ?>
                            <p><strong>Warnings:</strong></p>
                            <?php if (!empty($preview['validation']['warnings'])) { ?>
                                <ul>
                                    <?php foreach ($preview['validation']['warnings'] as $item) { ?>
                                        <li><?php echo html_escape($item); ?></li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <p class="text-success">None</p>
                            <?php } ?>
                            <hr>
                            <p><strong>Source pages:</strong> <?php echo (int) ($preview['plan']['source_pages'] ?? 0); ?></p>
                            <p><strong>Source sections:</strong> <?php echo (int) ($preview['plan']['source_sections'] ?? 0); ?></p>
                            <p><strong>Source menus:</strong> <?php echo (int) ($preview['plan']['source_menus'] ?? 0); ?></p>
                            <p><strong>Global blocks referenced:</strong></p>
                            <?php if (!empty($preview['plan']['global_blocks'])) { ?>
                                <ul>
                                    <?php foreach ($preview['plan']['global_blocks'] as $token) { ?>
                                        <li><code><?php echo html_escape($token); ?></code></li>
                                    <?php } ?>
                                </ul>
                            <?php } else { ?>
                                <p class="text-muted">None</p>
                            <?php } ?>
                            <hr>
                            <p><strong>Preview settings:</strong></p>
                            <pre style="white-space:pre-wrap; word-break:break-word; max-height:340px; overflow:auto;"><?php echo html_escape(json_encode($preview['plan']['settings'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
                        <?php } elseif (!empty($result)) { ?>
                            <p><strong>Clone result:</strong> <?php echo !empty($result['success']) ? 'Success' : 'Failed'; ?></p>
                            <p><?php echo html_escape($result['message'] ?? ''); ?></p>
                            <p><strong>Pages cloned:</strong> <?php echo (int) ($result['pages_cloned'] ?? 0); ?></p>
                            <p><strong>Sections cloned:</strong> <?php echo (int) ($result['sections_cloned'] ?? 0); ?></p>
                            <p><strong>Menus cloned:</strong> <?php echo (int) ($result['menus_cloned'] ?? 0); ?></p>
                        <?php } else { ?>
                            <p class="text-muted">Preview validation will appear here before clone.</p>
                        <?php } ?>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Governance</strong></div>
                    <div class="panel-body">
                        <ul class="list-unstyled">
                            <li><strong>Clone:</strong> pages, sections, menus, SEO metadata</li>
                            <li><strong>Reference:</strong> global blocks, pricing source, marketplace registry</li>
                            <li><strong>Skip:</strong> analytics, leads, publish history, activity logs</li>
                            <li><strong>Publish:</strong> draft only</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
