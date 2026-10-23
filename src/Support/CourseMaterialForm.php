<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Support;

use Closure;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Image;
use Tapp\FilamentLms\Models\Link;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentLms\Models\Video;
use Tapp\FilamentLms\Resources\DocumentResource;
use Tapp\FilamentLms\Resources\ImageResource;
use Tapp\FilamentLms\Resources\LinkResource;
use Tapp\FilamentLms\Resources\TestResource;
use Tapp\FilamentLms\Resources\VideoResource;
use Tapp\FilamentLms\Services\VideoUrlService;

final class CourseMaterialForm
{
    public const int RECENT_LIMIT = 10;

    public const int URL_PREVIEW_LENGTH = 36;

    /**
     * @return array<int, mixed>
     */
    public static function schema(string $type): array
    {
        return [
            Grid::make(['default' => 1, 'lg' => 2])
                ->schema([
                    Section::make(__('filament-lms::course-editor.material.create_heading'))
                        ->description(__('filament-lms::course-editor.material.create_description'))
                        ->schema([
                            ...self::fields($type),
                            View::make('filament-lms::filament.course-editor.material-draft-preview')
                                ->visible(fn (Get $get): bool => filled($get('name')) || filled($get('url')))
                                ->viewData(fn (Get $get): array => self::previewData($type, $get)),
                        ]),
                    Section::make(__('filament-lms::course-editor.material.existing_heading'))
                        ->description(__('filament-lms::course-editor.material.existing_description'))
                        ->schema([
                            TextInput::make('existing_search')
                                ->label(__('filament-lms::course-editor.material.search'))
                                ->placeholder(__('filament-lms::course-editor.material.search_placeholder'))
                                ->live(debounce: 300)
                                ->dehydrated(false),
                            Text::make(__('filament-lms::course-editor.material.empty'))
                                ->visible(fn (Get $get): bool => self::recent($type, $get('existing_search'))->isEmpty()),
                            Radio::make('existing_id')
                                ->label(__('filament-lms::course-editor.material.use_existing'))
                                ->helperText(__('filament-lms::course-editor.material.existing_helper'))
                                ->options(fn (Get $get): array => self::options($type, $get('existing_search')))
                                ->descriptions(fn (Get $get): array => self::descriptions($type, $get('existing_search')))
                                ->visible(fn (Get $get): bool => self::recent($type, $get('existing_search'))->isNotEmpty()),
                        ]),
                ]),
        ];
    }

    /**
     * @return class-string<Model>|null
     */
    public static function modelFor(string $type): ?string
    {
        return match ($type) {
            'video' => Video::class,
            'document' => Document::class,
            'link' => Link::class,
            'image' => Image::class,
            'test' => Test::class,
            default => null,
        };
    }

    public static function canCreate(?string $type): bool
    {
        $resource = self::resourceFor((string) $type);

        return $resource !== null && $resource::can('create');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function resolve(string $type, array $data, Schema $schema): Model
    {
        $class = self::modelFor($type);

        if ($class === null) {
            throw new InvalidArgumentException("Cannot create material [{$type}].");
        }

        if (filled($data['existing_id'] ?? null)) {
            $material = $class::query()->find($data['existing_id']);

            if (! $material instanceof Model) {
                throw ValidationException::withMessages([
                    'existing_id' => __('filament-lms::course-editor.material.invalid_existing'),
                ]);
            }

            return $material;
        }

        $material = new $class;
        $material->fill(self::attributes($type, $data));
        $material->save();
        $schema->model($material)->saveRelationships();

        return $material;
    }

    /**
     * @return Collection<int, Model>
     */
    public static function recent(string $type, mixed $search): Collection
    {
        $class = self::modelFor($type);

        if ($class === null) {
            return collect();
        }

        $query = $class::query()->orderByDesc((new $class)->getQualifiedKeyName());
        $search = trim((string) $search);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';

            $query->where(function (Builder $query) use ($like, $type): void {
                $query->where('name', 'like', $like);

                if (in_array($type, ['video', 'link'], true)) {
                    $query->orWhere('url', 'like', $like);
                }
            });
        }

        return $query->limit(self::RECENT_LIMIT)->get();
    }

