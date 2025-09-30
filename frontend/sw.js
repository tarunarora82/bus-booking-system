/**
 * Service Worker for Bus Booking System PWA
 * Handles caching, offline functionality, and background sync
 */

const CACHE_NAME = 'bus-booking-v1.0.0';
const STATIC_CACHE_NAME = 'bus-booking-static-v1.0.0';
const DYNAMIC_CACHE_NAME = 'bus-booking-dynamic-v1.0.0';
const API_CACHE_NAME = 'bus-booking-api-v1.0.0';

// Static assets to cache immediately
const STATIC_ASSETS = [
    '/',
    '/index.html',
    '/manifest.json',
    '/assets/css/styles.css',
    '/assets/js/config.js',
    '/assets/js/utils.js',
    '/assets/js/api.js',
    '/assets/js/components.js',
    '/assets/js/app.js',
    '/assets/images/icon-192.png',
    '/assets/images/icon-512.png',
    '/assets/images/bus-icon.svg',
    '/offline.html',
    '    // No external resources - everything is local'
];

// API endpoints to cache
const API_ENDPOINTS = [
    '/api/schedules',
    '/api/availability',
    '/api/booking-status'
];

// Cache duration in milliseconds
const CACHE_DURATION = {
    STATIC: 7 * 24 * 60 * 60 * 1000, // 7 days
    DYNAMIC: 24 * 60 * 60 * 1000,    // 1 day
    API: 5 * 60 * 1000               // 5 minutes
};

/**
 * Install event - cache static assets
 */
self.addEventListener('install', (event) => {
    console.log('Service Worker: Installing...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE_NAME)
            .then((cache) => {
                console.log('Service Worker: Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('Service Worker: Static assets cached');
                return self.skipWaiting();
            })
            .catch((error) => {
                console.error('Service Worker: Failed to cache static assets', error);
            })
    );
});

/**
 * Activate event - clean up old caches
 */
self.addEventListener('activate', (event) => {
    console.log('Service Worker: Activating...');
    
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                const deletePromises = cacheNames
                    .filter((cacheName) => {
                        return cacheName !== STATIC_CACHE_NAME &&
                               cacheName !== DYNAMIC_CACHE_NAME &&
                               cacheName !== API_CACHE_NAME;
                    })
                    .map((cacheName) => {
                        console.log('Service Worker: Deleting old cache', cacheName);
                        return caches.delete(cacheName);
                    });
                
                return Promise.all(deletePromises);
            })
            .then(() => {
                console.log('Service Worker: Activated');
                return self.clients.claim();
            })
            .catch((error) => {
                console.error('Service Worker: Activation failed', error);
            })
    );
});

/**
 * Fetch event - handle requests with cache-first or network-first strategy
 */
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Skip non-GET requests and chrome-extension requests
    if (request.method !== 'GET' || url.protocol === 'chrome-extension:') {
        return;
    }
    
    // Handle different types of requests
    if (isStaticAsset(request)) {
        event.respondWith(handleStaticAsset(request));
    } else if (isAPIRequest(request)) {
        event.respondWith(handleAPIRequest(request));
    } else {
        event.respondWith(handleDynamicRequest(request));
    }
});

/**
 * Background sync for offline bookings
 */
self.addEventListener('sync', (event) => {
    console.log('Service Worker: Background sync triggered', event.tag);
    
    if (event.tag === 'booking-sync') {
        event.waitUntil(syncOfflineBookings());
    }
});

/**
 * Push notification handler
 */
self.addEventListener('push', (event) => {
    console.log('Service Worker: Push notification received');
    
    const options = {
        body: 'Your booking status has been updated',
        icon: '/assets/images/icon-192.png',
        badge: '/assets/images/icon-192.png',
        vibrate: [200, 100, 200],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'View Details',
                icon: '/assets/images/checkmark.png'
            },
            {
                action: 'close',
                title: 'Close',
                icon: '/assets/images/xmark.png'
            }
        ]
    };
    
    if (event.data) {
        const data = event.data.json();
        options.body = data.message || options.body;
        options.data = { ...options.data, ...data };
    }
    
    event.waitUntil(
        self.registration.showNotification('Bus Booking Update', options)
    );
});

/**
 * Notification click handler
 */
self.addEventListener('notificationclick', (event) => {
    console.log('Service Worker: Notification clicked', event.action);
    
    event.notification.close();
    
    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/')
        );
    }
});

/**
 * Check if request is for a static asset
 */
function isStaticAsset(request) {
    const url = new URL(request.url);
    return STATIC_ASSETS.some(asset => {
        if (asset.startsWith('http')) {
            return request.url === asset;
        }
        return url.pathname === asset || url.pathname.startsWith('/assets/');
    });
}

/**
 * Check if request is for an API endpoint
 */
function isAPIRequest(request) {
    const url = new URL(request.url);
    return url.pathname.startsWith('/api/');
}

/**
 * Handle static asset requests with cache-first strategy
 */
async function handleStaticAsset(request) {
    try {
        // Try cache first
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            // Check if cache is still valid
            const cacheTime = cachedResponse.headers.get('sw-cache-time');
            if (cacheTime && Date.now() - parseInt(cacheTime) < CACHE_DURATION.STATIC) {
                return cachedResponse;
            }
        }
        
        // Fetch from network
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache the response
            const cache = await caches.open(STATIC_CACHE_NAME);
            const responseToCache = networkResponse.clone();
            
            // Add timestamp header
            const headers = new Headers(responseToCache.headers);
            headers.set('sw-cache-time', Date.now().toString());
            
            const cachedResponse = new Response(responseToCache.body, {
                status: responseToCache.status,
                statusText: responseToCache.statusText,
                headers: headers
            });
            
            await cache.put(request, cachedResponse);
        }
        
        return networkResponse;
        
    } catch (error) {
        console.log('Service Worker: Network failed for static asset, serving from cache', error);
        
        // Return cached version if network fails
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            return caches.match('/offline.html');
        }
        
        throw error;
    }
}

