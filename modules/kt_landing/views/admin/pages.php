<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<?php
$pages = $pages ?? [];
$sections = $sections ?? [];
$globalBlocks = $global_blocks ?? [];
$menus = $menus ?? [];
$themes = $themes ?? [];
$activePage = $active_page ?? null;
$activePageSections = $active_page_sections ?? [];
$activeSection = $active_section ?? null;
$blockUsageCounts = $global_block_usage_counts ?? [];
$canvasSections = !empty($activePageSections) ? $activePageSections : $sections;
$csrfTokenName = $this->security->get_csrf_token_name();
$csrfTokenHash = $this->security->get_csrf_hash();
?>
<div class="kt-cms-shell">
    <div class="kt-cms-hero">
        <div class="row">
            <div class="col-md-8">
                <h3>Website Builder</h3>
                <p class="kt-cms-subtitle">Marketing can compose pages by section cards and see a live page canvas without database concepts.</p>
            </div>
            <div class="col-md-4 text-right">
                <div class="btn-group">
                    <a class="btn btn-primary" href="#builder-pages">Pages</a>
                    <a class="btn btn-default" href="#builder-canvas">Canvas</a>
                    <a class="btn btn-default" href="#builder-preview">Preview</a>
                </div>
            </div>
        </div>
    </div>

    <div class="kt-cms-kpis">
        <div class="kt-cms-kpi"><span>Pages</span><strong><?php echo (int) count($pages); ?></strong></div>
        <div class="kt-cms-kpi"><span>Sections</span><strong><?php echo (int) count($sections); ?></strong></div>
        <div class="kt-cms-kpi"><span>Shared Blocks</span><strong><?php echo (int) count($globalBlocks); ?></strong></div>
        <div class="kt-cms-kpi"><span>Navigation</span><strong><?php echo (int) count($menus); ?></strong></div>
    </div>

    <div class="kt-cms-grid">
        <div class="kt-cms-card" style="grid-column: span 3;" id="builder-pages">
            <h5>Page Tree</h5>
            <div class="kt-cms-muted">Select a page to edit the live canvas.</div>
            <div class="kt-cms-divider"></div>
            <?php foreach ($pages as $page) {
                $pageId = (int) ($page['id'] ?? 0);
                $selected = $activePage && $pageId === (int) ($activePage['id'] ?? 0);
                ?>
                <div class="kt-cms-page-card" style="<?php echo $selected ? 'border-color:#0f4c81;background:#f7fbff;' : ''; ?>">
                    <div class="kt-cms-page-card__head">
                        <div style="min-width:0;">
                            <div class="kt-cms-page-card__title"><?php echo html_escape((string) ($page['title'] ?? '')); ?></div>
                            <div class="kt-cms-muted"><?php echo html_escape((string) ($page['slug'] ?? '')); ?></div>
                        </div>
                        <span class="kt-cms-pill"><?php echo html_escape((string) ($page['status'] ?? '')); ?></span>
                    </div>
                    <div class="kt-cms-divider" style="margin:12px 0;"></div>
                    <div class="kt-cms-page-card__meta">
                        <span class="kt-cms-pill">Page</span>
                        <span class="kt-cms-pill">Open</span>
                        <span class="kt-cms-pill">Canvas</span>
                    </div>
                    <div class="kt-cms-page-card__actions">
                        <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_landing/pages?page_id=' . $pageId); ?>">Open</a>
                        <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_landing/pages?page_id=' . $pageId . '&section_id=' . (int) ($canvasSections[0]['id'] ?? 0)); ?>">Canvas</a>
                    </div>
                    <form method="post" class="kt-cms-page-card__actions" style="margin-top:10px;">
                        <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                        <input type="hidden" name="entity" value="page">
                        <input type="hidden" name="id" value="<?php echo $pageId; ?>">
                        <button class="btn btn-default btn-xs" name="action" value="move_up">Move Up</button>
                        <button class="btn btn-default btn-xs" name="action" value="move_down">Move Down</button>
                        <button class="btn btn-danger btn-xs" name="delete" value="1">Delete</button>
                    </form>
                </div>
            <?php } ?>

            <div class="kt-cms-divider"></div>
            <h5>Create Page</h5>
            <form method="post">
                <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                <input type="hidden" name="entity" value="page">
                <div class="form-group"><label>Page title</label><input class="form-control" name="title" placeholder="Home"></div>
                <div class="form-group"><label>Page slug</label><input class="form-control" name="slug" placeholder="home"></div>
                <div class="form-group"><label>Template</label>
                    <select class="form-control" name="template_code">
                        <?php foreach ($themes as $theme) { ?>
                            <option value="<?php echo html_escape((string) ($theme['code'] ?? '')); ?>"><?php echo html_escape((string) ($theme['name'] ?? '')); ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="form-group"><label>Visibility</label>
                    <select class="form-control" name="status">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
                <button class="btn btn-primary btn-block">Add page</button>
            </form>
        </div>

        <div class="kt-cms-card" style="grid-column: span 6;" id="builder-canvas">
            <h5>Visual Canvas</h5>
            <div class="kt-cms-muted">Hero, Trust, Features, Pricing, FAQ, and CTA are shown as section cards in page order.</div>
            <div class="kt-cms-divider"></div>
            <?php if (!empty($activePage)) { ?>
                <div class="kt-cms-preview-card" style="margin-bottom:14px;">
                    <div class="kt-cms-page-card__head">
                        <div>
                            <div class="kt-cms-page-card__title" style="font-size:18px;"><?php echo html_escape((string) ($activePage['title'] ?? '')); ?></div>
                            <div class="kt-cms-muted"><?php echo html_escape((string) ($activePage['slug'] ?? '')); ?></div>
                        </div>
                        <span class="kt-cms-pill"><?php echo html_escape((string) ($activePage['status'] ?? '')); ?></span>
                    </div>
                    <div class="kt-cms-divider"></div>
                    <div class="kt-cms-tabs">
                        <?php foreach (['Hero', 'Trust', 'Features', 'Pricing', 'FAQ', 'CTA'] as $chip) { ?>
                            <span class="kt-cms-pill"><?php echo html_escape($chip); ?></span>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <?php if (!empty($canvasSections)) { ?>
                <?php foreach ($canvasSections as $section) {
                    $sectionId = (int) ($section['id'] ?? 0);
                    $sectionTitle = trim((string) ($section['title'] ?? ''));
                    if ($sectionTitle === '') {
                        $sectionTitle = trim((string) ($section['section_key'] ?? 'Section'));
                    }
                    $previewText = trim((string) ($section['subtitle'] ?? ''));
                    if ($previewText === '') {
                        $previewText = trim((string) ($section['content'] ?? ''));
                    }
                    $isEnabled = (int) ($section['is_enabled'] ?? 1) === 1;
                    ?>
                    <div class="kt-cms-section-card" style="margin-bottom:14px;border-left:4px solid <?php echo $isEnabled ? '#24a148' : '#d0d7de'; ?>;">
                        <div class="kt-cms-section-card__head">
                            <div style="display:flex;gap:12px;align-items:flex-start;min-width:0;">
                                <div style="width:42px;height:42px;border-radius:12px;background:#eef5ff;display:flex;align-items:center;justify-content:center;font-weight:700;"><?php echo html_escape(mb_strtoupper(mb_substr($sectionTitle, 0, 1))); ?></div>
                                <div style="min-width:0;">
                                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                        <strong class="kt-cms-section-card__title"><?php echo html_escape($sectionTitle); ?></strong>
                                        <span class="kt-cms-pill"><?php echo $isEnabled ? 'Visible' : 'Hidden'; ?></span>
                                    </div>
                                    <div class="kt-cms-muted"><?php echo html_escape($isEnabled ? 'Visible on page' : 'Hidden section'); ?></div>
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div class="kt-cms-muted">Status</div>
                                <strong><?php echo $isEnabled ? 'Active' : 'Disabled'; ?></strong>
                            </div>
                        </div>

                        <div class="kt-cms-section-card__canvas">
                            <div class="kt-cms-muted">Preview</div>
                            <div style="font-weight:600;line-height:1.6;"><?php echo html_escape(mb_substr($previewText !== '' ? $previewText : 'No content preview available.', 0, 180)); ?></div>
                        </div>

                        <div class="kt-cms-section-card__meta">
                            <span class="kt-cms-pill">Edit</span>
                            <span class="kt-cms-pill">Duplicate</span>
                            <span class="kt-cms-pill">Move</span>
                        </div>

                        <div class="kt-cms-section-card__actions">
                            <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_landing/pages?page_id=' . (int) ($activePage['id'] ?? 0) . '&section_id=' . $sectionId); ?>">Preview</a>
                            <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_landing/section_items?section_id=' . $sectionId); ?>">Edit</a>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                                <input type="hidden" name="entity" value="section">
                                <input type="hidden" name="id" value="<?php echo $sectionId; ?>">
                                <button class="btn btn-default btn-sm" name="action" value="move_up">Move Up</button>
                                <button class="btn btn-default btn-sm" name="action" value="move_down">Move Down</button>
                                <button class="btn btn-default btn-sm" name="action" value="duplicate">Duplicate</button>
                                <button class="btn btn-danger btn-sm" name="delete" value="1">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php } ?>
            <?php } else { ?>
                <div class="kt-cms-preview-card">
                    <strong>No sections available for this page.</strong>
                    <div class="kt-cms-muted">Use the page selector on the left to choose another page.</div>
                </div>
            <?php } ?>
        </div>

        <div class="kt-cms-card" style="grid-column: span 3;" id="builder-preview">
            <h5>Live Preview</h5>
            <div class="kt-cms-muted">Preview sits inside the builder instead of opening another screen.</div>
            <div class="kt-cms-divider"></div>

            <div class="kt-cms-preview-card" style="margin-bottom:12px;background:linear-gradient(180deg,#f7fbff,#ffffff);">
                <div class="kt-cms-muted">Selected Page</div>
                <strong><?php echo html_escape((string) ($activePage['title'] ?? 'Choose a page')); ?></strong>
                <div class="kt-cms-muted"><?php echo html_escape((string) ($activePage['slug'] ?? '')); ?></div>
            </div>

            <div class="kt-cms-preview-card" style="margin-bottom:12px;background:#0f4c81;color:#fff;">
                <div style="opacity:.8;">Hero</div>
                <strong>CRM Khách Tốt</strong>
                <div style="opacity:.9;margin-top:6px;">Landing, pricing, leads, and publishing in one flow.</div>
            </div>

            <div class="kt-cms-preview-card" style="margin-bottom:12px;">
                <div class="kt-cms-muted">Current Section</div>
                <strong><?php echo html_escape((string) ($activeSection['title'] ?? 'None selected')); ?></strong>
                <div class="kt-cms-muted"><?php echo html_escape((string) ($activeSection['subtitle'] ?? 'Use the Preview button on a card to focus a section.')); ?></div>
            </div>

            <div class="kt-cms-preview-card">
                <div class="kt-cms-muted">What the marketing user sees</div>
                <div class="kt-cms-tabs" style="margin-top:10px;">
                    <span class="kt-cms-pill">Hero</span>
                    <span class="kt-cms-pill">Trust</span>
                    <span class="kt-cms-pill">Features</span>
                    <span class="kt-cms-pill">Pricing</span>
                    <span class="kt-cms-pill">FAQ</span>
                    <span class="kt-cms-pill">CTA</span>
                </div>
            </div>

            <div class="kt-cms-divider"></div>
            <h5>Global Blocks</h5>
            <div class="kt-cms-muted">Reusable assets surfaced as cards, not raw JSON.</div>
            <div class="kt-cms-divider"></div>
            <?php foreach ($globalBlocks as $block) {
                $blockId = (int) ($block['id'] ?? 0);
                $usageCount = (int) ($blockUsageCounts[$blockId] ?? 0);
                ?>
                <div class="kt-cms-page-card" style="margin-bottom:10px;">
                    <div class="kt-cms-page-card__head">
                        <div style="min-width:0;">
                            <div class="kt-cms-page-card__title"><?php echo html_escape((string) ($block['block_name'] ?? '')); ?></div>
                            <div class="kt-cms-muted"><?php echo html_escape((string) ($block['block_type'] ?? '')); ?></div>
                        </div>
                        <span class="kt-cms-pill"><?php echo $usageCount; ?> used</span>
                    </div>
                    <div class="kt-cms-tabs" style="margin-top:10px;">
                        <a class="btn btn-default btn-xs" href="<?php echo admin_url('kt_landing/global_blocks?edit_id=' . $blockId); ?>">Edit</a>
                        <a class="btn btn-default btn-xs" href="<?php echo admin_url('kt_landing/global_blocks?preview_id=' . $blockId); ?>">Preview</a>
                        <span class="btn btn-default btn-xs" style="pointer-events:none;">Duplicate</span>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
