/**
 * External dependencies
 */
import { CheckboxControl } from '@wordpress/components';
import classnames from 'classnames';

/**
 * List component showing the option tree.
 *
 * @param {Object} props The component props
 * @param {Option[]} props.options The component options
 * @param {string[]} props.selected Array of selected keys
 * @param {Function} props.onChange Callback when the options change
 * @return {JSX.Element} The component to be rendered
 */
const List = ( { options = [], selected = [], onChange = () => {} } ) => {
	return (
		<div
			className="woocommerce-tree-select-control__listbox"
			role="listbox"
			tabIndex="-1"
		>
			<Options
				options={ options }
				selected={ selected }
				onChange={ onChange }
			/>
		</div>
	);
};

const Options = ( { options = [], selected, parent = '', onChange } ) => {
	/**
	 * Returns true if all the children for the parent are selected
	 *
	 * @param {Option} option The parent option to check
	 */
	const isParentSelected = ( option ) => {
		if ( ! option.children ) {
			return false;
		}

		return option.children.every(
			( child ) =>
				selected.includes( child.id ) || isParentSelected( child )
		);
	};

	const hasChildren = ( option ) => !! option.children?.length;

	return options.map( ( option ) => {
		return (
			<div
				key={ `${ parent }-${ option.id }` }
				className="woocommerce-tree-select-control__group"
			>
				<CheckboxControl
					value={ option.id }
					className={ classnames(
						'woocommerce-tree-select-control__option',
						'woocommerce-tree-select-control__parent'
					) }
					label={ option.name }
					checked={
						selected.includes( option.id ) ||
						isParentSelected( option )
					}
					onChange={ ( checked ) => {
						onChange( checked, option );
					} }
				/>

				{ hasChildren( option ) && (
					<div className="woocommerce-tree-select-control__children">
						<Options
							parent={ option.id }
							options={ option.children }
							onChange={ onChange }
							selected={ selected }
						/>
					</div>
				) }
			</div>
		);
	} );
};

export default List;
