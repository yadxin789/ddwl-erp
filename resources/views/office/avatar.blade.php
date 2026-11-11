@extends('layouts.admin')

@section('title', '上传头像')

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-image me-2 text-primary"></i>当前头像</span>
                <a href="{{ route('office.profile') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i>基本资料
                </a>
            </div>
            <div class="card-body text-center">
                @if ($user->avatar_url)
                    <img src="{{ $user->avatar_url }}" alt="当前头像" class="rounded-circle border"
                         style="width:150px;height:150px;object-fit:cover;">
                @else
                    <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center"
                         style="width:150px;height:150px;font-size:60px;">
                        {{ mb_substr($user->name, 0, 1) }}
                    </div>
                @endif
                <div class="mt-3 fw-semibold">{{ $user->identity_label }}</div>
                <div class="text-muted small">
                    {{ $user->position_label ?: '未绑定岗位' }}
                </div>

                <form method="POST" action="{{ route('office.avatar.update') }}" enctype="multipart/form-data" class="mt-4 text-start">
                    @csrf
                    <label class="form-label">选择新头像</label>
                    <input type="file" name="avatar" class="form-control" accept="image/jpeg,image/png,image/gif" onchange="previewImage(event)">
                    <div class="form-text">支持 JPG、PNG、GIF，最大 2MB。</div>

                    <div id="previewBox" class="text-center mt-3" style="display:none;">
                        <img id="avatarPreview" src="#" alt="预览" class="rounded-circle border"
                             style="width:96px;height:96px;object-fit:cover;">
                        <div class="text-muted small mt-1">新头像预览</div>
                    </div>

                    @error('avatar') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
                    <button class="btn btn-primary mt-3 w-100"><i class="fa-solid fa-upload me-1"></i>上传头像</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header"><span><i class="fa-solid fa-user-group me-2 text-success"></i>默认头像仓库</span></div>
            <div class="card-body">
                <p class="text-muted small">选择一个默认头像作为你的新头像：</p>
                <div class="row g-3">
                    @foreach (['avatar1.svg', 'avatar2.svg', 'avatar3.svg', 'avatar4.svg', 'avatar5.svg'] as $avatar)
                        <div class="col-6 col-md-4 text-center">
                            <form method="POST" action="{{ route('office.avatar.update') }}">
                                @csrf
                                <input type="hidden" name="default_avatar" value="defaults/{{ $avatar }}">
                                <button type="submit" class="btn p-0 border-0 bg-transparent">
                                    <img src="{{ asset('avatars/defaults/' . $avatar) }}" alt="默认头像{{ $loop->iteration }}"
                                         class="rounded-circle border default-avatar"
                                         style="width:80px;height:80px;object-fit:cover;">
                                    <div class="small text-muted mt-2">头像{{ $loop->iteration }}</div>
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>
                @error('default_avatar') <div class="text-danger small mt-2">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function previewImage(event) {
    const input = event.target;
    const box = document.getElementById('previewBox');
    const img = document.getElementById('avatarPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; box.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    } else {
        box.style.display = 'none';
    }
}
</script>
@endpush
