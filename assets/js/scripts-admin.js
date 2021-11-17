jQuery(document).ready(function () {
	
	// При обновление формы заказа
	if (jQuery('input#post_type[value="shop_order"]').length) {
		jQuery('form#post').on('submit', function (event) {
			if (jQuery(this).hasClass('stop')) {
				event.preventDefault();
				return false;
			}
		});
	}
	
	// Изменение адреса
	if (jQuery('input#_deliv_type').length) {
		if ('courier' == jQuery('input#_deliv_type').val())
		{
			let checkAddress = function () {
				let address = jQuery('#order_data #_shipping_city').val() + ', ' + jQuery('#order_data #_shipping_address_1').val();
				jQuery.ajax({
					beforeSend: function() {
						jQuery('#woocommerce-order-actions .save_order').prop('disabled', true);
						jQuery('form#post').addClass('stop');
					},
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'check_address',
						address: address,
					},
					success: function (response) {
						if (response.success === true) {
							jQuery('#order_data #_shipping_city, #order_data #_shipping_address_1').addClass('success');
							jQuery('#order_data #_shipping_city, #order_data #_shipping_address_1').removeClass('error');
							jQuery('#woocommerce-order-actions .save_order').prop('disabled', false);
							jQuery('form#post').removeClass('stop');
						} else {
							jQuery('#order_data #_shipping_city, #order_data #_shipping_address_1').removeClass('success');
							jQuery('#order_data #_shipping_city, #order_data #_shipping_address_1').addClass('error');
						}
					},
					complete: function () {
						// jQuery('#woocommerce-order-actions .save_order').prop('disabled', false);
					},
				});
				
			}
			
			jQuery('#wpwrap').on('change', '#order_data #_shipping_city', function () {
				checkAddress();
			});
			jQuery('#wpwrap').on('change', '#order_data #_shipping_address_1', function () {
				checkAddress();
			});
		}
    }

	// Способ доставки для нового заказа
	if (jQuery('#lpost_wc_metabox_new').length) {
		let cityInp = jQuery('#_lp_courier_city'),
			addressInp = jQuery('#_lp_courier_address'),
			dateInp = jQuery('#_lp_delivery_date'),
			instance_id = 0, deliv_type = 'pickup';
			
		jQuery('#woocommerce-order-actions .save_order').prop('disabled', true);
		jQuery('#lpost_wc_metabox_new .pickup-field').prop('disabled', true);
		jQuery('#lpost_wc_metabox_new .courier-field').prop('disabled', true);
		
		jQuery('select#lp_method_instance_id_new').on('change', function(){
			instance_id = jQuery(this).find(':selected').val();
			deliv_type = jQuery(this).find(':selected').data('deliv_type');

			jQuery('#lpost_wc_metabox_new .pickup-field').prop('disabled', true);
			jQuery('#lpost_wc_metabox_new .courier-field').prop('disabled', true);
			
			if ('pickup' == deliv_type) {
				jQuery('#lpost_wc_metabox_new .pickup-field').prop('disabled', false);
			}
			if ('courier' == deliv_type) {
				jQuery('#lpost_wc_metabox_new .courier-field').prop('disabled', false);
			}
		});

		cityInp.on('change', function(){
			checkAddressNew();
		});
		addressInp.on('change', function(){
			checkAddressNew();
		});
			
		let checkAddressNew = function () {
			
			if (!cityInp.val() || !addressInp.val())
				return;
			
			let address = cityInp.val() +', ' + addressInp.val(); 
			
			jQuery.ajax({
				beforeSend: function() {
					jQuery('#woocommerce-order-actions .save_order').prop('disabled', true);
					jQuery('form#post').addClass('stop');
				},
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'check_address',
					address: address,
					instance_id: instance_id,
				},
				success: function (response) {
					if (response.success === true) 
					{
						jQuery('#woocommerce-order-actions .save_order').prop('disabled', false);
						jQuery('form#post').removeClass('stop');
						
						cityInp.addClass('success');
						addressInp.addClass('success');
						
						cityInp.removeClass('error');
						addressInp.removeClass('error');
						
						if (response.data.PossibleDelivDates && dateInp.length) {
							dateInp.find('option').remove();
							for (var date in response.data.PossibleDelivDates)
							{
								dateInp.append(jQuery('<option>', {
									value: date,
									text: response.data.PossibleDelivDates[date]
								}));
							}
						}
						
					} else {
						cityInp.removeClass('success');
						addressInp.removeClass('success');
						
						cityInp.addClass('error');
						addressInp.addClass('error');
					}
				},
				complete: function () {},
			});
		}
    }
	
    // Вызов - Создание накладной
    jQuery('.invoices-table').on('click', '[data-action="create-invoice"]', function () {
		createInvoice([this.dataset.order_id]);
    });
	
	//  Вызов - Создание накладной для нескольких заказов
    jQuery('.wrap').on('click', 'button[data-action="create-invoice-select"]', function () {
		
		let orders = [];
		jQuery('.invoices-table input[name="order[]"]:checked').each(function(i) {
			orders.push(this.value);
		});
		createInvoice(orders);
    });

    // Запрос на создание накладной
    function createInvoice(orders) {
        jQuery.ajax({
            beforeSend: function() {
                jQuery('.invoices-table-wrapper').addClass('loading');
            },
            url: ajaxurl,
			type: 'POST',
            data: {
                action: 'create_invoice',
                orders: orders,
            },
            success: function (response) {
                if (response.success === true) 
				{
                    jQuery.colorbox({
                        html: '<h3>Акт успешно создан,</h3><h4>присвоенный номер: '+response.data+'</h4>',
                        close: 'Закрыть'
                    });
                } else {
                    jQuery.colorbox({
                        html: '<h3>Ошибка при создании акта</h3><h4>'+response.data+'</h4>',
                        close: 'Закрыть'
                    });
                }
				jQuery('.invoices-table-wrapper').addClass('loading');
                jQuery('.invoices-table input[type="checkbox"]').prop('checked', false);
            }
        });
    }
	
    // Показать заказ для редактирования
    function editOrderEvent() {
        if (jQuery('.edit-order a').length > 0) {
            window.toReloadOrder = true;
            jQuery('.edit-order a').not('[href*="void"]').colorbox({
                iframe: true,
                width: '80%',
                height: '80%',
                close: 'Закрыть'
            })
        }
    }
    editOrderEvent();

    // Обновить список
    jQuery(document).bind('cbox_closed', function(){
        if (window.toReloadOrder !== true) return true;

        jQuery.ajax({
            url: '',
            beforeSend: function () {
				jQuery('.invoices-table-wrapper').addClass('loading');
            },
            success: function(response) {
                let data = jQuery.parseHTML(response, false, false)
                let table = jQuery(data).find('.invoices-table')
                jQuery('.invoices-table tbody').replaceWith(table.find('tbody'))
                editOrderEvent()
            },
            complete: function () {
				jQuery('.invoices-table-wrapper').removeClass('loading');
            }
        })
        window.toReloadOrder = false
    });
});