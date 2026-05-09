/**
 * Front-end behaviour for dtmg/posts-weather: refresh weather via REST.
 *
 * No build-time deps; runs after the block has been rendered server-side.
 */

document
	.querySelectorAll( '.wp-block-dtmg-posts-weather[data-lat]' )
	.forEach( initBlock );

function initBlock( el ) {
	const lat = parseFloat( el.dataset.lat );
	const lon = parseFloat( el.dataset.lon );
	if ( ! Number.isFinite( lat ) || ! Number.isFinite( lon ) ) {
		return;
	}

	const button = el.querySelector( '[data-pwb-refresh]' );
	if ( ! button ) {
		return;
	}

	button.addEventListener( 'click', () => refresh( el, lat, lon, button ) );
}

async function refresh( el, lat, lon, button ) {
	const root =
		( window.wpApiSettings && window.wpApiSettings.root ) || '/wp-json/';
	const url = `${ root }dtmg/v1/weather?lat=${ encodeURIComponent(
		lat
	) }&lon=${ encodeURIComponent( lon ) }`;

	button.disabled = true;
	try {
		const r = await fetch( url, {
			headers: { Accept: 'application/json' },
		} );
		if ( ! r.ok ) {
			return;
		}
		const data = await r.json();
		applyValues( el, data );
	} catch ( _err ) {
		/* Soft fail: leave the existing values in place. */
	} finally {
		button.disabled = false;
	}
}

function applyValues( el, data ) {
	set( el, 'location', data.location );
	set( el, 'temp', format( data.temp, '°C', 1 ) );
	set( el, 'feels_like', format( data.feels_like, '°C', 1 ) );
	set( el, 'description', cap( data.description ) );
	set( el, 'humidity', `${ data.humidity } %` );
	set( el, 'pressure', `${ data.pressure } hPa` );
	set( el, 'wind_speed', format( data.wind_speed, 'm/s', 1 ) );
	setTime( el, 'sunrise', data.sunrise );
	setTime( el, 'sunset', data.sunset );
	setConditionIcon( el, data.condition, data.icon );
}

/**
 * OWM condition + icon code → Lucide icon class. Mirrors the PHP helper
 * `dtmg_pwb_lucide_icon_for_condition()` in weather-aside.php — keep in sync.
 */
function lucideIconFor( condition, icon ) {
	const isNight = typeof icon === 'string' && icon.endsWith( 'n' );
	switch ( condition ) {
		case 'Clear':
			return isNight ? 'icon-moon' : 'icon-sun';
		case 'Clouds': {
			const prefix =
				typeof icon === 'string' ? icon.slice( 0, 2 ) : '';
			if ( prefix === '02' ) {
				return isNight ? 'icon-cloud-moon' : 'icon-cloud-sun';
			}
			return 'icon-cloudy';
		}
		case 'Rain':
			return 'icon-cloud-rain';
		case 'Drizzle':
			return 'icon-cloud-drizzle';
		case 'Thunderstorm':
			return 'icon-cloud-lightning';
		case 'Snow':
			return 'icon-cloud-snow';
		case 'Tornado':
			return 'icon-tornado';
		case 'Squall':
			return 'icon-wind';
		default:
			/* Mist, Smoke, Haze, Dust, Fog, Sand, Ash. */
			return 'icon-cloud-fog';
	}
}

function setConditionIcon( el, condition, icon ) {
	const node = el.querySelector( '[data-pwb-condition-icon]' );
	if ( ! node || typeof condition !== 'string' ) {
		return;
	}
	const next = lucideIconFor( condition, icon );
	/* Strip any existing `icon-*` class, then add the new one. */
	[ ...node.classList ]
		.filter( ( c ) => c.startsWith( 'icon-' ) )
		.forEach( ( c ) => node.classList.remove( c ) );
	node.classList.add( next );
}

function set( el, field, value ) {
	const node = el.querySelector( `[data-pwb-field="${ field }"]` );
	if ( node && value !== undefined ) {
		node.replaceChildren( document.createTextNode( String( value ) ) );
	}
}

function setTime( el, field, unix ) {
	if ( ! Number.isFinite( unix ) ) {
		return;
	}
	const d = new Date( unix * 1000 );
	const text = d.toLocaleTimeString( undefined, {
		hour: '2-digit',
		minute: '2-digit',
	} );
	const node = el.querySelector( `[data-pwb-field="${ field }"]` );
	if ( node ) {
		const time = document.createElement( 'time' );
		time.dateTime = d.toISOString();
		time.textContent = text;
		node.replaceChildren( time );
	}
}

function format( value, suffix, decimals ) {
	if ( typeof value !== 'number' ) {
		return '';
	}
	return `${ value.toFixed( decimals ) } ${ suffix }`;
}

function cap( s ) {
	return typeof s === 'string' && s.length
		? s.charAt( 0 ).toUpperCase() + s.slice( 1 )
		: s;
}
