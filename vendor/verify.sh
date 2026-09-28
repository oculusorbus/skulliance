#!/bin/sh
# vendor/verify.sh — check vendored wallet code against npm's published hashes.
#
# Vendoring removes the CDN from the RUNTIME trust path. It does not, by
# itself, prove that what was fetched at vendoring time was authentic -- a
# compromised CDN at that moment would simply be baked in permanently, which
# is arguably worse because it then never changes.
#
# So the chain has to reach something the CDN does not control. npm publishes
# a SHA-512 integrity hash per package version; this verifies the tarball
# against it and then verifies our copy byte-for-byte against the tarball.
#
#   registry integrity  ->  tarball  ->  the file we serve
#
# Run from the repo root:  sh vendor/verify.sh
set -e
TMP=$(mktemp -d); trap 'rm -rf "$TMP"' EXIT
fail=0

check() {  # package version path-in-tarball local-file
	name=$1; ver=$2; inner=$3; local_file=$4
	meta=$(curl -sS "https://registry.npmjs.org/$name/$ver")
	tarball=$(printf '%s' "$meta" | python3 -c "import json,sys;print(json.load(sys.stdin)['dist']['tarball'])")
	want=$(printf '%s' "$meta" | python3 -c "import json,sys;print(json.load(sys.stdin)['dist'].get('integrity',''))")
	curl -sS "$tarball" -o "$TMP/pkg.tgz"
	got=$(python3 -c "
import hashlib,base64
print('sha512-'+base64.b64encode(hashlib.sha512(open('$TMP/pkg.tgz','rb').read()).digest()).decode())")
	if [ "$want" != "$got" ]; then
		echo "  FAIL  $name@$ver tarball does not match the registry integrity hash"; fail=1; return
	fi
	rm -rf "$TMP/x"; mkdir "$TMP/x"; tar xzf "$TMP/pkg.tgz" -C "$TMP/x"
	if cmp -s "$TMP/x/$inner" "$local_file"; then
		echo "  ok    $local_file"
	else
		echo "  FAIL  $local_file differs from $name@$ver:$inner"; fail=1
	fi
}

echo "Verifying vendored wallet code against npm..."
check "@crossmarkio/sdk" 0.4.0 "package/pack/umd/index.js"    vendor/xrpl/crossmark-sdk-0.4.0.umd.js
check "@gemwallet/api"   3.8.0 "package/umd/gemwallet-api.js" vendor/xrpl/gemwallet-api-3.8.0.umd.js

echo
echo "NOT VERIFIED: vendor/ledger/"
echo "  Those files came from esm.sh, which TRANSPILES the source and rewrites"
echo "  its imports, so they cannot match an npm tarball by construction."
echo "  See vendor/ledger/UNVERIFIED.md."

[ $fail -eq 0 ] && echo "\nall verifiable files match npm" || echo "\nFAILURES ABOVE"
exit $fail
