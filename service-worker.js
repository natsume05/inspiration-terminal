// service-worker.js
const CACHE_NAME = 'inspiration-v1';
const urlsToCache = [
  '/',
  '/index.php',
  '/assets/css/index.css',
  '/assets/images/app-icon.png'
];

// 安装时：缓存关键文件
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('Opened cache');
        return cache.addAll(urlsToCache);
      })
  );
});

// 运行时：尝试从缓存读取（离线支持基础）
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response;
        }
        return fetch(event.request);
      })
  );
});