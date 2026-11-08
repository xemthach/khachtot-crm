<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="row">
    <div class="col-md-12">
        <h4
            class="tw-mt-0 tw-font-bold tw-text-lg tw-text-neutral-700 section-text section-heading-verification-required">
            <?= _l('email_verification_required'); ?>
        </h4>
        <div class="panel_s">
            <div class="panel-body">
                <div class="alert alert-<?php echo html_escape($verification_alert_class ?? 'warning'); ?> no-mbot verification-required-alert">
                    <h4 class="verification-required-heading">
                        <?= html_escape($verification_heading ?? _l('email_verification_required_message')); ?>
                    </h4>
                    <p class="bold verification-required-message">
                        <?php if (isset($verification_message)) { ?>
                            <?= html_escape($verification_message); ?>
                        <?php } else { ?>
                            <?= _l('email_verification_required_message_mail', site_url('verification/resend')); ?>
                        <?php } ?>
                    </p>
                    <?php if (!empty($verification_cta_url) && !empty($verification_cta_label)) { ?>
                        <p class="tw-mb-0">
                            <a href="<?php echo html_escape($verification_cta_url); ?>" class="btn btn-default">
                                <?php echo html_escape($verification_cta_label); ?>
                            </a>
                        </p>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
