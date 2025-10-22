<?php

namespace Tapp\FilamentLms\Forms\Components;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;
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
    public static function make(string $name): array
    {
        return [
            Select::make('material_type')
                ->label('Material Type')
                ->options([
                    'video' => 'Video',
                    'document' => 'Document',
                    'link' => 'Link',
                    'image' => 'Image',
                    'form' => 'Filament form',
                    'test' => 'Test',
                ])
                ->live()
                ->required()
                ->afterStateUpdated(function (Set $set) {
                    $set('material_id', null);
                }),

            Select::make('material_id')
                ->label('Select Material')
                ->options(function (Get $get) {
                    $materialType = $get('material_type');
                    if (!$materialType) {
                        return [];
                    }

                    // Map morph aliases to class names
                    $classMap = [
                        'video' => Video::class,
                        'document' => Document::class,
                        'link' => Link::class,
                        'image' => Image::class,
                        'form' => FilamentForm::class,
                        'test' => Test::class,
                    ];

                    $className = $classMap[$materialType] ?? null;
                    if (!$className) {
                        return [];
                    }

                    return $className::query()->pluck('name', 'id');
                })
                ->searchable()
                ->required()
                ->suffixActions([
                    Action::make('create_video')
                        ->label('New')
                        ->icon('heroicon-o-plus')
                        ->color('primary')
                        ->visible(fn (Get $get) => $get('material_type') === 'video')
                        ->schema(VideoResource::form(\Filament\Schemas\Schema::make())->getComponents())
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
