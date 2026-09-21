<?php

namespace App\Content;

use App\Models\Activity;
use App\Models\ContentVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Verwaltet den Lebenszyklus einer Aktivitaet ueber `content_versions`
 * (ADR 0071, W3): draft -> review -> published, sowie Rollback auf die
 * vorherige veroeffentlichte Version. Ersetzt die Git-Historie als
 * Aenderungsverlauf.
 *
 * Pro Aktivitaet ist ausserdem hoechstens eine `draft`/`review`-Version
 * gleichzeitig aktiv (Betreiber-Review nach #178): `createDraft()` und
 * `publish()` setzen dafuer jede andere offene `draft`/`review`-Version
 * derselben Aktivitaet atomar auf den Endzustand `superseded` -- weder
 * erneut einreichbar (`submitForReview()` verlangt Status `draft`) noch
 * veroeffentlichbar (`publish()` verlangt Status `review`), beide Pruefungen
 * bestanden bereits vor diesem Fix und brauchten keine Erweiterung.
 * `superseded` ist kein DB-Constraint, nur ein weiterer freier Wert in der
 * bestehenden `status`-Spalte (keine Migration noetig).
 *
 * Bewusst getrennt von ContentWriter (W2): eine Version zu veroeffentlichen
 * oder zurueckzusetzen ist hier reine Buchfuehrung ueber `content_versions`.
 * Das tatsaechliche Zurueckschreiben eines `payload` nach content/ setzt
 * voraus, dass ActivityContract::serialize() aus einem gespeicherten
 * payload rendert statt aus dem Ist-Zustand zu lesen (ADR 0073) -- das
 * liefert erst der Autoren-Editor (W6). Siehe ADR 0075 und
 * docs/offene-fragen.md.
 */
final class ContentVersioningService
{
    /**
     * Legt einen neuen Entwurf an und superseded dabei atomar jede andere
     * `draft`- oder `review`-Version derselben Aktivitaet (Betreiber-Review
     * nach #178/PR "content-version-supersession"): Vorher konnte
     * `storeDraft()` in jedem der fuenf Editoren (Lesson/Node/Quiz/Exam/
     * Achievement) beliebig oft aufgerufen werden, ohne dass ein aelterer,
     * noch offener Entwurf oder eine bereits eingereichte Review-Version
     * ungueltig wurde -- ein Reviewer haette Wochen spaeter versehentlich
     * eine laengst ueberholte Fassung veroeffentlichen koennen. Pro
     * Aktivitaet bleibt jetzt hoechstens eine `draft`/`review`-Version
     * gleichzeitig aktiv; alle aelteren wandern auf `superseded` (siehe
     * `publish()` fuer den spiegelbildlichen Fall beim Veroeffentlichen).
     * Bereits veroeffentlichte Versionen und Versionen anderer Aktivitaeten
     * bleiben unangetastet.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createDraft(Activity $activity, array $payload, User $author): ContentVersion
    {
        return DB::transaction(function () use ($activity, $payload, $author): ContentVersion {
            Activity::query()->whereKey($activity->id)->lockForUpdate()->firstOrFail();

            ContentVersion::query()
                ->where('activity_id', $activity->id)
                ->whereIn('status', ['draft', 'review'])
                ->update(['status' => 'superseded']);

            return ContentVersion::create([
                'activity_id' => $activity->id,
                'status' => 'draft',
                'payload' => $payload,
                'is_current' => false,
                'created_by' => $author->id,
            ]);
        });
    }

    /**
     * Betreiber-Review nach #179 (Stale-Model-Race): Die uebergebene
     * `$version` kann veraltet sein, wenn der Aufrufer sie geladen hat,
     * BEVOR eine parallele `createDraft()` sie superseded hat -- ein
     * frueher Check auf `$version->status` allein wuerde dann faelschlich
     * durchlaufen. Der massgebliche Check erfolgt deshalb erst INNERHALB
     * der Transaktion, HINTER dem Activity-Lock, gegen ein frisch (und
     * gesperrt) aus der DB geladenes Modell -- `createDraft()`,
     * `submitForReview()` und `publish()` serialisieren sich dadurch alle
     * ueber denselben Activity-Lock. Der Check oben bleibt als frueher
     * Fast-Fail fuer den haeufigen Fall (z. B. Doppelklick) erhalten, ist
     * aber nie die alleinige Pruefung.
     */
    public function submitForReview(ContentVersion $version): ContentVersion
    {
        if ($version->status !== 'draft') {
            throw new RuntimeException('Nur ein Entwurf kann zur Pruefung eingereicht werden.');
        }

        return DB::transaction(function () use ($version): ContentVersion {
            Activity::query()->whereKey($version->activity_id)->lockForUpdate()->firstOrFail();

            $current = ContentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            if ($current->status !== 'draft') {
                throw new RuntimeException('Nur ein Entwurf kann zur Pruefung eingereicht werden.');
            }

            $current->status = 'review';
            $current->save();

            return $current;
        });
    }

    /**
     * Derselbe Stale-Model-Race wie bei `submitForReview()` (Betreiber-Review
     * nach #179): Zwei `publish()`-Aufrufe fuer verschiedene Versionen
     * derselben Aktivitaet koennen beide ihre jeweilige `$version` mit
     * Status `review` geladen haben, bevor einer von beiden den Activity-
     * Lock erhaelt. Ohne einen massgeblichen Re-Check HINTER dem Lock
     * wuerde der zweite Aufruf die vom ersten bereits superseded Version
     * trotzdem noch veroeffentlichen und die frisch veroeffentlichte
     * wieder verdraengen. Der fruehe Check oben bleibt nur Fast-Fail.
     */
    public function publish(ContentVersion $version, User $reviewer): ContentVersion
    {
        if ($version->status !== 'review') {
            throw new RuntimeException('Nur eine Version im Review-Status kann veroeffentlicht werden.');
        }

        return DB::transaction(function () use ($version, $reviewer): ContentVersion {
            $activity = Activity::query()->whereKey($version->activity_id)->lockForUpdate()->firstOrFail();

            // Massgeblich: der Status IN DER DB, JETZT, hinter dem Lock --
            // nicht das moeglicherweise veraltete $version-Objekt des
            // Aufrufers (siehe Klassendoc-Ergaenzung oben).
            $current = ContentVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();

            if ($current->status !== 'review') {
                throw new RuntimeException('Nur eine Version im Review-Status kann veroeffentlicht werden.');
            }

            ContentVersion::query()
                ->where('activity_id', $activity->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            // Spiegelbild zu createDraft(): jede andere parallel entstandene
            // draft-/review-Version derselben Aktivitaet wird beim
            // Veroeffentlichen ungueltig, statt spaeter versehentlich
            // publizierbar zu bleiben (Betreiber-Review nach #178). Greift
            // auch fuer Versionen, die VOR diesem Fix bereits existierten.
            ContentVersion::query()
                ->where('activity_id', $activity->id)
                ->whereIn('status', ['draft', 'review'])
                ->where('id', '!=', $current->id)
                ->update(['status' => 'superseded']);

            $current->status = 'published';
            $current->is_current = true;
            $current->published_at = now();
            $current->reviewed_by = $reviewer->id;
            $current->save();

            return $current;
        });
    }

    /**
     * Setzt die aktuell gueltige Version auf die zuletzt davor
     * veroeffentlichte zurueck -- als NEUE, eigene Version mit demselben
     * `payload` (ADR 0102, CMS-5b): eine einmal veroeffentlichte Version
     * wird nie erneut mutiert (weder ihr `payload` noch ihr `status`), nur
     * `is_current` bewegt sich als reiner Zeiger auf die jeweils aktive
     * Version -- das aendert keine Historie rueckwirkend. Wirft, wenn es
     * keine aktuelle oder keine vorherige veroeffentlichte Version gibt.
     *
     * Bewusst weiterhin reine Buchfuehrung (siehe Klassendoc): das
     * tatsaechliche Zurueckschreiben des wiederhergestellten `payload` auf
     * die Lektion/Datei ist Aufgabe von ActivityContentApplier, sobald ein
     * Aufrufer (z. B. eine kuenftige Studio-"Wiederherstellen"-Aktion) beide
     * Schritte kombiniert -- siehe docs/offene-fragen.md.
     */
    public function rollback(Activity $activity, User $performedBy): ContentVersion
    {
        $current = $activity->contentVersions()->where('is_current', true)->first();

        if ($current === null) {
            throw new RuntimeException('Keine aktuelle veroeffentlichte Version zum Zuruecksetzen.');
        }

        $previous = $activity->contentVersions()
            ->where('status', 'published')
            ->where('id', '!=', $current->id)
            ->orderByDesc('published_at')
            ->first();

        if ($previous === null) {
            throw new RuntimeException('Keine vorherige veroeffentlichte Version vorhanden.');
        }

        return DB::transaction(function () use ($current, $previous, $performedBy): ContentVersion {
            $current->is_current = false;
            $current->save();

            return ContentVersion::create([
                'activity_id' => $previous->activity_id,
                'status' => 'published',
                'payload' => $previous->payload,
                'is_current' => true,
                'created_by' => $previous->created_by,
                'reviewed_by' => $performedBy->id,
                'published_at' => now(),
            ]);
        });
    }

    /**
     * PR #152 (Lab Content Deployment): eine Version, die direkt als
     * veroeffentlicht gilt, ohne den draft -> review -> publish-Dreischritt
     * zu durchlaufen. Ein automatisierter Deployment-Import hat keinen
     * separaten menschlichen Reviewer -- `publish()` einen zweiten,
     * vorgetaeuschten Review-Schritt durchlaufen zu lassen waere
     * irrefuehrend (siehe `LabDeploymentImporter`-Klassendoc fuer die volle
     * Begruendung). `$actor` ist bewusst sowohl `created_by` als auch
     * `reviewed_by` -- klar als EIN automatisierter Vorgang identifizierbar,
     * nicht als zwei verschiedene Personen. Dieselbe `is_current`-Umhaenge-
     * Logik wie `publish()`, in derselben Transaktion.
     *
     * @param  array<string, mixed>  $payload
     */
    public function publishSnapshot(Activity $activity, array $payload, User $actor): ContentVersion
    {
        return DB::transaction(function () use ($activity, $payload, $actor): ContentVersion {
            ContentVersion::query()
                ->where('activity_id', $activity->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            return ContentVersion::create([
                'activity_id' => $activity->id,
                'status' => 'published',
                'payload' => $payload,
                'is_current' => true,
                'created_by' => $actor->id,
                'reviewed_by' => $actor->id,
                'published_at' => now(),
            ]);
        });
    }

    /**
     * Aktivitaeten ohne jede Versionshistorie sind Bestandscontent von vor
     * W3 und bleiben sichtbar, wie sie es vorher waren (ADR 0075) -- diese
     * Methode veraendert deshalb fuer keine heute existierende Aktivitaet
     * das Ergebnis, bis ein Editor (W6) echte Versionen anlegt.
     */
    public function isPublished(Activity $activity): bool
    {
        if (! $activity->contentVersions()->exists()) {
            return true;
        }

        return $activity->contentVersions()
            ->where('is_current', true)
            ->where('status', 'published')
            ->exists();
    }
}
