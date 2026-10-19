{{-- Feedback flashed by an Admin action. Livewire re-renders the page component, not the layout, so pages render it themselves; the layout skips a message the page already shows. --}}
@if (session('status'))
    <p class="ag-alert ag-alert--success" role="status">{{ session('status') }}</p>
@endif
@if (session('error'))
    <p class="ag-alert ag-alert--danger" role="alert">{{ session('error') }}</p>
@endif
