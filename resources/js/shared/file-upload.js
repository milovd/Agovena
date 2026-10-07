/**
 * File input label state shared by the Admin (x-ag.file-upload) and
 * storefront (x-store.file-upload) upload components. Presentation stays
 * in each component; only this behaviour is common.
 */
export function fileUploadState() {
    return {
        fileLabel: '',
        onChange(event) {
            const input = event.target;
            if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;
            const files = input.files;
            if (!files || files.length === 0) {
                this.fileLabel = '';
                return;
            }
            this.fileLabel = files.length === 1
                ? files[0].name
                : this.$root.dataset.filesSelectedLabel.replace(':count', String(files.length));
        },
    };
}
