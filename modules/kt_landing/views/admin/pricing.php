<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<?php
$report = $pricing_report ?? ['summary' => [], 'rows' => []];
$summary = $report['summary'] ?? [];
$rows = $report['rows'] ?? [];
$csrfTokenName = $this->security->get_csrf_token_name();
$csrfTokenHash = $this->security->get_csrf_hash();

$stateClassMap = [
    'synced' => 'success',
    'warning' => 'warning',
    'mismatch' => 'danger',
];

$formatMoney = static function ($amount, $currency) {
    $currency = strtoupper(trim((string) $currency));
    return app_format_money((float) $amount, $currency, true) . ' ' . $currency;
};
?>
<div class="kt-cms-shell">
    <div class="kt-cms-hero">
        <div class="row">
            <div class="col-md-8">
                <h3>Pricing</h3>
                <p class="kt-cms-subtitle">Marketing-first pricing manager. Presentation fields stay editable while price truth stays locked to CRM plans.</p>
            </div>
        </div>
    </div>

    <div class="kt-cms-kpis">
        <div class="kt-cms-kpi"><span>Synced</span><strong><?php echo (int) ($summary['synced'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Warning</span><strong><?php echo (int) ($summary['warning'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Mismatch</span><strong><?php echo (int) ($summary['mismatch'] ?? 0); ?></strong></div>
        <div class="kt-cms-kpi"><span>Total Plans</span><strong><?php echo (int) ($summary['total'] ?? count($rows)); ?></strong></div>
    </div>

    <div class="kt-cms-grid">
        <div class="kt-cms-card" style="grid-column: span 12;">
            <h5>Plan Cards</h5>
            <?php if (!empty($rows)) { ?>
                <div class="row">
                    <?php foreach ($rows as $row) {
                        $planId = (int) ($row['id'] ?? 0);
                        $syncState = (string) ($row['sync_state'] ?? 'warning');
                        $stateClass = $stateClassMap[$syncState] ?? 'warning';
                        $planName = (string) ($row['plan_name'] ?? '');
                        $planCode = (string) ($row['plan_code'] ?? '');
                        $currency = (string) ($row['currency'] ?? 'VND');
                        $lockedPrice = $formatMoney($row['price'] ?? 0, $currency);
                        $lockedSetupFee = $formatMoney($row['setup_fee'] ?? 0, $currency);
                        $billingCycle = (string) ($row['billing_cycle'] ?? 'monthly');
                        $trialDays = (int) ($row['trial_days'] ?? 0);
                        $marketingTitle = (string) ($row['landing_marketing_title'] ?? '');
                        $marketingSubtitle = (string) ($row['landing_marketing_subtitle'] ?? '');
                        $marketingDescription = (string) ($row['landing_marketing_description'] ?? '');
                        $badgeText = (string) ($row['landing_badge_text'] ?? '');
                        $ctaText = (string) ($row['landing_cta_text'] ?? '');
                        $ctaUrl = (string) ($row['landing_cta_url'] ?? '');
                        $sortOrder = (int) ($row['landing_sort_order'] ?? 0);
                        $isVisible = (int) ($row['is_visible'] ?? 1) === 1;
                        $isFeatured = (int) ($row['landing_featured'] ?? 0) === 1;
                        $reasons = (array) ($row['sync_reasons'] ?? []);
                    ?>
                        <div class="col-md-6" style="margin-bottom:16px;">
                            <div class="kt-cms-sidebar">
                                <form method="post">
                                    <input type="hidden" name="<?php echo html_escape($csrfTokenName); ?>" value="<?php echo html_escape($csrfTokenHash); ?>">
                                    <input type="hidden" name="plan_id" value="<?php echo $planId; ?>">
                                <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">
                                    <div>
                                        <strong style="font-size:20px;"><?php echo html_escape($planName); ?></strong>
                                        <div class="kt-cms-muted"><?php echo html_escape($planCode); ?></div>
                                    </div>
                                    <span class="label label-<?php echo html_escape($stateClass); ?>"><?php echo html_escape((string) ($row['sync_label'] ?? ucfirst($syncState))); ?></span>
                                </div>

                                <div class="kt-cms-divider"></div>

                                <div class="row">
                                    <div class="col-sm-6 form-group">
                                        <label>Plan</label>
                                        <input class="form-control" type="text" value="<?php echo html_escape($planName); ?>" readonly>
                                    </div>
                                    <div class="col-sm-6 form-group">
                                        <label>Badge</label>
                                        <input class="form-control" name="badge_text" value="<?php echo html_escape($badgeText); ?>" placeholder="Popular / Trial / Recommended">
                                    </div>
                                    <div class="col-sm-6 form-group">
                                        <label>Price</label>
                                        <input class="form-control" type="text" value="<?php echo html_escape($lockedPrice); ?>" readonly>
                                    </div>
                                    <div class="col-sm-6 form-group">
                                        <label>Setup Fee</label>
                                        <input class="form-control" type="text" value="<?php echo html_escape($lockedSetupFee); ?>" readonly>
                                    </div>
                                    <div class="col-sm-6 form-group">
                                        <label>Best For</label>
                                        <input class="form-control" name="marketing_subtitle" value="<?php echo html_escape($marketingSubtitle); ?>" placeholder="Best fit customer segment">
                                    </div>
                                    <div class="col-sm-6 form-group">
                                        <label>CTA</label>
                                        <input class="form-control" name="cta_text" value="<?php echo html_escape($ctaText); ?>" placeholder="Free trial / Book a demo">
                                    </div>
                                    <div class="col-sm-12 form-group">
                                        <label>Marketing name</label>
                                        <input class="form-control" name="marketing_title" value="<?php echo html_escape($marketingTitle); ?>" placeholder="Marketing name shown on the landing page">
                                    </div>
                                    <div class="col-sm-12 form-group">
                                        <label>Marketing description</label>
                                        <input class="form-control" name="marketing_description" value="<?php echo html_escape($marketingDescription); ?>" placeholder="Short marketing description for the landing page">
                                    </div>
                                    <div class="col-sm-6 form-group">
                                        <label>Display order</label>
                                        <input class="form-control" type="number" name="sort_order" value="<?php echo (int) $sortOrder; ?>">
                                    </div>
                                    <div class="col-sm-6 form-group">
                                        <label>Visibility</label>
                                        <div class="checkbox">
                                            <label><input type="checkbox" name="is_visible" value="1" <?php echo $isVisible ? 'checked' : ''; ?>> Visible</label>
                                        </div>
                                        <div class="checkbox" style="margin-top:0;">
                                            <label><input type="checkbox" name="is_featured" value="1" <?php echo $isFeatured ? 'checked' : ''; ?>> Featured</label>
                                        </div>
                                    </div>
                                </div>

                                <details class="pricing-reasons">
                                    <summary class="kt-cms-pill" style="cursor:pointer;">Advanced diagnostics</summary>
                                    <div style="margin-top:12px;">
                                        <div class="kt-cms-muted">Locked by CRM source: price, setup fee, billing cycle, trial days, plan code.</div>
                                        <div class="kt-cms-divider"></div>
                                        <div class="kt-cms-soft-table">
                                            <table class="table table-bordered table-condensed">
                                                <tbody>
                                                    <tr><th style="width:180px;">Billing cycle</th><td><?php echo html_escape(kt_saas_billing_cycles()[$billingCycle] ?? $billingCycle); ?></td></tr>
                                                    <tr><th>Trial days</th><td><?php echo $trialDays; ?></td></tr>
                                                    <tr><th>Internal state</th><td><?php echo html_escape((string) ($row['sync_label'] ?? ucfirst($syncState))); ?></td></tr>
                                                    <tr>
                                                        <th>Reasons</th>
                                                        <td>
                                                            <?php if (!empty($reasons)) { ?>
                                                                <ul style="margin:0;padding-left:18px;">
                                                                    <?php foreach ($reasons as $reason) { ?>
                                                                        <li><?php echo html_escape((string) $reason); ?></li>
                                                                    <?php } ?>
                                                                </ul>
                                                            <?php } else { ?>
                                                                <span class="text-muted">No diagnostics.</span>
                                                            <?php } ?>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </details>

                                <div class="kt-cms-divider"></div>
                                <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center;">
                                    <div class="kt-cms-tabs" style="margin:0;">
                                        <span class="kt-cms-pill">Price <?php echo html_escape($lockedPrice); ?></span>
                                        <span class="kt-cms-pill">Setup fee <?php echo html_escape($lockedSetupFee); ?></span>
                                    </div>
                                    <div class="btn-group">
                                        <button type="submit" name="action" value="save" class="btn btn-default btn-sm">Save override</button>
                                        <button type="submit" name="action" value="sync_now" class="btn btn-primary btn-sm">Sync CRM source</button>
                                    </div>
                                </div>
                                </form>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <div class="alert alert-info">No public plans found.</div>
            <?php } ?>
        </div>
    </div>
</div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
