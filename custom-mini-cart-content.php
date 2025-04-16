<?php if (!defined('ABSPATH')) exit; ?>
<div class="custom-cart-container">
    <div class="cart-overlay"></div>
    <div class="cart-box">
        <!-- Cabeçalho -->
        <div class="cart-header">
            <h3><?php _e('Seu Carrinho', 'hello-elementor-child'); ?></h3>
            <button class="close-cart"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#000000" viewBox="0 0 256 256"><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"></path></svg></button>
        </div>

        <!-- Corpo - Itens -->
        <div class="cart-body">
            <?php if (!WC()->cart->is_empty()) : ?>
                <?php foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) : 
                    $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                ?>
                    <div class="cart-item" data-key="<?php echo esc_attr($cart_item_key); ?>">
                        <div class="item-image">
                            <?php echo $_product->get_image(); ?>
                        </div>
                        
                        <div class="cart-content">
                            <div class="item-details">
                                <h4><?php echo $_product->get_name(); ?></h4>
                                <button class="remove-item" data-key="<?php echo esc_attr($cart_item_key); ?>">
                                    <?php _e('Remover', 'hello-elementor-child'); ?>
                                </button>
                            </div>
                            
                            <div class="priceAndQuantity">
                                <div class="item-quantity">
                                    <button class="qty-minus">-</button>
                                    <span><?php echo $cart_item['quantity']; ?></span>
                                    <button class="qty-plus">+</button>
                                </div>
                                
                                <div class="item-price" data-unit-price="<?php echo esc_attr($_product->get_price()); ?>">
                                    <?php echo WC()->cart->get_product_price($_product); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <p class="empty-cart"><?php _e('Seu carrinho está vazio', 'hello-elementor-child'); ?></p>
            <?php endif; ?>
        </div>
        
         <!-- Frete -->
        <div class="cart-shipping">
            <div class="shipping-toggle" tabindex="0">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-icon lucide-package"><path d="M11 21.73a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73z"/><path d="M12 22V12"/><polyline points="3.29 7 12 12 20.71 7"/><path d="m7.5 4.27 9 5.15"/></svg>
                <h4><?php _e('Meios de envio', 'hello-elementor-child'); ?></h4>
                <span class="toggle-icon">+</span>
            </div>
            
            <div class="shipping-content" style="display:none">
                <div class="shipping-input">
                    <input 
                        type="text" 
                        id="custom-shipping-postcode" 
                        placeholder="<?php esc_attr_e('Digite seu CEP', 'hello-elementor-child'); ?>" 
                        maxlength="9"
                        value="<?php echo WC()->customer->get_shipping_postcode(); ?>"
                    >
                    <button id="calculate-shipping"><?php _e('Calcular', 'hello-elementor-child'); ?></button>
                    <a href="https://buscacepinter.correios.com.br/app/endereco/index.php" class="searchCep">Não sei meu CEP</a>
                </div>
                 <div class="shipping-confirmation" style="display:none">
                    <p>Enviando para: <span class="confirmed-cep"></span></p>
                    <a href="#" class="change-shipping-btn"><?php _e('Alterar CEP', 'hello-elementor-child'); ?></a>
                </div>
                <div class="shipping-results">
                    <!-- Será preenchido via JavaScript -->
                </div>
            </div>
        </div>

        <!-- Cupom -->
        <div class="cart-coupon">
            <?php if (WC()->cart->has_discount()) : ?>
                <?php foreach (WC()->cart->get_coupons() as $code => $coupon) : ?>
                    <div class="applied-coupon">
                        <span><?php printf(__('Cupom %s aplicado!', 'hello-elementor-child'), '<strong>' . esc_html(strtoupper($code)) . '</strong>'); ?></span>
                        <button class="remove-coupon" data-code="<?php echo esc_attr($code); ?>">
                            <?php _e('Remover', 'hello-elementor-child'); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
            <div style="display:flex">
                <input type="text" id="custom-coupon-code" placeholder="<?php esc_attr_e('Código do cupom', 'hello-elementor-child'); ?>">
                <button id="apply-coupon"><?php _e('Aplicar', 'hello-elementor-child'); ?></button>
            </div>
            <div class="coupon-message"></div>
            <?php endif; ?>
        </div>


        <!-- Totais -->
        <div class="cart-totals">
            <div class="total-row subtotal">
                <span><?php _e('Subtotal', 'hello-elementor-child'); ?></span>
                <span class="amount"><?php echo WC()->cart->get_cart_subtotal(); ?></span>
            </div>
            <div class="total-row shipping-total">
                <span><?php _e('Frete', 'hello-elementor-child'); ?></span>
                <span class="amount">R$ 0,00</span>
            </div>
            <div class="total-row discount-total">
                <span><?php _e('Desconto', 'hello-elementor-child'); ?></span>
                <span class="amount"><?php echo WC()->cart->get_discount_total() > 0 ? wc_price(WC()->cart->get_discount_total()) : 'R$ 0,00'; ?></span>
            </div>
            <div class="total-row order-total">
                <strong><?php _e('Total', 'hello-elementor-child'); ?></strong>
                <strong><span class="amount"><?php echo WC()->cart->get_total(); ?></span></strong>
            </div>
        </div>
    
        <!-- Botão Finalizar -->
        <!-- Botão Finalizar -->
        <a href="<?php echo esc_url(add_query_arg('_wpnonce', wp_create_nonce('woocommerce-process_checkout'), wc_get_checkout_url())); ?>" class="checkout-btn">
            <?php _e('Finalizar Compra', 'hello-elementor-child'); ?>
        </a>
    </div>
</div>