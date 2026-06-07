<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<?php
$settings = $settings ?? [];
$pages = $pages ?? [];
$seoReport = $seo_report ?? ['health_score' => 0, 'pages_audited' => 0, 'pages_healthy' => 0, 'warnings' => [], 'critical_issues' => [], 'pages' => []];
$seoCounts = $seo_counts ?? ['pass' => 0, 'warning' => 0, 'critical' => 0, 'audited' => 0];
$selectedPage = $selected_page ?? null;
$selectedPageSeo = $selected_page_seo ?? [];
$publishBlockers = $publish_blockers ?? [];
$csrfTokenName = $this->security->get_csrf_token_name();
$csrfTokenHash = $this->security->get_csrf_hash();
$warningItems = (array) ($seoReport['warnings'] ?? []);
$criticalItems = (array) ($seoReport['critical_issues'] ?? []);
$reportPages = (array) ($seoReport['pages'] ?? []);
$missingTitleCount = 0;
$missingDescriptionCount = 0;
$missingH1Count = (int) ($seoReport['missing_h1_total'] ?? 0);
$missingAltCount = (int) ($seoReport['media_missing_alt_total'] ?? 0);
$brokenRefCount = (int) ($seoReport['broken_references_total'] ?? 0);
$duplicateMetaCount = 0;
$duplicateMetaDescriptionCount = 0;
$pageById = [];
foreach ($reportPages as $row) {
    $page = $row['page'] ?? [];
    $pageById[(int) ($page['id'] ?? 0)] = $row;
    foreach ((array) ($row['issues'] ?? []) as $issue) {
        $issueMessage = (string) ($issue['message'] ?? '');
        if ($issueMessage === 'Missing Title') {
            $missingTitleCount++;
        }
        if ($issueMessage === 'Missing Description') {
            $missingDescriptionCount++;
        }
        if ($issueMessage === 'Duplicate Meta Title') {
            $duplicateMetaCount++;
        }
        if ($issueMessage === 'Duplicate Meta Description') {
            $duplicateMetaDescriptionCount++;
        }
    }
}
?>
<div class="kt-cms-shell">
    <div class="kt-cms-hero">
        <div class="row">
            <div class="col-md-8">
                <h3>SEO Center</h3>
                <p class="kt-cms-subtitle">Central control for SEO health, page metadata, canonical rules, robots settings, and OpenGraph previews.</p>
            </div>
            <div class="col-md-4 text-right">
                <a class="btn btn-default" href="<?php echo admin_url('kt_landing/publish'); ?>">Open Publish Center</a>
            </div>
        </div>
    </div>

    <div class="kt-cms-kpis">
        <div class="kt-cms-kpi"><span>SEO Health Score</span><strong><?php echo (int) ($seoReport['health_score'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Pages Audited</span><strong><?php echo (int) ($seoCounts['audited'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Pages Healthy</span><strong><?php echo (int) ($seoCounts['pass'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Warnings</span><strong><?php echo (int) ($seoCounts['warning'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Critical Issues</span><strong><?php echo (int) ($seoCounts['critical'] ?? 0); ?></strong></div>
    </div>

    <div class="kt-cms-grid">
        <div class="kt-cms-card" style="grid-column: span 7;">
            <div class="row">
                <div class="col-sm-6">
                    <h5>SEO Dashboard</h5>
                    <div class="kt-cms-soft-table table-responsive">
                        <table class="table table-bordered table-condensed">
                            <thead><tr><th>Signal</th><th>Count</th><th>State</th></tr></thead>
                            <tbody>
                            <tr><td>Missing Title</td><td><?php echo (int) $missingTitleCount; ?></td><td>Critical</td></tr>
                            <tr><td>Missing Description</td><td><?php echo (int) $missingDescriptionCount; ?></td><td>Critical</td></tr>
                            <tr><td>Missing H1</td><td><?php echo (int) $missingH1Count; ?></td><td>Warning</td></tr>
                            <tr><td>Missing Alt</td><td><?php echo (int) $missingAltCount; ?></td><td>Warning</td></tr>
                            <tr><td>Broken References</td><td><?php echo (int) $brokenRefCount; ?></td><td>Critical</td></tr>
                            <tr><td>Duplicate Meta</td><td><?php echo (int) ($duplicateMetaCount + $duplicateMetaDescriptionCount); ?></td><td>Warning</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="col-sm-6">
                    <h5>Issues</h5>
                    <div class="kt-cms-soft-table table-responsive">
                        <table class="table table-bordered table-condensed">
                            <thead><tr><th>Type</th><th>Page</th><th>Issue</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice(array_merge($criticalItems, $warningItems), 0, 8) as $issue) { ?>
                                <tr>
                                    <td>
                                        <span class="label label-<?php echo (($issue['type'] ?? '') === 'CRITICAL') ? 'danger' : 'warning'; ?>">
                                            <?php echo html_escape((string) ($issue['type'] ?? '')); ?>
                                        </span>
                                    </td>
                                    <td><?php echo html_escape((string) ($issue['page_slug'] ?? '')); ?></td>
                                    <td><?php echo html_escape((string) ($issue['issue'] ?? '')); ?></td>
                                </tr>
                            <?php } ?>
                            <?php if (empty($criticalItems) && empty($warningItems)) { ?><tr><td colspan="3">No SEO issues detected.</td></tr><?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="kt-cms-card" style="grid-column: span 5;">
            <h5>Publish Integration</h5>
            <?php if (!empty($publishBlockers)) { ?>
                <div class="alert alert-danger">
                    <strong>Publish blocked</strong>
                    <div class="tw-mt-2">
                        <?php foreach (array_slice($publishBlockers, 0, 5) as $blocker) { ?>
                            <div class="small">- <?php echo html_escape((string) ($blocker['page_slug'] ?? '')); ?>: <?php echo html_escape((string) ($blocker['message'] ?? '')); ?></div>
                        <?php } ?>
                    </div>
                </div>
            <?php } else { ?>
                <div class="alert alert-success">No publish blockers from SEO Center.</div>
            <?php } ?>

            <?php if (!empty($selectedPage)) { ?>
                <div class="kt-cms-divider"></div>
                <h5>Preview Card</h5>
                <div class="kt-cms-card" style="background:#fff;border:1px solid #d9e3f0;">
                    <div class="small text-muted"><?php echo html_escape((string) ($selectedPageSeo['canonical_url'] ?? '')); ?></div>
                    <div style="font-size:18px;font-weight:700;margin:6px 0;"><?php echo html_escape((string) ($selectedPageSeo['og_title'] ?? $selectedPageSeo['meta_title'] ?? $selectedPage['title'] ?? '')); ?></div>
                    <div class="small" style="line-height:1.5;"><?php echo html_escape((string) ($selectedPageSeo['og_description'] ?? $selectedPageSeo['meta_description'] ?? '')); ?></div>
                    <div class="tw-mt-3 small text-muted">Robots: <?php echo html_escape((string) ($selectedPageSeo['robots_index'] ?? 'index')); ?> / <?php echo html_escape((string) ($selectedPageSeo['robots_follow'] ?? 'follow')); ?></div>
                </div>
            <?php } ?>

            <div class="kt-cms-divider"></div>
            <h5>SEO Settings</h5>
            <form method="post">
                <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                <input type="hidden" name="action" value="save_settings">
                <?php foreach ([
                    'default_meta_title' => 'Default Meta Title',
                    'default_meta_description' => 'Default Meta Description',
                    'default_og_image' => 'Default OG Image',
                    'canonical_url' => 'Canonical URL',
                    'robots_index' => 'Robots Index',
                    'robots_follow' => 'Robots Follow',
                    'google_analytics_id' => 'GA4 ID',
                    'facebook_pixel_id' => 'Meta Pixel ID',
                ] as $key => $label) { ?>
                    <div class="form-group">
                        <label><?php echo html_escape($label); ?></label>
                        <input type="text" class="form-control" name="<?php echo html_escape($key); ?>" value="<?php echo html_escape($settings[$key] ?? ''); ?>">
                    </div>
                <?php } ?>
                <div class="form-group">
                    <label>Custom Head HTML</label>
                    <textarea class="form-control" name="custom_head_html" rows="5"><?php echo html_escape($settings['custom_head_html'] ?? ''); ?></textarea>
                </div>
                <button class="btn btn-primary btn-block">Save SEO settings</button>
            </form>
        </div>
    </div>

    <div class="kt-cms-card" style="margin-top:20px;">
        <h5>Page SEO Manager</h5>
        <div class="kt-cms-soft-table table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                <tr>
                    <th>Page</th>
                    <th>SEO Title</th>
                    <th>Canonical</th>
                    <th>Robots</th>
                    <th>State</th>
                    <th>Issues</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($pages as $page) {
                    $pageId = (int) ($page['id'] ?? 0);
                    $row = $pageById[$pageId] ?? ['status' => 'PASS', 'issues' => []];
                    $state = (string) ($row['status'] ?? 'PASS');
                    $issues = (array) ($row['issues'] ?? []);
                    ?>
                    <tr <?php echo $selectedPageId === $pageId ? 'class="info"' : ''; ?>>
                        <td>
                            <strong><?php echo html_escape((string) ($page['title'] ?? '')); ?></strong>
                            <div class="small text-muted"><?php echo html_escape((string) ($page['slug'] ?? '')); ?></div>
                        </td>
                        <td><?php echo html_escape((string) ($row['meta_title'] ?? '')); ?></td>
                        <td><?php echo html_escape((string) ($row['canonical_url'] ?? '')); ?></td>
                        <td><?php echo html_escape((string) ($row['robots_index'] ?? '')); ?> / <?php echo html_escape((string) ($row['robots_follow'] ?? '')); ?></td>
                        <td>
                            <span class="label label-<?php echo $state === 'PASS' ? 'success' : ($state === 'WARNING' ? 'warning' : 'danger'); ?>">
                                <?php echo html_escape($state); ?>
                            </span>
                        </td>
                        <td><?php echo (int) count($issues); ?></td>
                        <td>
                            <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_landing/seo?page_id=' . $pageId); ?>">Edit</a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="kt-cms-grid" style="margin-top:20px;">
        <div class="kt-cms-card" style="grid-column: span 12;">
            <h5><?php echo html_escape((string) ($selectedPage['title'] ?? 'Select a page to edit')); ?></h5>
            <?php if (!empty($selectedPage)) { ?>
                <p class="kt-cms-subtitle"><?php echo html_escape((string) ($selectedPage['slug'] ?? '')); ?></p>
                <form method="post">
                    <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                    <input type="hidden" name="action" value="save_page_seo">
                    <input type="hidden" name="page_id" value="<?php echo (int) ($selectedPage['id'] ?? 0); ?>">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Meta Title</label>
                                <input type="text" class="form-control" name="meta_title" value="<?php echo html_escape((string) ($selectedPageSeo['meta_title'] ?? '')); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Canonical</label>
                                <input type="text" class="form-control" name="canonical_url" value="<?php echo html_escape((string) ($selectedPageSeo['canonical_url'] ?? '')); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Meta Description</label>
                        <textarea class="form-control" name="meta_description" rows="3"><?php echo html_escape((string) ($selectedPageSeo['meta_description'] ?? '')); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>OpenGraph Title</label>
                                <input type="text" class="form-control" name="og_title" value="<?php echo html_escape((string) ($selectedPageSeo['og_title'] ?? '')); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>OpenGraph Image</label>
                                <input type="text" class="form-control" name="og_image" value="<?php echo html_escape((string) ($selectedPageSeo['og_image'] ?? '')); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>OpenGraph Description</label>
                        <textarea class="form-control" name="og_description" rows="3"><?php echo html_escape((string) ($selectedPageSeo['og_description'] ?? '')); ?></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Robots Index</label>
                                <select class="form-control" name="robots_index">
                                    <option value="index" <?php echo (string) ($selectedPageSeo['robots_index'] ?? 'index') === 'index' ? 'selected' : ''; ?>>index</option>
                                    <option value="noindex" <?php echo (string) ($selectedPageSeo['robots_index'] ?? 'index') === 'noindex' ? 'selected' : ''; ?>>noindex</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Robots Follow</label>
                                <select class="form-control" name="robots_follow">
                                    <option value="follow" <?php echo (string) ($selectedPageSeo['robots_follow'] ?? 'follow') === 'follow' ? 'selected' : ''; ?>>follow</option>
                                    <option value="nofollow" <?php echo (string) ($selectedPageSeo['robots_follow'] ?? 'follow') === 'nofollow' ? 'selected' : ''; ?>>nofollow</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Twitter Card</label>
                                <select class="form-control" name="twitter_card">
                                    <?php foreach (['summary', 'summary_large_image'] as $card) { ?>
                                        <option value="<?php echo html_escape($card); ?>" <?php echo (string) ($selectedPageSeo['twitter_card'] ?? 'summary_large_image') === $card ? 'selected' : ''; ?>>
                                            <?php echo html_escape($card); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Current State</label>
                                <div class="form-control" style="background:#f8fafc;"><?php echo html_escape((string) ($pageById[(int) ($selectedPage['id'] ?? 0)]['status'] ?? 'PASS')); ?></div>
                            </div>
                        </div>
                    </div>
                    <button class="btn btn-primary">Save Page SEO</button>
                </form>
            <?php } else { ?>
                <p class="kt-cms-muted">Select a page from the table above to edit its SEO settings.</p>
            <?php } ?>
        </div>
    </div>
</div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
