@php
    $selected = $selected ?? old('payment_terms');
@endphp
<select name="payment_terms" class="form-select">
    <option value="">未指定</option>
    @foreach ($paymentTerms as $term)
        <option value="{{ $term->name }}" @selected((string) $selected === (string) $term->name)>
            {{ $term->name }}@if ($term->days > 0)（{{ $term->days }}天）@endif
        </option>
    @endforeach
    @if ($selected && ! $paymentTerms->contains('name', $selected))
        <option value="{{ $selected }}" selected>{{ $selected }}（历史值，不在当前字典）</option>
    @endif
</select>
@if (auth()->user()?->can('admin.masterdata.manage'))
    <div class="form-text">
        <a href="{{ route('admin.master-data.tax-terms') }}">维护结算方式字典</a>
    </div>
@endif
