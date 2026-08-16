( function ( $ ) {
	'use_strict';

	let events = [],
		qtipDescription,
		initialLocaleCode = WCFM_RNB_CALENDAR.lang_domain ? WCFM_RNB_CALENDAR.lang_domain : 'en',
		dayOfWeekStart = WCFM_RNB_CALENDAR.day_of_week_start ? WCFM_RNB_CALENDAR.day_of_week_start : 0,
		calendrData = WCFM_RNB_CALENDAR.calendar_data ? WCFM_RNB_CALENDAR.calendar_data : {};

	for ( let key in calendrData ) {
		events.push( calendrData[key] );
	}

	let calendarEl = document.getElementById( 'wcfm-rental-calendar' );

	function handleDatesRender( arg ) {
		console.log( 'viewType:', arg.view.calendar.state.viewType );
	}
	let calendar = new FullCalendar.Calendar( calendarEl, {
		plugins: ['dayGrid', 'timeGrid', 'list'],
		defaultView: 'dayGridMonth',
		datesRender: handleDatesRender,
		header: {
			left: 'prev,next today',
			center: 'title',
			right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek',
		},
		locale: initialLocaleCode,
		firstDay: dayOfWeekStart,
		// displayEventTime: false,
		eventRender: function ( info ) { },
		events: events,
		eventClick: function ( info ) {
			info.jsEvent.preventDefault();
			$( '#eventProduct' ).html( info.event.title );
			$( '#eventProduct' ).attr( 'href', info.event.extendedProps.link );
			$( '#eventInfo' ).html( info.event.extendedProps.description );
			$( '#eventLink' ).attr( 'href', info.event.url );
			$.magnificPopup.open( {
				items: {
					src: '#eventContent',
					type: 'inline',
				},
			} );
		},
	} );
	calendar.render();
} )( jQuery );
