<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>

<?php
$editBlock = $edit_block ?? null;
$previewBlock = $preview_block ?? null;
$usageGraph = $usage_graph ?? ['total' => 0, 'by_type' => [], 'references' => []];
$summary = $summary ?? ['total_blocks' => 0, 'active_blocks' => 0, 'disabled_blocks' => 0, 'usage_rows' => 0];
$blockTypes = $block_types ?? ['CTA', 'FAQ', 'Trust Metrics', 'Footer'];
?>

<div class="panel_s">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-3"><div class="alert alert-info"><strong>Total:</strong> <?php echo (int) ($summary['total_blocks'] ?? 0); ?></div></div>
            <div class="col-md-3"><div class="alert alert-success"><strong>Active:</strong> <?php echo (int) ($summary['active_blocks'] ?? 0); ?></div></div>
            <div class="col-md-3"><div class="alert alert-warning"><strong>Disabled:</strong> <?php echo (int) ($summary['disabled_blocks'] ?? 0); ?></div></div>
            <div class="col-md-3"><div class="alert alert-default"><strong>Usage refs:</strong> <?php echo (int) ($summary['usage_rows'] ?? 0); ?></div></div>
        </div>

        <div class="row">
            <div class="col-md-7">
                <form method="post">
                    <input type="hidden" name="block_id" value="<?php echo (int) ($editBlock['id'] ?? 0); ?>">
                    <div class="form-group">
                        <label>Block Key</label>
                        <input class="form-control" name="block_key" value="<?php echo html_escape($editBlock['block_key'] ?? ''); ?>" placeholder="cta-demo" <?php echo !empty($editBlock['id']) ? 'readonly' : ''; ?>>
                    </div>
                    <div class="form-group">
                        <label>Block Name</label>
                        <input class="form-control" name="block_name" value="<?php echo html_escape($editBlock['block_name'] ?? ''); ?>" placeholder="CTA Demo">
                    </div>
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>Block Type</label>
                            <select class="form-control" name="block_type">
                                <?php foreach ($blockTypes as $type) { ?>
                                    <option value="<?php echo html_escape($type); ?>" <?php echo (string) ($editBlock['block_type'] ?? 'CTA') === (string) $type ? 'selected' : ''; ?>><?php echo html_escape($type); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Status</label>
                            <select class="form-control" name="status">
                                <option value="active" <?php echo (string) ($editBlock['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="disabled" <?php echo (string) ($editBlock['status'] ?? '') === 'disabled' ? 'selected' : ''; ?>>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Usage syntax</label>
                            <div class="form-control" style="height:auto; min-height:34px;">&#123;&#123;block:block_key&#125;&#125;</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Content JSON</label>
                        <textarea class="form-control" name="content_json" rows="14" placeholder='{"title":"CTA Demo","cta_text":"Đăng ký ngay"}'><?php echo html_escape($editBlock['content_json'] ?? ''); ?></textarea>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-primary" name="action" value="save" type="submit">Save Block</button>
                        <?php if (!empty($editBlock['id'])) { ?>
                            <button class="btn btn-default" name="action" value="duplicate" type="submit" onclick="return confirm('Duplicate this block?');">Duplicate</button>
                            <?php if ((string) ($editBlock['status'] ?? 'active') === 'active') { ?>
                                <button class="btn btn-warning" name="action" value="disable" type="submit" onclick="return confirm('Disable this block?');">Disable</button>
                            <?php } ?>
                            <?php if (!empty($edit_block_can_delete)) { ?>
                                <button class="btn btn-danger" name="action" value="delete" type="submit" onclick="return confirm('Delete this block?');">Delete</button>
                            <?php } ?>
                        <?php } ?>
                    </div>
                </form>
            </div>

            <div class="col-md-5">
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Usage Graph</strong></div>
                    <div class="panel-body">
                        <?php if (!empty($previewBlock)) { ?>
                            <p><strong><?php echo html_escape($previewBlock['block_name']); ?></strong></p>
                            <p><code><?php echo html_escape($previewBlock['block_key']); ?></code> | <?php echo html_escape($previewBlock['block_type']); ?> | <?php echo html_escape($previewBlock['status']); ?></p>
                        <?php } else { ?>
                            <p>No block selected.</p>
                        <?php } ?>
                        <p><strong>Total references:</strong> <?php echo (int) ($usageGraph['total'] ?? 0); ?></p>
                        <ul class="list-unstyled">
                            <?php foreach (($usageGraph['by_type'] ?? []) as $type => $count) { ?>
                                <li><strong><?php echo html_escape(ucfirst((string) $type)); ?>:</strong> <?php echo (int) $count; ?></li>
                            <?php } ?>
                        </ul>
                        <hr>
                        <?php if (!empty($usageGraph['references'])) { ?>
                            <div style="max-height:320px; overflow:auto;">
                                <?php foreach ($usageGraph['references'] as $ref) { ?>
                                    <div class="alert alert-default" style="margin-bottom:8px;">
                                        <strong><?php echo html_escape(ucfirst((string) ($ref['usage_type'] ?? 'landing'))); ?></strong><br>
                                        <?php echo html_escape((string) ($ref['usage_label'] ?? '')); ?><br>
                                        <small><?php echo html_escape((string) ($ref['usage_ref_type'] ?? '')); ?> / <?php echo html_escape((string) ($ref['usage_ref_key'] ?? '')); ?> / <?php echo html_escape((string) ($ref['source_field'] ?? '')); ?></small>
                                    </div>
                                <?php } ?>
                            </div>
                        <?php } else { ?>
                            <p class="text-muted">No references yet.</p>
                        <?php } ?>
                    </div>
                </div>

                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Preview</strong></div>
                    <div class="panel-body">
                        <?php if (!empty($previewBlock)) { ?>
                            <?php $decodedPreview = json_decode((string) ($previewBlock['content_json'] ?? ''), true); ?>
                            <?php if (is_array($decodedPreview)) { ?>
                                <pre style="white-space:pre-wrap; word-break:break-word;"><?php echo html_escape(json_encode($decodedPreview, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre>
                            <?php } else { ?>
                                <pre style="white-space:pre-wrap; word-break:break-word;"><?php echo html_escape((string) ($previewBlock['content_json'] ?? '')); ?></pre>
                            <?php } ?>
                        <?php } else { ?>
                            <p class="text-muted">Select a block to preview its content JSON.</p>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <hr>

        <table class="table table-bordered table-hover">
            <thead>
            <tr>
                <th>ID</th>
                <th>Key</th>
                <th>Name</th>
                <th>Type</th>
                <th>Status</th>
                <th>Usage</th>
                <th>Updated</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!empty($blocks)) { ?>
                <?php foreach ($blocks as $block) { ?>
                    <?php $usageCount = (int) ($block_usage_counts[(int) $block['id']] ?? 0); ?>
                    <tr>
                        <td><?php echo (int) $block['id']; ?></td>
                        <td><code><?php echo html_escape($block['block_key']); ?></code></td>
                        <td><?php echo html_escape($block['block_name']); ?></td>
                        <td><?php echo html_escape($block['block_type']); ?></td>
                        <td><?php echo html_escape($block['status']); ?></td>
                        <td><?php echo (int) $usageCount; ?></td>
                        <td><?php echo html_escape($block['updated_at'] ?? $block['created_at'] ?? ''); ?></td>
                        <td>
                            <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_landing/global_blocks?edit_id=' . (int) $block['id']); ?>">Edit</a>
                            <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_landing/global_blocks?preview_id=' . (int) $block['id']); ?>">Preview</a>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="action" value="duplicate">
                                <input type="hidden" name="block_id" value="<?php echo (int) $block['id']; ?>">
                                <button type="submit" class="btn btn-default btn-sm">Duplicate</button>
                            </form>
                            <?php if ((string) ($block['status'] ?? 'active') === 'active') { ?>
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="action" value="disable">
                                    <input type="hidden" name="block_id" value="<?php echo (int) $block['id']; ?>">
                                    <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Disable this block?');">Disable</button>
                                </form>
                            <?php } ?>
                            <form method="post" style="display:inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="block_id" value="<?php echo (int) $block['id']; ?>">
                                <button type="submit" class="btn btn-danger btn-sm" <?php echo $usageCount > 0 ? 'disabled title="Block is in use"' : 'onclick="return confirm(\'Delete this block?\');"'; ?>>Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            <?php } else { ?>
                <tr><td colspan="8" class="text-center text-muted">No global blocks yet.</td></tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
