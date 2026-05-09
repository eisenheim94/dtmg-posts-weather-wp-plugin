import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis -- NumberControl is the only stable spinner-style numeric input; downgrade to TextControl loses spin buttons.
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import PostPicker from './PostPicker';

const FIELD_LABELS = {
	location: __( 'Location name', 'dtmg-posts-weather-block' ),
	temp: __( 'Temperature', 'dtmg-posts-weather-block' ),
	feelsLike: __( 'Feels-like temperature', 'dtmg-posts-weather-block' ),
	condition: __( 'Weather condition', 'dtmg-posts-weather-block' ),
	humidity: __( 'Humidity', 'dtmg-posts-weather-block' ),
	pressure: __( 'Pressure', 'dtmg-posts-weather-block' ),
	wind: __( 'Wind speed', 'dtmg-posts-weather-block' ),
	sunrise: __( 'Sunrise', 'dtmg-posts-weather-block' ),
	sunset: __( 'Sunset', 'dtmg-posts-weather-block' ),
};

export default function WeatherInspector( { attributes, setAttributes } ) {
	const { postIds, latitude, longitude, units, timeFormat, weatherFields } =
		attributes;
	// Defensive default: an older saved post may predate the units attribute.
	const currentUnits = units === 'imperial' ? 'imperial' : 'metric';
	// Same idea for timeFormat: legacy posts collapse to 'auto'.
	const currentTimeFormat =
		timeFormat === '12' || timeFormat === '24' ? timeFormat : 'auto';

	const setPostId = ( index, id ) => {
		const next = [ ...postIds ];
		next[ index ] = id;
		setAttributes( { postIds: next } );
	};

	const setField = ( key, value ) => {
		setAttributes( {
			weatherFields: { ...weatherFields, [ key ]: value },
		} );
	};

	const isValidLat =
		latitude === null || ( latitude >= -90 && latitude <= 90 );
	const isValidLon =
		longitude === null || ( longitude >= -180 && longitude <= 180 );

	return (
		<InspectorControls>
			<PanelBody
				title={ __( 'Posts', 'dtmg-posts-weather-block' ) }
				initialOpen
			>
				<PostPicker
					label={ __( 'Post 1', 'dtmg-posts-weather-block' ) }
					value={ postIds[ 0 ] || 0 }
					onChange={ ( id ) => setPostId( 0, id ) }
				/>
				<PostPicker
					label={ __( 'Post 2', 'dtmg-posts-weather-block' ) }
					value={ postIds[ 1 ] || 0 }
					onChange={ ( id ) => setPostId( 1, id ) }
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Weather location', 'dtmg-posts-weather-block' ) }
				initialOpen
			>
				<NumberControl
					label={ __( 'Latitude', 'dtmg-posts-weather-block' ) }
					value={ latitude ?? '' }
					min={ -90 }
					max={ 90 }
					step={ 0.0001 }
					onChange={ ( v ) =>
						setAttributes( {
							latitude: v === '' ? null : Number( v ),
						} )
					}
					__next40pxDefaultSize
				/>
				{ ! isValidLat && (
					<p className="dtmg-pwb-error">
						{ __(
							'Latitude must be between -90 and 90.',
							'dtmg-posts-weather-block'
						) }
					</p>
				) }
				<NumberControl
					label={ __( 'Longitude', 'dtmg-posts-weather-block' ) }
					value={ longitude ?? '' }
					min={ -180 }
					max={ 180 }
					step={ 0.0001 }
					onChange={ ( v ) =>
						setAttributes( {
							longitude: v === '' ? null : Number( v ),
						} )
					}
					__next40pxDefaultSize
				/>
				{ ! isValidLon && (
					<p className="dtmg-pwb-error">
						{ __(
							'Longitude must be between -180 and 180.',
							'dtmg-posts-weather-block'
						) }
					</p>
				) }
			</PanelBody>

			<PanelBody
				title={ __( 'Display', 'dtmg-posts-weather-block' ) }
				initialOpen
			>
				<SelectControl
					label={ __( 'Units', 'dtmg-posts-weather-block' ) }
					value={ currentUnits }
					options={ [
						{
							label: __(
								'Metric (°C, m/s)',
								'dtmg-posts-weather-block'
							),
							value: 'metric',
						},
						{
							label: __(
								'Imperial (°F, mph)',
								'dtmg-posts-weather-block'
							),
							value: 'imperial',
						},
					] }
					onChange={ ( v ) =>
						setAttributes( {
							units: v === 'imperial' ? 'imperial' : 'metric',
						} )
					}
					help={ __(
						'Conversion happens at render time; cached weather data stays metric.',
						'dtmg-posts-weather-block'
					) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>

				<SelectControl
					label={ __( 'Time format', 'dtmg-posts-weather-block' ) }
					value={ currentTimeFormat }
					options={ [
						{
							label: __(
								'Automatic — visitor’s locale',
								'dtmg-posts-weather-block'
							),
							value: 'auto',
						},
						{
							label: __(
								'12-hour (1:30 PM)',
								'dtmg-posts-weather-block'
							),
							value: '12',
						},
						{
							label: __(
								'24-hour (13:30)',
								'dtmg-posts-weather-block'
							),
							value: '24',
						},
					] }
					onChange={ ( v ) => {
						const next = v === '12' || v === '24' ? v : 'auto';
						setAttributes( { timeFormat: next } );
					} }
					help={ __(
						'Applies to sunrise and sunset. Automatic uses each visitor’s browser locale; an explicit choice is server-rendered for everyone.',
						'dtmg-posts-weather-block'
					) }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			</PanelBody>

			<PanelBody
				title={ __( 'Weather fields', 'dtmg-posts-weather-block' ) }
				initialOpen={ false }
			>
				{ Object.entries( FIELD_LABELS ).map( ( [ key, label ] ) => (
					<ToggleControl
						key={ key }
						label={ label }
						checked={ !! weatherFields[ key ] }
						onChange={ ( v ) => setField( key, v ) }
						__nextHasNoMarginBottom
					/>
				) ) }
			</PanelBody>
		</InspectorControls>
	);
}
