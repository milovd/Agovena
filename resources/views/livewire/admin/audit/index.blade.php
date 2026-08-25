<div class="admin-page">
    <x-ag.page-header :heading="__('admin.audit.title')" :lede="__('admin.audit.lede')" />

    <div class="ag-toolbar ag-toolbar--filters">
        <div class="ag-toolbar__filters">
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="audit-search">{{ __('admin.audit.search_label') }}</label>
                <input id="audit-search" class="ag-input ag-input--search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('admin.audit.search_placeholder') }}">
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="audit-category">{{ __('admin.audit.category') }}</label>
                <select id="audit-category" class="ag-select" wire:model.live="category">
                    <option value="">{{ __('admin.audit.all_categories') }}</option>
                    @foreach ($categories as $value)
                        <option value="{{ $value }}">{{ __('admin.audit.categories.'.$value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="audit-severity">{{ __('admin.audit.severity') }}</label>
                <select id="audit-severity" class="ag-select" wire:model.live="severity">
                    <option value="">{{ __('admin.audit.all_severities') }}</option>
                    @foreach ($severities as $value)
                        <option value="{{ $value }}">{{ __('admin.audit.severities.'.$value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="audit-outcome">{{ __('admin.audit.outcome') }}</label>
                <select id="audit-outcome" class="ag-select" wire:model.live="outcome">
                    <option value="">{{ __('admin.audit.all_outcomes') }}</option>
                    @foreach ($outcomes as $value)
                        <option value="{{ $value }}">{{ __('admin.audit.outcomes.'.$value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ag-field ag-field--inline">
                <label class="visually-hidden" for="audit-actor-type">{{ __('admin.audit.actor_type') }}</label>
                <select id="audit-actor-type" class="ag-select" wire:model.live="actorType">
                    <option value="">{{ __('admin.audit.all_actor_types') }}</option>
                    <option value="staff">{{ __('admin.audit.actor_types.staff') }}</option>
                    <option value="customer">{{ __('admin.audit.actor_types.customer') }}</option>
                    <option value="system">{{ __('admin.audit.actor_types.system') }}</option>
                </select>
            </div>
        </div>
        <div class="ag-toolbar__actions">
            <button class="ag-button ag-button--secondary" type="button" wire:click="resetFilters">{{ __('admin.audit.reset') }}</button>
            <a class="ag-button ag-button--secondary" href="{{ $exportUrl }}">{{ __('admin.audit.export') }}</a>
        </div>
    </div>

    <details class="ag-card" open>
        <summary>{{ __('admin.audit.advanced_filters') }}</summary>
        <div class="ag-form-grid">
            <div class="ag-field"><label for="audit-action">{{ __('admin.audit.action') }}</label><input id="audit-action" class="ag-input" type="text" wire:model.live.debounce.300ms="action" placeholder="order.paid"></div>
            <div class="ag-field"><label for="audit-actor-id">{{ __('admin.audit.actor_id') }}</label><input id="audit-actor-id" class="ag-input" type="text" wire:model.live.debounce.300ms="actorId"></div>
            <div class="ag-field"><label for="audit-subject-type">{{ __('admin.audit.subject_type') }}</label><input id="audit-subject-type" class="ag-input" type="text" wire:model.live.debounce.300ms="subjectType" placeholder="Order"></div>
            <div class="ag-field"><label for="audit-subject-id">{{ __('admin.audit.subject_id') }}</label><input id="audit-subject-id" class="ag-input" type="text" wire:model.live.debounce.300ms="subjectId"></div>
            <div class="ag-field"><label for="audit-ip">{{ __('admin.audit.ip') }}</label><input id="audit-ip" class="ag-input" type="text" wire:model.live.debounce.300ms="ip"></div>
            <div class="ag-field"><label for="audit-method">{{ __('admin.audit.method') }}</label><input id="audit-method" class="ag-input" type="text" wire:model.live.debounce.300ms="method" placeholder="POST"></div>
            <div class="ag-field"><label for="audit-request-id">{{ __('admin.audit.request_id') }}</label><input id="audit-request-id" class="ag-input" type="text" wire:model.live.debounce.300ms="requestId"></div>
            <div class="ag-field"><label for="audit-correlation-id">{{ __('admin.audit.correlation_id') }}</label><input id="audit-correlation-id" class="ag-input" type="text" wire:model.live.debounce.300ms="correlationId"></div>
            <div class="ag-field"><label for="audit-from">{{ __('admin.audit.from') }}</label><input id="audit-from" class="ag-input" type="date" wire:model.live="from"></div>
            <div class="ag-field"><label for="audit-to">{{ __('admin.audit.to') }}</label><input id="audit-to" class="ag-input" type="date" wire:model.live="to"></div>
        </div>
    </details>

    @if ($logs->isEmpty())
        <div class="ag-empty" role="status">
            <p class="ag-empty__title">{{ __('admin.audit.empty') }}</p>
            <p class="ag-empty__text">{{ __('admin.audit.empty_hint') }}</p>
        </div>
    @else
        <div class="ag-table-wrap">
            <table class="ag-table">
                <caption class="visually-hidden">{{ __('admin.audit.table_caption') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.audit.time') }}</th>
                        <th scope="col">{{ __('admin.audit.severity') }}</th>
                        <th scope="col">{{ __('admin.audit.outcome') }}</th>
                        <th scope="col">{{ __('admin.audit.action') }}</th>
                        <th scope="col">{{ __('admin.audit.actor') }}</th>
                        <th scope="col">{{ __('admin.audit.subject') }}</th>
                        <th scope="col">{{ __('admin.audit.request_id') }}</th>
                        <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr wire:key="audit-{{ $log->id }}">
                            <td><span title="{{ $log->created_at?->toDateTimeString() }}">{{ $log->created_at?->translatedFormat('d M Y H:i:s') }}</span></td>
                            <td><span class="ag-badge ag-badge--{{ $log->severity === 'critical' ? 'danger' : ($log->severity === 'warning' ? 'warning' : 'muted') }}">{{ __('admin.audit.severities.'.$log->severity) }}</span></td>
                            <td>{{ __('admin.audit.outcomes.'.$log->outcome) }}</td>
                            <td><strong>{{ $log->action }}</strong><br><span class="ag-muted">{{ __('admin.audit.categories.'.$log->category) }}</span></td>
                            <td>{{ __('admin.audit.actor_types.'.$log->actor_type) }}{{ $log->actor_id ? ' #'.$log->actor_id : '' }}</td>
                            <td>{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : __('common.em_dash') }}</td>
                            <td><code>{{ $log->request_id ?: __('common.em_dash') }}</code></td>
                            <td class="ag-table__actions"><button class="ag-button ag-button--small ag-button--secondary" type="button" wire:click="showDetails({{ $log->id }})">{{ __('admin.audit.details') }}</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="ag-pagination">{{ $logs->links() }}</div>
    @endif

    @if ($selected)
        <div class="ag-card ag-card--detail" role="dialog" aria-labelledby="audit-detail-title">
            <div class="ag-card__header">
                <div>
                    <h2 id="audit-detail-title">{{ __('admin.audit.detail_title') }}</h2>
                    <p class="ag-muted"><code>{{ $selected->event_id }}</code></p>
                </div>
                <button class="ag-button ag-button--secondary" type="button" wire:click="closeDetails">{{ __('admin.audit.close') }}</button>
            </div>
            <dl class="ag-definition-list">
                <dt>{{ __('admin.audit.action') }}</dt><dd>{{ $selected->action }}</dd>
                <dt>{{ __('admin.audit.time') }}</dt><dd>{{ $selected->created_at?->toIso8601String() }}</dd>
                <dt>{{ __('admin.audit.actor') }}</dt><dd>{{ __('admin.audit.actor_types.'.$selected->actor_type) }}{{ $selected->actor_id ? ' #'.$selected->actor_id : '' }}</dd>
                <dt>{{ __('admin.audit.subject') }}</dt><dd>{{ $selected->subject_type ? class_basename($selected->subject_type).' #'.$selected->subject_id : __('common.em_dash') }}</dd>
                <dt>{{ __('admin.audit.request_id') }}</dt><dd><code>{{ $selected->request_id ?: __('common.em_dash') }}</code></dd>
                <dt>{{ __('admin.audit.correlation_id') }}</dt><dd><code>{{ $selected->correlation_id ?: __('common.em_dash') }}</code></dd>
                <dt>{{ __('admin.audit.network_context') }}</dt><dd>{{ $selected->method ?: __('common.em_dash') }} {{ $selected->route ?: '' }} / {{ $selected->ip ?: __('common.em_dash') }}</dd>
                <dt>{{ __('admin.audit.user_agent') }}</dt><dd>{{ $selected->user_agent ?: __('common.em_dash') }}</dd>
                <dt>{{ __('admin.audit.integrity') }}</dt><dd>{{ $selected->integrityIsValid() ? __('admin.audit.integrity_valid') : __('admin.audit.integrity_unavailable') }}</dd>
            </dl>
            <div class="ag-audit-json-grid">
                @foreach (['properties' => 'properties', 'before' => 'before', 'after' => 'after', 'context' => 'context'] as $field => $label)
                    <section>
                        <h3>{{ __('admin.audit.'.$label) }}</h3>
                        <pre>{{ json_encode($selected->{$field}, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                    </section>
                @endforeach
            </div>
        </div>
    @endif
</div>
