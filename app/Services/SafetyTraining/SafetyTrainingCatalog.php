<?php

namespace App\Services\SafetyTraining;

/**
 * Static V6 §7.7 module + §7.6 exam content. The catalog never hits the
 * database — content lives here so it ships with the deploy and the FE can
 * fall back to backend copy if its local catalog gets out of sync.
 *
 * IMPORTANT — exam grading: the correct-answer key is private to this
 * class (`EXAM_ANSWER_KEY`) and never returned by `examQuestions()`. The
 * Safety Training service grades server-side via `gradeExam()` — clients
 * never see the key.
 */
class SafetyTrainingCatalog
{
    public const MODULE_HYDROGEN = 'hydrogen';
    public const MODULE_SITE_RULES = 'site-rules';
    public const MODULE_PPE = 'ppe';
    public const MODULE_EMERGENCY = 'emergency';

    public const ORDERED_MODULE_IDS = [
        self::MODULE_HYDROGEN,
        self::MODULE_SITE_RULES,
        self::MODULE_PPE,
        self::MODULE_EMERGENCY,
    ];

    public const PASS_THRESHOLD = 4;
    public const EXAM_TOTAL = 5;

    /** V6 §16.4 answer key — NEVER leak this through any response. */
    private const EXAM_ANSWER_KEY = [
        'q1' => 'b',
        'q2' => 'c',
        'q3' => 'b',
        'q4' => 'b',
        'q5' => 'c',
    ];

    /**
     * The 4 modules in fixed order. Each is a flat array — no nested
     * DB-level state. Frontend can read this verbatim.
     */
    public static function modules(): array
    {
        return [
            self::moduleHydrogen(),
            self::moduleSiteRules(),
            self::modulePpe(),
            self::moduleEmergency(),
        ];
    }

    public static function module(string $id): ?array
    {
        foreach (self::modules() as $m) {
            if ($m['id'] === $id) {
                return $m;
            }
        }
        return null;
    }

    public static function moduleIds(): array
    {
        return self::ORDERED_MODULE_IDS;
    }

    public static function isValidModuleId(string $id): bool
    {
        return in_array($id, self::ORDERED_MODULE_IDS, true);
    }

    /**
     * 5 exam questions. Correct-answer marker is stripped unless
     * $withAnswers is true (kept internal for grading only).
     */
    public static function examQuestions(bool $withAnswers = false): array
    {
        $questions = [
            [
                'id' => 'q1',
                'prompt' => 'What is the maximum speed limit inside the hydrogen facility?',
                'options' => [
                    ['key' => 'a', 'label' => '20 km/h on the main yard, 10 km/h near bay lines'],
                    ['key' => 'b', 'label' => '10 km/h, everywhere on site'],
                    ['key' => 'c', 'label' => 'Whatever the driver feels is safe'],
                    ['key' => 'd', 'label' => '30 km/h if no one is walking nearby'],
                ],
            ],
            [
                'id' => 'q2',
                'prompt' => 'A hydrogen flame is best described as…',
                'options' => [
                    ['key' => 'a', 'label' => 'Bright blue and easy to see'],
                    ['key' => 'b', 'label' => 'Yellow with thick smoke'],
                    ['key' => 'c', 'label' => 'Almost invisible in daylight'],
                    ['key' => 'd', 'label' => 'Pink-orange, like a propane flame'],
                ],
            ],
            [
                'id' => 'q3',
                'prompt' => 'You hear a continuous siren and see flashing red beacons while at a bay line. You should…',
                'options' => [
                    ['key' => 'a', 'label' => 'Drive the trailer out of the area as fast as possible'],
                    ['key' => 'b', 'label' => 'Press the emergency stop, leave the trailer, walk upwind to the green assembly point.'],
                    ['key' => 'c', 'label' => 'Call your dispatcher and wait inside the cab for instructions'],
                    ['key' => 'd', 'label' => 'Run downwind so you can see what is happening'],
                ],
            ],
            [
                'id' => 'q4',
                'prompt' => 'Which combination of PPE is required before you step out of your cab in the loading area?',
                'options' => [
                    ['key' => 'a', 'label' => 'Sunglasses, baseball cap, normal work shoes'],
                    ['key' => 'b', 'label' => 'Hard hat, hi-vis vest, safety glasses, anti-static safety shoes'],
                    ['key' => 'c', 'label' => 'Only a hi-vis vest — the rest is optional'],
                    ['key' => 'd', 'label' => 'Whatever you wear on a normal customer site'],
                ],
            ],
            [
                'id' => 'q5',
                'prompt' => 'Mobile phones in the H₂ area are…',
                'options' => [
                    ['key' => 'a', 'label' => 'Allowed for short voice calls only'],
                    ['key' => 'b', 'label' => 'Allowed if the call is work-related'],
                    ['key' => 'c', 'label' => 'Forbidden — switch them off and use the operator phone or step outside the H₂ area'],
                    ['key' => 'd', 'label' => 'Allowed if the phone is in airplane mode'],
                ],
            ],
        ];

        if (! $withAnswers) {
            return $questions;
        }

        // Internal use only — merge the answer key. Never returned by the
        // controller; gradeExam() consumes this directly.
        return array_map(static function (array $q): array {
            $q['correct'] = self::EXAM_ANSWER_KEY[$q['id']] ?? null;
            return $q;
        }, $questions);
    }

