import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import metadata from './block.json';
import WeatherInspector from './components/WeatherInspector';

/**
 * Drop attribute keys whose value is `null`.
 *
 * `<ServerSideRender>` builds the block-renderer query via `addQueryArgs`,
 * which serializes `null` as an empty string (`attributes[latitude]=`). The
 * REST validator then rejects the empty string against the attribute's type
 * union (number|null). Omitting the key entirely lets the default from
 * block.json kick in instead.
 *
 * @param {Object} source Attribute map.
 * @return {Object} Copy of `source` minus any entries whose value is null.
 */
function stripNullAttributes( source ) {
	const out = {};
	for ( const [ key, value ] of Object.entries( source ) ) {
		if ( value !== null ) {
			out[ key ] = value;
		}
	}
	return out;
}

export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const { postIds, latitude, longitude } = attributes;

	const hasPosts =
		( postIds[ 0 ] && postIds[ 0 ] > 0 ) ||
		( postIds[ 1 ] && postIds[ 1 ] > 0 );
	const hasCoords =
		Number.isFinite( latitude ) && Number.isFinite( longitude );

	const renderAttributes = stripNullAttributes( attributes );

	return (
		<div { ...blockProps }>
			<WeatherInspector
				attributes={ attributes }
				setAttributes={ setAttributes }
			/>

			{ ! hasPosts && ! hasCoords ? (
				<Placeholder
					icon="cloud"
					label={ __(
						'Posts + Weather',
						'dtmg-posts-weather-block'
					) }
					instructions={ __(
						'Pick two posts and enter latitude and longitude in the sidebar to see the preview.',
						'dtmg-posts-weather-block'
					) }
				/>
			) : (
				<ServerSideRender
					block={ metadata.name }
					attributes={ renderAttributes }
					EmptyResponsePlaceholder={ () => (
						<Placeholder
							icon="cloud"
							label={ __(
								'Posts + Weather',
								'dtmg-posts-weather-block'
							) }
							instructions={ __(
								'Nothing to preview yet.',
								'dtmg-posts-weather-block'
							) }
						/>
					) }
				/>
			) }
		</div>
	);
}
