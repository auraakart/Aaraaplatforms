(function ($) {
	'use strict';

	var _map = null;
	var _dirService  = null;
	var _dirRenderer = null;
	var _markers     = [];
	var _mapReady    = false;

	/* ── Map init ─────────────────────────────────────────────── */

	function initMapOnce() {
		if (_mapReady) return;
		var el = document.getElementById('wcfmd-route-map');
		if (!el || typeof google === 'undefined' || typeof google.maps === 'undefined') return;

		_map = new google.maps.Map(el, {
			zoom              : 13,
			center            : { lat: 13.0827, lng: 80.2707 },
			mapTypeControl    : false,
			streetViewControl : false,
			fullscreenControl : true,
			zoomControl       : true
		});

		_dirService  = new google.maps.DirectionsService();
		_dirRenderer = new google.maps.DirectionsRenderer({
			suppressMarkers : true,
			polylineOptions : {
				strokeColor  : '#1e3a8a',
				strokeWeight : 4,
				strokeOpacity: 0.8
			}
		});
		_dirRenderer.setMap(_map);
		_mapReady = true;
	}

	function clearMarkers() {
		_markers.forEach(function (m) { m.setMap(null); });
		_markers = [];
	}

	function addMarker(position, label, infoHtml, isStart) {
		var marker = new google.maps.Marker({
			position : position,
			map      : _map,
			label    : {
				text      : String(label),
				color     : '#ffffff',
				fontWeight: 'bold',
				fontSize  : '12px'
			},
			icon : {
				path        : google.maps.SymbolPath.CIRCLE,
				scale       : 15,
				fillColor   : isStart ? '#16a34a' : '#1e3a8a',
				fillOpacity : 1,
				strokeColor : '#ffffff',
				strokeWeight: 2.5
			},
			zIndex: isStart ? 10 : 5
		});

		if (infoHtml) {
			var iw = new google.maps.InfoWindow({ content: infoHtml, maxWidth: 260 });
			marker.addListener('click', function () { iw.open(_map, marker); });
		}
		_markers.push(marker);
		return marker;
	}

	function getWaypoint(delivery) {
		if (delivery.lat && delivery.lng) {
			return new google.maps.LatLng(parseFloat(delivery.lat), parseFloat(delivery.lng));
		}
		return delivery.address;
	}

	/* ── Route building ───────────────────────────────────────── */

	function buildRoute(deliveries, originLat, originLng) {
		var $sidebar = $('#wcfmd-route-sidebar');
		var $summary = $('#wcfmd-route-summary');

		clearMarkers();
		initMapOnce();

		if (!deliveries.length) {
			$sidebar.html('<p class="wcfmd-route-msg">No pending deliveries found.</p>');
			$summary.html('');
			return;
		}

		/* Place origin marker and center map */
		if (originLat && originLng) {
			_map.setCenter({ lat: originLat, lng: originLng });
			addMarker({ lat: originLat, lng: originLng }, '★', '<b>Your current location</b>', true);
		}

		/* Single delivery: no routing needed */
		if (deliveries.length === 1) {
			var d = deliveries[0];
			if (d.lat && d.lng) {
				var pos = { lat: parseFloat(d.lat), lng: parseFloat(d.lng) };
				addMarker(pos, 1, infoHtml(d), false);
				if (!originLat) _map.setCenter(pos);
			}
			renderSidebar([d], $sidebar);
			renderSummary([d], 0, 0, originLat, originLng, $summary);
			return;
		}

		/* Determine origin/destination/waypoints */
		var originLoc, destDelivery, wpDeliveries;

		if (originLat && originLng) {
			originLoc    = new google.maps.LatLng(originLat, originLng);
			destDelivery = deliveries[deliveries.length - 1];
			wpDeliveries = deliveries.slice(0, -1);
		} else {
			originLoc    = getWaypoint(deliveries[0]);
			destDelivery = deliveries[deliveries.length - 1];
			wpDeliveries = deliveries.slice(1, -1);
		}

		var waypoints = wpDeliveries.map(function (d) {
			return { location: getWaypoint(d), stopover: true };
		});

		$sidebar.html('<p class="wcfmd-route-msg">Calculating optimal route…</p>');

		_dirService.route({
			origin            : originLoc,
			destination       : getWaypoint(destDelivery),
			waypoints         : waypoints,
			optimizeWaypoints : true,
			travelMode        : google.maps.TravelMode.DRIVING
		}, function (result, status) {
			if (status !== google.maps.DirectionsStatus.OK) {
				$sidebar.html('<p class="wcfmd-route-msg wcfmd-route-err">Route error: ' + status + '. Check address data.</p>');
				return;
			}

			_dirRenderer.setDirections(result);

			var route         = result.routes[0];
			var optimizedIdx  = route.waypoint_order; // reordered indices into wpDeliveries

			/* Reconstruct ordered stop list */
			var orderedStops = [];
			if (!originLat || !originLng) {
				orderedStops.push(deliveries[0]); // first delivery was the origin
			}
			optimizedIdx.forEach(function (i) {
				orderedStops.push(wpDeliveries[i]);
			});
			orderedStops.push(destDelivery);

			/* Numbered markers */
			orderedStops.forEach(function (d, idx) {
				if (d.lat && d.lng) {
					addMarker(
						{ lat: parseFloat(d.lat), lng: parseFloat(d.lng) },
						idx + 1,
						infoHtml(d),
						false
					);
				}
			});

			/* Totals from route legs */
			var totalDist = 0, totalSecs = 0;
			route.legs.forEach(function (leg) {
				totalDist += leg.distance.value;
				totalSecs += leg.duration.value;
			});

			renderSidebar(orderedStops, $sidebar);
			renderSummary(orderedStops, totalDist, totalSecs, originLat, originLng, $summary);
		});
	}

	function itemsText(d) {
		if (!d.items || !d.items.length) return '';
		return d.items.map(function (it) {
			return it.qty + ' x ' + it.name;
		}).join(', ');
	}

	function infoHtml(d) {
		var items = itemsText(d);
		return '<div style="line-height:1.5">'
			+ '<strong>Order #' + escHtml(d.order_number) + '</strong><br>'
			+ escHtml(d.customer_name)
			+ (items ? '<br><small style="color:#1e3a8a">(' + escHtml(items) + ')</small>' : '')
			+ (d.phone ? '<br><span style="color:#555">📞 ' + escHtml(d.phone) + '</span>' : '')
			+ '<br><small style="color:#666">' + escHtml(d.address) + '</small>'
			+ '</div>';
	}

	function renderSidebar(stops, $sidebar) {
		var html = '<div class="wcfmd-stops-list">';
		stops.forEach(function (d, idx) {
			var items = itemsText(d);
			html += '<div class="wcfmd-stop-item">'
				+ '<div class="wcfmd-stop-num">' + (idx + 1) + '</div>'
				+ '<div class="wcfmd-stop-body">'
				+ '<div class="wcfmd-stop-name">' + escHtml(d.customer_name) + '</div>'
				+ '<div class="wcfmd-stop-order">Order #' + escHtml(d.order_number) + '</div>'
				+ (items ? '<div class="wcfmd-stop-items">(' + escHtml(items) + ')</div>' : '')
				+ '<div class="wcfmd-stop-addr">' + escHtml(d.address) + '</div>'
				+ (d.phone ? '<div class="wcfmd-stop-phone"><i class="wcfmfa fa-phone"></i> <a href="tel:' + escHtml(d.phone) + '" style="color:#374151;text-decoration:none;">' + escHtml(d.phone) + '</a></div>' : '')
				+ '</div></div>';
		});
		html += '</div>';
		$sidebar.html(html);
	}

	function renderSummary(stops, totalDist, totalSecs, originLat, originLng, $summary) {
		var km    = totalDist ? (totalDist / 1000).toFixed(1) + ' km' : '—';
		var mins  = totalSecs ? Math.round(totalSecs / 60) : 0;
		var hrs   = Math.floor(mins / 60);
		var rem   = mins % 60;
		var dur   = totalSecs ? (hrs > 0 ? hrs + 'h ' + rem + 'min' : mins + ' min') : '—';

		var html = '<div class="wcfmd-route-stats">'
			+ statBlock('fa-road',          km,                  'Total Distance')
			+ statBlock('fa-clock',         dur,                 'Est. Duration')
			+ statBlock('fa-map-marker-alt', stops.length + ' stops', 'Deliveries')
			+ '</div>';

		/* Google Maps navigation link */
		var navUrl = 'https://www.google.com/maps/dir/';
		if (originLat && originLng) navUrl += originLat + ',' + originLng + '/';
		stops.forEach(function (d) {
			navUrl += encodeURIComponent(d.address) + '/';
		});

		html += '<div class="wcfmd-nav-wrap">'
			+ '<a href="' + navUrl + '" target="_blank" class="wcfmd-nav-btn">'
			+ '<i class="wcfmfa fa-directions"></i> Open in Google Maps Navigation'
			+ '</a></div>';

		$summary.html(html);
	}

	function statBlock(icon, value, label) {
		return '<div class="wcfmd-route-stat">'
			+ '<i class="wcfmfa ' + icon + '"></i>'
			+ '<strong>' + value + '</strong>'
			+ '<span>' + label + '</span>'
			+ '</div>';
	}

	function escHtml(str) {
		return $('<div>').text(str || '').html();
	}

	/* ── Main flow ────────────────────────────────────────────── */

	function runOptimizer() {
		var $btn       = $('#wcfmd-optimize-btn');
		var $sidebar   = $('#wcfmd-route-sidebar');
		var $summary   = $('#wcfmd-route-summary');
		var deliveryId = $('#wcfm_delivery_boy_id').val();

		if (!deliveryId) {
			$sidebar.html('<p class="wcfmd-route-msg wcfmd-route-err">Could not determine delivery person.</p>');
			return;
		}

		$btn.prop('disabled', true).html('<i class="wcfmfa fa-spinner fa-spin"></i> Loading…');
		$sidebar.html('<p class="wcfmd-route-msg">Fetching pending deliveries…</p>');
		$summary.html('');

		function fetchDeliveries(lat, lng) {
			$.post(wcfm_params.ajax_url, {
				action          : 'wcfmd_get_route_deliveries',
				delivery_boy_id : deliveryId,
				wcfm_ajax_nonce : wcfm_params.wcfm_ajax_nonce
			}, function (resp) {
				$btn.prop('disabled', false).html('<i class="wcfmfa fa-route"></i> Optimize Route');
				if (!resp.success) {
					$sidebar.html('<p class="wcfmd-route-msg wcfmd-route-err">Failed to load deliveries.</p>');
					return;
				}
				buildRoute(resp.data, lat, lng);
			}).fail(function () {
				$btn.prop('disabled', false).html('<i class="wcfmfa fa-route"></i> Optimize Route');
				$sidebar.html('<p class="wcfmd-route-msg wcfmd-route-err">Network error. Please try again.</p>');
			});
		}

		if (navigator.geolocation) {
			navigator.geolocation.getCurrentPosition(
				function (pos) { fetchDeliveries(pos.coords.latitude, pos.coords.longitude); },
				function ()    { fetchDeliveries(null, null); },
				{ timeout: 8000, enableHighAccuracy: true }
			);
		} else {
			fetchDeliveries(null, null);
		}
	}

	/* ── DOM ready ────────────────────────────────────────────── */

	$(document).ready(function () {

		/* Toggle route optimizer panel */
		$(document).on('click', '#wcfmd-route-toggle', function () {
			var $body = $('#wcfmd-route-body');
			var $icon = $(this).find('.wcfmd-toggle-arrow');
			$body.slideToggle(250, function () {
				if ($body.is(':visible') && typeof google !== 'undefined') {
					initMapOnce();
					/* Trigger map resize after expand */
					setTimeout(function () {
						if (_map) google.maps.event.trigger(_map, 'resize');
					}, 300);
				}
			});
			$icon.toggleClass('wcfmd-arrow-up');
		});

		/* Optimize button */
		$(document).on('click', '#wcfmd-optimize-btn', function (e) {
			e.preventDefault();
			if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
				alert('Google Maps is still loading. Please wait a moment and try again.');
				return;
			}
			initMapOnce();
			runOptimizer();
		});
	});

})(jQuery);
