self.addEventListener('install', (_event) => {
	self.skipWaiting();
});

self.addEventListener('push', (event) => {
	json = event.data.json();
	self.registration.showNotification(json.title, {
		body: json.body,
		timestamp: json.timestamp,
		tag: json.tag,
		renotify: true,
	});
});

self.addEventListener('notificationclick', (event) => {
	event.notification.close();
	event.waitUntil(
		clients.openWindow('http://localhost/')
	);
});
