{{-- progress line: $steps = [['label' => ..., 'sub' => ..., 'state' => 'done' | 'current' | ''], ...] --}}
<div class="card">
    <div class="steps">
        @foreach ($steps as $step)
            <div class="step {{ $step['state'] }}">
                <span class="step-dot">
                    @if ($step['state'] === 'done')
                        <x-icon name="check" size="16" />
                    @else
                        {{ $loop->iteration }}
                    @endif
                </span>
                <span class="step-text">
                    <span class="step-label">{{ $step['label'] }}</span>
                    @if (! empty($step['sub']))
                        <span class="step-sub">{{ $step['sub'] }}</span>
                    @endif
                </span>
            </div>
        @endforeach
    </div>
</div>
