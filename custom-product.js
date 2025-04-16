/**
 * Script para o Template de Produto Personalizado
 */
jQuery(document).ready(function($) {
    // Variáveis globais
    const productId = $('input[name="add-to-cart"]').val() || $('button.single_add_to_cart_button').data('product_id');
    
    // ===== Galeria de Imagens =====
    $('.thumbnail-item').on('click', function() {
        const imgSrc = $(this).find('img').attr('src');
        $('.main-image').attr('src', imgSrc);
        $('.thumbnail-item').removeClass('active');
        $(this).addClass('active');
    });
    
    // ===== Seleção de Atributos =====
    $('.color-option').on('click', function() {
        $('.color-option').removeClass('selected');
        $(this).addClass('selected');
    });
    
    $('.size-option').on('click', function() {
        $('.size-option').removeClass('selected');
        $(this).addClass('selected');
    });
    
    // ===== Controle de Quantidade =====
    $('.qty-btn.minus').on('click', function() {
        let qty = parseInt($('.qty-input').val());
        if (qty > 1) {
            $('.qty-input').val(qty - 1).trigger('change');
        }
    });
    
    $('.qty-btn.plus').on('click', function() {
        let qty = parseInt($('.qty-input').val());
        let max = $('.qty-input').attr('max') || 999;
        if (qty < parseInt(max)) {
            $('.qty-input').val(qty + 1).trigger('change');
        }
    });
    
    // ===== Botão Adicionar ao Carrinho =====
    $('.add-to-cart-btn').on('click', function() {
        const quantity = $('.qty-input').val();
        let variationId = 0;
        
        // Verificar se é um produto variável
        if ($('.variations_form').length) {
            variationId = $('input[name="variation_id"]').val();
            if (!variationId) {
                alert('Por favor, selecione as opções do produto.');
                return;
            }
        } else {
            // Para produtos simples, verificar se todas as opções foram selecionadas
            if ($('.color-option').length && !$('.color-option.selected').length) {
                alert('Por favor, selecione uma cor.');
                return;
            }
            
            if ($('.size-option').length && !$('.size-option.selected').length) {
                alert('Por favor, selecione um tamanho.');
                return;
            }
        }
        
        // Adicionar ao carrinho via AJAX
        const button = $(this);
        button.prop('disabled', true).text('Adicionando...');
        
        $.ajax({
            type: 'POST',
            url: wc_add_to_cart_params.ajax_url,
            data: {
                action: 'custom_add_to_cart',
                product_id: productId,
                variation_id: variationId,
                quantity: quantity,
                nonce: wc_add_to_cart_params.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Atualizar fragmentos do carrinho
                    if (response.fragments) {
                        $.each(response.fragments, function(key, value) {
                            $(key).replaceWith(value);
                        });
                    }
                    
                    // Mostrar mensagem de sucesso
                    $('<div class="added-to-cart-message">Produto adicionado ao carrinho!</div>')
                        .appendTo('.custom-product-summary')
                        .fadeIn()
                        .delay(2000)
                        .fadeOut(function() {
                            $(this).remove();
                        });
                        
                    // Atualizar contador do carrinho
                    if (response.cart_count) {
                        $('.cart-count').text(response.cart_count);
                    }
                } else {
                    alert(response.message || 'Erro ao adicionar produto ao carrinho.');
                }
            },
            error: function() {
                alert('Erro ao processar sua solicitação. Por favor, tente novamente.');
            },
            complete: function() {
                button.prop('disabled', false).text('Comprar');
            }
        });
    });
    
    // ===== Sistema de Cálculo de Frete =====
    
    // Configuração inicial do tabindex
    function setupTabindex() {
        $('.shipping-toggle, #custom-shipping-postcode, #calculate-shipping, .searchCep, .change-shipping-btn, input[name="shipping_method"]')
            .attr('tabindex', '0');
    }
    
    // Gerenciamento do foco quando o conteúdo é exibido
    $(document).on('click', '.shipping-toggle', function() {
        $(this).toggleClass('active')
               .next('.shipping-content').slideToggle(200);
               
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
    
    // Formatação do CEP
    $('#custom-shipping-postcode').on('input', function() {
        let cep = $(this).val().replace(/\D/g, '');
        if (cep.length > 5) cep = cep.replace(/(\d{5})(\d)/, '$1-$2');
        $(this).val(cep.substring(0, 9));
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
        
        $.post(woocommerce_params.ajax_url, {
            action: 'calculate_custom_shipping',
            cep: cep,
            product_id: productId,
            quantity: $('.qty-input').val(),
            nonce: woocommerce_params.nonce
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
                
                // Dispara um evento personalizado
                $(document).trigger('shipping_calculated');
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
        if ($shippingRow.length) {
            $shippingRow.html(priceText);
        }
        
        // Atualização no servidor
        $.post(woocommerce_params.ajax_url, {
            action: 'update_shipping_method',
            method: methodId,
            product_id: productId,
            quantity: $('.qty-input').val(),
            nonce: woocommerce_params.nonce
        }, function(response) {
            if (response.success) {
                // Mantém tudo como está - não esconde as opções
                $('.shipping-toggle').addClass('active');
                $('.shipping-content').show();
                $('.shipping-methods').show();
                $('.shipping-confirmation').show();
            }
        });
    });
    
    // Execução inicial
    setupTabindex();
});