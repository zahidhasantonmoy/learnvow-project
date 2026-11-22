/* LearnVow offline cache (spec §1.7, A20).
 * Caches ONLY downloaded EPUB responses (the signed-file route),
 * cache-first. NEVER caches payment, account, admin or any other
 * page — those always go to the network. */
const CACHE_NAME = 'learnvow-epub-v1';

self.addEventListener('fetch', (event) => {
  const url = new URL(event.request.url);
  if (event.request.method !== 'GET') return;
  if (!url.pathname.includes('/signed-file/')) return;

  event.respondWith(
    caches.open(CACHE_NAME).then((cache) =>
      cache.match(event.request).then(
        (cached) =>
          cached ||
          fetch(event.request).then((response) => {
            if (response.ok) cache.put(event.request, response.clone());
            return response;
          })
      )
    )
  );
});
