<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$report = $media_report ?? ['summary' => ['total' => 0, 'used' => 0, 'unused' => 0, 'large_files' => 0, 'missing_alt' => 0], 'media' => []];
$summary = $report['summary'] ?? [];
$mediaRows = $report['media'] ?? [];
$csrfTokenName = $this->security->get_csrf_token_name();
$csrfTokenHash = $this->security->get_csrf_hash();
?>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<div class="kt-cms-shell">
    <div class="kt-cms-hero">
        <div class="row">
            <div class="col-md-8">
                <h3>Media</h3>
                <p class="kt-cms-subtitle">Asset Library with usage awareness, metadata, and safe replace workflows.</p>
            </div>
        </div>
    </div>

    <div class="kt-cms-card-grid">
        <div class="kt-cms-stat-card"><span class="label label-default">Total Media</span><strong><?php echo (int) ($summary['total'] ?? 0); ?></strong><div class="kt-cms-muted">All assets in the library.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-success">Used</span><strong><?php echo (int) ($summary['used'] ?? 0); ?></strong><div class="kt-cms-muted">Referenced by pages, blocks, or content.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-warning">Unused</span><strong><?php echo (int) ($summary['unused'] ?? 0); ?></strong><div class="kt-cms-muted">Ready for reuse or cleanup.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-danger">Missing Alt</span><strong><?php echo (int) ($summary['missing_alt'] ?? 0); ?></strong><div class="kt-cms-muted">Accessibility gaps to fix.</div></div>
    </div>

    <div class="kt-cms-card">
        <h5>Upload Asset</h5>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
            <input type="hidden" name="action" value="save">
            <div class="row">
                <div class="col-md-3 form-group"><label>Folder</label><input class="form-control" name="folder" value="landing" placeholder="landing"></div>
                <div class="col-md-3 form-group"><label>Title</label><input class="form-control" name="title" placeholder="Asset title"></div>
                <div class="col-md-3 form-group"><label>Alt Text</label><input class="form-control" name="alt_text" placeholder="Alternative text"></div>
                <div class="col-md-3 form-group"><label>Category</label><input class="form-control" name="category" placeholder="Category"></div>
                <div class="col-md-3 form-group"><label>Tags</label><input class="form-control" name="tags" placeholder="Tags"></div>
                <div class="col-md-3 form-group"><label>Caption</label><input class="form-control" name="caption" placeholder="Caption"></div>
                <div class="col-md-3 form-group"><label>Existing Path</label><input class="form-control" name="file_path" placeholder="Existing path or leave blank"></div>
                <div class="col-md-3 form-group"><label>File</label><input type="file" class="form-control" name="media_file" accept=".png,.jpg,.jpeg,.gif,.webp,.avif,.svg,.pdf,.mp4,.mov,.webm"></div>
            </div>
            <button class="btn btn-primary">Upload Asset</button>
        </form>
    </div>

    <div class="kt-cms-card">
        <div class="kt-cms-tabs">
            <span class="kt-cms-pill">Grid View</span>
            <span class="kt-cms-pill">List View</span>
            <span class="kt-cms-pill">Folders</span>
            <span class="kt-cms-pill">Usage</span>
            <span class="kt-cms-pill">Metadata</span>
            <span class="kt-cms-pill">Replace</span>
        </div>
        <div class="text-right tw-mb-3">
            <form method="post" style="display:inline">
                <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                <input type="hidden" name="action" value="refresh_usage">
                <button class="btn btn-default">Refresh usage index</button>
            </form>
        </div>

        <?php if (!empty($mediaRows)) { ?>
            <div class="kt-cms-media-grid">
                <?php foreach ($mediaRows as $m) {
                    $filePath = (string) ($m['file_path'] ?? '');
                    $fileUrl = preg_match('#^https?://#i', $filePath) ? $filePath : base_url(ltrim($filePath, '/'));
                    $isImage = strpos((string) ($m['mime_type'] ?? ''), 'image/') === 0 || preg_match('/\.(png|jpe?g|gif|webp|avif|svg)$/i', $filePath);
                    $canDelete = !empty($m['usage_count']) ? false : true;
                    $usageGraph = $m['usage_graph'] ?? ['references' => []];
                    ?>
                    <div class="kt-cms-media-card">
                        <div class="kt-cms-media-thumb">
                            <?php if ($isImage) { ?>
                                <a href="<?php echo html_escape($fileUrl); ?>" target="_blank" style="display:block;width:100%;height:100%;">
                                    <img src="<?php echo html_escape($fileUrl); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                                </a>
                            <?php } else { ?>
                                <div class="text-muted" style="padding:32px 12px;text-align:center;width:100%;">Asset</div>
                            <?php } ?>
                        </div>

                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                            <div style="min-width:0;">
                                <div class="kt-cms-page-card__title"><?php echo html_escape($m['title'] ?: $m['file_name']); ?></div>
                                <div class="kt-cms-muted"><?php echo html_escape($m['folder']); ?></div>
                            </div>
                            <span class="kt-cms-pill"><?php echo (int) ($m['usage_count'] ?? 0); ?> used</span>
                        </div>

                        <div class="kt-cms-media-card__meta">
                            <?php if (!empty($m['category'])) { ?><span class="kt-cms-pill"><?php echo html_escape($m['category']); ?></span><?php } ?>
                            <?php if (!empty($m['mime_type'])) { ?><span class="kt-cms-pill"><?php echo html_escape($m['mime_type']); ?></span><?php } ?>
                            <?php if (!empty($m['alt_text'])) { ?><span class="kt-cms-pill">Alt set</span><?php } else { ?><span class="kt-cms-pill">Alt missing</span><?php } ?>
                        </div>

                        <div class="kt-cms-divider"></div>
                        <div class="kt-cms-muted"><strong>Alt:</strong> <?php echo html_escape($m['alt_text'] ?: '—'); ?></div>
                        <div class="kt-cms-muted"><strong>Size:</strong> <?php echo number_format(((int) ($m['file_size'] ?? 0)) / 1024, 1); ?> KB</div>
                        <div class="kt-cms-muted"><strong>Dims:</strong> <?php echo !empty($m['width']) ? (int) $m['width'] . ' x ' . (int) $m['height'] : '—'; ?></div>
                        <div class="kt-cms-muted"><strong>Last used:</strong> <?php echo html_escape($m['last_used_at'] ?: '—'); ?></div>

                        <div class="kt-cms-divider"></div>
                        <div class="kt-cms-muted"><strong><?php echo (int) ($usageGraph['total'] ?? 0); ?></strong> references</div>
                        <details class="tw-mt-2">
                            <summary>Usage graph</summary>
                            <ul class="small tw-mt-2">
                                <?php foreach (($usageGraph['references'] ?? []) as $ref) { ?>
                                    <li>
                                        <?php echo html_escape(ucfirst((string) ($ref['usage_type'] ?? 'page'))); ?>:
                                        <?php echo html_escape((string) ($ref['usage_label'] ?? ($ref['usage_ref_key'] ?? ''))); ?>
                                        <span class="text-muted">[<?php echo html_escape((string) ($ref['source_field'] ?? '')); ?>]</span>
                                    </li>
                                <?php } ?>
                            </ul>
                        </details>

                        <div class="kt-cms-divider"></div>
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                            <div class="form-group">
                                <label>Title</label>
                                <input class="form-control input-sm" name="title" value="<?php echo html_escape($m['title']); ?>" placeholder="Title">
                            </div>
                            <div class="form-group">
                                <label>Alt Text</label>
                                <input class="form-control input-sm" name="alt_text" value="<?php echo html_escape($m['alt_text']); ?>" placeholder="Alt text">
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <input class="form-control input-sm" name="category" value="<?php echo html_escape($m['category']); ?>" placeholder="Category">
                            </div>
                            <div class="form-group">
                                <label>Tags</label>
                                <input class="form-control input-sm" name="tags" value="<?php echo html_escape($m['tags']); ?>" placeholder="Tags">
                            </div>
                            <div class="form-group">
                                <label>Caption</label>
                                <input class="form-control input-sm" name="caption" value="<?php echo html_escape($m['caption']); ?>" placeholder="Caption">
                            </div>
                            <div class="form-group">
                                <label>Replace asset</label>
                                <input class="form-control input-sm" name="media_file" type="file" accept=".png,.jpg,.jpeg,.gif,.webp,.avif,.svg,.pdf,.mp4,.mov,.webm">
                            </div>
                            <div class="kt-cms-tabs">
                                <button class="btn btn-primary btn-sm">Save</button>
                            </div>
                        </form>
                        <form method="post" class="tw-mt-2">
                            <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                            <input type="hidden" name="action" value="refresh_usage">
                            <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                            <button class="btn btn-default btn-sm">Refresh usage</button>
                        </form>

                        <div class="kt-cms-divider"></div>
                        <form method="post">
                            <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
                            <button class="btn btn-danger btn-sm" <?php echo $canDelete ? '' : 'disabled'; ?>>Delete</button>
                            <?php if (!$canDelete) { ?><div class="small text-danger tw-mt-2">In use</div><?php } ?>
                        </form>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="alert alert-info">No media items yet.</div>
        <?php } ?>
    </div>
</div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
