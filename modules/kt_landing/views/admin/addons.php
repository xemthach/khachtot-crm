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
                <h3>Landing Add-on Cards</h3>
                <p class="kt-cms-subtitle">Manage the public landing-page cards for add-ons and services. This screen controls marketing copy and visibility only.</p>
            </div>
        </div>
    </div>

    <div class="kt-cms-card-grid">
        <div class="kt-cms-stat-card"><span class="label label-default">Landing Cards</span><strong><?php echo (int) count($addons); ?></strong><div class="kt-cms-muted">Cards available for the public landing surface.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-info">Service Types</span><strong><?php echo (int) count($addons); ?></strong><div class="kt-cms-muted">Marketing entries managed in this landlord-only screen.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-success">Visible</span><strong><?php echo (int) count(array_filter($addons, static function ($addon) { return (string) ($addon['visible'] ?? '1') === '1'; })); ?></strong><div class="kt-cms-muted">Currently shown on the public landing page.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-default">Purpose</span><strong>Marketing Only</strong><div class="kt-cms-muted">This page does not install apps for tenants or manage tenant runtime access.</div></div>
    </div>

    <div class="kt-cms-card">
        <h5>Landing Add-on Card Manager</h5>
        <p class="kt-cms-muted tw-mb-3">Use these fields to control how add-ons and services appear on the public website. Tenant marketplace install flows are managed separately in the SaaS workspace.</p>
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
                            <span class="kt-cms-pill">Landing Card</span>
                            <span class="kt-cms-pill">Public Marketing</span>
                            <span class="kt-cms-pill">Marketing Description</span>
                        </div>

                        <div class="form-group tw-mt-3">
                            <label>Card title</label>
                            <input class="form-control" name="<?php echo html_escape($addonKey); ?>_title" value="<?php echo html_escape((string) ($addon['title'] ?? '')); ?>">
                        </div>
                        <div class="form-group">
                            <label>CTA link or label</label>
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
                <button class="btn btn-primary">Save landing add-on cards</button>
            </div>
        </form>
    </div>
</div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
