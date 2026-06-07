# SEO Sprinkler — License Server & Storefront

A self-contained **Flask** app that backs the SEO Sprinkler plugin:

- **Storefront** (public): landing, features, pricing, FAQ, account lookup, sandbox checkout, download, contact.
- **License REST API**: activate / validate / deactivate — what the plugin calls.
- **Admin panel** (password-protected): dashboard, customers, licenses (issue / revoke / extend / regenerate), coupons, activations.

Prices live in `server/licensing.py` and mirror [`../seo-sprinkler/PRICING.md`](../seo-sprinkler/PRICING.md).

## Run it

```bash
cd license-server
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
python run.py                      # http://127.0.0.1:5001
```

- Storefront: <http://127.0.0.1:5001/>
- Admin: <http://127.0.0.1:5001/admin/>  (seeded `admin` / `changeme`)

Configure via env vars: `SECRET_KEY`, `DATABASE_URL`, `ADMIN_USER`, `ADMIN_PASS`, `PORT`, `HOST`.
The SQLite DB (`license.db`) and a demo license are created on first run.

## Tests

```bash
PYTHONPATH=. pytest -q
```

## License API

All endpoints accept JSON or form-encoded bodies.

| Method | Path | Body | Returns |
| --- | --- | --- | --- |
| POST | `/api/v1/activate` | `key`, `site_url`, `site_name?` | takes/refreshes a seat → `{success, edition, expires_at, sites_used, …}` |
| POST | `/api/v1/validate` | `key`, `site_url?` | current status (no seat change) |
| POST | `/api/v1/deactivate` | `key`, `site_url` | frees a seat |
| GET | `/api/v1/ping` | — | health check |

`edition` is always one of `free` / `pro` / `expert`. An unknown, expired or
revoked key resolves to `free`, so the plugin degrades gracefully.

## Wire it to the plugin

The plugin already exposes a `spr_validate_license` filter (see
`SPR_Edition::activate_key()`). Drop this into a small mu-plugin or your theme's
`functions.php`, pointing at your server:

```php
// Turn an entered key into an edition by asking the license server.
add_filter( 'spr_validate_license', function ( $edition, $key ) {
    $resp = wp_remote_post( 'https://YOUR-SERVER.com/api/v1/activate', array(
        'timeout' => 15,
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => wp_json_encode( array(
            'key'       => $key,
            'site_url'  => home_url( '/' ),
            'site_name' => get_bloginfo( 'name' ),
        ) ),
    ) );
    if ( is_wp_error( $resp ) ) {
        return $edition; // network issue — keep current edition.
    }
    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    return ! empty( $data['edition'] ) ? $data['edition'] : $edition;
}, 10, 2 );
```

Re-check periodically (so revoked/expired keys downgrade) via WP-Cron:

```php
add_action( 'spr_daily_refresh_event', function () {
    $key = SPR_Settings::get( 'license_key' );
    if ( ! $key ) {
        return;
    }
    $resp = wp_remote_post( 'https://YOUR-SERVER.com/api/v1/validate', array(
        'timeout' => 15,
        'headers' => array( 'Content-Type' => 'application/json' ),
        'body'    => wp_json_encode( array( 'key' => $key, 'site_url' => home_url( '/' ) ) ),
    ) );
    if ( ! is_wp_error( $resp ) ) {
        $data = json_decode( wp_remote_retrieve_body( $resp ), true );
        if ( ! empty( $data['edition'] ) ) {
            update_option( 'spr_edition', $data['edition'] );
        }
    }
} );
```

## Going to production

- Put it behind a real WSGI server (gunicorn/uwsgi) + HTTPS; set a strong `SECRET_KEY`.
- Swap the **sandbox checkout** for Stripe / Lemon Squeezy / Freemius and create the
  license in their webhook (call the same `License` model used by `/admin`).
- Optionally sign API responses (HMAC with a shared secret) so keys can't be spoofed offline.
