<?php

namespace App\Enums;

enum Permission: string
{
    case APPROVALS_VIEW = 'approvals.view';
    case APPROVALS_UPDATE = 'approvals.update';

    case RESOURCES_VIEW = 'resources.view';
    case RESOURCES_CREATE = 'resources.create';
    case RESOURCES_UPDATE = 'resources.update';
    case RESOURCES_DELETE = 'resources.delete';

    case BOOKINGS_VIEW = 'bookings.view';

    case LOANS_VIEW = 'loans.view';
    case LOANS_UPDATE = 'loans.update';

    case LOCATIONS_VIEW = 'locations.view';
    case LOCATIONS_CREATE = 'locations.create';
    case LOCATIONS_UPDATE = 'locations.update';
    case LOCATIONS_DELETE = 'locations.delete';

    case CATEGORIES_VIEW = 'categories.view';
    case CATEGORIES_CREATE = 'categories.create';
    case CATEGORIES_UPDATE = 'categories.update';
    case CATEGORIES_DELETE = 'categories.delete';

    case FACILITIES_VIEW = 'facilities.view';
    case FACILITIES_CREATE = 'facilities.create';
    case FACILITIES_UPDATE = 'facilities.update';
    case FACILITIES_DELETE = 'facilities.delete';

    case SETTINGS_VIEW = 'settings.view';
    case SETTINGS_UPDATE = 'settings.update';

    case USERS_VIEW = 'users.view';
    case USERS_CREATE = 'users.create';
    case USERS_UPDATE = 'users.update';

    case PENALTIES_VIEW = 'penalties.view';
    case PENALTIES_CREATE = 'penalties.create';
    case PENALTIES_UPDATE = 'penalties.update';

    case REPORTS_VIEW = 'reports.view';
    case AUDIT_VIEW = 'audit.view';

    public function module(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    public function action(): string
    {
        return explode('.', $this->value, 2)[1];
    }

    public function moduleLabel(): string
    {
        return match ($this->module()) {
            'approvals' => 'Persetujuan',
            'resources' => 'Ruangan & aset',
            'bookings' => 'Peminjaman',
            'loans' => 'Peminjaman aset',
            'locations' => 'Lokasi & area',
            'categories' => 'Kategori',
            'facilities' => 'Fasilitas',
            'settings' => 'Pengaturan',
            'users' => 'Pengguna',
            'penalties' => 'Pelanggaran & pembatasan akun',
            'reports' => 'Laporan',
            'audit' => 'Riwayat aktivitas',
            default => $this->module(),
        };
    }

    public function actionLabel(): string
    {
        return self::actionLabels()[$this->action()] ?? $this->action();
    }

    /**
     * @return array<string, string>
     */
    public static function actionLabels(): array
    {
        return [
            'view' => 'Lihat',
            'create' => 'Tambah',
            'update' => 'Ubah',
            'delete' => 'Hapus',
        ];
    }

    /**
     * Semua izin yang bisa diatur untuk sebuah peran, dikelompokkan per modul.
     *
     * @return array<string, list<self>>
     */
    public static function catalogForRole(Role $role): array
    {
        if (! in_array($role, [Role::APPROVER, Role::RESOURCE_MANAGER], true)) {
            return [];
        }

        $catalog = [];

        foreach (self::cases() as $permission) {
            $catalog[$permission->module()][] = $permission;
        }

        return $catalog;
    }

    /**
     * Izin bawaan ketika sebuah peran belum pernah dikonfigurasi.
     *
     * @return list<string>
     */
    public static function defaultsForRole(Role $role): array
    {
        return match ($role) {
            Role::APPROVER => [
                'approvals.view',
                'approvals.update',
            ],
            Role::RESOURCE_MANAGER => [
                'resources.view',
                'resources.create',
                'resources.update',
                'bookings.view',
                'loans.view',
                'loans.update',
            ],
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    public static function valuesForRole(Role $role): array
    {
        $values = [];

        foreach (self::catalogForRole($role) as $permissions) {
            foreach ($permissions as $permission) {
                $values[] = $permission->value;
            }
        }

        return $values;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}
