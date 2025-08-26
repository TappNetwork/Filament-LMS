<?php

namespace Tapp\FilamentLms\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Tapp\FilamentLms\Models\Link;

class GenerateLinkScreenshot implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Link $link
    ) {}

    public function handle(): void
    {
        try {
            // Save screenshot to temp file
            $tempPath = sys_get_temp_dir().'/link-preview-'.$this->link->id.'-'.Str::uuid().'.jpg';

            Storage::disk('public')->makeDirectory('filament-lms/link-screenshots');

            Browsershot::url($this->link->url)
                ->windowSize(1280, 800)
                ->waitUntilNetworkIdle()
                ->setOption('user-data-dir', '/tmp/chrome-data-'.Str::uuid())
                ->save($tempPath);

            $this->link->clearMediaCollection('preview');
            $this->link->addMedia($tempPath)
                ->preservingOriginal()
                ->toMediaCollection('preview');

            @unlink($tempPath);
        } catch (Exception $e) {
            Log::error('Failed to generate screenshot for link: '.$this->link->url, [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
