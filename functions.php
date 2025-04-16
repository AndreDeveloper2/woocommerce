<?php

// =============================================
//  TEMPLATE PRODUTO UNICO
// =============================================

/**
 * Solução simplificada para template de produto personalizado
 */
function dcamomila_custom_product_template($template) {
    // Verifica se é uma página de produto e se o parâmetro custom_template está presente
    if ((is_product() || isset($_GET['product_id'])) && isset($_GET['custom_template']) && $_GET['custom_template'] == 'yes') {
        // Procura o template na raiz do tema
        $new_template = get_stylesheet_directory() . '/product-custom-template.php';
        
        // Se não encontrar na raiz, procura na pasta inc
        if (!file_exists($new_template)) {
            $new_template = get_stylesheet_directory() . '/inc/product-custom-template.php';
        }
        
        // Se encontrou o arquivo, use-o
        if (file_exists($new_template)) {
            return $new_template;
        }
    }
    
    return $template;
}
add_filter('template_include', 'dcamomila_custom_product_template', 999);

/**
 * Carregar CSS e JS para o template personalizado de produto
 */
function dcamomila_enqueue_custom_product_scripts() {
    // Verifica se estamos na página de produto personalizado
    if ((is_product() || isset($_GET['product_id'])) && isset($_GET['custom_template']) && $_GET['custom_template'] == 'yes') {
        // Carrega o CSS
        wp_enqueue_style('custom-product-style', get_stylesheet_directory_uri() . '/css/custom-product.css', array(), '1.0.0');
        
        // Carrega o JavaScript
        wp_enqueue_script('custom-product-script', get_stylesheet_directory_uri() . '/js/custom-product.js', array('jquery'), '1.0.0', true);
        
        // Localiza o script com variáveis necessárias
        wp_localize_script('custom-product-script', 'custom_product_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('custom-product-nonce')
        ));
        
        // Adiciona também as variáveis do WooCommerce que o script usa
        wp_localize_script('custom-product-script', 'wc_add_to_cart_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woocommerce-nonce')
        ));
        
        wp_localize_script('custom-product-script', 'woocommerce_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('woocommerce-nonce')
        ));
    }
}
add_action('wp_enqueue_scripts', 'dcamomila_enqueue_custom_product_scripts', 20);

/**
 * Certifique-se de que o produto esteja disponível no template personalizado
 */
function dcamomila_setup_product_on_custom_template() {
    if (isset($_GET['custom_template']) && $_GET['custom_template'] == 'yes') {
        global $product;
        
        if (!is_object($product)) {
            $product_id = get_the_ID();
            if (!$product_id && isset($_GET['product_id'])) {
                $product_id = absint($_GET['product_id']);
            }
            
            if ($product_id) {
                $product = wc_get_product($product_id);
            }
        }
    }
}
add_action('wp', 'dcamomila_setup_product_on_custom_template', 5);

/**
 * Redirecionar para o template personalizado se configurado para o produto
 */
function dcamomila_redirect_to_custom_template() {
    if (is_product()) {
        global $post;
        $use_custom_template = get_post_meta($post->ID, '_use_custom_template', true);
        
        if ($use_custom_template === 'yes' && !isset($_GET['custom_template'])) {
            wp_redirect(add_query_arg('custom_template', 'yes'));
            exit;
        }
    }
}
add_action('template_redirect', 'dcamomila_redirect_to_custom_template');

/**
 * Calcular frete personalizado
 */
