<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<?php
$themes = $themes ?? [];
$globalBlocks = $global_blocks ?? [];
$sections = $sections ?? [];
$pages = $pages ?? [];
$mediaCount = (int) ($media_count ?? 0);
$analytics = $analytics ?? [];
$leads = $leads ?? [];
$posts = $posts ?? [];
$snapshots = $snapshots ?? [];
$pageCount = count($pages);
$draftPages = 0;
$publishedPages = 0;
foreach ($pages as $page) {
    if ((string) ($page['status'] ?? '') === 'published') {
        $publishedPages++;
    } elseif ((string) ($page['status'] ?? '') === 'draft') {
        $draftPages++;
    }
}
$recentLeadsCount = count($leads);
$pageViews = (int) ($analytics['page_view'] ?? 0);
$leadSubmits = (int) ($analytics['lead_submit'] ?? 0);
$signupSubmits = (int) ($analytics['signup_submit'] ?? 0);
?>
<div class="kt-cms-shell">
    <div class="kt-cms-hero">
        <div class="row">
            <div class="col-md-8">
                <h3>Dashboard</h3>
                <p class="kt-cms-subtitle">A marketing control center for website status, SEO health, content publishing, and lead activity.</p>
            </div>
            <div class="col-md-4 text-right">
                <div class="btn-group">
                    <a class="btn btn-primary" href="<?php echo admin_url('kt_landing/pages'); ?>">Open Website Builder</a>
                    <a class="btn btn-default" href="<?php echo admin_url('kt_landing/publish'); ?>">Open Publish Center</a>
                </div>
            </div>
        </div>
    </div>

    <div class="kt-cms-card-grid">
        <div class="kt-cms-stat-card"><span class="label label-success">Website Health</span><strong><?php echo empty($draftPages) ? 'Healthy' : 'Needs Review'; ?></strong><div class="kt-cms-muted">Public pages are ready for visitors.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-info">SEO Health</span><strong><?php echo !empty($analytics) ? 'Tracked' : 'Pending'; ?></strong><div class="kt-cms-muted">Metadata and indexing signals are visible.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-primary">Published Pages</span><strong><?php echo (int) $publishedPages; ?></strong><div class="kt-cms-muted">Pages currently live.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-warning">Draft Pages</span><strong><?php echo (int) $draftPages; ?></strong><div class="kt-cms-muted">Pages still in progress.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-primary">Leads</span><strong><?php echo (int) $recentLeadsCount; ?></strong><div class="kt-cms-muted">Recent conversions captured.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-default">Recent Changes</span><strong><?php echo (int) count($snapshots); ?></strong><div class="kt-cms-muted">Latest publish snapshots.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-success">Top CTA</span><strong><?php echo (int) $signupSubmits; ?></strong><div class="kt-cms-muted">Primary conversion action.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-default">Traffic</span><strong><?php echo number_format($pageViews); ?></strong><div class="kt-cms-muted">Page views tracked from marketing.</div></div>
    </div>

    <div class="kt-cms-grid">
        <div class="kt-cms-card" style="grid-column: span 7;">
            <h5>Recent Changes</h5>
            <?php if (!empty($snapshots)) { ?>
                <div class="kt-cms-card-grid">
                    <?php foreach (array_slice($snapshots, 0, 4) as $snapshot) { ?>
                        <div class="kt-cms-stat-card">
                            <span class="label label-default">v<?php echo (int) ($snapshot['snapshot_version'] ?? $snapshot['id']); ?></span>
                            <strong style="font-size:18px;"><?php echo html_escape((string) ($snapshot['snapshot_name'] ?? 'Snapshot')); ?></strong>
                            <div class="kt-cms-muted"><?php echo html_escape((string) ($snapshot['snapshot_status'] ?? 'draft')); ?></div>
                            <div class="kt-cms-muted"><?php echo html_escape((string) ($snapshot['created_at'] ?? '')); ?></div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <p class="kt-cms-muted">No recent publish snapshots yet.</p>
            <?php } ?>
        </div>

        <div class="kt-cms-card" style="grid-column: span 5;">
            <h5>Quick Actions</h5>
            <div class="kt-cms-tabs">
                <a class="btn btn-primary" href="<?php echo admin_url('kt_landing/pages'); ?>">Edit Website</a>
                <a class="btn btn-default" href="<?php echo admin_url('kt_landing/blog'); ?>">Open Content Hub</a>
                <a class="btn btn-default" href="<?php echo admin_url('kt_landing/media'); ?>">Open Media</a>
                <a class="btn btn-default" href="<?php echo admin_url('kt_landing/pricing'); ?>">Open Pricing</a>
                <a class="btn btn-default" href="<?php echo admin_url('kt_landing/seo'); ?>">Review SEO</a>
                <a class="btn btn-default" href="<?php echo admin_url('kt_landing/publish'); ?>">Publish Center</a>
                <a class="btn btn-default" href="<?php echo admin_url('kt_landing/clone'); ?>">Clone Template</a>
            </div>
            <div class="kt-cms-divider"></div>
            <p class="kt-cms-muted">This dashboard highlights the current website state instead of internal database counters.</p>
        </div>
    </div>

    <div class="kt-cms-grid">
        <div class="kt-cms-card" style="grid-column: span 6;">
            <h5>Recent Leads</h5>
            <?php if (!empty($leads)) { ?>
                <div class="kt-cms-card-grid">
                    <?php foreach (array_slice($leads, 0, 4) as $lead) { ?>
                        <div class="kt-cms-stat-card">
                            <span class="label label-default"><?php echo html_escape((string) ($lead['status'] ?? 'New')); ?></span>
                            <strong style="font-size:18px;"><?php echo html_escape((string) ($lead['name'] ?? '')); ?></strong>
                            <div class="kt-cms-muted"><?php echo html_escape((string) ($lead['company'] ?? '')); ?></div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <p class="kt-cms-muted">No leads captured yet.</p>
            <?php } ?>
        </div>

        <div class="kt-cms-card" style="grid-column: span 6;">
            <h5>Content Overview</h5>
            <div class="kt-cms-card-grid">
                <div class="kt-cms-stat-card"><span class="label label-default">Pages</span><strong><?php echo (int) $pageCount; ?></strong><div class="kt-cms-muted">Website pages in the current workspace.</div></div>
                <div class="kt-cms-stat-card"><span class="label label-default">Sections</span><strong><?php echo (int) count($sections); ?></strong><div class="kt-cms-muted">Reusable section structures.</div></div>
                <div class="kt-cms-stat-card"><span class="label label-default">Global Blocks</span><strong><?php echo (int) count($globalBlocks); ?></strong><div class="kt-cms-muted">Shared CTA, FAQ, and trust blocks.</div></div>
                <div class="kt-cms-stat-card"><span class="label label-default">Media</span><strong><?php echo (int) $mediaCount; ?></strong><div class="kt-cms-muted">Assets available in the library.</div></div>
                <div class="kt-cms-stat-card"><span class="label label-default">Content Posts</span><strong><?php echo (int) count($posts); ?></strong><div class="kt-cms-muted">Blog and content hub items.</div></div>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
