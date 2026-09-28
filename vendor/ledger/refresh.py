"""Mirror the Ledger ESM graph from esm.sh into vendor/ledger/, rewriting
absolute imports to relative local ones. Re-runnable."""
import urllib.request, re, os, collections, posixpath, sys, hashlib

BASE = 'https://esm.sh'
OUT  = 'vendor/ledger'
ENTRIES = {
    '/@ledgerhq/hw-app-xrp@6.38.0':          'entry-hw-app-xrp.mjs',
    '/@ledgerhq/hw-transport-webhid@6.36.0': 'entry-webhid.mjs',
}

def local_for(path):
    if path in ENTRIES: return ENTRIES[path]
    p = path.lstrip('/')
    if not p.endswith('.mjs'): p += '.mjs'
    return p

bodies, q = {}, collections.deque(ENTRIES)
while q:
    path = q.popleft()
    if path in bodies: continue
    with urllib.request.urlopen(BASE + path, timeout=60) as r:
        bodies[path] = r.read().decode('utf8')
    for m in re.finditer(r'"(/[^"]+)"', bodies[path]):
        if m.group(1).startswith('/'): q.append(m.group(1))

os.makedirs(OUT, exist_ok=True)
total = 0
for path, body in bodies.items():
    here = local_for(path)
    def fix(m):
        dep = local_for(m.group(1))
        rel = posixpath.relpath(dep, posixpath.dirname(here) or '.')
        if not rel.startswith('.'): rel = './' + rel
        return '"' + rel + '"'
    body = re.sub(r'"(/[^"]+)"', fix, body)
    dest = os.path.join(OUT, here)
    os.makedirs(os.path.dirname(dest), exist_ok=True)
    open(dest, 'w').write(body)
    total += len(body)

print('vendored %d files, %.1f KB into %s' % (len(bodies), total/1024, OUT))
