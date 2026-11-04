<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Feature;

use Filament\Facades\Filament;
use Illuminate\Contracts\Support\MessageBag as MessageBagContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Server;
use Laravel\Sanctum\Sanctum;
use Livewire\Component;
use Livewire\Livewire;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tapp\FilamentFormBuilder\Livewire\FilamentForm\Show as FilamentFormShow;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentFormBuilder\Models\FilamentFormField;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Helpers\TenantHelper;
use Tapp\FilamentLms\Jobs\GenerateLinkScreenshot;
use Tapp\FilamentLms\Livewire\TestStep;
use Tapp\FilamentLms\Mcp\LmsServer;
use Tapp\FilamentLms\Mcp\LogMcpAuditor;
use Tapp\FilamentLms\Mcp\McpAuditor;
use Tapp\FilamentLms\Mcp\RemoteMediaFetcher;
use Tapp\FilamentLms\Mcp\Tools\CreateDocumentStep;
use Tapp\FilamentLms\Mcp\Tools\CreateImageStep;
use Tapp\FilamentLms\Mcp\Tools\CreateLinkStep;
use Tapp\FilamentLms\Mcp\Tools\CreateTestStep;
use Tapp\FilamentLms\Mcp\Tools\CreateTextStep;
use Tapp\FilamentLms\Mcp\Tools\DeleteCourse;
use Tapp\FilamentLms\Mcp\Tools\DeleteLesson;
use Tapp\FilamentLms\Mcp\Tools\DeleteStep;
use Tapp\FilamentLms\Mcp\Tools\GetTest;
use Tapp\FilamentLms\Mcp\Tools\UpdateStep;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Image;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Link;
use Tapp\FilamentLms\Models\Scopes\TenantScope;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\StepUser;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentLms\Pages\Step as StepPage;
use Tapp\FilamentLms\Tests\TestTeam;
use Tapp\FilamentLms\Tests\TestUser;

class ContentToolsStepPage extends StepPage
{
    public function getErrorBag(): MessageBagContract
    {
        return new MessageBag;
    }
}

