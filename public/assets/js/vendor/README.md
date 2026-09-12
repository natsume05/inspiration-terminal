# Vendored browser libraries

These files are checked in rather than installed, because the project has no
build step and no package manager. Loading them from a third-party CDN is
deliberately avoided: the Content-Security-Policy sent by the application
restricts `script-src` to `'self'`, and a page should not depend on a host
outside its control to render its own content.

| File | Library | Version | License | Upstream |
|---|---|---|---|---|
| `marked.min.js` | marked | 12.0.0 | MIT | https://github.com/markedjs/marked |
| `purify.min.js` | DOMPurify | 3.0.6 | Apache-2.0 OR MPL-2.0 | https://github.com/cure53/DOMPurify |

Both bundles retain their upstream license banner in the first lines of the file.

## Why both are needed

`marked` converts Markdown to HTML. It performs **no** sanitisation and its own
documentation says so. Feeding its output straight into a page is a
cross-site-scripting hole, because Markdown permits raw HTML: a single
`<img src=x onerror=...>` in a blog entry would execute.

`DOMPurify` sanitises that HTML, which is why the rendering path in
`../markdown.js` is always `marked` → `DOMPurify` → DOM, and never `marked` →
DOM.

## Updating

Replace the file, update the version in this table, and confirm the banner at the
top of the bundle still states a license. `marked` and `DOMPurify` are both
published as UMD bundles, which is why they are loaded with a plain `<script>`
tag rather than imported as an ES module.
