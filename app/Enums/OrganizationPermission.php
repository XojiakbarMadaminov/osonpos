<?php

namespace App\Enums;

enum OrganizationPermission: string
{
    case PosAccess = 'pos.access';
    case ProductsView = 'products.view';
    case ProductsManage = 'products.manage';
    case OrdersView = 'orders.view';
    case OrdersCreate = 'orders.create';
    case OrdersUpdate = 'orders.update';
    case OrdersCancel = 'orders.cancel';
    case OrdersReprint = 'orders.reprint';
    case PaymentsView = 'payments.view';
    case PaymentsCreate = 'payments.create';
    case PaymentsRefund = 'payments.refund';
    case TablesView = 'tables.view';
    case TablesManage = 'tables.manage';
    case ReportsView = 'reports.view';
    case PrintersView = 'printers.view';
    case PrintersManage = 'printers.manage';
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesView = 'roles.view';
    case RolesManage = 'roles.manage';
    case StoresView = 'stores.view';
    case StoresManage = 'stores.manage';
    case ShiftsView = 'shifts.view';
    case ShiftsManage = 'shifts.manage';
    case ExpensesView = 'expenses.view';
    case ExpensesManage = 'expenses.manage';
    case TelegramSettingsManage = 'telegram_settings.manage';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::PosAccess => 'POS tizimidan foydalanish',
            self::ProductsView => 'Mahsulotlarni ko‘rish',
            self::ProductsManage => 'Mahsulotlarni boshqarish',
            self::OrdersView => 'Buyurtmalarni ko‘rish',
            self::OrdersCreate => 'Buyurtma yaratish',
            self::OrdersUpdate => 'Buyurtmani o‘zgartirish',
            self::OrdersCancel => 'Buyurtmani bekor qilish',
            self::OrdersReprint => 'Cheklarni qayta chiqarish',
            self::PaymentsView => 'To‘lovlarni ko‘rish',
            self::PaymentsCreate => 'To‘lov yaratish',
            self::PaymentsRefund => 'To‘lovni qaytarish',
            self::TablesView => 'Stollarni ko‘rish',
            self::TablesManage => 'Stollarni boshqarish',
            self::ReportsView => 'Hisobotlarni ko‘rish',
            self::PrintersView => 'Printerlarni ko‘rish',
            self::PrintersManage => 'Printerlarni boshqarish',
            self::UsersView => 'Foydalanuvchilarni ko‘rish',
            self::UsersManage => 'Foydalanuvchilarni boshqarish',
            self::RolesView => 'Rollarni ko‘rish',
            self::RolesManage => 'Rollarni boshqarish',
            self::StoresView => 'Filiallarni ko‘rish',
            self::StoresManage => 'Filiallarni boshqarish',
            self::ShiftsView => 'Smenalarni ko‘rish',
            self::ShiftsManage => 'Smenalarni boshqarish',
            self::ExpensesView => 'Chiqimlarni ko‘rish',
            self::ExpensesManage => 'Chiqimlarni boshqarish',
            self::TelegramSettingsManage => 'Telegram sozlamasini boshqarish',
        };
    }

    public static function labels(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $permission): array => [$permission->value => $permission->label()])
            ->all();
    }
}
