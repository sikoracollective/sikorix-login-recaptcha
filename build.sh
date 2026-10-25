#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="sikorix-login-recaptcha"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_ZIP="${ROOT_DIR}/${PLUGIN_SLUG}.zip"
TEMP_DIR="$(mktemp -d "${TMPDIR:-/tmp}/${PLUGIN_SLUG}-build.XXXXXX")"
STAGE_DIR="${TEMP_DIR}/${PLUGIN_SLUG}"

cleanup() {
	rm -rf "${TEMP_DIR}"
}
trap cleanup EXIT

mkdir -p "${STAGE_DIR}"

# files/directories included in the installable plugin zip
INCLUDE_PATHS=(
	"sikorix-login-recaptcha.php"
	"includes"
	"uninstall.php"
	"license.txt"
	"readme.txt"
)

echo "Building ${PLUGIN_SLUG}.zip"

listed_files=()

for path in "${INCLUDE_PATHS[@]}"; do
	src="${ROOT_DIR}/${path}"
	if [[ ! -e "${src}" ]]; then
		echo "error: missing required path: ${path}" >&2
		exit 1
	fi

	cp -R "${src}" "${STAGE_DIR}/"

	if [[ -d "${src}" ]]; then
		# collect each file under directories so the build output is explicit
		while IFS= read -r -d '' file; do
			listed_files+=( "${file#${STAGE_DIR}/}" )
		done < <(find "${STAGE_DIR}/${path}" -type f -print0 | sort -z)
	else
		listed_files+=( "${path}" )
	fi
done

rm -f "${DIST_ZIP}"

(
	cd "${TEMP_DIR}"
	zip -r "${DIST_ZIP}" "${PLUGIN_SLUG}" >/dev/null
)

echo "Added ${#listed_files[@]} files to the zip file:"
for file in "${listed_files[@]}"; do
	echo "  ${file}"
done
# temp dir removed by trap on exit
