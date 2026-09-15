<?php

namespace App\Providers;

use App\Activities\AchievementCatalogActivity;
use App\Activities\ActivityRegistry;
use App\Activities\ActivityType;
use App\Activities\ExamActivity;
use App\Activities\LessonActivity;
use App\Activities\NodeActivity;
use App\Activities\SandboxActivity;
use App\Content\CacheInvalidatorContract;
use App\Content\ContentRepository;
use App\Content\HttpCacheInvalidator;
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
        // In der Testumgebung bewusst NullCacheInvalidator statt echter
        // HTTP-Aufrufe an drei (in Tests nie erreichbare) Dienste --
        // HttpCacheInvalidator selbst hat eine eigene Testabdeckung
        // (HttpCacheInvalidatorTest, mit Http::fake()).
        $this->app->singleton(
            CacheInvalidatorContract::class,
            fn ($app) => $app->environment('testing') ? new NullCacheInvalidator : new HttpCacheInvalidator,
        );

        $this->app->singleton(ActivityRegistry::class, function ($app) {
            $registry = new ActivityRegistry;

            // Quiz haengt an seiner Lektion und hat keinen eigenen
            // `activities`-Verzeichniseintrag -- siehe QuizActivity
            // Klassendoc. Spielwiese hat seit ADR 0096 (CMS-2b) einen
            // eigenen Eintrag (nur wenn die Lektion eine hat, siehe
            // ContentSync::syncLessons()), ist aber wie Quiz weiterhin
            // nicht versionable -- ihre Konfiguration bleibt Teil des
            // Lektions-content_versions-Datensatzes.
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
                ActivityType::Sandbox,
                fn (Activity $activity) => new SandboxActivity(
                    Lesson::where('lesson_id', $activity->key)->firstOrFail(),
                ),
            );
            $registry->register(
                ActivityType::Exam,
                fn (Activity $activity) => new ExamActivity(
                    Track::where('slug', $activity->key)->firstOrFail(),
                    $app->make(ContentRepository::class),
                ),
            );
            // Der Achievement-Katalog kennt nur eine Instanz (`key ===
            // 'catalog'`, ADR 0083) -- welcher einzelne Eintrag bearbeitet
            // wird, steht im Entwurf, nicht in der Aktivitaets-Identitaet.
            $registry->register(
                ActivityType::Achievement,
                fn (Activity $activity) => new AchievementCatalogActivity(
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
