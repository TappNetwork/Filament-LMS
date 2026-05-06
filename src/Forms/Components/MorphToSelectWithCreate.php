<?php

namespace Tapp\FilamentLms\Forms\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Image;
use Tapp\FilamentLms\Models\Link;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentLms\Models\Video;
use Tapp\FilamentLms\Resources\VideoResource;
use Tapp\FilamentLms\Services\VideoUrlService;

class MorphToSelectWithCreate
{
    private const LIBRARY_ITEM_CLASS = 'Tapp\\FilamentLibrary\\Models\\LibraryItem';

    public static function filamentLibraryIntegrationEnabled(): bool
    {
        return (bool) config('filament-lms.integrations.filament_library.enabled', false)
            && class_exists(self::LIBRARY_ITEM_CLASS);
    }

    public static function libraryMaterialSelectLimit(): int
    {
        $limit = (int) config('filament-lms.integrations.filament_library.material_select_limit', 200);

        return max(1, min($limit, 500));
    }

    public static function isLibraryMaterialType(?string $materialType): bool
    {
        return in_array($materialType, ['library_file', 'library_link'], true);
    }

    /**
     * @return array<string, string>
     */
    public static function materialTypeOptions(): array
    {
        $options = [
            'video' => 'Video',
            'document' => 'Document',
            'link' => 'Link',
            'image' => 'Image',
            'form' => 'Form',
            'test' => 'Test',
        ];

        if (self::filamentLibraryIntegrationEnabled()) {
            $options['library_file'] = 'Library file';
            $options['library_link'] = 'Library link';
        }

        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    private static function libraryItemOptionsForMaterialType(string $materialType): array
    {
        $limit = self::libraryMaterialSelectLimit();

        /** @var class-string<Model> $modelClass */
        $modelClass = self::LIBRARY_ITEM_CLASS;

        $query = $modelClass::query()
            ->orderBy('name')
            ->limit($limit);

        if ($materialType === 'library_file') {
            $query->where('type', 'file');
        } elseif ($materialType === 'library_link') {
            $query->where('type', 'link');
        } else {
            return [];
        }

        return $query->pluck('name', 'id')->all();
    }

    public static function make(string $name): array
    {
        return [
            Select::make('material_type')
                ->label('Material Type')
                ->options(self::materialTypeOptions())
                ->live()
                ->placeholder('Select a material type if needed')
                ->nullable()
                ->afterStateUpdated(function (Set $set) {
                    $set('material_id', null);
                }),

            Select::make('material_id')
                ->label('Select Material')
                ->options(function (Get $get): array {
                    $materialType = $get('material_type');
                    if (! $materialType) {
                        return [];
                    }

                    if (self::isLibraryMaterialType($materialType)) {
                        return self::libraryItemOptionsForMaterialType($materialType);
                    }

                    $classMap = [
                        'video' => Video::class,
                        'document' => Document::class,
                        'link' => Link::class,
                        'image' => Image::class,
                        'form' => FilamentForm::class,
                        'test' => Test::class,
                    ];

                    $className = $classMap[$materialType] ?? null;
                    if (! $className) {
                        return [];
                    }

                    return $className::query()->orderBy('name')->pluck('name', 'id')->all();
                })
                ->searchable()
                ->requiredWith('material_type')
                ->suffixActions([
                    Action::make('create_video')
                        ->label('New')
                        ->icon('heroicon-o-plus')
                        ->color('primary')
                        ->visible(fn (Get $get) => $get('material_type') === 'video')
                        ->schema(VideoResource::form(Schema::make())->getComponents())
                        ->action(function (array $data, Set $set) {
                            // Convert the URL (validation already happened in the form rules)
                            $data['url'] = VideoUrlService::convertToEmbedUrl($data['url']);

                            $video = Video::create($data);
                            $set('material_id', $video->id);
                        }),
                ]),
        ];
    }
}