function dcamomila_calculate_custom_shipping() {
    check_ajax_referer('woocommerce-nonce', 'nonce');
    
    if (empty($_POST['cep'])) {
        wp_send_json_error('CEP não informado');
    }
    
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep']);
    
    if (strlen($cep) !== 8) {
        wp_send_json_error('CEP inválido');
    }
    
    // Verificar se precisamos simular o carrinho para um produto específico
    if (isset($_POST['product_id']) && !empty($_POST['product_id'])) {
        $product_id = absint($_POST['product_id']);
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;
        
        // Salvar o carrinho atual
        $saved_cart = WC()->cart->get_cart_contents();
        
        // Limpar o carrinho temporariamente
        WC()->cart->empty_cart();
        
        // Adicionar o produto atual
        WC()->cart->add_to_cart($product_id, $quantity);
        
        // Definir o CEP e calcular o frete
        WC()->customer->set_shipping_postcode($cep);
        WC()->cart->calculate_shipping();
        
        // Gerar HTML dos métodos de envio
        $shipping_html = dcamomila_get_shipping_methods_html();
        
        // Restaurar o carrinho original
        WC()->cart->empty_cart();
        foreach ($saved_cart as $cart_item_key => $cart_item) {
            WC()->cart->add_to_cart(
                $cart_item['product_id'],
                $cart_item['quantity'],
                $cart_item['variation_id'],
                $cart_item['variation'],
                $cart_item
            );
        }
        WC()->cart->calculate_totals();
    } else {
        // Fluxo normal - usar o carrinho existente
        WC()->customer->set_shipping_postcode($cep);
        WC()->cart->calculate_shipping();
        $shipping_html = dcamomila_get_shipping_methods_html();
    }
    
    wp_send_json_success([
        'html' => $shipping_html
    ]);
}
add_action('wp_ajax_calculate_custom_shipping', 'dcamomila_calculate_custom_shipping');
add_action('wp_ajax_nopriv_calculate_custom_shipping', 'dcamomila_calculate_custom_shipping');

/**
 * Obter HTML dos métodos de envio
 */
function dcamomila_get_shipping_methods_html() {
    $packages = WC()->shipping->get_packages();
    $html = '';
    
    if (empty($packages) || empty($packages[0]['rates'])) {
        return '<p class="no-shipping-methods">Não há métodos de envio disponíveis para este endereço.</p>';
    }
    
    foreach ($packages[0]['rates'] as $method) {
        $html .= '<div class="shipping-method">';
        $html .= '<input type="radio" name="shipping_method" value="'.esc_attr($method->id).'" id="'.esc_attr($method->id).'">';
        $html .= '<label for="'.esc_attr($method->id).'">';
        
        // Nome da transportadora
        $html .= esc_html($method->label);
        
        // Preço
        $html .= '<span class="shipping-method-price">'.wc_price($method->cost).'</span>';
        
        // Estimativa de entrega
        $html .= '<span class="delivery-estimate">';
        $html .= dcamomila_get_formatted_delivery_estimate($method);
        $html .= '</span>';
        
        $html .= '</label></div>';
    }
    
    return $html;
}

/**
 * Obter estimativa de entrega formatada
 */
function dcamomila_get_formatted_delivery_estimate($method) {
    // Esta função simula a extração de dados de estimativa de entrega
    
    $estimate_days = '';
    
    if (strpos($method->id, 'flat_rate') !== false) {
        $estimate_days = 'em 2 a 5 dias úteis';
    } elseif (strpos($method->id, 'free_shipping') !== false) {
        $estimate_days = 'em 5 a 8 dias úteis';
    } elseif (strpos($method->id, 'local_pickup') !== false) {
        $estimate_days = 'retire hoje mesmo na loja';
    } else {
        // Para outros métodos, vamos gerar uma estimativa aleatória
        $min = rand(2, 5);
        $max = $min + rand(2, 5);
        $estimate_days = "em {$min} a {$max} dias úteis";
    }
    
    return $estimate_days;
}

/**
 * Atualizar método de envio
 */
function dcamomila_update_shipping_method() {
    check_ajax_referer('woocommerce-nonce', 'nonce');
    
    if (empty($_POST['method'])) {
        wp_send_json_error('Método de envio não especificado');
    }
    
    // Define o método de envio selecionado
    $method_id = sanitize_text_field($_POST['method']);
    WC()->session->set('chosen_shipping_methods', [sanitize_text_field($method_id)]);
    
    // Força o recálculo dos totais
    WC()->cart->calculate_totals();
    
    // Obtém o custo do método selecionado
    $shipping_total = WC()->cart->get_shipping_total();
    $shipping_html = $shipping_total > 0 ? wc_price($shipping_total) : 'Grátis';
    
    wp_send_json_success([
        'shipping_html' => $shipping_html
    ]);
}
add_action('wp_ajax_update_shipping_method', 'dcamomila_update_shipping_method');
add_action('wp_ajax_nopriv_update_shipping_method', 'dcamomila_update_shipping_method');

/**
 * Adicionar ao carrinho personalizado
 */
