<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'role',
        'base_location_id',
        'department_id',
        'work_group_id',
        'work_shift_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'nik',
        'phone',
        'address',
        'npwp_document_path',
        'profile_photo_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function fcmDevices(): HasMany
    {
        return $this->hasMany(FcmDevice::class);
    }

    public function attendanceCorrectionRequests(): HasMany
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function activityReports(): HasMany
    {
        return $this->hasMany(ActivityReport::class);
    }

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }

    public function taxDocuments(): HasMany
    {
        return $this->hasMany(TaxDocument::class);
    }

    public function baseLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'base_location_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function supervisedDepartment(): HasOne
    {
        return $this->hasOne(Department::class, 'supervisor_id');
    }

    public function supervisedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'supervisor_id');
    }

    public function directedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'director_id');
    }

    public function workGroup(): BelongsTo
    {
        return $this->belongsTo(WorkGroup::class);
    }

    public function ledWorkGroup(): HasOne
    {
        return $this->hasOne(WorkGroup::class, 'leader_id');
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function workAssignments(): HasMany
    {
        return $this->hasMany(WorkAssignment::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereIn('role', ['employee', 'group_lead', 'division_head']);
    }

    public function isHr(): bool
    {
        return $this->role === 'hr';
    }

    public function isDirector(): bool
    {
        return $this->role === 'director';
    }

    public function isCeo(): bool
    {
        return $this->role === 'ceo';
    }

    public function isCommissioner(): bool
    {
        return $this->role === 'commissioner';
    }

    public function isDivisionHead(): bool
    {
        return in_array($this->role, ['employee', 'division_head'], true)
            && $this->department()->where('is_active', true)
                ->whereHas('supervisor', fn ($query) => $query->whereKey($this->id))
                ->exists();
    }

    public function isGroupLead(): bool
    {
        return $this->role === 'group_lead'
            && $this->workGroup()->where('is_active', true)
                ->where('leader_id', $this->id)
                ->exists();
    }

    public function canAccessAdminPortal(): bool
    {
        return $this->is_active && ($this->isAdmin()
            || $this->isHr()
            || $this->isDirector()
            || $this->isCeo()
            || $this->isCommissioner()
            || $this->isDivisionHead()
            || $this->isGroupLead());
    }

    public function isDepartmentSupervisor(): bool
    {
        return $this->isDivisionHead();
    }
}
