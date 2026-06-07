# Owner sites — running SEO Sprinkler at full edition (the legitimate way)

This is for installs **you own and control** (your own client/agency sites, staging,
local). It documents the *transparent, built‑in* ways to set the edition — the same
public hooks the plugin uses itself. None of this is hidden, and **none of it belongs
in the distributed plugin**: never bake an edition override (especially one keyed to a
name, email, or domain) into the zips you hand to customers — that's a backdoor / supply‑chain
compromise, and AV + the wordpress.org review team will flag and pull it.

## How the edition is decided

`SPR_Edition::current()` resolves, in order:

1. the **`spr_edition` filter** return value (has the final say, if you use it);
2. the **`SPR_EDITION` constant**, if defined to `free` / `pro` / `expert`;
3. the **saved license result** (what a key resolved to via the license server);
4. otherwise **`free`**.

Separately, the **free‑page grace** unlocks *every* premium feature on small sites
(≤ 25 published items by default), regardless of tier.

---

## Pick one method

### 1. `wp-config.php` constant — simplest, per site
Add above the `/* That's all, stop editing! */` line:

```php
define( 'SPR_EDITION', 'expert' );   // 'free' | 'pro' | 'expert'
```

No key, no server. This is exactly what the pinned **Expert/Pro build zips** do for you.

### 2. mu‑plugin — best across many installs / managed hosts
Drop [`examples/spr-owner-unlock.php`](examples/spr-owner-unlock.php) into
`wp-content/mu-plugins/` (create the folder if needed — mu‑plugins auto‑load, no
activation). It uses the public `spr_edition` filter:

```php
add_filter( 'spr_edition', fn() => 'expert' );   // 'pro' or 'expert'
```

Handy when you don't want to touch `wp-config.php`, or want one file you copy to every
site you run.

### 3. Pre‑activated build
Install `seo-sprinkler-expert-<ver>.zip` (or `-pro-`). The edition is baked into that
build — nothing to configure. (This is for sites you control or direct/demo delivery,
not the wordpress.org / storefront download, which is the **bundled** build.)

---

## Testing the license‑key flow

- **Offline** (no server): [`examples/spr-test-licenses.php`](examples/spr-test-licenses.php)
  validates a couple of hard‑coded keys via the `spr_validate_license` filter, so you can
  exercise the *License key* field end‑to‑end without infrastructure. Use the **bundled**
  build (the pinned builds ignore keys, since the constant wins). Delete it when done.
- **Real server**: point a bundled install at your deployed license server with the
  **License server URL** setting (or `define( 'SPR_LICENSE_SERVER', 'https://…' );`),
  then paste a key issued by that server. This exercises seat limits, expiry and revoke.

---

## Other useful filters

```php
// Change or disable the "everything's free under N pages" grace (negative = off).
add_filter( 'spr_free_page_limit', fn( $n ) => 50 );

// Where the in‑dashboard "Upgrade" links point.
add_filter( 'spr_upgrade_url', fn() => 'https://your-store.example.com/pricing' );

// Resolve an entered key into an edition yourself (what the license client does).
add_filter( 'spr_validate_license', function ( $edition, $key ) {
    // return 'pro' | 'expert' | $edition;
    return $edition;
}, 10, 2 );
```

---

## The line

✅ On **your own** sites: constant, mu‑plugin, or pre‑activated build — all transparent,
all reversible, none shipped to anyone else.

🚫 In the **distributed** plugin: no hidden unlocks, no identity/email/domain‑keyed admin
or edition grants. That's a backdoor; it breaks customer trust, fails review, and trips
the very scanners that are already blocking downloads.
