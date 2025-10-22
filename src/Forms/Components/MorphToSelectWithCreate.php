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
                        ->form([
                            TextInput::make('name')
                                ->required(),
                            TextInput::make('url')
                                ->helperText(new HtmlString('Paste any YouTube or Vimeo URL (watch, short, or embed format)<br/>Examples:<br/>• https://www.youtube.com/watch?v=ABC123<br/>• https://youtu.be/ABC123<br/>• https://vimeo.com/123456<br/>• https://www.youtube.com/embed/ABC123'))
                                ->activeUrl()
                                ->required(),
                        ])
                        ->action(function (array $data, Set $set) {
                            // Convert standard YouTube/Vimeo URLs to embed URLs
                            $data['url'] = self::convertToEmbedUrl($data['url']);
                            
                            $video = Video::create($data);
                            $set('material_id', $video->id);
                        }),
                ]),
        ];
    }

    /**
     * Convert standard YouTube/Vimeo URLs to embed URLs
     */
    private static function convertToEmbedUrl(string $url): string
    {
        // If it's already an embed URL, return as is
        if (str_contains($url, 'youtube.com/embed/') || str_contains($url, 'player.vimeo.com/video/')) {
            return $url;
        }

        // Convert YouTube watch URLs to embed URLs
        if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        // Convert YouTube short URLs to embed URLs
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        // Convert Vimeo URLs to embed URLs
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return 'https://player.vimeo.com/video/' . $matches[1];
        }

        // If no conversion is possible, return the original URL
        return $url;
    }
}
