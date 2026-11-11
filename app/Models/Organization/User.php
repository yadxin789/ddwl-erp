<?php

namespace App\Models\Organization;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\BelongsToTenant;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, BelongsToTenant;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'department_id',
        'position_id',
        'tenant_id',
        'avatar',
        'wecom_userid',
        'wecom_bound_at',
        'status',
        'last_login_at',
        'last_login_ip',
        'mfa_secret',
        'mfa_recovery_codes',
        'mfa_enabled_at',
        'mfa_last_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'mfa_recovery_codes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'department_id' => 'integer',
        'position_id' => 'integer',
        'status' => 'integer',
        'wecom_bound_at' => 'datetime',
        'last_login_at' => 'datetime',
        'mfa_secret' => 'encrypted',
        'mfa_recovery_codes' => 'array',
        'mfa_enabled_at' => 'datetime',
        'mfa_last_verified_at' => 'datetime',
    ];

    /**
     * 操作日志记录配置：记录除隐藏字段外的所有变更
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['username', 'name', 'email', 'department_id', 'position_id', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * 操作人统一展示口径：姓名 +（登录账号）。
     * 姓名与账号相同（实名账号）时只显示一次，避免"张三（张三）"。
     */
    public function getIdentityLabelAttribute(): string
    {
        $name = trim((string) $this->name);
        $username = trim((string) $this->username);

        if ($name === '') {
            $name = $username;
        }

        if ($username === '' || $name === $username) {
            return $name !== '' ? $name : '-';
        }

        return $name . '（' . $username . '）';
    }

    /** 岗位名称；岗位不是人名，只在需要时作为附属信息展示。 */
    public function getPositionLabelAttribute(): string
    {
        return (string) ($this->position?->pos_name ?? '');
    }
}
