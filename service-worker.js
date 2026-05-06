// Minimal service worker — exists to satisfy Android PWA installability criteria.
// No caching: every request goes straight to the network. This keeps the live
// site fully dynamic (NFT image swaps, balance updates, raid state etc.) and
// avoids cache-busting headaches. Caching can be layered on later if needed.

self.addEventListener('install', function() {
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function() {
  // Intentionally no-op — let the browser handle the request normally.
});
