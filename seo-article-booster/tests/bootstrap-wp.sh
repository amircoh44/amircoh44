#!/usr/bin/env bash
#
# Stand up a throwaway WordPress on SQLite for the smoke test — no MySQL and no
# wordpress.org required (core + the SQLite drop-in are pulled from GitHub).
#
# Works both in CI and locally:
#   bash seo-article-booster/tests/bootstrap-wp.sh
#
# Honours:
#   WP_CORE  (default: ./wp)        where to put the WordPress install
#   WP_REF   (default: 6.7)         WordPress/WordPress branch/tag to clone
#
set -euo pipefail

WP_REF="${WP_REF:-6.7}"
WP_CORE="${WP_CORE:-$PWD/wp}"
PLUGIN_SRC="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_SLUG="seo-article-booster"

echo "Plugin source : $PLUGIN_SRC"
echo "WP core dir   : $WP_CORE (ref: $WP_REF)"

rm -rf "$WP_CORE"
# Prefer a stable branch; fall back to the default branch if it doesn't exist.
git clone --depth 1 --branch "$WP_REF" https://github.com/WordPress/WordPress.git "$WP_CORE" \
	|| git clone --depth 1 https://github.com/WordPress/WordPress.git "$WP_CORE"

# Single-file SQLite drop-in.
rm -rf /tmp/wp-sqlite-db
git clone --depth 1 https://github.com/aaemnnosttv/wp-sqlite-db.git /tmp/wp-sqlite-db
cp /tmp/wp-sqlite-db/src/db.php "$WP_CORE/wp-content/db.php"
mkdir -p "$WP_CORE/wp-content/database"

# Link the plugin under test into the install.
ln -sfn "$PLUGIN_SRC" "$WP_CORE/wp-content/plugins/$PLUGIN_SLUG"

# Minimal wp-config (SQLite drop-in ignores the MySQL creds, but they must exist).
cat > "$WP_CORE/wp-config.php" <<'PHP'
<?php
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', '' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );
$table_prefix = 'wp_';
define( 'WP_HOME', 'http://localhost:8088' );
define( 'WP_SITEURL', 'http://localhost:8088' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'FS_METHOD', 'direct' );
define( 'AUTH_KEY', 'k1' ); define( 'SECURE_AUTH_KEY', 'k2' ); define( 'LOGGED_IN_KEY', 'k3' ); define( 'NONCE_KEY', 'k4' );
define( 'AUTH_SALT', 's1' ); define( 'SECURE_AUTH_SALT', 's2' ); define( 'LOGGED_IN_SALT', 's3' ); define( 'NONCE_SALT', 's4' );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
PHP

# Install + activate.
WP_CORE="$WP_CORE" php "$PLUGIN_SRC/tests/install.php"
WP_CORE="$WP_CORE" php "$PLUGIN_SRC/tests/activate.php"

echo "Bootstrap complete: $WP_CORE"
