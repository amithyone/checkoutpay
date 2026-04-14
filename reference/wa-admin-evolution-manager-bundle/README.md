# Evolution Manager (wa-admin) — deployed bundle backup

This folder stores the **minified production JavaScript** served by the Evolution Manager UI behind **`wa-admin`** (CheckoutNow branding), plus the **unpatched** copy taken from the same container before the display change.

## Why this exists

The live manager runs from **Docker** (`evolution_manager`), with assets under `/usr/share/nginx/html/assets/`. The chat list and headers used `pushName || phone`, which often showed **only the number**. The patched bundle shows **`Name · phone`** when `pushName` is present and is not just the digits of the JID.

## Files

| File | Description |
|------|-------------|
| `assets/index-B_oZUlX_.js` | **Patched** bundle in use after the change (April 2026). |
| `assets/index-B_oZUlX_.js.pre-patch-backup` | Original bundle from the container (`.bak`) before patching. |
| `apply-name-phone-labels.mjs` | Node script that reapplies the same string replacements to a **pre-patch** file (run if you restore from backup and need the behaviour again). |

**Note:** Vite changes the hashed filename (`index-*.js`) on each build. After a rebuild, copy the new main bundle path from `index.html`, replace the `pre-patch-backup` input file name in the script if needed, run the script, then deploy the output into the container’s `assets/` directory and hard-refresh the browser.

## Deploying a restored or re-patched file

```bash
docker exec -i evolution_manager sh -c 'cat > /usr/share/nginx/html/assets/index-B_oZUlX_.js' < assets/index-B_oZUlX_.js
```

Use the actual asset filename referenced in `/usr/share/nginx/html/index.html` if it differs after a rebuild.

## Source tree

The editable app lives under **`/var/www/whatsapp-gateway/evolution-manager-src/`** (separate clone of Evolution Manager v2). Prefer implementing the same logic in TypeScript there and rebuilding the image when the project becomes buildable again; this backup is for **operations and regression reference** only.
