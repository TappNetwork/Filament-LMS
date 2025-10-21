<?php

namespace Tapp\FilamentLms\Resources\StepResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\HtmlString;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Image;
use Tapp\FilamentLms\Models\Link;
use Tapp\FilamentLms\Models\Video;
use Tapp\FilamentLms\Resources\StepResource;

class CreateStep extends CreateRecord
{
    protected static string $resource = StepResource::class;

    protected function fillForm(): void
    {
        parent::fillForm();

        $request = request();

        $this->form->fill($request->query());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_video')
                ->label('Create Video')
                ->icon('heroicon-o-video-camera')
                ->color('success')
                ->visible(false)
                ->form([
                    TextInput::make('name')
                        ->required(),
                    TextInput::make('url')
                        ->helperText(new HtmlString('YouTube: https://www.youtube.com/embed/xxxxxxxxxxx<br/>Vimeo: https://player.vimeo.com/video/xxxxxxxxx'))
                        ->regex('/(https:\/\/www\.youtube\.com\/embed\/|https:\/\/player\.vimeo\.com\/video\/)([a-zA-Z0-9_-]+)/')
                        ->activeUrl()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $video = Video::create($data);
                    $this->form->fill(['material' => $video->getKey(), 'material_type' => Video::class]);
                }),
            Action::make('create_document')
                ->label('Create Document')
                ->icon('heroicon-o-document')
                ->color('success')
                ->visible(false)
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
                ->action(function (array $data) {
                    $document = Document::create(['name' => $data['name']]);
                    $this->form->fill(['material' => $document->getKey(), 'material_type' => Document::class]);
                }),
            Action::make('create_link')
                ->label('Create Link')
                ->icon('heroicon-o-link')
                ->color('success')
                ->visible(false)
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
                ->action(function (array $data) {
                    $link = Link::create(['name' => $data['name'], 'url' => $data['url']]);
                    $this->form->fill(['material' => $link->getKey(), 'material_type' => Link::class]);
                }),
            Action::make('create_image')
                ->label('Create Image')
                ->icon('heroicon-o-photo')
                ->color('success')
                ->visible(false)
                ->form([
                    TextInput::make('name')
                        ->required(),
                    SpatieMediaLibraryFileUpload::make('image')
                        ->image()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $image = Image::create(['name' => $data['name']]);
                    $this->form->fill(['material' => $image->getKey(), 'material_type' => Image::class]);
                }),
        ];
    }

}