/**
 * Handle API requests with network-first strategy
 */
async function handleAPIRequest(request) {
    try {
        // Try network first for fresh data
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            // Cache successful API responses
            const cache = await caches.open(API_CACHE_NAME);
            const responseToCache = networkResponse.clone();
            
            // Add timestamp header
            const headers = new Headers(responseToCache.headers);
            headers.set('sw-cache-time', Date.now().toString());
            
            const cachedResponse = new Response(responseToCache.body, {
                status: responseToCache.status,
                statusText: responseToCache.statusText,
                headers: headers
            });
            
            await cache.put(request, cachedResponse);
        }
        
        return networkResponse;
        
    } catch (error) {
        console.log('Service Worker: Network failed for API request, trying cache', error);
        
        // Fallback to cache
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            // Check if cache is still reasonable (not too old)
            const cacheTime = cachedResponse.headers.get('sw-cache-time');
            if (!cacheTime || Date.now() - parseInt(cacheTime) < CACHE_DURATION.API * 10) {
                // Add offline indicator header
                const headers = new Headers(cachedResponse.headers);
                headers.set('x-served-from-cache', 'true');
                
                return new Response(cachedResponse.body, {
                    status: cachedResponse.status,
                    statusText: cachedResponse.statusText,
                    headers: headers
                });
            }
        }
        
        // Return offline response for API requests
        return new Response(JSON.stringify({
            success: false,
            message: 'You are offline. Please check your connection and try again.',
            error_code: 'OFFLINE',
            data: null
        }), {
            status: 503,
            headers: {
                'Content-Type': 'application/json',
                'x-served-from-cache': 'false'
            }
        });
    }
}

/**
 * Handle dynamic requests with cache-first strategy
 */
async function handleDynamicRequest(request) {
    try {
        // Try cache first for navigation requests
        if (request.mode === 'navigate') {
            const cachedResponse = await caches.match(request);
            if (cachedResponse) {
                return cachedResponse;
            }
        }
        
        // Fetch from network
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok && request.url.startsWith(self.location.origin)) {
            // Cache successful responses from our origin
            const cache = await caches.open(DYNAMIC_CACHE_NAME);
            await cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
        
    } catch (error) {
        console.log('Service Worker: Network failed for dynamic request', error);
        
        // Try cache for any request
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Return offline page for navigation requests
        if (request.mode === 'navigate') {
            return caches.match('/offline.html');
        }
        
        throw error;
    }
}

/**
 * Sync offline bookings when back online
 */
async function syncOfflineBookings() {
    try {
        console.log('Service Worker: Syncing offline bookings...');
        
        // Get offline bookings from IndexedDB or localStorage
        const offlineBookings = await getOfflineBookings();
        
        if (offlineBookings.length === 0) {
            console.log('Service Worker: No offline bookings to sync');
            return;
        }
        
        const syncResults = [];
        
        for (const booking of offlineBookings) {
            try {
                const response = await fetch('/api/bookings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(booking)
                });
                
                if (response.ok) {
                    syncResults.push({ booking, success: true });
                    await removeOfflineBooking(booking.id);
                } else {
                    syncResults.push({ booking, success: false, error: 'Server error' });
                }
                
            } catch (error) {
                syncResults.push({ booking, success: false, error: error.message });
            }
        }
        
        // Notify the main app about sync results
        const clients = await self.clients.matchAll();
        clients.forEach(client => {
            client.postMessage({
                type: 'BOOKING_SYNC_COMPLETE',
                results: syncResults
            });
        });
        
        console.log('Service Worker: Booking sync completed', syncResults);
        
    } catch (error) {
        console.error('Service Worker: Booking sync failed', error);
    }
}

/**
 * Get offline bookings from storage
 */
async function getOfflineBookings() {
    // This would typically use IndexedDB
    // For now, return empty array as we haven't implemented offline booking storage
    return [];
}

/**
 * Remove offline booking from storage
 */
async function removeOfflineBooking(bookingId) {
    // This would typically remove from IndexedDB
    console.log('Service Worker: Removed offline booking', bookingId);
}

/**
 * Clean old cache entries
 */
async function cleanOldCacheEntries() {
    const cacheNames = [STATIC_CACHE_NAME, DYNAMIC_CACHE_NAME, API_CACHE_NAME];
    
    for (const cacheName of cacheNames) {
        const cache = await caches.open(cacheName);
        const requests = await cache.keys();
        
        for (const request of requests) {
            const response = await cache.match(request);
            const cacheTime = response.headers.get('sw-cache-time');
            
            if (cacheTime) {
                const age = Date.now() - parseInt(cacheTime);
                let maxAge = CACHE_DURATION.DYNAMIC;
                
                if (cacheName === STATIC_CACHE_NAME) {
                    maxAge = CACHE_DURATION.STATIC;
                } else if (cacheName === API_CACHE_NAME) {
                    maxAge = CACHE_DURATION.API;
                }
                
                if (age > maxAge) {
                    console.log('Service Worker: Removing old cache entry', request.url);
                    await cache.delete(request);
                }
            }
        }
    }
}

// Clean old cache entries periodically
setInterval(cleanOldCacheEntries, 60 * 60 * 1000); // Every hour

console.log('Service Worker: Script loaded');