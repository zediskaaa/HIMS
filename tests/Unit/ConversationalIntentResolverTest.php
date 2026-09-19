<?php

namespace Tests\Unit;

use App\Services\Ai\ConversationalIntentResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Intent accuracy for naturally phrased HIMS questions.
 *
 * The point of these is not that a particular keyword maps to a particular
 * intent, but that the readings a user can reasonably mean stay distinct:
 * "walang expiry", "malapit nang mag-expire" and "expired na" share the word
 * expiry and must still reach three different answers.
 */
class ConversationalIntentResolverTest extends TestCase
{
    private ConversationalIntentResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new ConversationalIntentResolver;
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function intentProvider(): array
    {
        return [
            // --- Items with no expiry date recorded ---
            'no expiry taglish' => ['Anong mga items ang walang expiry?', ConversationalIntentResolver::NO_EXPIRY],
            'no expiry english' => ['Which items have no expiration date?', ConversationalIntentResolver::NO_EXPIRY],
            'no expiry without' => ['Show me items without expiry', ConversationalIntentResolver::NO_EXPIRY],
            'no expiry hindi naexpire' => ['Anong gamot ang hindi nag-e-expire?', ConversationalIntentResolver::NO_EXPIRY],
            'no expiry dont expire' => ["Which supplies don't expire?", ConversationalIntentResolver::NO_EXPIRY],
            'no expiry non expiring' => ['List non-expiring inventory items', ConversationalIntentResolver::NO_EXPIRY],
            'no expiry walang expiry date' => ['May item ba na walang expiry date?', ConversationalIntentResolver::NO_EXPIRY],
            'no expiry no shelf life' => ['Which items have no shelf life?', ConversationalIntentResolver::NO_EXPIRY],

            // --- Batches nearing expiry ---
            'expiring soon taglish' => ['Anong mga gamot ang malapit nang mag-expire?', ConversationalIntentResolver::EXPIRY],
            'expiring soon english' => ['Which batches are expiring soon?', ConversationalIntentResolver::EXPIRY],
            'expiring this month' => ['Anong items ang mag-e-expire this month?', ConversationalIntentResolver::EXPIRY],
            'expiring within 30 days' => ['What is expiring within 30 days?', ConversationalIntentResolver::EXPIRY],
            'expiring within 90 days' => ['Show me batches expiring within 90 days', ConversationalIntentResolver::EXPIRY],
            'expiring fefo' => ['Which items are nearing expiration for FEFO?', ConversationalIntentResolver::EXPIRY],

            // --- Batches already expired ---
            'expired taglish' => ['May expired stock ba?', ConversationalIntentResolver::EXPIRED],
            'expired na' => ['Anong mga items ang expired na?', ConversationalIntentResolver::EXPIRED],
            'expired already' => ['Which batches are already expired?', ConversationalIntentResolver::EXPIRED],
            'expired past' => ['Are there items past their expiry date?', ConversationalIntentResolver::EXPIRED],

            // --- Replenishment ---
            'replenish taglish orderin' => ['May item ba na need na talagang orderin?', ConversationalIntentResolver::REPLENISHMENT],
            'replenish paubos' => ['Ano yung mga paubos na?', ConversationalIntentResolver::REPLENISHMENT],
            'replenish english' => ['What should we reorder?', ConversationalIntentResolver::REPLENISHMENT],
            'replenish running low' => ['Which items are running low?', ConversationalIntentResolver::REPLENISHMENT],
            'replenish kulang' => ['May kulang ba sa stock natin?', ConversationalIntentResolver::REPLENISHMENT],
            // "What should we watch out for?" is answered with the attention
            // digest — the items needing action — rather than a bare list.
            'replenish bantayan' => ['Ano yung dapat bantayan?', ConversationalIntentResolver::ATTENTION],

            // --- Out of stock ---
            'out of stock english' => ['What items are out of stock?', ConversationalIntentResolver::OUT_OF_STOCK],
            'out of stock taglish' => ['May mga ubos na ba tayong item?', ConversationalIntentResolver::OUT_OF_STOCK],

            // --- Suppliers ---
            'supplier who' => ['Who is the supplier of Paracetamol?', ConversationalIntentResolver::SUPPLIER],
            'supplier sino' => ['Sino ang supplier ng amoxicillin?', ConversationalIntentResolver::SUPPLIER],
            'supplier kanino galing' => ['Kanino galing yung latex gloves?', ConversationalIntentResolver::SUPPLIER],

            // --- Movements, procurement, requisitions, shipments, valuation ---
            'movements english' => ['Show me the stock movements.', ConversationalIntentResolver::MOVEMENTS],
            'movements taglish' => ['Ano nangyari sa stock kahapon?', ConversationalIntentResolver::MOVEMENTS],
            'procurement' => ['Kamusta procurement natin?', ConversationalIntentResolver::PROCUREMENT],
            'requisitions' => ['May pending requisition ba?', ConversationalIntentResolver::REQUISITIONS],
            'shipments' => ['Nasaan yung delivery?', ConversationalIntentResolver::SHIPMENTS],
            'shipments delayed' => ['May delayed ba na shipment?', ConversationalIntentResolver::SHIPMENTS],
            'valuation' => ['Magkano ang total inventory natin?', ConversationalIntentResolver::VALUATION],
            // "Which will run out first?" is answered from the replenishment
            // set: those are the items HIMS has flagged as heading for a
            // stockout, with the quantities already calculated for them.
            'run out soon taglish' => ['Alin yung mabilis maubos?', ConversationalIntentResolver::REPLENISHMENT],
            'forecast english' => ['Show me the demand forecast.', ConversationalIntentResolver::FORECAST],
            'forecast taglish' => ['Ano ang projected demand sa susunod na buwan?', ConversationalIntentResolver::FORECAST],

            // --- Summary ---
            'summary english' => ['Give me a summary of our inventory.', ConversationalIntentResolver::SUMMARY],
            'summary taglish' => ['Kamusta ang inventory natin ngayon?', ConversationalIntentResolver::SUMMARY],

            // --- Definitional ---
            'definition fefo' => ['Ano ang FEFO?', ConversationalIntentResolver::DEFINITION],
            'definition reorder level' => ['What is the meaning of reorder level?', ConversationalIntentResolver::DEFINITION],

            // --- Capability questions: what can this assistant answer? ---
            'capabilities taglish itanong' => ['Ano yung mga puwede kong itanong?', ConversationalIntentResolver::CAPABILITIES],
            'capabilities taglish pwede' => ['Anong pwede kong itanong sayo?', ConversationalIntentResolver::CAPABILITIES],
            'capabilities taglish sakop' => ['Anong sakop mo?', ConversationalIntentResolver::CAPABILITIES],
            'capabilities english answer' => ['What can you answer?', ConversationalIntentResolver::CAPABILITIES],
            'capabilities english do' => ['What can you do for me?', ConversationalIntentResolver::CAPABILITIES],

            // --- Out of scope ---
            'out of scope arithmetic' => ['What is 1+1?', ConversationalIntentResolver::OUT_OF_SCOPE],
            'out of scope geography' => ['What is the capital of France?', ConversationalIntentResolver::OUT_OF_SCOPE],
            'out of scope poem' => ['Write me a poem about the sea.', ConversationalIntentResolver::OUT_OF_SCOPE],
            'out of scope programming' => ['What is Python?', ConversationalIntentResolver::OUT_OF_SCOPE],
            'out of scope weather' => ['What is the weather today?', ConversationalIntentResolver::OUT_OF_SCOPE],

            // --- Vague but on-topic ---
            'clarify vague' => ['May problema ba?', ConversationalIntentResolver::CLARIFY],
        ];
    }

