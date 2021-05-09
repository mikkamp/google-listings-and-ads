/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import {
	CheckboxControl,
	Button,
	Card,
	CardHeader,
	CardBody,
	CardFooter,
	__experimentalText as Text,
} from '@wordpress/components';
import {
	EmptyTable,
	Pagination,
	Table,
	TablePlaceholder,
} from '@woocommerce/components';
import classnames from 'classnames';

/**
 * Internal dependencies
 */
import AppTableCardDiv from '.~/components/app-table-card-div';
import EditProductLink from '.~/components/edit-product-link';
import './index.scss';
import useAppSelectDispatch from '.~/hooks/useAppSelectDispatch';
import statusLabelMap from './statusLabelMap';

const PER_PAGE = 10;

/**
 * Product Feed table.
 */
const ProductFeedTableCard = () => {
	const [ selectedRows, setSelectedRows ] = useState( new Set() );
	const [ query, setQuery ] = useState( {
		page: 1,
		per_page: PER_PAGE,
		orderby: 'title',
		order: 'asc',
	} );
	const { hasFinishedResolution, data } = useAppSelectDispatch(
		'getMCProductFeed',
		query
	);

	// TODO: what happens upon clicking the Edit Visibility button.
	const handleEditVisibilityClick = () => {};

	const handleSelectAllCheckboxChange = ( checked ) => {
		if ( checked ) {
			const ids = data?.products.map( ( el ) => el.id );
			setSelectedRows( new Set( [ ...ids ] ) );
		} else {
			setSelectedRows( new Set() );
		}
	};
	const getHandleSelectRowCheckboxChange = ( productId ) => ( checked ) => {
		if ( checked ) {
			setSelectedRows( new Set( [ ...selectedRows, productId ] ) );
		} else {
			selectedRows.delete( productId );
			setSelectedRows( new Set( selectedRows ) );
		}
	};

	const handlePageChange = ( newPage ) => {
		setQuery( {
			...query,
			page: newPage,
		} );
	};

	const handleSort = ( orderby, order ) => {
		setQuery( {
			...query,
			orderby,
			order,
		} );
	};

	const headers = [
		{
			key: 'select',
			label: (
				<CheckboxControl
					disabled={ ! data?.products }
					checked={
						data?.products?.length > 0 &&
						data?.products?.every( ( el ) =>
							selectedRows.has( el.id )
						)
					}
					onChange={ handleSelectAllCheckboxChange }
				/>
			),
			isLeftAligned: true,
			required: true,
		},
		{
			key: 'title',
			label: __( 'Product Title', 'google-listings-and-ads' ),
			isLeftAligned: true,
			required: true,
			isSortable: true,
		},
		{
			key: 'visible',
			label: __( 'Channel Visibility', 'google-listings-and-ads' ),
			isLeftAligned: true,
			isSortable: true,
		},
		{
			key: 'status',
			label: __( 'Status', 'google-listings-and-ads' ),
			isLeftAligned: true,
			isSortable: true,
		},
		{ key: 'action', label: '', required: true },
	];

	const actions = (
		<Button
			isSecondary
			disabled={ selectedRows.size === 0 }
			title={ __(
				'Select one or more products',
				'google-listings-and-ads'
			) }
			onClick={ handleEditVisibilityClick }
		>
			{ __( 'Edit channel visibility', 'google-listings-and-ads' ) }
		</Button>
	);

	return (
		<AppTableCardDiv className="gla-product-feed-table-card">
			<Card
				className={ classnames( 'woocommerce-table', {
					'has-actions': !! actions,
				} ) }
			>
				<CardHeader>
					{ /* We use this Text component to make it similar to TableCard component. */ }
					<Text variant="title.small" as="h2">
						{ __( 'Product Feed', 'google-listings-and-ads' ) }
					</Text>
					{ /* This is also similar to TableCard component implementation. */ }
					<div className="woocommerce-table__actions">
						{ actions }
					</div>
				</CardHeader>
				<CardBody size={ null }>
					{ ! hasFinishedResolution && (
						<TablePlaceholder
							headers={ headers }
							numberOfRows={ query.per_page }
						/>
					) }
					{ hasFinishedResolution && ! data && (
						<EmptyTable headers={ headers } numberOfRows={ 1 }>
							{ __(
								'An error occurred while retrieving products. Please try again later.',
								'google-listings-and-ads'
							) }
						</EmptyTable>
					) }
					{ hasFinishedResolution && data && (
						<Table
							headers={ headers }
							rows={ data.products.map( ( el ) => {
								return [
									{
										display: (
											<CheckboxControl
												checked={ selectedRows.has(
													el.id
												) }
												onChange={ getHandleSelectRowCheckboxChange(
													el.id
												) }
											/>
										),
									},
									{ display: el.title },
									{
										display: el.visible
											? __(
													'Sync and show',
													'google-listings-and-ads'
											  )
											: __(
													`Don't sync and show`,
													'google-listings-and-ads'
											  ),
									},
									{
										display: statusLabelMap[ el.status ],
									},
									{
										display: (
											<EditProductLink
												productId={ el.id }
											/>
										),
									},
								];
							} ) }
							query={ query }
							onSort={ handleSort }
						/>
					) }
				</CardBody>
				<CardFooter justify="center">
					<Pagination
						page={ query.page }
						perPage={ query.per_page }
						total={ data?.total }
						showPagePicker={ true }
						showPerPagePicker={ false }
						onPageChange={ handlePageChange }
					/>
				</CardFooter>
			</Card>
		</AppTableCardDiv>
	);
};

export default ProductFeedTableCard;
