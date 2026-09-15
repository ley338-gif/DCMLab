<?php

namespace App\Providers;

use App\Activities\ActivityRegistry;
use App\Activities\ActivityType;
use App\Activities\ExamActivity;
use App\Activities\LessonActivity;
use App\Activities\NodeActivity;
use App\Content\CacheInvalidatorContract;
use App\Content\ContentRepository;
use App\Content\NullCacheInvalidator;
use App\Models\Activity;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\Track;
use App\Services\EngineClient;
use App\Services\EngineClientContract;
use App\Services\SandboxClient;
use App\Services\SandboxClientContract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ContentRepository::class, fn () => new ContentRepository(config('content.path')));
        $this->app->singleton(EngineClientContract::class, EngineClient::class);
        $this->app->singleton(SandboxClientContract::class, SandboxClient::class);
        $this->app->singleton(CacheInvalidatorContract::class, NullCacheInvalidator::class);

        $this->app->singleton(ActivityRegistry::class, function ($app) {
            $registry = new ActivityRegistry;

            // Quiz und Spielwiese haengen an ihrer Lektion und haben keinen
            // eigenen `activities`-Verzeichniseintrag -- siehe
            // QuizActivity/SandboxActivity Klassendoc.
            $registry->register(
                ActivityType::Lesson,
                fn (Activity $activity) => new LessonActivity(
                    Lesson::where('lesson_id', $activity->key)->firstOrFail(),
                    $app->make(ContentRepository::class),
                ),
            );
            $registry->register(
                ActivityType::Node,
                fn (Activity $activity) => new NodeActivity(
                    Node::where('slug', $activity->key)->firstOrFail(),
                    $app->make(ContentRepository::class),
                ),
            );
            $registry->register(
                ActivityType::Exam,
                fn (Activity $activity) => new ExamActivity(
                    Track::where('slug', $activity->key)->firstOrFail(),
                    $app->make(ContentRepository::class),
                ),
            );

            return $registry;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
