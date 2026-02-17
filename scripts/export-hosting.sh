#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

FRONTEND_DIR="${REPO_ROOT}/frontend"
DIST_DIR="${FRONTEND_DIR}/dist"

PHP_INDEX="${REPO_ROOT}/php/public/index.php"
PHP_HTACCESS="${REPO_ROOT}/php/public/.htaccess"
PHP_PROMPTS="${REPO_ROOT}/php/src/prompts.php"

OUT_DIR="${REPO_ROOT}/hosting"
ARCHIVE_PATH="${OUT_DIR}/hosting.zip"
ENV_PATH="${OUT_DIR}/.env"

preserved_env_file=""
if [[ -f "${ENV_PATH}" ]]; then
  preserved_env_file="$(mktemp)"
  cp "${ENV_PATH}" "${preserved_env_file}"
fi
trap '[[ -n "${preserved_env_file}" && -f "${preserved_env_file}" ]] && rm -f "${preserved_env_file}"' EXIT

echo "== Build frontend =="
pushd "${FRONTEND_DIR}" >/dev/null
if [[ ! -x "${FRONTEND_DIR}/node_modules/.bin/vite" ]]; then
  npm install --no-package-lock
fi
npm run build
popd >/dev/null

if [[ ! -d "${DIST_DIR}" ]]; then
  echo "Frontend build output not found: ${DIST_DIR}" >&2
  exit 1
fi

echo "== Prepare output folder =="
if [[ -d "${OUT_DIR}" ]]; then
  rm -rf "${OUT_DIR}"
fi
mkdir -p "${OUT_DIR}/src"

echo "== Copy PHP runtime files =="
cp -f "${PHP_INDEX}" "${OUT_DIR}/index.php"
cp -f "${PHP_HTACCESS}" "${OUT_DIR}/.htaccess"
cp -f "${PHP_PROMPTS}" "${OUT_DIR}/src/prompts.php"

echo "== Copy frontend build =="
cp -r "${DIST_DIR}/." "${OUT_DIR}/"

if [[ -n "${preserved_env_file}" ]]; then
  cp "${preserved_env_file}" "${ENV_PATH}"
  rm -f "${preserved_env_file}"
fi

echo "== Create archive =="
if command -v zip >/dev/null 2>&1; then
  TMP_ARCHIVE="${REPO_ROOT}/.tmp-hosting.zip"
  rm -f "${TMP_ARCHIVE}" "${ARCHIVE_PATH}"
  (cd "${OUT_DIR}" && zip -qr "${TMP_ARCHIVE}" .)
  mv -f "${TMP_ARCHIVE}" "${ARCHIVE_PATH}"
elif command -v tar >/dev/null 2>&1; then
  ARCHIVE_PATH="${OUT_DIR}/hosting.tar.gz"
  TMP_ARCHIVE="${REPO_ROOT}/.tmp-hosting.tar.gz"
  rm -f "${TMP_ARCHIVE}" "${ARCHIVE_PATH}"
  tar -czf "${TMP_ARCHIVE}" -C "${OUT_DIR}" .
  mv -f "${TMP_ARCHIVE}" "${ARCHIVE_PATH}"
else
  echo "Neither zip nor tar found. Install one utility to create archive." >&2
  exit 1
fi
echo "Archive created: ${ARCHIVE_PATH}"

echo ""
echo "Done."
echo "Upload the contents of: ${OUT_DIR}"
echo "Or upload the archive: ${ARCHIVE_PATH}"
echo "Create: ${ENV_PATH}  (OPENAI_API_KEY=...)"
