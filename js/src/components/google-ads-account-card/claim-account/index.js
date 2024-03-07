/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Notice, ExternalLink } from '@wordpress/components';
import { createInterpolateElement, Fragment } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { useAppDispatch } from '.~/data';
import Section from '.~/wcdl/section';
import useGoogleAdsAccountStatus from '.~/hooks/useGoogleAdsAccountStatus';
import useWindowFocusCallbackIntervalEffect from '.~/hooks/useWindowFocusCallbackIntervalEffect';
import DisconnectAccount from '../disconnect-account';
import './index.scss';

const ClaimAccount = () => {
	const { inviteLink } = useGoogleAdsAccountStatus();
	const { fetchGoogleAdsAccountStatus } = useAppDispatch();
	useWindowFocusCallbackIntervalEffect( fetchGoogleAdsAccountStatus, 30 );

	return (
		<Fragment>
			<Notice
				status="warning"
				isDismissible={ false }
				className="gla-ads-claim-account-notice"
			>
				{ createInterpolateElement(
					__(
						'Your new ads account has been created, but you do not have access to it yet. <link>Claim your new ads account</link> to automatically configure conversion tracking and configure onboarding',
						'google-listings-and-ads'
					),
					{ link: <ExternalLink href={ inviteLink } /> }
				) }
			</Notice>

			<Section.Card.Footer>
				<DisconnectAccount />
			</Section.Card.Footer>
		</Fragment>
	);
};

export default ClaimAccount;
