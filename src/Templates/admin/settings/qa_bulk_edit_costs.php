<div class="qa-bulk-edit-costs">
    <div class="qa-bulk-header">
        <div class="left-bulk-head">
            <h1>Bulk Edit Costs</h1>
            <p>Bulk edit products costs/prices/stock. Tools options can be set in "Cost of Goods for WooCommerce" <a href="/wp-admin/admin.php?page=<?php echo esc_html(QA_COG_SETTINGS_SLUG); ?>">plugin settings</a></p>
        </div>
        <div class="right-bulk-head">
            <input type="text" id="bulk-search" value="<?php echo esc_html($search); ?>" placeholder="Fast Search">
        </div>
    </div>
    <table class="widefat fixed" cellspacing="0">
        <thead>
        <tr>
            <th id="product_id" class="manage-column column-id" scope="col">Product ID</th>
            <th id="product_sku" class="manage-column column-sku" scope="col">SKU</th>
            <th id="product_title" class="manage-column column-title" scope="col">Title</th>
            <th id="product_cost" class="manage-column column-cost num" scope="col">New Cost</th>
            <th id="product_cost" class="manage-column column-cost num" scope="col">Current Cost</th>
            <th id="product_price" class="manage-column column-price num" scope="col">Price</th>
            <th id="product_stock" class="manage-column column-stock num" scope="col">Stocks</th>
        </tr>
        </thead>
        <tfoot>
        <tr>
            <th class="manage-column column-id" scope="col">Product ID</th>
            <th class="manage-column column-sku" scope="col">SKU</th>
            <th class="manage-column column-title" scope="col">Title</th>
            <th class="manage-column column-cost num" scope="col">New Cost</th>
            <th class="manage-column column-cost num" scope="col">Current Cost</th>
            <th class="manage-column column-price num" scope="col">Price</th>
            <th class="manage-column column-stock num" scope="col">Stocks</th>
        </tr>
        </tfoot>
        <tbody>
            <?php
                while($products->have_posts()) : $products->the_post();

                // Prepare Variables
                $product_id = get_the_ID();
                $product = wc_get_product($product_id);
                $qa_price = get_post_meta($product_id, '_qa_cog_cost', true) ? get_post_meta($product_id, '_qa_cog_cost', true) : 0;
            ?>
                    <tr>
                        <th class="column-id" scope="col"><a title="Edit Product" href="<?php echo esc_html("/wp-admin/post.php?post={$product_id}&action=edit"); ?>"><?php echo esc_html($product_id); ?></a></th>
                        <th class="column-sku" scope="col"><?php echo esc_html($product->get_sku()); ?></th>
                        <th class="column-title" scope="col"><a title="To Product Page" href="<?php echo esc_html(get_permalink($product_id)); ?>"><?php echo esc_html($product->get_name()); ?></a></th>
                        <th class="column-cost num" scope="col"><input type="number" data-product-id="<?php echo esc_html($product_id); ?>" class="new-price-set" value="0"></th>
                        <th class="column-price num" scope="col"><?php echo esc_html($qa_price.get_woocommerce_currency_symbol()); ?></th>
                        <th class="column-price num" scope="col"><?php echo esc_html($product->get_price().get_woocommerce_currency_symbol()); ?></th>
                        <th class="column-stock num" scope="col"><?php echo esc_html($product->get_stock_quantity()); ?></th>
                    </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <div class="qa-settings-save-footer">
        <button id="save-qa-bulk" class="button action">Save Page Products</button>
        <div class="qa-bulk-pagination">
            <button id="prev-bulk-page" data-page="<?php echo esc_html($page-1); ?>" class="button action" <?php if($page == 1) {echo esc_html('disabled');} ?>><</button>
            <span><?php echo esc_html($page); ?> of <?php echo esc_html($products->max_num_pages); ?></span>
            <button id="prev-bulk-page" data-page="<?php echo esc_html($page+1); ?>" class="button action" <?php if($page == $products->max_num_pages) {echo esc_html('disabled');} ?>>></button>
        </div>
    </div>
</div>
