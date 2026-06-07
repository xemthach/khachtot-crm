<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<?php
$filters = $filters ?? ['draft', 'published', 'archived'];
$activeFilter = $active_filter ?? 'all';
$counts = $counts ?? ['draft' => 0, 'published' => 0, 'archived' => 0, 'all' => 0];
$snapshots = $snapshots ?? [];
$jobs = $jobs ?? [];
$csrfTokenName = $this->security->get_csrf_token_name();
$csrfTokenHash = $this->security->get_csrf_hash();
?>
<div class="kt-cms-shell">
    <div class="kt-cms-hero">
        <div class="row">
            <div class="col-md-8">
                <h3>Publish Center</h3>
                <p class="kt-cms-subtitle">Draft, preview, publish, rollback, and version history in a CMS publishing workflow.</p>
            </div>
        </div>
    </div>

    <div class="kt-cms-kpis">
        <div class="kt-cms-kpi"><span>All</span><strong><?php echo (int) ($counts['all'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Draft</span><strong><?php echo (int) ($counts['draft'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Published</span><strong><?php echo (int) ($counts['published'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Archived</span><strong><?php echo (int) ($counts['archived'] ?? 0); ?></strong></div>
    </div>

    <div class="kt-cms-grid">
        <div class="kt-cms-card" style="grid-column: span 4;">
            <h5>Draft Actions</h5>
            <form method="post" class="tw-mb-3">
                <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                <input type="hidden" name="publish_now" value="1">
                <button class="btn btn-primary btn-block">Publish Draft Snapshot</button>
            </form>
            <form method="post">
                <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                <div class="form-group">
                    <label>Snapshot ID</label>
                    <input class="form-control" name="snapshot_id" placeholder="snapshot_id">
                </div>
                <div class="form-group">
                    <label>Publish At</label>
                    <input class="form-control" name="publish_at" placeholder="YYYY-MM-DD HH:MM:SS">
                </div>
                <button name="schedule_publish" value="1" class="btn btn-default btn-block">Schedule Publish</button>
            </form>
        </div>

        <div class="kt-cms-card" style="grid-column: span 8;">
            <h5>Versions</h5>
            <div class="btn-group tw-mb-3" role="group">
                <a class="btn btn-default <?php echo $activeFilter === 'all' ? 'active' : ''; ?>" href="<?php echo admin_url('kt_landing/publish'); ?>">All</a>
                <?php foreach ($filters as $filter) { ?>
                    <a class="btn btn-default <?php echo $activeFilter === $filter ? 'active' : ''; ?>" href="<?php echo admin_url('kt_landing/publish?status=' . rawurlencode($filter)); ?>"><?php echo html_escape(ucfirst($filter)); ?></a>
                <?php } ?>
            </div>
            <div class="kt-cms-soft-table table-responsive">
                <table class="table table-bordered table-hover">
                    <thead>
                    <tr>
                        <th>Version</th>
                        <th>Snapshot</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Author</th>
                        <th>Checklist</th>
                        <th style="width: 220px;">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($snapshots as $snapshot) {
                        $checklist = $snapshot['checklist'] ?? [];
                        $summary = $snapshot['summary'] ?? [];
                        $summaryData = $snapshot['summary_data'] ?? [];
                        $status = (string) ($snapshot['snapshot_status'] ?? 'draft');
                        $version = (int) ($snapshot['snapshot_version'] ?? $snapshot['id']);
                        $issues = is_array($checklist) ? count((array) ($checklist['issues'] ?? [])) : 0;
                        $checkCount = is_array($checklist) ? count((array) ($checklist['items'] ?? [])) : 0;
                        ?>
                        <tr>
                            <td><strong>v<?php echo $version; ?></strong></td>
                            <td>
                                <div><strong><?php echo html_escape((string) ($snapshot['snapshot_name'] ?? ('Snapshot #' . (int) $snapshot['id']))); ?></strong></div>
                                <div class="text-muted small"><?php echo html_escape((string) ($snapshot['snapshot_type'] ?? 'full')); ?></div>
                                <div class="text-muted small">
                                    <?php echo (int) ($summaryData['pages'] ?? ($summary['pages'] ?? 0)); ?> pages ·
                                    <?php echo (int) ($summaryData['sections'] ?? ($summary['sections'] ?? 0)); ?> sections ·
                                    <?php echo (int) ($summaryData['global_blocks'] ?? ($summary['global_blocks'] ?? 0)); ?> blocks ·
                                    <?php echo (int) ($summaryData['pricing_overrides'] ?? ($summary['pricing_overrides'] ?? 0)); ?> pricing ·
                                    <?php echo (int) ($summaryData['menus'] ?? ($summary['menus'] ?? 0)); ?> menus
                                </div>
                            </td>
                            <td>
                                <span class="label label-<?php echo $status === 'published' ? 'success' : ($status === 'archived' ? 'default' : 'warning'); ?>">
                                    <?php echo html_escape(ucfirst($status)); ?>
                                </span>
                            </td>
                            <td>
                                <div><?php echo html_escape((string) ($snapshot['created_at'] ?? '')); ?></div>
                                <?php if (!empty($snapshot['published_at'])) { ?><div class="text-success small">Published: <?php echo html_escape((string) $snapshot['published_at']); ?></div><?php } ?>
                                <?php if (!empty($snapshot['archived_at'])) { ?><div class="text-muted small">Archived: <?php echo html_escape((string) $snapshot['archived_at']); ?></div><?php } ?>
                            </td>
                            <td><?php echo (int) ($snapshot['published_by'] ?? 0); ?></td>
                            <td>
                                <div><strong><?php echo $checkCount; ?></strong> checks</div>
                                <div class="small <?php echo $issues > 0 ? 'text-warning' : 'text-success'; ?>">
                                    <?php echo $issues > 0 ? $issues . ' issue(s)' : 'Clean'; ?>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group btn-group-vertical btn-block">
                                    <a class="btn btn-default btn-sm" href="<?php echo admin_url('kt_landing/publish?preview_id=' . (int) $snapshot['id']); ?>">Preview</a>
                                    <form method="post" class="tw-mt-1">
                                        <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                                        <input type="hidden" name="snapshot_id" value="<?php echo (int) $snapshot['id']; ?>">
                                        <button class="btn btn-warning btn-sm btn-block" name="apply_snapshot" value="1">Rollback</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($snapshots)) { ?><tr><td colspan="7">No snapshots</td></tr><?php } ?>
                    </tbody>
                </table>
            </div>

            <h5 class="tw-mt-4">Publish Jobs</h5>
            <div class="kt-cms-soft-table table-responsive">
                <table class="table table-bordered">
                    <thead><tr><th>ID</th><th>Snapshot</th><th>Publish At</th><th>Status</th><th>Processed At</th><th>Error</th></tr></thead>
                    <tbody>
                    <?php foreach ($jobs as $job) { ?>
                        <tr>
                            <td><?php echo (int) $job['id']; ?></td>
                            <td><?php echo (int) $job['snapshot_id']; ?></td>
                            <td><?php echo html_escape((string) ($job['publish_at'] ?? '')); ?></td>
                            <td><?php echo html_escape((string) ($job['status'] ?? '')); ?></td>
                            <td><?php echo html_escape((string) ($job['processed_at'] ?? '')); ?></td>
                            <td><?php echo html_escape((string) ($job['error_message'] ?? '')); ?></td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($jobs)) { ?><tr><td colspan="6">No jobs</td></tr><?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
