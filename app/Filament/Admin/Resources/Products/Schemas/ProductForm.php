<?php

namespace App\Filament\Admin\Resources\Products\Schemas;

use App\Support\StoreContext;
use App\Support\TenantContext;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('organization_id')
                    ->hidden()
                    ->dehydrated(false),
                Select::make('category_id')
                    ->label('Kategoriya')
                    ->relationship(
                        'category',
                        'name',
                        fn ($query) => $query
                            ->forTenant(app(TenantContext::class)->requireCurrent())
                            ->forStore(app(StoreContext::class)->requireCurrent()),
                    )
                    ->required(),
                TextInput::make('name')
                    ->label('Nomi')
                    ->required(),
                Textarea::make('description')
                    ->label('Tavsif')
                    ->maxLength(2000),
                FileUpload::make('image_path')
                    ->label('Mahsulot rasmi')
                    ->helperText('Rasm hajmi 2,5 MB dan oshmasligi kerak.')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(2560)
                    ->disk('public')
                    ->directory('menu-products')
                    ->visibility('public')
                    ->placeholder('Rasmni shu yerga tashlang yoki tanlash uchun bosing')
                    ->extraAlpineAttributes([
                        'x-init' => <<<'JS'
                            const labels = {
                                labelInvalidField: 'Yaroqsiz fayl',
                                labelFileWaitingForSize: 'Fayl hajmi aniqlanmoqda',
                                labelFileSizeNotAvailable: 'Fayl hajmi noma’lum',
                                labelFileLoading: 'Fayl yuklanmoqda',
                                labelFileLoadError: 'Faylni ochib bo‘lmadi',
                                labelFileProcessing: 'Rasm yuklanmoqda',
                                labelFileProcessingComplete: 'Rasm yuklandi',
                                labelFileProcessingAborted: 'Yuklash bekor qilindi',
                                labelFileProcessingError: 'Yuklashda xatolik yuz berdi',
                                labelFileProcessingRevertError: 'Yuklashni bekor qilib bo‘lmadi',
                                labelFileRemoveError: 'Faylni olib tashlab bo‘lmadi',
                                labelTapToCancel: 'bekor qilish uchun bosing',
                                labelTapToRetry: 'qayta urinish uchun bosing',
                                labelTapToUndo: 'qaytarish uchun bosing',
                                labelButtonRemoveItem: 'Faylni olib tashlash',
                                labelButtonAbortItemLoad: 'Ochishni bekor qilish',
                                labelButtonRetryItemLoad: 'Qayta ochish',
                                labelButtonAbortItemProcessing: 'Yuklashni bekor qilish',
                                labelButtonUndoItemProcessing: 'Yuklashni qaytarish',
                                labelButtonRetryItemProcessing: 'Qayta yuklash',
                                labelButtonProcessItem: 'Yuklash',
                                labelMaxFileSizeExceeded: 'Fayl juda katta',
                                labelMaxFileSize: 'Eng katta hajm: {filesize}',
                                labelFileTypeNotAllowed: 'Bu turdagi faylga ruxsat yo‘q',
                            }
                            $watch('pond', (instance) => instance?.setOptions(labels))
                            if (pond) pond.setOptions(labels)
                            JS,
                    ]),
                TextInput::make('price')
                    ->label('Sotuv narxi')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->prefix('UZS'),
                TextInput::make('cost_price')
                    ->label('Tannarx')
                    ->helperText('Bir dona mahsulotning taxminiy tannarxi')
                    ->required()
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->prefix('UZS'),
                Toggle::make('is_active')
                    ->label('Faol')
                    ->required(),
                TextInput::make('sort_order')
                    ->label('Tartib raqami')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
