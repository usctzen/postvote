# Post Vote (`usctzen/postvote`) — phpBB 3.3 Extension

A lightweight, modern **phpBB 3.3.x** extension that allows registered users to **Upvote** (👍) and **Downvote** (👎) forum posts with instant **AJAX** updates.

## Features

- **Upvote & Downvote buttons** integrated cleanly into the `prosilver` post action bar.
- **Mobile-friendly**: stays visible on mobile screens (`data-skip-responsive`) and uses DOM event delegation plus a server-side `GET` redirect fallback so mobile taps never fail.
- **Separate Upvote & Downvote counters** displayed below each post (`👍 X  👎 Y`).
- **Instant AJAX toggling & switching**: switch between Upvote and Downvote or click again to remove your vote without reloading the page.
- **No self-voting**: post authors cannot vote on their own posts.
- **Optimized SQL**: preloads all votes for a topic page in a single SQL query (no `N+1` queries).
- **Automatic legacy migration**: automatically imports existing likes from `phpbb_post_likes` if present.

## Requirements

- **phpBB**: `>= 3.3.0, < 3.4.0`
- **PHP**: `>= 7.4.0` (compatible with PHP 8.0, 8.1, 8.2, 8.3+)

## Installation

1. Download or clone this repository into your phpBB installation under:
   ```text
   <phpbb_root>/ext/usctzen/postvote/
   ```
2. Log in to your **Administration Control Panel (ACP)**.
3. Navigate to **Customise** > **Manage extensions**.
4. Find **Post Vote** under *Disabled Extensions* and click **Enable**.

## Author & License

- **Author**: Marc DUVAL (`usctzen`) — <duvalm@gmail.com>
- **Repository**: https://github.com/usctzen/postvote
- **License**: [MIT License](LICENSE)
