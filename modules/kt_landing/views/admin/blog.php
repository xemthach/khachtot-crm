<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<?php $posts = $posts ?? []; ?>
<?php $csrfTokenName = $this->security->get_csrf_token_name(); ?>
<?php $csrfTokenHash = $this->security->get_csrf_hash(); ?>
<div class="kt-cms-shell">
    <div class="kt-cms-hero">
        <div class="row">
            <div class="col-md-8">
                <h3>Content Hub</h3>
                <p class="kt-cms-subtitle">An editorial workspace for blog posts, FAQs, case studies, and resources.</p>
            </div>
            <div class="col-md-4 text-right">
                <span class="kt-cms-pill">Draft → Review → Preview → Publish</span>
            </div>
        </div>
    </div>

    <div class="kt-cms-card-grid">
        <div class="kt-cms-stat-card"><span class="label label-default">Draft</span><strong><?php echo (int) count(array_filter($posts, static function ($post) { return (string) ($post['status'] ?? '') === 'draft'; })); ?></strong><div class="kt-cms-muted">Items in progress.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-success">Published</span><strong><?php echo (int) count(array_filter($posts, static function ($post) { return (string) ($post['status'] ?? '') === 'published'; })); ?></strong><div class="kt-cms-muted">Live content items.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-info">Review</span><strong><?php echo (int) count($posts); ?></strong><div class="kt-cms-muted">Editorial items in the library.</div></div>
        <div class="kt-cms-stat-card"><span class="label label-primary">Preview</span><strong>Ready</strong><div class="kt-cms-muted">Content moves through a publish workflow.</div></div>
    </div>

    <div class="kt-cms-grid">
        <div class="kt-cms-card" style="grid-column: span 4;">
            <h5>Create Content</h5>
            <form method="post">
                <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                <div class="form-group"><label>Title</label><input class="form-control" name="title" placeholder="Article title"></div>
                <div class="form-group"><label>Slug</label><input class="form-control" name="slug" placeholder="article-slug"></div>
                <div class="form-group"><label>Excerpt</label><textarea class="form-control" name="excerpt" rows="3" placeholder="Short summary for cards and search previews"></textarea></div>
                <div class="form-group"><label>Content</label><textarea class="form-control" name="content" rows="8" placeholder="Write the article content here"></textarea></div>
                <div class="form-group"><label>Status</label>
                    <select class="form-control" name="status">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                    </select>
                </div>
                <button class="btn btn-primary btn-block">Add post</button>
            </form>
        </div>

        <div class="kt-cms-card" style="grid-column: span 8;">
            <h5>Editorial Workspace</h5>
            <div class="kt-cms-muted">Posts are shown as content cards with workflow context instead of raw CRUD tables.</div>
            <div class="kt-cms-divider"></div>
            <div class="kt-cms-card-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
                <?php foreach ($posts as $post) { ?>
                    <div class="kt-cms-stat-card">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start;">
                            <div style="min-width:0;">
                                <strong style="font-size:18px;"><?php echo html_escape((string) ($post['title'] ?? '')); ?></strong>
                                <div class="kt-cms-muted"><?php echo html_escape((string) ($post['slug'] ?? '')); ?></div>
                            </div>
                            <span class="label label-<?php echo ((string) ($post['status'] ?? '') === 'published') ? 'success' : 'warning'; ?>"><?php echo html_escape((string) ($post['status'] ?? '')); ?></span>
                        </div>
                        <div class="kt-cms-divider"></div>
                        <div class="kt-cms-muted"><?php echo html_escape((string) ($post['excerpt'] ?? '')); ?></div>
                        <div class="kt-cms-asset-meta">
                            <span class="kt-cms-pill">Featured image</span>
                            <span class="kt-cms-pill">Category</span>
                            <span class="kt-cms-pill">SEO</span>
                            <span class="kt-cms-pill">Author</span>
                        </div>
                        <div class="kt-cms-tabs" style="margin-top:12px;">
                            <span class="kt-cms-pill">Draft</span>
                            <span class="kt-cms-pill">Review</span>
                            <span class="kt-cms-pill">Preview</span>
                            <span class="kt-cms-pill">Publish</span>
                        </div>
                        <form method="post" class="tw-mt-3">
                            <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                            <input type="hidden" name="id" value="<?php echo (int) ($post['id'] ?? 0); ?>">
                            <input type="hidden" name="delete" value="1">
                            <button class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </div>
                <?php } ?>
                <?php if (empty($posts)) { ?>
                    <div class="col-md-12"><p class="kt-cms-muted">No content items yet.</p></div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
