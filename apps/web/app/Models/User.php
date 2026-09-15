<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property Carbon|null $email_verified_at
 * @property bool $review_reminders_enabled
 * @property Carbon|null $review_reminder_sent_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'review_reminders_enabled'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'email_verified_at' => 'datetime',
            'review_reminders_enabled' => 'boolean',
            'review_reminder_sent_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasOne<Profile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * @return HasMany<NodeAttempt, $this>
     */
    public function nodeAttempts(): HasMany
    {
        return $this->hasMany(NodeAttempt::class);
    }

    /**
     * @return HasMany<Achievement, $this>
     */
    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    /**
     * Freischaltungen des generischen Achievement-Systems (App\Achievements).
     * Bewusst nicht "achievements" genannt -- das meint bereits die aeltere
     * First-Blood-Tabelle oben.
     *
     * @return HasMany<AchievementUnlock, $this>
     */
    public function achievementUnlocks(): HasMany
    {
        return $this->hasMany(AchievementUnlock::class);
    }

    /**
     * Aktivitaeten, bei denen dieser Nutzer als Autor eingetragen ist
     * (ADR 0071, W3) -- die echte Beziehung, gegen die ActivityPolicy
     * prueft, nicht die Freitextliste in activities.authors.
     *
     * @return BelongsToMany<Activity, $this>
     */
    public function authoredActivities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'activity_authors');
    }
}