    /**
     * Grade an array of {questionId, choice}. Unrecognised question ids
     * and missing choices are treated as wrong but never throw — the
     * service can submit even a partial answer set and get an honest
     * score.
     *
     * @return array{score:int,total:int,passed:bool}
     */
    public static function gradeExam(array $answers): array
    {
        // Normalize to a [questionId => choice] map for O(1) lookup.
        $given = [];
        foreach ($answers as $row) {
            if (! is_array($row)) {
                continue;
            }
            $qid = $row['questionId'] ?? $row['question_id'] ?? null;
            $choice = $row['choice'] ?? null;
            if (is_string($qid) && is_string($choice)) {
                $given[$qid] = strtolower(trim($choice));
            }
        }

        $score = 0;
        foreach (self::EXAM_ANSWER_KEY as $qid => $correct) {
            if (isset($given[$qid]) && $given[$qid] === $correct) {
                $score++;
            }
        }

        return [
            'score' => $score,
            'total' => self::EXAM_TOTAL,
            'passed' => $score >= self::PASS_THRESHOLD,
        ];
    }

    /* =====================================================================
     * Module bodies — V6 §7.7 verbatim where possible. Section prose
     * expands the spec's two-line summaries into the kiosk reader copy.
     * ===================================================================== */

    protected static function moduleHydrogen(): array
    {
        return [
            'id' => self::MODULE_HYDROGEN,
            'title' => 'Hydrogen at a glance',
            'summary' => 'What hydrogen is, why it behaves differently, and what that means for you.',
            'readingTime' => 3,
            'icon' => 'flame',
            'gridTitle' => 'Key properties of hydrogen (H₂)',
            'keyItems' => [
                ['key' => 'h2', 'label' => 'Lighter than air'],
                ['key' => 'flame', 'label' => 'Invisible flame'],
                ['key' => 'range', 'label' => 'Wide flammability range'],
                ['key' => 'no-odour', 'label' => 'No smell when it leaks'],
                ['key' => 'grounding', 'label' => 'Trailers must be grounded'],
                ['key' => 'speed', 'label' => 'Low ignition energy'],
            ],
            'keyFacts' => [
                ['title' => 'About 14× lighter than air', 'body' => 'Rises quickly — leaks travel upward, not toward the ground.'],
                ['title' => 'Flammable from 4 % to 77 %', 'body' => 'A very wide range — far wider than gasoline or natural gas.'],
                ['title' => 'Almost invisible in daylight', 'body' => 'You can be next to a hydrogen flame and not see it.'],
                ['title' => 'No smell, no colour', 'body' => 'A leak gives no warning — you cannot smell it like LPG.'],
            ],
            'sections' => [
                [
                    'heading' => 'What you are loading',
                    'body' => 'Hydrogen (H₂) is a compressed, flammable gas. It is colourless, odourless and lighter than air. We move it in tube trailers at high pressure (up to 350 bar), so the trailer itself is part of a pressure system.',
                ],
                [
                    'heading' => 'Why this changes your behaviour',
                    'body' => 'Because hydrogen is invisible and odourless, your senses cannot warn you. You rely on procedures: stay in marked zones, follow the operator’s instructions, never improvise around the loading station.',
                ],
                [
                    'heading' => 'Ignition sources you must control',
                    'body' => 'Hydrogen ignites with very little energy. Mobile phones, lighters, smoking, hot exhausts and static sparks are all potential ignitions. Keep them away from the loading area.',
                ],
                [
                    'heading' => 'Why grounding matters',
                    'body' => 'A static spark is enough to ignite a leak. The grounding cable equalises the trailer with the loading station so static cannot build up. Never disconnect the cable yourself — the operator owns that step.',
                ],
            ],
            'confirmations' => [
                'I understand that hydrogen is invisible and odourless when it leaks',
                'I will keep all ignition sources away from H₂ areas',
                'I will not disconnect a grounding cable myself',
            ],
            'warning' => [
                'title' => 'Loading may be stopped',
                'subtitle' => 'if grounding or safety procedures are not followed.',
            ],
        ];
    }

