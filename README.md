# Information
A custom glossary plugin for WordPress, offering tooltip functionality, an archive page, caching, and more.

## Important Note
If you plan to create a large number of glossary entries or expect significant traffic, we recommend using a caching plugin alongside this script’s built-in caching for enhanced performance, e.g., LiteSpeed Cache.

## Features

### Custom Glossary Archive Page
A dedicated page with search functionality, abbreviation support, and alphabetical filtering, making it easy for users to find glossary terms. This page also includes pagination, displaying up to 25 glossaries per page. Users can navigate through additional pages if there are more than 25 terms.

### Tooltips with Abbreviation Support
A centered tooltip box displays up to 300 characters of text from the "Add New Glossary" editor. The tooltip background dims, and you can close it by:
- Pressing the "X"
- Clicking on the background
- Pressing "Escape" on the keyboard

### Caching
- **On-demand Caching**: Glossary terms are cached the first time they are viewed.
- **Glossary Cache Log**: Displays the newest entries at the top, limited to 1000 entries (older entries are automatically removed).
- **Optimized Caching**: Uses WordPress’s native caching and minimizes redundant actions. Caching occurs only if a glossary term appears on a page and is not already cached.
- **Automatic Cache Reset**: Cached terms are stored for 1 week (168 hours) and automatically rebuild when users visit pages containing glossary terms.

### Responsive Design
Works well on both desktop and mobile devices, ensuring accessibility for all users.

---

## Security Features

### Input Sanitization
All user inputs, including search queries and glossary entries, are sanitized to prevent code injection.

### Data Validation
Only valid data is stored for tooltip text and glossary terms, ensuring database integrity.

### Nonce Verification
Form submissions use nonce verification to prevent CSRF attacks.

### XSS Protection
Tooltip text and glossary data are escaped before being displayed in the browser to prevent XSS attacks.

### Glossary Management
Administrators and Editors can securely create, edit, or delete glossary terms.

### Secure Caching and Logging
Cache data is securely stored without sensitive information. Logging is limited to technical events like cache generation, with a maximum of 1000 log entries retained.

---

## How Glossary Terms are Stored and Retrieved

### Storage in Database
Glossary terms are stored as custom post types (`glossary`) in the `wp_posts` table, with tooltip text and abbreviation stored in the `wp_postmeta` table using meta keys `_tooltip_text` and `_abbreviation_full_form`.

### Retrieval of Glossary Terms
Glossary terms are fetched using a `WP_Query` that targets `post_type` set to `glossary`.

Example:
```php
$glossary_terms = new WP_Query(array(
    'post_type' => 'glossary',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
));

```

Tooltip text and abbreviation full form are retrieved using `get_post_meta()`:

```
$tooltip_text = get_post_meta($term->ID, '_tooltip_text', true);
$abbreviation_full_form = get_post_meta($term->ID, '_abbreviation_full_form', true);
```

## Changelog (latest 11.07.24) (MM/DD/YY)
- Reworked tooltip text - tooltip now appears in a centered box when clicked, no longer on hover.
- Cache implementation added.

## Installation

1. **Download the Plugin**:  
   Download the plugin zip file from this repository.

2. **Upload to WordPress**:  
   Go to your WordPress dashboard, navigate to **Plugins > Add New**, and upload the zip file.

3. **Activate the Plugin**:  
   Activate the plugin through the **Plugins** menu in WordPress.

## Usage

- **Shortcode for Archive**:  
  Use the `[glossary_archive]` shortcode to display the glossary archive page on any page.

- **Shortcode to exclude terms**:
  Use `[gloss_ign]Glossary[/gloss_ign]` o exclude specific glossary tooltips on pages or posts.

- **Adding Glossary Terms**:  
  Add terms in the WordPress dashboard under "Glossary", including descriptions and abbreviations as needed.

- **Automatic Tooltips**:  
  Tooltips automatically appear in posts for matching glossary terms (case sensitive).

## Known Bugs or Errors

- None, as as of this version.

## How and Why This Plugin Was Created

Please check out this article on our blog to see how and why I decided to make my own plugin:  
[https://www.nuvorix.com/2024/10/18/free-wordpress-glossary-plugin-chatgpt4/](https://www.nuvorix.com/2024/10/18/free-wordpress-glossary-plugin-chatgpt4/)

## License

This project is licensed under the GPLv3 License. See the [LICENSE](LICENSE) file for details.

## Contribution and Modification

You are free to use, modify, and distribute this plugin as you wish, as long as it remains open-source. Any modifications or derivative works must also be released under the same GPLv3 License. This ensures that the community can continue to benefit from and build upon this work.
