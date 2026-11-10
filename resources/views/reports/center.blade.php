@extends('layouts.admin')

@section('title', '报表中心')

@section('content')
<p class="text-muted small">口径说明：收入按<strong>开票</strong>确认（D-10），订单额不作为收入；金额为累计，不含税额单列。</p>
<div class="row g-3">
    @foreach ($cards as $card)
        <div class="col-md-6 col-xl-4">
            <a href="{{ route($card['route']) }}" class="text-decoration-none">
                <div class="card h-100">
                    <div class="card-body d-flex gap-3 align-items-center">
                        <div class="bg-secondary bg-opacity-10 text-secondary rounded-3 p-3"><i class="fa-solid {{ $card['icon'] }} fa-xl"></i></div>
                        <div>
                            <div class="fw-bold text-body">{{ $card['name'] }}</div>
                            <div class="text-muted small">{{ $card['desc'] }}</div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endsection
