jQuery(document).ready(function () {

	var inpCoords = jQuery('[name="courier_coords"]'),
		inpCity = (jQuery('[name="shipping_city"]').is(':visible') ? jQuery('[name="shipping_city"]') : jQuery('[name="billing_city"]')),
		inpAddress = (jQuery('[name="shipping_address_1"]').is(':visible') ? jQuery('[name="shipping_address_1"]') : jQuery('[name="billing_address_1"]')),
		boxCourierFields = jQuery('#lp_courier_checkout_field'),
		inpCourierFields = boxCourierFields.find('[name]');
	
    jQuery('body').on('updated_checkout', function(event, data) {
		var isShowBoxCourierFields = false;
		if (jQuery('#shipping_method [data-deliv_type]').length > 0) {
			if ('courier' == jQuery('#shipping_method [data-deliv_type]').data('deliv_type')){
				isShowBoxCourierFields = true;
			}
		}
		boxCourierFields.css({'display': (isShowBoxCourierFields?'block':'none')});
		inpCourierFields.prop('disabled', !isShowBoxCourierFields);

		
        if (jQuery('[name="ID_PickupPoint"]').length > 0) {
            inpAddress.attr('readonly', 'readonly');
        } else {
            inpAddress.removeAttr('readonly');
        }
        if (jQuery('#colorbox').is(':visible')) return true;
        //jQuery('.ch-pickup-pont').trigger('click');
    });
	
	// При изменении города, очищать адрес
    jQuery('[name*="_city"]').on('change', function (event) {
		if (jQuery(event.target).attr('name') === inpCity.attr('name'))
		{
			inpAddress.val('');
			inpCoords.val('');
		}
		// перерасчет при заполнении города
		jQuery('body').trigger('update_checkout');
    });
	
	// При изменении адреса, получать новые координаты
    jQuery('[name*="address_1"]').on('change', function (event) {
		if (jQuery(event.target).attr('name') === inpAddress.attr('name'))
		{
			var address = inpCity.val()+' '+inpAddress.val();
			inpCoords.val('');			
			ymaps.geocode(address, {
				results: 1
			}).then(function (res) {
				var coords = res.geoObjects.get(0).geometry.getCoordinates();
				inpCoords.val(coords[0]+','+coords[1]);
				let startCheck = processCourierPlacemarkMove('', true);
				if (startCheck) {
					createCourierPlacemark(coords);
				} else {
					inpAddress.attr('value', '');
				}
			})
		}
    });

    // выбор пункта самовывоза на карте 
    jQuery('body').on('click', '.choose-ppoint', function () {
        let addressText = 'Самовывоз: ' + this.dataset.address;
		inpAddress.val(addressText);
        jQuery('[name="ID_PickupPoint"]').val(this.dataset.pickuppoint);

        window.noFboxOpen = true;
        jQuery('body').trigger('update_checkout');
        jQuery.colorbox.close();
    })

    jQuery('body').on('click', '.ch-pickup-pont', function () {
        let deliv_type = this.dataset.deliv_type;
        let mapContainerTitle = "map-container-" + deliv_type;
        let mapPoints = window['pickupPoints' + deliv_type];

        ymaps.ready(function(){
            // Указывается идентификатор HTML-элемента.
            window.pickupMap = new ymaps.Map(jQuery('#' + mapContainerTitle).find('.map-element').get(0), {
                center: [55.75, 37.62],
                zoom: 12,
                controls: ['fullscreenControl', 'typeSelector', 'zoomControl']
            });

            pickupMap.controls.get('zoomControl').options.set('position', {left: 'auto', right: 10, top: 108})

            if (deliv_type === 'pickup') {
                jQuery('#' + mapContainerTitle).append('<div class="pickup-list"><div class="pickup-search"><input type="text" name="pickup-search" placeholder="Найти по улице или станции метро"/></div><div class="list-holder"><ul>')
                if (jQuery(window).width() > 767) pickupMap.margin.setDefaultMargin([20, 20, 20, 330])
            } else {}

            var ppCounter = 0;

            jQuery(mapPoints).each(function (index, el) {
                if (el.IsCourier == 0) { //самовывоз
                    let paymentTextArr = [];
                    let cardInfo = (el.IsCard === 1) ? 'Да' : 'Нет';
                    let cashInfo = (el.IsCash === 1) ? 'Да' : 'Нет';
                    let workHours = '<div>Время работы:</div>';
                    let currNIndex = ppCounter
                    let metroString = (el.Metro !== undefined) ? '<div class="pickup-metro">Метро: ' + el.Metro + '</div>' : ''

                    var startHours = el.SimpleWorkHours[0].From + ' - ' + el.SimpleWorkHours[0].To
                    var startDay = 'пн';

                    //console.info(el.SimpleWorkHours)
                    jQuery.each(el.SimpleWorkHours, function (indexInfo, dayInfo) {
                        let dayStart = dayInfo.From
                        let dayEnd = dayInfo.To
                        let currHours = dayInfo.From + ' - ' + dayInfo.To
                        let currDay = dayInfo.shortTitle
                        let actDay = currDay;

                        if (currHours !== startHours || indexInfo == 6) {
                            //console.info(currDay, currHours, dayInfo)
                            if (currDay !== startDay) actDay = startDay + '-' + currDay

                            workHours += '<div class="day">' + actDay + ': ' + currHours + '</div>'
                            startDay = currDay
                            startHours = currHours
                        }
                    }) //время работы

                    var myPlacemark = new ymaps.Placemark([el.Latitude, el.Longitude], {
                        balloonContentHeader: el.Address,
                        balloonContentBody: '<div class="extra-info">' +
                            metroString +
                            '<div class="payment-card-info">Оплата картой: '+ cardInfo + '</div>' +
                            '<div class="payment-cash-info">Оплата наличными: '+ cashInfo + '</div>' +
                            '<div class="workhours-info">'+ workHours + '</div>' +
                            '<div class="ex-info">'+ el.PickupDop + '</div>' +
                            '<div>',
                        balloonContentFooter: '<a href="javascript:void(0)" data-index="' + currNIndex + '" data-pickuppoint="' + el.ID_PickupPoint + '" data-address="' + el.Address + '" class="choose-ppoint btn pp-btn">Заберу отсюда</a>'
                    },{
						preset: 'islands#nightDotIcon'
					});

                    if (el.IsCard === 1) paymentTextArr.push('картой')
                    if (el.IsCash === 1) paymentTextArr.push('наличными')

                    jQuery('.pickup-list ul').append(
                        '<li>' +
                            '<a>' +
                                '<div class="pickup-item-title">' + el.CityName + ', ' + el.Address + '</div>' +
                                metroString +
                                '<div class="pickup-delivery-info">Доставка: ' + el.DeliveryDate + '</div>' +
                                '<div class="pickup-payment">Оплата: ' + paymentTextArr.join(' или ') + '</div>' +
                                '<div class="hidden-block">' +
                                    '<div class="time-block"> ' + workHours + ' </div>' +
                                    '<div class="link-holder"><span class="pp-btn">Заберу отсюда</span></div>' +
                                    '<div class="pp-info">'+ el.PickupDop + '</div>'
                    );

                    myPlacemark.events.add('click', function (e) {
                        jQuery('.pickup-list li').eq(currNIndex).find('a').trigger('click')
                        //console.info(e, currNIndex)
                    })
                    pickupMap.geoObjects.add(myPlacemark); //самовывоз
                    ppCounter++
                } 
				else 
				{ 
					// курьер
                    let polygons = [];
                    let exZone = false;
                    window.zoneGeoObjects = [];

                    jQuery(el.Zone).each(function (index, zonearea) {
						// Зона возможной доставки 
                        //if (zonearea.WKT.GeometryType != 'MultiPolygon') return true
                        if (zonearea.WKT.GeometryType != 'Polygon' || exZone === true) return true
                        let zoneGeoObject = new ymaps.Polygon(
                            zonearea.WKT.Coordinates, {}, {
                                fillColor: '#00FF00',
                                strokeColor: '#0000FF',
                                opacity: 0.2,
                                strokeWidth: 0,
                                visible: false
                            }
                        )
                        window.zoneGeoObjects.push(zoneGeoObject)
                        pickupMap.geoObjects.add(zoneGeoObject);
                        pickupMap.setBounds(zoneGeoObject.geometry.getBounds(), {
							useMapMargin: true
						});
                        //exZone = true;
                    }); // перебор точек
                }
            });

            if (deliv_type === 'courier') { // действия для курьера
                pickupMap.events.add('click', function (e) {
                    if (typeof courierPlacemark === 'undefined') createCourierPlacemark(e.get('coords'), true);
                    courierPlacemark.geometry.setCoordinates(e.get('coords'));
                    processCourierPlacemarkMove(e.get('coords'));
                    //console.info(e.get('coords'))
                });

                //var startCheck = processCourierPlacemarkMove('', true);
				var startCheck = false;
				var address = (inpCity.length ? inpCity.val() : '') + ' ' +jQuery(inpAddress).val();

                if (inpCoords.val()) {
                    startCheck = createCourierPlacemark(JSON.parse('['+inpCoords.val()+']'));
				}
                if (!startCheck) {
                    ymaps.geocode(address, {
                        results: 1
                    }).then(function (res) {
                        var coords = res.geoObjects.get(0).geometry.getCoordinates();
						inpCoords.val(coords[0]+','+coords[1]);
                        if (processCourierPlacemarkMove('', true)) {
                            createCourierPlacemark(coords);
                        } else {
							inpAddress.attr('value', '');
						}
                    })
                } // если есть координаты

                function createCourierPlacemark(coords, fromClean = false) {
                    window.courierPlacemark = new ymaps.Placemark(coords, {}, {
                        draggable: true,
						preset: 'islands#nightDotIcon'
                    });
                    pickupMap.geoObjects.add(courierPlacemark);
                    if (!fromClean) pickupMap.setBounds(pickupMap.geoObjects.getBounds())

                    courierPlacemark.events.add('dragend', function (event) {
                        processCourierPlacemarkMove(event.get('target').geometry.getCoordinates());
                    });

                    courierPlacemark.events.add('click', function (event) {
                        processCourierPlacemarkMove(event.get('coords'));
                    });
                }

                function processCourierPlacemarkMove(coords = '', startCheck = false) 
				{
                    if (startCheck && inpCoords.val() === '') return false;
					
					coords = (startCheck) ? JSON.parse('['+inpCoords.val()+']') : coords;
					if(!coords) 
					{
						return false;
					}
					
                    jQuery(window.zoneGeoObjects).each(function (index, zonePolygon) {
                        if (zonePolygon.geometry.contains(coords) === true) {
							// console.info('входит в зону')
                            jQuery('#map-container-courier .control-div button').removeAttr('disabled').attr('data-coords', coords[0]+','+coords[1]);
							jQuery('body').trigger('update_checkout');
                            return true;
                        } else {
                            jQuery('#map-container-courier .control-div button').attr('disabled', 'disabled');
                        }
                    });

                    return false;
				}
            } else {

            } //действия для курьера

           setTimeout(function () {
                pickupMap.setBounds(pickupMap.geoObjects.getBounds(), {
                    // checkZoomRange: true,
                    //zoomMargin: 20,
                    useMapMargin: true
                });

                if (typeof courierPlacemark !== 'undefined') pickupMap.setCenter(courierPlacemark.geometry.getCoordinates());
                //pickupMap.options.set('restrictMapArea', pickupMap.getBounds)
            }, 1000);


            jQuery.colorbox({
                'inline': true,
                'href': jQuery('#' + mapContainerTitle),
                'closeButton': false
            });

            /*jQuery(document).bind('cbox_complete', function(){
                //pickupMap.container.fitToViewport();
                if (typeof pickupMap === 'undefined') return true;

                pickupMap.setBounds(pickupMap.geoObjects.getBounds(), {
                    checkZoomRange: true,
                    useMapMargin: true
                    //zoomMargin: 20
                });

                setTimeout(function () {
                    //pickupMap.options.set('restrictMapArea', pickupMap.getBounds)
                }, 2000)
            }); //открытие*/

            jQuery(document).bind('cbox_closed', function(){
                pickupMap.destroy()
                delete courierPlacemark
            });
        });


    })
    /*карта*/

    /*нажать на элемент списка*/
    jQuery('body').on('click', '.pickup-list a', function (event) {
        //console.info(event)
        let parent = jQuery(this.parentElement)
        let elIndex = parent.index()
        let placeMark = pickupMap.geoObjects.get(elIndex)

        jQuery(this).closest('ul')
            .find('li')
            .not(parent)
            .find('.hidden-block')
            .slideUp(200)

        parent.find('.hidden-block')
            .slideToggle(300, function () {
                if (event.isTrigger) jQuery('.pickup-list .list-holder').animate({
                    scrollTop: parent.position().top
                }, 200)
            })

        if (event.isTrigger) return true;

        placeMark.balloon.open()
        pickupMap.setCenter(placeMark.geometry.getCoordinates(), pickupMap.getZoom(), {
            duration: 300,
            useMapMargin: true
        })
    })
    /*нажать на элемент списка*/

    /*доставить сюда*/
    jQuery('body').on('click', '.link-holder .pp-btn', function () {
        let parent = jQuery(this).closest('li')
        jQuery('a.choose-ppoint[data-index="' + parent.index() + '"]').trigger('click')
    })

    jQuery('body').on('click', '#map-container-courier .control-div button', function () {
        let coords = this.dataset.coords;
        inpCoords.val(coords);
        window.noFboxOpen = true;
		if (coords) {
			ymaps.geocode(JSON.parse('['+coords+']')).then(function (res) {
				let addressObj = res.geoObjects.get(0),
					addressCountry = addressObj.getCountry(),
					addressCity = addressObj.getLocalities()[0],
					addressText = addressObj.getAddressLine();
					
				addressText = addressText.replace(addressCountry+', ', '');
				addressText = addressText.replace(addressCity+', ', '');

				inpAddress.val(addressText);
			});
		}

        jQuery('body').trigger('update_checkout')
        jQuery.colorbox.close()
        //makeCalcRequest(coords)
    });

    function makeCalcRequest(coords) {
        jQuery.ajax({
            url: woocommerce_params.ajax_url,
            data: {
                action: 'lpcalcrequest',
                coords: coords
            }
        }) //запрос на пересчет
    }
    /*доставить сюда*/

    /*поиск по улицам*/
    jQuery('body').on('keyup', '[name="pickup-search"]', function (event) {
        var element = jQuery(this);
        if (this.value === '') {
            jQuery('.list-holder ul li').slideDown(200)
            return true
        }

        jQuery('.list-holder ul li').hide().each(function (index, el) {
            let itElement = jQuery(this)
            if (itElement.text().toUpperCase().includes(element.val().toUpperCase())) itElement.slideDown(200)
        })
    })
    /*поиск по улицам*/

})