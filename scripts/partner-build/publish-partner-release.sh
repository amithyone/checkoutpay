#!/usr/bin/env bash
# CheckoutPay partner release publisher (run from plaintext checkoutpay on a tagged release).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
MANIFEST_JSON="${ROOT}/scripts/partner-build/encode-manifest.json"
BUILD_MANIFEST="${ROOT}/partner-build-manifest.json"
STAGING="${PARTNER_BUILD_STAGING:-${ROOT}/../partner-release-staging}"
IONCUBE_ENCODER="${IONCUBE_ENCODER:-ioncube_encoder}"

VERSION="${1:-}"
PARTNER_REPO="${PARTNER_RELEASE_GIT_REMOTE:-}"

if [[ -z "${VERSION}" ]]; then
  echo "Usage: $0 <semver> [partner-git-remote-url]" >&2
  echo "  Example: PARTNER_RELEASE_GIT_REMOTE=git@github.com:org/partner-checkout.git $0 1.0.0" >&2
  exit 1
fi

if [[ ! -f "${MANIFEST_JSON}" ]]; then
  echo "Missing ${MANIFEST_JSON}" >&2
  exit 1
fi

if ! command -v "${IONCUBE_ENCODER}" >/dev/null 2>&1; then
  echo "ionCube encoder not found (${IONCUBE_ENCODER}). Refusing to ship plaintext core." >&2
  exit 1
fi

if [[ ! -f "${ROOT}/composer.json" ]]; then
  echo "Run from checkout project root (composer.json missing)." >&2
  exit 1
fi

echo "==> Staging partner release ${VERSION} in ${STAGING}"
rm -rf "${STAGING}"
mkdir -p "${STAGING}"

rsync -a --delete \
  --exclude '.git' \
  --exclude 'node_modules' \
  --exclude 'vendor' \
  --exclude 'storage/logs/*' \
  --exclude 'storage/framework/cache/*' \
  --exclude 'storage/framework/sessions/*' \
  --exclude 'storage/framework/views/*' \
  "${ROOT}/" "${STAGING}/"

cd "${STAGING}"
composer install --no-dev --optimize-autoloader --no-interaction

ENCODE_DIRS=$(php -r '$m=json_decode(file_get_contents($argv[1]),true); echo implode(" ", $m["encode_directories"] ?? []);' "${MANIFEST_JSON}")

for dir in ${ENCODE_DIRS}; do
  if [[ -d "${STAGING}/${dir}" ]]; then
    echo "==> Encoding ${dir}"
    "${IONCUBE_ENCODER}" --encode "${STAGING}/${dir}" --into "${STAGING}/${dir}" --replace-target
  fi
done

# Restore readable partner ops commands (never encoded).
while IFS= read -r rel; do
  rel="${rel//\"/}"
  rel="${rel// /}"
  [[ -z "${rel}" ]] && continue
  src="${ROOT}/${rel}"
  dest="${STAGING}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "$(dirname "${dest}")"
    cp "${src}" "${dest}"
    echo "==> Restored readable ${rel}"
  fi
done < <(php -r '$m=json_decode(file_get_contents($argv[1]),true); foreach($m["readable_command_sources"]??[] as $p){echo $p,PHP_EOL;}' "${MANIFEST_JSON}")

# Remove any leftover plaintext PHP under encoded dirs except excluded files.
EXCLUDE_LIST=$(mktemp)
php -r '$m=json_decode(file_get_contents($argv[1]),true); foreach(array_merge($m["exclude_files"]??[], $m["readable_command_sources"]??[]) as $p){echo $p,PHP_EOL;}' "${MANIFEST_JSON}" > "${EXCLUDE_LIST}"

find ${ENCODE_DIRS} -type f -name '*.php' 2>/dev/null | while read -r f; do
  rel="${f#${STAGING}/}"
  if grep -Fxq "${rel}" "${EXCLUDE_LIST}"; then
    continue
  fi
  if ! head -n 3 "${f}" | grep -qi 'ioncube'; then
    echo "Plaintext core still present after encode: ${rel}" >&2
    exit 1
  fi
done
rm -f "${EXCLUDE_LIST}"

echo "==> Generating checksums"
CHECKSUMS_JSON=$(mktemp)
echo '{' > "${CHECKSUMS_JSON}"
first=1
while IFS= read -r f; do
  rel="${f#${STAGING}/}"
  hash="sha256:$(sha256sum "${f}" | awk '{print $1}')"
  if [[ ${first} -eq 1 ]]; then first=0; else echo ',' >> "${CHECKSUMS_JSON}"; fi
  printf '  "%s": "%s"' "${rel}" "${hash}" >> "${CHECKSUMS_JSON}"
done < <(find ${ENCODE_DIRS} -type f -name '*.php' 2>/dev/null | sort)
echo '' >> "${CHECKSUMS_JSON}"
echo '}' >> "${CHECKSUMS_JSON}"

BUILD_ID="$(git -C "${ROOT}" rev-parse --short HEAD 2>/dev/null || echo "local")"
GENERATED_AT="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

php -r '
$checksums = json_decode(file_get_contents($argv[1]), true) ?: [];
$out = [
  "product" => "checkoutpay-partner",
  "version" => $argv[2],
  "build_id" => $argv[3],
  "requires_ioncube" => true,
  "generated_at" => $argv[4],
  "checksums" => $checksums,
];
file_put_contents($argv[5], json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
' "${CHECKSUMS_JSON}" "${VERSION}" "${BUILD_ID}" "${GENERATED_AT}" "${BUILD_MANIFEST}"

rm -f "${CHECKSUMS_JSON}"

php artisan partner:verify-build || {
  echo "Build verification failed before publish." >&2
  exit 1
}

if [[ -n "${PARTNER_REPO}" ]]; then
  echo "==> Pushing tag release/${VERSION} to ${PARTNER_REPO}"
  (
    cd "${STAGING}"
    git init -q
    git add -A
    git commit -q -m "Partner encoded release ${VERSION}"
    git tag "release/${VERSION}"
    git remote add partner "${PARTNER_REPO}"
    git push -u partner HEAD
    git push partner "release/${VERSION}"
  )
else
  echo "==> Staged build ready at ${STAGING} (set PARTNER_RELEASE_GIT_REMOTE to push)"
fi

echo "Done: partner release ${VERSION}"
