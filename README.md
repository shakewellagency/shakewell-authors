# Shakewell Multiple Authors

Free, GPL-2.0-or-later WordPress plugin: give a post more than one author, in order, using users or private author profiles. Imports from Co-Authors Plus. See `readme.txt` for usage.

```sh
composer install
npm ci
npm run env:start      # WordPress at http://localhost:8904 (admin / password), needs Docker
npm test               # PHPUnit inside the WordPress test container
composer lint          # WordPress coding standards
npm run plugin-check   # the official Plugin Check
npm run env:stop
```

## How it stores things

- Per post: one meta key `_shakewell_authors`, an array of `user:<id>` / `profile:<id>`, in author order.
- Profiles: private post type `shakewell_author` (title = display name, excerpt = bio, featured image = photo).
- Co-Authors Plus data read by the import: terms of taxonomy `author` (order = `term_order`, slug = `cap-<nicename>`), and `guest-author` posts whose `post_name` equals the term slug, with `cap-*` meta.

Built from the Shakewell plugin template. Release steps: bump the version in the plugin header, `readme.txt` and the `VERSION` constant, then tag `vX.Y.Z`.
