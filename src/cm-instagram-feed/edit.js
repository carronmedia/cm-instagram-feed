import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	Placeholder,
	Spinner,
	Notice,
	Button
} from '@wordpress/components';
import { Fragment, useState, useEffect, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

// Placeholder images for preview mode
const PLACEHOLDER_POSTS = [
	{ id: 1, media_url: 'https://picsum.photos/seed/insta1/600/600', media_type: 'IMAGE', caption: 'Sample Instagram post 1' },
	{ id: 2, media_url: 'https://picsum.photos/seed/insta2/600/600', media_type: 'IMAGE', caption: 'Sample Instagram post 2' },
	{ id: 3, media_url: 'https://picsum.photos/seed/insta3/600/600', media_type: 'IMAGE', caption: 'Sample Instagram post 3' },
	{ id: 4, media_url: 'https://picsum.photos/seed/insta4/600/600', media_type: 'IMAGE', caption: 'Sample Instagram post 4' },
];

const CMInstagramFeedEdit = ({ attributes, setAttributes }) => {
	const { showCaption } = attributes;

	const [posts, setPosts] = useState([]);
	const [isLoading, setIsLoading] = useState(true);
	const [error, setError] = useState(null);
	const [connectionStatus, setConnectionStatus] = useState(null);
	const [pluginActive, setPluginActive] = useState(true);
	const [showPreview, setShowPreview] = useState(false);

	// Track if initial load is complete to prevent scroll jumps
	const hasLoadedRef = useRef(false);

	useEffect(() => {
		// Only show loading on first mount
		if (!hasLoadedRef.current) {
			setIsLoading(true);
		}
		setError(null);

		// First check connection status
		apiFetch({ path: '/cm-instagram-feed/v1/status' })
			.then((status) => {
				setPluginActive(true);
				setConnectionStatus(status);
				if (status.connected) {
					// Fetch posts
					return apiFetch({ path: '/cm-instagram-feed/v1/posts' });
				} else {
					setIsLoading(false);
					hasLoadedRef.current = true;
					return null;
				}
			})
			.then((response) => {
				if (response) {
					setPosts(response.slice(0, 4)); // Always limit to 4
				}
				setIsLoading(false);
				hasLoadedRef.current = true;
			})
			.catch((err) => {
				// Check if it's a "route not found" error - means plugin is not active
				const errorMessage = err.message || '';
				const errorCode = err.code || '';

				if (
					errorMessage.includes('No route was found') ||
					errorMessage.includes('rest_no_route') ||
					errorCode === 'rest_no_route' ||
					err.status === 404
				) {
					setPluginActive(false);
					setError(null);
				} else {
					setError(errorMessage || __('Failed to load Instagram posts', 'cm-instagram-feed'));
				}
				setIsLoading(false);
				hasLoadedRef.current = true;
			});
	}, []);

	// Use placeholder posts for preview or when showing preview mode
	const displayPosts = showPreview || (!pluginActive && showPreview) ? PLACEHOLDER_POSTS : posts.slice(0, 4);
	const isPreviewMode = showPreview || (!isLoading && !pluginActive) || (!isLoading && pluginActive && connectionStatus && !connectionStatus.connected);

	const blockProps = useBlockProps({
		className: 'cm-instagram-feed-editor',
		style: {
			// Prevent layout shift by setting min-height during loading
			minHeight: isLoading ? '200px' : undefined
		}
	});

	return (
		<Fragment>
			<InspectorControls>
				<PanelBody title={__('Feed Settings', 'cm-instagram-feed')} initialOpen={true}>
					<ToggleControl
						label={__('Show Captions on Hover', 'cm-instagram-feed')}
						checked={showCaption}
						onChange={(value) => setAttributes({ showCaption: value })}
					/>
					<ToggleControl
						label={__('Show Preview', 'cm-instagram-feed')}
						checked={showPreview}
						onChange={(value) => setShowPreview(value)}
						help={__('Show placeholder images to preview the layout', 'cm-instagram-feed')}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{isLoading && (
					<Placeholder
						icon="instagram"
						label={__('Instagram Feed', 'cm-instagram-feed')}
					>
						<Spinner />
						<p>{__('Loading Instagram posts...', 'cm-instagram-feed')}</p>
					</Placeholder>
				)}

				{/* Show preview with placeholder images */}
				{!isLoading && (showPreview || displayPosts.length > 0) && (
					<>
						{isPreviewMode && !connectionStatus?.connected && (
							<div className="cm-instagram-feed__preview-notice">
								<Notice status="info" isDismissible={false}>
									<strong>{__('Preview Mode', 'cm-instagram-feed')}</strong>
									{' — '}
									{!pluginActive ? (
										<>
											{__('Activate the ', 'cm-instagram-feed')}
											<a href="/wp-admin/plugins.php">{__('Filter Instagram Feed plugin', 'cm-instagram-feed')}</a>
											{__(' and connect your account to show real posts.', 'cm-instagram-feed')}
										</>
									) : (
										<>
											<a href="/wp-admin/options-general.php?page=cm-instagram-feed-settings">
												{__('Connect your Instagram account', 'cm-instagram-feed')}
											</a>
											{__(' to show real posts.', 'cm-instagram-feed')}
										</>
									)}
								</Notice>
							</div>
						)}
						<div className="cm-instagram-feed__grid">
							{(showPreview ? PLACEHOLDER_POSTS : displayPosts).map((post) => (
								<div key={post.id} className="cm-instagram-feed__item">
									<div className="cm-instagram-feed__image-wrapper">
										<img
											src={post.media_type === 'VIDEO' ? post.thumbnail_url : post.media_url}
											alt={post.caption ? post.caption.substring(0, 100) : __('Instagram post', 'cm-instagram-feed')}
										/>
										{post.media_type === 'VIDEO' && (
											<span className="cm-instagram-feed__video-icon">▶</span>
										)}
										{post.media_type === 'CAROUSEL_ALBUM' && (
											<span className="cm-instagram-feed__carousel-icon">⊞</span>
										)}
										{showCaption && post.caption && (
											<div className="cm-instagram-feed__caption">
												<p>{post.caption.substring(0, 100)}...</p>
											</div>
										)}
									</div>
								</div>
							))}
						</div>
					</>
				)}

				{/* Plugin not activated - no preview mode */}
				{!isLoading && !pluginActive && !showPreview && (
					<Placeholder
						icon="instagram"
						label={__('Instagram Feed', 'cm-instagram-feed')}
						instructions={__('The Filter Instagram Feed plugin is required to display your Instagram posts.', 'cm-instagram-feed')}
					>
						<Notice status="warning" isDismissible={false}>
							<p><strong>{__('Plugin Not Activated', 'cm-instagram-feed')}</strong></p>
							<p>{__('To use this block, please:', 'cm-instagram-feed')}</p>
							<ol style={{ marginLeft: '20px', marginTop: '8px' }}>
								<li>
									{__('Go to ', 'cm-instagram-feed')}
									<a href="/wp-admin/plugins.php">
										{__('Plugins', 'cm-instagram-feed')}
									</a>
									{__(' and activate the ', 'cm-instagram-feed')}
									<strong>{__('Filter Instagram Feed', 'cm-instagram-feed')}</strong>
									{__(' plugin', 'cm-instagram-feed')}
								</li>
								<li>
									{__('Then go to ', 'cm-instagram-feed')}
									<a href="/wp-admin/options-general.php?page=cm-instagram-feed-settings">
										{__('Settings → Instagram Feed', 'cm-instagram-feed')}
									</a>
									{__(' to connect your Instagram account', 'cm-instagram-feed')}
								</li>
							</ol>
							<p style={{ marginTop: '12px' }}>
								{__('Or enable ', 'cm-instagram-feed')}
								<strong>{__('Show Preview', 'cm-instagram-feed')}</strong>
								{__(' in the sidebar to see how the block will look.', 'cm-instagram-feed')}
							</p>
						</Notice>
					</Placeholder>
				)}

				{/* Plugin active but not connected - no preview mode */}
				{!isLoading && pluginActive && connectionStatus && !connectionStatus.connected && !showPreview && (
					<Placeholder
						icon="instagram"
						label={__('Instagram Feed', 'cm-instagram-feed')}
						instructions={__('Connect your Instagram account to display your feed.', 'cm-instagram-feed')}
					>
						<Notice status="warning" isDismissible={false}>
							<p><strong>{__('Instagram Account Not Connected', 'cm-instagram-feed')}</strong></p>
							<p>
								{__('The plugin is active, but you need to connect your Instagram account.', 'cm-instagram-feed')}
							</p>
							<p>
								{__('Go to ', 'cm-instagram-feed')}
								<a href="/wp-admin/options-general.php?page=cm-instagram-feed-settings">
									{__('Settings → Instagram Feed', 'cm-instagram-feed')}
								</a>
								{__(' and follow the instructions to generate and enter your Access Token.', 'cm-instagram-feed')}
							</p>
							<p style={{ marginTop: '12px' }}>
								{__('Or enable ', 'cm-instagram-feed')}
								<strong>{__('Show Preview', 'cm-instagram-feed')}</strong>
								{__(' in the sidebar to see how the block will look.', 'cm-instagram-feed')}
							</p>
						</Notice>
						<div style={{ marginTop: '16px' }}>
							<Button
								variant="primary"
								href="/wp-admin/options-general.php?page=cm-instagram-feed-settings"
							>
								{__('Connect Instagram Account', 'cm-instagram-feed')}
							</Button>
						</div>
					</Placeholder>
				)}

				{/* Other errors */}
				{!isLoading && pluginActive && error && !showPreview && (
					<Placeholder
						icon="instagram"
						label={__('Instagram Feed', 'cm-instagram-feed')}
					>
						<Notice status="error" isDismissible={false}>
							<p><strong>{__('Error Loading Instagram Feed', 'cm-instagram-feed')}</strong></p>
							<p>{error}</p>
							<p>
								{__('Check your ', 'cm-instagram-feed')}
								<a href="/wp-admin/options-general.php?page=cm-instagram-feed-settings">
									{__('Instagram Feed Settings', 'cm-instagram-feed')}
								</a>
								{__(' to ensure your account is properly connected.', 'cm-instagram-feed')}
							</p>
						</Notice>
					</Placeholder>
				)}

				{/* Connected but no posts */}
				{!isLoading && !error && pluginActive && posts.length === 0 && connectionStatus?.connected && !showPreview && (
					<Placeholder
						icon="instagram"
						label={__('Instagram Feed', 'cm-instagram-feed')}
						instructions={__('No Instagram posts found. Make sure your Instagram account has public posts.', 'cm-instagram-feed')}
					/>
				)}
			</div>
		</Fragment>
	);
};

export default CMInstagramFeedEdit;