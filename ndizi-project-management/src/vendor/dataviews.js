/**
 * Shared DataViews vendor bundle.
 *
 * WordPress core does not register a public `wp-dataviews` script handle (it
 * ships DataViews only bundled inside the editor packages — see
 * https://github.com/WordPress/gutenberg/issues/63657). Rather than re-bundle
 * @wordpress/dataviews into every admin script that needs it, we build it once
 * here into build/vendor-dataviews.js, exposed on the `window.ndiziDataViews`
 * global and registered in PHP as the `ndizi-dataviews` script handle. Any
 * Ndizi script can then declare `ndizi-dataviews` as a dependency and import
 * from `@wordpress/dataviews/wp` (which webpack.config.js maps to the global).
 *
 * The `/wp` entry point keeps the other @wordpress/* packages (react,
 * wp-components, wp-private-apis, …) external so this bundle reuses the copies
 * WordPress already ships. We also pull in DataViews' own stylesheet here so it
 * is extracted once alongside the bundle.
 */
import '@wordpress/dataviews/build-style/style.css';
export * from '@wordpress/dataviews/wp';
