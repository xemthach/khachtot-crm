<?php $this->load->view(KT_LANDING_MODULE . '/admin/_header', ['title' => $title]); ?>
<div class="panel_s"><div class="panel-body">
    <form method="post">
        <div class="row">
            <div class="col-md-2"><input class="form-control" name="menu_area" placeholder="header/footer/social"></div>
            <div class="col-md-2"><input class="form-control" name="label" placeholder="label"></div>
            <div class="col-md-3"><input class="form-control" name="url" placeholder="url"></div>
            <div class="col-md-1"><input class="form-control" name="target" placeholder="_self"></div>
            <div class="col-md-2"><input class="form-control" name="sort_order" placeholder="sort"></div>
            <div class="col-md-2"><button class="btn btn-primary">Add</button></div>
        </div>
    </form>
    <hr>
    <table class="table table-bordered">
        <thead><tr><th>ID</th><th>Area</th><th>Label</th><th>URL</th><th>Sort</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach ($menus as $m) { ?>
                <tr>
                    <td><?php echo (int) $m['id']; ?></td>
                    <td><?php echo html_escape($m['menu_area']); ?></td>
                    <td><?php echo html_escape($m['label']); ?></td>
                    <td><?php echo html_escape($m['url']); ?></td>
                    <td><?php echo (int) $m['sort_order']; ?></td>
                    <td>
                        <form method="post">
                            <input type="hidden" name="id" value="<?php echo (int) $m['id']; ?>">
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
