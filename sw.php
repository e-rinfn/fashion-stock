<?php
// Set header sebagai JavaScript
header('Content-Type: application/javascript');

// Load base_url dari config
require_once __DIR__ . '/config/config.php';

// Generate service worker dengan dynamic base URL
?>
const BASE_URL = '<?= $base_url ?>';
const CACHE_NAME = "ipenk-legend-v5";
const STATIC_CACHE = "ipenk-static-v5";
const DYNAMIC_CACHE = "ipenk-dynamic-v5";

const staticAssets = [
BASE_URL + "/icons/Logo-Ipenk.png",
BASE_URL + "/assets/css/style.css",
BASE_URL + "/assets/css/style-preset.css",
];

// Install event - cache static resources only
self.addEventListener("install", (event) => {
event.waitUntil(
caches.open(STATIC_CACHE).then((cache) => {
console.log("Caching static assets");
return cache.addAll(staticAssets);
})
);
self.skipWaiting();
});

// Activate event - clean old caches
self.addEventListener("activate", (event) => {
event.waitUntil(
caches.keys().then((cacheNames) => {
return Promise.all(
cacheNames.map((cacheName) => {
if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
console.log("Deleting old cache:", cacheName);
return caches.delete(cacheName);
}
})
);
})
);
self.clients.claim();
});

// Fetch event - network first for dynamic content, cache first for static
self.addEventListener("fetch", (event) => {
const { request } = event;
const url = new URL(request.url);

// Skip non-GET requests
if (request.method !== "GET") return;

// Check if it's a PHP file or HTML page (dynamic content)
const isDynamic =
url.pathname.endsWith(".php") ||
url.pathname.includes("/api/") ||
request.mode === "navigate" ||
(request.headers.get("accept") || "").includes("text/html");

if (isDynamic) {
// Network-first strategy for dynamic content
event.respondWith(
fetch(request)
.then((response) => {
// Only cache successful responses
if (response && response.status === 200) {
const responseClone = response.clone();
caches.open(DYNAMIC_CACHE).then((cache) => {
cache.put(request, responseClone);
});
}
return response;
})
.catch(() => {
// Fallback to cache if offline
return caches.match(request).then((cached) => {
return cached || caches.match(BASE_URL + "/auth/login.php");
});
})
);
} else {
// Cache-first strategy for static assets (CSS, JS, images)
event.respondWith(
caches.match(request).then((cached) => {
if (cached) {
return cached;
}

return fetch(request).then((response) => {
if (
!response ||
response.status !== 200 ||
response.type !== "basic"
) {
return response;
}

const responseClone = response.clone();
caches.open(STATIC_CACHE).then((cache) => {
cache.put(request, responseClone);
});

return response;
});
})
);
}
});