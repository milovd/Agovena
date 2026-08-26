self.addEventListener('push', (event) => {
    const data = event.data ? event.data.json() : {};
    const title = typeof data.title === 'string' && data.title !== '' ? data.title : 'Agovena';
    const body = typeof data.body === 'string' ? data.body : '';
    const requestedUrl = typeof data.url === 'string' ? data.url : '/';
    let url = '/';

    try {
        const parsedUrl = new URL(requestedUrl, self.location.origin);
        if (parsedUrl.origin === self.location.origin) {
            url = parsedUrl.pathname + parsedUrl.search + parsedUrl.hash;
        }
    } catch (error) {
        url = '/';
    }

    event.waitUntil(self.registration.showNotification(title, {
        body,
        icon: '/vendor/agovena/logo.png',
        badge: '/vendor/agovena/logo.png',
        data: { url },
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
            for (const client of clientList) {
                if ('focus' in client && client.url.includes(self.location.origin)) {
                    client.navigate(url);
                    return client.focus();
                }
            }

            return clients.openWindow(url);
        }),
    );
});
