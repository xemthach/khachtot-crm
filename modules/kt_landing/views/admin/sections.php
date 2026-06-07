<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<div class="panel_s"><div class="panel-body">
    <form method="post">
        <input type="hidden" name="id" value="">
        <div class="row">
            <div class="col-md-2"><input class="form-control" name="page_key" placeholder="page_key" value="home"></div>
            <div class="col-md-2"><input class="form-control" name="section_key" placeholder="section_key"></div>
            <div class="col-md-2"><input class="form-control" name="title" placeholder="title"></div>
            <div class="col-md-2"><input class="form-control" name="subtitle" placeholder="subtitle"></div>
            <div class="col-md-2"><input class="form-control" name="sort_order" placeholder="sort_order"></div>
            <div class="col-md-2"><button class="btn btn-primary">Add</button></div>
        </div>
    </form>
    <hr>
    <table class="table table-bordered">
        <thead><tr><th>ID</th><th>Page</th><th>Key</th><th>Title</th><th>Enabled</th><th>Sort</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach ($sections as $s) { ?>
                <tr>
                    <td><?php echo (int) $s['id']; ?></td>
                    <td><?php echo html_escape($s['page_key']); ?></td>
                    <td><?php echo html_escape($s['section_key']); ?></td>
                    <td><?php echo html_escape($s['title']); ?></td>
                    <td><?php echo (int) $s['is_enabled']; ?></td>
                    <td><?php echo (int) $s['sort_order']; ?></td>
                    <td>
                        <form method="post" style="display:inline">
                            <input type="hidden" name="id" value="<?php echo (int) $s['id']; ?>">
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
