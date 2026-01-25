# CM Instagram Feed - AI Coding Agent Instructions

## Project Overview
WordPress plugin that displays Instagram posts using a Gutenberg block with mobile carousel support. Built with `@wordpress/scripts` and follows WordPress 6.7+ block registration patterns using `blocks-manifest.php`.

## Architecture

### Block Registration (Modern WordPress 6.7+)
- Uses performance-optimized block registration via `blocks-manifest.php` in [cm-instagram-feed.php](../cm-instagram-feed.php#L33-L63)
- Supports both WP 6.8 `wp_register_block_types_from_metadata_collection()` and WP 6.7 fallback
- Source blocks in `src/`, compiled to `build/` directory
- Single block: `carronmedia/cm-instagram-feed` defined in [src/cm-instagram-feed/block.json](../src/cm-instagram-feed/block.json)

### Dual Rendering System
**Editor (React)**: [edit.js](../src/cm-instagram-feed/edit.js) fetches posts via REST API endpoints (`/cm-instagram-feed/v1/posts`, `/cm-instagram-feed/v1/status`) with placeholder fallback when not connected

**Frontend (PHP)**: [template.php](../src/cm-instagram-feed/template.php) uses server-side rendering with same placeholder system - critical for SEO

Both modes use identical placeholder URLs (`https://picsum.photos/seed/insta1/600/600`) to ensure consistent preview experience

### State Management Pattern
Instagram connection uses WordPress Options API:
- `cm_instagram_access_token` - Basic Display API token
- `cm_instagram_username` / `cm_instagram_user_id` - User info
- Cache key: `cm_instagram_posts_{md5(token)}` (1 hour TTL)

**Token refresh**: Automatic WP Cron job every 50 days ([cm-instagram-feed.php#L497-L516](../cm-instagram-feed.php#L497-L516)) prevents 60-day expiration

### Frontend Enhancement
[view.js](../src/cm-instagram-feed/view.js) initializes Swiper.js with responsive breakpoints:
- Mobile (0-767px): 1.15 slides (shows partial next slide as visual hint)
- Tablet (768-991px): 2 slides
- Desktop (992px+): 4 slides grid (disables swiper behavior)

Relies on external Swiper library loaded by parent theme - **not bundled with plugin**

## Development Workflow

### Build Commands
```bash
npm run start      # Dev mode with watch + blocks-manifest generation
npm run build      # Production build with --blocks-manifest flag
```
**Critical**: Always use `--blocks-manifest` flag (configured in [package.json](../package.json)) to generate `build/blocks-manifest.php` required by WP 6.7+

### File Structure Conventions
```
src/cm-instagram-feed/     # Source (edit here)
  ├── block.json           # Block metadata + asset declarations
  ├── edit.js              # Editor component
  ├── save.js              # (empty - uses template.php)
  ├── template.php         # Server-side render
  ├── view.js              # Frontend interactivity
  └── style.scss           # Shared styles

build/cm-instagram-feed/   # Compiled (auto-generated)
  ├── block.json           # Copied from src
  ├── index.js/.asset.php  # Editor bundle
  ├── view.js/.asset.php   # Frontend bundle
  └── style-index.css      # Compiled styles
```

**Never edit `build/` directly** - changes will be overwritten

### Settings Page Architecture
Custom admin page at `Settings → Instagram Feed` ([cm-instagram-feed.php#L206-L363](../cm-instagram-feed.php#L206-L363)):
- Inline CSS in PHP (no separate stylesheet)
- Form handlers in `cm_instagram_feed_handle_form_submission()` process connects/disconnects
- Uses transients (`cm_instagram_message`) for flash messages with 60s TTL
- Token validation via Instagram Graph API `/me` endpoint

## Critical Integration Points

### Instagram API Flow
1. User generates token via Facebook Developers (Basic Display API)
2. Plugin validates token at `/graph.instagram.com/me` ([cm-instagram-feed.php#L145-L154](../cm-instagram-feed.php#L145-L154))
3. Fetch posts from `/graph.instagram.com/me/media` with fields: `id,caption,media_type,media_url,thumbnail_url,permalink,timestamp`
4. Store 25 posts reversed (newest first), display 4

**Error Handling**: Token expiry (codes 190/463) auto-clears saved credentials ([cm-instagram-feed.php#L440-L445](../cm-instagram-feed.php#L440-L445))

### REST API Endpoints
- `GET /wp-json/cm-instagram-feed/v1/posts` - Returns cached posts or fetches from Instagram
- `GET /wp-json/cm-instagram-feed/v1/status` - Returns connection status (`connected` boolean + `username`)
- Both have `'permission_callback' => '__return_true'` (public access)

### Block Attributes
Only one: `showCaption` (boolean, default: false) - toggles caption overlay on hover

## Project-Specific Patterns

### Text Domain Inconsistency
**Watch out**: Mixed text domains throughout codebase
- Plugin header: `cm-instagram-feed`
- Most strings: `oliver-james-theme` (likely copy-paste from parent theme)
- Should be standardized to `cm-instagram-feed`

### Placeholder System
Shared between editor and frontend for consistency:
```php
// Always 4 posts with seed-based picsum URLs
'https://picsum.photos/seed/insta1/600/600'
```
Used when: not connected, no posts, or preview mode enabled

### Cache Management
Always clear cache when:
- Disconnecting account
- Connecting new account
- Token refresh
- Use: `delete_transient('cm_instagram_posts_' . md5($access_token))`

## Dependencies
- **WordPress**: 6.7+ (uses `blocks-manifest.php` features)
- **PHP**: 7.4+
- **External Library**: Swiper.js (loaded by parent theme, not plugin)
- **Build Tools**: `@wordpress/scripts` ^31.2.0

## Common Tasks

### Adding New Block Attributes
1. Update [src/cm-instagram-feed/block.json](../src/cm-instagram-feed/block.json) `attributes`
2. Add to [edit.js](../src/cm-instagram-feed/edit.js) `attributes` destructuring
3. Update [template.php](../src/cm-instagram-feed/template.php) to access via `$attributes` array
4. Run `npm run build`

### Modifying Instagram API Fields
Change `fields` parameter in both:
- [cm-instagram-feed.php#L413](../cm-instagram-feed.php#L413) (REST endpoint)
- [template.php#L60](../src/cm-instagram-feed/template.php#L60) (frontend render)

### Testing Preview Mode
Toggle "Show Preview" in block inspector or disable plugin connection to see placeholder behavior

## Debugging
- Check browser console for REST API errors in editor
- Verify `build/blocks-manifest.php` exists after build
- Check WP Cron status: `wp cron event list` for `cm_instagram_refresh_token`
- Cache issues: Delete transient via WP CLI or database
