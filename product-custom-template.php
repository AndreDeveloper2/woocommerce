<?php
/**
 * Template Name: Produto Personalizado
 * Template Post Type: product
 */
if (!defined('ABSPATH')) {
    exit; // Saída direta se acessado diretamente
}

// Certificando-se de que temos um produto
global $product;

if (!is_object($product) || !$product->is_visible()) {
    $product_id = get_the_ID();
    $product = wc_get_product($product_id);
    
    if (!is_object($product) || !$product->is_visible()) {
        wp_redirect(wc_get_page_permalink('shop'));
        exit;
    }
}

get_header();
?>

<div class="custom-product-container">
    <div class="breadcrumbs">
        <a href="<?php echo esc_url(home_url('/')); ?>"><?php _e('Início', 'woocommerce'); ?></a> &gt;
        <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"><?php _e('Lançamentos', 'woocommerce'); ?></a> &gt;
        <span><?php echo esc_html($product->get_name()); ?></span>
    </div>

    <div class="custom-product-wrapper">
        <div class="custom-product-gallery">
            <div class="gallery-main">
                <?php
                $attachment_ids = $product->get_gallery_image_ids();
                $featured_image = get_post_thumbnail_id($product->get_id());
                
                if ($featured_image) {
                    array_unshift($attachment_ids, $featured_image);
                }
                
                if (!empty($attachment_ids)) {
                    echo wp_get_attachment_image($attachment_ids[0], 'full', false, array('class' => 'main-image'));
                } else {
                    echo wc_placeholder_img('full');
                }
                ?>
            </div>
            
            <div class="gallery-thumbnails">
                <?php
                if (!empty($attachment_ids)) {
                    foreach ($attachment_ids as $attachment_id) {
                        echo '<div class="thumbnail-item">';
                        echo wp_get_attachment_image($attachment_id, 'thumbnail', false, array('class' => 'thumbnail-image'));
                        echo '</div>';
                    }
                }
                ?>
            </div>
        </div>

        <div class="custom-product-summary">
            <h1 class="product-title"><?php echo esc_html($product->get_name()); ?></h1>
            
            <div class="product-price">
                <span class="regular-price"><?php echo wc_price($product->get_regular_price()); ?></span>
                
                <?php if ($product->is_on_sale()) : ?>
                    <span class="sale-price"><?php echo wc_price($product->get_sale_price()); ?></span>
                <?php endif; ?>
                
                <div class="price-pix">
                    <?php
                    // Preço com desconto para PIX (10%)
                    $pix_price = $product->get_price() * 0.9;
                    echo wc_price($pix_price) . ' com PIX';
                    ?>
                </div>
                
                <div class="installments">
                    <?php
                    // Parcelamento em 6x
                    $installment_price = $product->get_price() / 6;
                    echo '6 x de ' . wc_price($installment_price) . ' sem juros';
                    ?>
                </div>
                
                <div class="discount">
                    10% de desconto pagando com PIX
                </div>
            </div>
            
            <?php if ($product->is_type('variable')) : ?>
                <div class="product-variations">
                    <?php woocommerce_variable_add_to_cart(); ?>
                </div>
            <?php else : ?>
                <!-- Atributos para produtos simples -->
                <?php if ($product->has_attributes()) : ?>
                    <div class="product-attributes">
                        <?php
                        $attributes = $product->get_attributes();
                        
                        foreach ($attributes as $attribute) {
                            if ($attribute->get_visible()) {
                                $attribute_name = wc_attribute_label($attribute->get_name());
                                $attribute_values = array();
                                
                                if ($attribute->is_taxonomy()) {
                                    $attribute_taxonomy = $attribute->get_taxonomy_object();
                                    $attribute_terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), array('fields' => 'all'));
                                    
                                    foreach ($attribute_terms as $term) {
                                        $attribute_values[] = $term->name;
                                    }
                                } else {
                                    $attribute_values = $attribute->get_options();
                                }
                                
                                if (!empty($attribute_values)) {
                                    echo '<div class="attribute-section">';
                                    echo '<h3>' . esc_html($attribute_name) . ': ' . current($attribute_values) . '</h3>';
                                    
                                    if ($attribute_name == 'Cor' || $attribute_name == 'cor') {
                                        echo '<div class="color-options">';
                                        foreach ($attribute_values as $value) {
                                            $color_slug = sanitize_title($value);
                                            echo '<div class="color-option ' . esc_attr($color_slug) . '" data-color="' . esc_attr($value) . '">' . esc_html($value) . '</div>';
                                        }
                                        echo '</div>';
                                    } else {
                                        echo '<div class="size-options">';
                                        foreach ($attribute_values as $value) {
                                            echo '<div class="size-option">' . esc_html($value) . '</div>';
                                        }
                                        echo '</div>';
                                    }
                                    echo '</div>';
                                }
                            }
                        }
                        ?>
                    </div>
                <?php endif; ?>
                
                <div class="product-quantity">
                    <div class="quantity-selector">
                        <button class="qty-btn minus">-</button>
                        <input type="number" class="qty-input" value="1" min="1" max="<?php echo $product->get_stock_quantity() ?? 999; ?>">
                        <button class="qty-btn plus">+</button>
                    </div>
                    
                    <button class="add-to-cart-btn"><?php _e('Comprar', 'woocommerce'); ?></button>
                </div>
            <?php endif; ?>
            
            <!-- Frete -->
            <div class="cart-shipping">
                <div class="shipping-toggle" tabindex="0">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-icon lucide-package">
                        <path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/>
                        <path d="M12 22V12"/>
                        <polyline points="3.29 7 12 12 20.71 7"/>
                        <path d="m7.5 4.27 9 5.15"/>
                    </svg>
                    <h4><?php _e('Meios de envio', 'woocommerce'); ?></h4>
                    <span class="toggle-icon">+</span>
                </div>
                
                <div class="shipping-content" style="display:none">
                    <div class="shipping-input">
                        <input 
                            type="text" 
                            id="custom-shipping-postcode" 
                            placeholder="<?php esc_attr_e('Digite seu CEP', 'woocommerce'); ?>" 
                            maxlength="9"
                            value="<?php echo WC()->customer->get_shipping_postcode(); ?>"
                        >
                        <button id="calculate-shipping"><?php _e('Calcular', 'woocommerce'); ?></button>
                        <a href="https://buscacepinter.correios.com.br/app/endereco/index.php" class="searchCep" target="_blank">Não sei meu CEP</a>
                    </div>
                    <div class="shipping-confirmation" style="display:none">
                        <p>Enviando para: <span class="confirmed-cep"></span></p>
                        <a href="#" class="change-shipping-btn"><?php _e('Alterar CEP', 'woocommerce'); ?></a>
                    </div>
                    <div class="shipping-results">
                        <!-- Será preenchido via JavaScript -->
                    </div>
                </div>
            </div>
            
            <div class="product-description">
                <h3><?php _e('Descrição', 'woocommerce'); ?></h3>
                <div class="description-content">
                    <?php echo wpautop($product->get_description()); ?>
                </div>
                
                <?php if ($product->get_short_description()) : ?>
                    <div class="short-description">
                        <?php echo wpautop($product->get_short_description()); ?>
                    </div>
                <?php endif; ?>
                
                <?php 
                // Verifica se há informações adicionais para mostrar
                $product_attributes = $product->get_attributes();
                $display_attributes = array();
                
                foreach ($product_attributes as $attribute) {
                    if ($attribute['is_visible'] && $attribute['is_variation'] === false) {
                        $display_attributes[] = $attribute;
                    }
                }
                
                if (!empty($display_attributes)) : ?>
                    <div class="additional-info">
                        <h3><?php _e('Informações Adicionais', 'woocommerce'); ?></h3>
                        <table>
                            <?php foreach ($display_attributes as $attribute) : ?>
                                <tr>
                                    <th><?php echo wc_attribute_label($attribute['name']); ?></th>
                                    <td><?php
                                        $values = array();
                                        
                                        if ($attribute['is_taxonomy']) {
                                            $terms = wc_get_product_terms($product->get_id(), $attribute['name'], array('fields' => 'names'));
                                            $values = apply_filters('woocommerce_attribute', wptexturize(implode(', ', $terms)), $attribute, $terms);
                                        } else {
                                            $values = apply_filters('woocommerce_attribute', wptexturize(implode(', ', $attribute['value'])), $attribute, $attribute['value']);
                                        }
                                        
                                        echo $values;
                                    ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
get_footer();