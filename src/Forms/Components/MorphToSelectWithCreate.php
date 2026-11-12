<?php

namespace Tapp\FilamentLms\Forms\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Image;
use Tapp\FilamentLms\Models\Link;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentLms\Models\Video;
use Tapp\FilamentLms\Resources\VideoResource;
use Tapp\FilamentLms\Services\VideoUrlService;
use Tapp\FilamentLms\Support\CourseMaterialForm;
use Tapp\FilamentLms\Support\MaterialBadge;

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
            'video' => __('filament-lms::course-editor.material.types.video'),
            'document' => __('filament-lms::course-editor.material.types.document'),
            'link' => __('filament-lms::course-editor.material.types.link'),
            'image' => __('filament-lms::course-editor.material.types.image'),
            'form' => __('filament-lms::course-editor.material.types.form'),
            'test' => __('filament-lms::course-editor.material.types.test'),
        ];

        if (self::filamentLibraryIntegrationEnabled()) {
            $options['library_file'] = __('filament-lms::course-editor.material.types.library_file');
            $options['library_link'] = __('filament-lms::course-editor.material.types.library_link');
        }

        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    private static function libraryItemOptionsForMaterialType(string $materialType): array
    {
        $limit = self::libraryMaterialSelectLimit();

        if (! is_a(self::LIBRARY_ITEM_CLASS, Model::class, true)) {
            return [];
        }

        $query = self::LIBRARY_ITEM_CLASS::query()
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

    /**
     * @return array<int, mixed>
     */
    public static function make(string $name, bool $segmented = false): array
    {
        $typeField = $segmented
            ? ToggleButtons::make('material_type')
                ->label(__('filament-lms::course-editor.material.label'))
                ->options(self::materialTypeOptions())
                ->icons(self::materialTypeIcons())
                ->inline()
                ->grouped()
                ->live()
                ->afterStateUpdated(function (Set $set): void {
                    $set('material_id', null);
                })
            : Select::make('material_type')
                ->label('Material Type')
                ->options(self::materialTypeOptions())
                ->live()
                ->placeholder('Select a material type if needed')
                ->nullable()
                ->afterStateUpdated(function (Set $set): void {
                    $set('material_id', null);
                });

        $suffixActions = $segmented
            ? [self::addMaterialAction()]
            : [
                Action::make('create_video')
                    ->label('New')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->visible(fn (Get $get) => $get('material_type') === 'video')
                    ->schema(VideoResource::form(Schema::make())->getComponents())
                    ->action(function (array $data, Set $set): void {
                        $data['url'] = VideoUrlService::convertToEmbedUrl($data['url']);

                        $video = Video::create($data);
                        $set('material_id', $video->id);
                    }),
            ];

        if ($segmented) {
            $suffixActions[] = Action::make('detach_material')
                ->label(__('filament-lms::course-editor.material.detach'))
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (Get $get): bool => filled($get('material_id')))
                ->action(function (Set $set): void {
                    $set('material_type', null);
                    $set('material_id', null);
                });
        }

        $materialSelect = Select::make('material_id')
            ->label($segmented ? __('filament-lms::course-editor.material.choose_existing') : 'Select Material')
            ->options(fn (Get $get): array => self::materialOptions($get('material_type')))
            ->searchable()
            ->requiredWith('material_type')
            ->suffixActions($suffixActions);

        if ($segmented) {
            $materialSelect->live();
        }

        $components = [$typeField, $materialSelect];

        if ($segmented) {
            $components[] = View::make('filament-lms::filament.course-editor.material-preview')
                ->visible(fn (Get $get): bool => filled($get('material_id')))
                ->viewData(fn (Get $get): array => [
                    'materialType' => $get('material_type'),
                    'material' => self::findMaterial($get('material_type'), $get('material_id')),
                ]);
        }

        return $components;
    }

    public static function addMaterialAction(): Action
    {
        return Action::make('addMaterial')
            ->label(__('filament-lms::course-editor.material.add'))
            ->tooltip(__('filament-lms::course-editor.material.add'))
            ->modalHeading(fn (Get $get): string => __('filament-lms::course-editor.material.add_heading', [
                'type' => self::materialTypeOptions()[(string) $get('material_type')] ?? '',
            ]))
            ->modalSubmitActionLabel(__('filament-lms::course-editor.material.use'))
            ->modalWidth(Width::SixExtraLarge)
            ->icon('heroicon-o-plus')
            ->color('primary')
            ->visible(fn (Get $get): bool => CourseMaterialForm::canCreate($get('material_type')))
            ->model(fn (Get $get): string => CourseMaterialForm::modelFor((string) $get('material_type')) ?? Video::class)
            ->schema(fn (Get $get): array => CourseMaterialForm::schema((string) $get('material_type')))
            ->action(function (array $data, Get $get, Set $set, Schema $schema): void {
                $type = (string) $get('material_type');
                abort_unless(CourseMaterialForm::canCreate($type), 403);

                $material = CourseMaterialForm::resolve($type, $data, $schema);
                $set('material_id', $material->getKey());
            });
    }

    /**
     * @return array<string, Heroicon>
     */
    public static function materialTypeIcons(): array
    {
        $icons = [];

        foreach (array_keys(self::materialTypeOptions()) as $type) {
            $icons[$type] = MaterialBadge::iconForType($type);
        }

        return $icons;
    }

    /**
     * @return array<int|string, string>
     */
    public static function materialOptions(?string $materialType): array
    {
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
    }

    public static function findMaterial(?string $materialType, mixed $materialId): ?Model
    {
        if (! $materialType || blank($materialId)) {
            return null;
        }

        $class = Relation::getMorphedModel($materialType);

        if (! is_string($class) || ! class_exists($class)) {
            return null;
        }

        $material = $class::query()->find($materialId);

        return $material instanceof Model ? $material : null;
    }
}