    #[DataProvider('intentProvider')]
    public function test_intent_is_resolved_from_natural_phrasing(string $message, string $expected): void
    {
        $this->assertSame(
            $expected,
            $this->resolver->resolve($message)['intent'],
            "Failed to resolve intent for: {$message}"
        );
    }

    /**
     * The three expiry readings share a word and nothing else. Answering one
     * with another's dataset is the defect these keep closed.
     */
    public function test_the_three_expiry_readings_stay_distinct(): void
    {
        $noExpiry = $this->resolver->resolve('Anong mga items ang walang expiry?')['intent'];
        $expiring = $this->resolver->resolve('Anong mga items ang malapit nang mag-expire?')['intent'];
        $expired = $this->resolver->resolve('Anong mga items ang expired na?')['intent'];

        $this->assertSame(ConversationalIntentResolver::NO_EXPIRY, $noExpiry);
        $this->assertSame(ConversationalIntentResolver::EXPIRY, $expiring);
        $this->assertSame(ConversationalIntentResolver::EXPIRED, $expired);
        $this->assertCount(3, array_unique([$noExpiry, $expiring, $expired]));
    }

    public function test_hims_scope_and_intent_are_decided_independently(): void
    {
        // In scope, and a specific intent.
        $inScope = $this->resolver->resolve('Anong mga items ang walang expiry?');
        $this->assertTrue($inScope['in_hims_scope']);
        $this->assertSame(ConversationalIntentResolver::NO_EXPIRY, $inScope['intent']);

        // Out of scope, and a different intent outcome.
        $outOfScope = $this->resolver->resolve('What is the capital of France?');
        $this->assertFalse($outOfScope['in_hims_scope']);
        $this->assertSame(ConversationalIntentResolver::OUT_OF_SCOPE, $outOfScope['intent']);

        // Ambiguous, but plainly a question about this system rather than
        // about something else: a clarification, not a refusal and not an
        // unrelated summary.
        $vague = $this->resolver->resolve('May problema ba?');
        $this->assertSame(ConversationalIntentResolver::CLARIFY, $vague['intent']);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function expiryWindowProvider(): array
    {
        return [
            'within 30 days' => ['Anong items ang mag-e-expire within 30 days?', 30],
            'within 60 days' => ['What is expiring within 60 days?', 60],
            'within 90 days' => ['Show me batches expiring within 90 days', 90],
            'within one year' => ['Which items expire within a year?', 365],
            'bare 45 days' => ['What expires in 45 days?', 45],
        ];
    }

    #[DataProvider('expiryWindowProvider')]
    public function test_stated_expiry_window_is_carried_through(string $message, int $expectedDays): void
    {
        $resolution = $this->resolver->resolve($message);

        $this->assertSame(ConversationalIntentResolver::EXPIRY, $resolution['intent']);
        $this->assertSame($expectedDays, $resolution['days_ahead']);
    }

    public function test_expiry_window_is_calibrated_to_the_end_of_the_current_month(): void
    {
        $this->travelTo(now()->startOfMonth());

        $days = $this->resolver->resolve('Anong items ang mag-e-expire this month?')['days_ahead'];

        $expected = (int) now()->startOfDay()->diffInDays(now()->endOfMonth()->startOfDay());

        $this->assertSame($expected, $days);
        $this->assertGreaterThanOrEqual(1, $days);
    }

    public function test_no_window_is_stated_when_the_question_names_none(): void
    {
        // Callers apply their own default here rather than being handed a
        // window the user never asked for.
        $this->assertNull($this->resolver->resolve('Anong mga gamot ang malapit nang mag-expire?')['days_ahead']);
    }

    /**
     * Asking what a word means is not the same as asking for data about it.
     */
    public function test_definitional_and_data_questions_are_told_apart(): void
    {
        $this->assertSame(
            ConversationalIntentResolver::DEFINITION,
            $this->resolver->resolve('Ano ang ibig sabihin ng reorder level?')['intent']
        );

        $this->assertSame(
            ConversationalIntentResolver::REPLENISHMENT,
            $this->resolver->resolve('What is the reorder level for the N95 mask?')['intent']
        );
    }

    /**
     * A capability question is about the assistant, not about stock, but it is
     * still a question the assistant must answer — declining it reads as "I
     * cannot help you" to a user who asked how to use the system.
     */
    public function test_capability_questions_are_not_treated_as_out_of_scope(): void
    {
        foreach (['Ano yung mga puwede kong itanong?', 'Anong pwede mong sagutin?', 'What can you do?'] as $phrasing) {
            $resolution = $this->resolver->resolve($phrasing);

            $this->assertSame(ConversationalIntentResolver::CAPABILITIES, $resolution['intent'], "Not read as a capability question: {$phrasing}");
            $this->assertNotSame(ConversationalIntentResolver::OUT_OF_SCOPE, $resolution['intent']);
        }
    }

    /**
     * "Pwede" alone is not a capability cue: what the user may order is a
     * replenishment question, not a question about the assistant.
     */
    public function test_asking_what_can_be_ordered_stays_a_replenishment_question(): void
    {
        $this->assertSame(
            ConversationalIntentResolver::REPLENISHMENT,
            $this->resolver->resolve('Ano ang pwede kong i-order?')['intent']
        );
    }

    /**
     * Natural Filipino, English and Taglish questions about the system are HIMS
     * questions. None of them needs technical wording, and none is turned away.
     */
    public function test_casual_hims_phrasings_are_never_treated_as_out_of_scope(): void
    {
        $phrasings = [
            'Ano yung paubos?',
            'May kailangan bang orderin?',
            'Sino supplier nito?',
            'May pending delivery?',
            'Magkano inventory natin?',
            'Ano nangyari sa stock?',
            'May kulang ba?',
            'Alin yung mabilis maubos?',
            'Ano yung dapat bantayan?',
            'May issue ba sa inventory?',
            'Kamusta procurement?',
            'Nasaan yung delivery?',
            'Show me the stock movements.',
        ];

        foreach ($phrasings as $phrasing) {
            $resolution = $this->resolver->resolve($phrasing);

            $this->assertNotSame(
                ConversationalIntentResolver::OUT_OF_SCOPE,
                $resolution['intent'],
                "Casual HIMS question was treated as out of scope: {$phrasing}"
            );
            $this->assertTrue($resolution['in_hims_scope'], "Not recognised as HIMS scope: {$phrasing}");
        }
    }

    /**
     * Greetings and pleasantries are recognized as conversational intents, not out of scope.
     */
    public function test_pure_greeting_intent_resolved(): void
    {
        $greetings = ['hi', 'hello', 'hey', 'good morning', 'good evening', 'magandang umaga', 'magandang gabi'];
        foreach ($greetings as $greeting) {
            $resolution = $this->resolver->resolve($greeting);
            $this->assertSame(
                ConversationalIntentResolver::GREETING,
                $resolution['intent'],
                "Failed resolving GREETING for: {$greeting}"
            );
            $this->assertTrue($resolution['in_hims_scope']);
        }
    }

    public function test_acknowledgment_intent_resolved(): void
    {
        $acknowledgments = ['thank you', 'thanks', 'salamat', 'salamat po', 'okay', 'noted', 'got it', 'sige'];
        foreach ($acknowledgments as $ack) {
            $resolution = $this->resolver->resolve($ack);
            $this->assertSame(
                ConversationalIntentResolver::ACKNOWLEDGMENT,
                $resolution['intent'],
                "Failed resolving ACKNOWLEDGMENT for: {$ack}"
            );
        }
    }

    public function test_farewell_intent_resolved(): void
    {
        $farewells = ['bye', 'goodbye', 'paalam', 'ingat', 'see you', 'Goodnight', 'good night', 'goodnight po', 'tulog na'];
        foreach ($farewells as $farewell) {
            $resolution = $this->resolver->resolve($farewell);
            $this->assertSame(
                ConversationalIntentResolver::FAREWELL,
                $resolution['intent'],
                "Failed resolving FAREWELL for: {$farewell}"
            );
            $this->assertTrue($resolution['in_hims_scope']);
        }
    }

    public function test_conversational_clarification_resolved(): void
    {
        $clarifications = ['what?', 'what', 'What?', 'ano?', 'ano', 'why?', 'huh?', 'ha?', 'bakit?', 'come again?', 'di ko gets'];
        foreach ($clarifications as $clarify) {
            $resolution = $this->resolver->resolve($clarify);
            $this->assertSame(
                ConversationalIntentResolver::CONVERSATIONAL_CLARIFY,
                $resolution['intent'],
                "Failed resolving CONVERSATIONAL_CLARIFY for: {$clarify}"
            );
            $this->assertTrue($resolution['in_hims_scope']);
        }
    }

    public function test_domain_capabilities_stay_in_hims_scope(): void
    {
        $capabilityQueries = [
            'Nasaan ang storage locations natin?' => true,
            'Check receiving reports and GRN' => true,
            'Sino ang may hawak ng chain of custody?' => true,
            'Show me stock alerts and notifications' => true,
            'What reports can we generate in HIMS?' => true,
            'List the active user accounts in HIMS' => true,
        ];

        foreach ($capabilityQueries as $query => $expectedInScope) {
            $resolution = $this->resolver->resolve($query);
            $this->assertSame(
                $expectedInScope,
                $resolution['in_hims_scope'],
                "Expected in_hims_scope={$expectedInScope} for query: {$query}"
            );
            $this->assertNotSame(ConversationalIntentResolver::OUT_OF_SCOPE, $resolution['intent']);
        }
    }

    public function test_how_are_you_intent_resolved(): void
    {
        $queries = ['how are you', 'how are you doing', 'kamusta ka', 'kumusta ka', 'kumusta po'];
        foreach ($queries as $q) {
            $resolution = $this->resolver->resolve($q);
            $this->assertSame(
                ConversationalIntentResolver::HOW_ARE_YOU,
                $resolution['intent'],
                "Failed resolving HOW_ARE_YOU for: {$q}"
            );
        }
    }

    public function test_combined_greeting_and_query_preserves_prefix_and_domain_intent(): void
    {
        $resolution = $this->resolver->resolve('Good evening, may low stock ba tayo?');
        $this->assertSame(ConversationalIntentResolver::REPLENISHMENT, $resolution['intent']);
        $this->assertSame('Good evening', $resolution['greeting_prefix']);
        $this->assertTrue($resolution['in_hims_scope']);

        $resolution2 = $this->resolver->resolve('Hi! May Zonrox ba tayo?');
        $this->assertSame(ConversationalIntentResolver::ITEM_LOOKUP, $resolution2['intent']);
        $this->assertSame('Hi', $resolution2['greeting_prefix']);
        $this->assertSame('Zonrox', $resolution2['candidate_item']);
    }

    public function test_conversational_continuation_and_delegation_resolved(): void
    {
        $delegations = [
            'ikaw bahala',
            'ikaw na bahala',
            'sige ikaw na',
            'bahala ka',
            'bahala ka na',
            'kayo bahala',
            'up to you',
            'your call',
            'you decide',
            'whatever you think is best',
            'go ahead',
            'proceed',
            'what\'s next?',
            'whats next',
            'ano susunod?',
            'anong susunod',
            'then what?',
            'what next?',
            'tuloy mo',
            'sige go',
        ];

        foreach ($delegations as $text) {
            $resolution = $this->resolver->resolve($text);
            $this->assertSame(
                ConversationalIntentResolver::CONVERSATIONAL_CONTINUE,
                $resolution['intent'],
                "Failed resolving CONVERSATIONAL_CONTINUE for: {$text}"
            );
            $this->assertTrue($resolution['in_hims_scope'], "Expected in_hims_scope for: {$text}");
            $this->assertNotSame(ConversationalIntentResolver::OUT_OF_SCOPE, $resolution['intent']);
        }
    }

    public function test_subjectless_followup_detection(): void
    {
        $followups = [
            'ilan?' => true,
            'ilan pa?' => true,
            'how many?' => true,
            'meron?' => true,
            'meron pa?' => true,
            'may stock pa?' => true,
            'sino?' => true,
            'who?' => true,
            'sino supplier?' => true,
            'kanino galing?' => true,
            'nasaan?' => true,
            'saan?' => true,
            'where is it?' => true,
            'kailan?' => true,
            'when?' => true,
            'alin?' => true,
            'which one?' => true,
            'yung una' => true,
            'the first one' => true,
            'talaga?' => true,
            'sure ka?' => true,
        ];

        foreach ($followups as $q => $expected) {
            $this->assertSame(
                $expected,
                $this->resolver->isSubjectlessFollowup($q),
                "Failed isSubjectlessFollowup for: {$q}"
            );
        }
    }
}

