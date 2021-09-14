jQuery(document).ready(function () {


	// Изменение адреса
    jQuery('#wpwrap').on('change', '#order_data #_shipping_address_1', function () {
		if (jQuery('#LPost_WC_MetaBox #courier_coords').length)
		{
			jQuery('#LPost_WC_MetaBox #courier_coords').val('');
		}
    })
	
	
    /*создание накладной*/
    jQuery('.invoices-table').on('click', '[data-action="create-invoice"]', function () {
        let orderID = this.dataset.orderid
        let shipment_id = this.dataset.shipment_id
        jQuery.ajax({
            beforeSend: function() {
                jQuery('.invoices-table').block({message: ''})
            },
            url: ajaxurl,
            data: {
                action: 'createInvoice',
                orderID: orderID,
                shipment_id: shipment_id,
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
                console.info(response);
                jQuery('.invoices-table').unblock()
            }
        })
    })
    /*создание накладной*/

    /*редактирование*/
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
    /*редактирование*/

    // Вариант отправки товара
    /*jQuery('body').on('change', '[name="woocommerce_lpost_wc_shipping_warehouse_deliv_type"]', function (event) {
        if (this.value === 'courier') {
            jQuery('[name="woocommerce_lpost_wc_shipping_receive_id_warehouse"]').val(3).trigger('change')
        }
        //console.info(this, this.value)
    });*/

    jQuery('body').on('change', '[name="deliv_type"]', function (event) {
        checkDelivTypeField()
        //console.info(this, this.value)
    });

    function checkDelivTypeField() {
        let coordField = jQuery('#courier_coords_field');
        let dateField = jQuery('#delivery_date_field');
        let timeField = jQuery('#delivery_interval_field');
        let delivTypeField = jQuery('#deliv_type');

        if (delivTypeField.val() === 'courier') {
            jQuery('[name="receive_id_warehouse"]').val(3).trigger('change');
            coordField.slideDown(300);
            dateField.slideDown(300).find('input').attr('required', 'required');
            timeField.slideDown(300).attr('required', 'required');
        } else {
            coordField.slideUp(100).removeAttr('required');
            dateField.slideUp(100).find('input').removeAttr('required');
            timeField.slideUp(100).removeAttr('required');
        }
    }

    checkDelivTypeField();
    /*Вариант отправки товара*/

    /*обновление заказа*/
    /*jQuery(document).bind('cbox_complete', function(){});*/
    jQuery(document).bind('cbox_closed', function(){
        if (window.toReloadOrder !== true) return true;

        jQuery.ajax({
            url: '',
            beforeSend: function () {
                jQuery('.invoices-table').block({
                    //message: 'Обновление...'
                    message: ''
                })
            },
            success: function(response) {
                let data = jQuery.parseHTML(response, false, false)
                let table = jQuery(data).find('.invoices-table')
                jQuery('.invoices-table tbody').replaceWith(table.find('tbody'))
                editOrderEvent()
            },
            complete: function () {
                jQuery('.invoices-table').unblock()
            }
        })
        window.toReloadOrder = false
    });
    /*обновление заказа*/
	
	// Обновление координат при изменении адреса доставки в заказе
	jQuery('body').on('change', '#_shipping_city,#_shipping_address_1', function (event) {
		var inpCity = jQuery('#_shipping_city'),
			inpAddress_1 = jQuery('#_shipping_address_1'),
			inpCoords = jQuery('#courier_coords'),
			address = '';
		if (inpAddress_1.length && inpCoords.length) 
		{
			inpCoords.val('');
			if (inpCity.length) address += inpCity.val() + ', ';
			address += inpAddress_1.val();
			ymaps.geocode(address, {
				results: 1
			}).then(function (res) {
				var coords = res.geoObjects.get(0).geometry.getCoordinates();
				inpCoords.val(coords[0]+','+coords[1]);
			});
		}
    });

});