    protected static function moduleSiteRules(): array
    {
        return [
            'id' => self::MODULE_SITE_RULES,
            'title' => 'Site rules and speed limit',
            'summary' => 'How to drive, walk and behave on a hydrogen filling site.',
            'readingTime' => 3,
            'icon' => 'gauge',
            'gridTitle' => 'Driving and behaviour rules on site',
            'keyItems' => [
                ['key' => 'speed', 'label' => 'Max. 10 km/h on site'],
                ['key' => 'walking', 'label' => 'Stay on yellow paths'],
                ['key' => 'no-phone', 'label' => 'Phones off in H₂ area'],
                ['key' => 'no-smoking', 'label' => 'No smoking, no flames'],
                ['key' => 'grounding', 'label' => 'Grounding before loading'],
                ['key' => 'stop', 'label' => 'Stop on operator signal'],
            ],
            'keyFacts' => [
                ['title' => 'Speed limit', 'body' => '10 km/h everywhere on the site — no exceptions for empty yards.'],
                ['title' => 'Phones forbidden', 'body' => 'Switch them OFF (not silent) when you enter the H₂ area.'],
                ['title' => 'Smoking forbidden', 'body' => 'No cigarettes, vapes, lighters or open flames anywhere on site.'],
                ['title' => 'Yellow paths', 'body' => 'Walk only on the marked yellow paths between cab, terminal and bay line.'],
            ],
            'sections' => [
                [
                    'heading' => 'Speed limit',
                    'body' => 'The whole site is signed at 10 km/h. This applies the moment you pass the gate barrier and remains in force in every yard, including empty bays and parking. Drive at a walking pace near pedestrians.',
                ],
                [
                    'heading' => 'Where you may drive and walk',
                    'body' => 'You drive only on the marked roads. When out of the cab, you walk only on the yellow walkways. Do not cut across loading bays or trailer parking lanes — operators are not expecting you there.',
                ],
                [
                    'heading' => 'Mobile phones, radios and cameras',
                    'body' => 'Phones and personal radios must be switched off in the H₂ area. The kiosk and the operator phone are your communication channels while on site. Photography is not allowed without explicit permission.',
                ],
                [
                    'heading' => 'Things that get you sent off site',
                    'body' => 'Speeding, smoking, ignoring an operator signal, or entering a restricted area can lead to your access being revoked. Repeat offences are logged against the carrier as well as the driver.',
                ],
            ],
            'confirmations' => [
                'I will keep my speed at or below 10 km/h on site',
                'I will switch my phone off when entering the H₂ area',
                'I will follow operator signals and stop on request',
            ],
            'warning' => [
                'title' => 'Access may be revoked',
                'subtitle' => 'if speed, phone or smoking rules are broken.',
            ],
        ];
    }