class ContentToolsFormShow extends FilamentFormShow
{
    public function getErrorBag(): MessageBagContract
    {
        return new MessageBag;
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

class ContentToolsTestStep extends TestStep
{
    public function getErrorBag(): MessageBagContract
    {
        return new MessageBag;
    }
}

class ContentToolsNotAnAuditor {}

class ContentToolsRecordingAuditor implements McpAuditor
{
    /** @var list<array<string, mixed>> */
    public static array $records = [];

    public function record(array $context): void
    {
        self::$records[] = $context;
    }
}

class ContentToolsStubComponent extends Component
{
    public function mount(mixed ...$parameters): void {}

    public function getErrorBag(): MessageBagContract
    {
        return new MessageBag;
    }

    public function render(): string
    {
        return '<div></div>';
    }
}

beforeEach(function () {
    config([
        'filament-lms.tenancy.enabled' => false,
        'filament-lms.mcp.auditor' => null,
    ]);
    Filament::setTenant(null);
    Model::clearBootedModels();

    if (! class_exists(LmsServer::class) || ! class_exists(Server::class)) {
        $this->markTestSkipped('laravel/mcp is required to run LMS MCP tests.');
    }

    Bus::fake([GenerateLinkScreenshot::class]);

    app()->instance(RemoteMediaFetcher::class, new class extends RemoteMediaFetcher
    {
        protected function resolveHost(string $host): array
        {
            return match ($host) {
                'intranet.example.test' => ['10.0.0.5'],
                'nat64.example.test' => ['64:ff9b::7f00:1'],
                default => ['93.184.215.14'],
            };
        }
    });
});

afterEach(function () {
    Filament::setTenant(null);
    config([
        'filament-lms.tenancy.enabled' => false,
        'filament-lms.mcp.auditor' => null,
    ]);

    $dispatcher = Model::getEventDispatcher();

    if ($dispatcher !== null) {
        foreach ([Course::class, Lesson::class, Step::class, Link::class, Document::class, Image::class, Test::class] as $model) {
            $dispatcher->forget('eloquent.creating: '.$model);
        }

        $dispatcher->forget('eloquent.deleting: '.Media::class);
    }

    Model::clearBootedModels();
    ContentToolsRecordingAuditor::$records = [];
});

function contentToolsUser(bool $isAdmin = true, string $email = 'content-admin@example.com'): TestUser
{
    $user = $isAdmin
        ? new class extends TestUser
        {
            public function isLmsAdmin(): bool
            {
                return true;
            }
        }
    : new class extends TestUser
    {
        public function isLmsAdmin(): bool
        {
            return false;
        }
    };

    $user->forceFill([
        'name' => $isAdmin ? 'Admin' : 'Member',
        'email' => $email,
        'password' => bcrypt('password'),
    ])->save();

    return $user;
}

function contentToolsLesson(): Lesson
{
    $course = Course::factory()->create([
        'name' => 'Content Course',
        'slug' => 'content-course',
        'external_id' => 'content_course',
        'is_private' => true,
    ]);

    return Lesson::factory()->create([
        'course_id' => $course->id,
        'name' => 'Lesson One',
        'slug' => 'lesson-one',
        'order' => 1,
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function contentToolsQuizQuestions(): array
{
    return [
        [
            'type' => 'radio',
            'text' => 'What is the capital of France?',
            'options' => ['Paris', 'Lyon', 'Nice'],
            'correct_answer' => 'Paris',
        ],
        [
            'type' => 'select_multiple',
            'text' => 'Which numbers are even?',
            'options' => ['2', '3', '4', '5'],
            'correct_answers' => ['4', '2'],
        ],
        [
            'type' => 'checkbox',
            'text' => 'The sky is blue.',
            'correct_answer' => 'true',
        ],
        [
            'type' => 'select',
            'text' => 'Pick the largest planet.',
            'options' => ['Mars', 'Jupiter'],
            'correct_answer' => 'Jupiter',
        ],
        [
            'type' => 'text',
            'text' => 'Type the word yes.',
            'correct_answer' => 'yes',
        ],
    ];
}

function contentToolsPng(): string
{
    $image = imagecreatetruecolor(4, 4);
    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

function contentToolsPdf(): string
{
    return "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";
}

/**
 * @param  array<string, mixed>  $answers  field index => form state value
 */
function contentToolsSubmitQuiz(FilamentForm $form, array $answers): FilamentFormUser
{
    $fields = $form->filamentFormFields()->get()->values();
    $component = Livewire::test(ContentToolsFormShow::class, ['form' => $form, 'blockRedirect' => true]);

    foreach ($answers as $index => $value) {
        $component->set('data.'.$fields[$index]->id, $value);
    }

    $component->call('create')->assertHasNoErrors();

    return FilamentFormUser::query()
        ->where('filament_form_id', $form->id)
        ->where('user_id', auth()->id())
        ->latest('id')
        ->firstOrFail();
}

dataset('new content tools', [
    'create_text_step' => [CreateTextStep::class, 'create_text_step'],
    'create_link_step' => [CreateLinkStep::class, 'create_link_step'],
    'create_document_step' => [CreateDocumentStep::class, 'create_document_step'],
    'create_image_step' => [CreateImageStep::class, 'create_image_step'],
    'create_test_step' => [CreateTestStep::class, 'create_test_step'],
    'get_test' => [GetTest::class, 'get_test'],
]);

test('new tools are registered as sanctum abilities', function (string $tool, string $name) {
    expect(LmsServer::TOOLS)->toContain($tool)
        ->and(LmsServer::sanctumAbilities())->toHaveKey($name);
})->with('new content tools');

test('delete tools are annotated as destructive so clients ask before calling them', function (string $tool) {
    expect((new $tool)->toArray()['annotations'])->toHaveKey('destructiveHint', true);
})->with([DeleteCourse::class, DeleteLesson::class, DeleteStep::class]);

test('new tools deny non-admin users', function (string $tool) {
    $this->actingAs(contentToolsUser(isAdmin: false, email: 'member@example.com'));

    LmsServer::tool($tool, ['lesson_id' => 1, 'id' => 1])
        ->assertHasErrors(['You must be an LMS admin to use this tool.']);
})->with('new content tools');

test('new tools deny tokens without the tool ability', function (string $tool, string $name) {
    Sanctum::actingAs(contentToolsUser(), ['list_courses']);

    LmsServer::tool($tool, ['lesson_id' => 1, 'id' => 1])
        ->assertHasErrors(['Token lacks ability '.$name.'.']);
})->with('new content tools');

test('new tools require tenant context when tenancy is enabled', function (string $tool) {
    config(['filament-lms.tenancy.enabled' => true]);
    $this->actingAs(contentToolsUser());

    LmsServer::tool($tool, ['lesson_id' => 1, 'id' => 1])
        ->assertHasErrors(['Tenant context is required when LMS tenancy is enabled.']);
})->with('new content tools');

test('a passport-style oauth token with the mcp:use scope can call tools', function () {
    $admin = contentToolsUser();
    $admin->withAccessToken(new class
    {
        public function can(string $scope): bool
        {
            return $scope === 'mcp:use';
        }
    });
    $this->actingAs($admin);

    LmsServer::tool(CreateTextStep::class, [
        'lesson_id' => contentToolsLesson()->id,
        'name' => 'Welcome',
        'text' => 'Hello',
    ])->assertOk();
});

test('a sanctum token with only the mcp:use ability is still denied', function () {
    Sanctum::actingAs(contentToolsUser(), ['mcp:use']);

    LmsServer::tool(CreateTextStep::class, ['lesson_id' => 1])
        ->assertHasErrors(['Token lacks ability create_text_step.']);
});

test('create_text_step creates a step without material and logs the write', function () {
    Log::spy();
    config(['filament-lms.mcp.auditor' => LogMcpAuditor::class]);
    $admin = contentToolsUser();
    $this->actingAs($admin);
    $lesson = contentToolsLesson();

    LmsServer::tool(CreateTextStep::class, [
        'lesson_id' => $lesson->id,
        'name' => 'Read me',
        'text' => "## Intro\n\nSome **bold** text.",
        'is_optional' => true,
    ])->assertOk()->assertSee('Read me');

    $step = Step::query()->firstOrFail();

    expect($step->material_type)->toBeNull()
        ->and($step->material_id)->toBeNull()
        ->and($step->slug)->toBe('content-course-lesson-one-read-me')
        ->and($step->is_optional)->toBeTrue();

    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'LMS MCP write.'
        && $context['tool'] === 'create_text_step'
        && $context['user_id'] === $admin->id
        && $context['step_id'] === $step->id);
});

test('mcp writes are not audited unless an auditor class is configured', function () {
    Log::spy();
    $this->actingAs(contentToolsUser());

    LmsServer::tool(CreateTextStep::class, [
        'lesson_id' => contentToolsLesson()->id,
        'name' => 'Quiet',
        'text' => 'No log.',
    ])->assertOk();

    Log::shouldNotHaveReceived('info', ['LMS MCP write.', \Mockery::any()]);
});

test('mcp writes are sent to the configured auditor', function () {
    $admin = contentToolsUser(email: 'auditor@example.com');
    $this->actingAs($admin);
    config(['filament-lms.mcp.auditor' => ContentToolsRecordingAuditor::class]);

    LmsServer::tool(CreateTextStep::class, [
        'lesson_id' => contentToolsLesson()->id,
        'name' => 'Audited',
        'text' => 'Record this.',
    ])->assertOk();

    expect(ContentToolsRecordingAuditor::$records)->toBe([[
        'tool' => 'create_text_step',
        'user_id' => $admin->id,
        'step_id' => Step::query()->value('id'),
    ]]);
});

test('mcp writes reject an auditor that does not implement the contract', function () {
    config([
        'app.debug' => true,
        'filament-lms.mcp.auditor' => ContentToolsNotAnAuditor::class,
    ]);
    $this->actingAs(contentToolsUser());

    LmsServer::tool(CreateTextStep::class, [
        'lesson_id' => contentToolsLesson()->id,
        'name' => 'Nope',
        'text' => 'Still saved.',
    ])->assertHasErrors(['filament-lms.mcp.auditor must be a class that implements']);
});

test('create_text_step appends after the highest order when a middle step was deleted', function () {
    // Host apps can turn off eloquent-sortable's creating hook. Order still has to stay unique.
    config(['eloquent-sortable.sort_when_creating' => false]);
    $this->actingAs(contentToolsUser());
    $lesson = contentToolsLesson();

    foreach (['One', 'Two', 'Three'] as $name) {
        LmsServer::tool(CreateTextStep::class, [
            'lesson_id' => $lesson->id,
            'name' => $name,
            'text' => $name,
            'slug' => 'step-'.strtolower($name),
        ])->assertOk();
    }

    $middle = Step::query()->where('name', 'Two')->firstOrFail();

    expect($lesson->steps()->orderBy('order')->pluck('order')->all())->toBe([1, 2, 3]);

    LmsServer::tool(DeleteStep::class, ['id' => $middle->id])->assertOk();

    LmsServer::tool(CreateTextStep::class, [
        'lesson_id' => $lesson->id,
        'name' => 'Four',
        'text' => 'Four',
        'slug' => 'step-four',
    ])->assertOk();

    expect($lesson->steps()->orderBy('order')->pluck('name', 'order')->all())->toBe([
        1 => 'One',
        3 => 'Three',
        4 => 'Four',
    ]);
});

test('create_text_step requires text and a unique slug', function () {
    $this->actingAs(contentToolsUser());
    $lesson = contentToolsLesson();

    LmsServer::tool(CreateTextStep::class, ['lesson_id' => $lesson->id, 'name' => 'Empty'])
        ->assertHasErrors();

    LmsServer::tool(CreateTextStep::class, ['lesson_id' => $lesson->id, 'name' => 'Once', 'text' => 'A'])->assertOk();
    LmsServer::tool(CreateTextStep::class, ['lesson_id' => $lesson->id, 'name' => 'Once', 'text' => 'B'])
        ->assertHasErrors(['A step with this slug already exists.']);

    expect(Step::query()->count())->toBe(1);
});

test('a text-only step renders its markdown and the next button on the learner step page', function () {
    $this->actingAs(contentToolsUser());
    $lesson = contentToolsLesson();
    $lesson->course->update(['is_private' => false]);

    LmsServer::tool(CreateTextStep::class, [
        'lesson_id' => $lesson->id,
        'name' => 'Reading',
        'text' => 'Some **bold** words.',
    ])->assertOk();

    Livewire::test(ContentToolsStepPage::class, [
        'courseSlug' => 'content-course',
        'lessonSlug' => 'lesson-one',
        'stepSlug' => 'content-course-lesson-one-reading',
    ])
        ->assertSeeHtml('<strong>bold</strong>')
        ->assertSeeHtml('next-button')
        ->assertDontSee('unsupported material type');
});

test('create_link_step creates a link step and queues a preview screenshot', function () {
    $this->actingAs(contentToolsUser());
    $lesson = contentToolsLesson();

    LmsServer::tool(CreateLinkStep::class, [
        'lesson_id' => $lesson->id,
        'name' => 'Read the docs',
        'url' => 'https://laravel.com/docs',
        'text' => 'Skim the intro.',
    ])->assertOk()->assertSee('laravel.com');

    $step = Step::query()->with('material')->firstOrFail();

    expect($step->material_type)->toBe('link')
        ->and($step->material)->toBeInstanceOf(Link::class)
        ->and($step->material->name)->toBe('Read the docs')
        ->and($step->text)->toBe('Skim the intro.');

    Bus::assertDispatched(GenerateLinkScreenshot::class);
});

test('create_link_step rejects non-http and private urls', function (string $url) {
    $this->actingAs(contentToolsUser());

    LmsServer::tool(CreateLinkStep::class, [
        'lesson_id' => contentToolsLesson()->id,
        'name' => 'Bad link',
        'url' => $url,
    ])->assertHasErrors();

    expect(Link::query()->count())->toBe(0)
        ->and(Step::query()->count())->toBe(0);
})->with([
    'javascript' => 'javascript:alert(1)',
    'ftp' => 'ftp://example.com/file',
    'loopback' => 'http://127.0.0.1/admin',
    'metadata' => 'http://169.254.169.254/latest/meta-data',
    'dns to private ip' => 'https://intranet.example.test/wiki',
    'nat64 prefix' => 'https://nat64.example.test/wiki',
    'nonstandard port' => 'https://laravel.com:8443/docs',
]);

test('remote url checks block nat64, reject extra ports, and pin ipv4', function () {
    $fetcher = new class extends RemoteMediaFetcher
    {
        protected function resolveHost(string $host): array
        {
            return match ($host) {
                'nat64.example.test' => ['64:ff9b::a9fe:a9fe'],
                'dual.example.test' => ['2606:4700:4700::1111', '93.184.215.14'],
                default => ['93.184.215.14'],
            };
        }
    };

    expect(fn () => $fetcher->assertPublicUrl('https://nat64.example.test/a.pdf', ['https'], 'file_url'))
        ->toThrow(ValidationException::class, 'The URL must point to a public host.');

    expect(fn () => $fetcher->assertPublicUrl('https://[64:ff9b::7f00:1]/meta', ['https'], 'file_url'))
        ->toThrow(ValidationException::class, 'The URL must point to a public host.');

    expect(fn () => $fetcher->assertPublicUrl('https://cdn.example.com:8443/a.pdf', ['https'], 'file_url'))
        ->toThrow(ValidationException::class, 'The URL must use port 443.');

    expect(fn () => $fetcher->assertPublicUrl('https://cdn.example.com:80/a.pdf', ['https'], 'file_url'))
        ->toThrow(ValidationException::class, 'The URL must use port 443.');

    expect($fetcher->assertPublicUrl('https://dual.example.test/a.pdf', ['https'], 'file_url'))
        ->toBe('dual.example.test:443:93.184.215.14')
        ->and($fetcher->assertPublicUrl('http://cdn.example.com/a', ['http', 'https'], 'url'))
        ->toBe('cdn.example.com:80:93.184.215.14');
});

test('update_step updates a link and clears the stale preview', function () {
    $this->actingAs(contentToolsUser());
    $lesson = contentToolsLesson();

    LmsServer::tool(CreateLinkStep::class, [
        'lesson_id' => $lesson->id,
        'name' => 'Docs',
        'url' => 'https://laravel.com/docs',
    ])->assertOk();

    $step = Step::query()->firstOrFail();
    $link = Link::query()->firstOrFail();
    $preview = sys_get_temp_dir().'/lms-link-preview.png';
    file_put_contents($preview, contentToolsPng());
    $link->addMedia($preview)->toMediaCollection('preview');

    $levelDuringClear = null;
    Media::deleting(function () use (&$levelDuringClear): void {
        $levelDuringClear = DB::transactionLevel();
    });

    LmsServer::tool(UpdateStep::class, [
        'id' => $step->id,
        'link_url' => 'https://filamentphp.com/docs',
        'link_name' => 'Filament docs',
    ])->assertOk()->assertSee('filamentphp.com');

    $link->refresh();

    expect($link->url)->toBe('https://filamentphp.com/docs')
        ->and($link->name)->toBe('Filament docs')
        ->and($link->getFirstMedia('preview'))->toBeNull()
        ->and($levelDuringClear)->toBe(0);

    Bus::assertDispatchedTimes(GenerateLinkScreenshot::class, 2);

    LmsServer::tool(UpdateStep::class, [
        'id' => $step->id,
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ])->assertHasErrors(['This step is not a video.']);

    LmsServer::tool(UpdateStep::class, [
        'id' => $step->id,
        'link_url' => 'http://10.1.2.3/',
    ])->assertHasErrors(['The URL must point to a public host.']);
});

test('update_step rejects link fields on a video step', function () {
    $this->actingAs(contentToolsUser());
    $lesson = contentToolsLesson();
    $step = Step::factory()->create(['lesson_id' => $lesson->id, 'material_type' => 'video', 'material_id' => 1]);

    LmsServer::tool(UpdateStep::class, [
        'id' => $step->id,
        'link_url' => 'https://laravel.com',
    ])->assertHasErrors(['This step is not a link.']);
});

test('create_document_step downloads a pdf into the media library', function () {
    Http::fake(['*' => Http::response(contentToolsPdf(), 200, ['Content-Type' => 'application/pdf'])]);
    $this->actingAs(contentToolsUser());

    LmsServer::tool(CreateDocumentStep::class, [
        'lesson_id' => contentToolsLesson()->id,
        'name' => 'Handbook',
        'file_url' => 'https://93.184.215.14/handbook.pdf',
    ])->assertOk();

    $document = Document::query()->firstOrFail();
    $media = $document->getFirstMedia();

    expect(Step::query()->firstOrFail()->material_type)->toBe('document')
        ->and($media)->not->toBeNull()
        ->and($media->mime_type)->toBe('application/pdf')
        ->and($media->file_name)->toBe('handbook.pdf');
});

test('create_image_step downloads an image into the image collection', function () {
    Http::fake(['*' => Http::response(contentToolsPng(), 200, ['Content-Type' => 'image/png'])]);
    $this->actingAs(contentToolsUser());

    LmsServer::tool(CreateImageStep::class, [
        'lesson_id' => contentToolsLesson()->id,
        'name' => 'Diagram',
        'file_url' => 'https://cdn.example.com/diagram.png',
    ])->assertOk();

    $image = Image::query()->firstOrFail();

    expect(Step::query()->firstOrFail()->material_type)->toBe('image')
        ->and($image->getFirstMedia('image')?->mime_type)->toBe('image/png');
});

test('a rolled back media step deletes the file saved to disk', function () {
    Http::fake(['*' => Http::response(contentToolsPdf(), 200, ['Content-Type' => 'application/pdf'])]);
    config(['app.debug' => true]);
    $this->actingAs(contentToolsUser());
    $token = 'rollback-handbook-'.uniqid();
    $savedCopies = fn (): array => array_values(array_filter(
        Storage::disk('local')->allFiles(),
        fn (string $path): bool => str_contains($path, $token),
    ));

    Step::creating(function (): void {
        throw new RuntimeException('fail the step');
    });

    try {
        LmsServer::tool(CreateDocumentStep::class, [
            'lesson_id' => contentToolsLesson()->id,
            'name' => 'Handbook',
            'document_name' => $token,
            'file_url' => 'https://cdn.example.com/handbook.pdf',
        ])->assertHasErrors(['fail the step']);
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('fail the step');
    }

    expect($savedCopies())->toBe([])
        ->and(Document::query()->count())->toBe(0)
        ->and(Media::query()->count())->toBe(0);
});

test('media step tools reject unsafe downloads', function (string $tool, string $url, mixed $response, string $error) {
    Http::fake(['*' => $response]);
    config(['filament-lms.mcp.remote_media_max_kb' => 1]);
    $this->actingAs(contentToolsUser());

    LmsServer::tool($tool, [
        'lesson_id' => contentToolsLesson()->id,
        'name' => 'Unsafe',
        'file_url' => $url,
    ])->assertHasErrors([$error]);

    expect(Step::query()->count())->toBe(0)
        ->and(Document::query()->count())->toBe(0)
        ->and(Image::query()->count())->toBe(0);
})->with([
    'http scheme' => [CreateImageStep::class, 'http://cdn.example.com/a.png', fn () => Http::response(contentToolsPng(), 200, ['Content-Type' => 'image/png']), 'file url'],
    'loopback host' => [CreateImageStep::class, 'https://127.0.0.1/a.png', fn () => Http::response(contentToolsPng(), 200, ['Content-Type' => 'image/png']), 'The URL must point to a public host.'],
    'dns to private ip' => [CreateDocumentStep::class, 'https://intranet.example.test/a.pdf', fn () => Http::response(contentToolsPdf(), 200, ['Content-Type' => 'application/pdf']), 'The URL must point to a public host.'],
    'svg' => [CreateImageStep::class, 'https://cdn.example.com/a.svg', fn () => Http::response('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 200, ['Content-Type' => 'image/svg+xml']), 'The file content type must be one of'],
    'html disguised as png' => [CreateImageStep::class, 'https://cdn.example.com/a.png', fn () => Http::response('<html><body>hi</body></html>', 200, ['Content-Type' => 'image/png']), 'The file contents do not match an allowed type'],
    'too large' => [CreateDocumentStep::class, 'https://cdn.example.com/a.pdf', fn () => Http::response(contentToolsPdf().str_repeat('0', 2048), 200, ['Content-Type' => 'application/pdf']), 'The file must not be larger than 1 KB.'],
    'redirect' => [CreateDocumentStep::class, 'https://cdn.example.com/a.pdf', fn () => Http::response('', 302, ['Location' => 'http://127.0.0.1/']), 'Redirects are not followed'],
    'nat64 prefix' => [CreateDocumentStep::class, 'https://nat64.example.test/a.pdf', fn () => Http::response(contentToolsPdf(), 200, ['Content-Type' => 'application/pdf']), 'The URL must point to a public host.'],
    'nonstandard port' => [CreateDocumentStep::class, 'https://cdn.example.com:8443/a.pdf', fn () => Http::response(contentToolsPdf(), 200, ['Content-Type' => 'application/pdf']), 'The URL must use port 443.'],
    'port 80 when only https is allowed' => [CreateImageStep::class, 'https://cdn.example.com:80/a.png', fn () => Http::response(contentToolsPng(), 200, ['Content-Type' => 'image/png']), 'The URL must use port 443.'],
]);

test('create_test_step builds a locked form, rubric, test, and step in one transaction', function () {
    $this->actingAs(contentToolsUser());

    LmsServer::tool(CreateTestStep::class, [
        'lesson_id' => contentToolsLesson()->id,
        'name' => 'Quiz',
        'test_name' => 'Module quiz',
        'require_perfect_score' => true,
        'questions' => contentToolsQuizQuestions(),
    ])->assertOk()->assertSee('Module quiz');

    $step = Step::query()->firstOrFail();
    $test = Test::query()->with(['form.filamentFormFields', 'rubric'])->firstOrFail();
    $fields = $test->form->filamentFormFields;

    expect($step->material_type)->toBe('test')
        ->and($step->material_id)->toBe($test->id)
        ->and($step->require_perfect_score)->toBeTrue()
        ->and((bool) $test->form->locked)->toBeTrue()
        ->and($fields)->toHaveCount(5)
        ->and($fields->pluck('type')->map->name->all())->toBe(['RADIO', 'SELECT_MULTIPLE', 'CHECKBOX', 'SELECT', 'TEXT'])
        ->and((bool) $fields[2]->required)->toBeFalse()
        ->and((bool) $fields[0]->required)->toBeTrue()
        ->and($test->rubric->user_id)->toBeNull()
        ->and(array_column($test->rubric->entry, 'field_id'))->toBe($fields->pluck('id')->all())
        ->and(array_column($test->rubric->entry, 'answer'))->toBe(['Paris', '4, 2', 'true', 'Jupiter', 'yes'])
        ->and(array_column($test->rubric->entry, 'type'))->toBe(['Radio', 'Select Multiple', 'Checkbox', 'Select', 'Text Input']);
});

test('create_test_step validates questions before writing anything', function (array $question, string $error) {
    $this->actingAs(contentToolsUser());

    LmsServer::tool(CreateTestStep::class, [
        'lesson_id' => contentToolsLesson()->id,
        'name' => 'Quiz',
        'test_name' => 'Broken quiz',
        'questions' => [contentToolsQuizQuestions()[0], $question],
    ])->assertHasErrors([$error]);

    expect(FilamentForm::query()->count())->toBe(0)
        ->and(FilamentFormField::query()->count())->toBe(0)
        ->and(FilamentFormUser::query()->count())->toBe(0)
        ->and(Test::query()->count())->toBe(0)
        ->and(Step::query()->count())->toBe(0);
})->with([
    'missing answer' => [['type' => 'radio', 'text' => 'Q', 'options' => ['A', 'B']], 'Question 2 is missing a valid correct answer.'],
    'answer not an option' => [['type' => 'select', 'text' => 'Q', 'options' => ['A', 'B'], 'correct_answer' => 'C'], 'Question 2 is missing a valid correct answer.'],
    'checkbox without answer' => [['type' => 'checkbox', 'text' => 'Q'], 'Question 2 is missing a valid correct answer.'],
    'multi answer outside options' => [['type' => 'select_multiple', 'text' => 'Q', 'options' => ['A', 'B'], 'correct_answers' => ['A', 'Z']], 'Question 2 is missing a valid correct answer.'],
    'too few options' => [['type' => 'radio', 'text' => 'Q', 'options' => ['A'], 'correct_answer' => 'A'], 'Question 2 needs at least two options.'],
    'duplicate options' => [['type' => 'radio', 'text' => 'Q', 'options' => ['A', 'A'], 'correct_answer' => 'A'], 'Question 2 options must be unique and not blank.'],
    'comma in multi option' => [['type' => 'select_multiple', 'text' => 'Q', 'options' => ['Paris, France', 'Rome'], 'correct_answers' => ['Rome']], 'cannot contain commas'],
    'options on text' => [['type' => 'text', 'text' => 'Q', 'options' => ['A', 'B'], 'correct_answer' => 'A'], 'does not take options'],
    'html in label' => [['type' => 'text', 'text' => '<script>alert(1)</script>', 'correct_answer' => 'x'], 'Question text cannot contain "<".'],
    'html in option' => [['type' => 'radio', 'text' => 'Q', 'options' => ['<img src=x onerror=alert(1)>', 'Safe'], 'correct_answer' => 'Safe'], 'Answer options cannot contain "<".'],
    'unsupported type' => [['type' => 'file_upload', 'text' => 'Q', 'correct_answer' => 'x'], 'questions.1.type'],
]);

test('a quiz created through mcp grades learner entries end to end', function () {
    $this->actingAs(contentToolsUser());
    $lesson = contentToolsLesson();

    LmsServer::tool(CreateTestStep::class, [
        'lesson_id' => $lesson->id,
        'name' => 'Quiz',
        'test_name' => 'Module quiz',
        'questions' => contentToolsQuizQuestions(),
    ])->assertOk();

    $step = Step::query()->firstOrFail();
    $test = Test::query()->with('form')->firstOrFail();

    $learner = TestUser::query()->create([
        'name' => 'Learner',
        'email' => 'learner@example.com',
        'password' => bcrypt('password'),
    ]);
    $this->actingAs($learner);

    // Form state uses option keys; the form builder stores the option labels.
    $perfect = contentToolsSubmitQuiz($test->form, [0 => '0', 1 => ['2', '0'], 2 => true, 3 => '1', 4 => 'yes']);

    expect($test->gradeEntry($perfect))->toBe(100.0);

    Livewire::component('view-graded-entry', ContentToolsStubComponent::class);

    Livewire::test(ContentToolsTestStep::class, ['step' => $step])
        ->call('entrySaved', $perfect)
        ->assertSet('testGrade', 100.0)
        ->assertSet('testPassed', true);

    expect(StepUser::query()->where('user_id', $learner->id)->where('step_id', $step->id)->value('completed_at'))->not->toBeNull();

    $other = TestUser::query()->create([
        'name' => 'Other learner',
        'email' => 'other-learner@example.com',
        'password' => bcrypt('password'),
    ]);
    $this->actingAs($other);

    $partial = contentToolsSubmitQuiz($test->form, [0 => '1', 1 => ['0'], 2 => false, 3 => '1', 4 => 'yes']);
    $graded = $test->gradedKeyValueEntry($partial);

    expect($test->gradeEntry($partial))->toBe(40.0)
        ->and($graded['What is the capital of France?']['correct'])->toBeFalse()
        ->and($graded['Which numbers are even?']['correct'])->toBeFalse()
        ->and($graded['The sky is blue.']['correct'])->toBeFalse()
        ->and($graded['Pick the largest planet.']['correct'])->toBeTrue();
});

test('get_test reads a quiz back with its answer key', function () {
    $this->actingAs(contentToolsUser());

    LmsServer::tool(CreateTestStep::class, [
        'lesson_id' => contentToolsLesson()->id,
        'name' => 'Quiz',
        'test_name' => 'Module quiz',
        'questions' => contentToolsQuizQuestions(),
    ])->assertOk();

    $test = Test::query()->firstOrFail();

    LmsServer::tool(GetTest::class, ['id' => $test->id])
        ->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('name', 'Module quiz')
                ->where('locked', true)
                ->where('has_rubric', true)
                ->where('questions.0.type', 'radio')
                ->where('questions.0.correct_answer', 'Paris')
                ->where('questions.1.type', 'select_multiple')
                ->where('questions.1.correct_answer', ['4', '2'])
                ->where('questions.2.correct_answer', true)
                ->where('questions.4.correct_answer', 'yes')
                ->etc();
        });

    LmsServer::tool(GetTest::class, ['id' => 9999])->assertHasErrors();
});

test('get_test reads a quiz whose form or rubric is missing', function () {
    $this->actingAs(contentToolsUser());

    $removedForm = FilamentForm::create([
        'name' => 'Removed form',
        'locked' => true,
    ]);
    $removedRubric = FilamentFormUser::create([
        'filament_form_id' => $removedForm->id,
        'user_id' => null,
        'entry' => [],
    ]);
    $orphaned = Test::create([
        'name' => 'Orphaned quiz',
        'filament_form_id' => $removedForm->id,
        'filament_form_user_id' => $removedRubric->id,
    ]);
    $removedForm->delete();
    $removedRubric->delete();

    LmsServer::tool(GetTest::class, ['id' => $orphaned->id])
        ->assertOk()
        ->assertStructuredContent(function ($json): void {
            $json->where('name', 'Orphaned quiz')
                ->where('locked', false)
                ->where('has_rubric', false)
                ->where('questions', [])
                ->etc();
        });

    $form = FilamentForm::create([
        'name' => 'Practice form',
        'locked' => true,
    ]);
    $field = FilamentFormField::create([
        'filament_form_id' => $form->id,
        'label' => 'What is 2 + 2?',
        'type' => 'RADIO',
        'required' => true,
        'order' => 1,
        'options' => ['3', '4'],
    ]);
    $pending = Test::create([
        'name' => 'Practice quiz',
        'filament_form_id' => $form->id,
    ]);
    $step = Step::factory()->create([
        'lesson_id' => contentToolsLesson()->id,
        'name' => 'Practice',
        'slug' => 'practice',
        'material_type' => 'test',
        'material_id' => $pending->id,
    ]);

    LmsServer::tool(GetTest::class, ['id' => $pending->id])
        ->assertOk()
        ->assertStructuredContent(function ($json) use ($form, $field, $step): void {
            $json->where('name', 'Practice quiz')
                ->where('filament_form_id', $form->id)
                ->where('locked', true)
                ->where('has_rubric', false)
                ->where('step_ids', [$step->id])
                ->where('questions', [[
                    'field_id' => $field->id,
                    'type' => 'radio',
                    'text' => 'What is 2 + 2?',
                    'options' => ['3', '4'],
                    'correct_answer' => null,
                ]])
                ->etc();
        });
});

test('get_test hides a quiz that belongs to another tenant', function () {
    $schema = Schema::connection((string) config('database.default'));

    foreach (['lms_courses', 'lms_lessons', 'lms_steps', 'lms_tests'] as $tableName) {
        if (! $schema->hasColumn($tableName, 'team_id')) {
            $schema->table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('team_id')->nullable();
            });
        }
    }

    if (! $schema->hasTable('teams')) {
        $schema->create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    config([
        'filament-lms.tenancy.enabled' => true,
        'filament-lms.tenancy.model' => TestTeam::class,
        'filament-lms.tenancy.relationship_name' => 'team',
        'filament-lms.tenancy.column' => 'team_id',
    ]);

    if (! Test::hasGlobalScope('filament_lms_tenancy')) {
        Test::addGlobalScope('filament_lms_tenancy', new TenantScope);
    }

    Test::creating(function (Test $test): void {
        if (empty($test->team_id) && Filament::getTenant() !== null) {
            $test->team_id = Filament::getTenant()->getKey();
        }
    });

    $this->actingAs(contentToolsUser());
    $teamA = TestTeam::query()->create(['name' => 'Team A']);
    $teamB = TestTeam::query()->create(['name' => 'Team B']);

    Filament::setTenant($teamA);

    LmsServer::tool(CreateTestStep::class, [
        'lesson_id' => contentToolsLesson()->id,
        'name' => 'Quiz',
        'test_name' => 'Secret quiz',
        'questions' => [contentToolsQuizQuestions()[0]],
    ])->assertOk();

    $test = Test::query()->withoutGlobalScopes()->where('name', 'Secret quiz')->firstOrFail();

    expect($test->team_id)->toBe($teamA->id);

    Filament::setTenant($teamB);

    LmsServer::tool(GetTest::class, ['id' => $test->id])->assertHasErrors();

    Filament::setTenant($teamA);

    LmsServer::tool(GetTest::class, ['id' => $test->id])->assertOk()->assertSee('Secret quiz');
});

test('delete_step soft deletes link materials and keeps shared ones', function () {
    $this->actingAs(contentToolsUser());
    $lesson = contentToolsLesson();

    LmsServer::tool(CreateLinkStep::class, [
        'lesson_id' => $lesson->id,
        'name' => 'Docs',
        'url' => 'https://laravel.com/docs',
    ])->assertOk();

    $step = Step::query()->firstOrFail();
    $shared = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'slug' => 'shared-link',
        'material_type' => 'link',
        'material_id' => $step->material_id,
    ]);

    LmsServer::tool(DeleteStep::class, ['id' => $step->id])->assertOk();

    expect(Link::query()->whereKey($shared->material_id)->exists())->toBeTrue();

    LmsServer::tool(DeleteStep::class, ['id' => $shared->id])->assertOk();

    expect(Link::query()->count())->toBe(0)
        ->and(Link::withTrashed()->count())->toBe(1);
});

test('delete_lesson removes an unused quiz and keeps one with learner entries', function () {
    $this->actingAs(contentToolsUser());
    $lesson = contentToolsLesson();

    foreach (['Unused quiz', 'Taken quiz'] as $name) {
        LmsServer::tool(CreateTestStep::class, [
            'lesson_id' => $lesson->id,
            'name' => $name,
            'test_name' => $name,
            'questions' => [contentToolsQuizQuestions()[0]],
        ])->assertOk();
    }

    $taken = Test::query()->where('name', 'Taken quiz')->firstOrFail();
    $learner = TestUser::query()->create(['name' => 'L', 'email' => 'l@example.com', 'password' => bcrypt('password')]);
    FilamentFormUser::create([
        'filament_form_id' => $taken->filament_form_id,
        'user_id' => $learner->id,
        'entry' => [['type' => 'Radio', 'field' => 'Q', 'answer' => 'Paris', 'field_id' => 1]],
    ]);

    LmsServer::tool(DeleteLesson::class, ['id' => $lesson->id])->assertOk();

    expect(Step::query()->count())->toBe(0)
        ->and(Test::query()->pluck('name')->all())->toBe(['Taken quiz'])
        ->and(FilamentForm::query()->pluck('name')->all())->toBe(['Taken quiz'])
        ->and(FilamentFormUser::query()->where('filament_form_id', $taken->filament_form_id)->count())->toBe(2);
});

test('new step tools write into the current tenant', function () {
    $schema = Schema::connection((string) config('database.default'));

    if (! $schema->hasTable('teams')) {
        $schema->create('teams', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    foreach (['lms_courses', 'lms_lessons', 'lms_steps', 'lms_links'] as $tableName) {
        if (! $schema->hasColumn($tableName, 'team_id')) {
            $schema->table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('team_id')->nullable();
            });
        }
    }

    config([
        'filament-lms.tenancy.enabled' => true,
        'filament-lms.tenancy.model' => TestTeam::class,
        'filament-lms.tenancy.relationship_name' => 'team',
        'filament-lms.tenancy.column' => 'team_id',
    ]);

    foreach ([Course::class, Lesson::class, Step::class, Link::class] as $model) {
        if (! $model::hasGlobalScope('filament_lms_tenancy')) {
            $model::addGlobalScope('filament_lms_tenancy', new TenantScope);
        }

        $model::creating(function ($instance): void {
            $column = TenantHelper::getTenantColumnName();

            if (empty($instance->{$column}) && Filament::getTenant() !== null) {
                $instance->{$column} = Filament::getTenant()->getKey();
            }
        });
    }

    $this->actingAs(contentToolsUser());
    $teamA = TestTeam::query()->create(['name' => 'Team A']);
    $teamB = TestTeam::query()->create(['name' => 'Team B']);

    Filament::setTenant($teamA);
    $lesson = contentToolsLesson();

    LmsServer::tool(CreateTextStep::class, ['lesson_id' => $lesson->id, 'name' => 'Text', 'text' => 'Hi'])->assertOk();
    LmsServer::tool(CreateLinkStep::class, ['lesson_id' => $lesson->id, 'name' => 'Link', 'url' => 'https://laravel.com'])->assertOk();

    expect(Step::query()->withoutGlobalScopes()->pluck('team_id')->unique()->all())->toBe([$teamA->id])
        ->and(Link::query()->withoutGlobalScopes()->value('team_id'))->toBe($teamA->id);

    Filament::setTenant($teamB);

    LmsServer::tool(CreateTextStep::class, ['lesson_id' => $lesson->id, 'name' => 'Other', 'text' => 'Hi'])
        ->assertHasErrors();

    expect(Step::query()->withoutGlobalScopes()->where('name', 'Other')->exists())->toBeFalse();
});
