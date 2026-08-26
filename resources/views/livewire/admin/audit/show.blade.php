@php
    $severityClass = $auditLog->severity === 'critical' ? 'danger' : ($auditLog->severity === 'warning' ? 'warning' : 'muted');
    $jsonOptions = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
@endphp

<div class="admin-page audit-log audit-log-detail">
    <x-ag.page-header :heading="__('admin.audit.detail_title')" :lede="$auditLog->action">
        <x-slot:breadcrumbs>
            <x-ag.breadcrumbs :items="[
                ['label' => __('admin.audit.title'), 'url' => route('admin.audit.index')],
                ['label' => $auditLog->action],
            ]" />
        </x-slot:breadcrumbs>
        <x-slot:back>
            <x-ag.back :href="route('admin.audit.index')" :label="__('admin.audit.back_to_list')" />
        </x-slot:back>
        <x-slot:actions>
            <span class="ag-badge ag-badge--{{ $severityClass }}">{{ __('admin.audit.severities.'.$auditLog->severity) }}</span>
            <span class="ag-badge">{{ __('admin.audit.outcomes.'.$auditLog->outcome) }}</span>
        </x-slot:actions>
    </x-ag.page-header>

    <div class="audit-log-detail__layout">
        <section class="admin-panel audit-log-detail__summary">
            <div class="audit-log-detail__summary-top">
                <div>
                    <p class="audit-log__eyebrow">{{ __('admin.audit.detail_eyebrow') }}</p>
                    <h2>{{ $auditLog->action }}</h2>
                    <p class="audit-log-detail__event-id"><code>{{ $auditLog->event_id }}</code></p>
                </div>
                <span class="audit-log-detail__status">{{ __('admin.audit.read_only') }}</span>
            </div>

            <dl class="audit-log-detail__facts">
                <div>
                    <dt>{{ __('admin.audit.time') }}</dt>
                    <dd>{{ $auditLog->created_at?->toDayDateTimeString() }}</dd>
                </div>
                <div>
                    <dt>{{ __('admin.audit.category') }}</dt>
                    <dd>{{ __('admin.audit.categories.'.$auditLog->category) }}</dd>
                </div>
                <div>
                    <dt>{{ __('admin.audit.actor') }}</dt>
                    <dd>{{ __('admin.audit.actor_types.'.$auditLog->actor_type) }}{{ $auditLog->actor_id ? ' #'.$auditLog->actor_id : '' }}</dd>
                </div>
                <div>
                    <dt>{{ __('admin.audit.subject') }}</dt>
                    <dd>{{ $auditLog->subject_type ? class_basename($auditLog->subject_type).' #'.$auditLog->subject_id : __('common.em_dash') }}</dd>
                </div>
                <div>
                    <dt>{{ __('admin.audit.request_id') }}</dt>
                    <dd><code>{{ $auditLog->request_id ?: __('common.em_dash') }}</code></dd>
                </div>
                <div>
                    <dt>{{ __('admin.audit.correlation_id') }}</dt>
                    <dd><code>{{ $auditLog->correlation_id ?: __('common.em_dash') }}</code></dd>
                </div>
            </dl>
        </section>

        <aside class="audit-log-detail__side">
            <section class="admin-panel">
                <h2 class="admin-panel__title">{{ __('admin.audit.integrity') }}</h2>
                <div class="audit-log-detail__integrity {{ $auditLog->integrityIsValid() ? 'is-valid' : 'is-legacy' }}">
                    <span class="audit-log-detail__integrity-dot" aria-hidden="true"></span>
                    <div>
                        <strong>{{ $auditLog->integrityIsValid() ? __('admin.audit.integrity_valid') : __('admin.audit.integrity_unavailable') }}</strong>
                        <p>{{ __('admin.audit.integrity_help') }}</p>
                    </div>
                </div>
            </section>

            <section class="admin-panel">
                <h2 class="admin-panel__title">{{ __('admin.audit.network_context') }}</h2>
                <dl class="audit-log-detail__compact-facts">
                    <div><dt>{{ __('admin.audit.method') }}</dt><dd><code>{{ $auditLog->method ?: __('common.em_dash') }}</code></dd></div>
                    <div><dt>{{ __('admin.audit.ip') }}</dt><dd><code>{{ $auditLog->ip ?: __('common.em_dash') }}</code></dd></div>
                    <div><dt>{{ __('admin.audit.user_agent') }}</dt><dd>{{ $auditLog->user_agent ?: __('common.em_dash') }}</dd></div>
                    <div><dt>{{ __('admin.audit.route') }}</dt><dd><code>{{ $auditLog->route ?: __('common.em_dash') }}</code></dd></div>
                </dl>
            </section>
        </aside>
    </div>

    <section class="audit-log-detail__payloads" aria-labelledby="audit-payload-heading">
        <div class="audit-log-detail__section-heading">
            <div>
                <p class="audit-log__eyebrow">{{ __('admin.audit.payload_eyebrow') }}</p>
                <h2 id="audit-payload-heading">{{ __('admin.audit.payload_heading') }}</h2>
            </div>
            <p>{{ __('admin.audit.payload_help') }}</p>
        </div>

        <div class="audit-log-detail__payload-grid">
            @foreach (['properties' => 'properties', 'before' => 'before', 'after' => 'after', 'context' => 'context'] as $field => $label)
                <section class="admin-panel audit-log-detail__payload">
                    <header class="audit-log-detail__payload-heading">
                        <h3>{{ __('admin.audit.'.$label) }}</h3>
                        <span>{{ is_countable($auditLog->{$field}) ? count($auditLog->{$field}) : 0 }}</span>
                    </header>
                    <pre>{{ json_encode($auditLog->{$field} ?? [], $jsonOptions) }}</pre>
                </section>
            @endforeach
        </div>
    </section>
</div>
