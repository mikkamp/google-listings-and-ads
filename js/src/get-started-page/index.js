/**
 * Internal dependencies
 */
import './index.scss';
import BenefitsCard from './benefits-card';
import CustomerQuotesCard from './customer-quotes-card';
import FeaturesCard from './features-card';
import GetStartedCard from './get-started-card';
import GetStartedWithVideoCard from './get-started-with-video-card';
import UnsupportedNotices from './unsupported-notices';

const GetStartedPage = () => {
	return (
		<div className="woocommerce-marketing-google-get-started-page">
			<UnsupportedNotices />
			<GetStartedWithVideoCard />
			<BenefitsCard />
			<FeaturesCard />
			<CustomerQuotesCard />
			<GetStartedCard />
		</div>
	);
};

export default GetStartedPage;
