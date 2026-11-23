@extends('admin.layout')

@section('title', 'Pengaturan')

@section('content')
    @php
        $canUpdate = auth()->user()->hasPermission('settings.update');
        $visibleDefinitions = collect($definitions)
            ->reject(fn (array $definition): bool => $definition['key'] === 'waitlist_offer_minutes')
            ->map(fn (array $definition): array => [
                ...$definition,
                'group' => $definition['group'] === 'Persetujuan & antrean'
                    ? 'Persetujuan'
                    : $definition['group'],
            ]);
    @endphp

    <header class="plate">
        <div>
            <h1 class="plate-title">Pengaturan sistem</h1>
            <p class="plate-desc">Nilai ini dipakai untuk pemeriksaan saat masuk, persetujuan, antrean, dan pelanggaran.</p>
        </div>
    </header>

    <form method="POST" action="{{ route('admin.settings.update') }}">
        @csrf
        @method('PUT')

        <div class="stack">
            @foreach($visibleDefinitions->groupBy('group') as $group => $items)
                <section class="panel">
                    <div class="panel-head">
                        <h2 class="panel-title">{{ $group }}</h2>
                    </div>
                    <div class="panel-body">
                        <div class="form-grid">
                            @foreach($items as $definition)
                                @php($key = $definition['key'])
                                <div class="field">
                                    @if($definition['type'] === 'boolean')
                                        <label class="check">
                                            <input type="checkbox" name="settings[{{ $key }}]" value="1" @checked(old('settings.'.$key, $values[$key])) @disabled(! $canUpdate)>
                                            <span>
                                                <span class="field-label">{{ $definition['label'] }}</span>
                                                <span class="field-hint">{{ $definition['hint'] }}</span>
                                            </span>
                                        </label>
                                    @else
                                        <label class="field-label" for="setting_{{ $key }}">{{ $definition['label'] }}</label>
                                        <input
                                            class="input"
                                            type="{{ $definition['type'] === 'string' ? 'text' : 'number' }}"
                                            id="setting_{{ $key }}"
                                            name="settings[{{ $key }}]"
                                            value="{{ old('settings.'.$key, $values[$key]) }}"
                                            @if($definition['type'] !== 'string') step="{{ $definition['type'] === 'float' ? '0.01' : '1' }}" @endif
                                            @disabled(! $canUpdate)
                                        >
                                        <span class="field-hint">{{ $definition['hint'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endforeach
        </div>

        @if($canUpdate)
            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Simpan pengaturan</button>
            </div>
        @else
            <p class="field-hint">Anda hanya memiliki akses lihat untuk pengaturan.</p>
        @endif
    </form>
@endsection
