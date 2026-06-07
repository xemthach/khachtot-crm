<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<?php $settings = $settings ?? []; ?>
<?php $csrfTokenName = $this->security->get_csrf_token_name(); ?>
<?php $csrfTokenHash = $this->security->get_csrf_hash(); ?>
<div class="kt-cms-shell">
    <div class="kt-cms-hero">
        <div class="row">
            <div class="col-md-8">
                <h3>Settings</h3>
                <p class="kt-cms-subtitle">Business-facing website settings first, with advanced platform toggles tucked away below.</p>
            </div>
        </div>
    </div>

    <form method="post">
        <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
        <div class="kt-cms-grid">
            <div class="kt-cms-card" style="grid-column: span 7;">
                <h5>Website Identity</h5>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label>Website Name</label>
                        <input type="text" class="form-control" name="site_name" value="<?php echo html_escape($settings['site_name'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Tagline</label>
                        <input type="text" class="form-control" name="site_title" value="<?php echo html_escape($settings['site_title'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Logo</label>
                        <input type="text" class="form-control" name="light_logo" value="<?php echo html_escape($settings['light_logo'] ?? ''); ?>" placeholder="Logo URL or file path">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Contact Email</label>
                        <input type="text" class="form-control" name="contact_email" value="<?php echo html_escape($settings['contact_email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Contact Phone</label>
                        <input type="text" class="form-control" name="contact_phone" value="<?php echo html_escape($settings['contact_phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 form-group">
                        <label>Company Address</label>
                        <input type="text" class="form-control" name="company_address" value="<?php echo html_escape($settings['company_address'] ?? ''); ?>">
                    </div>
                </div>

                <div class="kt-cms-divider"></div>
                <h5>Brand Defaults</h5>
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label>Homepage Mode</label>
                        <input type="text" class="form-control" name="homepage_mode" value="<?php echo html_escape($settings['homepage_mode'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Default Template</label>
                        <input type="text" class="form-control" name="default_template" value="<?php echo html_escape($settings['default_template'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Default Currency</label>
                        <input type="text" class="form-control" name="default_currency" value="<?php echo html_escape($settings['default_currency'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 form-group">
                        <label>Default Language</label>
                        <input type="text" class="form-control" name="default_language" value="<?php echo html_escape($settings['default_language'] ?? ''); ?>">
                    </div>
                    <div class="col-md-8 form-group">
                        <label>Website Description</label>
                        <input type="text" class="form-control" name="site_description" value="<?php echo html_escape($settings['site_description'] ?? ''); ?>">
                    </div>
                </div>

                <div class="kt-cms-divider"></div>
                <div class="form-group">
                    <label><input type="checkbox" name="landing_enabled" value="1" <?php echo ($settings['landing_enabled'] ?? '') === '1' ? 'checked' : ''; ?>> Landing enabled</label>
                    <label style="margin-left:16px;"><input type="checkbox" name="enable_blog" value="1" <?php echo ($settings['enable_blog'] ?? '') === '1' ? 'checked' : ''; ?>> Content Hub enabled</label>
                    <label style="margin-left:16px;"><input type="checkbox" name="enable_contact_form" value="1" <?php echo ($settings['enable_contact_form'] ?? '') === '1' ? 'checked' : ''; ?>> Contact form</label>
                    <label style="margin-left:16px;"><input type="checkbox" name="enable_pricing" value="1" <?php echo ($settings['enable_pricing'] ?? '') === '1' ? 'checked' : ''; ?>> Pricing visible</label>
                </div>
            </div>

            <div class="kt-cms-card" style="grid-column: span 5;">
                <h5>Advanced</h5>
                <details>
                    <summary class="kt-cms-pill" style="cursor:pointer;">Show advanced platform toggles</summary>
                    <div class="kt-cms-divider"></div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>SEO Enabled</label>
                            <input type="text" class="form-control" name="enable_seo" value="<?php echo html_escape($settings['enable_seo'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Public Signup</label>
                            <input type="text" class="form-control" name="enable_public_signup" value="<?php echo html_escape($settings['enable_public_signup'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Maintenance Mode</label>
                            <input type="text" class="form-control" name="maintenance_mode" value="<?php echo html_escape($settings['maintenance_mode'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>Custom Head HTML</label>
                            <textarea class="form-control" name="custom_head_html" rows="6"><?php echo html_escape($settings['custom_head_html'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-12 form-group">
                            <label>Site Theme Note</label>
                            <input type="text" class="form-control" name="default_template_note" value="<?php echo html_escape($settings['default_template_note'] ?? ''); ?>" placeholder="Optional internal note">
                        </div>
                    </div>
                </details>
                <div class="kt-cms-divider"></div>
                <button type="submit" class="btn btn-primary">Save settings</button>
            </div>
        </div>
    </form>
</div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
