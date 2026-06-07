<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<?php $leads = $leads ?? []; ?>
<?php $csrfTokenName = $this->security->get_csrf_token_name(); ?>
<?php $csrfTokenHash = $this->security->get_csrf_hash(); ?>
<div class="kt-cms-shell">
    <div class="kt-cms-hero">
        <div class="row">
            <div class="col-md-8">
                <h3>Conversion Center</h3>
                <p class="kt-cms-subtitle">Track leads, forms, CTA clicks, and the path from landing page to CRM.</p>
            </div>
        </div>
    </div>

    <div class="kt-cms-kpis">
        <div class="kt-cms-kpi"><span>Leads</span><strong><?php echo (int) count($leads); ?></strong></div>
        <div class="kt-cms-kpi"><span>Forms</span><strong><?php echo (int) count($leads); ?></strong></div>
        <div class="kt-cms-kpi"><span>CTA</span><strong>Tracked</strong></div>
        <div class="kt-cms-kpi"><span>UTM</span><strong>Captured</strong></div>
    </div>

    <div class="kt-cms-card">
        <h5>Lead Pipeline</h5>
        <div class="kt-cms-soft-table table-responsive">
            <table class="table table-bordered">
                <thead><tr><th>Name</th><th>Email</th><th>Company</th><th>Status</th><th>Workflow</th></tr></thead>
                <tbody>
                    <?php foreach ($leads as $lead) { ?>
                        <tr>
                            <td><?php echo html_escape((string) ($lead['name'] ?? '')); ?></td>
                            <td><?php echo html_escape((string) ($lead['email'] ?? '')); ?></td>
                            <td><?php echo html_escape((string) ($lead['company'] ?? '')); ?></td>
                            <td><?php echo html_escape((string) ($lead['status'] ?? '')); ?></td>
                            <td>
                                <form method="post" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                    <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int) ($lead['id'] ?? 0); ?>">
                                    <select name="status" class="form-control input-sm" style="width:140px;">
                                        <option value="new">New</option>
                                        <option value="contacted">Contacted</option>
                                        <option value="closed">Closed</option>
                                        <option value="converted">Converted</option>
                                    </select>
                                    <input type="text" name="note" class="form-control input-sm" style="width:160px;" placeholder="Note">
                                    <button class="btn btn-default btn-sm">Update</button>
                                    <button class="btn btn-info btn-sm" name="convert" value="1">Convert</button>
                                    <button class="btn btn-danger btn-sm" name="delete" value="1">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php } ?>
                    <?php if (empty($leads)) { ?><tr><td colspan="5">No leads yet.</td></tr><?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
