# Orienta Yacht Club — WordPress Theme

A custom WordPress theme for the Orienta Yacht Club, Mamaroneck, NY.

The homepage is a single-page layout (Hero, About, Membership, Sailing & Racing, Fishing, Visitors, Contact) and **every piece of copy is editable from the WordPress Customizer** — no plugins required.

## Requirements

- WordPress 6.0+
- PHP 7.4+

## Install

### Option A — Upload as ZIP (easiest)

1. Zip this entire `orienta-yacht-club-theme` folder.
2. In WordPress admin, go to **Appearance → Themes → Add New → Upload Theme**.
3. Choose the zip, upload, and click **Activate**.

### Option B — Copy via SFTP/SSH

1. Rename the folder from `orienta-yacht-club-theme` to **`orienta-yacht-club`** (the slug WP expects).
2. Upload it to `/wp-content/themes/orienta-yacht-club/` on your server.
3. In WP admin, go to **Appearance → Themes** and activate **Orienta Yacht Club**.

## First-time setup

After activating, do these four things in WordPress admin:

1. **Set the homepage to a static front page** — Settings → Reading → "A static page". Create a page called "Home" and choose it as the homepage. (The theme's `front-page.php` will render automatically.)
2. **Upload the real club burgee/logo** — Appearance → Customize → Site Identity → Logo. SVG uploads are enabled by this theme. Until you do, a placeholder burgee is shown.
3. **Set Site Title and Tagline** — Settings → General. The tagline appears under the club name in the header (e.g. "Mamaroneck Harbor · Est. 1907").
4. **Edit the homepage copy** — Appearance → Customize → **OYC Site Content**. You'll find one section per block of the page (Hero, About, Membership, etc.) with all defaults pre-filled.

## Adjusting brand colors

Open `style.css` and edit the `:root` block at the top. The current palette is:

```css
--navy:         #0b2a4a   /* primary brand color */
--brass:        #b08a3e   /* accent / pinstripe */
--brass-bright: #d4a851   /* highlights */
--cream:        #f5efe2   /* warm light bg */
--harbor:       #2c6e9b   /* link color */
```

Replace those hex values with the official OYC brand colors and save.

## Adding a real contact form

The Contact section ships with a placeholder. To wire up a real form:

1. Install **Contact Form 7** (or WPForms, Gravity Forms — anything that exposes a shortcode).
2. Create your form and copy its shortcode (e.g. `[contact-form-7 id="123"]`).
3. Paste the shortcode under **Customize → OYC Site Content → Contact → Contact Form Shortcode**.

## Navigation menus

The header and footer fall back to anchor links for the on-page sections out of the box. To use a real WP menu instead:

- **Appearance → Menus** → create a menu and assign it to "Primary Menu" or "Footer Menu".
- The theme will use the WP menu automatically when one is assigned.
- Add the CSS class `cta` on a menu item (Screen Options → CSS Classes) to render it as the brass "Join" button.

## Files

```
orienta-yacht-club/
├── style.css           Theme header + all styles
├── functions.php       Theme setup, asset enqueue, helpers
├── inc/
│   └── customizer.php  All Customizer sections & settings
├── header.php          Site header (logo + nav)
├── footer.php          Site footer
├── front-page.php      Single-page homepage layout
├── page.php            Standard WP page template
├── single.php          Blog post template
├── index.php           Archive/blog fallback
├── assets/
│   ├── burgee.svg      Placeholder club mark — replace via Customize → Site Identity → Logo
│   ├── waves.svg       Hero overlay pattern
│   └── main.js         Mobile nav toggle
└── README.md
```

## Replacing the placeholder burgee

You have two options:

1. **Recommended:** upload the real logo at **Customize → Site Identity → Logo**. The theme uses it automatically.
2. **Alternative:** replace `assets/burgee.svg` directly in this folder with the official burgee artwork (same filename).

## Notes

- This theme allows SVG uploads for logged-in users with the `upload_files` capability. If your site has untrusted contributors, install [Safe SVG](https://wordpress.org/plugins/safe-svg/) to sanitize uploads.
- The hero, racing, and fishing sections use pure-CSS decorative graphics rather than photos so the site loads fast and works without any image library setup. Swap in real photography by editing `style.css` (`.visual-sail`, `.visual-fish`, `.hero-media`).
