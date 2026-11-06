=== Shakewell Multiple Authors ===
Contributors: shakewell
Tags: authors, multiple authors, byline, co-authors, guest authors
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Give a post more than one author, in order, using users or private author profiles. Imports from Co-Authors Plus.

== Description ==

Editors get an "Authors" box on the post edit screen. Pick WordPress users or author profiles and put them in order: drag them, or use the Up and Down buttons, which work with a keyboard and screen readers. Themes read the list with two template functions.

What it does:

* An Authors box on posts (other post types through a filter).
* Author profiles for people who have no login: a display name, an optional photo and a short bio.
* The ordered list is stored in one post meta field. The post's normal author is never changed.
* A WP-CLI import from Co-Authors Plus, and a command that checks the result.

What it deliberately does not do: it creates no author archive pages, adds no REST routes, changes no queries, and does not edit `post_author`. Author profiles are private: they have no public URL, are not in search and are not in the REST API.

= Usage =

Edit a post, find the Authors box, type a name in "Add an author" and choose a match. Use Up, Down and Remove to arrange the list, or drag the rows. Update the post. With no authors listed, the post author is used.

To use the box on other post types, add this to a plugin or theme:

`add_filter( 'shakewell_authors_post_types', fn( $types ) => array( 'post', 'page' ) );`

= Template functions =

`shakewell_get_authors( $post = null )` returns the ordered authors as arrays with `type` (`user` or `profile`), `id` and `display_name`. It falls back to the post's author when none are set.

`shakewell_authors_names( $post = null )` returns the names as a readable list, such as "Ann, Bo, and Cy".

Both return plain text. Escape it when you print it:

`<?php echo esc_html( shakewell_authors_names() ); ?>`

= Importing from Co-Authors Plus =

The import reads Co-Authors Plus data straight from the database, so it works even after Co-Authors Plus is deactivated. It never changes Co-Authors Plus data or `post_author`.

1. Take a database backup.
2. Run `wp shakewell-authors import-coauthors --dry-run` and read the counts.
3. Run `wp shakewell-authors import-coauthors`. Guest authors become author profiles, authors that are WordPress users are referenced directly, and every post gets its list in the same order. A guest author mapped to a user is treated as that user when both have the same display name, otherwise it keeps its own profile so the name does not change.
4. Run `wp shakewell-authors verify`. It compares every post's author names, in order, with Co-Authors Plus and lists any difference.
5. Change your theme to call `shakewell_get_authors()` instead of `get_coauthors()`, then deactivate Co-Authors Plus.

The import can be run again: profiles are reused and each post's list is rewritten from Co-Authors Plus, so run it before editors start using this plugin's box. `--batch=<n>` sets how many posts are read per query.

= Privacy =

The plugin stores author names, bios and photos that editors enter, and sends nothing anywhere. Author profiles are not public and not in the REST API. Your theme decides what to show; showing an author's name on a page is a decision about your visitors' view of your staff and guests, so only show what the person agreed to.

Deleting the plugin keeps its data. To remove the author lists and profiles on deletion, define `SHAKEWELL_AUTHORS_DELETE_DATA` as `true` in wp-config.php before deleting.

= External services =

None. The plugin makes no outside requests.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install it with Composer.
2. Activate it from the Plugins screen.

== Frequently Asked Questions ==

= Does it show authors on my site? =

No. It stores them and gives your theme the template functions. It adds no archives or markup.

= Does it change the post author? =

No. The post author is only used when a post has no authors listed.

= Can an author profile be viewed or fetched by the public? =

No. Profiles are private and have no REST route.

= Do the authors appear in the REST API? =

No. The author list is not exposed there.

= Where do I report a security problem? =

Please do not open a public issue. Follow SECURITY.md in the plugin's repository.

== Changelog ==

= 0.1.0 =
* First release: Authors box, private author profiles, template functions, Co-Authors Plus import and verify commands.