    protected static function modulePpe(): array
    {
        return [
            'id' => self::MODULE_PPE,
            'title' => 'Personal Protective Equipment (PPE)',
            'summary' => 'What you must wear before you step out of the cab in the loading area.',
            'readingTime' => 2,
            'icon' => 'shield',
            'gridTitle' => 'Required PPE before entering H₂ areas',
            'keyItems' => [
                ['key' => 'helmet', 'label' => 'Hard hat with chin strap'],
                ['key' => 'boot', 'label' => 'Anti-static S3 shoes'],
                ['key' => 'vest', 'label' => 'Class 2+ hi-vis vest'],
                ['key' => 'gloves', 'label' => 'Work gloves'],
                ['key' => 'glasses', 'label' => 'Safety glasses'],
                ['key' => 'clothing', 'label' => 'Long sleeves & trousers'],
            ],
            'keyFacts' => [
                ['title' => 'Hard hat', 'body' => 'With chin strap — hard hats blow off in gusts otherwise.'],
                ['title' => 'Hi-vis', 'body' => 'EN ISO 20471 class 2 or higher. Bright yellow / orange.'],
                ['title' => 'Footwear', 'body' => 'Anti-static (ESD) S3 safety shoes — normal trainers are NOT acceptable.'],
                ['title' => 'Eye protection', 'body' => 'Safety glasses during loading, sampling and document handover at the bay line.'],
            ],
            'sections' => [
                [
                    'heading' => 'What you must wear before you leave your cab',
                    'body' => 'Hard hat (chin strap fastened), hi-vis vest, anti-static safety shoes, safety glasses. Long-sleeve work clothing is required — no shorts, no open shoes, no synthetic athleisure that builds static.',
                ],
                [
                    'heading' => 'Why anti-static shoes',
                    'body' => 'Normal soles can build a static charge as you walk. Anti-static (ESD) shoes drain that charge to ground so you cannot become an ignition source when you touch the trailer.',
                ],
                [
                    'heading' => 'Gloves and hearing protection',
                    'body' => 'Wear gloves whenever you handle hoses, valves or documents at the bay line. Hearing protection is required if signs at the bay line indicate it — read the signs at each station.',
                ],
                [
                    'heading' => 'If something is missing',
                    'body' => 'If you are short of PPE, do NOT enter the loading area. Speak to the operator at the kiosk — they will either lend you what is missing or send you back. Loading will not start until your PPE is complete.',
                ],
            ],
            'confirmations' => [
                'I understand PPE is required in marked areas',
                'I will follow local safety signs',
                'I will not enter restricted areas without permission',
            ],
            'warning' => [
                'title' => 'Access may be denied',
                'subtitle' => 'if required PPE is missing.',
            ],
        ];
    }

    protected static function moduleEmergency(): array
    {
        return [
            'id' => self::MODULE_EMERGENCY,
            'title' => 'Emergencies — what to do',
            'summary' => 'How to recognise a gas alarm and respond safely.',
            'readingTime' => 3,
            'icon' => 'alarm',
            'gridTitle' => 'Emergency response essentials',
            'keyItems' => [
                ['key' => 'alarm', 'label' => 'Continuous siren = gas alarm'],
                ['key' => 'evac', 'label' => 'Walk upwind to assembly'],
                ['key' => 'assembly', 'label' => 'Green assembly point'],
                ['key' => 'stop', 'label' => 'Press red ESD button'],
                ['key' => 'no-phone', 'label' => 'No engine start, no calls'],
                ['key' => 'walking', 'label' => 'Walk — never run'],
            ],
            'keyFacts' => [
                ['title' => 'Gas alarm', 'body' => 'Continuous siren AND flashing red beacons. Both together = evacuate.'],
                ['title' => 'Walk upwind', 'body' => 'Move into the wind so the gas cloud is behind you, never ahead.'],
                ['title' => 'Red ESD', 'body' => 'Every bay line has a red Emergency Shut-Down button — hit it if you see a leak or fire.'],
                ['title' => 'Help line', 'body' => 'Emergency line +49 9721 000-000 (to be confirmed at your site).'],
            ],
            'sections' => [
                [
                    'heading' => 'Recognising a gas alarm',
                    'body' => 'A real gas alarm is a continuous siren AND flashing red beacons together. A single short tone or an amber beacon is a test or a maintenance signal — keep working until an operator tells you otherwise.',
                ],
                [
                    'heading' => 'What to do — and what NOT to do',
                    'body' => 'Leave the trailer in place. Do NOT try to drive away — engine starts are themselves an ignition source. Walk upwind to the green assembly point, calmly. Never run. Do not use your phone. Wait for the operator headcount.',
                ],
                [
                    'heading' => 'The red emergency stop (ESD) button',
                    'body' => 'Every bay line has a clearly marked red Emergency Shut-Down button. Press it without hesitation if you see fire, a major leak, or someone hurt. The button isolates the bay line — better to stop a loading than risk an incident.',
                ],
                [
                    'heading' => 'After the incident',
                    'body' => 'Stay at the assembly point until the operator confirms you can leave. You will be asked what you saw and where you were — be honest and specific. The information helps everyone stay safer next time.',
                ],
            ],
            'confirmations' => [
                'I will leave the trailer in place during a gas alarm',
                'I will walk UPWIND to the green assembly point',
                'I will press the red ESD button if I see a leak or fire',
            ],
            'warning' => [
                'title' => 'Wrong response can hurt people',
                'subtitle' => 'starting an engine or running can ignite a leak.',
            ],
        ];
    }
}
