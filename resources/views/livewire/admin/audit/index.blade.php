<div class="admin-page audit-log">
    <x-ag.page-header :heading="__('admin.audit.title')" :lede="__('admin.audit.lede')" />

    <div class="audit-log__stack">
        <section class="audit-log__toolbar" aria-labelledby="audit-filter-heading">
        <div class="audit-log__toolbar-heading">
            <div>
                <p class="audit-log__eyebrow">{{ __('admin.audit.title') }}</p>
                <h2 id="audit-filter-heading">{{ __('admin.audit.filter_heading') }}</h2>
                <p>{{ __('admin.audit.filter_help') }}</p>
            </div>
            <a class="ag-btn ag-btn--secondary" href="{{ $exportUrl }}">
                <x-ag.icon name="download" :size="16" />
                {{ __('admin.audit.export') }}
            </a>
        </div>

        <div class="audit-log__filters">
            <div class="ag-field audit-log__search">
                <label class="ag-field__label" for="audit-search">{{ __('admin.audit.search_label') }}</label>
                <input id="audit-search" class="ag-input ag-input--search" type="search" wire:model.live.debounce.300ms="search" placeholder="{{ __('admin.audit.search_placeholder') }}">
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="audit-category">{{ __('admin.audit.category') }}</label>
                <select id="audit-category" class="ag-select" wire:model.live="category">
                    <option value="">{{ __('admin.audit.all_categories') }}</option>
                    @foreach ($categories as $value)
                        <option value="{{ $value }}">{{ __('admin.audit.categories.'.$value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="audit-severity">{{ __('admin.audit.severity') }}</label>
                <select id="audit-severity" class="ag-select" wire:model.live="severity">
                    <option value="">{{ __('admin.audit.all_severities') }}</option>
                    @foreach ($severities as $value)
                        <option value="{{ $value }}">{{ __('admin.audit.severities.'.$value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="audit-outcome">{{ __('admin.audit.outcome') }}</label>
                <select id="audit-outcome" class="ag-select" wire:model.live="outcome">
                    <option value="">{{ __('admin.audit.all_outcomes') }}</option>
                    @foreach ($outcomes as $value)
                        <option value="{{ $value }}">{{ __('admin.audit.outcomes.'.$value) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ag-field">
                <label class="ag-field__label" for="audit-actor-type">{{ __('admin.audit.actor_type') }}</label>
                <select id="audit-actor-type" class="ag-select" wire:model.live="actorType">
                    <option value="">{{ __('admin.audit.all_actor_types') }}</option>
                    <option value="staff">{{ __('admin.audit.actor_types.staff') }}</option>
                    <option value="customer">{{ __('admin.audit.actor_types.customer') }}</option>
                    <option value="system">{{ __('admin.audit.actor_types.system') }}</option>
                </select>
            </div>
            <button class="ag-btn ag-btn--ghost audit-log__clear" type="button" wire:click="resetFilters">
                {{ __('admin.audit.reset') }}
            </button>
        </div>
    </section>

    @if ($logs->isEmpty())
        <div class="ag-empty audit-log__empty" role="status">
            <p class="ag-empty__title">{{ __('admin.audit.empty') }}</p>
            <p class="ag-empty__text">{{ __('admin.audit.empty_hint') }}</p>
        </div>
    @else
        <section class="audit-log__results" aria-labelledby="audit-results-heading">
            <div class="audit-log__results-heading">
                <div>
                    <p class="audit-log__eyebrow">{{ __('admin.audit.results_eyebrow') }}</p>
                    <h2 id="audit-results-heading">{{ __('admin.audit.results_heading') }}</h2>
                </div>
                <span class="audit-log__count">{{ $logs->total() }} {{ __('admin.audit.entries_count') }}</span>
            </div>

            <div class="ag-table-wrap">
                <table class="ag-table audit-log__table">
                    <caption class="visually-hidden">{{ __('admin.audit.table_caption') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('admin.audit.time') }}</th>
                            <th scope="col">{{ __('admin.audit.action') }}</th>
                            <th scope="col">{{ __('admin.audit.outcome') }}</th>
                            <th scope="col">{{ __('admin.audit.actor') }}</th>
                            <th scope="col">{{ __('admin.audit.subject') }}</th>
                            <th scope="col"><span class="visually-hidden">{{ __('common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr wire:key="audit-{{ $log->id }}">
                                <td>
                                    <time class="audit-log__time" datetime="{{ $log->created_at?->toIso8601String() }}">{{ $log->created_at?->translatedFormat('d M Y H:i') }}</time>
                                    <span class="audit-log__event-id">{{ $log->event_id }}</span>
                                </td>
                                <td>
                                    <strong class="audit-log__action">{{ $log->action }}</strong>
                                    <span class="audit-log__category">{{ __('admin.audit.categories.'.$log->category) }}</span>
                                </td>
                                <td>
                                    <span class="ag-badge ag-badge--{{ $log->severity === 'critical' ? 'danger' : ($log->severity === 'warning' ? 'warning' : 'muted') }}">{{ __('admin.audit.severities.'.$log->severity) }}</span>
                                    <span class="audit-log__outcome">{{ __('admin.audit.outcomes.'.$log->outcome) }}</span>
                                </td>
                                <td>{{ __('admin.audit.actor_types.'.$log->actor_type) }}{{ $log->actor_id ? ' #'.$log->actor_id : '' }}</td>
                                <td>{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : __('common.em_dash') }}</td>
                                <td class="ag-table__actions">
                                    <a class="ag-btn ag-btn--secondary ag-btn--sm audit-log__details" href="{{ route('admin.audit.show', $log) }}">
                                        <x-ag.icon name="eye" :size="16" />
                                        {{ __('admin.audit.details') }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="ag-pagination">{{ $logs->links() }}</div>
        </section>
    @endif
    </div>
</div>
