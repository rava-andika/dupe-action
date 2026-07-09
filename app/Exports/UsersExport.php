<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromQuery, WithHeadings, WithMapping
{
    public array $ids;

    public function __construct(array $ids)
    {
        $this->ids = $ids;
    }

    public function query()
    {
        return User::query()
            ->with('teams', 'profile')
            ->whereIn('id', $this->ids)
            ->withTrashed();
    }

    public function headings(): array
    {
        return [
            'id',
            'name',
            'email',
            'avatar',
            'email_verified_at',
            'privileges',
            'google_id',
            'created_at',
            'updated_at',
            'deleted_at',
            'teams',
            'birth_date',
            'phone_number',
            'province',
            'address',
            'institution',
            'student_id',
            'institution_card',
            'follow_proof',
            'twibbon_proof'
        ];
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->avatar,
            $user->email_verified_at,
            $user->privileges,
            $user->google_id,
            $user->created_at,
            $user->updated_at,
            $user->deleted_at,
            $user->teams->pluck('name')->implode(', '),
            $user->profile->birth_date ?? null,
            $user->profile->phone_number ?? null,
            $user->profile->province ?? null,
            $user->profile->address ?? null,
            $user->profile->institution ?? null,
            $user->profile->student_id ?? null,
            $user->profile->institution_card ?? null,
            $user->profile->follow_proof ?? null,
            $user->profile->twibbon_proof ?? null
        ];
    }
}