function dcamomila_custom_add_to_cart() {
    check_ajax_referer('woocommerce-nonce', 'nonce');
    
    $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
    $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
    $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;
    
    if (!$product_id) {
        wp_send_json_error(['message' => 'Produto inválido']);
        return;
    }
    
    // Verificar estoque
    $product_data = wc_get_product($variation_id ? $variation_id : $product_id);
    
    if (!$product_data || !$product_data->is_purchasable()) {
        wp_send_json_error(['message' => 'Produto não disponível para compra']);
        return;
    }
    
    if (!$product_data->is_in_stock()) {
        wp_send_json_error(['message' => 'Produto fora de estoque']);
        return;
    }
    
    // Adicionar ao carrinho
    $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id);
    
    if (!$cart_item_key) {
        wp_send_json_error(['message' => 'Erro ao adicionar produto ao carrinho']);
        return;
    }
    
    // Preparar dados para retorno
    WC()->cart->calculate_totals();
    
    // Obter fragmentos do carrinho
    $fragments = dcamomila_get_cart_fragments();
    
    wp_send_json_success([
        'message' => 'Produto adicionado ao carrinho',
        'fragments' => $fragments,
        'cart_count' => WC()->cart->get_cart_contents_count()
    ]);
}
add_action('wp_ajax_custom_add_to_cart', 'dcamomila_custom_add_to_cart');
add_action('wp_ajax_nopriv_custom_add_to_cart', 'dcamomila_custom_add_to_cart');

/**
 * Obter fragmentos do carrinho
 */
function dcamomila_get_cart_fragments() {
    $fragments = [];
    
    ob_start();
    woocommerce_mini_cart();
    $mini_cart = ob_get_clean();
    
    $fragments['div.widget_shopping_cart_content'] = '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>';
    
    return $fragments;
}


// =============================================
// 1. CONFIGURAÇÕES INICIAIS E SEGURANÇA
// =============================================

add_action('wp_head', 'dcamomila_add_open_graph_tags');
function dcamomila_add_open_graph_tags() {
    if (is_front_page()) {
        ?>
        <!-- Open Graph -->
        <meta property="og:title" content="Dona Camomila - Roupas leves, confortáveis e cheias de estilo" />
        <meta property="og:description" content="Descubra moda com propósito na Dona Camomila. Looks femininos para quem busca conforto, beleza e autenticidade no dia a dia." />
        <meta property="og:image" content="https://dcamomila.com/wp-content/uploads/2025/04/Logo-para-wpp.png" />
        <meta property="og:url" content="<?php echo esc_url(home_url()); ?>" />
        <meta property="og:type" content="website" />
        
        <!-- Twitter Card -->
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:title" content="Dona Camomila - Roupas leves, confortáveis e cheias de estilo" />
        <meta name="twitter:description" content="Descubra moda com propósito na Dona Camomila. Looks femininos para quem busca conforto, beleza e autenticidade no dia a dia." />
        <meta name="twitter:image" content="https://dcamomila.com/wp-content/uploads/2025/04/Logo-para-wpp.png" />
        <?php
    }
}

// Garante que o template aparece na lista de templates
function register_custom_product_template($templates) {
    $templates['product-custom-template.php'] = 'Template de Produto Personalizado';
    return $templates;
}
add_filter('theme_page_templates', 'register_custom_product_template');


add_shortcode('cart_counter', function() {
    $count = WC()->cart->get_cart_contents_count();
    return '<span class="cart-counter-shortcode" data-count="' . esc_attr($count) . '">' . $count . '</span>';
});

