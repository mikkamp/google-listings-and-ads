/**
 * External dependencies
 */
import '@testing-library/jest-dom';
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import ProductStatistics from './index';
import useMCProductStatistics from '.~/hooks/useMCProductStatistics';
import useAppSelectDispatch from '.~/hooks/useAppSelectDispatch';

jest.mock( '.~/hooks/useMCProductStatistics' );
jest.mock( '.~/hooks/useAppSelectDispatch' );

const stats = {
	loading: false,
	statistics: {
		active: 10,
		expiring: 20,
		pending: 30,
		disapproved: 2132,
		not_synced: 1074,
	},
	error: null,
	scheduledSync: 0,
};

describe( 'Product Statistics', () => {
	describe( `When the data still being populated or the request still in progress`, () => {
		it( 'Should render placeholder if the data is loading', () => {
			useMCProductStatistics.mockImplementation( () => {
				return {
					hasFinishedResolution: true,
					data: {
						loading: true,
					},
				};
			} );

			render( <ProductStatistics /> );

			const placeHolders = screen.queryAllByTestId(
				'summary-placeholder'
			);

			expect( placeHolders[ 0 ] ).toHaveClass( 'is-placeholder' );
		} );
		it( 'Should render placeholder if hasFinishedResolution = false', () => {
			useMCProductStatistics.mockImplementation( () => {
				return {
					hasFinishedResolution: false,
					data: {},
				};
			} );

			render( <ProductStatistics /> );

			const placeHolders = screen.queryAllByTestId(
				'summary-placeholder'
			);

			expect( placeHolders[ 0 ] ).toHaveClass( 'is-placeholder' );
		} );
	} );
	describe( `When the data is available`, () => {
		it( 'Should render the stats', () => {
			useMCProductStatistics.mockImplementation( () => {
				return {
					hasFinishedResolution: true,
					data: stats,
				};
			} );

			const { getByText } = render( <ProductStatistics /> );

			expect( getByText( 'Active' ) ).toBeInTheDocument();
			expect( getByText( '10' ) ).toBeInTheDocument();
			expect( getByText( 'Expiring' ) ).toBeInTheDocument();
			expect( getByText( '20' ) ).toBeInTheDocument();
			expect( getByText( 'Pending' ) ).toBeInTheDocument();
			expect( getByText( '30' ) ).toBeInTheDocument();
			expect( getByText( 'Disapproved' ) ).toBeInTheDocument();
			expect( getByText( '2132' ) ).toBeInTheDocument();
			expect( getByText( 'Not Synced' ) ).toBeInTheDocument();
			expect( getByText( '1074' ) ).toBeInTheDocument();
		} );
		describe( 'Render the footer', () => {
			beforeAll( () => {
				useMCProductStatistics.mockImplementation( () => {
					return {
						hasFinishedResolution: true,
						data: stats,
					};
				} );

				useAppSelectDispatch.mockImplementation( ( arg ) => {
					if ( arg === 'getMCReviewRequest' ) {
						return {
							hasFinishedResolution: true,
							data: {
								status: 'APPROVED',
							},
						};
					}
					return {
						hasFinishedResolution: true,
						data: stats,
					};
				} );
			} );

			it( 'Should render the footer', () => {
				const { getByText } = render( <ProductStatistics /> );

				expect( getByText( 'Feed setup:' ) ).toBeInTheDocument();
				expect( getByText( 'Sync with Google:' ) ).toBeInTheDocument();
				expect( getByText( 'Account status:' ) ).toBeInTheDocument();
			} );
		} );
	} );
} );
