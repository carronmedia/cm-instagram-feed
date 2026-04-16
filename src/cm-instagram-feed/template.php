<?php
/**
 * Instagram Feed block template
 *
 * @package CM\Blocks\InstagramFeed
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (unused).
 * @var WP_Block $block      Block instance.
 */

$show_caption = $attributes['showCaption'] ?? false;
$number_of_posts = $attributes['postCount'] ?? 4;

// Placeholder posts for preview mode
$placeholder_posts = array(
		array(
				'id'         => 'placeholder-1',
				'media_type' => 'IMAGE',
				'media_url'  => 'https://picsum.photos/seed/insta1/600/600',
				'permalink'  => '#',
				'caption'    => 'Sample Instagram post 1',
		),
		array(
				'id'         => 'placeholder-2',
				'media_type' => 'IMAGE',
				'media_url'  => 'https://picsum.photos/seed/insta2/600/600',
				'permalink'  => '#',
				'caption'    => 'Sample Instagram post 2',
		),
		array(
				'id'         => 'placeholder-3',
				'media_type' => 'IMAGE',
				'media_url'  => 'https://picsum.photos/seed/insta3/600/600',
				'permalink'  => '#',
				'caption'    => 'Sample Instagram post 3',
		),
		array(
				'id'         => 'placeholder-4',
				'media_type' => 'IMAGE',
				'media_url'  => 'https://picsum.photos/seed/insta4/600/600',
				'permalink'  => '#',
				'caption'    => 'Sample Instagram post 4',
		),
);

// Get Instagram data
$access_token   = get_option( 'cm_instagram_access_token' );
$is_connected   = ! empty( $access_token );
$is_preview     = false;
$posts          = array();

if ( $is_connected ) {
		// Get cached posts or fetch from API
		$cache_key = 'cm_instagram_posts_' . md5( $access_token );
		$posts     = get_transient( $cache_key );

		if ( false === $posts ) {
				$api_url = add_query_arg(
						array(
								'fields'       => 'id,caption,media_type,media_url,thumbnail_url,permalink,timestamp',
								'access_token' => $access_token,
								'limit'        => 25,
						),
						'https://graph.instagram.com/me/media'
				);

				$response = wp_remote_get( $api_url );

				if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
						$body = wp_remote_retrieve_body( $response );
						$data = json_decode( $body, true );

						if ( isset( $data['data'] ) ) {
							$posts = cm_instagram_feed_sort_posts_newest_first( $data['data'] );
								set_transient( $cache_key, $posts, HOUR_IN_SECONDS );
						}
				}
		}

				$posts = cm_instagram_feed_sort_posts_newest_first( $posts );

		// Limit posts to number specified in attributes
		if ( ! empty( $posts ) ) {
				$posts = array_slice( $posts, 0, $number_of_posts );
		}
}

// If not connected or no posts, use placeholder posts
if ( empty( $posts ) ) {
		$posts      = $placeholder_posts;
		$is_preview = true;
}

// Build wrapper classes
$wrapper_classes = 'cm-instagram-feed';
if ( $is_preview ) {
		$wrapper_classes .= ' cm-instagram-feed--preview';
}

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $wrapper_classes ) );
?>

<div <?php echo $wrapper_attributes; ?>>
		<?php if ( $is_preview && current_user_can( 'manage_options' ) ) : ?>
				<div class="cm-instagram-feed__preview-banner">
						<p>
								<strong><?php esc_html_e( 'Preview Mode', 'cm-instagram-feed' ); ?></strong> — 
								<?php esc_html_e( 'These are placeholder images.', 'cm-instagram-feed' ); ?>
								<a href="<?php echo esc_url( admin_url( 'options-general.php?page=cm-instagram-feed-settings' ) ); ?>">
										<?php esc_html_e( 'Connect your Instagram account', 'cm-instagram-feed' ); ?>
								</a>
								<?php esc_html_e( 'to show real posts.', 'cm-instagram-feed' ); ?>
						</p>
				</div>
		<?php endif; ?>

		<div class="cm-swiper cm-swiper-js-instagram swiper"
				data-slides-to-show="4"
				data-mobile-slides-to-show="1"
				data-loop="false"
				data-auto-play="false"
				data-show-dots="false"
				data-show-arrows="true"
				data-space-between="16"
				data-mobile-space-between="8"
		>
				<div class="swiper-wrapper cm-swiper-wrapper">
						<?php foreach ( $posts as $post ) : 
								$image_url = 'VIDEO' === $post['media_type'] && ! empty( $post['thumbnail_url'] ) 
										? $post['thumbnail_url'] 
										: $post['media_url'];
								$caption   = isset( $post['caption'] ) ? $post['caption'] : '';
								$permalink = isset( $post['permalink'] ) ? $post['permalink'] : '#';
						?>
								<div class="swiper-slide cm-swiper-slide cm-instagram-feed__slide">
										<a href="<?php echo esc_url( $permalink ); ?>" 
											class="cm-instagram-feed__item" 
											<?php echo $is_preview ? '' : 'target="_blank" rel="noopener noreferrer"'; ?>
											aria-label="<?php echo esc_attr( $caption ? substr( $caption, 0, 50 ) : __( 'View on Instagram', 'cm-instagram-feed' ) ); ?>"
										>
												<div class="cm-instagram-feed__image-wrapper">
														<img 
																src="<?php echo esc_url( $image_url ); ?>" 
																alt="<?php echo esc_attr( $caption ? substr( $caption, 0, 100 ) : __( 'Instagram post', 'cm-instagram-feed' ) ); ?>"
																loading="lazy"
														/>
														<?php if ( 'VIDEO' === $post['media_type'] ) : ?>
																<span class="cm-instagram-feed__video-icon" aria-hidden="true">▶</span>
														<?php endif; ?>
														<?php if ( 'CAROUSEL_ALBUM' === $post['media_type'] ) : ?>
																<span class="cm-instagram-feed__carousel-icon" aria-hidden="true">⊞</span>
														<?php endif; ?>
														<?php if ( $show_caption && $caption ) : ?>
																<div class="cm-instagram-feed__caption">
																		<p><?php echo esc_html( substr( $caption, 0, 100 ) ); ?>...</p>
																</div>
														<?php endif; ?>
												</div>
										</a>
								</div>
						<?php endforeach; ?>
				</div>
		</div>
</div>