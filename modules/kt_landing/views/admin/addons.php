<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<?php
$addons = $addons ?? [];
$csrfTokenName = $this->security->get_csrf_token_name();
$csrfTokenHash = $this->security->get_csrf_hash();
?>
<div class="kt-cms-shell">
    <div class="kt-cms-hero">
        <div class="row">
            <div class="col-md-8">
                <h3>Marketplace</h3>
                <p class="kt-cms-subtitle">Manage add-ons as app cards with marketing descriptions, featured status, and CTA.</p>
            </div>
        </div>
    </div>

    <div class="kt-cms-card-grid">
        <div class="kt-cms-stat-card"><span class="label label-default">Featured Apps</span><strong><?php echo (int) count(array_filter($addons, static function ($addon) { return (string) ($addon['featured'] ?? '0') === '1'; })); ?></strong><div class="kt-cms-muted">Apps highlighted on the landing page.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-info">Categories</span><strong><?php echo (int) count($addons); ?></strong><div class="kt-cms-muted">Marketplace entries available to market.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-success">Visible</span><strong><?php echo (int) count(array_filter($addons, static function ($addon) { return (string) ($addon['visible'] ?? '1') === '1'; })); ?></strong><div class="kt-cms-muted">Currently shown to customers.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-default">App Cards</span><strong>Ready</strong><div class="kt-cms-muted">Internal keys stay hidden from marketing copy.</div></div>
    </div>

    <div class="kt-cms-card">
        <h5>Marketplace Manager</h5>
        <form method="post">
            <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
            <div class="kt-cms-asset-grid">
                <?php foreach ($addons as $addon) {
                    $addonKey = (string) ($addon['key'] ?? '');
                    ?>
                    <div class="kt-cms-app-card">
                        <div class="kt-cms-app-head">
                            <div class="kt-cms-app-icon"><?php echo html_escape(mb_strtoupper(mb_substr((string) ($addon['title'] ?? 'A'), 0, 1))); ?></div>
                            <div style="min-width:0;flex:1;">
                                <div class="kt-cms-app-name"><?php echo html_escape((string) ($addon['title'] ?? '')); ?></div>
                                <div class="kt-cms-muted"><?php echo html_escape((string) ($addon['description'] ?? '')); ?></div>
                            </div>
                            <label class="kt-cms-pill" style="cursor:pointer;">
                                <input type="checkbox" name="<?php echo html_escape($addonKey); ?>_visible" value="1" <?php echo (($addon['visible'] ?? '1') === '1') ? 'checked' : ''; ?>>
                                Visible
                            </label>
                        </div>

                        <div class="kt-cms-divider"></div>
                        <div class="kt-cms-asset-meta">
                            <span class="kt-cms-pill">App Card</span>
                            <span class="kt-cms-pill">Featured Apps</span>
                            <span class="kt-cms-pill">Marketing Description</span>
                        </div>

                        <div class="form-group tw-mt-3">
                            <label>App name</label>
                            <input class="form-control" name="<?php echo html_escape($addonKey); ?>_title" value="<?php echo html_escape((string) ($addon['title'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label>CTA</label>
                            <input class="form-control" name="<?php echo html_escape($addonKey); ?>_cta" value="<?php echo html_escape((string) ($addon['cta'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label>Marketing description</label>
                            <textarea class="form-control" name="<?php echo html_escape($addonKey); ?>_description" rows="3"><?php echo html_escape((string) ($addon['description'] ?? '')); ?></textarea>
                        </div>
                    </div>
                <?php } ?>
            </div>
            <div class="tw-mt-4">
                <button class="btn btn-primary">Save marketplace</button>
            </div>
        </form>
    </div>
</div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
