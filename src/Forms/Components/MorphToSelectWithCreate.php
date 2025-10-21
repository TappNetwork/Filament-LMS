<?php

namespace Tapp\FilamentLms\Forms\Components;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Actions\Action;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Image;
use Tapp\FilamentLms\Models\Link;
use Tapp\FilamentLms\Models\Video;

class MorphToSelectWithCreate
{
    public static function make(string $name): array
    {
        return [
            Select::make('material_type')
                ->label('Material Type')
                ->options([
                    Video::class => 'Video',
                    Document::class => 'Document',
                    Link::class => 'Link',
                    Image::class => 'Image',
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

                    return $materialType::query()->pluck('name', 'id');
                })
                ->searchable()
                ->required()
                ->suffixActions([
                    Action::make('create_video')
                        ->label('Create Video')
                        ->icon('heroicon-o-video-camera')
                        ->color('success')
                        ->visible(fn (Get $get) => $get('material_type') === Video::class)
                        ->form([
                            TextInput::make('name')
                                ->required(),
                            TextInput::make('url')
                                ->helperText(new HtmlString('YouTube: https://www.youtube.com/embed/xxxxxxxxxxx<br/>Vimeo: https://player.vimeo.com/video/xxxxxxxxx'))
                                ->regex('/(https:\/\/www\.youtube\.com\/embed\/|https:\/\/player\.vimeo\.com\/video\/)([a-zA-Z0-9_-]+)/')
                                ->activeUrl()
                                ->required(),
                        ])
                        ->action(function (array $data, Set $set) {
                            $video = Video::create($data);
                            $set('material_id', $video->id);
                        }),

                    Action::make('create_document')
                        ->label('Create Document')
                        ->icon('heroicon-o-document')
                        ->color('success')
                        ->visible(fn (Get $get) => $get('material_type') === Document::class)
                        ->form([
                            TextInput::make('name')
                                ->required(),
                            SpatieMediaLibraryFileUpload::make('file')
                                ->required(),
                            SpatieMediaLibraryFileUpload::make('preview')
                                ->collection('preview')
                                ->label('Custom Preview Image (optional)')
                                ->image()
                                ->maxFiles(1),
                        ])
                        ->action(function (array $data, Set $set) {
                            $document = Document::create(['name' => $data['name']]);
                            $set('material_id', $document->id);
                        }),

                    Action::make('create_link')
                        ->label('Create Link')
                        ->icon('heroicon-o-link')
                        ->color('success')
                        ->visible(fn (Get $get) => $get('material_type') === Link::class)
                        ->form([
                            TextInput::make('name')
                                ->required(),
                            TextInput::make('url')
                                ->activeUrl()
                                ->required(),
                            SpatieMediaLibraryFileUpload::make('preview')
                                ->collection('preview')
                                ->image()
                                ->helperText('Optional - will be auto-generated if not provided'),
                        ])
                        ->action(function (array $data, Set $set) {
                            $link = Link::create(['name' => $data['name'], 'url' => $data['url']]);
                            $set('material_id', $link->id);
                        }),

                    Action::make('create_image')
                        ->label('Create Image')
                        ->icon('heroicon-o-photo')
                        ->color('success')
                        ->visible(fn (Get $get) => $get('material_type') === Image::class)
                        ->form([
                            TextInput::make('name')
                                ->required(),
                            SpatieMediaLibraryFileUpload::make('image')
                                ->image()
                                ->required(),
                        ])
                        ->action(function (array $data, Set $set) {
                            $image = Image::create(['name' => $data['name']]);
                            $set('material_id', $image->id);
                        }),
                ]),
        ];
    }
}
