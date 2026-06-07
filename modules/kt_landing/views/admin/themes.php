<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<?php
$themes = $themes ?? [];
$style = $style ?? [];
$csrfTokenName = $this->security->get_csrf_token_name();
$csrfTokenHash = $this->security->get_csrf_hash();
?>
<div class="kt-cms-shell">
    <div class="kt-cms-hero">
        <div class="row">
            <div class="col-md-8">
                <h3>Design Studio</h3>
                <p class="kt-cms-subtitle">Control branding, colors, typography, buttons, cards, and templates from one place.</p>
            </div>
        </div>
    </div>

    <div class="kt-cms-grid">
        <div class="kt-cms-card" style="grid-column: span 7;">
            <h5>Templates</h5>
            <div class="kt-cms-soft-table">
                <table class="table">
                    <thead><tr><th>Template</th><th>State</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($themes as $theme) { ?>
                            <tr>
                                <td>
                                    <strong><?php echo html_escape((string) ($theme['name'] ?? '')); ?></strong>
                                    <div class="kt-cms-muted"><?php echo html_escape((string) ($theme['code'] ?? '')); ?></div>
                                </td>
                                <td><?php echo (int) ($theme['is_default'] ?? 0) === 1 ? '<span class="kt-cms-pill">Default</span>' : '<span class="kt-cms-muted">Available</span>'; ?></td>
                                <td>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                                        <input type="hidden" name="action" value="set_default">
                                        <input type="hidden" name="theme_code" value="<?php echo html_escape((string) ($theme['code'] ?? '')); ?>">
                                        <button class="btn btn-default btn-sm">Set Default</button>
                                    </form>
                                    <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_landing/preview/' . rawurlencode((string) ($theme['code'] ?? ''))); ?>">Preview</a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="kt-cms-card" style="grid-column: span 5;">
            <h5>Branding</h5>
            <form method="post">
                <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                <input type="hidden" name="action" value="save_style">
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Primary color</label>
                        <input type="text" class="form-control" name="primary_color" value="<?php echo html_escape($style['primary_color'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Secondary color</label>
                        <input type="text" class="form-control" name="secondary_color" value="<?php echo html_escape($style['secondary_color'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Accent color</label>
                        <input type="text" class="form-control" name="accent_color" value="<?php echo html_escape($style['accent_color'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Font family</label>
                        <input type="text" class="form-control" name="font_family" value="<?php echo html_escape($style['font_family'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Button radius</label>
                        <input type="text" class="form-control" name="button_radius" value="<?php echo html_escape($style['button_radius'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Card radius</label>
                        <input type="text" class="form-control" name="card_radius" value="<?php echo html_escape($style['card_radius'] ?? ''); ?>">
                    </div>
                    <div class="col-md-12 form-group">
                        <label>Light logo</label>
                        <input type="text" class="form-control" name="light_logo" value="<?php echo html_escape($style['light_logo'] ?? ''); ?>">
                    </div>
                    <div class="col-md-12 form-group">
                        <label>Dark logo</label>
                        <input type="text" class="form-control" name="dark_logo" value="<?php echo html_escape($style['dark_logo'] ?? ''); ?>">
                    </div>
                </div>
                <div class="kt-cms-divider"></div>
                <h5>Advanced</h5>
                <details>
                    <summary class="kt-cms-pill" style="cursor:pointer;">Show custom CSS / JS</summary>
                    <div class="kt-cms-divider"></div>
                    <div class="form-group"><label>Custom CSS</label><textarea class="form-control" name="custom_css" rows="5"><?php echo html_escape($style['custom_css'] ?? ''); ?></textarea></div>
                    <div class="form-group"><label>Custom JS</label><textarea class="form-control" name="custom_js" rows="5"><?php echo html_escape($style['custom_js'] ?? ''); ?></textarea></div>
                </details>
                <button type="submit" class="btn btn-primary">Save design settings</button>
            </form>
        </div>
    </div>
</div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
