import { fileUploadState } from '../shared/file-upload.js';

/** Customer account: file uploads, referrals, push notifications and account navigation (theme::account.*). */
export function registerAccountComponents(Alpine) {
    Alpine.data('storefrontFileUpload', fileUploadState);

    Alpine.data('storefrontReferral', () => ({
        copied: false,
        async copy() {
            await navigator.clipboard.writeText(this.$refs.link.value);
            this.copied = true;
            window.setTimeout(() => { this.copied = false; }, 2000);
        },
    }));

    Alpine.data('storefrontPushInstaller', () => ({
        configured: false,
        configUrl: '',
        subscribeUrl: '',
        unsubscribeUrl: '',
        messages: {},
        supported: false,
        subscribed: false,
        busy: false,
        status: '',
        registration: null,
        init() {
            this.configured = this.$root.dataset.configured === 'true';
            this.configUrl = this.$root.dataset.configUrl || '';
            this.subscribeUrl = this.$root.dataset.subscribeUrl || '';
            this.unsubscribeUrl = this.$root.dataset.unsubscribeUrl || '';
            this.messages = JSON.parse(this.$root.dataset.messages || '{}');
            this.supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
            if (!this.supported) {
                this.status = this.messages.unsupported;
                return;
            }
            if (!this.configured) this.status = this.messages.notConfigured;
            this.register();
        },
        async register() {
            try {
                this.registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                const subscription = await this.registration.pushManager.getSubscription();
                this.subscribed = Boolean(subscription);
            } catch {
                this.status = this.messages.failed;
            }
        },
        async install() {
            if (!this.supported || !this.configured) return;
            this.busy = true;
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    this.status = this.messages.permissionDenied;
                    return;
                }
                const configResponse = await fetch(this.configUrl, { headers: { Accept: 'application/json' } });
                const config = await configResponse.json();
                if (!config.configured || !config.publicKey) {
                    this.configured = false;
                    this.status = this.messages.notConfigured;
                    return;
                }
                const subscription = await this.registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.decodeKey(config.publicKey),
                });
                const response = await fetch(this.subscribeUrl, {
                    method: 'POST',
                    headers: this.headers(),
                    body: JSON.stringify(subscription.toJSON()),
                });
                if (!response.ok) throw new Error('subscription_failed');
                this.subscribed = true;
                this.status = this.messages.installed;
            } catch {
                this.status = this.messages.failed;
            } finally {
                this.busy = false;
            }
        },
        async remove() {
            this.busy = true;
            try {
                const subscription = await this.registration?.pushManager.getSubscription();
                if (subscription) {
                    await fetch(this.unsubscribeUrl, {
                        method: 'DELETE',
                        headers: this.headers(),
                        body: JSON.stringify({ endpoint: subscription.endpoint }),
                    });
                    await subscription.unsubscribe();
                }
                this.subscribed = false;
                this.status = this.messages.removed;
            } catch {
                this.status = this.messages.failed;
            } finally {
                this.busy = false;
            }
        },
        headers() {
            return {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            };
        },
        decodeKey(value) {
            const padding = '='.repeat((4 - (value.length % 4)) % 4);
            const normalized = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
            const raw = window.atob(normalized);
            return Uint8Array.from(raw, (character) => character.charCodeAt(0));
        },
    }));
    Alpine.data('storefrontAccountNav', () => ({
        open: false,
        init() {
            this.open = this.$root.dataset.open === 'true';
        },
        toggle() {
            this.open = !this.open;
        },
    }));
}
