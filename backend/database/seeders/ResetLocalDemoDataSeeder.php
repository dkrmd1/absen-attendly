<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use App\Models\WorkGroup;
use App\Models\WorkLocation;
use App\Models\WorkShift;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ResetLocalDemoDataSeeder extends Seeder
{
    private const DEMO_PASSWORD = 'AttendlyDemo123!';

    public function run(): void
    {
        $connection = DB::connection();
        if (
            ! app()->environment('local')
            || $connection->getDatabaseName() !== 'absen'
            || ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost'], true)
        ) {
            throw new RuntimeException(
                'Reset data dummy dibatasi untuk APP_ENV=local dan database lokal absen.',
            );
        }

        DB::transaction(function (): void {
            foreach ([
                'attendance_correction_reviews',
                'attendance_correction_requests',
                'leave_request_reviews',
                'work_assignments',
                'attendances',
                'leave_requests',
                'activity_reports',
                'payroll_records',
                'tax_documents',
                'announcements',
                'leave_balances',
                'notifications',
                'personal_access_tokens',
                'sessions',
                'password_reset_tokens',
            ] as $table) {
                DB::table($table)->delete();
            }

            DB::table('users')->delete();

            $departments = $this->demoDepartments();
            $location = WorkLocation::query()->where('is_active', true)->orderBy('id')->first();
            $shift = WorkShift::query()->where('is_active', true)->orderBy('id')->first();
            $workGroup = $this->demoWorkGroup($departments[0]);

            $admin = $this->createUser('Attendly Admin', 'admin@attendly.test', 'admin');
            $hr = $this->createUser('Attendly HR', 'hr@attendly.test', 'hr');
            $ceo = $this->createUser('Direktur Utama Demo', 'direktur.utama@attendly.test', 'ceo');
            $commissionerOne = $this->createUser('Komisaris Demo 1', 'komisaris.1@attendly.test', 'commissioner');
            $commissionerTwo = $this->createUser('Komisaris Demo 2', 'komisaris.2@attendly.test', 'commissioner');
            $directorOne = $this->createUser(
                'Direktur Divisi Umum',
                'direktur.umum@attendly.test',
                'director',
                $departments[0]->id,
            );
            $directorTwo = $this->createUser(
                'Direktur Divisi Keuangan',
                'direktur.keuangan@attendly.test',
                'director',
                $departments[1]->id,
            );
            $divisionHead = $this->createUser(
                'Kepala Divisi Demo',
                'kepala.divisi@attendly.test',
                'division_head',
                $departments[0]->id,
            );
            $groupLead = $this->createUser(
                'Ketua Tim Demo',
                'ketua.tim@attendly.test',
                'group_lead',
                $departments[0]->id,
                $workGroup->id,
            );
            $staffOne = $this->createUser(
                'Staf Demo 1',
                'staf.01@attendly.test',
                'employee',
                $departments[0]->id,
                $workGroup->id,
                $location?->id,
                $shift?->id,
            );
            $staffTwo = $this->createUser(
                'Staf Demo 2',
                'staf.02@attendly.test',
                'employee',
                $departments[0]->id,
                $workGroup->id,
                $location?->id,
                $shift?->id,
            );
            $staffThree = $this->createUser(
                'Staf Demo 3',
                'staf.03@attendly.test',
                'employee',
                $departments[1]->id,
                null,
                $location?->id,
                $shift?->id,
            );

            $departments[0]->update([
                'supervisor_id' => $divisionHead->id,
                'director_id' => $directorOne->id,
            ]);
            $departments[1]->update(['director_id' => $directorTwo->id]);
            $workGroup->update(['leader_id' => $groupLead->id]);
            $groupLead->update(['work_group_id' => $workGroup->id]);

            unset(
                $admin,
                $hr,
                $ceo,
                $commissionerOne,
                $commissionerTwo,
                $directorOne,
                $directorTwo,
                $staffOne,
                $staffTwo,
                $staffThree,
            );
        });

        foreach ([
            'attendance-selfies',
            'employee-request-files',
            'payslips',
            'tax-documents',
        ] as $directory) {
            if (! Storage::disk('local')->deleteDirectory($directory)) {
                throw new RuntimeException("Gagal membersihkan berkas privat {$directory}.");
            }
        }
    }

    /**
     * @return array{0: Department, 1: Department}
     */
    private function demoDepartments(): array
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->take(2)
            ->get()
            ->values();

        if ($departments->isEmpty()) {
            $departments->push(Department::query()->create([
                'name' => 'Divisi Umum',
                'is_active' => true,
            ]));
        }

        if ($departments->count() < 2) {
            $name = 'Divisi Keuangan Demo';
            $suffix = 2;
            while (Department::query()->where('name', $name)->exists()) {
                $name = 'Divisi Keuangan Demo '.$suffix++;
            }

            $departments->push(Department::query()->create([
                'name' => $name,
                'description' => 'Departemen tambahan untuk akun demonstrasi.',
                'is_active' => true,
            ]));
        }

        return [$departments[0], $departments[1]];
    }

    private function demoWorkGroup(Department $department): WorkGroup
    {
        $group = WorkGroup::query()
            ->where('department_id', $department->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($group) {
            return $group;
        }

        return WorkGroup::query()->create([
            'department_id' => $department->id,
            'name' => 'Tim Demo',
            'is_active' => true,
        ]);
    }

    private function createUser(
        string $name,
        string $email,
        string $role,
        ?int $departmentId = null,
        ?int $workGroupId = null,
        ?int $locationId = null,
        ?int $shiftId = null,
    ): User {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::DEMO_PASSWORD),
            'role' => $role,
            'is_active' => true,
            'department_id' => $departmentId,
            'work_group_id' => $workGroupId,
            'base_location_id' => $locationId,
            'work_shift_id' => $shiftId,
        ]);
    }
}
