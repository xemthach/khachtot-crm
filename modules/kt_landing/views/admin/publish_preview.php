<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$snapshot = $snapshot ?? null;
$payload = $payload ?? [];
$summary = $summary ?? ['pages' => 0, 'sections' => 0, 'global_blocks' => 0, 'pricing_overrides' => 0, 'menus' => 0];
$checklist = $checklist ?? ['items' => [], 'issues' => [], 'has_warning' => false, 'has_fail' => false];
$pages = (array) ($payload['pages'] ?? []);
$sections = (array) ($payload['sections'] ?? []);
$menus = (array) ($payload['menus'] ?? []);
$pricing = (array) ($payload['pricing'] ?? []);
$globalBlocks = (array) ($payload['global_blocks'] ?? []);
$settings = (array) ($payload['settings'] ?? []);
$status = (string) ($snapshot['snapshot_status'] ?? 'draft');
$snapshotName = (string) ($snapshot['snapshot_name'] ?? 'Preview');
$snapshotVersion = (int) ($snapshot['snapshot_version'] ?? 0);
$csrfTokenName = $this->security->get_csrf_token_name();
$csrfTokenHash = $this->security->get_csrf_hash();
?>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title ?? 'Publish Preview']); ?>
<div class="kt-cms-shell">
    <div class="kt-cms-hero">
        <div class="row">
            <div class="col-md-8">
                <h3><?php echo html_escape($snapshotName); ?></h3>
                <p class="kt-cms-subtitle">Version v<?php echo (int) $snapshotVersion; ?> · Status: <?php echo html_escape(ucfirst($status)); ?> · Admin-only preview with noindex and no-cache headers.</p>
            </div>
            <div class="col-md-4 text-right">
                <a class="btn btn-default" href="<?php echo admin_url('kt_landing/publish'); ?>">Back to Publish Center</a>
            </div>
        </div>
    </div>

    <div class="kt-cms-kpis">
        <div class="kt-cms-kpi"><span>Pages</span><strong><?php echo (int) ($summary['pages'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Sections</span><strong><?php echo (int) ($summary['sections'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Blocks</span><strong><?php echo (int) ($summary['global_blocks'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Pricing</span><strong><?php echo (int) ($summary['pricing_overrides'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Menus</span><strong><?php echo (int) ($summary['menus'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Author</span><strong><?php echo (int) ($snapshot['published_by'] ?? 0); ?></strong></div>
    </div>

    <div class="kt-cms-grid">
        <div class="kt-cms-card" style="grid-column: span 5;">
            <h5>Publish Checklist</h5>
            <div class="kt-cms-soft-table table-responsive">
                <table class="table table-bordered table-condensed">
                    <thead><tr><th>Check</th><th>Status</th><th>Message</th></tr></thead>
                    <tbody>
                    <?php foreach ((array) ($checklist['items'] ?? []) as $item) { ?>
                        <?php $state = (string) ($item['status'] ?? 'warning'); ?>
                        <tr>
                            <td><?php echo html_escape((string) ($item['label'] ?? '')); ?></td>
                            <td>
                                <span class="label label-<?php echo $state === 'pass' ? 'success' : ($state === 'fail' ? 'danger' : 'warning'); ?>">
                                    <?php echo html_escape(strtoupper($state)); ?>
                                </span>
                            </td>
                            <td><?php echo html_escape((string) ($item['message'] ?? '')); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="kt-cms-card" style="grid-column: span 7;">
            <h5>Snapshot Content</h5>
            <div class="kt-cms-tabs">
                <span class="kt-cms-pill">Pages</span>
                <span class="kt-cms-pill">Sections</span>
                <span class="kt-cms-pill">Global Blocks</span>
                <span class="kt-cms-pill">Pricing</span>
                <span class="kt-cms-pill">Menus</span>
            </div>

            <div class="kt-cms-divider"></div>
            <div class="kt-cms-soft-table table-responsive">
                <table class="table table-bordered table-condensed">
                    <thead><tr><th>Title</th><th>Slug</th><th>SEO</th></tr></thead>
                    <tbody>
                    <?php foreach ($pages as $page) { ?>
                        <tr>
                            <td><?php echo html_escape((string) ($page['title'] ?? '')); ?></td>
                            <td><?php echo html_escape((string) ($page['slug'] ?? '')); ?></td>
                            <td>
                                <div class="small">Title: <?php echo html_escape((string) ($page['seo_title'] ?? '')); ?></div>
                                <div class="small">Description: <?php echo html_escape((string) ($page['seo_description'] ?? '')); ?></div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="kt-cms-divider"></div>
            <div class="kt-cms-soft-table table-responsive">
                <table class="table table-bordered table-condensed">
                    <thead><tr><th>Global Block</th><th>Type</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($globalBlocks as $block) { ?>
                        <tr>
                            <td><?php echo html_escape((string) ($block['block_name'] ?? '')); ?></td>
                            <td><?php echo html_escape((string) ($block['block_type'] ?? '')); ?></td>
                            <td><?php echo html_escape((string) ($block['status'] ?? '')); ?></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