add_filter('woocommerce_blocks_register_checkout_block', function($settings) {
    $settings['mercadopago'] = [
        'name' => 'mercadopago',
        'label' => 'Mercado Pago',
        'content' => '<div id="mp-checkout-container-blocks"></div>',
        'icon' => '',
        'place_order_button_label' => 'Pagar com Mercado Pago'
    ];
    return $settings;
});

    // Garantir nonce no cabeçalho REST
    add_filter('rest_request_before_callbacks', function($response, $handler, $request) {
        // Se for uma rota do WooCommerce
        if (strpos($request->get_route(), '/wc/') === 0 && !$request->get_header('x-wp-nonce')) {
            // Tentar recuperar o nonce via query string ou cookie
            $nonce = $request->get_param('_wpnonce') ?: (isset($_COOKIE['wp_rest_nonce']) ? $_COOKIE['wp_rest_nonce'] : null);
            
            if ($nonce) {
                $request->add_header('X-WP-Nonce', $nonce);
            }
        }
        
        return $response;
    }, 10, 3);
    
    
    
    // Configurar cookie nonce para requisições REST
    add_action('template_redirect', function() {
        if (is_checkout() && !isset($_COOKIE['wp_rest_nonce'])) {
            setcookie('wp_rest_nonce', wp_create_nonce('wp_rest'), time() + 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
    });
    
    add_action('woocommerce_before_checkout_form', function() {
    error_log('Shipping Postcode: ' . WC()->customer->get_shipping_postcode());
    error_log('Chosen Shipping Methods: ' . print_r(WC()->session->get('chosen_shipping_methods'), true));
    });
    
    
// Corrige carregamento de traduções

add_action('init', function() {
    if (function_exists('load_plugin_textdomain')) {
        load_plugin_textdomain('woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
}, 5);

// =============================================
// 2. CARREGAMENTO DE ESTILOS E SCRIPTS
// =============================================

add_action('wp_enqueue_scripts', function() {
    // Estilo do tema filho
    wp_enqueue_style(
        'hello-elementor-child-style', 
        get_stylesheet_uri(), 
        ['hello-elementor-style'], 
        wp_get_theme()->get('Version')
    );
    
    add_action('wp_head', function () {
    echo '<style>
    a:focus,
    input:focus,
    select:focus,
    textarea:focus,
    button:focus,
    [type=button]:focus,
    [type=submit]:focus,
    .elementor input:focus,
    .elementor select:focus,
    .elementor textarea:focus,
    .elementor button:focus {
        outline: none !important;
        background-color: none !important;
        border: 1px solid #513626 !important;
        border-radius: 4px !important;
        box-shadow: none !important;
    }
    </style>';
});

   
   // Adicionar as fontes Inter e Poppins do Google Fonts
    wp_enqueue_style(
        'google-fonts',
        'https://fonts.googleapis.com/css2?family=Inter:wght@100;200;300;400;500;600;700;800;900&family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap',
        [],
        null // não precisa de versão para fontes externas
    );
    
    // CSS do mini-cart
    $css_path = get_stylesheet_directory() . '/css/custom-mini-cart.css';
    if (file_exists($css_path)) {
        wp_enqueue_style(
            'custom-mini-cart-css',
            get_stylesheet_directory_uri() . '/css/custom-mini-cart.css',
            [],
            filemtime($css_path)
        );
    } else {
        error_log('Arquivo CSS não encontrado: ' . $css_path);
    }
    
    // JS do mini-cart
    $js_path = get_stylesheet_directory() . '/js/custom-mini-cart.js';
    if (file_exists($js_path)) {
        wp_enqueue_script(
            'custom-mini-cart-js',
            get_stylesheet_directory_uri() . '/js/custom-mini-cart.js',
            ['jquery'],
            filemtime($js_path),
            true
        );
        
        // Variáveis para o JavaScript
        wp_localize_script('custom-mini-cart-js', 'miniCart', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('mini-cart-nonce'),
            'cart_url' => wc_get_cart_url()
        ]);
    }
});

// =============================================
// 3. CLASSE DO CARRINHO PERSONALIZADO
// =============================================

class Custom_Mini_Cart {
    public function __construct() {
        $this->register_ajax_handlers();
    }
    
    private function register_ajax_handlers() {
        $actions = [
            'apply_custom_coupon',
            'remove_custom_coupon',
            'calculate_custom_shipping',
            'update_shipping_method',
            'get_custom_cart_totals',
            'update_custom_cart_item'
        ];
        
        foreach ($actions as $action) {
            add_action("wp_ajax_{$action}", [$this, $action]);
            add_action("wp_ajax_nopriv_{$action}", [$this, $action]);
        }
    }
    
       public function apply_custom_coupon() {
        check_ajax_referer('mini-cart-nonce', 'nonce');
        
        if (empty($_POST['coupon_code'])) {
            wp_send_json_error('Digite um código de cupom');
        }
        
        $coupon_code = sanitize_text_field($_POST['coupon_code']);
        
        // Verifica se o cupom já foi aplicado
        if (WC()->cart->has_discount($coupon_code)) {
            wp_send_json_error('Este cupom já foi aplicado');
        }
        
        $applied = WC()->cart->apply_coupon($coupon_code);
        
        if ($applied) {
            wp_send_json_success([
                'message' => 'Cupom aplicado com sucesso!',
                'fragments' => $this->get_cart_fragments()
            ]);
        } else {
            wp_send_json_error('Cupom inválido ou expirado');
        }
    }

    public function remove_custom_coupon() {
        check_ajax_referer('mini-cart-nonce', 'nonce');
        
        if (empty($_POST['coupon_code'])) {
            wp_send_json_error('Código do cupom não informado');
        }
        
        $coupon_code = sanitize_text_field($_POST['coupon_code']);
        $removed = WC()->cart->remove_coupon($coupon_code);
        
        if ($removed) {
            wp_send_json_success([
                'message' => 'Cupom removido com sucesso!',
                'fragments' => $this->get_cart_fragments()
            ]);
        } else {
            wp_send_json_error('Não foi possível remover o cupom');
        }
    }
    
       public function calculate_custom_shipping() {
        check_ajax_referer('mini-cart-nonce', 'nonce');
        
        if (empty($_POST['cep'])) {
            wp_send_json_error('CEP não informado');
        }
        
        $cep = preg_replace('/[^0-9]/', '', $_POST['cep']);
        
        if (strlen($cep) !== 8) {
            wp_send_json_error('CEP inválido');
        }
        
        WC()->customer->set_shipping_postcode($cep);
        WC()->cart->calculate_shipping();
        
        ob_start();
        ?>
        <ul class="shipping-methods">
            <?php foreach (WC()->shipping()->get_packages()[0]['rates'] as $method) : ?>
                <li class="shipping-method">
                    <input 
                        type="radio" 
                        name="shipping_method" 
                        value="<?php echo esc_attr($method->id); ?>" 
                        id="<?php echo esc_attr($method->id); ?>"
                    >
                    <label for="<?php echo esc_attr($method->id); ?>">
                        <?php echo esc_html($method->label); ?>
                        <span class="shipping-method-price">
                            <?php echo wc_price($method->cost); ?>
                        </span>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        $html = ob_get_clean();
        
        wp_send_json_success([
            'html' => $html,
            'fragments' => $this->get_cart_fragments()
        ]);
    }
    
    // Na função que gera o HTML dos métodos de envio
private function get_shipping_methods_html() {
    $packages = WC()->shipping->get_packages();
    $html = '';
    
    foreach ($packages[0]['rates'] as $method) {
        $html .= '<div class="shipping-method">';
        $html .= '<input type="radio" name="shipping_method" value="'.esc_attr($method->id).'" id="'.esc_attr($method->id).'">';
        $html .= '<label for="'.esc_attr($method->id).'">';
        
        // Nome da transportadora
        $html .= esc_html($method->label);
        
        // Preço
        $html .= '<span class="shipping-method-price">'.wc_price($method->cost).'</span>';
        
        // Estimativa formatada (nova parte)
        $html .= '<span class="delivery-estimate">';
        $html .= $this->get_formatted_delivery_estimate($method);
        $html .= '</span>';
        
        $html .= '</label></div>';
    }
    
    return $html;
}

    
        public function get_custom_cart_totals() {
        check_ajax_referer('mini-cart-nonce', 'nonce');
        
        WC()->cart->calculate_totals();
        
        // Obter todos os totais de uma vez
        $totals = WC()->cart->get_totals();
        
        wp_send_json_success([
            'total' => WC()->cart->get_total(),
            'subtotal' => WC()->cart->get_cart_subtotal(),
            'shipping' => $totals['shipping_total'] > 0 ? wc_price($totals['shipping_total']) : 'Grátis',
            'discount' => $totals['discount_total'] > 0 ? wc_price($totals['discount_total']) : 'R$ 0,00',
            'fragments' => $this->get_cart_fragments()
        ]);
    }

        public function update_custom_cart_item() {
            check_ajax_referer('mini-cart-nonce', 'nonce');
            
            if (!isset($_POST['item_key'])) {
                wp_send_json_error('Item não especificado');
            }
    
            $cart_item_key = sanitize_text_field($_POST['item_key']);
            $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    
            if ($quantity < 1) {
                WC()->cart->remove_cart_item($cart_item_key);
            } else {
                WC()->cart->set_quantity($cart_item_key, $quantity);
            }
    
            wp_send_json_success([
                'fragments' => $this->get_cart_fragments()
            ]);
        }
        
        
        
        public function update_shipping_method() {
        check_ajax_referer('mini-cart-nonce', 'nonce');
        
        if (empty($_POST['method'])) {
            wp_send_json_error('Método de envio não especificado');
        }
        
        // Define o método de envio selecionado
        WC()->session->set('chosen_shipping_methods', [sanitize_text_field($_POST['method'])]);
        
        // Força o recálculo dos totais
        WC()->cart->calculate_totals();
        
        // Obtém o custo do método selecionado
        $shipping_total = WC()->cart->get_shipping_total();
        $shipping_html = $shipping_total > 0 ? wc_price($shipping_total) : 'Grátis';
        
        wp_send_json_success([
            'shipping_html' => $shipping_html,
            'fragments' => $this->get_cart_fragments()
        ]);
    }
        
     private function get_cart_fragments() {
         
        WC()->cart->calculate_totals();
        $totals = WC()->cart->get_totals();
        
        // Captura o HTML de todos os itens do carrinho
        ob_start();
        if (!WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                $product_price = apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($_product), $cart_item, $cart_item_key);
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
                                <?php echo $product_price; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
        } else {
            echo '<p class="empty-cart">' . __('Seu carrinho está vazio', 'hello-elementor-child') . '</p>';
        }
        $items_html = ob_get_clean();
    
       // HTML do cupom
        ob_start();
        if (WC()->cart->has_discount()) {
            foreach (WC()->cart->get_coupons() as $code => $coupon) {
                ?>
                <div class="applied-coupon">
                    <span><?php printf(__('Cupom %s aplicado com sucesso!', 'hello-elementor-child'), '<strong>' . esc_html(strtoupper($code)) . '</strong>'); ?></span>
                    <button class="remove-coupon" data-code="<?php echo esc_attr($code); ?>">
                        <?php _e('Remover', 'hello-elementor-child'); ?>
                    </button>
                </div>
                <?php
            }
        } else {
            ?>
            <div style="display:flex">
                <input type="text" id="custom-coupon-code" placeholder="<?php esc_attr_e('Código do cupom', 'hello-elementor-child'); ?>">
                <button id="apply-coupon"><?php _e('Aplicar', 'hello-elementor-child'); ?></button>
            </div>
            <div class="coupon-message"></div>
            <?php
        }
        $coupon_html = ob_get_clean();

        
        return [
        '.cart-body' => $items_html,
        '.cart-items-count' => WC()->cart->get_cart_contents_count(),
        '.cart-counter-shortcode' => WC()->cart->get_cart_contents_count(), // Atualiza o shortcode
        '.discount-total .amount' => $totals['discount_total'] > 0 ? wc_price($totals['discount_total']) : 'R$ 0,00',
        '.shipping-total .amount' => $totals['shipping_total'] > 0 ? wc_price($totals['shipping_total']) : 'Grátis',
        '.order-total .amount' => WC()->cart->get_total(),
        '.subtotal .amount' => WC()->cart->get_cart_subtotal(),
        '.cart-coupon' => $coupon_html
        ];
    }
}

// Adicionar este código após a classe Custom_Mini_Cart

        // Suporte à API REST para checkout
        add_action('wp_enqueue_scripts', function() {
            if (is_checkout()) {
                wp_enqueue_script(
                    'custom-checkout-support',
                    get_stylesheet_directory_uri() . '/js/checkout-support.js',
                    ['jquery', 'wc-checkout'],
                    time(),
                    true
                );
                
                // Passar o nonce REST para o JavaScript
                wp_localize_script('custom-checkout-support', 'wc_rest_params', [
                    'wpnonce' => wp_create_nonce('wp_rest'),
                    'rest_url' => esc_url_raw(rest_url())
                ]);
            }
        }, 20);

// Inicializa apenas se WooCommerce estiver ativo
if (function_exists('WC')) {
    new Custom_Mini_Cart();
}

// =============================================
// 4. SHORTCODE E CARREGAMENTO DO CARRINHO
// =============================================

add_shortcode('custom-mini-cart', function() {
    // Verifica se o WooCommerce está ativo
    if (!function_exists('WC')) {
        return '<p class="woocommerce-error">WooCommerce não está ativo</p>';
    }
    
    // Caminho seguro para o template
    $template_path = get_stylesheet_directory() . '/inc/custom-mini-cart-content.php';
    
    if (file_exists($template_path)) {
        ob_start();
        include $template_path;
        return ob_get_clean();
    } else {
        error_log('Erro: Template do carrinho não encontrado em ' . $template_path);
        return '<p class="woocommerce-error">Carrinho indisponível</p>';
    }
});

// Garante o carregamento no footer
add_action('wp_footer', function() {
    if (function_exists('WC') && !did_action('custom_mini_cart_rendered')) {
        echo do_shortcode('[custom-mini-cart]');
        do_action('custom_mini_cart_rendered');
    }
}, 5);