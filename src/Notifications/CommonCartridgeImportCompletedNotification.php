<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CommonCartridgeImportCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?string $courseName = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'course_name' => $this->courseName,
        ];
    }
}
