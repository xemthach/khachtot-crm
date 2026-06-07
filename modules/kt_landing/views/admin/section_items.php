<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<div class="panel_s"><div class="panel-body">
    <form method="get" action="<?php echo admin_url('kt_landing/section_items'); ?>" class="row">
        <div class="col-md-4">
            <select name="section_id" class="form-control">
                <option value="">-- section --</option>
                <?php foreach (($sections ?? []) as $s) { ?>
                    <option value="<?php echo (int) $s['id']; ?>" <?php echo ((int)($selected_section_id ?? 0) === (int)$s['id']) ? 'selected' : ''; ?>>
                        <?php echo html_escape(($s['page_key'] ?? 'home') . ':' . ($s['section_key'] ?? '')); ?>
                    </option>
                <?php } ?>
            </select>
        </div>
        <div class="col-md-3"><input class="form-control" name="item_key" placeholder="item_key (optional)" value="<?php echo html_escape($selected_item_key ?? ''); ?>"></div>
        <div class="col-md-2"><button class="btn btn-default">Filter</button></div>
    </form>
    <hr>
    <form method="post" class="row">
        <input type="hidden" name="id" value="">
        <div class="col-md-2"><input class="form-control" name="section_id" placeholder="section_id" value="<?php echo (int)($selected_section_id ?? 0); ?>"></div>
        <div class="col-md-2"><input class="form-control" name="item_key" placeholder="item_key"></div>
        <div class="col-md-2"><input class="form-control" name="title" placeholder="title"></div>
        <div class="col-md-2"><input class="form-control" name="subtitle" placeholder="subtitle"></div>
        <div class="col-md-2"><input class="form-control" name="badge" placeholder="badge"></div>
        <div class="col-md-1"><input class="form-control" name="sort_order" placeholder="sort"></div>
        <div class="col-md-1"><button class="btn btn-primary">Add</button></div>
        <div class="col-md-12" style="margin-top:8px;"><input class="form-control" name="content" placeholder="content"></div>
    </form>
    <hr>
    <table class="table table-bordered">
        <thead><tr><th>ID</th><th>section_id</th><th>item_key</th><th>title</th><th>badge</th><th>enabled</th><th>sort</th><th>action</th></tr></thead>
        <tbody>
        <?php foreach (($items ?? []) as $item) { ?>
            <tr>
                <td><?php echo (int) $item['id']; ?></td>
                <td><?php echo (int) $item['section_id']; ?></td>
                <td><?php echo html_escape($item['item_key'] ?? ''); ?></td>
                <td><?php echo html_escape($item['title'] ?? ''); ?></td>
                <td><?php echo html_escape($item['badge'] ?? ''); ?></td>
                <td><?php echo (int) ($item['is_enabled'] ?? 0); ?></td>
                <td><?php echo (int) ($item['sort_order'] ?? 0); ?></td>
                <td>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                        <input type="hidden" name="delete" value="1">
                        <button class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
        <?php } ?>
        </tbody>
    </table>
</div></div>
<?php $this->load->view(KT_LANDING_MODULE . '/admin/_footer'); ?>
