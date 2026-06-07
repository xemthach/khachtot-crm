<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<div class="panel_s"><div class="panel-body">
    <form method="post" class="tw-mb-4">
        <button name="rebuild" value="1" class="btn btn-default">Rebuild Daily Analytics</button>
    </form>
    <table class="table table-bordered">
        <thead><tr><th>Event</th><th>Total (30d)</th></tr></thead>
        <tbody>
            <?php foreach ($overview as $event => $total) { ?>
                <tr><td><?php echo html_escape($event); ?></td><td><?php echo (int) $total; ?></td></tr>
            <?php } ?>
            <?php if (empty($overview)) { ?><tr><td colspan="2">No data</td></tr><?php } ?>
        </tbody>
    </table>
</div></div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
