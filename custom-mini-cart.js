jQuery(document).ready(function($) {
    // Criar fallback para o container do Mercado Pago
    if (!$('#mp-checkout-container').length) {
        $('form.woocommerce-checkout').prepend('<div id="mp-checkout-container"></div>');
    }

    // Verificar novamente após atualizações do checkout
    $(document.body).on('updated_checkout', function() {
        if (!$('#mp-checkout-container').length) {
            $('form.woocommerce-checkout').prepend('<div id="mp-checkout-container"></div>');
            // Disparar evento para o Mercado Pago recarregar
            if (typeof mp !== 'undefined') {
                setTimeout(() => {
                    $(document.body).trigger('mp_reinit_checkout');
                }, 500);
            }
        }
    });
});


jQuery(document).ready(function($) {
    // Controle de abertura/fechamento
    $(document)
        .on('click', '.open-mini-cart', function(e) {
            e.preventDefault();
            $('.custom-cart-container').addClass('active');
            $('.cart-overlay').show().css({
                'opacity': '1',
                'pointer-events': 'all'
            });
        })
        .on('click', '.close-cart, .cart-overlay', function() {
            $('.custom-cart-container').removeClass('active');
            $('.cart-overlay').hide().css({
                'opacity': '0',
                'pointer-events': 'none'
            });
        });

    // Controle de quantidade
        $(document).on('click', '.qty-plus, .qty-minus', function() {
        const $item = $(this).closest('.cart-item');
        const key = $item.data('key');
        const isPlus = $(this).hasClass('qty-plus');
        const $qtyDisplay = $item.find('.item-quantity span');
        let qty = parseInt($qtyDisplay.text());
        
        // Calcula nova quantidade
        qty = isPlus ? qty + 1 : Math.max(1, qty - 1);
        
        // Atualiza visualmente imediatamente
        $qtyDisplay.text(qty);
        
        // Atualiza o preço visualmente
        const unitPrice = parseFloat($item.find('.item-price').data('unit-price'));
        $item.find('.item-price').text('R$ ' + (unitPrice * qty).toFixed(2).replace('.', ','));
        
        // Envia para o servidor
        updateCartItem(key, qty);
    });

    // Remover item
    $(document).on('click', '.remove-item', function() {
        if (!confirm('Tem certeza que deseja remover este item?')) return;
        
        const key = $(this).data('key');
        updateCartItem(key, 0); // Quantidade zero remove o item
    });

    // Aplicar cupom
    $(document).on('click', '#apply-coupon', function() {
        const couponCode = $('#custom-coupon-code').val().trim();
        const $btn = $(this);
        const $message = $('.coupon-message');
    
        if (!couponCode) {
            showCouponMessage('Digite um código de cupom', 'error');
            return;
        }
    
        $btn.prop('disabled', true).text('Aplicando...');
    
        $.post(miniCart.ajax_url, {
            action: 'apply_custom_coupon',
            coupon_code: couponCode,
            nonce: miniCart.nonce
        }, function(response) {
            if (response.success) {
                showCouponMessage(response.data.message, 'success');
                updateCartFragments(response.data.fragments);
            } else {
                showCouponMessage(response.data, 'error');
            }
        }).always(function() {
            $btn.prop('disabled', false).text('Aplicar');
        });
    });
    
    // Remover cupom
    $(document).on('click', '.remove-coupon', function(e) {
        e.preventDefault();
        const couponCode = $(this).data('code');
        const $btn = $(this);
        
        $btn.prop('disabled', true).text('Removendo...');
    
        $.post(miniCart.ajax_url, {
            action: 'remove_custom_coupon',
            coupon_code: couponCode,
            nonce: miniCart.nonce
        }, function(response) {
            if (response.success) {
                showCouponMessage(response.data.message, 'success');
                updateCartFragments(response.data.fragments);
            } else {
                showCouponMessage(response.data, 'error');
            }
        });
    });
    
    // Função auxiliar para mensagens
    function showCouponMessage(message, type) {
    const $message = $('.coupon-message');
    $message.html(`<p class="${type}">${message}</p>`).stop().fadeIn();
    
    if (type === 'error') {
        $('#custom-coupon-code').focus();
    }
    
    setTimeout(() => {
        $message.fadeOut(300);
    }, 5000);
}



    // Calcular frete
         // Toggle do frete
         // Tab para a section frete
        $(document).ready(function($) {
            // Configuração inicial do tabindex
            function setupTabindex() {
                $('.shipping-toggle, #custom-shipping-postcode, #calculate-shipping, .searchCep, .change-shipping-btn, input[name="shipping_method"]')
                    .attr('tabindex', '0');
            }
        
            // Gerenciamento do foco quando o conteúdo é exibido
            $(document).on('click', '.shipping-toggle', function() {
                if ($(this).hasClass('active')) {
                    setTimeout(() => {
                        $('#custom-shipping-postcode').focus();
                    }, 300);
                }
            });
        
            // Manipulação do teclado no toggle
            $('.shipping-toggle').on('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    $(this).click();
                }
            });
        
            // Foco nos métodos de envio quando calculados
            $(document).on('shipping_calculated', function() {
                $('input[name="shipping_method"]:first').focus();
            });
        
            // Chamada inicial
            setupTabindex();
        });
        $(document).on('click', '.shipping-toggle', function() {
            $(this).toggleClass('active')
                   .next('.shipping-content').slideToggle(200);
        });
        
        // Cálculo de frete
        $(document).on('click', '#calculate-shipping', function() {
            const cep = $('#custom-shipping-postcode').val().replace(/\D/g, '');
            const $btn = $(this);
            const $results = $('.shipping-results');
            
            if (cep.length !== 8) {
                $results.html('<p class="error">CEP deve ter 8 dígitos</p>').show();
                return;
            }
            
            // Adiciona o spinner no botão e desabilita
            $btn.prop('disabled', true).html('<span class="spinner"></span> Calculando...');
            
            // Mostra o indicador de carregamento nos resultados
            $results.html(`
                <div class="loading-shipping">
                    <div class="spinner"></div>
                    <p>Buscando opções de frete...</p>
                </div>
            `).show();
            
            $.post(miniCart.ajax_url, {
                action: 'calculate_custom_shipping',
                cep: cep,
                nonce: miniCart.nonce
            }, function(response) {
                if (response.success) {
                    $('.shipping-input').hide();
                    $('.shipping-confirmation').show();
                    $('.confirmed-cep').text($('#custom-shipping-postcode').val());
                    
                    $results.html(`
                        <div class="shipping-methods">
                            ${response.data.html}
                        </div>
                    `).show();
                    
                    $('input[name="shipping_method"]:first').prop('checked', true).trigger('change');
                } else {
                    $results.html(`<p class="error">${response.data}</p>`);
                }
            }).always(() => {
                // Restaura o texto original do botão
                $btn.prop('disabled', false).text('Calcular');
            });
        });
        
        // Alterar CEP
        $(document).on('click', '.change-shipping-btn', function(e) {
            e.preventDefault();
            $('.shipping-input').show();
            $('.shipping-confirmation').hide();
            $('.shipping-results').empty().hide();
            $('#custom-shipping-postcode').focus();
        });
        
        // Atualizar método de envio
       $(document).on('change', 'input[name="shipping_method"]', function() {
        const methodId = $(this).val();
        const $shippingRow = $('.shipping-total .amount');
        const priceText = $(this).closest('.shipping-method').find('.shipping-method-price').text();
        
        // Atualização visual imediata
        $shippingRow.html(priceText);
        
        // Atualização no servidor
        $.post(miniCart.ajax_url, {
            action: 'update_shipping_method',
            method: methodId,
            nonce: miniCart.nonce
        }, function(response) {
            if (response.success) {
                // Atualiza apenas os valores necessários
                $('.shipping-total .amount').html(response.data.shipping_html);
                updateCartTotals();
                
                // Mantém tudo como está - não esconde as opções
                $('.shipping-toggle').addClass('active');
                $('.shipping-content').show();
                $('.shipping-methods').show();
                $('.shipping-confirmation').show();
            }
        });
    });
   
        
        // Formatar CEP
        $('#custom-shipping-postcode').on('input', function() {
            let cep = $(this).val().replace(/\D/g, '');
            if (cep.length > 5) cep = cep.replace(/(\d{5})(\d)/, '$1-$2');
            $(this).val(cep.substring(0, 9));
        });

    // Atualiza item no carrinho
        function updateCartItem(key, quantity) {
        const $item = $('.cart-item[data-key="' + key + '"]');
        const $qtyDisplay = $item.find('.item-quantity span');
        
        // Bloqueia os botões durante a requisição
        $item.find('.qty-plus, .qty-minus').prop('disabled', true);
        
        jQuery.ajax({
            url: miniCart.ajax_url,
            type: 'POST',
            data: {
                action: 'update_custom_cart_item',
                item_key: key,
                quantity: quantity,
                nonce: miniCart.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Atualiza todos os fragments
                    updateCartFragments(response.data.fragments);
                }
            },
            error: function(xhr) {
                console.error('Erro ao atualizar:', xhr.responseText);
                // Reverte a quantidade visual em caso de erro
                const currentQty = parseInt($qtyDisplay.text());
                $qtyDisplay.text(quantity > currentQty ? currentQty : currentQty);
            },
            complete: function() {
                // Reativa os botões
                $item.find('.qty-plus, .qty-minus').prop('disabled', false);
            }
        });
    }

    // Atualiza totais
        function updateCartTotals() {
            $.post(miniCart.ajax_url, {
                action: 'get_custom_cart_totals',
                nonce: miniCart.nonce
            }, function(response) {
                if (response.success) {
                    // Atualiza todos os valores formatados
                    $('.subtotal .amount').html(response.data.subtotal);
                    $('.shipping-total .amount').html(response.data.shipping);
                    $('.discount-total .amount').html(response.data.discount);
                    $('.order-total .amount').html(response.data.total);
                    
            
                }
            });
        }
    
        // Atualiza fragments
          function updateCartFragments(fragments) {
        // 1. Atualiza os elementos específicos
        $.each(fragments, function(selector, html) {
            $(selector).html(html);
        });
        
        // 2. Atualiza os totais
        updateCartTotals();
        
        // 3. Atualiza os preços dos itens individualmente
        $('.cart-item').each(function() {
            const $item = $(this);
            const unitPrice = parseFloat($item.find('.item-price').data('unit-price'));
            const qty = parseInt($item.find('.item-quantity span').text());
            if (!isNaN(unitPrice) && !isNaN(qty)) {
                $item.find('.item-price').text('R$ ' + (unitPrice * qty).toFixed(2).replace('.', ','));
            }
        });
    }
        
    // Atualiza ao adicionar itens
    $(document.body).on('added_to_cart', function() {
        $.post(miniCart.ajax_url, {
            action: 'get_custom_cart_totals',
            nonce: miniCart.nonce
        }, function(response) {
            if (response.success) {
                updateCartFragments(response.data.fragments);
                updateCartTotals();
            }
        });
    });
});