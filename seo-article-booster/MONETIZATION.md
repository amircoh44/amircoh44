# Selling SEO Article Booster (Free / Pro / Expert)

This plugin is built to be sold as **freemium**. It already contains the gating
(`SAB_Edition`), so wiring a payment/licensing provider is a few lines.

## The model

- **Free** — everything is unlocked **up to 25 published pages** (configurable
  via the `sab_free_page_limit` filter). Audits + manual tools stay free at any
  size.
- **Pro** — removes the 25-page limit for automation/output: auto internal
  linking, bulk apply/revert, content distribution, bulk + auto cleaning,
  new-post auto inbound links, AI actions, JSON-LD schema output.
- **Expert** — Pro + the Export / Migrate tool and multisite/agency use.

## Integration points (already in the code)

| Hook / constant | Purpose |
| --- | --- |
| `sab_validate_license` (filter) | Turn an entered license key into `free`/`pro`/`expert`. |
| `sab_edition` (filter) | Force the active edition (wire your provider's license state here). |
| `SAB_EDITION` (constant) | Hard-set the edition (used by the premium ZIP). |
| `sab_upgrade_url` (filter) | Your checkout/pricing URL (the Upgrade buttons). |
| `sab_free_page_limit` (filter) | Change the 25-page free grace. |

---

## Option A — Freemius (recommended)

Freemius handles checkout, licenses, EU VAT, refunds, affiliates, auto-updates,
and **auto-generates the Free + Premium ZIPs from one codebase**.

1. Create an account at <https://freemius.com>, **Add Plugin**, and copy your
   **Plugin ID**, **Public Key**, **Secret Key**.
2. Add the Freemius SDK (`freemius/wordpress-sdk`) to the plugin and initialise
   it near the top of `seo-article-booster.php`:

   ```php
   if ( ! function_exists( 'sab_fs' ) ) {
       function sab_fs() {
           global $sab_fs;
           if ( ! isset( $sab_fs ) ) {
               require_once __DIR__ . '/freemius/start.php';
               $sab_fs = fs_dynamic_init( array(
                   'id'             => 'YOUR_ID',
                   'slug'           => 'seo-article-booster',
                   'public_key'     => 'pk_XXX',
                   'is_premium'     => false,
                   'has_premium_version' => true,
                   'menu'           => array( 'slug' => 'sab-dashboard' ),
               ) );
           }
           return $sab_fs;
       }
       sab_fs();
       do_action( 'sab_fs_loaded' );
   }
   ```
3. **Bridge Freemius → the edition gate** (one filter — no other code changes):

   ```php
   add_filter( 'sab_edition', function ( $edition ) {
       if ( function_exists( 'sab_fs' ) ) {
           if ( sab_fs()->is_plan( 'expert', true ) ) { return 'expert'; }
           if ( sab_fs()->is_plan( 'pro', true ) )    { return 'pro'; }
       }
       return $edition;
   } );
   ```
4. In the Freemius dashboard create **plans** (Pro, Expert) and prices, then
   **Deploy** your ZIP. Freemius produces the free build (for WordPress.org) and
   the premium build (for customers) automatically.
5. Connect payouts (Stripe/PayPal). Done — checkout + licensing is live.

### Suggested pricing (benchmarked against Yoast/Rank Math/AIOSEO)

| Plan | Sites | Price (annual) | Lifetime |
| --- | --- | --- | --- |
| Pro | 1 | $49 | $149 |
| Pro | 5 | $99 | $299 |
| Expert | 25 / unlimited | $149–$199 | $499 |

(AIOSEO Pro is ~$199/yr for 10 sites; Yoast Premium ~$99/yr per site — you have
room to undercut while bundling features they don't offer.)

---

## Option B — Lemon Squeezy / Gumroad (license key)

Sell license keys on Lemon Squeezy or Gumroad and validate them via the filter:

```php
add_filter( 'sab_validate_license', function ( $edition, $key ) {
    if ( '' === $key ) { return 'free'; }
    $res = wp_remote_post( 'https://api.lemonsqueezy.com/v1/licenses/validate', array(
        'headers' => array( 'Accept' => 'application/json' ),
        'body'    => array( 'license_key' => $key ),
    ) );
    $data = json_decode( wp_remote_retrieve_body( $res ), true );
    if ( ! empty( $data['valid'] ) ) {
        // Map the product/variant to your tier.
        return ( false !== stripos( $data['meta']['variant_name'] ?? '', 'expert' ) ) ? 'expert' : 'pro';
    }
    return 'free';
}, 10, 2 );
```

The **License key** field is already on **SEO Booster → Settings → License**.

---

## Option C — Direct sale (no license server)

Hand customers the prebuilt **`seo-article-booster-pro.zip`** — it defines
`SAB_EDITION = 'expert'`, so every feature is unlocked on install. Sell it on
Gumroad/your site; give everyone else the **free** ZIP. Simplest, but no
auto-updates or per-seat enforcement.

---

## Recommended go-to-market

1. Publish the **free** build on **WordPress.org** (huge reach, free installs).
2. Sell **Pro/Expert** via **Freemius** (Option A) for the funnel + licensing.
3. Keep the upgrade URL (`sab_upgrade_url`) pointing at your Freemius pricing page.
