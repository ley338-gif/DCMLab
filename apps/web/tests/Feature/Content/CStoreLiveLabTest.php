<?php

namespace Tests\Feature\Content;

use App\Activities\LabActivity;
use App\Content\ContentRepository;
use App\Models\Activity;
use App\Models\ActivityProgress;
use App\Models\Lab;
use App\Models\LabAttempt;
use App\Models\SandboxTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * PR #151: das erste echte, fact-verifizierte C-STORE-Lab
 * ("c-store-live", live in der Studio-/`content_versions`-Pipeline
 * angelegt und an Lektion 2.2 verlinkt -- siehe PR-Beschreibung).
 *
 * Ein Lab hat kein `content/**`-Dateipendant (siehe `LabActivity`-Klassendoc)
 * -- anders als `ContentValidateTest::test_real_lessons_1_1_and_1_5_pass_
 * validation()`, das echte Dateien aus `content/lessons/` liest, gibt es
 * hier keine Datei, gegen die dieser Test laufen koennte. Diese Klasse
 * uebernimmt stattdessen dieselbe Rolle als ausfuehrbare Spezifikation:
 * die exakte Definition, mit der `c-store-live` in der echten Dev-Datenbank
 * angelegt wurde (siehe PR-Beschreibung fuer den Tinker-Auszug, der sie
 * dort erzeugt hat), als Literal hier im Test -- haelt der Autor die reale
 * DB-Zeile je fuer noetig anders zu speichern, faellt dieser Test auf.
 */
class CStoreLiveLabTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'c-store-live';

    private const DATASET = 'ct-thorax-60';

    private const PATIENT_ID = '4711';

    private const MODALITY = 'CT';

    private const SOP_CLASS = '1.2.840.10008.5.1.4.1.1.2';

    private const MIN_INSTANCES = 60;

    private const LABEL = 'Vollständige CT-Studie übertragen';

    /**
     * @return array<string, mixed>
     */
    private static function assertionDefinition(): array
    {
        return [
            'type' => 'dicom_instance_received',
            'patient_id' => self::PATIENT_ID,
            'modality' => self::MODALITY,
            'sop_class' => self::SOP_CLASS,
            'min_instances' => self::MIN_INSTANCES,
            'label' => self::LABEL,
        ];
    }

    /**
     * Deckt Betreiber-Vorgabe (Abschnitt 10 des PR #151-Prompts) ab: nur
     * `dicom_instance_received`, kein `command_executed`, korrekte
     * PatientID/Modality/min_instances, veroeffentlichungsfaehig.
     */
    public function test_c_store_live_uses_only_dicom_instance_received_with_the_real_dataset_facts(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);

        $lab = Lab::factory()->create([
            'slug' => self::SLUG,
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => self::DATASET,
            'assertions' => [self::assertionDefinition()],
            'rich_content' => ['type' => 'doc', 'version' => 1, 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Test-Briefing.']]],
            ]],
        ]);
        $activityModel = Activity::factory()->create(['type' => 'lab', 'key' => self::SLUG]);

        $this->assertCount(1, $lab->assertions);
        $this->assertSame('dicom_instance_received', $lab->assertions[0]['type']);
        $this->assertSame(self::PATIENT_ID, $lab->assertions[0]['patient_id']);
        $this->assertSame(self::MODALITY, $lab->assertions[0]['modality']);
        $this->assertSame(self::MIN_INSTANCES, $lab->assertions[0]['min_instances']);
        $this->assertNotContains('command_executed', array_column($lab->assertions, 'type'));

        $activity = new LabActivity($activityModel, $lab, app(ContentRepository::class));
        $this->assertSame([], $activity->validate());
    }

    /**
     * End-to-End-Regressionstest fuer genau diese Lab-Definition, gegen
     * dieselben Uebergaenge, die der Live-Smoke-Test von PR #151 gegen den
     * echten Docker/Orthanc/Sandbox-Stack durchlaufen hat: leer -> knapp
     * unterhalb der vollstaendigen Studie -> eine zusaetzliche, aber
     * fremdpatientenbezogene Instanz zaehlt nicht mit -> vollstaendige
     * Studie (60 Instanzen) loest.
     */
    public function test_c_store_live_stays_open_below_the_full_study_and_solves_at_exactly_the_full_count(): void
    {
        SandboxTemplate::factory()->published()->create(['slug' => 'dicom-basic-tools']);
        Lab::factory()->create([
            'slug' => self::SLUG,
            'runtime_template' => 'dicom-basic-tools',
            'dataset' => self::DATASET,
            'points' => 20,
            'assertions' => [self::assertionDefinition()],
        ]);
        Activity::factory()->create(['type' => 'lab', 'key' => self::SLUG]);
        $user = User::factory()->create();

        Http::fake(['*/v1/sandboxes' => Http::response(['status' => 'running', 'sandbox_id' => 'sb-1'], 201)]);
        $this->actingAs($user)->post('/de/labs/'.self::SLUG.'/start');

        // Betreiber-Vorgabe: eine zusaetzliche Instanz mit falscher
        // PatientID darf trotz insgesamt 60 in Orthanc gespeicherten
        // Objekten NICHT mitzaehlen -- nur 59 davon erfuellen den Filter
        // (dritter Schritt unten).
        $withWrongPatient = [...$this->instances(59), ...$this->instances(1, 'WRONG-ID', 'w-')];

        // Ein einziges Http::fake() fuer die gesamte Sequenz -- ein
        // erneuter Http::fake()-Aufruf fuer dieselbe URL wuerde NUR einen
        // weiteren Stub-Callback ANHAENGEN (Factory::fake() -> merge()),
        // nicht den vorherigen ersetzen; Laravels Stub-Aufloesung nimmt den
        // ERSTEN passenden Callback in Registrierungsreihenfolge
        // (PendingRequest::buildStubHandler(): ->filter()->first()), ein
        // spaeterer Http::fake()-Aufruf fuer dieselbe URL haette also
        // schlicht NIE gegriffen.
        Http::fake([
            '*/v1/sandboxes/sb-1/exec' => Http::response(['stdout' => '', 'stderr' => '', 'exit_code' => 0]),
            '*/v1/sandboxes/sb-1/events' => Http::sequence()
                ->push(['exec' => [], 'orthanc' => ['new_instances' => []]])
                ->push(['exec' => [], 'orthanc' => ['new_instances' => $this->instances(59)]])
                ->push(['exec' => [], 'orthanc' => ['new_instances' => $withWrongPatient]])
                ->push(['exec' => [], 'orthanc' => ['new_instances' => $this->instances(60)]]),
        ]);

        $this->actingAs($user)
            ->postJson('/de/labs/'.self::SLUG.'/exec', ['command' => 'true'])
            ->assertJson(['all_satisfied' => false]);

        $this->actingAs($user)
            ->postJson('/de/labs/'.self::SLUG.'/exec', ['command' => 'storescu -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 daten/ct-thorax-60/'])
            ->assertJson(['all_satisfied' => false]);

        $this->actingAs($user)
            ->postJson('/de/labs/'.self::SLUG.'/exec', ['command' => 'storescu -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 /tmp/wrong-patient.dcm'])
            ->assertJson(['all_satisfied' => false]);

        $response = $this->actingAs($user)
            ->postJson('/de/labs/'.self::SLUG.'/exec', ['command' => 'storescu -aet MEINE-WS -aec ORTHANC 127.0.0.1 4242 daten/ct-thorax-60/instance-0060.dcm'])
            ->assertOk();

        $response->assertJson(['all_satisfied' => true]);

        $attempt = LabAttempt::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('solved', $attempt->status);

        $progress = ActivityProgress::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(20, $progress->score);
    }

    /**
     * PR #153 (Assertion-Label-/Progress-Audit): das Label wurde ueber den
     * echten Studio/ContentVersioning-Pfad gesetzt (nicht direkt im
     * Deployment-JSON editiert, siehe PR-Beschreibung) und danach per
     * `labs:export` neu in das committete Artefakt geschrieben -- dieser
     * Test liest exakt diese Datei, kein DB-Zustand.
     */
    public function test_the_committed_deployment_artifact_includes_the_assertion_label(): void
    {
        $path = dirname(__DIR__, 5).'/deploy/labs/'.self::SLUG.'.json';
        $this->assertFileExists($path, 'deploy/labs/c-store-live.json sollte im Repo committet sein');

        $artifact = json_decode(file_get_contents($path), true);

        $this->assertSame(self::LABEL, $artifact['lab']['assertions'][0]['label']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function instances(int $count, string $patientId = self::PATIENT_ID, string $prefix = 'inst-'): array
    {
        $instances = [];

        for ($i = 0; $i < $count; $i++) {
            $instances[] = [
                'instance_id' => $prefix.$i,
                'sop_class' => self::SOP_CLASS,
                'transfer_syntax' => '1.2.840.10008.1.2.1',
                'patient_id' => $patientId,
                'study_instance_uid' => '1.2.3.4.5',
                'modality' => self::MODALITY,
            ];
        }

        return $instances;
    }
}
