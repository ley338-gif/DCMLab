<?php

namespace App\Providers;

use App\Activities\AchievementCatalogActivity;
use App\Activities\ActivityRegistry;
use App\Activities\ActivityType;
use App\Activities\ExamActivity;
use App\Activities\LabActivity;
use App\Activities\LessonActivity;
use App\Activities\NodeActivity;
use App\Activities\QuizActivity;
use App\Activities\SandboxActivity;
use App\Content\CacheInvalidatorContract;
use App\Content\ContentRepository;
use App\Content\HttpCacheInvalidator;
use App\Content\NullCacheInvalidator;
use App\Models\Activity;
use App\Models\Lab;
use App\Models\Lesson;
use App\Models\Node;
use App\Models\Track;
use App\Models\User;
use App\Models\UserRole;
use App\Services\EngineClient;
use App\Services\EngineClientContract;
use App\Services\SandboxClient;
use App\Services\SandboxClientContract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

            // Sandbox und Quiz haben seit ADR 0096/0105 (CMS-2b/CMS-6b)
            // beide einen eigenen `activities`-Eintrag (nur wenn die
            // Lektion tatsaechlich eine Spielwiese/ein Quiz hat, siehe
            // ContentSync::syncLessons()) -- fuer lesson_elements'
            // Activity-Referenz. Beide bleiben trotzdem nicht versionable
            // (ADR 0095): ihre Konfiguration haengt weiterhin am
            // Lektions-content_versions-Datensatz, siehe ihre jeweilige
            // Klassendoc.
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
                ActivityType::Quiz,
                fn (Activity $activity) => new QuizActivity(
                    Lesson::where('lesson_id', $activity->key)->firstOrFail(),
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
            // Der Achievement-Katalog kennt nur eine Instanz (`key ===
            // 'catalog'`, ADR 0083) -- welcher einzelne Eintrag bearbeitet
            // wird, steht im Entwurf, nicht in der Aktivitaets-Identitaet.
            $registry->register(
                ActivityType::Achievement,
                fn (Activity $activity) => new AchievementCatalogActivity(
                    $app->make(ContentRepository::class),
                ),
            );
            // Lab (CMS-8a, Entscheidung C) hat kein content/**-Dateipendant
            // -- braucht deshalb, anders als die anderen Typen hier, die
            // Activity-Zeile selbst (fuer LabAttempt::activity_id), nicht nur
            // ihren `key`.
            $registry->register(
                ActivityType::Lab,
                fn (Activity $activity) => new LabActivity(
                    $activity,
                    Lab::where('slug', $activity->key)->firstOrFail(),
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
        $this->configureGates();
    }

    /**
     * `studio.access` (ADR 0098, CMS-3a) ersetzt AuthorPanelControllers
     * bisheriges `abort_if($user->role === UserRole::Learner, 403)` --
     * dieselbe Regel, aber als Policy-Entscheidung statt als Rollen-If im
     * Controller. Ein benannter Gate statt einer Modell-Policy, weil es
     * keine einzelne Eloquent-Ressource gibt, die "der Autoren-/Studio-
     * Bereich als Ganzes" waere (analog zu ReviewQueueController, das
     * dafuer bewusst UserPolicy::viewAny wiederverwendet).
     */
    protected function configureGates(): void
    {
        Gate::define('studio.access', fn (User $user): bool => $user->role !== UserRole::Learner);
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
