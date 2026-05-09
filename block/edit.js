import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { useEffect, useRef } from '@wordpress/element';
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

/**
 * Replace each `<time data-pwb-localtime>` text inside `root` with its locale-
 * formatted equivalent.
 *
 * Mirrors the inline script in `weather-aside.php` byte-for-byte intentionally:
 * the front-end runs the inline script directly, the editor runs *this* copy
 * because ServerSideRender injects its response via `innerHTML` and per the
 * HTML spec, scripts set via innerHTML never execute. Keep the two formatters
 * in sync — same hour-cycle, same field options.
 *
 * @param {HTMLElement|null} root Container to scan.
 */
function localizeTimes( root ) {
	if ( ! root ) {
		return;
	}
	root.querySelectorAll( '[data-pwb-localtime]' ).forEach( ( node ) => {
		const iso = node.getAttribute( 'datetime' );
		if ( ! iso ) {
			return;
		}
		const d = new Date( iso );
		if ( Number.isNaN( d.getTime() ) ) {
			return;
		}
		node.textContent = d.toLocaleTimeString( [], {
			hour: 'numeric',
			minute: '2-digit',
		} );
	} );
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

	/*
	 * ServerSideRender renders into the wrapper below by setting innerHTML on
	 * its host element. Two consequences:
	 *   1. The inline `<script>` from `weather-aside.php` is delivered but
	 *      never runs (innerHTML-set scripts don't execute).
	 *   2. The HTML lands *after* the React commit, then changes again on
	 *      every attribute tweak — so we can't run the swap once on mount.
	 *
	 * A MutationObserver scoped to the SSR wrapper handles both: it fires on
	 * the initial paint and on every subsequent SSR refresh, and we call the
	 * formatter against a contained subtree (no global DOM walks).
	 */
	const ssrRef = useRef( null );
	useEffect( () => {
		const root = ssrRef.current;
		if ( ! root ) {
			return undefined;
		}
		/*
		 * Pause-mutate-resume avoids an infinite loop: localizeTimes() writes
		 * `textContent`, which fires childList on the same subtree we observe
		 * — without disconnecting first, the observer's own callback re-
		 * triggers itself and the editor hangs hard. Reconnecting after each
		 * pass keeps us reactive to the next genuine SSR refresh.
		 */
		const observer = new window.MutationObserver( () => {
			observer.disconnect();
			localizeTimes( root );
			observer.observe( root, { childList: true, subtree: true } );
		} );
		localizeTimes( root );
		observer.observe( root, { childList: true, subtree: true } );
		return () => observer.disconnect();
	}, [] );

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
				<div ref={ ssrRef }>
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
				</div>
			) }
		</div>
	);
}