    /**
     * @return array<int, mixed>
     */
    public static function fields(string $type): array
    {
        $name = TextInput::make('name')
            ->label(__('filament-lms::course-editor.fields.name.label'))
            ->live(onBlur: true)
            ->required(fn (Get $get): bool => blank($get('existing_id')))
            ->maxLength(255);

        return match ($type) {
            'video' => [
                $name,
                TextInput::make('url')
                    ->label(__('filament-lms::course-editor.material.fields.url'))
                    ->helperText(VideoUrlService::getHelperText())
                    ->live(onBlur: true)
                    ->activeUrl()
                    ->required(fn (Get $get): bool => blank($get('existing_id')))
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            if (filled($get('existing_id')) || blank($value)) {
                                return;
                            }

                            $result = VideoUrlService::validateAndConvertWithErrors((string) $value);

                            if ($result['errors'] !== []) {
                                $fail($result['errors']['url']);
                            }
                        },
                    ]),
            ],
            'document' => [
                $name,
                SpatieMediaLibraryFileUpload::make('file')
                    ->label(__('filament-lms::course-editor.material.fields.file'))
                    ->required(fn (Get $get): bool => blank($get('existing_id'))),
                SpatieMediaLibraryFileUpload::make('preview')
                    ->collection('preview')
                    ->label(__('filament-lms::course-editor.material.fields.preview_image'))
                    ->image()
                    ->maxFiles(1)
                    ->helperText(__('filament-lms::course-editor.material.fields.preview_image_helper')),
            ],
            'link' => [
                $name,
                TextInput::make('url')
                    ->label(__('filament-lms::course-editor.material.fields.url'))
                    ->live(onBlur: true)
                    ->activeUrl()
                    ->required(fn (Get $get): bool => blank($get('existing_id'))),
                SpatieMediaLibraryFileUpload::make('preview')
                    ->collection('preview')
                    ->label(__('filament-lms::course-editor.material.fields.preview_image'))
                    ->helperText(__('filament-lms::course-editor.material.fields.link_preview_helper'))
                    ->image(),
            ],
            'image' => [
                $name,
                SpatieMediaLibraryFileUpload::make('image')
                    ->label(__('filament-lms::course-editor.material.fields.image'))
                    ->collection('image')
                    ->image()
                    ->required(fn (Get $get): bool => blank($get('existing_id'))),
            ],
            'test' => [
                $name,
                Select::make('filament_form_id')
                    ->label(__('filament-lms::course-editor.material.fields.form'))
                    ->helperText(__('filament-lms::course-editor.material.fields.form_helper'))
                    ->relationship(
                        name: 'form',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where('locked', true),
                    )
                    ->required(fn (Get $get): bool => blank($get('existing_id'))),
            ],
            default => [$name],
        };
    }

    /**
     * @return array<int|string, string>
     */
    private static function options(string $type, mixed $search): array
    {
        return self::recent($type, $search)
            ->mapWithKeys(fn (Model $material): array => [$material->getKey() => (string) $material->getAttribute('name')])
            ->all();
    }

    /**
     * @return array<int|string, Htmlable>
     */
    private static function descriptions(string $type, mixed $search): array
    {
        $descriptions = [];

        foreach (self::recent($type, $search) as $material) {
            $detail = self::existingDetail($material);

            if ($detail === null) {
                continue;
            }

            $descriptions[$material->getKey()] = $detail;
        }

        return $descriptions;
    }

    public static function existingDetail(Model $material): ?Htmlable
    {
        $url = self::urlFrom($material);

        if ($url === null) {
            return null;
        }

        return new HtmlString(view('filament-lms::filament.course-editor.material-url', [
            'url' => $url,
            'label' => self::urlPreviewLabel($url),
        ])->render());
    }

    public static function urlPreviewLabel(string $url): string
    {
        return mb_strimwidth($url, 0, self::URL_PREVIEW_LENGTH, '...');
    }

    public static function urlFrom(Model $material): ?string
    {
        $url = $material->getAttributes()['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * @return array{materialType: string, name: string, url: ?string, embedUrl: ?string}
     */
    private static function previewData(string $type, Get $get): array
    {
        $url = filled($get('url')) ? (string) $get('url') : null;
        $embedUrl = null;

        if ($type === 'video' && $url !== null) {
            $converted = VideoUrlService::convertToEmbedUrl($url);
            $embedUrl = VideoUrlService::isValidEmbedUrl($converted) ? $converted : null;
        }

        return [
            'materialType' => $type,
            'name' => (string) ($get('name') ?? ''),
            'url' => $url,
            'embedUrl' => $embedUrl,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function attributes(string $type, array $data): array
    {
        return match ($type) {
            'video' => [
                'name' => $data['name'],
                'url' => VideoUrlService::convertToEmbedUrl((string) $data['url']),
            ],
            'link' => [
                'name' => $data['name'],
                'url' => $data['url'],
            ],
            'test' => [
                'name' => $data['name'],
                'filament_form_id' => $data['filament_form_id'],
            ],
            default => [
                'name' => $data['name'],
            ],
        };
    }

    /**
     * @return class-string<resource>|null
     */
    private static function resourceFor(string $type): ?string
    {
        return match ($type) {
            'video' => VideoResource::class,
            'document' => DocumentResource::class,
            'link' => LinkResource::class,
            'image' => ImageResource::class,
            'test' => TestResource::class,
            default => null,
        };
    }
}
