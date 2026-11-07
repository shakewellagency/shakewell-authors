# Shakewell Multiple Authors

> **Made by [Shakewell](https://shakewell.agency).** Free and open source, built and maintained by the Shakewell team.

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

## Made by Shakewell

This plugin is one of a family of free WordPress plugins from [Shakewell](https://shakewell.agency). Each one does one job, keeps its dependencies small and is released under GPL-2.0-or-later.

Use it, fork it and send fixes. To have it set up on your site, or to have a plugin built, [talk to Shakewell](https://shakewell.agency).

More from Shakewell:

- [Shakewell Activity Log](https://github.com/shakewellagency/shakewell-activity-log)
- [Shakewell Clone Posts](https://github.com/shakewellagency/shakewell-clone-posts)
- [Shakewell Connector for Klaviyo and Gravity Forms](https://github.com/shakewellagency/shakewell-klaviyo-connector)
- [Shakewell Media Offload for Amazon S3](https://github.com/shakewellagency/shakewell-media-offload)
- [Shakewell Redirects](https://github.com/shakewellagency/shakewell-redirects)
- [Shakewell Two-way Relationships for ACF](https://github.com/shakewellagency/shakewell-relationship-sync)
- [Shakewell Single Sign-On (SAML)](https://github.com/shakewellagency/shakewell-saml-sso)
- [Shakewell Error Reporting for Sentry](https://github.com/shakewellagency/shakewell-sentry)
- [Shakewell Mailer for Amazon SES](https://github.com/shakewellagency/shakewell-ses-mailer)
- [Shakewell Sitemap](https://github.com/shakewellagency/shakewell-sitemap)
- [Shakewell plugin template](https://github.com/shakewellagency/shakewell-plugin-template), the starting point for every plugin
