import { __ } from '@wordpress/i18n';
import { ComboboxControl, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useState, useMemo } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';

/**
 * Searchable post picker. Wraps ComboboxControl with debounced search
 * against the core/data 'postType'/'post' resource.
 *
 * @param {Object}            props          Component props.
 * @param {string}            props.label    Field label.
 * @param {number}            props.value    Selected post id (0 for none).
 * @param {(id:number)=>void} props.onChange Called with the chosen post id.
 */
export default function PostPicker( { label, value, onChange } ) {
	const [ search, setSearch ] = useState( '' );

	const { posts, isResolving, selected } = useSelect(
		( select ) => {
			const args = { per_page: 10, status: 'publish' };
			if ( search ) {
				args.search = search;
			}
			const list =
				select( coreStore ).getEntityRecords(
					'postType',
					'post',
					args
				) || [];
			const isLoading = select( coreStore ).isResolving(
				'getEntityRecords',
				[ 'postType', 'post', args ]
			);
			const selectedRecord = value
				? select( coreStore ).getEntityRecord(
						'postType',
						'post',
						value
				  )
				: null;
			return {
				posts: list,
				isResolving: isLoading,
				selected: selectedRecord,
			};
		},
		[ search, value ]
	);

	const options = useMemo( () => {
		const base = posts.map( ( p ) => ( {
			label: decodeEntities(
				p.title?.rendered ||
					__( '(no title)', 'dtmg-posts-weather-block' )
			),
			value: p.id,
		} ) );
		if ( selected && ! base.some( ( o ) => o.value === selected.id ) ) {
			base.unshift( {
				label: decodeEntities(
					selected.title?.rendered ||
						__( '(no title)', 'dtmg-posts-weather-block' )
				),
				value: selected.id,
			} );
		}
		return base;
	}, [ posts, selected ] );

	return (
		<div className="dtmg-pwb-post-picker">
			<ComboboxControl
				label={ label }
				value={ value || null }
				options={ options }
				onChange={ ( id ) => onChange( id ? Number( id ) : 0 ) }
				onFilterValueChange={ setSearch }
				allowReset
				__nextHasNoMarginBottom
			/>
			{ isResolving && <Spinner /> }
		</div>
	);
}
